#!/usr/bin/env bash
set -Eeuo pipefail

EXPECTED_SOURCE_SHA="__SOURCE_SHA__"
EXPECTED_PACKAGE_SHA="__PACKAGE_SHA__"
EXPECTED_MANIFEST_SHA="__MANIFEST_SHA__"
PACKAGE_FILE="__PACKAGE_FILE__"
MANIFEST_FILE="__MANIFEST_FILE__"
EXPECTED_HOST="vm-c47e0dd9"
WEB02_IP="10.0.10.12"
MOODLE_ROOT="/var/www/moodle/public"
STATE_FILE="/root/digiera-tiptap-v1-deploy-state.env"

say() { printf '%s\n' "$*"; }
die() { say "ERROR: $*" >&2; exit 1; }
sha256_file() { sha256sum "$1" | awk '{print $1}'; }
manifest_get() { awk -F= -v key="$1" '$1==key {sub(/^[^=]*=/, ""); print; exit}' "$MANIFEST"; }

tree_hash() {
    local root="$1"
    (
        cd "$root"
        find . -type f -print0 | LC_ALL=C sort -z | xargs -0 sha256sum | sha256sum | awk '{print $1}'
    )
}

verify_package() {
    test -f "$PACKAGE" || die "Missing package: $PACKAGE"
    test -f "$MANIFEST" || die "Missing manifest: $MANIFEST"
    test "$(sha256_file "$PACKAGE")" = "$EXPECTED_PACKAGE_SHA" || die "Package SHA256 mismatch"
    test "$(sha256_file "$MANIFEST")" = "$EXPECTED_MANIFEST_SHA" || die "Manifest SHA256 mismatch"
    test "$(manifest_get SOURCE_COMMIT_SHA)" = "$EXPECTED_SOURCE_SHA" || die "Manifest source SHA mismatch"
    test "$(manifest_get PACKAGE_SHA256)" = "$EXPECTED_PACKAGE_SHA" || die "Manifest package SHA mismatch"
    test "$(manifest_get DB_UPGRADE_REQUIRED)" = "NO" || die "Candidate unexpectedly requires DB upgrade"

    python3 - "$PACKAGE" <<'PY'
import sys, tarfile
p = sys.argv[1]
allowed = (
    'public/local/digieranative',
    'public/local/worksheetlibrary',
    'public/mod/worksheetgrader',
)
with tarfile.open(p, 'r:gz') as tf:
    members = tf.getmembers()
    if not members:
        raise SystemExit('empty candidate archive')
    for m in members:
        name = m.name.rstrip('/')
        if name.startswith('/') or '..' in name.split('/'):
            raise SystemExit(f'unsafe archive path: {m.name}')
        if m.issym() or m.islnk():
            raise SystemExit(f'links are not allowed in candidate: {m.name}')
        if not any(name == root or name.startswith(root + '/') for root in allowed):
            raise SystemExit(f'path outside approved plugin roots: {m.name}')
print('PACKAGE_PATH_SAFETY=PASS')
PY

    local checkdir
    checkdir=$(mktemp -d)
    tar -xzf "$PACKAGE" -C "$checkdir"
    test "$(tree_hash "$checkdir/public/local/digieranative")" = "$(manifest_get TREE_HASH_DIGIERANATIVE)" || die "digieranative tree hash mismatch"
    test "$(tree_hash "$checkdir/public/local/worksheetlibrary")" = "$(manifest_get TREE_HASH_WORKSHEETLIBRARY)" || die "worksheetlibrary tree hash mismatch"
    test "$(tree_hash "$checkdir/public/mod/worksheetgrader")" = "$(manifest_get TREE_HASH_WORKSHEETGRADER)" || die "worksheetgrader tree hash mismatch"
    test "$(sha256_file "$checkdir/public/local/digieranative/amd/build/native_editor.min.js")" = "$(manifest_get NATIVE_AMD_SHA256)" || die "Native AMD hash mismatch"
    rm -rf "$checkdir"
    say "PACKAGE_BINDING=PASS"
}

MODE="deploy"
WORKDIR="$(pwd)"
if [ "${1:-}" = "--verify-package-only" ]; then
    MODE="verify"
    WORKDIR="${2:-$(pwd)}"
elif [ -n "${1:-}" ]; then
    WORKDIR="$1"
fi
PACKAGE="$WORKDIR/$PACKAGE_FILE"
MANIFEST="$WORKDIR/$MANIFEST_FILE"
ROLLBACK_RUNNER="$WORKDIR/rollback.sh"

verify_package
if [ "$MODE" = "verify" ]; then
    say "DEPLOY_RUNNER_PACKAGE_VERIFY=PASS"
    exit 0
fi

[ "${EUID:-$(id -u)}" -eq 0 ] || die "Run as root"
[ "$(hostname)" = "$EXPECTED_HOST" ] || die "This runner must execute on $EXPECTED_HOST"
for cmd in php tar rsync sha256sum ssh scp date systemctl awk find sort xargs python3; do
    command -v "$cmd" >/dev/null || die "Missing required command: $cmd"
