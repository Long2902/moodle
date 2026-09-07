#!/usr/bin/env bash
set -Eeuo pipefail

WEB02="${WEB02:-10.0.10.12}"
MOODLE="${MOODLE:-/var/www/moodle/public}"
TINY="$MOODLE/lib/editor/tiny/plugins/digieramedia"
REF="823315c6abee280130a3fec3bd4c7cf3d173c4b0"
RAW="https://raw.githubusercontent.com/Long2902/moodle/$REF/public/lib/editor/tiny/plugins/digieramedia"
TS="$(date +%Y%m%d-%H%M%S)"
TMP="/root/DIGIERA_MEDIA_HOTFIX1_$TS"
REMOTE_TMP="/root/DIGIERA_MEDIA_HOTFIX1_$TS"
SNAP1="/root/DIGIERA_MEDIA_TINY_PRE_HOTFIX1_WEB01_$TS.tgz"
SNAP2="/root/DIGIERA_MEDIA_TINY_PRE_HOTFIX1_WEB02_$TS.tgz"

FILES=(
  "version.php"
  "amd/src/commands.js"
  "amd/src/modal.js"
  "amd/src/ui.js"
  "amd/build/commands.min.js"
  "amd/build/modal.min.js"
  "amd/build/ui.min.js"
)

mkdir -p "$TMP"
cat > "$TMP/SHA256SUMS" <<'SUMS'
456ee9dfc766f099d98daf962c8448d9706427793ecc3c2a815e930534b7ba67  version.php
4649d48747c23ce8d81864858c1d1cef5f225c86efcd6d269a89a7dec0af6efe  amd/src/commands.js
7a52690acacc3e2391d1e9e79061ad5c1d089f7760b0430b859a5684e0ebd3b7  amd/src/modal.js
11c236f329281cf045327a5955c544fbdd3bcf13e04465f419da65304b9204b1  amd/src/ui.js
61c7bd8fcc4395366f0af530b7c65b70b0b11c0d029f0945394db0226f3deb30  amd/build/commands.min.js
df152a3ab03f07bf6c81e50f715a8c2664c68a7a50f4834678e888630185e3f1  amd/build/modal.min.js
f3c4b71d05c3b7189ea4548f985c367415e4d928c53b1b0ce0973a9c973f7294  amd/build/ui.min.js
SUMS
printf '%s\n' "${FILES[@]}" > "$TMP/FILES.txt"

fail() {
  echo "HOTFIX1_DEPLOY=FAIL"
  echo "Maintenance/cron state may intentionally remain stopped for safety if failure occurred after quiesce."
}
trap fail ERR

echo '===== 1. PRECHECK ====='
test -f "$TINY/version.php"
ssh -n -o BatchMode=yes -o ConnectTimeout=8 "root@$WEB02" "test -f '$TINY/version.php'"
echo "WEB02_SSH=PASS"

echo '===== 2. DOWNLOAD PINNED HOTFIX ====='
for rel in "${FILES[@]}"; do
  mkdir -p "$TMP/$(dirname "$rel")"
  curl -fL --retry 3 --connect-timeout 10 --max-time 60 "$RAW/$rel" -o "$TMP/$rel"
done
(cd "$TMP" && sha256sum -c SHA256SUMS)
echo "HOTFIX_DOWNLOAD_IDENTITY=PASS"

echo '===== 3. SNAPSHOT TINY PLUGIN ON BOTH NODES ====='
tar -czf "$SNAP1" -C "$MOODLE/lib/editor/tiny/plugins" digieramedia
ssh -n "root@$WEB02" "tar -czf '$SNAP2' -C '$MOODLE/lib/editor/tiny/plugins' digieramedia"
echo "WEB01_SNAPSHOT=$SNAP1"
echo "WEB02_SNAPSHOT=$SNAP2"

echo '===== 4. STAGE VERIFIED PAYLOAD TO WEB02 ====='
tar -C "$TMP" -czf - FILES.txt SHA256SUMS version.php amd | ssh "root@$WEB02" "rm -rf '$REMOTE_TMP'; mkdir -p '$REMOTE_TMP'; tar -xzf - -C '$REMOTE_TMP'; cd '$REMOTE_TMP'; sha256sum -c SHA256SUMS"
echo "WEB02_STAGE_IDENTITY=PASS"

