#!/usr/bin/env bash
set -Eeuo pipefail

EXPECTED_HOST="vm-c47e0dd9"
WEB02="${WEB02:-root@10.0.10.12}"
EXPECTED_WEB02_HOST="${EXPECTED_WEB02_HOST:-moodle-web02}"
MOODLE="${MOODLE:-/var/www/moodle/public}"
RESTORE_CRON1="${RESTORE_CRON1:-1}"
RESTORE_CRON2="${RESTORE_CRON2:-0}"
STAMP="$(date +%Y%m%d-%H%M%S)"
LOG="/root/digiera-preview-controlled-deploy/recovery-$STAMP.log"
CHECK="/tmp/digiera-preview-recovery-check-${STAMP}-$$.php"
MAINT_BEFORE=""
MAINT_ENABLED_BY_RECOVERY=0
RECOVERY_DONE=0

mkdir -p "$(dirname "$LOG")"
exec > >(tee -a "$LOG") 2>&1

run_moodle_local() {
  if id www-data >/dev/null 2>&1; then
    runuser -u www-data -- php "$@"
  else
    php "$@"
  fi
}

maintenance_state() {
  local out
  if id www-data >/dev/null 2>&1; then
    out="$(runuser -u www-data -- php -r 'define("CLI_SCRIPT", true); require "/var/www/moodle/public/config.php"; echo !empty($CFG->maintenance_enabled) ? "1" : "0";')"
  else
    out="$(php -r 'define("CLI_SCRIPT", true); require "/var/www/moodle/public/config.php"; echo !empty($CFG->maintenance_enabled) ? "1" : "0";')"
  fi
  case "$out" in
    0|1) printf '%s' "$out" ;;
    *) echo "INVALID_MAINTENANCE_STATE=$out" >&2; return 1 ;;
  esac
}

wait_cron_inactive_local() {
  local n
  for n in $(seq 1 60); do
    systemctl is-active --quiet moodle-cron.service || return 0
    sleep 2
  done
  return 1
}

wait_cron_inactive_remote() {
  local n
  for n in $(seq 1 60); do
    ssh "$WEB02" 'systemctl is-active --quiet moodle-cron.service' || return 0
    sleep 2
  done
  return 1
}

tree_hash_local() {
  (
    cd "$MOODLE"
    find local/digieranative local/digieraoffice local/worksheetlibrary mod/worksheetgrader \
      -type f -print0 | LC_ALL=C sort -z | xargs -0 sha256sum | sha256sum | awk '{print $1}'
  )
}

restore_runtime() {
  if [ "$RESTORE_CRON1" = "1" ]; then
    systemctl start moodle-cron.timer
  else
    systemctl stop moodle-cron.timer 2>/dev/null || true
  fi
  if [ "$RESTORE_CRON2" = "1" ]; then
    ssh "$WEB02" 'systemctl start moodle-cron.timer'
  else
    ssh "$WEB02" 'systemctl stop moodle-cron.timer 2>/dev/null || true'
  fi

  if [ "$MAINT_ENABLED_BY_RECOVERY" -eq 1 ]; then
    run_moodle_local "$MOODLE/admin/cli/maintenance.php" --disable
    MAINT_ENABLED_BY_RECOVERY=0
  fi
}

on_exit() {
  rc=$?
  rm -f "$CHECK" 2>/dev/null || true
  if [ "$rc" -eq 0 ] && [ "$RECOVERY_DONE" -eq 1 ]; then
    return 0
  fi
  echo
  echo '===== RECOVERY STOPPED ====='
  echo "EXIT_CODE=$rc"
  echo "LOG=$LOG"
  echo 'DB_UPGRADE_REPLAY=NO'
  echo 'SAFETY_ACTION=KEEP_MAINTENANCE_ENABLED_AND_CRON_STOPPED'
  if [ -n "$MAINT_BEFORE" ] && [ "$MAINT_BEFORE" = "0" ] && [ "$MAINT_ENABLED_BY_RECOVERY" -eq 0 ]; then
    run_moodle_local "$MOODLE/admin/cli/maintenance.php" --enable || true
    MAINT_ENABLED_BY_RECOVERY=1
  fi
  systemctl stop moodle-cron.timer 2>/dev/null || true
  ssh "$WEB02" 'systemctl stop moodle-cron.timer 2>/dev/null || true' || true
  echo 'PREVIEW_RECOVERY=STOPPED'
}
trap on_exit EXIT

