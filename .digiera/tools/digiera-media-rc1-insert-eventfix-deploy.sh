#!/usr/bin/env bash
set -Eeuo pipefail

WEB02="${WEB02:-10.0.10.12}"
MOODLE="${MOODLE:-/var/www/moodle/public}"
TINY="$MOODLE/lib/editor/tiny/plugins/digieramedia"
REF="b9a54d768b9ac3550c9b6f94e531c5cbbeab2138"
RAW="https://raw.githubusercontent.com/Long2902/moodle/$REF/public/lib/editor/tiny/plugins/digieramedia"
TS="$(date +%Y%m%d-%H%M%S)"
TMP="/root/DIGIERA_MEDIA_INSERT_EVENTFIX_$TS"
REMOTE_TMP="/root/DIGIERA_MEDIA_INSERT_EVENTFIX_$TS"
SNAP1="/root/DIGIERA_MEDIA_TINY_PRE_INSERT_EVENTFIX_WEB01_$TS.tgz"
SNAP2="/root/DIGIERA_MEDIA_TINY_PRE_INSERT_EVENTFIX_WEB02_$TS.tgz"
CRON_WAS_ACTIVE=0
MAINTENANCE_ON=0

FILES=(
  "version.php"
  "templates/modal.mustache"
  "amd/src/modal.js"
  "amd/build/modal.min.js"
)

restore_service() {
  set +e
  if [[ "$MAINTENANCE_ON" == "1" ]]; then
    php "$MOODLE/admin/cli/maintenance.php" --disable >/dev/null 2>&1 || true
  fi
  if [[ "$CRON_WAS_ACTIVE" == "1" ]]; then
    systemctl start moodle-cron.timer >/dev/null 2>&1 || true
  fi
}

fail() {
  echo "INSERT_EVENTFIX_DEPLOY=FAIL"
  restore_service
}
trap fail ERR

install_payload() {
  local src="$1"
  local dst="$2"
  local f
  for f in "${FILES[@]}"; do
    install -D -m 0644 "$src/$f" "$dst/$f"
  done
}

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

mkdir -p "$TMP"

echo '===== 2. DOWNLOAD PINNED INSERT EVENT FIX ====='
for f in "${FILES[@]}"; do
  mkdir -p "$TMP/$(dirname "$f")"
  curl -fL "$RAW/$f" -o "$TMP/$f"
done

grep -Fq "core/modal_save_cancel" "$TMP/amd/src/modal.js"
grep -Fq "extends ModalSaveCancel" "$TMP/amd/src/modal.js"
grep -Fq 'data-action="save"' "$TMP/templates/modal.mustache"
grep -Fq 'data-region="status"' "$TMP/templates/modal.mustache"
grep -Fq '2026090801' "$TMP/version.php"
echo 'INSERT_EVENTFIX_SOURCE_CONTRACT=PASS'
(cd "$TMP" && sha256sum "${FILES[@]}")

echo '===== 3. SNAPSHOT TINY PLUGIN ON BOTH NODES ====='
tar -C "$(dirname "$TINY")" -czf "$SNAP1" "$(basename "$TINY")"
ssh -n root@"$WEB02" "tar -C '$(dirname "$TINY")' -czf '$SNAP2' '$(basename "$TINY")'"
echo "WEB01_SNAPSHOT=$SNAP1"
echo "WEB02_SNAPSHOT=$SNAP2"

echo '===== 4. STAGE VERIFIED PAYLOAD TO WEB02 ====='
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
php "$MOODLE/admin/cli/maintenance.php" --enable
MAINTENANCE_ON=1
echo 'MAINTENANCE=ON'

echo '===== 6. INSTALL WEB01 ====='
install_payload "$TMP" "$TINY"
grep -Fq "core/modal_save_cancel" "$TINY/amd/src/modal.js"
echo 'WEB01_INSERT_EVENT_FILES=PASS'

echo '===== 7. INSTALL WEB02 ====='
ssh -n root@"$WEB02" "mkdir -p '$TINY/templates' '$TINY/amd/src' '$TINY/amd/build'"
tar -C "$TMP" -cf - "${FILES[@]}" | ssh root@"$WEB02" "tar -C '$TINY' -xf -"
ssh -n root@"$WEB02" "grep -Fq 'core/modal_save_cancel' '$TINY/amd/src/modal.js'"
echo 'WEB02_INSERT_EVENT_FILES=PASS'

echo '===== 8. MOODLE UPGRADE ON WEB01 ONLY ====='
php "$MOODLE/admin/cli/upgrade.php" --non-interactive
DB_TINY_VERSION="$(php -r "define('CLI_SCRIPT', true); require '$MOODLE/config.php'; echo (string)\$DB->get_field('config_plugins','value',['plugin'=>'tiny_digieramedia','name'=>'version']);")"
[[ "$DB_TINY_VERSION" == "2026090801" ]]
echo "DB_TINY_VERSION=$DB_TINY_VERSION"
echo 'MOODLE_UPGRADE=PASS'

echo '===== 9. PURGE CACHES + RELOAD PHP-FPM ====='
php "$MOODLE/admin/cli/purge_caches.php"
reload_fpm_local
ssh -n root@"$WEB02" "svc=\$(systemctl list-units --type=service --all 'php*-fpm.service' --no-legend 2>/dev/null | awk 'NR==1{print \$1}'); if [ -n \"\$svc\" ]; then systemctl reload \"\$svc\"; fi"
echo 'CACHE_FPM_REFRESH=PASS'

echo '===== 10. VERIFY TWO-NODE PARITY ====='
LOCAL_SHA="$(cd "$TINY" && sha256sum "${FILES[@]}")"
REMOTE_SHA="$(ssh -n root@"$WEB02" "cd '$TINY' && sha256sum ${FILES[*]}")"
[[ "$LOCAL_SHA" == "$REMOTE_SHA" ]]
echo 'TWO_NODE_INSERT_EVENT_PARITY=PASS'

echo '===== 11. RETURN TO SERVICE ====='
php "$MOODLE/admin/cli/maintenance.php" --disable
MAINTENANCE_ON=0
if [[ "$CRON_WAS_ACTIVE" == "1" ]]; then
  systemctl start moodle-cron.timer
fi
echo 'MAINTENANCE=OFF'
echo "CRON_TIMER=$(systemctl is-active moodle-cron.timer 2>/dev/null || true)"
trap - ERR

echo 'INSERT_EVENTFIX_DEPLOY=PASS'
echo "PINNED_PRODUCT_COMMIT=$REF"
