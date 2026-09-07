#!/usr/bin/env bash
set -euo pipefail

ROOT="${MOODLE_ROOT:-/var/www/moodle/public}"
WEB02="${DIGIERA_WEB02:-root@10.0.10.12}"
EXPECTED_BRANCH="501"
EXPECTED_LOCAL_VERSION="2026090600"
EXPECTED_FILTER_VERSION="2026090600"
SSH_CONTROL="/tmp/digiera-stage-ssh-%C"
SSH_OPTS=(-o ControlMaster=auto -o ControlPersist=300 -o ControlPath="$SSH_CONTROL")
MAINTENANCE=0
CRON_STOPPED=0

fail() {
    echo "ERROR: $*" >&2
    exit 1
}

on_error() {
    local rc=$?
    echo
    echo "===== ACTIVATION FAILED (exit=$rc) =====" >&2
    if [ "$MAINTENANCE" -eq 1 ]; then
        echo "Maintenance mode is intentionally being LEFT ENABLED for safety." >&2
    fi
    if [ "$CRON_STOPPED" -eq 1 ]; then
        echo "moodle-cron.timer is intentionally being LEFT STOPPED for safety." >&2
    fi
    echo "Do not remove plugin code or alter DB manually before reviewing the failure." >&2
    exit "$rc"
}
trap on_error ERR

run_moodle_cli() {
    local script="$1"
    shift
    runuser -u www-data -- sh -c "cd '$ROOT' && exec /usr/bin/php '$script' \"\$@\"" sh "$@"
}

codeset_sha_local() {
    (
        cd "$ROOT"
        find local/digieramedia filter/digieramedia -type f -print0 \
            | sort -z | xargs -0 sha256sum
    ) | sha256sum | awk '{print $1}'
}

codeset_sha_remote() {
    ssh "${SSH_OPTS[@]}" "$WEB02" \
        "cd '$ROOT' && find local/digieramedia filter/digieramedia -type f -print0 | sort -z | xargs -0 sha256sum | sha256sum | awk '{print \\$1}'"
}

plugin_source_version() {
    local file="$1"
    sed -nE 's/^\$plugin->version[[:space:]]*=[[:space:]]*([0-9]+);.*/\1/p' "$file" | head -n1
}

preflight() {
    test "$(id -u)" -eq 0 || fail "Run activation as root on Web01"
    test -f "$ROOT/version.php" || fail "Moodle root invalid: $ROOT"
    test -f "$ROOT/local/digieramedia/version.php" || fail "local_digieramedia code not deployed on Web01"
    test -f "$ROOT/filter/digieramedia/version.php" || fail "filter_digieramedia code not deployed on Web01"

    local branch localver filterver
    branch="$(sed -nE "s/^\$branch[[:space:]]*=[[:space:]]*'([^']+)'.*/\1/p" "$ROOT/version.php" | head -n1)"
    [ "$branch" = "$EXPECTED_BRANCH" ] || fail "Web01 Moodle branch is $branch, expected $EXPECTED_BRANCH"
    localver="$(plugin_source_version "$ROOT/local/digieramedia/version.php")"
    filterver="$(plugin_source_version "$ROOT/filter/digieramedia/version.php")"
    [ "$localver" = "$EXPECTED_LOCAL_VERSION" ] || fail "Unexpected local_digieramedia source version: $localver"
    [ "$filterver" = "$EXPECTED_FILTER_VERSION" ] || fail "Unexpected filter_digieramedia source version: $filterver"

    echo "Opening/reusing SSH control connection to $WEB02 ..."
    ssh "${SSH_OPTS[@]}" "$WEB02" true

    ssh "${SSH_OPTS[@]}" "$WEB02" \
        "test -f '$ROOT/local/digieramedia/version.php' && test -f '$ROOT/filter/digieramedia/version.php'" \
        || fail "DIGIERA Media code is not deployed on Web02"

    local localsha remotesha
    localsha="$(codeset_sha_local)"
    remotesha="$(codeset_sha_remote)"
    echo "WEB01_CODESET_SHA256=$localsha"
    echo "WEB02_CODESET_SHA256=$remotesha"
    [ "$localsha" = "$remotesha" ] || fail "Web01/Web02 plugin code differs"

    runuser -u www-data -- test -w /mnt/moodledata \
        || fail "www-data cannot write /mnt/moodledata on Web01"
    ssh "${SSH_OPTS[@]}" "$WEB02" "runuser -u www-data -- test -w /mnt/moodledata" \
        || fail "www-data cannot write /mnt/moodledata on Web02"

    echo "PREFLIGHT=PASS"
}