echo '===== 5. QUIESCE CRON + ENABLE MAINTENANCE ====='
systemctl stop moodle-cron.timer
systemctl stop moodle-cron.service 2>/dev/null || true
cd "$MOODLE"
sudo -u www-data php admin/cli/maintenance.php --enable
echo "MAINTENANCE=ON"

echo '===== 6. INSTALL WEB01 ====='
OWNER1="$(stat -c %u "$TINY/version.php")"
GROUP1="$(stat -c %g "$TINY/version.php")"
for rel in "${FILES[@]}"; do
  mkdir -p "$TINY/$(dirname "$rel")"
  install -o "$OWNER1" -g "$GROUP1" -m 0644 "$TMP/$rel" "$TINY/$rel"
done
(cd "$TINY" && sha256sum -c "$TMP/SHA256SUMS")
echo "WEB01_HOTFIX_FILES=PASS"

echo '===== 7. INSTALL WEB02 ====='
ssh "root@$WEB02" bash -s -- "$TINY" "$REMOTE_TMP" <<'REMOTE'
set -Eeuo pipefail
TINY="$1"
PAYLOAD="$2"
OWNER="$(stat -c %u "$TINY/version.php")"
GROUP="$(stat -c %g "$TINY/version.php")"
while IFS= read -r rel; do
  mkdir -p "$TINY/$(dirname "$rel")"
  install -o "$OWNER" -g "$GROUP" -m 0644 "$PAYLOAD/$rel" "$TINY/$rel"
done < "$PAYLOAD/FILES.txt"
(cd "$TINY" && sha256sum -c "$PAYLOAD/SHA256SUMS")
echo "WEB02_HOTFIX_FILES=PASS"
REMOTE

echo '===== 8. MOODLE UPGRADE ON WEB01 ONLY ====='
cd "$MOODLE"
sudo -u www-data php admin/cli/upgrade.php --non-interactive
echo "MOODLE_UPGRADE=PASS"

echo '===== 9. PURGE CACHES + RELOAD PHP-FPM ====='
sudo -u www-data php admin/cli/purge_caches.php
ssh -n "root@$WEB02" "cd '$MOODLE' && sudo -u www-data php admin/cli/purge_caches.php"
systemctl reload php8.3-fpm.service
ssh -n "root@$WEB02" "systemctl reload php8.3-fpm.service"
echo "CACHE_FPM_REFRESH=PASS"

echo '===== 10. VERIFY DB + CODE ====='
DBVER="$(sudo -u www-data php -r 'define("CLI_SCRIPT", true); require "./config.php"; echo (string)get_config("tiny_digieramedia", "version");')"
echo "DB_TINY_VERSION=$DBVER"
test "$DBVER" = "2026090702"
grep -Fq "2026090702" "$TINY/version.php"
ssh -n "root@$WEB02" "grep -Fq '2026090702' '$TINY/version.php'"
grep -Fq '"./ui"' "$TINY/amd/build/commands.min.js"
grep -Fq '_ui.open' "$TINY/amd/build/commands.min.js"
! grep -Fq '_modal.open' "$TINY/amd/build/commands.min.js"
ssh -n "root@$WEB02" "grep -Fq '\"./ui\"' '$TINY/amd/build/commands.min.js' && grep -Fq '_ui.open' '$TINY/amd/build/commands.min.js' && ! grep -Fq '_modal.open' '$TINY/amd/build/commands.min.js'"
echo "HOTFIX_RUNTIME_CONTRACT=PASS"

echo '===== 11. RETURN TO SERVICE ====='
sudo -u www-data php admin/cli/maintenance.php --disable
systemctl start moodle-cron.timer
echo "MAINTENANCE=OFF"
echo "CRON_TIMER=$(systemctl is-active moodle-cron.timer)"
echo "HOTFIX1_DEPLOY=PASS"
echo "PINNED_COMMIT=$REF"
