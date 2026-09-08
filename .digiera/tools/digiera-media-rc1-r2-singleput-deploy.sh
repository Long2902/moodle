#!/usr/bin/env bash
set -Eeuo pipefail

WEB02="${WEB02:-10.0.10.12}"
MOODLE="${MOODLE:-/var/www/moodle/public}"
MOODLE_USER="${MOODLE_USER:-www-data}"
SITE_ORIGIN="${SITE_ORIGIN:-https://lms.digiera.vn}"
REF="342e40304fe02bc18be000a10bda3d5d70643ee9"
RAW="https://raw.githubusercontent.com/Long2902/moodle/$REF/public"
TS="$(date +%Y%m%d-%H%M%S)"
TMP="/var/tmp/DIGIERA_MEDIA_R2_SINGLEPUT_$TS"
REMOTE_TMP="$TMP"
SNAP1="/root/DIGIERA_MEDIA_PRE_R2_SINGLEPUT_WEB01_$TS.tgz"
SNAP2="/root/DIGIERA_MEDIA_PRE_R2_SINGLEPUT_WEB02_$TS.tgz"
EXPECTED_LOCAL=2026090803
EXPECTED_TINY=2026090803
CRON_WAS_ACTIVE=0
MAINTENANCE_ON=0

FILES=(
  "local/digieramedia/version.php"
  "local/digieramedia/db/services.php"
  "local/digieramedia/lang/en/local_digieramedia.php"
  "local/digieramedia/classes/r2/client_interface.php"
  "local/digieramedia/classes/r2/config.php"
  "local/digieramedia/classes/r2/sigv4_client.php"
  "local/digieramedia/classes/service/file_policy.php"
  "local/digieramedia/classes/service/upload_session_service.php"
  "local/digieramedia/classes/service/upload_finalize_service.php"
  "local/digieramedia/classes/external/create_upload_session.php"
  "local/digieramedia/classes/external/finalize_upload.php"
  "lib/editor/tiny/plugins/digieramedia/version.php"
  "lib/editor/tiny/plugins/digieramedia/templates/modal.mustache"
  "lib/editor/tiny/plugins/digieramedia/amd/src/ui.js"
  "lib/editor/tiny/plugins/digieramedia/amd/src/upload_client.js"
  "lib/editor/tiny/plugins/digieramedia/amd/build/ui.min.js"
  "lib/editor/tiny/plugins/digieramedia/amd/build/upload_client.min.js"
)

restore_service() {
  set +e
  if [[ "$MAINTENANCE_ON" == "1" ]]; then
    runuser -u "$MOODLE_USER" -- bash -c \
      "cd '$MOODLE' && php '$MOODLE/admin/cli/maintenance.php' --disable" >/dev/null 2>&1 || true
  fi
  if [[ "$CRON_WAS_ACTIVE" == "1" ]]; then
    systemctl start moodle-cron.timer >/dev/null 2>&1 || true
  fi
}

fail() {
  local rc=$?
  echo "R2_SINGLEPUT_DEPLOY=FAIL"
  restore_service
  exit "$rc"
}
trap fail ERR

reload_fpm_local() {
  local svc
  svc="$(systemctl list-units --type=service --all 'php*-fpm.service' --no-legend 2>/dev/null | awk 'NR==1{print $1}')"
  if [[ -n "$svc" ]]; then
    systemctl reload "$svc"
  fi
}

write_preflight_php() {
  local base="$1"
  cat > "$base/r2-preflight.php" <<'PHP'
<?php

define('CLI_SCRIPT', true);
require getenv('DIGIERA_MOODLE_ROOT') . '/config.php';
$base = getenv('DIGIERA_PAYLOAD_ROOT');
require_once $base . '/local/digieramedia/classes/r2/client_interface.php';
require_once $base . '/local/digieramedia/classes/r2/config.php';
require_once $base . '/local/digieramedia/classes/r2/sigv4_client.php';

$config = \local_digieramedia\r2\config::load();
$config->require_credentials();
$parts = parse_url($config->endpoint());
$scheme = strtolower((string)($parts['scheme'] ?? ''));
$host = (string)($parts['host'] ?? '');
if ($scheme !== 'https' || $host === '') {
    fwrite(STDERR, "R2 endpoint must be a valid HTTPS URL.\n");
    exit(2);
}
$client = new \local_digieramedia\r2\sigv4_client($config);
$url = $client->presign_put(
    $config->bucket(),
    'digiera/preflight/cors-probe.pdf',
    'application/pdf',
    60
);
if (!str_contains($url, 'X-Amz-Signature=') || !str_contains($url, 'X-Amz-Credential=')) {
    fwrite(STDERR, "Presigned URL contract failed.\n");
    exit(3);
}
$summary = [
    'host' => $host,
    'bucket' => $config->bucket(),
    'ttl' => $config->presign_ttl(),
    'maxsinglebytes' => $config->single_put_max_bytes(),
    'presignedurl' => $url,
];
echo json_encode($summary, JSON_UNESCAPED_SLASHES), PHP_EOL;
PHP
  chmod 0644 "$base/r2-preflight.php"
}

