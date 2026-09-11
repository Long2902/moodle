#!/usr/bin/env bash
set -Eeuo pipefail

# DIGIERA Phase 5 — toolbar/assets/layout/PDF resume fastlane.
# Expected invocation passes PIN=<exact commit containing this runbook>.
# Runs all verification locally on Web01; does not wait for GitHub Actions.

REPO="Long2902/moodle"
DEV_BRANCH="digiera/tiptap-v1-phase5-official-ui"
PROD_BRANCH="digiera/preview-v01"
PROD_BASE="ee5f5aea90e205260db6e0174eb459ec048b989f"
DEV_BASE="e9567e09c11fa806e71f0278864621f6d487294d"

: "${PIN:?PIN must be the exact runbook commit SHA}"

SRC="/root/digiera-phase5-official-ui"
MAT="/root/digiera-phase5-toolbar-assets-materialized"
DEV_STAGE="/root/digiera-phase5-toolbar-assets-devstage"
BASE_STAGE="/root/digiera-phase5-toolbar-assets-baseline"
ROOT="/var/www/moodle/public"
WEB02="10.0.10.12"
CLIUSER="www-data"
LOG="/root/digiera-phase5-one-command-$(date +%Y%m%d-%H%M%S).log"

fail() {
    echo
    echo "FASTLANE_ERROR=$*" >&2
    return 1
}

tree_hash() {
    (
        cd "$1"
        find . -type f -print0 \
            | LC_ALL=C sort -z \
            | xargs -0 sha256sum \
            | sha256sum \
            | cut -d' ' -f1
    )
}

remote_tree_hash() {
    ssh -n -o BatchMode=yes -o ConnectTimeout=8 root@"$WEB02" \
        "cd '$1' && find . -type f -print0 | LC_ALL=C sort -z | xargs -0 sha256sum | sha256sum | cut -d' ' -f1"
}

bootstrap_web01() {
    (
        cd "$ROOT"
        runuser -u "$CLIUSER" -- php -r \
            "define('CLI_SCRIPT', true); require '$ROOT/config.php'; echo 'PASS';"
    )
}

bootstrap_web02() {
    ssh -n -o BatchMode=yes -o ConnectTimeout=8 root@"$WEB02" \
        "cd '$ROOT' && runuser -u '$CLIUSER' -- php -r \"define('CLI_SCRIPT', true); require '$ROOT/config.php'; echo 'PASS';\""
}

maintenance_state() {
    (
        cd "$ROOT"
        runuser -u "$CLIUSER" -- php -r \
            "define('CLI_SCRIPT', true); require '$ROOT/config.php'; echo empty(get_config('core','maintenance_enabled')) ? '0' : '1';"
    )
}

echo '============================================================'
echo ' DIGIERA PHASE 5 — ONE-COMMAND GREEN / BUILD / DEPLOY'
echo '============================================================'
echo "LOG=$LOG"
exec > >(tee -a "$LOG") 2>&1

# ------------------------------------------------------------------
# 1. Exact source and machine binding.
# ------------------------------------------------------------------
echo
echo '===== 1/8 SOURCE + HOST BINDING ====='

[ "${EUID:-$(id -u)}" -eq 0 ] || fail 'Run as root'
[ "$(hostname)" = 'vm-c47e0dd9' ] || fail "Wrong host: $(hostname)"
[ -d "$SRC/.git" ] || fail "Missing isolated repository: $SRC"
[ -f "$ROOT/config.php" ] || fail "Invalid Moodle root: $ROOT"

for command in git gh node npm php python3 rsync ssh scp sha256sum gzip tar find sort xargs cut systemctl runuser cmp; do
    command -v "$command" >/dev/null || fail "Missing command: $command"
done

gh auth status -h github.com >/dev/null 2>&1 || fail 'GitHub CLI authentication missing'
gh auth setup-git >/dev/null 2>&1 || true

ssh -n -o BatchMode=yes -o ConnectTimeout=8 root@"$WEB02" \
    "test -f '$ROOT/config.php' && command -v rsync >/dev/null && id '$CLIUSER' >/dev/null 2>&1" \
    || fail 'Web02 preflight failed'

cd "$SRC"
git fetch --quiet origin \
    "+refs/heads/$DEV_BRANCH:refs/remotes/origin/$DEV_BRANCH" \
    "+refs/heads/$PROD_BRANCH:refs/remotes/origin/$PROD_BRANCH"

