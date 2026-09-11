#!/usr/bin/env bash
set -Eeuo pipefail
umask 022

WEB02="${WEB02:-10.0.10.12}"
MOODLE="${MOODLE:-/var/www/moodle/public}"
MOODLE_USER="${MOODLE_USER:-www-data}"
REF="9919b0801ab2e3a1dd29bd9fc25506ccecd07c28"
RAW="https://raw.githubusercontent.com/Long2902/moodle/$REF/public"
EXPECTED_LOCAL=2026091001
TS="$(date +%Y%m%d-%H%M%S)"
TMP="/var/tmp/DIGIERA_MEDIA_RECENT_PERMISSIONS_$TS"
REMOTE_TMP="$TMP"
SNAP1="/root/DIGIERA_MEDIA_PRE_RECENT_PERMISSIONS_WEB01_$TS.tgz"
SNAP2="/root/DIGIERA_MEDIA_PRE_RECENT_PERMISSIONS_WEB02_$TS.tgz"
CRON1_WAS_ACTIVE=0
CRON2_WAS_ACTIVE=0
MAINTENANCE_ON=0

FILES=(
  "local/digieramedia/version.php"
  "local/digieramedia/db/install.xml"
  "local/digieramedia/db/upgrade.php"
  "local/digieramedia/classes/service/recent_service.php"
  "local/digieramedia/classes/external/create_reference.php"
  "local/digieramedia/classes/external/update_reference_version.php"
  "local/digieramedia/classes/external/search_media.php"
  "local/digieramedia/classes/service/lifecycle_service.php"
)

cleanup_stage() {
  rm -rf "$TMP" >/dev/null 2>&1 || true
  ssh -n root@"$WEB02" "rm -rf '$REMOTE_TMP'" >/dev/null 2>&1 || true
}

