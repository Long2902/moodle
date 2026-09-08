#!/usr/bin/env bash
set -Eeuo pipefail

PRODUCT_COMMIT="465b2c884681d9179c2465c4d8dcf21797e99f4e"
BASE_HELPER_COMMIT="68c572dd02ff08a76c6acde6ea9d58737875dcf4"
HELPER="/root/.digiera-media-rc1-r2-singleput-permissionfix-inner.sh"
URL="https://raw.githubusercontent.com/Long2902/moodle/${BASE_HELPER_COMMIT}/.digiera/tools/digiera-media-rc1-r2-singleput-deploy.sh"

echo '===== R2 PERMISSION + PREFLIGHT FIX WRAPPER ====='
echo "PINNED_PRODUCT_COMMIT=${PRODUCT_COMMIT}"

curl -fL "$URL" -o "$HELPER"

python3 - "$HELPER" "$PRODUCT_COMMIT" <<'PY'
from pathlib import Path
import sys

path = Path(sys.argv[1])
product = sys.argv[2]
text = path.read_text(encoding='utf-8')

replacements = [
    ('set -Eeuo pipefail\n', 'set -Eeuo pipefail\numask 022\n', 1),
    ('REF="342e40304fe02bc18be000a10bda3d5d70643ee9"', f'REF="{product}"', 1),
    (
        "grep -Fq 'Content-Type' \"$TMP/lib/editor/tiny/plugins/digieramedia/amd/src/upload_client.js\"",
        "grep -Fq \"['name' => 'Content-Type'\" \"$TMP/local/digieramedia/classes/service/upload_session_service.php\"",
        1,
    ),
    (
        'echo "R2_SINGLEPUT_DEPLOY=FAIL"',
        'echo "R2_SINGLEPUT_DEPLOY=FAIL LINE=${BASH_LINENO[0]:-unknown}"',
        1,
    ),
    (
        'DIGIERA_MOODLE_ROOT="$MOODLE" DIGIERA_PAYLOAD_ROOT="$payload" \\\n    runuser -u "$MOODLE_USER" -- php "$payload/r2-preflight.php"',
        'runuser -u "$MOODLE_USER" -- env \\\n      DIGIERA_MOODLE_ROOT="$MOODLE" \\\n      DIGIERA_PAYLOAD_ROOT="$payload" \\\n      php "$payload/r2-preflight.php"',
        1,
    ),
    (
        '"DIGIERA_MOODLE_ROOT=\'$MOODLE\' DIGIERA_PAYLOAD_ROOT=\'$REMOTE_TMP\' runuser -u \'$MOODLE_USER\' -- php \'$REMOTE_TMP/r2-preflight.php\'"',
        '"runuser -u \'$MOODLE_USER\' -- env DIGIERA_MOODLE_ROOT=\'$MOODLE\' DIGIERA_PAYLOAD_ROOT=\'$REMOTE_TMP\' php \'$REMOTE_TMP/r2-preflight.php\'"',
        1,
    ),
]

for old, new, count in replacements:
    if old not in text:
        raise SystemExit('PATCH_TARGET_NOT_FOUND: ' + old.splitlines()[0][:120])
    text = text.replace(old, new, count)

needle = '''for f in "${FILES[@]}"; do
  mkdir -p "$TMP/$(dirname "$f")"
  curl -fL "$RAW/$f" -o "$TMP/$f"
done

write_preflight_php "$TMP"'''
replacement = '''for f in "${FILES[@]}"; do
  mkdir -p "$TMP/$(dirname "$f")"
  curl -fL "$RAW/$f" -o "$TMP/$f"
done

# Payload contains no secrets. Normalise modes so Moodle's www-data preflight can
# traverse/read it even if the parent interactive shell previously used umask 077.
find "$TMP" -type d -exec chmod 0755 {} +
find "$TMP" -type f -exec chmod 0644 {} +

write_preflight_php "$TMP"'''
if needle not in text:
    raise SystemExit('PATCH_TARGET_NOT_FOUND: payload download loop')
text = text.replace(needle, replacement, 1)

path.write_text(text, encoding='utf-8')
PY

bash -n "$HELPER"

grep -Fq 'umask 022' "$HELPER"
grep -Fq "REF=\"${PRODUCT_COMMIT}\"" "$HELPER"
grep -Fq 'find "$TMP" -type d -exec chmod 0755 {} +' "$HELPER"
grep -Fq 'find "$TMP" -type f -exec chmod 0644 {} +' "$HELPER"
grep -Fq 'runuser -u "$MOODLE_USER" -- env' "$HELPER"
grep -Fq "['name' => 'Content-Type'" "$HELPER"

if grep -Fq "Content-Type' \"\$TMP/lib/editor/tiny/plugins/digieramedia/amd/src/upload_client.js\"" "$HELPER"; then
  echo 'OLD_FALSE_CONTENT_TYPE_CONTRACT=FAIL'
  exit 1
fi

echo 'R2_PERMISSIONFIX_WRAPPER=PASS'

if [[ "${DIGIERA_PATCH_ONLY:-0}" == "1" ]]; then
  echo 'R2_PERMISSIONFIX_PATCH_ONLY=PASS'
  exit 0
fi

chmod +x "$HELPER"
exec "$HELPER"