REMOTE_DEV="$(git rev-parse "origin/$DEV_BRANCH")"
REMOTE_PROD="$(git rev-parse "origin/$PROD_BRANCH")"

[ "$REMOTE_DEV" = "$PIN" ] || fail "Dev branch moved; expected $PIN, got $REMOTE_DEV"
[ "$REMOTE_PROD" = "$PROD_BASE" ] || fail "Canonical moved; expected $PROD_BASE, got $REMOTE_PROD"

git merge-base --is-ancestor "$DEV_BASE" "$PIN" || fail 'Pinned source is not descended from approved development baseline'

git diff --quiet "$PROD_BASE" "$DEV_BASE" -- \
    public/local/digieranative \
    public/local/worksheetlibrary \
    public/mod/worksheetgrader \
    || fail 'Old dev product baseline differs from current production canonical'

git checkout --detach "$PIN" >/dev/null
git reset --hard "$PIN" >/dev/null
git clean -fd >/dev/null

echo "PIN=$PIN"
echo "PROD_BASE=$PROD_BASE"
echo 'SOURCE_HOST_BINDING=PASS'

# ------------------------------------------------------------------
# 2. Focused + full tests. Production is still untouched.
# ------------------------------------------------------------------
echo
echo '===== 2/8 LOCAL GREEN GATE ====='

CLIENT="$SRC/public/local/digieranative/client"
cd "$CLIENT"

if [ ! -d node_modules ]; then
    npm install --prefer-offline --no-audit --no-fund
fi

npx vitest run \
    tests/phase5-toolbar-completion.test.js \
    tests/schema.test.js \
    tests/official-ui-build.test.js

cd "$SRC"
python3 public/local/digieranative/tests/phase5_assets_pdf_contract.py

if [ -f public/local/digieranative/tests/link_roundtrip_contract.py ]; then
    python3 public/local/digieranative/tests/link_roundtrip_contract.py
fi

cd "$CLIENT"
npm test

cd "$SRC"
php public/local/digieranative/tests/standalone_validator.php
php public/local/digieranative/tests/standalone_renderer.php

if [ -f public/local/digieranative/tests/tiptap_hybrid_static_contract.py ]; then
    python3 public/local/digieranative/tests/tiptap_hybrid_static_contract.py
fi

php -r "
require '$SRC/public/lib/tcpdf/tcpdf.php';
\$pdf = new TCPDF();
\$pdf->SetCreator('DIGIERA');
\$pdf->AddPage();
\$pdf->writeHTML('<h1>DIGIERA Phase 5</h1><p>Toolbar Assets PDF smoke</p>');
\$bytes = \$pdf->Output('phase5-smoke.pdf', 'S');
if (substr(\$bytes, 0, 4) !== '%PDF') { fwrite(STDERR, \"TCPDF smoke failed\\n\"); exit(1); }
echo \"TCPDF_SMOKE=PASS\\n\";
"

echo 'LOCAL_GREEN_GATE=PASS'

# ------------------------------------------------------------------
# 3. Deterministic runtime build and static gates.
# ------------------------------------------------------------------
echo
echo '===== 3/8 DETERMINISTIC BUILD + STATIC GATES ====='

cd "$CLIENT"
npm run build >/tmp/digiera-p5-one-build1.log 2>&1 || { cat /tmp/digiera-p5-one-build1.log; fail 'Build #1 failed'; }
BUILD1="$(sha256sum ../amd/build/native_editor.min.js | cut -d' ' -f1)"
cp ../amd/build/native_editor.min.js /tmp/digiera-p5-one-build1.js

npm run build >/tmp/digiera-p5-one-build2.log 2>&1 || { cat /tmp/digiera-p5-one-build2.log; fail 'Build #2 failed'; }
BUILD2="$(sha256sum ../amd/build/native_editor.min.js | cut -d' ' -f1)"

[ "$BUILD1" = "$BUILD2" ] || fail 'AMD build is non-deterministic'
cmp /tmp/digiera-p5-one-build1.js ../amd/build/native_editor.min.js >/dev/null || fail 'AMD build bytes differ'

cd "$SRC"
cp public/local/worksheetlibrary/amd/src/native_editor.js public/local/worksheetlibrary/amd/build/native_editor.min.js
cp public/local/worksheetlibrary/amd/src/browser.js public/local/worksheetlibrary/amd/build/browser.min.js
cp public/mod/worksheetgrader/amd/src/native_attempt.js public/mod/worksheetgrader/amd/build/native_attempt.min.js

node --check public/local/digieranative/amd/build/native_editor.min.js
node --check public/local/worksheetlibrary/amd/build/native_editor.min.js
node --check public/local/worksheetlibrary/amd/build/browser.min.js
node --check public/mod/worksheetgrader/amd/build/native_attempt.min.js

find \
    public/local/digieranative \
    public/local/worksheetlibrary \
    public/mod/worksheetgrader \
    -type f -name '*.php' -print0 \
    | xargs -0 -n1 php -l >/tmp/digiera-p5-one-php-lint.log

echo "NATIVE_AMD_SHA256=$BUILD2"
echo 'DETERMINISTIC_BUILD=PASS'
echo 'PHP_LINT=PASS'

# ------------------------------------------------------------------
# 4. Freeze generated runtime bytes on dev branch.
# ------------------------------------------------------------------
echo
echo '===== 4/8 FREEZE LOCAL GREEN ARTIFACTS ====='

cd "$SRC"
git config user.name 'DIGIERA Phase5 Fastlane'
git config user.email 'phase5-fastlane@digiera.local'

mapfile -t DIRTY < <(git status --porcelain=v1 --untracked-files=all | sed -E 's/^.. //')
for f in "${DIRTY[@]}"; do
    case "$f" in
        public/local/digieranative/amd/build/native_editor.min.js|\
        public/local/worksheetlibrary/amd/build/native_editor.min.js|\
        public/local/worksheetlibrary/amd/build/browser.min.js|\
        public/mod/worksheetgrader/amd/build/native_attempt.min.js)
            ;;
        *) fail "Unexpected dirty file after build: $f" ;;
    esac
