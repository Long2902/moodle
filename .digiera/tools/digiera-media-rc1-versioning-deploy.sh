#!/usr/bin/env bash
set -Eeuo pipefail
umask 022

WEB02="${WEB02:-10.0.10.12}"
MOODLE="${MOODLE:-/var/www/moodle/public}"
MOODLE_USER="${MOODLE_USER:-www-data}"
REF="156b601202746e7c567c0d09c1fbb5f280cdba0d"
RAW="https://raw.githubusercontent.com/Long2902/moodle/$REF/public"
TS="$(date +%Y%m%d-%H%M%S)"
TMP="/var/tmp/DIGIERA_MEDIA_VERSIONING_$TS"
REMOTE_TMP="$TMP"
SNAP1="/root/DIGIERA_MEDIA_PRE_VERSIONING_WEB01_$TS.tgz"
SNAP2="/root/DIGIERA_MEDIA_PRE_VERSIONING_WEB02_$TS.tgz"
EXPECTED_LOCAL=2026090804
EXPECTED_TINY=2026090804
CRON_WAS_ACTIVE=0
MAINTENANCE_ON=0

FILES=(
  "local/digieramedia/version.php"
  "local/digieramedia/db/install.xml"
  "local/digieramedia/db/upgrade.php"
  "local/digieramedia/db/services.php"
  "local/digieramedia/db/access.php"
  "local/digieramedia/classes/external/create_upload_session.php"
  "local/digieramedia/classes/external/get_media_versions.php"
  "local/digieramedia/classes/external/create_reference.php"
  "local/digieramedia/classes/external/search_media.php"
  "local/digieramedia/classes/service/upload_session_service.php"
  "local/digieramedia/classes/service/upload_finalize_service.php"
  "lib/editor/tiny/plugins/digieramedia/version.php"
  "lib/editor/tiny/plugins/digieramedia/templates/modal.mustache"
  "lib/editor/tiny/plugins/digieramedia/amd/src/ui.js"
  "lib/editor/tiny/plugins/digieramedia/amd/src/upload_client.js"
  "lib/editor/tiny/plugins/digieramedia/amd/build/ui.min.js"
  "lib/editor/tiny/plugins/digieramedia/amd/build/upload_client.min.js"
)

restore_service() {
  set +e
  if [[ "$MAINTENANCE_ON" == "1" ]]; then
    runuser -u "$MOODLE_USER" -- bash -c \
      "cd '$MOODLE' && php '$MOODLE/admin/cli/maintenance.php' --disable" >/dev/null 2>&1 || true
  fi
  if [[ "$CRON_WAS_ACTIVE" == "1" ]]; then
    systemctl start moodle-cron.timer >/dev/null 2>&1 || true
  fi
}