echo '============================================================'
echo ' DIGIERA PREVIEW V0.1 - POST-UPGRADE RECOVERY / VERIFY'
echo '============================================================'
date -Is
echo "HOSTNAME=$(hostname)"
echo "WEB02=$WEB02"
echo "LOG=$LOG"
echo 'DB_UPGRADE_REPLAY=NO'

echo
echo '===== PREFLIGHT ====='
[ "$(hostname)" = "$EXPECTED_HOST" ] || { echo 'WEB01_HOST_GUARD=FAIL'; exit 20; }
ssh -o BatchMode=yes -o ConnectTimeout=10 "$WEB02" 'true'
WEB02_HOST="$(ssh "$WEB02" hostname)"
[ "$WEB02_HOST" = "$EXPECTED_WEB02_HOST" ] || { echo "WEB02_HOST_GUARD=FAIL:$WEB02_HOST"; exit 21; }
for f in "$MOODLE/config.php" "$MOODLE/admin/cli/maintenance.php" "$MOODLE/admin/cli/purge_caches.php"; do
  [ -f "$f" ] || { echo "MISSING_WEB01=$f"; exit 22; }
done
ssh "$WEB02" "test -f '$MOODLE/config.php' && test -f '$MOODLE/admin/cli/purge_caches.php'"
echo 'PREFLIGHT=PASS'

echo
echo '===== READ MAINTENANCE STATE AS MOODLE USER ====='
MAINT_BEFORE="$(maintenance_state)"
echo "MAINT_BEFORE_RECOVERY=$MAINT_BEFORE"
if [ "$MAINT_BEFORE" = "0" ]; then
  run_moodle_local "$MOODLE/admin/cli/maintenance.php" --enable
  MAINT_ENABLED_BY_RECOVERY=1
fi
[ "$(maintenance_state)" = "1" ] || { echo 'MAINTENANCE_ENABLE_VERIFY=FAIL'; exit 23; }
echo 'MAINTENANCE_GUARD=PASS'

echo
echo '===== QUIESCE CRON ====='
systemctl stop moodle-cron.timer 2>/dev/null || true
ssh "$WEB02" 'systemctl stop moodle-cron.timer 2>/dev/null || true'
wait_cron_inactive_local || { echo 'WEB01_CRON_SERVICE_STILL_ACTIVE=YES'; exit 24; }
wait_cron_inactive_remote || { echo 'WEB02_CRON_SERVICE_STILL_ACTIVE=YES'; exit 25; }
echo 'CRON_QUIESCE=PASS'

echo
echo '===== VERIFY TWO-NODE FILE IDENTITY ====='
TREE1="$(tree_hash_local)"
TREE2="$(ssh "$WEB02" "cd '$MOODLE' && find local/digieranative local/digieraoffice local/worksheetlibrary mod/worksheetgrader -type f -print0 | LC_ALL=C sort -z | xargs -0 sha256sum | sha256sum | awk '{print \$1}'")"
echo "WEB01_TREE_SHA256=$TREE1"
echo "WEB02_TREE_SHA256=$TREE2"
[ "$TREE1" = "$TREE2" ] || { echo 'TWO_NODE_TREE_IDENTITY=FAIL'; exit 26; }
echo 'TWO_NODE_TREE_IDENTITY=PASS'

echo
echo '===== VERIFY POST-UPGRADE DB / SCHEMA / SERVICES ====='
cat > "$CHECK" <<'PHP'
<?php
define('CLI_SCRIPT', true);
require '/var/www/moodle/public/config.php';

$expectedversions = [
    'local_digieranative' => 2026090501,
    'local_digieraoffice' => 2026090501,
    'local_worksheetlibrary' => 2026090701,
    'mod_worksheetgrader' => 2026090701,
];
foreach ($expectedversions as $component => $expected) {
    $actual = (int)get_config($component, 'version');
    if ($actual !== $expected) {
        fwrite(STDERR, "PLUGIN_VERSION_MISMATCH={$component}:{$actual}:{$expected}\n");
        exit(2);
    }
    echo "PLUGIN_VERSION_{$component}=PASS:{$actual}\n";
}

$dbman = $DB->get_manager();
$table = new xmldb_table('wslib_version');
foreach (['nativejson', 'schemaversion', 'revision', 'renderedhtml'] as $name) {
    if (!$dbman->field_exists($table, new xmldb_field($name))) {
        fwrite(STDERR, "MISSING_FIELD={$name}\n");
        exit(3);
    }
    echo "FIELD_{$name}=PASS\n";
}

