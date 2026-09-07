#!/usr/bin/env bash
set -Eeuo pipefail

EXPECTED_HOST="vm-c47e0dd9"
WEB02="${WEB02:-root@moodle-web02}"
EXPECTED_WEB02_HOST="${EXPECTED_WEB02_HOST:-moodle-web02}"
MOODLE="${MOODLE:-/var/www/moodle/public}"
PKGDIR="${PKGDIR:-/root/digiera-preview-hybrid-milestone/packages}"
ROOT="${ROOT:-/root/digiera-preview-controlled-deploy}"
STAMP="$(date +%Y%m%d-%H%M%S)"
RUNROOT="$ROOT/$STAMP"
STAGE1="$RUNROOT/stage-web01"
SNAP1="$RUNROOT/snapshot-web01"
REMOTE_ROOT="/root/digiera-preview-controlled-deploy/$STAMP"
STAGE2="$REMOTE_ROOT/stage-web02"
SNAP2="$REMOTE_ROOT/snapshot-web02"
LOG="$RUNROOT/deploy.log"

mkdir -p "$RUNROOT"
exec > >(tee -a "$LOG") 2>&1

declare -a NAMES=(
  "local_digieranative_moodle51_preview-v0.1.zip"
  "local_digieraoffice_moodle51_preview-v0.1.zip"
  "local_worksheetlibrary_moodle51_preview-v0.1.zip"
  "mod_worksheetgrader_moodle51_preview-v0.1.zip"
)
declare -a HASHES=(
  "96e3c31fbf32ac714532dd9ba8c4902a3cb6a6351d08877bb5851aeeb8c1be4b"
  "701e39f090a9c45d8bee33f72fd9e9e0078335ab906efe9f9ed9ab26b2789a63"
  "e817d73abf1965a2d6e7096e2c2978f91b0397b76d999c424fadc12fb360fb2d"
  "f2c1c8a71b773ef63fadebd2185ea2c7515a85abfa01ca100d17eafab9c8aaf4"
)
declare -a ROOTS=(
  "digieranative"
  "digieraoffice"
  "worksheetlibrary"
  "worksheetgrader"
)
declare -a DESTS=(
  "$MOODLE/local/digieranative"
  "$MOODLE/local/digieraoffice"
  "$MOODLE/local/worksheetlibrary"
  "$MOODLE/mod/worksheetgrader"
)
declare -a LABELS=(
  "local_digieranative"
  "local_digieraoffice"
  "local_worksheetlibrary"
  "mod_worksheetgrader"
)

LIVE_MUTATION_STARTED=0
DB_UPGRADE_STARTED=0
DB_UPGRADE_DONE=0
MAINT_WAS_ENABLED=0
MAINTENANCE_ENABLED_BY_RUN=0
CRON1_WAS_ACTIVE=0
CRON2_WAS_ACTIVE=0

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

restore_cron_state() {
  if [ "$CRON1_WAS_ACTIVE" -eq 1 ]; then
    systemctl start moodle-cron.timer || true
  fi
  if [ "$CRON2_WAS_ACTIVE" -eq 1 ]; then
    ssh "$WEB02" 'systemctl start moodle-cron.timer' || true
  fi
}

snapshot_local() {
  mkdir -p "$SNAP1"
  local i target parent base label
  for i in "${!DESTS[@]}"; do
    target="${DESTS[$i]}"
    label="${LABELS[$i]}"
    parent="$(dirname "$target")"
    base="$(basename "$target")"
    if [ -d "$target" ]; then
      tar --numeric-owner -C "$parent" -czf "$SNAP1/$label.tar.gz" "$base"
    else
      : > "$SNAP1/$label.absent"
    fi
  done
  (cd "$SNAP1" && sha256sum *.tar.gz 2>/dev/null || true) > "$SNAP1/SHA256SUMS.txt"
}

