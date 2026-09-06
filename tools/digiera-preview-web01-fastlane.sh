#!/usr/bin/env bash
set -Eeuo pipefail

REPO="Long2902/moodle"
BRANCH="digiera/preview-v01"
ROOT="/root/digiera-preview-fastlane"
STAGE="$ROOT/stage"
LOG="$ROOT/fastlane.log"
SUMMARY="$ROOT/summary.txt"

mkdir -p "$ROOT"
exec > >(tee "$LOG") 2>&1

finish() {
    rc=$?
    if [ "$rc" -ne 0 ]; then
        printf '\nFASTLANE=STOPPED\nEXIT_CODE=%s\nLOG=%s\n' "$rc" "$LOG" | tee "$SUMMARY"
    fi
}
trap finish EXIT

echo '============================================================'
echo ' DIGIERA PREVIEW V0.1 - WEB01 FASTLANE'
echo '============================================================'
date -Is
hostname

echo
echo '===== TOOLING ====='
command -v php >/dev/null && php -v | head -n1
command -v node >/dev/null && node --version || true
command -v npm >/dev/null && npm --version || true
command -v git >/dev/null && git --version
if command -v gh >/dev/null; then
    echo 'GH_CLI=YES'
    if gh auth status >/dev/null 2>&1; then
        echo 'GH_AUTH=YES'
    else
        echo 'GH_AUTH=NO'
    fi
else
    echo 'GH_CLI=NO'
fi

echo
echo '===== LOCATE VERIFIED SOURCE ====='
NATIVE="/root/digiera-native-build/local/digieranative"
[ -f "$NATIVE/version.php" ] || { echo "MISSING_NATIVE=$NATIVE"; exit 20; }
echo "NATIVE_SOURCE=$NATIVE"

find_zip() {
    local name="$1"
    local p
    for p in "/root/$name" "/root/digiera-native-build/$name" "/tmp/$name" "/var/tmp/$name"; do
        if [ -f "$p" ]; then printf '%s\n' "$p"; return 0; fi
    done
    return 1
}

OFFICE_ZIP="$(find_zip local_digieraoffice_moodle51_1.0.0-rc1.zip || true)"
LIB_ZIP="$(find_zip local_worksheetlibrary_moodle51_1.0.0-rc1.zip || true)"
GRADER_ZIP="$(find_zip mod_worksheetgrader_moodle51_12.0.0-rc1.zip || true)"
OFFICE_TREE=""; LIB_TREE=""; GRADER_TREE=""
if [ -z "$OFFICE_ZIP" ] && [ -f /var/www/moodle/public/local/digieraoffice/version.php ] && grep -q '2026090501' /var/www/moodle/public/local/digieraoffice/version.php; then OFFICE_TREE=/var/www/moodle/public/local/digieraoffice; fi
if [ -z "$LIB_ZIP" ] && [ -f /var/www/moodle/public/local/worksheetlibrary/version.php ] && grep -q '2026090501' /var/www/moodle/public/local/worksheetlibrary/version.php; then LIB_TREE=/var/www/moodle/public/local/worksheetlibrary; fi
if [ -z "$GRADER_ZIP" ] && [ -f /var/www/moodle/public/mod/worksheetgrader/version.php ] && grep -q '2026090501' /var/www/moodle/public/mod/worksheetgrader/version.php; then GRADER_TREE=/var/www/moodle/public/mod/worksheetgrader; fi

echo "OFFICE_ZIP=${OFFICE_ZIP:-NONE}"
echo "LIB_ZIP=${LIB_ZIP:-NONE}"
echo "GRADER_ZIP=${GRADER_ZIP:-NONE}"
echo "OFFICE_TREE=${OFFICE_TREE:-NONE}"
echo "LIB_TREE=${LIB_TREE:-NONE}"
echo "GRADER_TREE=${GRADER_TREE:-NONE}"

if [ -z "$OFFICE_ZIP$OFFICE_TREE" ] || [ -z "$LIB_ZIP$LIB_TREE" ] || [ -z "$GRADER_ZIP$GRADER_TREE" ]; then
    echo 'FASTLANE_SOURCE_SET=INCOMPLETE'
    exit 21
fi

echo
echo '===== ASSEMBLE ISOLATED STAGE ====='
rm -rf "$STAGE"
mkdir -p "$STAGE/public/local" "$STAGE/public/mod"
rsync -a --delete --exclude=node_modules/ "$NATIVE/" "$STAGE/public/local/digieranative/"

extract_plugin() {
    local zip="$1" tree="$2" target="$3" expectedroot="$4"
    if [ -n "$zip" ]; then
        local tmp="$ROOT/unpack-$expectedroot"
        rm -rf "$tmp" && mkdir -p "$tmp"
        unzip -q "$zip" -d "$tmp"
        [ -d "$tmp/$expectedroot" ] || { echo "BAD_ZIP_ROOT=$zip"; exit 22; }
        rsync -a --delete "$tmp/$expectedroot/" "$target/"
    else
        rsync -a --delete "$tree/" "$target/"
    fi
}

extract_plugin "$OFFICE_ZIP" "$OFFICE_TREE" "$STAGE/public/local/digieraoffice" digieraoffice
extract_plugin "$LIB_ZIP" "$LIB_TREE" "$STAGE/public/local/worksheetlibrary" worksheetlibrary
extract_plugin "$GRADER_ZIP" "$GRADER_TREE" "$STAGE/public/mod/worksheetgrader" worksheetgrader