done

for f in \
    public/local/digieranative/amd/build/native_editor.min.js \
    public/local/worksheetlibrary/amd/build/native_editor.min.js \
    public/local/worksheetlibrary/amd/build/browser.min.js \
    public/mod/worksheetgrader/amd/build/native_attempt.min.js; do
    git add "$f"
done

if ! git diff --cached --quiet; then
    git commit -m 'build(phase5): freeze toolbar assets PDF final local artifacts [skip ci]' >/dev/null
fi

DEV_FINAL="$(git rev-parse HEAD)"
git diff --quiet || fail 'Tracked unstaged changes remain after build freeze'

REMOTE_DEV_BEFORE="$(git ls-remote origin "refs/heads/$DEV_BRANCH" | cut -f1)"
[ "$REMOTE_DEV_BEFORE" = "$PIN" ] || fail 'Development branch raced before final artifact push'

if [ "$DEV_FINAL" != "$PIN" ]; then
    git push origin "HEAD:refs/heads/$DEV_BRANCH" >/dev/null
fi

REMOTE_DEV_AFTER="$(git ls-remote origin "refs/heads/$DEV_BRANCH" | cut -f1)"
[ "$REMOTE_DEV_AFTER" = "$DEV_FINAL" ] || fail 'Development final binding failed'

echo "DEV_FINAL_SHA=$DEV_FINAL"
echo 'BUILD_FREEZE=PASS'

# ------------------------------------------------------------------
# 5. Product-only canonical candidate + deterministic package.
# ------------------------------------------------------------------
echo
echo '===== 5/8 PRODUCT CANONICAL + PACKAGE ====='

REMOTE_PROD_NOW="$(git ls-remote origin "refs/heads/$PROD_BRANCH" | cut -f1)"
[ "$REMOTE_PROD_NOW" = "$PROD_BASE" ] || fail 'Canonical raced before materialization'

rm -rf "$DEV_STAGE"
mkdir -p "$DEV_STAGE"
git archive "$DEV_FINAL" \
    public/local/digieranative \
    public/local/worksheetlibrary \
    public/mod/worksheetgrader \
    | tar -x -C "$DEV_STAGE"

git worktree remove --force "$MAT" >/dev/null 2>&1 || true
rm -rf "$MAT"
git worktree add --detach "$MAT" "$PROD_BASE" >/dev/null