snapshot_remote() {
  ssh "$WEB02" bash -s -- "$MOODLE" "$SNAP2" <<'EOS'
set -Eeuo pipefail
MOODLE="$1"
SNAP="$2"
mkdir -p "$SNAP"
paths=(
  "$MOODLE/local/digieranative"
  "$MOODLE/local/digieraoffice"
  "$MOODLE/local/worksheetlibrary"
  "$MOODLE/mod/worksheetgrader"
)
labels=(
  "local_digieranative"
  "local_digieraoffice"
  "local_worksheetlibrary"
  "mod_worksheetgrader"
)
for i in "${!paths[@]}"; do
  target="${paths[$i]}"
  label="${labels[$i]}"
  parent="$(dirname "$target")"
  base="$(basename "$target")"
  if [ -d "$target" ]; then
    tar --numeric-owner -C "$parent" -czf "$SNAP/$label.tar.gz" "$base"
  else
    : > "$SNAP/$label.absent"
  fi
done
(cd "$SNAP" && sha256sum *.tar.gz 2>/dev/null || true) > "$SNAP/SHA256SUMS.txt"
EOS
}

rollback_local_files() {
  local i target parent label
  for i in "${!DESTS[@]}"; do
    target="${DESTS[$i]}"
    label="${LABELS[$i]}"
    parent="$(dirname "$target")"
    rm -rf "$target"
    if [ -f "$SNAP1/$label.tar.gz" ]; then
      tar --numeric-owner -C "$parent" -xzf "$SNAP1/$label.tar.gz"
    fi
  done
}

rollback_remote_files() {
  ssh "$WEB02" bash -s -- "$MOODLE" "$SNAP2" <<'EOS'
set -Eeuo pipefail
MOODLE="$1"
SNAP="$2"
paths=(
  "$MOODLE/local/digieranative"
  "$MOODLE/local/digieraoffice"
  "$MOODLE/local/worksheetlibrary"
  "$MOODLE/mod/worksheetgrader"
)
labels=(
  "local_digieranative"
  "local_digieraoffice"
  "local_worksheetlibrary"
  "mod_worksheetgrader"
)
for i in "${!paths[@]}"; do
  target="${paths[$i]}"
  label="${labels[$i]}"
  parent="$(dirname "$target")"
  rm -rf "$target"
  if [ -f "$SNAP/$label.tar.gz" ]; then
    tar --numeric-owner -C "$parent" -xzf "$SNAP/$label.tar.gz"
  fi
done
EOS
}

disable_maintenance_if_safe() {
  if [ "$MAINTENANCE_ENABLED_BY_RUN" -eq 1 ] && [ "$MAINT_WAS_ENABLED" -eq 0 ]; then
    run_moodle_local "$MOODLE/admin/cli/maintenance.php" --disable || true
  fi
}

on_exit() {
  rc=$?
  if [ "$rc" -eq 0 ]; then
    return 0
  fi

  echo
  echo '===== DEPLOYMENT STOPPED ====='
  echo "EXIT_CODE=$rc"
  echo "LOG=$LOG"

  if [ "$LIVE_MUTATION_STARTED" -eq 1 ] && [ "$DB_UPGRADE_STARTED" -eq 0 ]; then
    echo 'PRE_DB_FAILURE=YES'
    echo 'FILE_ROLLBACK=START'
    rollback_local_files || true
    rollback_remote_files || true
    echo 'FILE_ROLLBACK=ATTEMPTED'
    disable_maintenance_if_safe
    restore_cron_state
    echo 'MAINTENANCE_RESTORED=YES'
  elif [ "$DB_UPGRADE_STARTED" -eq 1 ]; then
    echo 'DB_UPGRADE_STARTED=YES'
    echo 'AUTO_DB_ROLLBACK=PROHIBITED'
    echo 'MAINTENANCE_REMAINS=ENABLED'
    echo 'CRON_REMAINS=STOPPED'
  else
    disable_maintenance_if_safe
    restore_cron_state
  fi

  echo 'PREVIEW_DEPLOY=STOPPED'
}
trap on_exit EXIT

echo '============================================================'
echo ' DIGIERA PREVIEW V0.1 - CONTROLLED TWO-NODE DEPLOYMENT'
echo '============================================================'
date -Is
echo "HOSTNAME=$(hostname)"
echo "WEB02=$WEB02"
echo "MOODLE=$MOODLE"
echo "PKGDIR=$PKGDIR"
echo "RUNROOT=$RUNROOT"