run_r2_preflight_local() {
  local payload="$1"
  local json
  json="$(
    DIGIERA_MOODLE_ROOT="$MOODLE" DIGIERA_PAYLOAD_ROOT="$payload" \
    runuser -u "$MOODLE_USER" -- php "$payload/r2-preflight.php"
  )"
  php -r '
    $j=json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);
    echo "R2_CONFIG=PASS HOST=".$j["host"]." BUCKET=".$j["bucket"]." TTL=".$j["ttl"]." MAX_SINGLE_BYTES=".$j["maxsinglebytes"].PHP_EOL;
  ' "$json" >&2
  printf '%s' "$json" | php -r '$j=json_decode(stream_get_contents(STDIN),true); echo $j["presignedurl"];'
}

echo '===== 1. PRECHECK ====='
ssh -n -o BatchMode=yes -o ConnectTimeout=8 root@"$WEB02" 'true'
echo 'WEB02_SSH=PASS'

runuser -u "$MOODLE_USER" -- bash -c \
  "cd '$MOODLE' && php -r 'define(\"CLI_SCRIPT\", true); require \"$MOODLE/config.php\"; echo \"WEB01_MOODLE_CLI=PASS DATAROOT=\".\$CFG->dataroot.PHP_EOL;'"
ssh -n root@"$WEB02" \
  "runuser -u '$MOODLE_USER' -- bash -c 'cd \"$MOODLE\" && php -r '\''define(\"CLI_SCRIPT\", true); require \"$MOODLE/config.php\"; echo \"WEB02_MOODLE_CLI=PASS DATAROOT=\".\$CFG->dataroot.PHP_EOL;'\'''"
echo 'TWO_NODE_MOODLE_CLI=PASS'

rm -rf "$TMP"
mkdir -p "$TMP"
chmod 0755 "$TMP"

echo '===== 2. DOWNLOAD PINNED R2 SINGLE-PUT PAYLOAD ====='
for f in "${FILES[@]}"; do
  mkdir -p "$TMP/$(dirname "$f")"
  curl -fL "$RAW/$f" -o "$TMP/$f"
done

write_preflight_php "$TMP"
php -l "$TMP/r2-preflight.php" >/dev/null

grep -Fq 'local_digieramedia_create_upload_session' "$TMP/local/digieramedia/db/services.php"
grep -Fq 'local_digieramedia_finalize_upload' "$TMP/local/digieramedia/db/services.php"
grep -Fq 'presign_put' "$TMP/local/digieramedia/classes/r2/sigv4_client.php"
grep -Fq 'head_object' "$TMP/local/digieramedia/classes/r2/sigv4_client.php"
grep -Fq 'Content-Type' "$TMP/lib/editor/tiny/plugins/digieramedia/amd/src/upload_client.js"
grep -Fq 'XMLHttpRequest' "$TMP/lib/editor/tiny/plugins/digieramedia/amd/src/upload_client.js"
grep -Fq '2026090803' "$TMP/local/digieramedia/version.php"
grep -Fq '2026090803' "$TMP/lib/editor/tiny/plugins/digieramedia/version.php"

find "$TMP/local/digieramedia" "$TMP/lib/editor/tiny/plugins/digieramedia" \
  -type f -name '*.php' -print0 | sort -z | xargs -0 -r -n1 php -l >/dev/null
node --check "$TMP/lib/editor/tiny/plugins/digieramedia/amd/src/ui.js"
node --check "$TMP/lib/editor/tiny/plugins/digieramedia/amd/src/upload_client.js"
node --check "$TMP/lib/editor/tiny/plugins/digieramedia/amd/build/ui.min.js"
node --check "$TMP/lib/editor/tiny/plugins/digieramedia/amd/build/upload_client.min.js"
echo 'R2_SINGLEPUT_SOURCE_CONTRACT=PASS'
(cd "$TMP" && sha256sum "${FILES[@]}")

