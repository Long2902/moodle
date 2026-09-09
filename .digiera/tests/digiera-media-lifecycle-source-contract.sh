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
grep -Fq "DELETE" "$LOCAL/classes/r2/sigv4_client.php" || fail 'DELETE canonical method missing'

grep -Fq "local_digieramedia_get_media_usage" "$LOCAL/db/services.php" || fail 'usage service missing'
grep -Fq "local_digieramedia_trash_media" "$LOCAL/db/services.php" || fail 'trash service missing'
grep -Fq "local_digieramedia_restore_media" "$LOCAL/db/services.php" || fail 'restore service missing'
grep -Fq "local_digieramedia_purge_media" "$LOCAL/db/services.php" || fail 'purge service missing'

grep -Fq "TRASHED" "$FILTER/classes/text_filter.php" || fail 'renderer trash contract missing'
grep -Fq "local_digieramedia_get_media_usage" "$TINY/amd/src/ui.js" || fail 'tiny usage call missing'
grep -Fq "local_digieramedia_trash_media" "$TINY/amd/src/ui.js" || fail 'tiny trash call missing'
grep -Fq "local_digieramedia_restore_media" "$TINY/amd/src/ui.js" || fail 'tiny restore call missing'
grep -Fq "local_digieramedia_purge_media" "$TINY/amd/src/ui.js" || fail 'tiny purge call missing'

! grep -R -E "secretaccesskey|Authorization:|X-Amz-Signature" "$TINY/amd/src" >/dev/null || fail 'browser code contains secret/signature material'

grep -Fq "2026090902" "$LOCAL/version.php" || fail 'local version not bumped'
grep -Fq "2026090902" "$TINY/version.php" || fail 'tiny version not bumped'
grep -Fq "upgrade_plugin_savepoint(true, 2026090902, 'local', 'digieramedia')" "$LOCAL/db/upgrade.php" || fail 'upgrade savepoint missing'
! grep -Eq '^namespace ' "$LOCAL/db/upgrade.php" || fail 'upgrade entrypoint must remain global'

echo 'LIFECYCLE_SOURCE_CONTRACT=PASS'
