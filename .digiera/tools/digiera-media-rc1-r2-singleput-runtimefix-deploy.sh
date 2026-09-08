#!/usr/bin/env bash
set -Eeuo pipefail

BASE_HELPER_COMMIT="68c572dd02ff08a76c6acde6ea9d58737875dcf4"
PRODUCT_REF="465b2c884681d9179c2465c4d8dcf21797e99f4e"
BASE_URL="https://raw.githubusercontent.com/Long2902/moodle/${BASE_HELPER_COMMIT}/.digiera/tools/digiera-media-rc1-r2-singleput-deploy.sh"
TMP_HELPER="/root/.digiera-media-rc1-r2-singleput-runtimefix-inner.sh"

cleanup() {
    rm -f "$TMP_HELPER"
}
trap cleanup EXIT

echo '===== R2 RUNTIME BUILD FIX WRAPPER ====='
echo "PINNED_PRODUCT_COMMIT=$PRODUCT_REF"

curl -fL "$BASE_URL" -o "$TMP_HELPER"
chmod 0700 "$TMP_HELPER"

sed -i -E "s/^REF=\"[0-9a-f]+\"$/REF=\"${PRODUCT_REF}\"/" "$TMP_HELPER"

grep -Fxq "REF=\"${PRODUCT_REF}\"" "$TMP_HELPER"
grep -Fq 'amd/build/upload_client.min.js' "$TMP_HELPER"
grep -Fq 'amd/build/ui.min.js' "$TMP_HELPER"
bash -n "$TMP_HELPER"

echo 'R2_RUNTIMEFIX_WRAPPER=PASS'
exec "$TMP_HELPER"
