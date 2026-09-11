#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
LOCAL="$ROOT/public/local/digieramedia"
FILTER="$ROOT/public/filter/digieramedia"
TINY="$ROOT/public/lib/editor/tiny/plugins/digieramedia"

fail() { echo "LIFECYCLE_SOURCE_CONTRACT=FAIL $*" >&2; exit 1; }

files=(
  "$LOCAL/classes/service/lifecycle_service.php"
  "$LOCAL/classes/service/usage_service.php"
  "$LOCAL/classes/external/get_media_usage.php"
  "$LOCAL/classes/external/trash_media.php"
  "$LOCAL/classes/external/restore_media.php"
  "$LOCAL/classes/external/purge_media.php"
)
for f in "${files[@]}"; do
  test -f "$f" || fail "missing=$f"
done

grep -Fq "function delete_object" "$LOCAL/classes/r2/sigv4_client.php" || fail 'r2 delete missing'
! grep -Fq "delete is outside" "$LOCAL/classes/r2/sigv4_client.php" || fail 'r2 delete still fail-closed'
grep -Fq 'CURLOPT_CUSTOMREQUEST => '\''DELETE'\''' "$LOCAL/classes/r2/sigv4_client.php" || fail 'server DELETE transport missing'
grep -Fq 'R2 DELETE failed with HTTP' "$LOCAL/classes/r2/sigv4_client.php" || fail 'delete failure contract missing'

grep -Fq "mark_live_unresolved" "$LOCAL/classes/service/lifecycle_service.php" || fail 'force purge unresolved transition missing'
grep -Fq "MEDIA_PURGE_FAILED" "$LOCAL/classes/service/lifecycle_service.php" || fail 'partial purge audit/retry contract missing'
grep -Fq "PURGING" "$LOCAL/classes/external/search_media.php" || fail 'PURGING must remain visible in Trash for retry'
grep -Fq "PURGING" "$LOCAL/classes/external/get_media_versions.php" || fail 'PURGING version history missing'

grep -Fq "local_digieramedia_get_media_usage" "$LOCAL/db/services.php" || fail 'usage service missing'
grep -Fq "local_digieramedia_trash_media" "$LOCAL/db/services.php" || fail 'trash service missing'
grep -Fq "local_digieramedia_restore_media" "$LOCAL/db/services.php" || fail 'restore service missing'
grep -Fq "local_digieramedia_purge_media" "$LOCAL/db/services.php" || fail 'purge service missing'

grep -Fq "TRASHED" "$FILTER/classes/text_filter.php" || fail 'renderer trash contract missing'
grep -Fq "2026090902" "$FILTER/version.php" || fail 'filter version not bumped'

grep -Fq "local_digieramedia_get_media_usage" "$TINY/amd/src/ui.js" || fail 'tiny usage call missing'
grep -Fq "local_digieramedia_trash_media" "$TINY/amd/src/ui.js" || fail 'tiny trash call missing'
grep -Fq "local_digieramedia_restore_media" "$TINY/amd/src/ui.js" || fail 'tiny restore call missing'
grep -Fq "local_digieramedia_purge_media" "$TINY/amd/src/ui.js" || fail 'tiny purge call missing'
grep -Fq 'data-action="trash-media"' "$TINY/amd/src/ui.js" || fail 'trash UI action missing'
grep -Fq 'data-action="restore-media"' "$TINY/amd/src/ui.js" || fail 'restore UI action missing'
grep -Fq 'data-action="request-purge"' "$TINY/amd/src/ui.js" || fail 'first purge confirmation missing'
grep -Fq 'data-action="confirm-purge"' "$TINY/amd/src/ui.js" || fail 'second purge confirmation missing'
grep -Fq 'Xác nhận xóa vĩnh viễn' "$TINY/amd/src/ui.js" || fail 'irreversible purge confirmation copy missing'

! grep -R -E "secretaccesskey|Authorization:|X-Amz-Signature|delete_object|CURLOPT_CUSTOMREQUEST" "$TINY/amd/src" >/dev/null || \
  fail 'browser code contains secret/signature/server-delete material'

localversion="$(sed -n 's/.*\$plugin->version = \([0-9][0-9]*\);.*/\1/p' "$LOCAL/version.php" | head -n1)"
[[ "$localversion" =~ ^[0-9]+$ ]] || fail 'local version unreadable'
(( localversion >= 2026090902 )) || fail 'local version older than lifecycle baseline'
grep -Fq "2026090902" "$TINY/version.php" || fail 'tiny version not bumped'
grep -Fq "upgrade_plugin_savepoint(true, 2026090902, 'local', 'digieramedia')" "$LOCAL/db/upgrade.php" || fail 'lifecycle upgrade savepoint missing'
! grep -Eq '^namespace ' "$LOCAL/db/upgrade.php" || fail 'upgrade entrypoint must remain global'

echo 'LIFECYCLE_SOURCE_CONTRACT=PASS'
