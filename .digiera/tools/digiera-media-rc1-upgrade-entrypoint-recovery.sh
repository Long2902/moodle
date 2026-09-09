#!/usr/bin/env bash
set -Eeuo pipefail
umask 022

WEB02="${WEB02:-10.0.10.12}"
MOODLE="${MOODLE:-/var/www/moodle/public}"
MOODLE_USER="${MOODLE_USER:-www-data}"
FIX_REF="e67278ffdddb61ff7b63108c01c113096485fd4f"
RAW="https://raw.githubusercontent.com/Long2902/moodle/$FIX_REF/public"
TS="$(date +%Y%m%d-%H%M%S)"
TMP="/var/tmp/DIGIERA_MEDIA_UPGRADE_ENTRYPOINT_RECOVERY_$TS"
FIXFILE="$TMP/upgrade.php"
BACKUP1="/root/DIGIERA_MEDIA_PRE_UPGRADE_ENTRYPOINT_FIX_WEB01_$TS.php"
BACKUP2="/root/DIGIERA_MEDIA_PRE_UPGRADE_ENTRYPOINT_FIX_WEB02_$TS.php"
EXPECTED_LOCAL=2026090901
EXPECTED_TINY=2026090901
CRON_WAS_ACTIVE=0
MAINTENANCE_ON=0