echo 'STAGE_ASSEMBLY=PASS'

echo
echo '===== STATIC BASELINE ====='
PHP_COUNT=0
while IFS= read -r -d '' f; do php -l "$f" >/dev/null; PHP_COUNT=$((PHP_COUNT+1)); done < <(find "$STAGE/public/local/digieranative" "$STAGE/public/local/digieraoffice" "$STAGE/public/local/worksheetlibrary" "$STAGE/public/mod/worksheetgrader" -name '*.php' -print0)
echo "PHP_LINT_FILES=$PHP_COUNT"
python3 - "$STAGE" <<'PY'
import sys, xml.etree.ElementTree as ET
from pathlib import Path
root=Path(sys.argv[1])
for p in [root/'public/local/worksheetlibrary/db/install.xml', root/'public/mod/worksheetgrader/db/install.xml']:
    ET.parse(p)
print('XMLDB_PARSE=PASS')
PY
php "$STAGE/public/local/digieranative/tests/standalone_validator.php"
php "$STAGE/public/local/digieranative/tests/standalone_renderer.php"

echo
echo '===== NATIVE JS FAST GATE ====='
cd "$STAGE/public/local/digieranative/client"
npm ci --ignore-scripts
npm test
npm run build
AMD="$STAGE/public/local/digieranative/amd/build/native_editor.min.js"
H1="$(sha256sum "$AMD" | awk '{print $1}')"; S1="$(stat -c '%s' "$AMD")"
npm run build
H2="$(sha256sum "$AMD" | awk '{print $1}')"; S2="$(stat -c '%s' "$AMD")"
[ "$H1" = "$H2" ] && [ "$S1" = "$S2" ]
echo "AMD_SHA256=$H2"; echo "AMD_BYTES=$S2"; echo 'DETERMINISTIC_BUILD=PASS'
cd "$ROOT"
find "$STAGE" -type d -name node_modules -prune -exec rm -rf {} +

echo
echo '===== GITHUB DIRECT MATERIALIZATION ====='
if ! command -v gh >/dev/null; then echo 'GITHUB_PUSH=SKIPPED_GH_MISSING'; exit 23; fi
if ! gh auth status >/dev/null 2>&1; then echo 'GITHUB_PUSH=SKIPPED_GH_NOT_AUTHENTICATED'; exit 24; fi

python3 - "$STAGE" "$REPO" "$BRANCH" <<'PY'
import base64, json, pathlib, subprocess, sys
stage=pathlib.Path(sys.argv[1]); repo=sys.argv[2]; branch=sys.argv[3]
def api(method, endpoint, payload=None):
    cmd=['gh','api','--method',method,endpoint]
    if payload is not None:
        cmd += ['--input','-']; p=subprocess.run(cmd,input=json.dumps(payload),text=True,capture_output=True)
    else:
        p=subprocess.run(cmd,text=True,capture_output=True)
    if p.returncode:
        print(p.stdout); print(p.stderr,file=sys.stderr); raise SystemExit(p.returncode)
    return json.loads(p.stdout)
ref=api('GET',f'/repos/{repo}/git/ref/heads/{branch}')
parent=ref['object']['sha']; commit=api('GET',f'/repos/{repo}/git/commits/{parent}'); base_tree=commit['tree']['sha']
entries=[]; files=[]
for root in [stage/'public/local/digieranative',stage/'public/local/digieraoffice',stage/'public/local/worksheetlibrary',stage/'public/mod/worksheetgrader']:
    files.extend(p for p in root.rglob('*') if p.is_file())
for i,p in enumerate(sorted(files)):
    rel=p.relative_to(stage).as_posix(); raw=p.read_bytes()
    blob=api('POST',f'/repos/{repo}/git/blobs',{'content':base64.b64encode(raw).decode(),'encoding':'base64'})
    entries.append({'path':rel,'mode':'100644','type':'blob','sha':blob['sha']})
    if (i+1)%25==0 or i+1==len(files): print(f'BLOBS={i+1}/{len(files)}')
tree=api('POST',f'/repos/{repo}/git/trees',{'base_tree':base_tree,'tree':entries})
newcommit=api('POST',f'/repos/{repo}/git/commits',{'message':'feat(preview): materialize worksheet vertical-slice baseline','tree':tree['sha'],'parents':[parent]})
api('PATCH',f'/repos/{repo}/git/refs/heads/{branch}',{'sha':newcommit['sha'],'force':False})
print('GITHUB_COMMIT='+newcommit['sha']); print('GITHUB_FILES='+str(len(files)))
PY
COMMIT="$(gh api "/repos/$REPO/git/ref/heads/$BRANCH" --jq '.object.sha')"
cat > "$SUMMARY" <<EOF
FASTLANE=PASS
STAGE=$STAGE
PHP_LINT_FILES=$PHP_COUNT
XMLDB_PARSE=PASS
NATIVE_JS_TESTS=PASS
DETERMINISTIC_BUILD=PASS
AMD_SHA256=$H2
AMD_BYTES=$S2
GITHUB_BRANCH=$BRANCH
GITHUB_COMMIT=$COMMIT
LOG=$LOG
EOF
cat "$SUMMARY"
trap - EXIT