echo '===== 3. R2 CONFIG + SIGNER PREFLIGHT WEB01 ====='
PRESIGNED1="$(run_r2_preflight_local "$TMP")"
# PRESIGNED1 is a short-lived temporary credential. Never echo it.
echo 'WEB01_R2_PREFLIGHT=PASS'

echo '===== 4. STAGE PAYLOAD TO WEB02 ====='
tar -C "$TMP" -cf - "${FILES[@]}" r2-preflight.php | \
  ssh root@"$WEB02" "rm -rf '$REMOTE_TMP'; mkdir -p '$REMOTE_TMP'; chmod 0755 '$REMOTE_TMP'; tar -C '$REMOTE_TMP' -xf -"
LOCAL_STAGE_SHA="$(cd "$TMP" && sha256sum "${FILES[@]}")"
REMOTE_STAGE_SHA="$(ssh -n root@"$WEB02" "cd '$REMOTE_TMP' && sha256sum ${FILES[*]}")"
[[ "$LOCAL_STAGE_SHA" == "$REMOTE_STAGE_SHA" ]]
echo 'WEB02_STAGE_IDENTITY=PASS'

REMOTE_R2_JSON="$(ssh -n root@"$WEB02" \
  "DIGIERA_MOODLE_ROOT='$MOODLE' DIGIERA_PAYLOAD_ROOT='$REMOTE_TMP' runuser -u '$MOODLE_USER' -- php '$REMOTE_TMP/r2-preflight.php'")"
php -r '
  $j=json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);
  echo "R2_CONFIG=PASS HOST=".$j["host"]." BUCKET=".$j["bucket"]." TTL=".$j["ttl"]." MAX_SINGLE_BYTES=".$j["maxsinglebytes"].PHP_EOL;
' "$REMOTE_R2_JSON"
echo 'WEB02_R2_PREFLIGHT=PASS'

echo '===== 5. CORS PREFLIGHT WEB01 (NON-BLOCKING) ====='
CORS_HEADERS="$(curl -sS -D - -o /dev/null -X OPTIONS "$PRESIGNED1" \
  -H "Origin: $SITE_ORIGIN" \
  -H 'Access-Control-Request-Method: PUT' \
  -H 'Access-Control-Request-Headers: content-type' || true)"
if printf '%s\n' "$CORS_HEADERS" | tr -d '\r' | grep -qi '^access-control-allow-origin:'; then
  echo 'R2_CORS_PREFLIGHT=PASS'
else
  echo 'R2_CORS_PREFLIGHT=WARN'
  echo "R2_CORS_EXPECTED_ORIGIN=$SITE_ORIGIN"
  echo 'R2_CORS_EXPECTED_METHOD=PUT'
  echo 'R2_CORS_EXPECTED_HEADER=Content-Type'
fi
unset PRESIGNED1 REMOTE_R2_JSON CORS_HEADERS

echo '===== 6. SNAPSHOT CURRENT PLUGINS ====='
tar -C "$MOODLE" -czf "$SNAP1" local/digieramedia lib/editor/tiny/plugins/digieramedia
ssh -n root@"$WEB02" "tar -C '$MOODLE' -czf '$SNAP2' local/digieramedia lib/editor/tiny/plugins/digieramedia"
echo "WEB01_SNAPSHOT=$SNAP1"
echo "WEB02_SNAPSHOT=$SNAP2"

echo '===== 7. QUIESCE CRON + ENABLE MAINTENANCE ====='
if systemctl is-active --quiet moodle-cron.timer; then
  CRON_WAS_ACTIVE=1
  systemctl stop moodle-cron.timer
fi
runuser -u "$MOODLE_USER" -- bash -c \
  "cd '$MOODLE' && php '$MOODLE/admin/cli/maintenance.php' --enable"
MAINTENANCE_ON=1
echo 'MAINTENANCE=ON'

echo '===== 8. INSTALL WEB01 ====='
for f in "${FILES[@]}"; do
  install -D -m 0644 "$TMP/$f" "$MOODLE/$f"
done
echo 'WEB01_R2_SINGLEPUT_FILES=PASS'

echo '===== 9. INSTALL WEB02 ====='
tar -C "$TMP" -cf - "${FILES[@]}" | ssh root@"$WEB02" "tar -C '$MOODLE' -xf -"
echo 'WEB02_R2_SINGLEPUT_FILES=PASS'

