#!/usr/bin/env bash
set -Eeuo pipefail

WEB02="${WEB02:-10.0.10.12}"
MOODLE="${MOODLE:-/var/www/moodle/public}"
MOODLE_USER="${MOODLE_USER:-www-data}"
REF="a6320632387860352e948ad1ebaad1e1add2c292"
RAW="https://raw.githubusercontent.com/Long2902/moodle/$REF/public"
TS="$(date +%Y%m%d-%H%M%S)"
TMP="/root/DIGIERA_MEDIA_REHYDRATE_METADATA_$TS"
REMOTE_TMP="/root/DIGIERA_MEDIA_REHYDRATE_METADATA_$TS"
SNAP1="/root/DIGIERA_MEDIA_PRE_REHYDRATE_WEB01_$TS.tgz"
SNAP2="/root/DIGIERA_MEDIA_PRE_REHYDRATE_WEB02_$TS.tgz"
CRON_WAS_ACTIVE=0
MAINTENANCE_ON=0
EXPECTED_LOCAL=2026090802
EXPECTED_TINY=2026090802

FILES=(
  "local/digieramedia/version.php"
  "local/digieramedia/db/services.php"
  "local/digieramedia/classes/external/resolve_references.php"
  "lib/editor/tiny/plugins/digieramedia/version.php"
  "lib/editor/tiny/plugins/digieramedia/amd/src/reference_component.js"
  "lib/editor/tiny/plugins/digieramedia/amd/build/reference_component.min.js"
)

moodle_cli_local() {
  runuser -u "$MOODLE_USER" -- bash -c "cd '$MOODLE' && php '$1' ${2:-}"
}

moodle_php_local() {
  local code="$1"
  runuser -u "$MOODLE_USER" -- bash -c "cd '$MOODLE' && php -r '$code'"
}

restore_service() {
  set +e
  if [[ "$MAINTENANCE_ON" == "1" ]]; then
    runuser -u "$MOODLE_USER" -- bash -c "cd '$MOODLE' && php '$MOODLE/admin/cli/maintenance.php' --disable" >/dev/null 2>&1 || true
  fi
  if [[ "$CRON_WAS_ACTIVE" == "1" ]]; then
    systemctl start moodle-cron.timer >/dev/null 2>&1 || true
  fi
}

fail() {
  local rc=$?
  echo "REHYDRATE_METADATA_DEPLOY=FAIL"
  restore_service
  exit "$rc"
}
trap fail ERR

reload_fpm_local() {
  local svc
  svc="$(systemctl list-units --type=service --all 'php*-fpm.service' --no-legend 2>/dev/null | awk 'NR==1{print $1}')"
  if [[ -n "$svc" ]]; then
    systemctl reload "$svc"
  fi
}

echo '===== 1. PRECHECK ====='
ssh -n -o BatchMode=yes -o ConnectTimeout=8 root@"$WEB02" 'true'
echo 'WEB02_SSH=PASS'

runuser -u "$MOODLE_USER" -- bash -c "cd '$MOODLE' && php -r 'define(\"CLI_SCRIPT\", true); require \"$MOODLE/config.php\"; echo \"WEB01_MOODLE_CLI=PASS DATAROOT=\".\$CFG->dataroot.PHP_EOL;'"
ssh -n root@"$WEB02" "runuser -u '$MOODLE_USER' -- bash -c 'cd \"$MOODLE\" && php -r '\''define(\"CLI_SCRIPT\", true); require \"$MOODLE/config.php\"; echo \"WEB02_MOODLE_CLI=PASS DATAROOT=\".\$CFG->dataroot.PHP_EOL;'\'''"
echo 'TWO_NODE_MOODLE_CLI=PASS'

mkdir -p "$TMP"

echo '===== 2. DOWNLOAD PINNED REHYDRATE PAYLOAD ====='
for f in "${FILES[@]}"; do
  mkdir -p "$TMP/$(dirname "$f")"
  curl -fL "$RAW/$f" -o "$TMP/$f"
done

grep -Fq 'local_digieramedia_resolve_references' "$TMP/local/digieramedia/db/services.php"
grep -Fq 'class resolve_references' "$TMP/local/digieramedia/classes/external/resolve_references.php"
grep -Fq 'hydrateReferenceLabels' "$TMP/lib/editor/tiny/plugins/digieramedia/amd/src/reference_component.js"
grep -Fq "name: 'Đang tải…'" "$TMP/lib/editor/tiny/plugins/digieramedia/amd/src/reference_component.js"
grep -Fq '2026090802' "$TMP/local/digieramedia/version.php"
grep -Fq '2026090802' "$TMP/lib/editor/tiny/plugins/digieramedia/version.php"
php -l "$TMP/local/digieramedia/version.php"
php -l "$TMP/local/digieramedia/db/services.php"
php -l "$TMP/local/digieramedia/classes/external/resolve_references.php"
php -l "$TMP/lib/editor/tiny/plugins/digieramedia/version.php"
node --check "$TMP/lib/editor/tiny/plugins/digieramedia/amd/src/reference_component.js"
node --check "$TMP/lib/editor/tiny/plugins/digieramedia/amd/build/reference_component.min.js"
echo 'REHYDRATE_SOURCE_CONTRACT=PASS'
(cd "$TMP" && sha256sum "${FILES[@]}")