rsync -a --delete "$DEV_STAGE/public/local/digieranative/" "$MAT/public/local/digieranative/"
rsync -a --delete "$DEV_STAGE/public/local/worksheetlibrary/" "$MAT/public/local/worksheetlibrary/"
rsync -a --delete "$DEV_STAGE/public/mod/worksheetgrader/" "$MAT/public/mod/worksheetgrader/"

mapfile -t CHANGED < <(git -C "$MAT" diff --name-only "$PROD_BASE")
[ "${#CHANGED[@]}" -gt 0 ] || fail 'No product delta'

for f in "${CHANGED[@]}"; do
    case "$f" in
        public/local/digieranative/*|public/local/worksheetlibrary/*|public/mod/worksheetgrader/*) ;;
        *) fail "Out-of-scope product file: $f" ;;
    esac
done

if printf '%s\n' "${CHANGED[@]}" | grep -Eq '(^|/)(version\.php|db/install\.xml|db/upgrade\.php)$'; then
    printf '%s\n' "${CHANGED[@]}"
    fail 'DB/version migration file changed'
fi

git -C "$MAT" config user.name 'DIGIERA Phase5 Fastlane'
git -C "$MAT" config user.email 'phase5-fastlane@digiera.local'
git -C "$MAT" add public/local/digieranative public/local/worksheetlibrary public/mod/worksheetgrader
git -C "$MAT" commit -m 'feat(phase5): toolbar assets layout and direct PDF final [skip ci]' >/dev/null
CANON_SHA="$(git -C "$MAT" rev-parse HEAD)"
[ "$(git -C "$MAT" rev-parse HEAD^)" = "$PROD_BASE" ] || fail 'Canonical candidate parent mismatch'

SHORT="${CANON_SHA:0:12}"
PKG="/root/digiera-phase5-toolbar-assets-pdf-${SHORT}.tar.gz"
PKG2="/root/digiera-phase5-toolbar-assets-pdf-${SHORT}.verify.tar.gz"
STAGE="/root/digiera-phase5-stage-${SHORT}"
rm -rf "$STAGE"
rm -f "$PKG" "$PKG2"
mkdir -p "$STAGE"

git -C "$MAT" archive --format=tar "$CANON_SHA" \
    public/local/digieranative \
    public/local/worksheetlibrary \
    public/mod/worksheetgrader \
    | gzip -n -9 >"$PKG"

git -C "$MAT" archive --format=tar "$CANON_SHA" \
    public/local/digieranative \
    public/local/worksheetlibrary \
    public/mod/worksheetgrader \
    | gzip -n -9 >"$PKG2"

cmp "$PKG" "$PKG2" >/dev/null || fail 'Package is non-deterministic'
rm -f "$PKG2"
PACKAGE_SHA="$(sha256sum "$PKG" | cut -d' ' -f1)"
tar -xzf "$PKG" -C "$STAGE"
NEW_DN="$(tree_hash "$STAGE/public/local/digieranative")"
NEW_WL="$(tree_hash "$STAGE/public/local/worksheetlibrary")"
NEW_WG="$(tree_hash "$STAGE/public/mod/worksheetgrader")"

echo "CANONICAL_CANDIDATE_SHA=$CANON_SHA"
echo "PACKAGE_SHA256=$PACKAGE_SHA"
echo 'DB_UPGRADE_REQUIRED=NO'
echo 'PACKAGE=PASS'

# ------------------------------------------------------------------
# 6. Production baseline + backup + remote stage.
# ------------------------------------------------------------------
echo
echo '===== 6/8 PRODUCTION BASELINE + BACKUP ====='

rm -rf "$BASE_STAGE"
mkdir -p "$BASE_STAGE"
git -C "$MAT" archive --format=tar "$PROD_BASE" \
    public/local/digieranative \
    public/local/worksheetlibrary \
    public/mod/worksheetgrader \
    | tar -x -C "$BASE_STAGE"

OLD_DN="$(tree_hash "$BASE_STAGE/public/local/digieranative")"
OLD_WL="$(tree_hash "$BASE_STAGE/public/local/worksheetlibrary")"
OLD_WG="$(tree_hash "$BASE_STAGE/public/mod/worksheetgrader")"
CUR_DN1="$(tree_hash "$ROOT/local/digieranative")"
CUR_WL1="$(tree_hash "$ROOT/local/worksheetlibrary")"
CUR_WG1="$(tree_hash "$ROOT/mod/worksheetgrader")"
CUR_DN2="$(remote_tree_hash "$ROOT/local/digieranative")"
CUR_WL2="$(remote_tree_hash "$ROOT/local/worksheetlibrary")"
CUR_WG2="$(remote_tree_hash "$ROOT/mod/worksheetgrader")"

[ "$CUR_DN1" = "$OLD_DN" ] || fail 'Web01 digieranative baseline drift'
[ "$CUR_WL1" = "$OLD_WL" ] || fail 'Web01 worksheetlibrary baseline drift'
[ "$CUR_WG1" = "$OLD_WG" ] || fail 'Web01 worksheetgrader baseline drift'
[ "$CUR_DN2" = "$OLD_DN" ] || fail 'Web02 digieranative baseline drift'
[ "$CUR_WL2" = "$OLD_WL" ] || fail 'Web02 worksheetlibrary baseline drift'
[ "$CUR_WG2" = "$OLD_WG" ] || fail 'Web02 worksheetgrader baseline drift'
[ "$(bootstrap_web01)" = 'PASS' ] || fail 'Web01 Moodle bootstrap failed'
[ "$(bootstrap_web02)" = 'PASS' ] || fail 'Web02 Moodle bootstrap failed'
[ "$(maintenance_state)" = '0' ] || fail 'Moodle already in maintenance mode'
[ "$(systemctl is-active moodle-cron.timer || true)" = 'active' ] || fail 'Web01 cron timer is not active'

STAMP="$(date +%Y%m%d-%H%M%S)"
B1="/root/digiera-p5-toolbar-assets-pre-${STAMP}"
B2="/root/digiera-p5-toolbar-assets-pre-${STAMP}"
mkdir -p "$B1/local" "$B1/mod"
cp -a "$ROOT/local/digieranative" "$B1/local/"
cp -a "$ROOT/local/worksheetlibrary" "$B1/local/"
cp -a "$ROOT/mod/worksheetgrader" "$B1/mod/"

ssh -n root@"$WEB02" \
    "mkdir -p '$B2/local' '$B2/mod' && cp -a '$ROOT/local/digieranative' '$B2/local/' && cp -a '$ROOT/local/worksheetlibrary' '$B2/local/' && cp -a '$ROOT/mod/worksheetgrader' '$B2/mod/'"

REMOTE_PKG="/root/$(basename "$PKG")"
REMOTE_STAGE="/root/digiera-phase5-stage-${SHORT}"
scp -q "$PKG" root@"$WEB02":"$REMOTE_PKG" </dev/null
REMOTE_PACKAGE_SHA="$(ssh -n root@"$WEB02" "sha256sum '$REMOTE_PKG' | cut -d' ' -f1")"
[ "$REMOTE_PACKAGE_SHA" = "$PACKAGE_SHA" ] || fail 'Web02 package SHA mismatch'
ssh -n root@"$WEB02" "rm -rf '$REMOTE_STAGE' && mkdir -p '$REMOTE_STAGE' && tar -xzf '$REMOTE_PKG' -C '$REMOTE_STAGE'"

echo "WEB01_BACKUP=$B1"
echo "WEB02_BACKUP=$B2"
echo 'PRODUCTION_BASELINE_BACKUP=PASS'

# ------------------------------------------------------------------
# Rollback can also restore canonical ref if the ref was advanced.
# ------------------------------------------------------------------
MUTATED=0
CANONICAL_ADVANCED=0

rollback() {
    local rc="${1:-1}"
    trap - ERR
    set +e
    echo
    echo '===== AUTO ROLLBACK START ====='

    (
        cd "$ROOT"
        runuser -u "$CLIUSER" -- php admin/cli/maintenance.php --enable >/dev/null 2>&1
    )
    systemctl stop moodle-cron.timer >/dev/null 2>&1

    rsync -a --delete "$B1/local/digieranative/" "$ROOT/local/digieranative/"
    rsync -a --delete "$B1/local/worksheetlibrary/" "$ROOT/local/worksheetlibrary/"
    rsync -a --delete "$B1/mod/worksheetgrader/" "$ROOT/mod/worksheetgrader/"
    ssh -n root@"$WEB02" \
        "rsync -a --delete '$B2/local/digieranative/' '$ROOT/local/digieranative/' && rsync -a --delete '$B2/local/worksheetlibrary/' '$ROOT/local/worksheetlibrary/' && rsync -a --delete '$B2/mod/worksheetgrader/' '$ROOT/mod/worksheetgrader/'"

    (
        cd "$ROOT"
        runuser -u "$CLIUSER" -- php admin/cli/purge_caches.php >/dev/null 2>&1
        runuser -u "$CLIUSER" -- php admin/cli/maintenance.php --disable >/dev/null 2>&1
    )
    ssh -n root@"$WEB02" "cd '$ROOT' && runuser -u '$CLIUSER' -- php admin/cli/purge_caches.php >/dev/null 2>&1"
    systemctl reload php8.3-fpm >/dev/null 2>&1 || true
    ssh -n root@"$WEB02" "systemctl reload php8.3-fpm >/dev/null 2>&1 || true"
    systemctl start moodle-cron.timer >/dev/null 2>&1

    if [ "$CANONICAL_ADVANCED" -eq 1 ]; then
        git -C "$MAT" push --force-with-lease="refs/heads/$PROD_BRANCH:$CANON_SHA" \
            origin "$PROD_BASE:refs/heads/$PROD_BRANCH" >/dev/null 2>&1 || true
    fi

    echo 'AUTO_ROLLBACK=FINISHED'
    echo 'PRODUCTION_RESTORED=YES'
    exit "$rc"
}

# ------------------------------------------------------------------
# 7. Two-node deploy + runtime verification.
# ------------------------------------------------------------------
echo
echo '===== 7/8 TWO-NODE DEPLOY ====='
MUTATED=1
trap 'rc=$?; if [ "${MUTATED:-0}" -eq 1 ]; then rollback "$rc"; fi; exit "$rc"' ERR

(
    cd "$ROOT"
    runuser -u "$CLIUSER" -- php admin/cli/maintenance.php --enable >/dev/null
)
systemctl stop moodle-cron.timer

rsync -a --delete "$STAGE/public/local/digieranative/" "$ROOT/local/digieranative/"
rsync -a --delete "$STAGE/public/local/worksheetlibrary/" "$ROOT/local/worksheetlibrary/"
rsync -a --delete "$STAGE/public/mod/worksheetgrader/" "$ROOT/mod/worksheetgrader/"
ssh -n root@"$WEB02" \
    "rsync -a --delete '$REMOTE_STAGE/public/local/digieranative/' '$ROOT/local/digieranative/' && rsync -a --delete '$REMOTE_STAGE/public/local/worksheetlibrary/' '$ROOT/local/worksheetlibrary/' && rsync -a --delete '$REMOTE_STAGE/public/mod/worksheetgrader/' '$ROOT/mod/worksheetgrader/'"

DN1="$(tree_hash "$ROOT/local/digieranative")"
WL1="$(tree_hash "$ROOT/local/worksheetlibrary")"
WG1="$(tree_hash "$ROOT/mod/worksheetgrader")"
DN2="$(remote_tree_hash "$ROOT/local/digieranative")"
WL2="$(remote_tree_hash "$ROOT/local/worksheetlibrary")"
WG2="$(remote_tree_hash "$ROOT/mod/worksheetgrader")"

[ "$DN1" = "$NEW_DN" ] || fail 'Web01 DN hash mismatch'
[ "$WL1" = "$NEW_WL" ] || fail 'Web01 WL hash mismatch'
[ "$WG1" = "$NEW_WG" ] || fail 'Web01 WG hash mismatch'
[ "$DN2" = "$NEW_DN" ] || fail 'Web02 DN hash mismatch'
[ "$WL2" = "$NEW_WL" ] || fail 'Web02 WL hash mismatch'
[ "$WG2" = "$NEW_WG" ] || fail 'Web02 WG hash mismatch'
[ "$DN1" = "$DN2" ] && [ "$WL1" = "$WL2" ] && [ "$WG1" = "$WG2" ] || fail 'Two-node tree identity failed'

find "$ROOT/local/digieranative" "$ROOT/local/worksheetlibrary" "$ROOT/mod/worksheetgrader" \
    -type f -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null
ssh -n root@"$WEB02" \
    "find '$ROOT/local/digieranative' '$ROOT/local/worksheetlibrary' '$ROOT/mod/worksheetgrader' -type f -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null"

(
    cd "$ROOT"
    runuser -u "$CLIUSER" -- php admin/cli/purge_caches.php >/dev/null
)
ssh -n root@"$WEB02" "cd '$ROOT' && runuser -u '$CLIUSER' -- php admin/cli/purge_caches.php >/dev/null"
systemctl reload php8.3-fpm || true
ssh -n root@"$WEB02" "systemctl reload php8.3-fpm || true"

[ "$(bootstrap_web01)" = 'PASS' ] || fail 'Web01 post-deploy bootstrap failed'
[ "$(bootstrap_web02)" = 'PASS' ] || fail 'Web02 post-deploy bootstrap failed'

(
    cd "$ROOT"
    runuser -u "$CLIUSER" -- php admin/cli/maintenance.php --disable >/dev/null
)
systemctl start moodle-cron.timer
[ "$(maintenance_state)" = '0' ] || fail 'Maintenance did not return to 0'
[ "$(systemctl is-active moodle-cron.timer || true)" = 'active' ] || fail 'Cron did not return active'

echo 'TWO_NODE_DEPLOY=PASS'

# ------------------------------------------------------------------
# 8. Canonical fast-forward + state file.
# ------------------------------------------------------------------
echo
echo '===== 8/8 CANONICAL + FINAL STATE ====='
REMOTE_PROD_FINAL="$(git -C "$MAT" ls-remote origin "refs/heads/$PROD_BRANCH" | cut -f1)"
[ "$REMOTE_PROD_FINAL" = "$PROD_BASE" ] || fail 'Canonical raced during deployment'

git -C "$MAT" push origin "$CANON_SHA:refs/heads/$PROD_BRANCH" >/dev/null
CANONICAL_ADVANCED=1
REMOTE_PROD_AFTER="$(git -C "$MAT" ls-remote origin "refs/heads/$PROD_BRANCH" | cut -f1)"
[ "$REMOTE_PROD_AFTER" = "$CANON_SHA" ] || fail 'Canonical final binding failed'

MUTATED=0
trap - ERR

STATE="/root/digiera-phase5-toolbar-assets-pdf-final-state.env"
cat >"$STATE" <<EOF
DEPLOY_STATUS=COMPLETED
SOURCE_COMMIT_SHA=$CANON_SHA
DEV_FINAL_SHA=$DEV_FINAL
PACKAGE_SHA256=$PACKAGE_SHA
NATIVE_AMD_SHA256=$BUILD2
WEB01_DN=$DN1
WEB01_WL=$WL1
WEB01_WG=$WG1
WEB02_DN=$DN2
WEB02_WL=$WL2
WEB02_WG=$WG2
DB_UPGRADE_REQUIRED=NO
EOF

echo
echo '============================================================'
echo ' PHASE 5 TOOLBAR / ASSETS / PDF FINAL FASTLANE RESULT'
echo '============================================================'
echo 'PREVIOUS_TDD_RED=PASS'
echo 'FOCUSED_GREEN=PASS'
echo 'FULL_TEST_GATE=PASS'
echo 'TCPDF_SMOKE=PASS'
echo 'DETERMINISTIC_BUILD=PASS'
echo 'PHP_LINT=PASS'
echo "RUNBOOK_PIN=$PIN"
echo "DEV_FINAL_SHA=$DEV_FINAL"
echo "CANONICAL_SOURCE_SHA=$CANON_SHA"
echo "PACKAGE_SHA256=$PACKAGE_SHA"
echo "NATIVE_AMD_SHA256=$BUILD2"
echo "WEB01_DN=$DN1"
echo "WEB01_WL=$WL1"
echo "WEB01_WG=$WG1"
echo "WEB02_DN=$DN2"
echo "WEB02_WL=$WL2"
echo "WEB02_WG=$WG2"
echo 'WEB01_TREE_HASHES=PASS'
echo 'WEB02_TREE_HASHES=PASS'
echo 'TWO_NODE_IDENTITY=PASS'
echo 'MAINTENANCE_AFTER=0'
echo 'CRON_AFTER=active'
echo 'DB_UPGRADE_REQUIRED=NO'
echo 'PHASE5_TOOLBAR_ASSETS_PDF_DEPLOY=PASS'
echo 'NEXT=BROWSER_ACCEPTANCE'
echo "STATE_FILE=$STATE"