done
[ -d "$MOODLE_ROOT" ] || die "Missing Moodle root: $MOODLE_ROOT"
[ -f "$MOODLE_ROOT/config.php" ] || die "Missing Moodle config.php"
ssh -o BatchMode=yes -o ConnectTimeout=8 "root@$WEB02_IP" "test -d '$MOODLE_ROOT' && test -f '$MOODLE_ROOT/config.php'" || die "Web02 SSH preflight failed"

for rel in local/digieranative local/worksheetlibrary mod/worksheetgrader; do
    [ -d "$MOODLE_ROOT/$rel" ] || die "Missing Web01 plugin dir: $rel"
    ssh -o BatchMode=yes "root@$WEB02_IP" "test -d '$MOODLE_ROOT/$rel'" || die "Missing Web02 plugin dir: $rel"
done

MAINT_WAS=$(php -r "define('CLI_SCRIPT', true); require '$MOODLE_ROOT/config.php'; echo empty(get_config('core','maintenance_enabled')) ? '0' : '1';")
if systemctl is-active --quiet moodle-cron.timer; then CRON_WAS="active"; else CRON_WAS="inactive"; fi
RUN_ID="$(date -u +%Y%m%dT%H%M%SZ)"
BACKUP_WEB01="/root/digiera-tiptap-v1-predeploy-${RUN_ID}-web01.tar.gz"
BACKUP_WEB02="/root/digiera-tiptap-v1-predeploy-${RUN_ID}-web02.tar.gz"
STAGE_WEB01="/root/digiera-tiptap-v1-stage-${RUN_ID}"
STAGE_WEB02="/root/digiera-tiptap-v1-stage-${RUN_ID}"
REMOTE_PACKAGE="/root/$PACKAGE_FILE"

write_state() {
    umask 077
    {
        printf 'EXPECTED_SOURCE_SHA=%q\n' "$EXPECTED_SOURCE_SHA"
        printf 'EXPECTED_PACKAGE_SHA=%q\n' "$EXPECTED_PACKAGE_SHA"
        printf 'EXPECTED_MANIFEST_SHA=%q\n' "$EXPECTED_MANIFEST_SHA"
        printf 'PACKAGE_FILE=%q\n' "$PACKAGE_FILE"
        printf 'MANIFEST_FILE=%q\n' "$MANIFEST_FILE"
        printf 'WEB02_IP=%q\n' "$WEB02_IP"
        printf 'MOODLE_ROOT=%q\n' "$MOODLE_ROOT"
        printf 'RUN_ID=%q\n' "$RUN_ID"
        printf 'BACKUP_WEB01=%q\n' "$BACKUP_WEB01"
        printf 'BACKUP_WEB02=%q\n' "$BACKUP_WEB02"
        printf 'MAINT_WAS=%q\n' "$MAINT_WAS"
        printf 'CRON_WAS=%q\n' "$CRON_WAS"
        printf 'DEPLOY_STATUS=%q\n' "${1:-PREPARED}"
    } > "$STATE_FILE"
}

cleanup_runtime_state() {
    if [ "$MAINT_WAS" = "0" ]; then
        php "$MOODLE_ROOT/admin/cli/maintenance.php" --disable >/dev/null 2>&1 || true
    fi
    if [ "$CRON_WAS" = "active" ]; then
        systemctl start moodle-cron.timer >/dev/null 2>&1 || true
    fi
}

on_error() {
    local rc=$?
    trap - ERR INT TERM
    say "DEPLOY_FAILURE_RC=$rc"
    if [ -f "$STATE_FILE" ] && [ -f "$ROLLBACK_RUNNER" ]; then
        say "AUTO_ROLLBACK=START"
        bash "$ROLLBACK_RUNNER" --auto "$WORKDIR" || true
    else
        cleanup_runtime_state
    fi
    exit "$rc"
}
trap on_error ERR INT TERM

say "===== PREDEPLOY BACKUP ====="
tar -C "$MOODLE_ROOT" -czf "$BACKUP_WEB01" local/digieranative local/worksheetlibrary mod/worksheetgrader
ssh -o BatchMode=yes "root@$WEB02_IP" "tar -C '$MOODLE_ROOT' -czf '$BACKUP_WEB02' local/digieranative local/worksheetlibrary mod/worksheetgrader"
write_state "BACKED_UP"

if [ "$MAINT_WAS" = "0" ]; then
    php "$MOODLE_ROOT/admin/cli/maintenance.php" --enable >/dev/null
fi
if [ "$CRON_WAS" = "active" ]; then
    systemctl stop moodle-cron.timer
fi

