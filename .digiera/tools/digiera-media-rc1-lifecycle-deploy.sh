#!/usr/bin/env bash
set -Eeuo pipefail
umask 022

WEB02="${WEB02:-10.0.10.12}"
MOODLE="${MOODLE:-/var/www/moodle/public}"
MOODLE_USER="${MOODLE_USER:-www-data}"
REF="a7284b8196b2e2d0805a829a350b72341df5c3dd"
RAW="https://raw.githubusercontent.com/Long2902/moodle/$REF/public"
TS="$(date +%Y%m%d-%H%M%S)"
TMP="/var/tmp/DIGIERA_MEDIA_LIFECYCLE_$TS"
REMOTE_TMP="$TMP"
SNAP1="/root/DIGIERA_MEDIA_PRE_LIFECYCLE_WEB01_$TS.tgz"
SNAP2="/root/DIGIERA_MEDIA_PRE_LIFECYCLE_WEB02_$TS.tgz"
EXPECTED_LOCAL=2026090902
EXPECTED_TINY=2026090902
EXPECTED_FILTER=2026090902
CRON_WAS_ACTIVE=0
MAINTENANCE_ON=0

FILES=(
  "local/digieramedia/version.php"
  "local/digieramedia/db/upgrade.php"
  "local/digieramedia/db/services.php"
  "local/digieramedia/classes/r2/sigv4_client.php"
  "local/digieramedia/classes/repository/reference_repository.php"
  "local/digieramedia/classes/service/lifecycle_service.php"
  "local/digieramedia/classes/service/usage_service.php"
  "local/digieramedia/classes/external/get_media_usage.php"
  "local/digieramedia/classes/external/get_media_versions.php"
  "local/digieramedia/classes/external/purge_media.php"
  "local/digieramedia/classes/external/restore_media.php"
  "local/digieramedia/classes/external/search_media.php"
  "local/digieramedia/classes/external/trash_media.php"
  "filter/digieramedia/version.php"
  "filter/digieramedia/classes/text_filter.php"
  "lib/editor/tiny/plugins/digieramedia/version.php"
  "lib/editor/tiny/plugins/digieramedia/amd/src/ui.js"
  "lib/editor/tiny/plugins/digieramedia/amd/build/ui.min.js"
)

cleanup_stage() {
  rm -rf "$TMP" >/dev/null 2>&1 || true
  ssh -n root@"$WEB02" "rm -rf '$REMOTE_TMP'" >/dev/null 2>&1 || true
}

