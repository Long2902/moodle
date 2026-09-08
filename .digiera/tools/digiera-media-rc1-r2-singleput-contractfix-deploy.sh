#!/usr/bin/env bash
set -Eeuo pipefail

PRODUCT_COMMIT="465b2c884681d9179c2465c4d8dcf21797e99f4e"
BASE_HELPER_COMMIT="68c572dd02ff08a76c6acde6ea9d58737875dcf4"
HELPER="/root/digiera-media-rc1-r2-singleput-contractfix-inner.sh"
URL="https://raw.githubusercontent.com/Long2902/moodle/${BASE_HELPER_COMMIT}/.digiera/tools/digiera-media-rc1-r2-singleput-deploy.sh"

echo '===== R2 SOURCE-CONTRACT FIX WRAPPER ====='
echo "PINNED_PRODUCT_COMMIT=${PRODUCT_COMMIT}"

curl -fL "$URL" -o "$HELPER"

# Pin the verified product commit that contains both committed AMD runtime builds.
sed -i "s/^REF=.*/REF=\"${PRODUCT_COMMIT}\"/" "$HELPER"

# The browser client intentionally consumes server-provided requiredheaders. Content-Type
# therefore belongs to the upload-session server contract, not as a client-side literal.
python3 - "$HELPER" <<'PY'
from pathlib import Path
import sys

path = Path(sys.argv[1])
text = path.read_text(encoding='utf-8')
old = "grep -Fq 'Content-Type' \"$TMP/lib/editor/tiny/plugins/digieramedia/amd/src/upload_client.js\""
new = "grep -Fq \"['name' => 'Content-Type'\" \"$TMP/local/digieramedia/classes/service/upload_session_service.php\""
if old not in text:
    raise SystemExit('CONTRACT_PATCH_TARGET=NOT_FOUND')
text = text.replace(old, new, 1)
text = text.replace(
    'echo "R2_SINGLEPUT_DEPLOY=FAIL"',
    'echo "R2_SINGLEPUT_DEPLOY=FAIL LINE=${BASH_LINENO[0]:-unknown}"',
    1,
)
path.write_text(text, encoding='utf-8')
PY

bash -n "$HELPER"
grep -Fq "REF=\"${PRODUCT_COMMIT}\"" "$HELPER"
grep -Fq "upload_session_service.php" "$HELPER"
grep -Fq "['name' => 'Content-Type'" "$HELPER"
if grep -Fq "Content-Type' \"\$TMP/lib/editor/tiny/plugins/digieramedia/amd/src/upload_client.js\"" "$HELPER"; then
    echo 'OLD_FALSE_CONTRACT_STILL_PRESENT=FAIL'
    exit 1
fi

chmod +x "$HELPER"
echo 'R2_CONTRACTFIX_WRAPPER=PASS'
exec "$HELPER"