echo '===== 10. MOODLE UPGRADE AS WWW-DATA ====='
runuser -u "$MOODLE_USER" -- bash -c \
  "cd '$MOODLE' && php '$MOODLE/admin/cli/upgrade.php' --non-interactive"
echo 'MOODLE_UPGRADE=PASS'

DB_LOCAL="$(runuser -u "$MOODLE_USER" -- bash -c \
  "cd '$MOODLE' && php -r 'define(\"CLI_SCRIPT\", true); require \"$MOODLE/config.php\"; echo (string)\$DB->get_field(\"config_plugins\",\"value\",[\"plugin\"=>\"local_digieramedia\",\"name\"=>\"version\"]);'")"
DB_TINY="$(runuser -u "$MOODLE_USER" -- bash -c \
  "cd '$MOODLE' && php -r 'define(\"CLI_SCRIPT\", true); require \"$MOODLE/config.php\"; echo (string)\$DB->get_field(\"config_plugins\",\"value\",[\"plugin\"=>\"tiny_digieramedia\",\"name\"=>\"version\"]);'")"
[[ "$DB_LOCAL" == "$EXPECTED_LOCAL" ]]
[[ "$DB_TINY" == "$EXPECTED_TINY" ]]
echo "DB_LOCAL_VERSION=$DB_LOCAL"
echo "DB_TINY_VERSION=$DB_TINY"
echo 'DB_VERSION=PASS'

echo '===== 11. PURGE CACHES + RELOAD PHP-FPM ====='
runuser -u "$MOODLE_USER" -- bash -c \
  "cd '$MOODLE' && php '$MOODLE/admin/cli/purge_caches.php'"
ssh -n root@"$WEB02" \
  "runuser -u '$MOODLE_USER' -- bash -c 'cd \"$MOODLE\" && php \"$MOODLE/admin/cli/purge_caches.php\"'"
reload_fpm_local
ssh -n root@"$WEB02" \
  "svc=\$(systemctl list-units --type=service --all 'php*-fpm.service' --no-legend 2>/dev/null | awk 'NR==1{print \$1}'); if [ -n \"\$svc\" ]; then systemctl reload \"\$svc\"; fi"
echo 'CACHE_FPM_REFRESH=PASS'

echo '===== 12. VERIFY TWO-NODE PARITY ====='
LOCAL_SHA="$(cd "$MOODLE" && sha256sum "${FILES[@]}")"
REMOTE_SHA="$(ssh -n root@"$WEB02" "cd '$MOODLE' && sha256sum ${FILES[*]}")"
[[ "$LOCAL_SHA" == "$REMOTE_SHA" ]]
echo 'TWO_NODE_R2_SINGLEPUT_PARITY=PASS'

echo '===== 13. POST-INSTALL R2 CONFIG CHECK ====='
runuser -u "$MOODLE_USER" -- bash -c \
  "cd '$MOODLE' && php -r 'define(\"CLI_SCRIPT\", true); require \"$MOODLE/config.php\"; \$c=\\local_digieramedia\\r2\\config::load(); \$c->require_credentials(); echo \"WEB01_R2_RUNTIME=PASS\".PHP_EOL;'"
ssh -n root@"$WEB02" \
  "runuser -u '$MOODLE_USER' -- bash -c 'cd \"$MOODLE\" && php -r '\''define(\"CLI_SCRIPT\", true); require \"$MOODLE/config.php\"; \$c=\\local_digieramedia\\r2\\config::load(); \$c->require_credentials(); echo \"WEB02_R2_RUNTIME=PASS\".PHP_EOL;'\'''"
echo 'TWO_NODE_R2_RUNTIME=PASS'

echo '===== 14. RETURN TO SERVICE ====='
runuser -u "$MOODLE_USER" -- bash -c \
  "cd '$MOODLE' && php '$MOODLE/admin/cli/maintenance.php' --disable"
MAINTENANCE_ON=0
if [[ "$CRON_WAS_ACTIVE" == "1" ]]; then
  systemctl start moodle-cron.timer
fi
echo 'MAINTENANCE=OFF'
echo "CRON_TIMER=$(systemctl is-active moodle-cron.timer 2>/dev/null || true)"
trap - ERR

echo 'R2_SINGLEPUT_DEPLOY=PASS'
echo "PINNED_PRODUCT_COMMIT=$REF"
echo 'NEXT=CTRL_F5_AND_UPLOAD_PDF_IMAGE_MP4_SMOKE'