fail() {
  local rc=$?
  echo "DIGIERA_VERSIONING_DEPLOY=FAIL LINE=${BASH_LINENO[0]:-unknown}"
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

runuser -u "$MOODLE_USER" -- bash -c \
  "cd '$MOODLE' && php -r 'define(\"CLI_SCRIPT\", true); require \"$MOODLE/config.php\"; echo \"WEB01_MOODLE_CLI=PASS DATAROOT=\".\$CFG->dataroot.PHP_EOL;'"
ssh -n root@"$WEB02" \
  "runuser -u '$MOODLE_USER' -- bash -c 'cd \"$MOODLE\" && php -r '\''define(\"CLI_SCRIPT\", true); require \"$MOODLE/config.php\"; echo \"WEB02_MOODLE_CLI=PASS DATAROOT=\".\$CFG->dataroot.PHP_EOL;'\'''"
echo 'TWO_NODE_MOODLE_CLI=PASS'

runuser -u "$MOODLE_USER" -- test -r /etc/digiera/r2.php
ssh -n root@"$WEB02" "runuser -u '$MOODLE_USER' -- test -r /etc/digiera/r2.php"
echo 'TWO_NODE_R2_CONFIG_READABLE=PASS'

echo '===== 2. DOWNLOAD PINNED VERSIONING PAYLOAD ====='
rm -rf "$TMP"
mkdir -p "$TMP"
chmod 0755 "$TMP"
for f in "${FILES[@]}"; do
  mkdir -p "$TMP/$(dirname "$f")"
  curl -fL "$RAW/$f" -o "$TMP/$f"
done
find "$TMP" -type d -exec chmod 0755 {} +
find "$TMP" -type f -exec chmod 0644 {} +

echo '===== 3. SOURCE + RUNTIME CONTRACT ====='
grep -Fq '2026090804' "$TMP/local/digieramedia/version.php"
grep -Fq '2026090804' "$TMP/lib/editor/tiny/plugins/digieramedia/version.php"
grep -Fq 'targetmediaid' "$TMP/local/digieramedia/db/install.xml"
grep -Fq 'targetmediaid' "$TMP/local/digieramedia/db/upgrade.php"
grep -Fq 'local_digieramedia_get_media_versions' "$TMP/local/digieramedia/db/services.php"
grep -Fq 'class get_media_versions' "$TMP/local/digieramedia/classes/external/get_media_versions.php"
grep -Fq 'replacemediauuid' "$TMP/local/digieramedia/classes/service/upload_session_service.php"
grep -Fq 'currentversionid' "$TMP/local/digieramedia/classes/service/upload_finalize_service.php"
grep -Fq 'PINNED_VERSION' "$TMP/local/digieramedia/classes/external/create_reference.php"
grep -Fq 'FOLLOW_CURRENT' "$TMP/local/digieramedia/classes/external/create_reference.php"
grep -Fq 'Thay thế file' "$TMP/lib/editor/tiny/plugins/digieramedia/amd/src/ui.js"
grep -Fq 'Lịch sử phiên bản' "$TMP/lib/editor/tiny/plugins/digieramedia/amd/src/ui.js"
grep -Fq 'local_digieramedia_get_media_versions' "$TMP/lib/editor/tiny/plugins/digieramedia/amd/build/ui.min.js"
grep -Fq 'replacemediauuid' "$TMP/lib/editor/tiny/plugins/digieramedia/amd/build/upload_client.min.js"

find "$TMP/local/digieramedia" "$TMP/lib/editor/tiny/plugins/digieramedia" \
  -type f -name '*.php' -print0 | sort -z | xargs -0 -r -n1 php -l >/dev/null
node --check "$TMP/lib/editor/tiny/plugins/digieramedia/amd/src/ui.js"
node --check "$TMP/lib/editor/tiny/plugins/digieramedia/amd/src/upload_client.js"
node --check "$TMP/lib/editor/tiny/plugins/digieramedia/amd/build/ui.min.js"
node --check "$TMP/lib/editor/tiny/plugins/digieramedia/amd/build/upload_client.min.js"
echo 'VERSIONING_SOURCE_RUNTIME_CONTRACT=PASS'

LOCAL_STAGE_SHA="$(cd "$TMP" && sha256sum "${FILES[@]}")"
tar -C "$TMP" -cf - "${FILES[@]}" | \
  ssh root@"$WEB02" "rm -rf '$REMOTE_TMP'; mkdir -p '$REMOTE_TMP'; chmod 0755 '$REMOTE_TMP'; tar -C '$REMOTE_TMP' -xf -"
REMOTE_STAGE_SHA="$(ssh -n root@"$WEB02" "cd '$REMOTE_TMP' && sha256sum ${FILES[*]}")"
[[ "$LOCAL_STAGE_SHA" == "$REMOTE_STAGE_SHA" ]]
echo 'WEB02_STAGE_IDENTITY=PASS'

echo '===== 4. SNAPSHOT CURRENT PLUGINS ====='
tar -C "$MOODLE" -czf "$SNAP1" local/digieramedia lib/editor/tiny/plugins/digieramedia
ssh -n root@"$WEB02" "tar -C '$MOODLE' -czf '$SNAP2' local/digieramedia lib/editor/tiny/plugins/digieramedia"
echo "WEB01_SNAPSHOT=$SNAP1"
echo "WEB02_SNAPSHOT=$SNAP2"

echo '===== 5. QUIESCE CRON + ENABLE MAINTENANCE ====='
if systemctl is-active --quiet moodle-cron.timer; then
  CRON_WAS_ACTIVE=1
  systemctl stop moodle-cron.timer
fi
runuser -u "$MOODLE_USER" -- bash -c \
  "cd '$MOODLE' && php '$MOODLE/admin/cli/maintenance.php' --enable"
MAINTENANCE_ON=1
echo 'MAINTENANCE=ON'

echo '===== 6. INSTALL WEB01 ====='
for f in "${FILES[@]}"; do
  install -D -m 0644 "$TMP/$f" "$MOODLE/$f"
done
echo 'WEB01_VERSIONING_FILES=PASS'

echo '===== 7. INSTALL WEB02 ====='
tar -C "$TMP" -cf - "${FILES[@]}" | ssh root@"$WEB02" "tar -C '$MOODLE' -xf -"
echo 'WEB02_VERSIONING_FILES=PASS'

echo '===== 8. MOODLE UPGRADE AS WWW-DATA ====='
runuser -u "$MOODLE_USER" -- bash -c \
  "cd '$MOODLE' && php '$MOODLE/admin/cli/upgrade.php' --non-interactive"
echo 'MOODLE_UPGRADE=PASS'

echo '===== 9. VERIFY DATABASE CONTRACT ====='
DB_REPORT="$(runuser -u "$MOODLE_USER" -- bash -c \
  "cd '$MOODLE' && php -r '
    define(\"CLI_SCRIPT\", true);
    require \"$MOODLE/config.php\";
    \$local=(string)\$DB->get_field(\"config_plugins\",\"value\",[\"plugin\"=>\"local_digieramedia\",\"name\"=>\"version\"]);
    \$tiny=(string)\$DB->get_field(\"config_plugins\",\"value\",[\"plugin\"=>\"tiny_digieramedia\",\"name\"=>\"version\"]);
    \$cols=\$DB->get_columns(\"local_digieramedia_upload\");
    \$service=\$DB->record_exists(\"external_functions\",[\"name\"=>\"local_digieramedia_get_media_versions\"]);
    echo \"LOCAL=\".\$local.PHP_EOL;
    echo \"TINY=\".\$tiny.PHP_EOL;
    echo \"TARGETMEDIAID=\".(isset(\$cols[\"targetmediaid\"])?\"YES\":\"NO\").PHP_EOL;
    echo \"VERSION_SERVICE=\".(\$service?\"YES\":\"NO\").PHP_EOL;
  '")"
printf '%s\n' "$DB_REPORT"
grep -Fq "LOCAL=$EXPECTED_LOCAL" <<<"$DB_REPORT"
grep -Fq "TINY=$EXPECTED_TINY" <<<"$DB_REPORT"
grep -Fq 'TARGETMEDIAID=YES' <<<"$DB_REPORT"
grep -Fq 'VERSION_SERVICE=YES' <<<"$DB_REPORT"
echo 'VERSIONING_DB_CONTRACT=PASS'

echo '===== 10. PURGE CACHES + RELOAD PHP-FPM ====='
runuser -u "$MOODLE_USER" -- bash -c \
  "cd '$MOODLE' && php '$MOODLE/admin/cli/purge_caches.php'"
ssh -n root@"$WEB02" \
  "runuser -u '$MOODLE_USER' -- bash -c 'cd \"$MOODLE\" && php \"$MOODLE/admin/cli/purge_caches.php\"'"
reload_fpm_local
ssh -n root@"$WEB02" \
  "svc=\$(systemctl list-units --type=service --all 'php*-fpm.service' --no-legend 2>/dev/null | awk 'NR==1{print \$1}'); if [ -n \"\$svc\" ]; then systemctl reload \"\$svc\"; fi"
echo 'CACHE_FPM_REFRESH=PASS'

echo '===== 11. VERIFY TWO-NODE PARITY ====='
LOCAL_SHA="$(cd "$MOODLE" && sha256sum "${FILES[@]}")"
REMOTE_SHA="$(ssh -n root@"$WEB02" "cd '$MOODLE' && sha256sum ${FILES[*]}")"
[[ "$LOCAL_SHA" == "$REMOTE_SHA" ]]
echo 'TWO_NODE_VERSIONING_PARITY=PASS'

echo '===== 12. R2 RUNTIME STILL CONFIGURED ====='
runuser -u "$MOODLE_USER" -- bash -c \
  "cd '$MOODLE' && php -r 'define(\"CLI_SCRIPT\", true); require \"$MOODLE/config.php\"; \$c=\\local_digieramedia\\r2\\config::load(); \$c->require_credentials(); echo \"WEB01_R2_RUNTIME=PASS\".PHP_EOL;'"
ssh -n root@"$WEB02" \
  "runuser -u '$MOODLE_USER' -- bash -c 'cd \"$MOODLE\" && php -r '\''define(\"CLI_SCRIPT\", true); require \"$MOODLE/config.php\"; \$c=\\local_digieramedia\\r2\\config::load(); \$c->require_credentials(); echo \"WEB02_R2_RUNTIME=PASS\".PHP_EOL;'\'''"
echo 'TWO_NODE_R2_RUNTIME=PASS'

echo '===== 13. RETURN TO SERVICE ====='
runuser -u "$MOODLE_USER" -- bash -c \
  "cd '$MOODLE' && php '$MOODLE/admin/cli/maintenance.php' --disable"
MAINTENANCE_ON=0
if [[ "$CRON_WAS_ACTIVE" == "1" ]]; then
  systemctl start moodle-cron.timer
fi
echo 'MAINTENANCE=OFF'
echo "CRON_TIMER=$(systemctl is-active moodle-cron.timer 2>/dev/null || true)"

echo '========================================='
echo 'DIGIERA_VERSIONING_DEPLOY=PASS'
echo "PINNED_PRODUCT_COMMIT=$REF"
echo 'NEXT=CTRL_F5_THEN_SMOKE_REPLACE_FOLLOW_PIN'
echo '========================================='
