#!/usr/bin/env bash
set -Eeuo pipefail

# DIGIERA Phase 5 — browser-output hotfix wrapper.
# Keeps the proven Cambridge final fastlane intact, but rebases its production
# baseline to the already-deployed Cambridge candidate and adds the browser
# output contract before any production mutation.

: "${PIN:?PIN must be the exact commit containing this runbook}"

SRC="/root/digiera-phase5-official-ui"
PROD_BASE="a20a92a007f4e077081df197efed45e98776d2ad"
BASE_RUNBOOK="docs/superpowers/runbooks/2026-09-12-phase5-cambridge-final-fastlane.sh"
TMP_RUNBOOK="/tmp/digiera-phase5-browser-output-hotfix-final-fastlane.sh"

fail() {
    echo
    echo "HOTFIX_FASTLANE_ERROR=$*" >&2
    exit 1
}

[ "${EUID:-$(id -u)}" -eq 0 ] || fail 'Run as root'
[ "$(hostname)" = 'vm-c47e0dd9' ] || fail "Wrong host: $(hostname)"
[ -d "$SRC/.git" ] || fail "Missing isolated repository: $SRC"

cd "$SRC"
git fetch --quiet origin \
  +refs/heads/digiera/tiptap-v1-phase5-official-ui:refs/remotes/origin/digiera/tiptap-v1-phase5-official-ui \
  +refs/heads/digiera/preview-v01:refs/remotes/origin/digiera/preview-v01

REMOTE_DEV="$(git rev-parse origin/digiera/tiptap-v1-phase5-official-ui)"
REMOTE_PROD="$(git rev-parse origin/digiera/preview-v01)"
[ "$REMOTE_DEV" = "$PIN" ] || fail "Dev branch moved; expected $PIN, got $REMOTE_DEV"
[ "$REMOTE_PROD" = "$PROD_BASE" ] || fail "Canonical moved; expected $PROD_BASE, got $REMOTE_PROD"

git checkout --detach "$PIN" >/dev/null
git reset --hard "$PIN" >/dev/null

echo '============================================================'
echo ' DIGIERA PHASE 5 — BROWSER OUTPUT HOTFIX FASTLANE'
echo '============================================================'
echo "PIN=$PIN"
echo "PROD_BASE=$PROD_BASE"

echo
echo '===== HOTFIX PREFLIGHT ====='
python3 public/local/worksheetlibrary/tests/native_output_browser_contract.py
php -l public/local/worksheetlibrary/print.php
php -l public/local/worksheetlibrary/pdf.php
node --check public/local/worksheetlibrary/amd/src/native_editor.js
node --check public/local/worksheetlibrary/amd/build/native_editor.min.js
cmp public/local/worksheetlibrary/amd/src/native_editor.js \
    public/local/worksheetlibrary/amd/build/native_editor.min.js >/dev/null \
    || fail 'WorksheetLibrary AMD src/build parity failed'

grep -q "SetFont('freesans'" public/local/worksheetlibrary/pdf.php \
    || fail 'FreeSans PDF font hotfix missing'
grep -q 'print.php?versionid=' public/local/worksheetlibrary/amd/src/native_editor.js \
    || fail 'Dedicated print route hotfix missing'
! grep -q 'window.location.assign' public/local/worksheetlibrary/amd/src/native_editor.js \
    || fail 'PDF navigation regression still present'

echo 'HOTFIX_OUTPUT_PREFLIGHT=PASS'

echo
echo '===== DELEGATE TO PROVEN 9/9 FASTLANE WITH CURRENT PROD BASE ====='
git show "$PIN:$BASE_RUNBOOK" \
  | sed "s/^PROD_BASE=\"[0-9a-f]\{40\}\"$/PROD_BASE=\"$PROD_BASE\"/" \
  > "$TMP_RUNBOOK"
chmod 700 "$TMP_RUNBOOK"

grep -q "^PROD_BASE=\"$PROD_BASE\"$" "$TMP_RUNBOOK" \
    || fail 'Could not bind delegated fastlane to current production baseline'

PIN="$PIN" bash "$TMP_RUNBOOK"

echo
echo '============================================================'
echo ' PHASE 5 BROWSER OUTPUT HOTFIX WRAPPER RESULT'
echo '============================================================'
echo 'HOTFIX_OUTPUT_PREFLIGHT=PASS'
echo 'HOTFIX_TWO_NODE_DEPLOY=PASS'
echo 'NEXT=BROWSER_RETEST_PRINT_PDF_REVISION_SINGLE_ROW'