foreach (['local_worksheetlibrary_save_native_draft', 'mod_worksheetgrader_save_attempt'] as $function) {
    if (!$DB->record_exists('external_functions', ['name' => $function])) {
        fwrite(STDERR, "EXTERNAL_FUNCTION_MISSING={$function}\n");
        exit(4);
    }
    echo "EXTERNAL_FUNCTION_{$function}=PASS\n";
}

echo "SOURCE_SCHEMA_SERVICE_CHECK=PASS\n";
PHP
chmod 0644 "$CHECK"
run_moodle_local "$CHECK"
rm -f "$CHECK"

echo
echo '===== PURGE CACHES / RELOAD PHP ====='
run_moodle_local "$MOODLE/admin/cli/purge_caches.php"
ssh "$WEB02" "if id www-data >/dev/null 2>&1; then runuser -u www-data -- php '$MOODLE/admin/cli/purge_caches.php'; else php '$MOODLE/admin/cli/purge_caches.php'; fi"
for svc in php8.3-fpm php-fpm; do
  if systemctl is-active --quiet "$svc"; then systemctl reload "$svc"; echo "WEB01_FPM_RELOADED=$svc"; break; fi
done
ssh "$WEB02" 'for svc in php8.3-fpm php-fpm; do if systemctl is-active --quiet "$svc"; then systemctl reload "$svc"; echo "WEB02_FPM_RELOADED=$svc"; exit 0; fi; done; echo WEB02_FPM_RELOAD=SKIPPED'
echo 'CACHE_RELOAD_GATE=PASS'

echo
echo '===== FINAL TWO-NODE IDENTITY ====='
TREE1_FINAL="$(tree_hash_local)"
TREE2_FINAL="$(ssh "$WEB02" "cd '$MOODLE' && find local/digieranative local/digieraoffice local/worksheetlibrary mod/worksheetgrader -type f -print0 | LC_ALL=C sort -z | xargs -0 sha256sum | sha256sum | awk '{print \$1}'")"
echo "WEB01_TREE_SHA256_FINAL=$TREE1_FINAL"
echo "WEB02_TREE_SHA256_FINAL=$TREE2_FINAL"
[ "$TREE1_FINAL" = "$TREE2_FINAL" ] || { echo 'FINAL_TWO_NODE_TREE_IDENTITY=FAIL'; exit 27; }
echo 'FINAL_TWO_NODE_TREE_IDENTITY=PASS'

echo
echo '===== RESTORE RUNTIME STATE ====='
restore_runtime
if [ "$MAINT_BEFORE" = "0" ]; then
  [ "$(maintenance_state)" = "0" ] || { echo 'MAINTENANCE_RESTORE=FAIL'; exit 28; }
else
  [ "$(maintenance_state)" = "1" ] || { echo 'MAINTENANCE_PRESERVE=FAIL'; exit 29; }
fi
if [ "$RESTORE_CRON1" = "1" ]; then systemctl is-active --quiet moodle-cron.timer || { echo 'WEB01_CRON_TIMER_RESTORE=FAIL'; exit 30; }; fi
if [ "$RESTORE_CRON2" = "0" ]; then ! ssh "$WEB02" 'systemctl is-active --quiet moodle-cron.timer' || { echo 'WEB02_CRON_TIMER_PRESERVE=FAIL'; exit 31; }; fi
echo 'RUNTIME_STATE_RESTORED=PASS'

RECOVERY_DONE=1
trap - EXIT

echo
echo '===== FINAL SUMMARY ====='
echo 'PREVIEW_RECOVERY=PASS'
echo 'DB_UPGRADE_REPLAY=NO'
echo 'TWO_NODE_TREE_IDENTITY=PASS'
echo 'SOURCE_SCHEMA_SERVICE_CHECK=PASS'
echo 'CACHE_RELOAD_GATE=PASS'
echo 'FINAL_TWO_NODE_TREE_IDENTITY=PASS'
echo 'RUNTIME_STATE_RESTORED=PASS'
echo "WEB01_TREE_SHA256=$TREE1_FINAL"
echo "WEB02_TREE_SHA256=$TREE2_FINAL"
echo "MAINT_BEFORE_RECOVERY=$MAINT_BEFORE"
echo "RESTORE_CRON1=$RESTORE_CRON1"
echo "RESTORE_CRON2=$RESTORE_CRON2"
echo "LOG=$LOG"
echo 'NEXT=OPERATOR_SMOKE_TEST'
