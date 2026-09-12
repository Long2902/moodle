#!/usr/bin/env bash
set -Eeuo pipefail
umask 022

# DIGIERA Media V1 Phase 3: Production Preflight & Multi-Node Deployment
# SAFETY: deploys ONLY the four DIGIERA/Course Publisher runtime trees.
# The product payload is pinned to an immutable commit.

PRODUCT_COMMIT="f8d825352d4bea24db80717c0ccd274dfdf16733"
WEB02="${WEB02:-10.0.10.12}"
MOODLE="${MOODLE:-/var/www/moodle/public}"
MOODLE_USER="${MOODLE_USER:-www-data}"
TS="$(date +%Y%m%d-%H%M%S)"
TMP="/var/tmp/DIGIERA_MEDIA_PHASE3_$TS"
REMOTE_TMP="$TMP"
SNAP1="/root/DIGIERA_MEDIA_PRE_PHASE3_WEB01_$TS.tgz"
SNAP2="/root/DIGIERA_MEDIA_PRE_PHASE3_WEB02_$TS.tgz"

RUNTIME_PATHS=(
  "local/digieramedia"
  "lib/editor/tiny/plugins/digieramedia"
  "filter/digieramedia"
  "local/coursepublisher"
)

MAINTENANCE_ON=0
CRON1_WAS_ACTIVE=0

cleanup_stage() {
  rm -rf "$TMP" >/dev/null 2>&1 || true
  ssh -n root@"$WEB02" "rm -rf '$REMOTE_TMP'" >/dev/null 2>&1 || true
}

