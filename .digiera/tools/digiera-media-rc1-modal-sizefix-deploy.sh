#!/usr/bin/env bash
set -Eeuo pipefail

WEB02="${WEB02:-10.0.10.12}"
MOODLE="${MOODLE:-/var/www/moodle/public}"
TINY="$MOODLE/lib/editor/tiny/plugins/digieramedia"
REF="35adbd6466eb3ca958599522eadd8ab6ffa37f89"
RAW="https://raw.githubusercontent.com/Long2902/moodle/$REF/public/lib/editor/tiny/plugins/digieramedia"
TS="$(date +%Y%m%d-%H%M%S)"
TMP="/root/DIGIERA_MEDIA_MODAL_SIZEFIX_$TS"
REMOTE_TMP="/root/DIGIERA_MEDIA_MODAL_SIZEFIX_$TS"
SNAP1="/root/DIGIERA_MEDIA_TINY_PRE_SIZEFIX_WEB01_$TS.tgz"
SNAP2="/root/DIGIERA_MEDIA_TINY_PRE_SIZEFIX_WEB02_$TS.tgz"

FILES=(
  "version.php"
  "styles.css"
  "amd/src/modal.js"
  "amd/build/modal.min.js"
)

fail() {
  echo "MODAL_SIZEFIX_DEPLOY=FAIL"
  echo "If failure occurred after quiesce, inspect maintenance/cron state before returning service."
}
trap fail ERR

mkdir -p "$TMP"
printf '%s\n' "${FILES[@]}" > "$TMP/FILES.txt"

echo '===== 1. PRECHECK ====='
test -f "$TINY/version.php"
ssh -n -o BatchMode=yes -o ConnectTimeout=8 "root@$WEB02" "test -f '$TINY/version.php'"
echo "WEB02_SSH=PASS"

echo '===== 2. DOWNLOAD PINNED SIZEFIX ====='
for rel in "${FILES[@]}"; do
  mkdir -p "$TMP/$(dirname "$rel")"
  curl -fL --retry 3 --connect-timeout 10 --max-time 60 "$RAW/$rel" -o "$TMP/$rel"
done
(
  cd "$TMP"
  sha256sum "${FILES[@]}" > SHA256SUMS
  sha256sum -c SHA256SUMS
)
echo "PINNED_DOWNLOAD=PASS"
cat "$TMP/SHA256SUMS"

echo '===== 3. SIZEFIX CONTRACT ====='
grep -Fq "this.getModal().addClass('tiny-digieramedia-dialog')" "$TMP/amd/src/modal.js"
grep -Fq "'--bs-modal-width': '1280px'" "$TMP/amd/src/modal.js"
grep -Fq "'width': '92vw'" "$TMP/amd/src/modal.js"
grep -Fq "'max-width': '1280px'" "$TMP/amd/src/modal.js"
grep -Fq "'height': '85vh'" "$TMP/amd/src/modal.js"
grep -Fq "1280px" "$TMP/amd/build/modal.min.js"
grep -Fq "92vw" "$TMP/amd/build/modal.min.js"
grep -Fq "85vh" "$TMP/amd/build/modal.min.js"
grep -Fq ".tiny-digieramedia-dialog .modal-content" "$TMP/styles.css"
grep -Fq "height: 100%" "$TMP/styles.css"
grep -Fq "2026090800" "$TMP/version.php"
node --check "$TMP/amd/src/modal.js"
node --check "$TMP/amd/build/modal.min.js"
php -l "$TMP/version.php"
echo "MODAL_SIZEFIX_SOURCE_CONTRACT=PASS"

echo '===== 4. SNAPSHOT BOTH NODES ====='
tar -czf "$SNAP1" -C "$MOODLE/lib/editor/tiny/plugins" digieramedia
ssh -n "root@$WEB02" "tar -czf '$SNAP2' -C '$MOODLE/lib/editor/tiny/plugins' digieramedia"
echo "WEB01_SNAPSHOT=$SNAP1"
echo "WEB02_SNAPSHOT=$SNAP2"