fail() {
  local rc=$?
  echo "DIGIERA_UPGRADE_ENTRYPOINT_RECOVERY=FAIL LINE=${BASH_LINENO[0]:-unknown}"
  if [[ "$MAINTENANCE_ON" == "1" ]]; then
    echo 'MAINTENANCE_REMAINS=ON_FOR_SAFETY'
  fi
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

echo '===== 1. PRECHECK PARTIAL DEPLOY STATE ====='
ssh -n -o BatchMode=yes -o ConnectTimeout=8 root@"$WEB02" 'true'
echo 'WEB02_SSH=PASS'

grep -Fq "$EXPECTED_LOCAL" "$MOODLE/local/digieramedia/version.php"
grep -Fq "$EXPECTED_TINY" "$MOODLE/lib/editor/tiny/plugins/digieramedia/version.php"
ssh -n root@"$WEB02" "grep -Fq '$EXPECTED_LOCAL' '$MOODLE/local/digieramedia/version.php' && grep -Fq '$EXPECTED_TINY' '$MOODLE/lib/editor/tiny/plugins/digieramedia/version.php'"
echo 'PARTIAL_REFERENCE_STATE_FILES_PRESENT=PASS'

echo '===== 2. DOWNLOAD FIXED GLOBAL UPGRADE ENTRYPOINT ====='
rm -rf "$TMP"
mkdir -p "$TMP"
curl -fL "$RAW/local/digieramedia/db/upgrade.php" -o "$FIXFILE"
chmod 0644 "$FIXFILE"
php -l "$FIXFILE" >/dev/null
if grep -Eq '^[[:space:]]*namespace[[:space:]]+' "$FIXFILE"; then
  echo 'FIXED_UPGRADE_NAMESPACE=UNEXPECTED'
  exit 2
fi
grep -Eq '^function[[:space:]]+xmldb_local_digieramedia_upgrade[[:space:]]*\(' "$FIXFILE"
php -r 'define("MOODLE_INTERNAL", true); require $argv[1]; if (!function_exists("xmldb_local_digieramedia_upgrade")) { exit(3); }' "$FIXFILE"
echo 'GLOBAL_UPGRADE_ENTRYPOINT_CONTRACT=PASS'

echo '===== 3. SNAPSHOT CURRENT UPGRADE FILE ====='
cp -a "$MOODLE/local/digieramedia/db/upgrade.php" "$BACKUP1"
ssh -n root@"$WEB02" "cp -a '$MOODLE/local/digieramedia/db/upgrade.php' '$BACKUP2'"
echo "WEB01_UPGRADE_BACKUP=$BACKUP1"
echo "WEB02_UPGRADE_BACKUP=$BACKUP2"

echo '===== 4. QUIESCE + MAINTENANCE ====='
if systemctl is-active --quiet moodle-cron.timer; then
  CRON_WAS_ACTIVE=1
  systemctl stop moodle-cron.timer
fi
runuser -u "$MOODLE_USER" -- bash -c "cd '$MOODLE' && php '$MOODLE/admin/cli/maintenance.php' --enable"
MAINTENANCE_ON=1
echo 'MAINTENANCE=ON'

echo '===== 5. INSTALL FIX WEB01 + WEB02 ====='
install -m 0644 "$FIXFILE" "$MOODLE/local/digieramedia/db/upgrade.php"
scp -q "$FIXFILE" root@"$WEB02":"/tmp/digiera-upgrade-entrypoint-$TS.php"
ssh -n root@"$WEB02" "install -m 0644 '/tmp/digiera-upgrade-entrypoint-$TS.php' '$MOODLE/local/digieramedia/db/upgrade.php'; rm -f '/tmp/digiera-upgrade-entrypoint-$TS.php'"

echo '===== 6. VERIFY GLOBAL FUNCTION ON BOTH NODES ====='
runuser -u "$MOODLE_USER" -- php -r 'define("MOODLE_INTERNAL", true); require $argv[1]; echo function_exists("xmldb_local_digieramedia_upgrade") ? "WEB01_GLOBAL_UPGRADE_FUNCTION=PASS\n" : "WEB01_GLOBAL_UPGRADE_FUNCTION=FAIL\n"; exit(function_exists("xmldb_local_digieramedia_upgrade") ? 0 : 4);' "$MOODLE/local/digieramedia/db/upgrade.php"
ssh -n root@"$WEB02" "runuser -u '$MOODLE_USER' -- php -r 'define(\"MOODLE_INTERNAL\", true); require \$argv[1]; echo function_exists(\"xmldb_local_digieramedia_upgrade\") ? \"WEB02_GLOBAL_UPGRADE_FUNCTION=PASS\\n\" : \"WEB02_GLOBAL_UPGRADE_FUNCTION=FAIL\\n\"; exit(function_exists(\"xmldb_local_digieramedia_upgrade\") ? 0 : 4);' '$MOODLE/local/digieramedia/db/upgrade.php'"

echo '===== 7. RESUME MOODLE UPGRADE ====='
runuser -u "$MOODLE_USER" -- bash -c "cd '$MOODLE' && php '$MOODLE/admin/cli/upgrade.php' --non-interactive"
echo 'MOODLE_UPGRADE=PASS'

echo '===== 8. VERIFY DATABASE + SERVICE ====='
DB_REPORT="$(runuser -u "$MOODLE_USER" -- bash -c "cd '$MOODLE' && php -r '
  define(\"CLI_SCRIPT\", true);
  require \"$MOODLE/config.php\";
  \$local=(string)\$DB->get_field(\"config_plugins\",\"value\",[\"plugin\"=>\"local_digieramedia\",\"name\"=>\"version\"]);
  \$tiny=(string)\$DB->get_field(\"config_plugins\",\"value\",[\"plugin\"=>\"tiny_digieramedia\",\"name\"=>\"version\"]);
  \$service=\$DB->record_exists(\"external_functions\",[\"name\"=>\"local_digieramedia_update_reference_version\"]);
  echo \"LOCAL=\".\$local.PHP_EOL;
  echo \"TINY=\".\$tiny.PHP_EOL;
  echo \"REFERENCE_VERSION_SERVICE=\".(\$service?\"YES\":\"NO\").PHP_EOL;
'")"
printf '%s\n' "$DB_REPORT"
grep -Fq "LOCAL=$EXPECTED_LOCAL" <<<"$DB_REPORT"
grep -Fq "TINY=$EXPECTED_TINY" <<<"$DB_REPORT"
grep -Fq 'REFERENCE_VERSION_SERVICE=YES' <<<"$DB_REPORT"
echo 'REFERENCE_STATE_DB_CONTRACT=PASS'

echo '===== 9. CACHE + FPM + PARITY ====='
runuser -u "$MOODLE_USER" -- bash -c "cd '$MOODLE' && php '$MOODLE/admin/cli/purge_caches.php'"
ssh -n root@"$WEB02" "runuser -u '$MOODLE_USER' -- bash -c 'cd \"$MOODLE\" && php \"$MOODLE/admin/cli/purge_caches.php\"'"
reload_fpm_local
ssh -n root@"$WEB02" "svc=\$(systemctl list-units --type=service --all 'php*-fpm.service' --no-legend 2>/dev/null | awk 'NR==1{print \$1}'); if [ -n \"\$svc\" ]; then systemctl reload \"\$svc\"; fi"
LOCAL_SHA="$(sha256sum "$MOODLE/local/digieramedia/db/upgrade.php" | awk '{print $1}')"
REMOTE_SHA="$(ssh -n root@"$WEB02" "sha256sum '$MOODLE/local/digieramedia/db/upgrade.php' | awk '{print \$1}'")"
[[ "$LOCAL_SHA" == "$REMOTE_SHA" ]]
echo 'TWO_NODE_UPGRADE_FILE_PARITY=PASS'

echo '===== 10. R2 RUNTIME CHECK ====='
runuser -u "$MOODLE_USER" -- bash -c "cd '$MOODLE' && php -r 'define(\"CLI_SCRIPT\", true); require \"$MOODLE/config.php\"; \$c=\\local_digieramedia\\r2\\config::load(); \$c->require_credentials(); echo \"WEB01_R2_RUNTIME=PASS\".PHP_EOL;'"
ssh -n root@"$WEB02" "runuser -u '$MOODLE_USER' -- bash -c 'cd \"$MOODLE\" && php -r '\''define(\"CLI_SCRIPT\", true); require \"$MOODLE/config.php\"; \$c=\\local_digieramedia\\r2\\config::load(); \$c->require_credentials(); echo \"WEB02_R2_RUNTIME=PASS\".PHP_EOL;'\'''"
echo 'TWO_NODE_R2_RUNTIME=PASS'

echo '===== 11. RETURN TO SERVICE ====='
runuser -u "$MOODLE_USER" -- bash -c "cd '$MOODLE' && php '$MOODLE/admin/cli/maintenance.php' --disable"
MAINTENANCE_ON=0
if [[ "$CRON_WAS_ACTIVE" == "1" ]]; then
  systemctl start moodle-cron.timer
fi
echo 'MAINTENANCE=OFF'
echo "CRON_TIMER=$(systemctl is-active moodle-cron.timer 2>/dev/null || true)"
echo '========================================='
echo 'DIGIERA_UPGRADE_ENTRYPOINT_RECOVERY=PASS'
echo "PINNED_FIX_COMMIT=$FIX_REF"
echo 'NEXT=CTRL_F5_AND_RETEST_PINNED_REFERENCE_REOPEN'
echo '========================================='
