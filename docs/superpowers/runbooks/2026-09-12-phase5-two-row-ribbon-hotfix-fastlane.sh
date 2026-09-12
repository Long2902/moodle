#!/usr/bin/env bash
set -Eeuo pipefail

# DIGIERA Phase 5 — user-approved two-row Cambridge ribbon hotfix.
# UI-only: preserves the already-verified print/PDF/autosave hotfix and uses
# the proven Phase 5 9/9 fastlane for full regression, backup and two-node deploy.

: "${PIN:?PIN must be the exact commit containing this runbook}"

SRC="/root/digiera-phase5-official-ui"
PROD_BASE="af15a1c0e676e5a59d66efe9b103732693d6d92e"
BASE_RUNBOOK="docs/superpowers/runbooks/2026-09-12-phase5-cambridge-final-fastlane.sh"
TMP_RUNBOOK="/tmp/digiera-phase5-two-row-ribbon-final-fastlane.sh"

fail() {
    echo
    echo "TWO_ROW_FASTLANE_ERROR=$*" >&2
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
echo ' DIGIERA PHASE 5 — TWO-ROW RIBBON UI HOTFIX FASTLANE'
echo '============================================================'
echo "PIN=$PIN"
echo "PROD_BASE=$PROD_BASE"

echo
echo '===== TWO-ROW GREEN PREFLIGHT ====='
python3 public/local/worksheetlibrary/tests/native_output_browser_contract.py

cd public/local/digieranative/client
npx vitest run \
  tests/cambridge-visual-contract.test.js \
  tests/cambridge-ribbon-migration.test.js \
  tests/phase5-toolbar-completion.test.js \
  tests/mount-contract.test.js
cd "$SRC"

node --check public/local/digieranative/client/src/editor.js
node --check public/local/digieranative/client/src/ui/cambridge_toolbar.js

grep -q "'data-dgn-toolbar-row': 'primary'" public/local/digieranative/client/src/ui/cambridge_toolbar.js \
  || fail 'Primary Cambridge ribbon row missing'
grep -q "'data-dgn-toolbar-row': 'secondary'" public/local/digieranative/client/src/ui/cambridge_toolbar.js \
  || fail 'Secondary Cambridge ribbon row missing'
! grep -q "cambridgeToolbar.style.flexWrap = 'nowrap'" public/local/digieranative/client/src/editor.js \
  || fail 'Legacy single-row runtime force is still present'
grep -q 'PDF_IN_PLACE_DOWNLOAD=PASS' <(python3 public/local/worksheetlibrary/tests/native_output_browser_contract.py) \
  || fail 'PDF lifecycle regression detected'

echo 'TWO_ROW_GREEN_PREFLIGHT=PASS'
echo 'PRINT_PDF_AUTOSAVE_REGRESSION_GUARD=PASS'

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
echo ' PHASE 5 TWO-ROW RIBBON HOTFIX RESULT'
echo '============================================================'
echo 'TWO_ROW_GREEN_PREFLIGHT=PASS'
echo 'PRINT_PDF_AUTOSAVE_REGRESSION_GUARD=PASS'
echo 'TWO_ROW_TWO_NODE_DEPLOY=PASS'
echo 'NEXT=BROWSER_RETEST_TWO_ROW_ONLY'