echo
echo '===== PREFLIGHT HOST / SSH / MOODLE ====='
[ "$(hostname)" = "$EXPECTED_HOST" ] || {
  echo "WRONG_HOST=$(hostname)"
  echo "EXPECTED_HOST=$EXPECTED_HOST"
  exit 20
}
echo 'WEB01_HOST_GUARD=PASS'

ssh -o BatchMode=yes -o ConnectTimeout=10 "$WEB02" 'true'
WEB02_HOST="$(ssh "$WEB02" hostname)"
[ "$WEB02_HOST" = "$EXPECTED_WEB02_HOST" ] || {
  echo "WRONG_WEB02_HOST=$WEB02_HOST"
  echo "EXPECTED_WEB02_HOST=$EXPECTED_WEB02_HOST"
  exit 21
}
echo "WEB02_HOST=$WEB02_HOST"
echo 'WEB02_SSH_GUARD=PASS'

for f in \
  "$MOODLE/config.php" \
  "$MOODLE/admin/cli/maintenance.php" \
  "$MOODLE/admin/cli/upgrade.php" \
  "$MOODLE/admin/cli/purge_caches.php"; do
  [ -f "$f" ] || { echo "MISSING_WEB01=$f"; exit 22; }
done
ssh "$WEB02" "test -f '$MOODLE/config.php' && test -f '$MOODLE/admin/cli/upgrade.php'"
echo 'MOODLE_PATH_GUARD=PASS'

echo
echo '===== VERIFY EXACT PREVIEW PACKAGES ====='
for i in "${!NAMES[@]}"; do
  file="$PKGDIR/${NAMES[$i]}"
  [ -f "$file" ] || { echo "MISSING_PACKAGE=$file"; exit 23; }
  actual="$(sha256sum "$file" | awk '{print $1}')"
  [ "$actual" = "${HASHES[$i]}" ] || {
    echo "PACKAGE_SHA_MISMATCH=${NAMES[$i]}"
    echo "EXPECTED=${HASHES[$i]}"
    echo "ACTUAL=$actual"
    exit 24
  }
  firstroots="$(unzip -Z1 "$file" | awk -F/ 'NF{print $1}' | sort -u)"
  [ "$firstroots" = "${ROOTS[$i]}" ] || {
    echo "BAD_PACKAGE_ROOT=${NAMES[$i]}"
    echo "EXPECTED_ROOT=${ROOTS[$i]}"
    echo "ACTUAL_ROOTS=$firstroots"
    exit 25
  }
  echo "PACKAGE_SHA_PASS=${NAMES[$i]}"
done
echo 'PACKAGE_GUARD=PASS'

echo
echo '===== ASSEMBLE STAGES ON BOTH NODES ====='
rm -rf "$STAGE1"
mkdir -p "$STAGE1"
for i in "${!NAMES[@]}"; do
  unzip -q "$PKGDIR/${NAMES[$i]}" -d "$STAGE1"
  [ -f "$STAGE1/${ROOTS[$i]}/version.php" ] || {
    echo "STAGE1_VERSION_MISSING=${ROOTS[$i]}"
    exit 26
  }
done
find "$STAGE1" -type d -name node_modules -prune -exec rm -rf {} +
echo 'WEB01_STAGE=PASS'

ssh "$WEB02" "rm -rf '$STAGE2' && mkdir -p '$STAGE2'"
for i in "${!NAMES[@]}"; do
  scp -q "$PKGDIR/${NAMES[$i]}" "$WEB02:$STAGE2/${NAMES[$i]}"
  ssh "$WEB02" "echo '${HASHES[$i]}  $STAGE2/${NAMES[$i]}' | sha256sum -c -"
  ssh "$WEB02" "unzip -q '$STAGE2/${NAMES[$i]}' -d '$STAGE2' && test -f '$STAGE2/${ROOTS[$i]}/version.php'"