echo '===== 5. STAGE VERIFIED PAYLOAD TO WEB02 ====='
tar -C "$TMP" -czf - FILES.txt SHA256SUMS version.php styles.css amd | \
  ssh "root@$WEB02" "rm -rf '$REMOTE_TMP'; mkdir -p '$REMOTE_TMP'; tar -xzf - -C '$REMOTE_TMP'; cd '$REMOTE_TMP'; sha256sum -c SHA256SUMS"
echo "WEB02_STAGE_IDENTITY=PASS"

echo '===== 6. QUIESCE CRON + ENABLE MAINTENANCE ====='
systemctl stop moodle-cron.timer
systemctl stop moodle-cron.service 2>/dev/null || true
cd "$MOODLE"
sudo -u www-data php admin/cli/maintenance.php --enable
echo "MAINTENANCE=ON"

echo '===== 7. INSTALL WEB01 ====='
OWNER1="$(stat -c %u "$TINY/version.php")"
GROUP1="$(stat -c %g "$TINY/version.php")"
for rel in "${FILES[@]}"; do
  mkdir -p "$TINY/$(dirname "$rel")"
  install -o "$OWNER1" -g "$GROUP1" -m 0644 "$TMP/$rel" "$TINY/$rel"
done
(cd "$TINY" && sha256sum -c "$TMP/SHA256SUMS")
echo "WEB01_SIZEFIX_FILES=PASS"

echo '===== 8. INSTALL WEB02 ====='
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
echo "WEB02_SIZEFIX_FILES=PASS"
REMOTE

echo '===== 9. MOODLE UPGRADE ON WEB01 ONLY ====='
cd "$MOODLE"
sudo -u www-data php admin/cli/upgrade.php --non-interactive
echo "MOODLE_UPGRADE=PASS"

echo '===== 10. PURGE CACHES + RELOAD PHP-FPM ====='
sudo -u www-data php admin/cli/purge_caches.php
ssh -n "root@$WEB02" "cd '$MOODLE' && sudo -u www-data php admin/cli/purge_caches.php"
systemctl reload php8.3-fpm.service
ssh -n "root@$WEB02" "systemctl reload php8.3-fpm.service"
echo "CACHE_FPM_REFRESH=PASS"

echo '===== 11. VERIFY DB + TWO-NODE PARITY ====='
DBVER="$(sudo -u www-data php -r 'define("CLI_SCRIPT", true); require "./config.php"; echo (string)get_config("tiny_digieramedia", "version");')"
echo "DB_TINY_VERSION=$DBVER"
test "$DBVER" = "2026090800"
grep -Fq "2026090800" "$TINY/version.php"
ssh -n "root@$WEB02" "grep -Fq '2026090800' '$TINY/version.php'"
grep -Fq "1280px" "$TINY/amd/build/modal.min.js"
grep -Fq "92vw" "$TINY/amd/build/modal.min.js"
grep -Fq "85vh" "$TINY/amd/build/modal.min.js"
ssh -n "root@$WEB02" "grep -Fq '1280px' '$TINY/amd/build/modal.min.js' && grep -Fq '92vw' '$TINY/amd/build/modal.min.js' && grep -Fq '85vh' '$TINY/amd/build/modal.min.js'"
(
  cd "$TINY"
  sha256sum "${FILES[@]}" > "$TMP/WEB01_INSTALLED_SHA256SUMS"
)
ssh -n "root@$WEB02" "cd '$TINY'; sha256sum ${FILES[*]}" > "$TMP/WEB02_INSTALLED_SHA256SUMS"
diff -u "$TMP/WEB01_INSTALLED_SHA256SUMS" "$TMP/WEB02_INSTALLED_SHA256SUMS"
echo "TWO_NODE_SIZEFIX_PARITY=PASS"

echo '===== 12. RETURN TO SERVICE ====='
sudo -u www-data php admin/cli/maintenance.php --disable
systemctl start moodle-cron.timer
echo "MAINTENANCE=OFF"
echo "CRON_TIMER=$(systemctl is-active moodle-cron.timer)"
echo "MODAL_SIZEFIX_DEPLOY=PASS"
echo "PINNED_PRODUCT_COMMIT=$REF"