fail() {
  local rc=$?
  local line="${BASH_LINENO[0]:-unknown}"
  echo "DIGIERA_PHASE3_DEPLOY=FAIL STEP_FAILED_LINE=$line RC=$rc"
  echo "WEB01_SNAPSHOT=$SNAP1"
  echo "WEB02_SNAPSHOT=$SNAP2"
  if [[ "$MAINTENANCE_ON" == "1" ]]; then
    echo 'RECOVERY_REQUIRED=YES'
    echo 'MAINTENANCE_LEFT_ON=YES'
    echo 'CRON_LEFT_QUIESCED=YES'
  fi
  cleanup_stage
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

echo '===== 1. PRECHECK ====='
ssh -n -o BatchMode=yes -o ConnectTimeout=8 root@"$WEB02" 'true'
echo 'WEB02_SSH=PASS'

WEB01_DATAROOT="$(runuser -u "$MOODLE_USER" -- bash -c "cd '$MOODLE' && php -r 'define(\"CLI_SCRIPT\", true); require \"$MOODLE/config.php\"; echo \$CFG->dataroot;'")"
if [[ "$WEB01_DATAROOT" != "/mnt/moodledata" ]]; then
  echo "INVALID_WEB01_DATAROOT: $WEB01_DATAROOT"
  exit 1
fi
echo "WEB01_MOODLE_CLI=PASS DATAROOT=$WEB01_DATAROOT"

WEB02_DATAROOT="$(ssh -n root@"$WEB02" "runuser -u '$MOODLE_USER' -- php -r 'define(\"CLI_SCRIPT\", true); require \"$MOODLE/config.php\"; echo \$CFG->dataroot;'")"
if [[ "$WEB02_DATAROOT" != "/mnt/moodledata" ]]; then
  echo "INVALID_WEB02_DATAROOT: $WEB02_DATAROOT"
  exit 1
fi
echo "WEB02_MOODLE_CLI=PASS DATAROOT=$WEB02_DATAROOT"
echo 'TWO_NODE_MOODLE_CLI=PASS'

runuser -u "$MOODLE_USER" -- test -r /etc/digiera/r2.php
ssh -n root@"$WEB02" "runuser -u '$MOODLE_USER' -- test -r /etc/digiera/r2.php"
echo 'R2_CONFIG_READABLE=PASS (secrets preserved)'

echo '===== 2. DOWNLOAD PINNED TARGETED PAYLOAD ====='
rm -rf "$TMP"
mkdir -p "$TMP"
chmod 0755 "$TMP"
ARCHIVE_URL="https://github.com/Long2902/moodle/archive/${PRODUCT_COMMIT}.tar.gz"
echo "Fetching $ARCHIVE_URL ..."
curl -fsSL "$ARCHIVE_URL" -o "$TMP/source.tar.gz"

tar -xzf "$TMP/source.tar.gz" -C "$TMP" --strip-components=1 \
  "moodle-${PRODUCT_COMMIT}/public/local/digieramedia" \
  "moodle-${PRODUCT_COMMIT}/public/lib/editor/tiny/plugins/digieramedia" \
  "moodle-${PRODUCT_COMMIT}/public/filter/digieramedia" \
  "moodle-${PRODUCT_COMMIT}/public/local/coursepublisher"
rm -f "$TMP/source.tar.gz"

# Strip non-runtime content from the four selected trees only.
rm -rf "$TMP/public/local/digieramedia/tests" "$TMP/public/local/digieramedia/docs"
rm -rf "$TMP/public/local/coursepublisher/tests" "$TMP/public/local/coursepublisher/docs"
rm -rf "$TMP/public/lib/editor/tiny/plugins/digieramedia/tests"
rm -rf "$TMP/public/filter/digieramedia/tests"

# Defense in depth: core/theme/admin must never exist in staging.
for forbidden in admin course lib/classes theme mod; do
  if [[ -e "$TMP/public/$forbidden" ]]; then
    echo "UNEXPECTED_STAGED_CORE_PATH=$forbidden"
    exit 1
  fi
done

for p in "${RUNTIME_PATHS[@]}"; do
  test -d "$TMP/public/$p"
done
find "$TMP/public" -type d -exec chmod 0755 {} +
find "$TMP/public" -type f -exec chmod 0644 {} +
echo "PINNED_PRODUCT_COMMIT=$PRODUCT_COMMIT"
echo 'TARGETED_PAYLOAD_ONLY=PASS'

echo '===== 3. SOURCE CONTRACT ====='
test -f "$TMP/public/local/digieramedia/version.php"
grep -Fq 'update_media' "$TMP/public/local/digieramedia/db/services.php"
test -f "$TMP/public/local/digieramedia/classes/external/update_media.php"
grep -Fq 'alttext' "$TMP/public/local/digieramedia/classes/external/create_reference.php"
grep -Fq 'newmediauuid' "$TMP/public/local/digieramedia/classes/external/update_reference_version.php"
grep -Fq 'alttext' "$TMP/public/local/digieramedia/classes/external/resolve_references.php"
test -f "$TMP/public/local/digieramedia/classes/restore/clone_policy_scope.php"
test -f "$TMP/public/local/digieramedia/classes/integration/coursepublisher_bridge.php"
test -s "$TMP/public/lib/editor/tiny/plugins/digieramedia/amd/build/ui.min.js"
grep -Fq 'renderUploadQueue' "$TMP/public/lib/editor/tiny/plugins/digieramedia/amd/src/ui.js"
test -s "$TMP/public/lib/editor/tiny/plugins/digieramedia/styles.css"
test -f "$TMP/public/local/coursepublisher/classes/local/digiera_integration.php"
test -f "$TMP/public/local/coursepublisher/db/upgrade.php"
find "$TMP/public" -type f -name '*.php' -print0 | sort -z | xargs -0 -r -n1 php -l >/dev/null
echo 'SOURCE_CONTRACT=PASS'

echo '===== 4. STAGE WEB02 + VERIFY STAGE IDENTITY ====='
tar -C "$TMP/public" -cf - . | ssh root@"$WEB02" "rm -rf '$REMOTE_TMP'; mkdir -p '$REMOTE_TMP'; chmod 0755 '$REMOTE_TMP'; tar -C '$REMOTE_TMP' -xf -"
ssh -n root@"$WEB02" "find '$REMOTE_TMP' -type d -exec chmod 0755 {} +; find '$REMOTE_TMP' -type f -exec chmod 0644 {} +"
STAGE1_HASH="$(cd "$TMP/public" && find . -type f -exec sha256sum {} + | sort | sha256sum | awk '{print $1}')"
STAGE2_HASH="$(ssh -n root@"$WEB02" "cd '$REMOTE_TMP' && find . -type f -exec sha256sum {} + | sort | sha256sum | awk '{print \$1}'")"
if [[ "$STAGE1_HASH" != "$STAGE2_HASH" ]]; then
  echo "STAGE_HASH_MISMATCH: $STAGE1_HASH vs $STAGE2_HASH"
  exit 1
fi
echo 'WEB02_STAGE_IDENTITY=PASS'

echo '===== 5. SNAPSHOT CURRENT RUNTIME CODE ====='
for p in "${RUNTIME_PATHS[@]}"; do
  test -d "$MOODLE/$p"
done
tar -C "$MOODLE" -czf "$SNAP1" "${RUNTIME_PATHS[@]}"
echo "WEB01_SNAPSHOT=$SNAP1"
ssh -n root@"$WEB02" "cd '$MOODLE' && tar -czf '$SNAP2' ${RUNTIME_PATHS[*]}"
echo "WEB02_SNAPSHOT=$SNAP2"

echo '===== 6. QUIESCE CRON + ENABLE MAINTENANCE ====='
if systemctl is-active --quiet moodle-cron.timer; then
  CRON1_WAS_ACTIVE=1
  systemctl stop moodle-cron.timer
fi
# Policy: cron is Web01-only. Ensure Web02 timer is not active.
ssh -n root@"$WEB02" 'systemctl stop moodle-cron.timer >/dev/null 2>&1 || true'
runuser -u "$MOODLE_USER" -- php "$MOODLE/admin/cli/maintenance.php" --enable
MAINTENANCE_ON=1
echo 'MAINTENANCE=ON'

echo '===== 7. INSTALL WEB01 TARGET TREES ONLY ====='
for p in "${RUNTIME_PATHS[@]}"; do
  rm -rf "$MOODLE/$p"
  mkdir -p "$(dirname "$MOODLE/$p")"
  cp -a "$TMP/public/$p" "$MOODLE/$p"
done
chown -R "$MOODLE_USER:$MOODLE_USER" \
  "$MOODLE/local/digieramedia" \
  "$MOODLE/lib/editor/tiny/plugins/digieramedia" \
  "$MOODLE/filter/digieramedia" \
  "$MOODLE/local/coursepublisher"
find "$MOODLE/local/digieramedia" "$MOODLE/lib/editor/tiny/plugins/digieramedia" "$MOODLE/filter/digieramedia" "$MOODLE/local/coursepublisher" -type d -exec chmod 0755 {} +
find "$MOODLE/local/digieramedia" "$MOODLE/lib/editor/tiny/plugins/digieramedia" "$MOODLE/filter/digieramedia" "$MOODLE/local/coursepublisher" -type f -exec chmod 0644 {} +
echo 'WEB01_INSTALL=PASS'

echo '===== 8. INSTALL WEB02 TARGET TREES ONLY ====='
for p in "${RUNTIME_PATHS[@]}"; do
  ssh -n root@"$WEB02" "rm -rf '$MOODLE/$p'; mkdir -p \"\$(dirname '$MOODLE/$p')\"; cp -a '$REMOTE_TMP/$p' '$MOODLE/$p'"
done
ssh -n root@"$WEB02" "
  chown -R '$MOODLE_USER:$MOODLE_USER' '$MOODLE/local/digieramedia' '$MOODLE/lib/editor/tiny/plugins/digieramedia' '$MOODLE/filter/digieramedia' '$MOODLE/local/coursepublisher'
  find '$MOODLE/local/digieramedia' '$MOODLE/lib/editor/tiny/plugins/digieramedia' '$MOODLE/filter/digieramedia' '$MOODLE/local/coursepublisher' -type d -exec chmod 0755 {} +
  find '$MOODLE/local/digieramedia' '$MOODLE/lib/editor/tiny/plugins/digieramedia' '$MOODLE/filter/digieramedia' '$MOODLE/local/coursepublisher' -type f -exec chmod 0644 {} +
"
echo 'WEB02_INSTALL=PASS'

echo '===== 9. MOODLE UPGRADE AS WWW-DATA ====='
runuser -u "$MOODLE_USER" -- php "$MOODLE/admin/cli/upgrade.php" --non-interactive
echo 'MOODLE_UPGRADE=PASS'

echo '===== 10. PURGE CACHES + RELOAD PHP-FPM ====='
runuser -u "$MOODLE_USER" -- php "$MOODLE/admin/cli/purge_caches.php"
ssh -n root@"$WEB02" "runuser -u '$MOODLE_USER' -- php '$MOODLE/admin/cli/purge_caches.php'"
reload_fpm_local
ssh -n root@"$WEB02" "svc=\$(systemctl list-units --type=service --all 'php*-fpm.service' --no-legend 2>/dev/null | awk 'NR==1{print \$1}'); if [ -n \"\$svc\" ]; then systemctl reload \"\$svc\"; fi"
echo 'CACHE_FPM_REFRESH=PASS'

echo '===== 11. AMD RUNTIME & TWO-NODE PARITY ====='
test -s "$MOODLE/lib/editor/tiny/plugins/digieramedia/amd/build/ui.min.js"
ssh -n root@"$WEB02" "test -s '$MOODLE/lib/editor/tiny/plugins/digieramedia/amd/build/ui.min.js'"
UI_SHA_1="$(sha256sum "$MOODLE/lib/editor/tiny/plugins/digieramedia/amd/build/ui.min.js" | awk '{print $1}')"
UI_SHA_2="$(ssh -n root@"$WEB02" "sha256sum '$MOODLE/lib/editor/tiny/plugins/digieramedia/amd/build/ui.min.js' | awk '{print \$1}'")"
if [[ "$UI_SHA_1" != "$UI_SHA_2" ]]; then
  echo "AMD_UI_HASH_MISMATCH: $UI_SHA_1 vs $UI_SHA_2"
  exit 1
fi
echo 'AMD_RUNTIME=PASS'

PARITY_1="$(cd "$MOODLE" && find "${RUNTIME_PATHS[@]}" -type f -exec sha256sum {} + | sort | sha256sum | awk '{print $1}')"
PARITY_2="$(ssh -n root@"$WEB02" "cd '$MOODLE' && find ${RUNTIME_PATHS[*]} -type f -exec sha256sum {} + | sort | sha256sum | awk '{print \$1}'")"
if [[ "$PARITY_1" != "$PARITY_2" ]]; then
  echo "TWO_NODE_PARITY_MISMATCH: $PARITY_1 vs $PARITY_2"
  exit 1
fi
echo 'WEB01_WEB02_PARITY=PASS'

echo '===== 12. DISABLE MAINTENANCE + RESTORE CRON ====='
runuser -u "$MOODLE_USER" -- php "$MOODLE/admin/cli/maintenance.php" --disable
MAINTENANCE_ON=0
echo 'MAINTENANCE=OFF'

if [[ "$CRON1_WAS_ACTIVE" == "1" ]]; then
  systemctl start moodle-cron.timer
else
  # The production policy expects Web01 cron active after deploy.
  systemctl start moodle-cron.timer
fi
if ! systemctl is-active --quiet moodle-cron.timer; then
  echo 'CRON_TIMER_START_FAILED'
  exit 1
fi
echo 'CRON_TIMER=active'

if ssh -n root@"$WEB02" 'systemctl is-active --quiet moodle-cron.timer'; then
  echo 'WEB02_CRON_POLICY_VIOLATION=ACTIVE'
  exit 1
fi
echo 'WEB02_CRON_TIMER=inactive'

FAILED_UNITS="$(systemctl list-units --state=failed --no-legend 2>/dev/null | grep -E 'moodle|php|nginx|apache' || true)"
if [[ -n "$FAILED_UNITS" ]]; then
  echo "FAILED_SYSTEMD_UNITS_DETECTED: $FAILED_UNITS"
  exit 1
fi
echo 'SYSTEMD_HEALTH=PASS'

cleanup_stage

echo '===== DEPLOYMENT SUMMARY ====='
echo 'DIGIERA_PHASE3_DEPLOY=PASS'
echo "PRODUCT_COMMIT=$PRODUCT_COMMIT"
echo 'TARGETED_PAYLOAD_ONLY=PASS'
echo 'WEB01_WEB02_PARITY=PASS'
echo 'MOODLE_UPGRADE=PASS'
echo 'AMD_RUNTIME=PASS'
echo 'MAINTENANCE=OFF'
echo 'CRON_TIMER=active'
echo 'WEB02_CRON_TIMER=inactive'
echo 'R2_DEPLOY_MUTATION=NONE'