fail() {
  local rc=$?
  local line="${BASH_LINENO[0]:-unknown}"
  echo "DIGIERA_RECENT_PERMISSIONS_DEPLOY=FAIL LINE=$line RC=$rc"
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

echo '===== 2. DOWNLOAD PINNED PAYLOAD ====='
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

echo '===== 3. SOURCE CONTRACT ====='
grep -Fq '2026091001' "$TMP/local/digieramedia/version.php"
grep -Fq 'local_digieramedia_recent' "$TMP/local/digieramedia/db/install.xml"
grep -Fq 'NAME="user-media" UNIQUE="true" FIELDS="userid, mediaid"' "$TMP/local/digieramedia/db/install.xml"
grep -Fq 'NAME="user-lastused" UNIQUE="false" FIELDS="userid, lastusedat"' "$TMP/local/digieramedia/db/install.xml"
grep -Fq "upgrade_plugin_savepoint(true, 2026091001, 'local', 'digieramedia')" "$TMP/local/digieramedia/db/upgrade.php"
! grep -Eq '^namespace ' "$TMP/local/digieramedia/db/upgrade.php"
grep -Fq 'class recent_service' "$TMP/local/digieramedia/classes/service/recent_service.php"
grep -Fq 'function touch' "$TMP/local/digieramedia/classes/service/recent_service.php"
grep -Fq 'CREATE_REFERENCE' "$TMP/local/digieramedia/classes/external/create_reference.php"
grep -Fq 'recent_service' "$TMP/local/digieramedia/classes/external/create_reference.php"
grep -Fq 'UPDATE_REFERENCE' "$TMP/local/digieramedia/classes/external/update_reference_version.php"
grep -Fq 'recent_service' "$TMP/local/digieramedia/classes/external/update_reference_version.php"
grep -Fq 'local_digieramedia_recent' "$TMP/local/digieramedia/classes/external/search_media.php"
grep -Fq 'recentuserid' "$TMP/local/digieramedia/classes/external/search_media.php"
grep -Fq 'r.lastusedat DESC' "$TMP/local/digieramedia/classes/external/search_media.php"
grep -Fq "delete_records('local_digieramedia_recent'" "$TMP/local/digieramedia/classes/service/lifecycle_service.php"

find "$TMP/local/digieramedia" -type f -name '*.php' -print0 | sort -z | xargs -0 -r -n1 php -l >/dev/null
echo 'RECENT_PERMISSIONS_SOURCE_CONTRACT=PASS'

echo '===== 4. STAGE WEB02 + VERIFY STAGE IDENTITY ====='
LOCAL_STAGE_SHA="$(cd "$TMP" && sha256sum "${FILES[@]}")"
tar -C "$TMP" -cf - "${FILES[@]}" | \
  ssh root@"$WEB02" "rm -rf '$REMOTE_TMP'; mkdir -p '$REMOTE_TMP'; chmod 0755 '$REMOTE_TMP'; tar -C '$REMOTE_TMP' -xf -"
ssh -n root@"$WEB02" "find '$REMOTE_TMP' -type d -exec chmod 0755 {} +; find '$REMOTE_TMP' -type f -exec chmod 0644 {} +"
REMOTE_STAGE_SHA="$(ssh -n root@"$WEB02" "cd '$REMOTE_TMP' && sha256sum ${FILES[*]}")"
[[ "$LOCAL_STAGE_SHA" == "$REMOTE_STAGE_SHA" ]]
echo 'WEB02_STAGE_IDENTITY=PASS'

echo '===== 5. SNAPSHOT CURRENT LOCAL PLUGIN ====='
tar -C "$MOODLE" -czf "$SNAP1" local/digieramedia
ssh -n root@"$WEB02" "tar -C '$MOODLE' -czf '$SNAP2' local/digieramedia"
echo "WEB01_SNAPSHOT=$SNAP1"
echo "WEB02_SNAPSHOT=$SNAP2"

echo '===== 6. QUIESCE CRON + ENABLE MAINTENANCE ====='
if systemctl is-active --quiet moodle-cron.timer; then
  CRON1_WAS_ACTIVE=1
  systemctl stop moodle-cron.timer
fi
if ssh -n root@"$WEB02" 'systemctl is-active --quiet moodle-cron.timer'; then
  CRON2_WAS_ACTIVE=1
  ssh -n root@"$WEB02" 'systemctl stop moodle-cron.timer'
fi
runuser -u "$MOODLE_USER" -- bash -c \
  "cd '$MOODLE' && php '$MOODLE/admin/cli/maintenance.php' --enable"
MAINTENANCE_ON=1
echo 'MAINTENANCE=ON'

echo '===== 7. INSTALL WEB01 ====='
for f in "${FILES[@]}"; do
  install -D -m 0644 "$TMP/$f" "$MOODLE/$f"
done
find "$MOODLE/local/digieramedia" -type d -exec chmod 0755 {} +
echo 'WEB01_RECENT_PERMISSIONS_FILES=PASS'

echo '===== 8. INSTALL WEB02 ====='
tar -C "$TMP" -cf - "${FILES[@]}" | ssh root@"$WEB02" "tar -C '$MOODLE' -xf -"
ssh -n root@"$WEB02" "find '$MOODLE/local/digieramedia' -type d -exec chmod 0755 {} +; find '$MOODLE/local/digieramedia' -type f -exec chmod 0644 {} +"
echo 'WEB02_RECENT_PERMISSIONS_FILES=PASS'

echo '===== 9. MOODLE UPGRADE AS WWW-DATA ====='
runuser -u "$MOODLE_USER" -- bash -c \
  "cd '$MOODLE' && php '$MOODLE/admin/cli/upgrade.php' --non-interactive"
echo 'MOODLE_UPGRADE=PASS'

echo '===== 10. VERIFY DATABASE CONTRACT ====='
DB_REPORT="$(runuser -u "$MOODLE_USER" -- bash -c \
  "cd '$MOODLE' && php -r '
    define(\"CLI_SCRIPT\", true);
    require \"$MOODLE/config.php\";
    require_once \"$MOODLE/local/digieramedia/db/upgrade.php\";
    \$local=(string)\$DB->get_field(\"config_plugins\",\"value\",[\"plugin\"=>\"local_digieramedia\",\"name\"=>\"version\"]);
    \$dbman=\$DB->get_manager();
    \$table=new xmldb_table(\"local_digieramedia_recent\");
    \$userMedia=new xmldb_index(\"user-media\", XMLDB_INDEX_UNIQUE, [\"userid\",\"mediaid\"]);
    \$userLast=new xmldb_index(\"user-lastused\", XMLDB_INDEX_NOTUNIQUE, [\"userid\",\"lastusedat\"]);
    echo \"LOCAL=\".\$local.PHP_EOL;
    echo \"RECENT_TABLE=\".(\$dbman->table_exists(\$table)?\"YES\":\"NO\").PHP_EOL;
    echo \"USER_MEDIA_INDEX=\".(\$dbman->index_exists(\$table,\$userMedia)?\"YES\":\"NO\").PHP_EOL;
    echo \"USER_LASTUSED_INDEX=\".(\$dbman->index_exists(\$table,\$userLast)?\"YES\":\"NO\").PHP_EOL;
    echo \"GLOBAL_UPGRADE_FUNCTION=\".(function_exists(\"xmldb_local_digieramedia_upgrade\")?\"YES\":\"NO\").PHP_EOL;
  '")"
printf '%s\n' "$DB_REPORT"
grep -Fq "LOCAL=$EXPECTED_LOCAL" <<<"$DB_REPORT"
grep -Fq 'RECENT_TABLE=YES' <<<"$DB_REPORT"
grep -Fq 'USER_MEDIA_INDEX=YES' <<<"$DB_REPORT"
grep -Fq 'USER_LASTUSED_INDEX=YES' <<<"$DB_REPORT"
grep -Fq 'GLOBAL_UPGRADE_FUNCTION=YES' <<<"$DB_REPORT"
echo 'RECENT_PERMISSIONS_DB_CONTRACT=PASS'

echo '===== 11. PURGE CACHES + RELOAD PHP-FPM ====='
runuser -u "$MOODLE_USER" -- bash -c \
  "cd '$MOODLE' && php '$MOODLE/admin/cli/purge_caches.php'"
ssh -n root@"$WEB02" \
  "runuser -u '$MOODLE_USER' -- bash -c 'cd \"$MOODLE\" && php \"$MOODLE/admin/cli/purge_caches.php\"'"
reload_fpm_local
ssh -n root@"$WEB02" \
  "svc=\$(systemctl list-units --type=service --all 'php*-fpm.service' --no-legend 2>/dev/null | awk 'NR==1{print \$1}'); if [ -n \"\$svc\" ]; then systemctl reload \"\$svc\"; fi"
echo 'CACHE_FPM_REFRESH=PASS'

echo '===== 12. VERIFY TWO-NODE FILE PARITY ====='
WEB01_SHA="$(cd "$MOODLE" && sha256sum "${FILES[@]}")"
WEB02_SHA="$(ssh -n root@"$WEB02" "cd '$MOODLE' && sha256sum ${FILES[*]}")"
[[ "$WEB01_SHA" == "$WEB02_SHA" ]]
echo 'TWO_NODE_FILE_PARITY=PASS'

echo '===== 13. DISABLE MAINTENANCE + RESTORE CRON ====='
runuser -u "$MOODLE_USER" -- bash -c \
  "cd '$MOODLE' && php '$MOODLE/admin/cli/maintenance.php' --disable"
MAINTENANCE_ON=0
if [[ "$CRON1_WAS_ACTIVE" == "1" ]]; then
  systemctl start moodle-cron.timer
fi
if [[ "$CRON2_WAS_ACTIVE" == "1" ]]; then
  ssh -n root@"$WEB02" 'systemctl start moodle-cron.timer'
fi
cleanup_stage

echo 'DIGIERA_RECENT_PERMISSIONS_DEPLOY=PASS'
echo "PINNED_PRODUCT_COMMIT=$REF"
echo "LOCAL_VERSION=$EXPECTED_LOCAL"
echo 'R2_MUTATION=NONE'
echo 'NEXT=Ctrl+F5 then verify User A/User B Recent isolation, Trash/Restore preservation, and Teacher/KTV permission matrix.'