say "===== STAGE IDENTICAL PACKAGE ====="
rm -rf "$STAGE_WEB01"
mkdir -p "$STAGE_WEB01"
tar -xzf "$PACKAGE" -C "$STAGE_WEB01"
scp -q "$PACKAGE" "root@$WEB02_IP:$REMOTE_PACKAGE"
ssh -o BatchMode=yes "root@$WEB02_IP" "test \"\$(sha256sum '$REMOTE_PACKAGE' | awk '{print \\$1}')\" = '$EXPECTED_PACKAGE_SHA'; rm -rf '$STAGE_WEB02'; mkdir -p '$STAGE_WEB02'; tar -xzf '$REMOTE_PACKAGE' -C '$STAGE_WEB02'"

say "===== DEPLOY WEB01 ====="
rsync -a --delete "$STAGE_WEB01/public/local/digieranative/" "$MOODLE_ROOT/local/digieranative/"
rsync -a --delete "$STAGE_WEB01/public/local/worksheetlibrary/" "$MOODLE_ROOT/local/worksheetlibrary/"
rsync -a --delete "$STAGE_WEB01/public/mod/worksheetgrader/" "$MOODLE_ROOT/mod/worksheetgrader/"

say "===== DEPLOY WEB02 ====="
ssh -o BatchMode=yes "root@$WEB02_IP" "rsync -a --delete '$STAGE_WEB02/public/local/digieranative/' '$MOODLE_ROOT/local/digieranative/'; rsync -a --delete '$STAGE_WEB02/public/local/worksheetlibrary/' '$MOODLE_ROOT/local/worksheetlibrary/'; rsync -a --delete '$STAGE_WEB02/public/mod/worksheetgrader/' '$MOODLE_ROOT/mod/worksheetgrader/'"
write_state "DEPLOYED"

EXPECTED_DN="$(manifest_get TREE_HASH_DIGIERANATIVE)"
EXPECTED_WL="$(manifest_get TREE_HASH_WORKSHEETLIBRARY)"
EXPECTED_WG="$(manifest_get TREE_HASH_WORKSHEETGRADER)"

say "===== VERIFY WEB01 TREE ====="
[ "$(tree_hash "$MOODLE_ROOT/local/digieranative")" = "$EXPECTED_DN" ]
[ "$(tree_hash "$MOODLE_ROOT/local/worksheetlibrary")" = "$EXPECTED_WL" ]
[ "$(tree_hash "$MOODLE_ROOT/mod/worksheetgrader")" = "$EXPECTED_WG" ]

say "===== VERIFY WEB02 TREE ====="
ssh -o BatchMode=yes "root@$WEB02_IP" bash -s -- "$MOODLE_ROOT" "$EXPECTED_DN" "$EXPECTED_WL" "$EXPECTED_WG" <<'REMOTE'
set -Eeuo pipefail
root="$1"; dn="$2"; wl="$3"; wg="$4"
tree_hash() { (cd "$1"; find . -type f -print0 | LC_ALL=C sort -z | xargs -0 sha256sum | sha256sum | awk '{print $1}'); }
[ "$(tree_hash "$root/local/digieranative")" = "$dn" ]
[ "$(tree_hash "$root/local/worksheetlibrary")" = "$wl" ]
[ "$(tree_hash "$root/mod/worksheetgrader")" = "$wg" ]
echo WEB02_TREE_HASHES=PASS
REMOTE

say "===== PHP STATIC CHECK ====="
while IFS= read -r -d '' f; do php -l "$f" >/dev/null; done < <(find "$MOODLE_ROOT/local/digieranative" "$MOODLE_ROOT/local/worksheetlibrary" "$MOODLE_ROOT/mod/worksheetgrader" -name '*.php' -print0)
ssh -o BatchMode=yes "root@$WEB02_IP" "find '$MOODLE_ROOT/local/digieranative' '$MOODLE_ROOT/local/worksheetlibrary' '$MOODLE_ROOT/mod/worksheetgrader' -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null"

say "===== CACHE / PHP-FPM ====="
php "$MOODLE_ROOT/admin/cli/purge_caches.php" >/dev/null
ssh -o BatchMode=yes "root@$WEB02_IP" "php '$MOODLE_ROOT/admin/cli/purge_caches.php' >/dev/null"
if systemctl is-active --quiet php8.3-fpm; then systemctl reload php8.3-fpm; fi
ssh -o BatchMode=yes "root@$WEB02_IP" "if systemctl is-active --quiet php8.3-fpm; then systemctl reload php8.3-fpm; fi"

cleanup_runtime_state
write_state "COMPLETED"
trap - ERR INT TERM

say "===== FINAL SUMMARY ====="
say "SOURCE_COMMIT_SHA=$EXPECTED_SOURCE_SHA"
say "PACKAGE_SHA256=$EXPECTED_PACKAGE_SHA"
say "WEB01_TREE_HASHES=PASS"
say "WEB02_TREE_HASHES=PASS"
say "TWO_NODE_IDENTITY=PASS"
say "DB_UPGRADE_REQUIRED=NO"
say "DEPLOY_STATUS=PASS"