wait_for_cron_idle() {
    local i
    for i in $(seq 1 90); do
        if ! systemctl is-active --quiet moodle-cron.service; then
            return 0
        fi
        sleep 1
    done
    fail "moodle-cron.service did not become idle within 90 seconds"
}

configure_filter() {
    local helper=/tmp/digiera-stage-enable-filter.php
    cat > "$helper" <<'PHP'
<?php

define('CLI_SCRIPT', true);
require $argv[1];
require_once($CFG->libdir . '/filterlib.php');

set_config('cdnbaseurl', 'https://cdn.digiera.vn', 'local_digieramedia');
set_config('pdfviewerurl', 'https://cdn.digiera.vn/pdfjs/web/viewer.html', 'local_digieramedia');
filter_set_global_state('digieramedia', TEXTFILTER_ON, 0);

$systemcontext = context_system::instance();
$record = $DB->get_record('filter_active', [
    'contextid' => $systemcontext->id,
    'filter' => 'digieramedia',
]);
if (!$record || (int)$record->active !== TEXTFILTER_ON) {
    fwrite(STDERR, "filter_digieramedia was not enabled globally\n");
    exit(2);
}

echo "FILTER_DIGIERAMEDIA=ON\n";
echo "CDN_BASE=" . get_config('local_digieramedia', 'cdnbaseurl') . "\n";
echo "PDF_VIEWER=" . get_config('local_digieramedia', 'pdfviewerurl') . "\n";
PHP
    chmod 0644 "$helper"
    runuser -u www-data -- php "$helper" "$ROOT/config.php"
    rm -f "$helper"
}

preflight

echo
 echo "===== STOP CRON TIMER ====="
systemctl stop moodle-cron.timer
CRON_STOPPED=1
wait_for_cron_idle

echo
 echo "===== ENABLE MAINTENANCE ====="
run_moodle_cli admin/cli/maintenance.php --enable
MAINTENANCE=1

echo
 echo "===== MOODLE UPGRADE ====="
run_moodle_cli admin/cli/upgrade.php --non-interactive

echo
 echo "===== VERIFY INSTALLED VERSIONS ====="
localdbver="$(run_moodle_cli admin/cli/cfg.php --component=local_digieramedia --name=version | tail -n1 | tr -d '\r')"
filterdbver="$(run_moodle_cli admin/cli/cfg.php --component=filter_digieramedia --name=version | tail -n1 | tr -d '\r')"
echo "LOCAL_DB_VERSION=$localdbver"
echo "FILTER_DB_VERSION=$filterdbver"
[ "$localdbver" = "$EXPECTED_LOCAL_VERSION" ] || fail "local_digieramedia DB version verification failed"
[ "$filterdbver" = "$EXPECTED_FILTER_VERSION" ] || fail "filter_digieramedia DB version verification failed"

echo
 echo "===== ENABLE PDF FILTER ====="
configure_filter

echo
 echo "===== PURGE CACHES ====="
run_moodle_cli admin/cli/purge_caches.php

echo
 echo "===== RELOAD PHP-FPM ON BOTH NODES ====="
systemctl reload php8.3-fpm
ssh "${SSH_OPTS[@]}" "$WEB02" "systemctl reload php8.3-fpm"

echo
 echo "===== DISABLE MAINTENANCE ====="
run_moodle_cli admin/cli/maintenance.php --disable
MAINTENANCE=0

echo
 echo "===== START CRON TIMER ====="
systemctl start moodle-cron.timer
CRON_STOPPED=0

echo
 echo "===== FINAL STATUS ====="
systemctl is-active nginx php8.3-fpm moodle-cron.timer
ssh "${SSH_OPTS[@]}" "$WEB02" "systemctl is-active nginx php8.3-fpm"

echo "ACTIVATION=PASS"
echo "NEXT=Run the stage PDF seed tool, paste its marker into a Moodle Page, and save."
