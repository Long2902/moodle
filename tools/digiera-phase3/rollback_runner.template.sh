#!/usr/bin/env bash
set -Eeuo pipefail

BOUND_SOURCE_SHA="__SOURCE_SHA__"
BOUND_PACKAGE_SHA="__PACKAGE_SHA__"
BOUND_MANIFEST_SHA="__MANIFEST_SHA__"
EXPECTED_HOST="vm-c47e0dd9"
BOUND_WEB02_IP="10.0.10.12"
BOUND_MOODLE_ROOT="/var/www/moodle/public"
STATE_FILE="/root/digiera-tiptap-v1-deploy-state.env"

say() { printf '%s\n' "$*"; }
die() { say "ERROR: $*" >&2; exit 1; }

MODE="rollback"
WORKDIR="$(pwd)"
if [ "${1:-}" = "--self-check" ]; then
    test "${#BOUND_SOURCE_SHA}" -eq 40
    test "${#BOUND_PACKAGE_SHA}" -eq 64
    test "${#BOUND_MANIFEST_SHA}" -eq 64
    case "$BOUND_SOURCE_SHA$BOUND_PACKAGE_SHA$BOUND_MANIFEST_SHA" in
        *"__"*) exit 1 ;;
    esac
    say "ROLLBACK_RUNNER_BINDING=PASS"
    exit 0
elif [ "${1:-}" = "--auto" ]; then
    MODE="auto"
    WORKDIR="${2:-$(pwd)}"
elif [ -n "${1:-}" ]; then
    WORKDIR="$1"
fi

[ "${EUID:-$(id -u)}" -eq 0 ] || die "Run as root"
[ "$(hostname)" = "$EXPECTED_HOST" ] || die "This rollback must execute on $EXPECTED_HOST"
[ -f "$STATE_FILE" ] || die "Missing deploy state: $STATE_FILE"
# shellcheck disable=SC1090
source "$STATE_FILE"

[ "$BOUND_SOURCE_SHA" = "${EXPECTED_SOURCE_SHA:-}" ] || die "State/source binding mismatch"
[ "$BOUND_PACKAGE_SHA" = "${EXPECTED_PACKAGE_SHA:-}" ] || die "State/package binding mismatch"
[ "$BOUND_MANIFEST_SHA" = "${EXPECTED_MANIFEST_SHA:-}" ] || die "State/manifest binding mismatch"
[ "${WEB02_IP:-}" = "$BOUND_WEB02_IP" ] || die "Unexpected Web02 in state"
[ "${MOODLE_ROOT:-}" = "$BOUND_MOODLE_ROOT" ] || die "Unexpected Moodle root in state"
[ -f "${BACKUP_WEB01:-}" ] || die "Missing Web01 backup"
ssh -o BatchMode=yes -o ConnectTimeout=8 "root@$WEB02_IP" "test -f '${BACKUP_WEB02:-}'" || die "Missing Web02 backup"

restore_runtime_state() {
    if [ "${MAINT_WAS:-0}" = "0" ]; then
        php "$MOODLE_ROOT/admin/cli/maintenance.php" --disable >/dev/null 2>&1 || true
    fi
    if [ "${CRON_WAS:-inactive}" = "active" ]; then
        systemctl start moodle-cron.timer >/dev/null 2>&1 || true
    fi
}

trap 'restore_runtime_state' EXIT
if [ "${MAINT_WAS:-0}" = "0" ]; then
    php "$MOODLE_ROOT/admin/cli/maintenance.php" --enable >/dev/null || true
fi
if [ "${CRON_WAS:-inactive}" = "active" ]; then
    systemctl stop moodle-cron.timer >/dev/null || true
fi

say "===== ROLLBACK WEB01 ====="
rm -rf "$MOODLE_ROOT/local/digieranative" "$MOODLE_ROOT/local/worksheetlibrary" "$MOODLE_ROOT/mod/worksheetgrader"
tar -C "$MOODLE_ROOT" -xzf "$BACKUP_WEB01"

say "===== ROLLBACK WEB02 ====="
ssh -o BatchMode=yes "root@$WEB02_IP" "rm -rf '$MOODLE_ROOT/local/digieranative' '$MOODLE_ROOT/local/worksheetlibrary' '$MOODLE_ROOT/mod/worksheetgrader'; tar -C '$MOODLE_ROOT' -xzf '$BACKUP_WEB02'"

say "===== VERIFY RESTORED PHP ====="
while IFS= read -r -d '' f; do php -l "$f" >/dev/null; done < <(find "$MOODLE_ROOT/local/digieranative" "$MOODLE_ROOT/local/worksheetlibrary" "$MOODLE_ROOT/mod/worksheetgrader" -name '*.php' -print0)
ssh -o BatchMode=yes "root@$WEB02_IP" "find '$MOODLE_ROOT/local/digieranative' '$MOODLE_ROOT/local/worksheetlibrary' '$MOODLE_ROOT/mod/worksheetgrader' -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null"

php "$MOODLE_ROOT/admin/cli/purge_caches.php" >/dev/null || true
ssh -o BatchMode=yes "root@$WEB02_IP" "php '$MOODLE_ROOT/admin/cli/purge_caches.php' >/dev/null || true"
if systemctl is-active --quiet php8.3-fpm; then systemctl reload php8.3-fpm || true; fi
ssh -o BatchMode=yes "root@$WEB02_IP" "if systemctl is-active --quiet php8.3-fpm; then systemctl reload php8.3-fpm || true; fi"

restore_runtime_state
trap - EXIT

umask 077
{
    printf 'EXPECTED_SOURCE_SHA=%q\n' "$BOUND_SOURCE_SHA"
    printf 'EXPECTED_PACKAGE_SHA=%q\n' "$BOUND_PACKAGE_SHA"
    printf 'EXPECTED_MANIFEST_SHA=%q\n' "$BOUND_MANIFEST_SHA"
    printf 'WEB02_IP=%q\n' "$WEB02_IP"
    printf 'MOODLE_ROOT=%q\n' "$MOODLE_ROOT"
    printf 'RUN_ID=%q\n' "${RUN_ID:-unknown}"
    printf 'BACKUP_WEB01=%q\n' "$BACKUP_WEB01"
    printf 'BACKUP_WEB02=%q\n' "$BACKUP_WEB02"
    printf 'MAINT_WAS=%q\n' "${MAINT_WAS:-0}"
    printf 'CRON_WAS=%q\n' "${CRON_WAS:-inactive}"
    printf 'DEPLOY_STATUS=%q\n' "ROLLED_BACK"
} > "$STATE_FILE"

say "===== ROLLBACK SUMMARY ====="
say "SOURCE_COMMIT_SHA=$BOUND_SOURCE_SHA"
say "PACKAGE_SHA256=$BOUND_PACKAGE_SHA"
say "ROLLBACK_STATUS=PASS"