done
ssh "$WEB02" "find '$STAGE2' -type d -name node_modules -prune -exec rm -rf {} +"
echo 'WEB02_STAGE=PASS'

echo
echo '===== SNAPSHOT CURRENT PLUGIN TREES ====='
snapshot_local
snapshot_remote
echo "WEB01_SNAPSHOT=$SNAP1"
echo "WEB02_SNAPSHOT=$SNAP2"
echo 'SNAPSHOT_GATE=PASS'

echo
echo '===== CAPTURE RUNTIME STATE ====='
MAINT_WAS_ENABLED="$(maintenance_state)"
if systemctl is-active --quiet moodle-cron.timer; then CRON1_WAS_ACTIVE=1; fi
if ssh "$WEB02" 'systemctl is-active --quiet moodle-cron.timer'; then CRON2_WAS_ACTIVE=1; fi
echo "MAINT_WAS_ENABLED=$MAINT_WAS_ENABLED"
echo "CRON1_WAS_ACTIVE=$CRON1_WAS_ACTIVE"
echo "CRON2_WAS_ACTIVE=$CRON2_WAS_ACTIVE"

echo
echo '===== ENTER MAINTENANCE / QUIESCE CRON ====='
if [ "$MAINT_WAS_ENABLED" -eq 0 ]; then
  run_moodle_local "$MOODLE/admin/cli/maintenance.php" --enable
  MAINTENANCE_ENABLED_BY_RUN=1
fi
systemctl stop moodle-cron.timer 2>/dev/null || true
ssh "$WEB02" 'systemctl stop moodle-cron.timer 2>/dev/null || true'

for n in $(seq 1 60); do
  if ! systemctl is-active --quiet moodle-cron.service; then break; fi
  sleep 2
done
if systemctl is-active --quiet moodle-cron.service; then
  echo 'WEB01_CRON_SERVICE_STILL_ACTIVE=YES'
  exit 27
fi

for n in $(seq 1 60); do
  if ! ssh "$WEB02" 'systemctl is-active --quiet moodle-cron.service'; then break; fi
  sleep 2
done
if ssh "$WEB02" 'systemctl is-active --quiet moodle-cron.service'; then
  echo 'WEB02_CRON_SERVICE_STILL_ACTIVE=YES'
  exit 28
fi
echo 'MAINTENANCE_AND_CRON_QUIESCE=PASS'

echo
echo '===== DEPLOY IDENTICAL PLUGIN TREES ====='
LIVE_MUTATION_STARTED=1

rsync -a --delete "$STAGE1/digieranative/" "$MOODLE/local/digieranative/"
rsync -a --delete "$STAGE1/digieraoffice/" "$MOODLE/local/digieraoffice/"
rsync -a --delete "$STAGE1/worksheetlibrary/" "$MOODLE/local/worksheetlibrary/"
rsync -a --delete "$STAGE1/worksheetgrader/" "$MOODLE/mod/worksheetgrader/"
chown -R --reference="$MOODLE/local" \
  "$MOODLE/local/digieranative" \
  "$MOODLE/local/digieraoffice" \
  "$MOODLE/local/worksheetlibrary"
chown -R --reference="$MOODLE/mod" "$MOODLE/mod/worksheetgrader"
echo 'WEB01_FILE_DEPLOY=PASS'

ssh "$WEB02" bash -s -- "$MOODLE" "$STAGE2" <<'EOS'
set -Eeuo pipefail
MOODLE="$1"
STAGE="$2"
rsync -a --delete "$STAGE/digieranative/" "$MOODLE/local/digieranative/"
rsync -a --delete "$STAGE/digieraoffice/" "$MOODLE/local/digieraoffice/"
rsync -a --delete "$STAGE/worksheetlibrary/" "$MOODLE/local/worksheetlibrary/"
rsync -a --delete "$STAGE/worksheetgrader/" "$MOODLE/mod/worksheetgrader/"
chown -R --reference="$MOODLE/local" \
  "$MOODLE/local/digieranative" \
  "$MOODLE/local/digieraoffice" \
  "$MOODLE/local/worksheetlibrary"