echo '===== 3. SNAPSHOT CURRENT PLUGINS ====='
tar -C "$MOODLE" -czf "$SNAP1" local/digieramedia lib/editor/tiny/plugins/digieramedia
ssh -n root@"$WEB02" "tar -C '$MOODLE' -czf '$SNAP2' local/digieramedia lib/editor/tiny/plugins/digieramedia"
echo "WEB01_SNAPSHOT=$SNAP1"
echo "WEB02_SNAPSHOT=$SNAP2"


echo '===== 4. STAGE PAYLOAD TO WEB02 ====='
tar -C "$TMP" -cf - "${FILES[@]}" | ssh root@"$WEB02" "rm -rf '$REMOTE_TMP'; mkdir -p '$REMOTE_TMP'; tar -C '$REMOTE_TMP' -xf -"
LOCAL_STAGE_SHA="$(cd "$TMP" && sha256sum "${FILES[@]}")"
REMOTE_STAGE_SHA="$(ssh -n root@"$WEB02" "cd '$REMOTE_TMP' && sha256sum ${FILES[*]}")"
[[ "$LOCAL_STAGE_SHA" == "$REMOTE_STAGE_SHA" ]]
echo 'WEB02_STAGE_IDENTITY=PASS'


echo '===== 5. QUIESCE CRON + ENABLE MAINTENANCE ====='
if systemctl is-active --quiet moodle-cron.timer; then
  CRON_WAS_ACTIVE=1
  systemctl stop moodle-cron.timer
fi
runuser -u "$MOODLE_USER" -- bash -c "cd '$MOODLE' && php '$MOODLE/admin/cli/maintenance.php' --enable"
MAINTENANCE_ON=1
echo 'MAINTENANCE=ON'


echo '===== 6. INSTALL WEB01 ====='
for f in "${FILES[@]}"; do
  install -D -m 0644 "$TMP/$f" "$MOODLE/$f"
done
echo 'WEB01_REHYDRATE_FILES=PASS'


echo '===== 7. INSTALL WEB02 ====='
tar -C "$TMP" -cf - "${FILES[@]}" | ssh root@"$WEB02" "tar -C '$MOODLE' -xf -"
echo 'WEB02_REHYDRATE_FILES=PASS'


echo '===== 8. MOODLE UPGRADE AS WWW-DATA ====='
runuser -u "$MOODLE_USER" -- bash -c "cd '$MOODLE' && php '$MOODLE/admin/cli/upgrade.php' --non-interactive"
echo 'MOODLE_UPGRADE=PASS'

DB_LOCAL="$(runuser -u "$MOODLE_USER" -- bash -c "cd '$MOODLE' && php -r 'define(\"CLI_SCRIPT\", true); require \"$MOODLE/config.php\"; echo (string)\$DB->get_field(\"config_plugins\",\"value\",[\"plugin\"=>\"local_digieramedia\",\"name\"=>\"version\"]);'")"
DB_TINY="$(runuser -u "$MOODLE_USER" -- bash -c "cd '$MOODLE' && php -r 'define(\"CLI_SCRIPT\", true); require \"$MOODLE/config.php\"; echo (string)\$DB->get_field(\"config_plugins\",\"value\",[\"plugin\"=>\"tiny_digieramedia\",\"name\"=>\"version\"]);'")"
[[ "$DB_LOCAL" == "$EXPECTED_LOCAL" ]]
[[ "$DB_TINY" == "$EXPECTED_TINY" ]]
echo "DB_LOCAL_VERSION=$DB_LOCAL"
echo "DB_TINY_VERSION=$DB_TINY"
echo 'DB_VERSION=PASS'


echo '===== 9. PURGE CACHES + RELOAD FPM ====='
runuser -u "$MOODLE_USER" -- bash -c "cd '$MOODLE' && php '$MOODLE/admin/cli/purge_caches.php'"
ssh -n root@"$WEB02" "runuser -u '$MOODLE_USER' -- bash -c 'cd \"$MOODLE\" && php \"$MOODLE/admin/cli/purge_caches.php\"'"
reload_fpm_local
ssh -n root@"$WEB02" "svc=\$(systemctl list-units --type=service --all 'php*-fpm.service' --no-legend 2>/dev/null | awk 'NR==1{print \$1}'); if [ -n \"\$svc\" ]; then systemctl reload \"\$svc\"; fi"
echo 'CACHE_FPM_REFRESH=PASS'


echo '===== 10. VERIFY TWO-NODE PARITY ====='
LOCAL_SHA="$(cd "$MOODLE" && sha256sum "${FILES[@]}")"
REMOTE_SHA="$(ssh -n root@"$WEB02" "cd '$MOODLE' && sha256sum ${FILES[*]}")"
[[ "$LOCAL_SHA" == "$REMOTE_SHA" ]]
echo 'TWO_NODE_REHYDRATE_PARITY=PASS'


echo '===== 11. RETURN TO SERVICE ====='
runuser -u "$MOODLE_USER" -- bash -c "cd '$MOODLE' && php '$MOODLE/admin/cli/maintenance.php' --disable"
MAINTENANCE_ON=0
if [[ "$CRON_WAS_ACTIVE" == "1" ]]; then
  systemctl start moodle-cron.timer
fi
echo 'MAINTENANCE=OFF'
echo "CRON_TIMER=$(systemctl is-active moodle-cron.timer 2>/dev/null || true)"
trap - ERR

echo 'REHYDRATE_METADATA_DEPLOY=PASS'
echo "PINNED_PRODUCT_COMMIT=$REF"