fail() {
  local rc=$?
  local line="${BASH_LINENO[0]:-unknown}"
  echo "DIGIERA_LIFECYCLE_DEPLOY=FAIL LINE=$line RC=$rc"
  if [[ "$MAINTENANCE_ON" == "1" ]]; then
    echo 'RECOVERY_REQUIRED=YES'
    echo 'MAINTENANCE_LEFT_ON=YES'
    echo 'CRON_LEFT_QUIESCED=YES'
    echo "WEB01_SNAPSHOT=$SNAP1"
    echo "WEB02_SNAPSHOT=$SNAP2"
  fi
  cleanup_stage
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

echo '===== 2. DOWNLOAD PINNED LIFECYCLE PAYLOAD ====='
rm -rf "$TMP"
mkdir -p "$TMP"
chmod 0755 "$TMP"
for f in "${FILES[@]}"; do
  mkdir -p "$TMP/$(dirname "$f")"
  curl -fsSL "$RAW/$f" -o "$TMP/$f"
done
find "$TMP" -type d -exec chmod 0755 {} +
find "$TMP" -type f -exec chmod 0644 {} +
echo "PINNED_PRODUCT_COMMIT=$REF"

echo '===== 3. SOURCE + RUNTIME CONTRACT ====='
grep -Fq '2026090902' "$TMP/local/digieramedia/version.php"
grep -Fq '2026090902' "$TMP/filter/digieramedia/version.php"
grep -Fq '2026090902' "$TMP/lib/editor/tiny/plugins/digieramedia/version.php"
grep -Fq "upgrade_plugin_savepoint(true, 2026090902, 'local', 'digieramedia')" \
  "$TMP/local/digieramedia/db/upgrade.php"
! grep -Eq '^namespace ' "$TMP/local/digieramedia/db/upgrade.php"

grep -Fq 'local_digieramedia_get_media_usage' "$TMP/local/digieramedia/db/services.php"
grep -Fq 'local_digieramedia_trash_media' "$TMP/local/digieramedia/db/services.php"
grep -Fq 'local_digieramedia_restore_media' "$TMP/local/digieramedia/db/services.php"
grep -Fq 'local_digieramedia_purge_media' "$TMP/local/digieramedia/db/services.php"

grep -Fq 'function delete_object' "$TMP/local/digieramedia/classes/r2/sigv4_client.php"
grep -Fq 'DELETE' "$TMP/local/digieramedia/classes/r2/sigv4_client.php"
! grep -Fq 'delete is outside' "$TMP/local/digieramedia/classes/r2/sigv4_client.php"
grep -Fq "['ACTIVE', 'TRASHED']" "$TMP/filter/digieramedia/classes/text_filter.php"

grep -Fq 'local_digieramedia_get_media_usage' \
  "$TMP/lib/editor/tiny/plugins/digieramedia/amd/src/ui.js"
grep -Fq 'local_digieramedia_trash_media' \
  "$TMP/lib/editor/tiny/plugins/digieramedia/amd/src/ui.js"
grep -Fq 'local_digieramedia_restore_media' \
  "$TMP/lib/editor/tiny/plugins/digieramedia/amd/src/ui.js"
grep -Fq 'local_digieramedia_purge_media' \
  "$TMP/lib/editor/tiny/plugins/digieramedia/amd/src/ui.js"
grep -Fq 'Xác nhận xóa vĩnh viễn' \
  "$TMP/lib/editor/tiny/plugins/digieramedia/amd/src/ui.js"

grep -Fq 'local_digieramedia_get_media_usage' \
  "$TMP/lib/editor/tiny/plugins/digieramedia/amd/build/ui.min.js"
grep -Fq 'local_digieramedia_purge_media' \
  "$TMP/lib/editor/tiny/plugins/digieramedia/amd/build/ui.min.js"

! grep -R -E 'secretaccesskey|Authorization:|X-Amz-Signature' \
  "$TMP/lib/editor/tiny/plugins/digieramedia/amd/src" >/dev/null

find "$TMP/local/digieramedia" "$TMP/filter/digieramedia" \
  "$TMP/lib/editor/tiny/plugins/digieramedia" \
  -type f -name '*.php' -print0 | sort -z | xargs -0 -r -n1 php -l >/dev/null
node --check "$TMP/lib/editor/tiny/plugins/digieramedia/amd/src/ui.js"
node --check "$TMP/lib/editor/tiny/plugins/digieramedia/amd/build/ui.min.js"
echo 'LIFECYCLE_SOURCE_RUNTIME_CONTRACT=PASS'

echo '===== 4. STAGE WEB02 + VERIFY IDENTITY ====='
LOCAL_STAGE_SHA="$(cd "$TMP" && sha256sum "${FILES[@]}")"
tar -C "$TMP" -cf - "${FILES[@]}" | \
  ssh root@"$WEB02" "rm -rf '$REMOTE_TMP'; mkdir -p '$REMOTE_TMP'; chmod 0755 '$REMOTE_TMP'; tar -C '$REMOTE_TMP' -xf -"
ssh -n root@"$WEB02" "find '$REMOTE_TMP' -type d -exec chmod 0755 {} +; find '$REMOTE_TMP' -type f -exec chmod 0644 {} +"
REMOTE_STAGE_SHA="$(ssh -n root@"$WEB02" "cd '$REMOTE_TMP' && sha256sum ${FILES[*]}")"
[[ "$LOCAL_STAGE_SHA" == "$REMOTE_STAGE_SHA" ]]
echo 'WEB02_STAGE_IDENTITY=PASS'

echo '===== 5. SNAPSHOT CURRENT PLUGINS ====='
tar -C "$MOODLE" -czf "$SNAP1" \
  local/digieramedia filter/digieramedia lib/editor/tiny/plugins/digieramedia
ssh -n root@"$WEB02" \
  "tar -C '$MOODLE' -czf '$SNAP2' local/digieramedia filter/digieramedia lib/editor/tiny/plugins/digieramedia"
echo "WEB01_SNAPSHOT=$SNAP1"
echo "WEB02_SNAPSHOT=$SNAP2"

echo '===== 6. QUIESCE CRON + ENABLE MAINTENANCE ====='
if systemctl is-active --quiet moodle-cron.timer; then
  CRON_WAS_ACTIVE=1
  systemctl stop moodle-cron.timer
fi
runuser -u "$MOODLE_USER" -- bash -c \
  "cd '$MOODLE' && php '$MOODLE/admin/cli/maintenance.php' --enable"
MAINTENANCE_ON=1
echo 'MAINTENANCE=ON'

echo '===== 7. INSTALL WEB01 ====='
for f in "${FILES[@]}"; do
  install -D -m 0644 "$TMP/$f" "$MOODLE/$f"
done
echo 'WEB01_LIFECYCLE_FILES=PASS'

echo '===== 8. INSTALL WEB02 ====='
tar -C "$TMP" -cf - "${FILES[@]}" | ssh root@"$WEB02" "tar -C '$MOODLE' -xf -"
echo 'WEB02_LIFECYCLE_FILES=PASS'

echo '===== 9. MOODLE UPGRADE AS WWW-DATA ====='
runuser -u "$MOODLE_USER" -- bash -c \
  "cd '$MOODLE' && php '$MOODLE/admin/cli/upgrade.php' --non-interactive"
echo 'MOODLE_UPGRADE=PASS'

echo '===== 10. VERIFY DATABASE + SERVICE CONTRACT ====='
DB_REPORT="$(runuser -u "$MOODLE_USER" -- bash -c \
  "cd '$MOODLE' && php -r '
    define(\"CLI_SCRIPT\", true);
    require \"$MOODLE/config.php\";
    require_once \"$MOODLE/local/digieramedia/db/upgrade.php\";
    \$local=(string)\$DB->get_field(\"config_plugins\",\"value\",[\"plugin\"=>\"local_digieramedia\",\"name\"=>\"version\"]);
    \$tiny=(string)\$DB->get_field(\"config_plugins\",\"value\",[\"plugin\"=>\"tiny_digieramedia\",\"name\"=>\"version\"]);
    \$filter=(string)\$DB->get_field(\"config_plugins\",\"value\",[\"plugin\"=>\"filter_digieramedia\",\"name\"=>\"version\"]);
    echo \"LOCAL=\".\$local.PHP_EOL;
    echo \"TINY=\".\$tiny.PHP_EOL;
    echo \"FILTER=\".\$filter.PHP_EOL;
    echo \"GLOBAL_UPGRADE_FUNCTION=\".(function_exists(\"xmldb_local_digieramedia_upgrade\")?\"YES\":\"NO\").PHP_EOL;
    foreach ([
      \"local_digieramedia_get_media_usage\",
      \"local_digieramedia_trash_media\",
      \"local_digieramedia_restore_media\",
      \"local_digieramedia_purge_media\"
    ] as \$name) {
      echo strtoupper(str_replace(\"local_digieramedia_\",\"\",\$name)).\"_SERVICE=\".
        (\$DB->record_exists(\"external_functions\",[\"name\"=>\$name])?\"YES\":\"NO\").PHP_EOL;
    }
  '")"
printf '%s\n' "$DB_REPORT"
grep -Fq "LOCAL=$EXPECTED_LOCAL" <<<"$DB_REPORT"
grep -Fq "TINY=$EXPECTED_TINY" <<<"$DB_REPORT"
grep -Fq "FILTER=$EXPECTED_FILTER" <<<"$DB_REPORT"
grep -Fq 'GLOBAL_UPGRADE_FUNCTION=YES' <<<"$DB_REPORT"
grep -Fq 'GET_MEDIA_USAGE_SERVICE=YES' <<<"$DB_REPORT"
grep -Fq 'TRASH_MEDIA_SERVICE=YES' <<<"$DB_REPORT"
grep -Fq 'RESTORE_MEDIA_SERVICE=YES' <<<"$DB_REPORT"
grep -Fq 'PURGE_MEDIA_SERVICE=YES' <<<"$DB_REPORT"
echo 'LIFECYCLE_DB_SERVICE_CONTRACT=PASS'

echo '===== 11. NON-DESTRUCTIVE R2 RUNTIME CHECK ====='
runuser -u "$MOODLE_USER" -- bash -c \
  "cd '$MOODLE' && php -r 'define(\"CLI_SCRIPT\", true); require \"$MOODLE/config.php\"; \$c=\\local_digieramedia\\r2\\config::load(); \$c->require_credentials(); if (!method_exists(\\local_digieramedia\\r2\\sigv4_client::class, \"delete_object\")) { exit(2); } echo \"WEB01_R2_DELETE_RUNTIME=PASS\".PHP_EOL;'"
ssh -n root@"$WEB02" \
  "runuser -u '$MOODLE_USER' -- bash -c 'cd \"$MOODLE\" && php -r '\''define(\"CLI_SCRIPT\", true); require \"$MOODLE/config.php\"; \$c=\\local_digieramedia\\r2\\config::load(); \$c->require_credentials(); if (!method_exists(\\local_digieramedia\\r2\\sigv4_client::class, \"delete_object\")) { exit(2); } echo \"WEB02_R2_DELETE_RUNTIME=PASS\".PHP_EOL;'\'''"
echo 'R2_DELETE_PREFLIGHT=NON_DESTRUCTIVE_PASS'

echo '===== 12. PURGE CACHES + RELOAD PHP-FPM ====='
runuser -u "$MOODLE_USER" -- bash -c \
  "cd '$MOODLE' && php '$MOODLE/admin/cli/purge_caches.php'"
ssh -n root@"$WEB02" \
  "runuser -u '$MOODLE_USER' -- bash -c 'cd \"$MOODLE\" && php \"$MOODLE/admin/cli/purge_caches.php\"'"
reload_fpm_local
ssh -n root@"$WEB02" \
  "svc=\$(systemctl list-units --type=service --all 'php*-fpm.service' --no-legend 2>/dev/null | awk 'NR==1{print \$1}'); if [ -n \"\$svc\" ]; then systemctl reload \"\$svc\"; fi"
echo 'CACHE_FPM_REFRESH=PASS'

echo '===== 13. VERIFY TWO-NODE PARITY ====='
LOCAL_SHA="$(cd "$MOODLE" && sha256sum "${FILES[@]}")"
REMOTE_SHA="$(ssh -n root@"$WEB02" "cd '$MOODLE' && sha256sum ${FILES[*]}")"
[[ "$LOCAL_SHA" == "$REMOTE_SHA" ]]
echo 'TWO_NODE_LIFECYCLE_PARITY=PASS'

echo '===== 14. RETURN TO SERVICE ====='
runuser -u "$MOODLE_USER" -- bash -c \
  "cd '$MOODLE' && php '$MOODLE/admin/cli/maintenance.php' --disable"
MAINTENANCE_ON=0
if [[ "$CRON_WAS_ACTIVE" == "1" ]]; then
  systemctl start moodle-cron.timer
fi
echo 'MAINTENANCE=OFF'
echo "CRON_TIMER=$(systemctl is-active moodle-cron.timer 2>/dev/null || true)"

cleanup_stage

echo '========================================='
echo 'DIGIERA_LIFECYCLE_DEPLOY=PASS'
echo "PINNED_PRODUCT_COMMIT=$REF"
echo 'R2_DELETE_PREFLIGHT=NO_REAL_DELETE'
echo 'NEXT=CTRL_F5_THEN_SMOKE_USAGE_TRASH_RESTORE_PURGE_WITH_DISPOSABLE_MEDIA'
echo '========================================='