chown -R --reference="$MOODLE/mod" "$MOODLE/mod/worksheetgrader"
EOS
echo 'WEB02_FILE_DEPLOY=PASS'

echo
echo '===== PRE-DB LIVE STATIC CHECK ====='
count1=0
while IFS= read -r -d '' f; do php -l "$f" >/dev/null; count1=$((count1+1)); done < <(
  find \
    "$MOODLE/local/digieranative" \
    "$MOODLE/local/digieraoffice" \
    "$MOODLE/local/worksheetlibrary" \
    "$MOODLE/mod/worksheetgrader" \
    -name '*.php' -print0
)
echo "WEB01_PHP_LINT_FILES=$count1"

ssh "$WEB02" bash -s -- "$MOODLE" <<'EOS'
set -Eeuo pipefail
MOODLE="$1"
count=0
while IFS= read -r -d '' f; do php -l "$f" >/dev/null; count=$((count+1)); done < <(
  find \
    "$MOODLE/local/digieranative" \
    "$MOODLE/local/digieraoffice" \
    "$MOODLE/local/worksheetlibrary" \
    "$MOODLE/mod/worksheetgrader" \
    -name '*.php' -print0
)
echo "WEB02_PHP_LINT_FILES=$count"
EOS
echo 'LIVE_STATIC_GATE=PASS'

tree_hash_local() {
  (
    cd "$MOODLE"
    find \
      local/digieranative \
      local/digieraoffice \
      local/worksheetlibrary \
      mod/worksheetgrader \
      -type f -print0 \
      | LC_ALL=C sort -z \
      | xargs -0 sha256sum \
      | sha256sum \
      | awk '{print $1}'
  )
}

TREE1="$(tree_hash_local)"
TREE2="$(ssh "$WEB02" "cd '$MOODLE' && find local/digieranative local/digieraoffice local/worksheetlibrary mod/worksheetgrader -type f -print0 | LC_ALL=C sort -z | xargs -0 sha256sum | sha256sum | awk '{print \$1}'")"
echo "WEB01_TREE_SHA256=$TREE1"
echo "WEB02_TREE_SHA256=$TREE2"
[ "$TREE1" = "$TREE2" ] || {
  echo 'TWO_NODE_TREE_IDENTITY=FAIL'
  exit 29
}
echo 'TWO_NODE_TREE_IDENTITY=PASS'

echo
echo '===== MOODLE DB UPGRADE - WEB01 ONLY ====='
DB_UPGRADE_STARTED=1
run_moodle_local "$MOODLE/admin/cli/upgrade.php" --non-interactive
DB_UPGRADE_DONE=1
echo 'DB_UPGRADE_ONCE=PASS'

echo
echo '===== TARGETED POST-UPGRADE SCHEMA / SERVICE CHECK ====='
SCHEMA_CHECK="/tmp/digiera-preview-schema-check-${STAMP}-$$.php"
cat > "$SCHEMA_CHECK" <<'PHP'
<?php
define('CLI_SCRIPT', true);
require '/var/www/moodle/public/config.php';

$dbman = $DB->get_manager();
$table = new xmldb_table('wslib_version');
$fields = ['nativejson', 'schemaversion', 'revision', 'renderedhtml'];

foreach ($fields as $name) {
    if (!$dbman->field_exists($table, new xmldb_field($name))) {
        fwrite(STDERR, "MISSING_FIELD={$name}\n");
        exit(2);
    }
    echo "FIELD_{$name}=PASS\n";
}

$components = [
    'local_digieranative',
    'local_digieraoffice',
    'local_worksheetlibrary',
    'mod_worksheetgrader',
];
foreach ($components as $component) {
    $version = get_config($component, 'version');
    if (empty($version)) {
        fwrite(STDERR, "PLUGIN_VERSION_MISSING={$component}\n");
        exit(3);
    }
    echo "PLUGIN_{$component}_VERSION={$version}\n";
}

$functions = [
    'local_worksheetlibrary_save_native_draft',
    'mod_worksheetgrader_save_attempt',
];
foreach ($functions as $function) {
    if (!$DB->record_exists('external_functions', ['name' => $function])) {
        fwrite(STDERR, "EXTERNAL_FUNCTION_MISSING={$function}\n");
        exit(4);
    }
    echo "EXTERNAL_FUNCTION_{$function}=PASS\n";
}

echo "TARGETED_SCHEMA_SERVICE_CHECK=PASS\n";
PHP
chmod 0644 "$SCHEMA_CHECK"
run_moodle_local "$SCHEMA_CHECK"
rm -f "$SCHEMA_CHECK"

echo
echo '===== PURGE CACHES / RELOAD PHP ====='
run_moodle_local "$MOODLE/admin/cli/purge_caches.php"
ssh "$WEB02" "if id www-data >/dev/null 2>&1; then runuser -u www-data -- php '$MOODLE/admin/cli/purge_caches.php'; else php '$MOODLE/admin/cli/purge_caches.php'; fi"

reload_fpm_local() {
  local svc
  for svc in php8.3-fpm php-fpm; do
    if systemctl is-active --quiet "$svc"; then
      systemctl reload "$svc"
      echo "WEB01_FPM_RELOADED=$svc"
      return 0
    fi
  done
  echo 'WEB01_FPM_RELOAD=SKIPPED_NO_ACTIVE_UNIT'
}
reload_fpm_local

ssh "$WEB02" 'for svc in php8.3-fpm php-fpm; do if systemctl is-active --quiet "$svc"; then systemctl reload "$svc"; echo "WEB02_FPM_RELOADED=$svc"; exit 0; fi; done; echo "WEB02_FPM_RELOAD=SKIPPED_NO_ACTIVE_UNIT"'
echo 'CACHE_RELOAD_GATE=PASS'

echo
echo '===== FINAL TWO-NODE IDENTITY ====='
TREE1_FINAL="$(tree_hash_local)"
TREE2_FINAL="$(ssh "$WEB02" "cd '$MOODLE' && find local/digieranative local/digieraoffice local/worksheetlibrary mod/worksheetgrader -type f -print0 | LC_ALL=C sort -z | xargs -0 sha256sum | sha256sum | awk '{print \$1}'")"
echo "WEB01_TREE_SHA256_FINAL=$TREE1_FINAL"
echo "WEB02_TREE_SHA256_FINAL=$TREE2_FINAL"
[ "$TREE1_FINAL" = "$TREE2_FINAL" ] || {
  echo 'FINAL_TWO_NODE_TREE_IDENTITY=FAIL'
  exit 30
}
echo 'FINAL_TWO_NODE_TREE_IDENTITY=PASS'

echo
echo '===== RESTORE RUNTIME STATE ====='
restore_cron_state
if [ "$MAINT_WAS_ENABLED" -eq 0 ]; then
  run_moodle_local "$MOODLE/admin/cli/maintenance.php" --disable
  MAINTENANCE_ENABLED_BY_RUN=0
fi
echo 'RUNTIME_STATE_RESTORED=PASS'

trap - EXIT

echo
echo '===== FINAL SUMMARY ====='
echo 'PREVIEW_DEPLOY=PASS'
echo 'WEB01=vm-c47e0dd9'
echo "WEB02=$WEB02_HOST"
echo 'PACKAGE_GUARD=PASS'
echo 'SNAPSHOT_GATE=PASS'
echo 'TWO_NODE_TREE_IDENTITY=PASS'
echo 'DB_UPGRADE_ONCE=PASS'
echo 'TARGETED_SCHEMA_SERVICE_CHECK=PASS'
echo 'CACHE_RELOAD_GATE=PASS'
echo 'FINAL_TWO_NODE_TREE_IDENTITY=PASS'
echo "WEB01_TREE_SHA256=$TREE1_FINAL"
echo "WEB02_TREE_SHA256=$TREE2_FINAL"
echo "WEB01_SNAPSHOT=$SNAP1"
echo "WEB02_SNAPSHOT=$SNAP2"
echo "LOG=$LOG"
echo 'NEXT=OPERATOR_SMOKE_TEST'
