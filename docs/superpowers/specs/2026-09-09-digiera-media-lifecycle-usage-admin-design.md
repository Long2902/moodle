# DIGIERA Media RC1 — Lifecycle, Usage & Admin/KTV Design

Date: 2026-09-09  
Branch: `feature/digiera-media-v1-rc1-versioning`

## Goal

Complete the next RC1 administration batch after versioning:

1. Usage locations.
2. Trash.
3. Restore.
4. Permanent R2 purge with reference safety.
5. Finish the existing Admin/KTV panel in the TinyMCE DIGIERA Media modal.

The design must preserve the proven marker contract `[[digiera-ref:UUID]]`, the existing Media → immutable Version model, two-node Web01/Web02 deployment, Cloudflare R2 direct upload, and the already-verified FOLLOW_CURRENT / PINNED_VERSION behavior.

## Existing foundation we will reuse

The current code already has most of the data model required for this batch:

- `local_digieramedia_media.status` and state constants `ACTIVE`, `TRASHED`, `PURGING`, `PURGED`.
- `local_digieramedia_trash` with `mediaid`, `deletedby`, `deletedat`, `purgeafter`, `reason`, and `previousvisibility`.
- `local_digieramedia_version.status` includes `PURGED`, plus `timepurged`.
- `local_digieramedia_reference` already records `contextid`, `courseid`, `cmid`, `component`, `entitytype`, `entityid`, `fieldname`, version mode and status.
- Capabilities already exist for `trashown`, `trash`, `restore`, `viewusage`, and `purge`.
- The R2 client interface already defines `delete_object()`, although the current SigV4 implementation intentionally fails closed.
- The library search endpoint already understands a `trash` tab and filters `m.status = TRASHED` there.

Therefore this batch should not introduce a second lifecycle table or a second media identity model.

## Lifecycle semantics

### ACTIVE → TRASHED

Trash is a soft-delete operation only.

- Set Media status to `TRASHED`.
- Insert/update one row in `local_digieramedia_trash` with actor, timestamp, reason and previous visibility.
- Do **not** delete any R2 object.
- Do **not** delete or rewrite references.
- Do **not** create a new Media UUID.
- Remove the item from the normal Library tab and show it in the Trash tab.
- Block creation of new references to a trashed Media.
- Block Replace/version-management mutations while Media is trashed.

### Existing content while Media is TRASHED

Existing references must continue rendering while the Media is only in Trash.

This is intentional safety behavior: sending a file to Trash must not silently break live courses. The renderer will therefore accept Media status `ACTIVE` or `TRASHED` for an already-existing ACTIVE/DRAFT reference, while new insertions still require Media status `ACTIVE`.

This is a deliberate change from the current filter, which renders only `ACTIVE` Media.

### TRASHED → ACTIVE

Restore:

- Requires restore permission.
- Set Media status back to `ACTIVE`.
- Restore `visibility` from `local_digieramedia_trash.previousvisibility`.
- Keep the same Media UUID, Version rows, R2 object keys, currentversionid and references.
- Remove or close the active trash metadata record so a later trash cycle is unambiguous.
- Existing references continue rendering without marker changes.

### TRASHED → PURGING → PURGED

Permanent delete means physical object deletion from R2 while keeping minimal DB tombstones for audit/referential integrity.

Normal purge is rejected when active/draft references still exist.

If there are zero live references:

1. Lock the Media lifecycle operation.
2. Set Media status `PURGING`.
3. For every non-purged Version, call server-side R2 `delete_object(bucket, objectkey)`.
4. Treat R2 2xx as success; treat 404/not-found as idempotent success.
5. After each successful physical delete, mark Version `PURGED` and set `timepurged`.
6. After all versions are physically absent, set Media status `PURGED`.
7. Keep Media/Version IDs and UUID metadata as tombstones; do not reuse UUIDs.
8. Write audit metadata without secrets.

If one R2 delete fails, Media remains `PURGING`; completed version deletions remain `PURGED`; retry continues idempotently from the remaining versions.

## Force purge with live references

Force purge is deliberately separate from normal purge.

Requirements:

- Requires `local/digieramedia:purge` specifically. The existing capability has no ordinary archetype assignment; this remains a high-risk Admin/KTV permission.
- UI must show the current live-reference count and an explicit destructive confirmation.
- Confirmation text must state that N references will become unavailable.
- Server re-counts references after the confirmation; browser-provided counts are never trusted.
- Before final Media `PURGED`, all live references for that Media are changed to `UNRESOLVED`.
- Marker UUIDs are left in Moodle content. The filter renders the existing `Học liệu hiện không khả dụng.` placeholder rather than silently removing content.
- Force purge is auditable.

## Usage locations

Usage comes from the reference registry already stored in `local_digieramedia_reference`.

A new read endpoint returns visible references for one Media with:

- reference UUID,
- reference status,
- course id/name when resolvable,
- cmid and activity/module name when resolvable,
- component/entity/field fallback metadata,
- version mode (`FOLLOW_CURRENT` or `PINNED_VERSION`),
- pinned version number when applicable,
- a safe view/edit URL when Moodle can resolve one,
- created/modified time.

Permission rule:

- Caller requires `local/digieramedia:viewusage`.
- Each returned usage is additionally filtered by the caller's ability to view the referenced course/context, unless the caller has system-wide `viewall/manage` rights.
- Do not expose inaccessible course names, URLs or entity metadata.

Accuracy rule:

This RC1 usage panel reports the **reference registry**, not a full database-wide content rescan. If a marker was manually removed without a future reconciliation job marking its reference stale, usage may over-count. This is safe for purge because it can only block deletion unnecessarily, never allow deletion too early. Full stale-reference reconciliation remains an Operations/Migration task.

## API surface

Add AJAX services:

- `local_digieramedia_get_media_usage` — read usage + lifecycle/permission summary for selected Media.
- `local_digieramedia_trash_media` — soft-delete ACTIVE Media.
- `local_digieramedia_restore_media` — restore TRASHED Media.
- `local_digieramedia_purge_media` — permanent R2 purge; accepts `force` boolean but enforces server-side capability and reference count.

Existing `search_media` remains the list API and keeps the `trash` tab. It may be extended with only the capability fields needed by the UI; destructive authorization remains server-side in the lifecycle endpoints.

## Server architecture

### `usage_service`

Responsibilities:

- Resolve one Media by UUID.
- Count/list live references.
- Resolve friendly Moodle location labels and safe URLs.
- Apply per-reference visibility/access filtering.
- Return permission/lifecycle summary for the Admin/KTV panel.

### `lifecycle_service`

Responsibilities:

- `trash()`
- `restore()`
- `purge(force=false)`
- ownership/capability enforcement,
- lifecycle locking,
- state transition validation,
- DB transaction boundaries,
- audit records,
- R2 delete orchestration.

The external classes remain thin Moodle parameter/context adapters and delegate business rules to these services.

### R2 delete implementation

Implement `sigv4_client::delete_object()` using the same server-side SigV4 credentials and target validation as HEAD/PUT.

- HTTP method: `DELETE`.
- Same configured bucket only.
- No browser presigned DELETE.
- No credentials returned to JavaScript.
- 2xx = success.
- 404 = idempotent success.
- Other HTTP/transport failures throw and leave Media `PURGING` for safe retry.

## Permissions

Preserve the existing capability model:

- Owner + `trashown`: may trash own Media where context rules permit.
- System `trash`: may trash Media beyond ownership.
- `restore`: may restore.
- `viewusage`: may view usage locations subject to referenced-context visibility.
- `purge`: required for permanent delete and force purge.
- `manage`/`viewall`: may provide broad read/manage access but do not replace the explicit `purge` capability for destructive physical deletion.

No secret or R2 credential is exposed by any endpoint.

## TinyMCE UI

Keep the approved three-column modal. Extend only the right Admin/KTV panel and Trash behavior.

### ACTIVE Media panel

Order:

1. Existing Version History / Follow-Pin controls.
2. `Vị trí đang sử dụng (N)`.
3. Usage list with course/activity labels and links.
4. `Vòng đời học liệu`.
5. `Đưa vào thùng rác` when caller is allowed.

If N > 0, show informational text:

`Đưa vào thùng rác không xóa file R2 và không làm hỏng N vị trí đang sử dụng.`

### TRASHED Media panel

Show:

- badge `Trong thùng rác`,
- deleted date/user/reason when available,
- live usage count,
- `Khôi phục` when permitted,
- `Xóa vĩnh viễn` only when permitted.

Version replacement and new-reference controls are disabled for TRASHED Media.

### Permanent delete confirmation

Zero live usages:

`Xóa vĩnh viễn tất cả phiên bản vật lý khỏi Cloudflare R2? Thao tác này không thể hoàn tác.`

With live usages and force permission:

`Học liệu đang được dùng tại N vị trí. Xóa cưỡng bức sẽ xóa file vật lý khỏi R2 và làm N reference chuyển sang trạng thái không khả dụng.`

The destructive button must be visually distinct and require an explicit second confirmation action.

## Renderer behavior

- Reference status ACTIVE/DRAFT + Media ACTIVE: render normally.
- Reference status ACTIVE/DRAFT + Media TRASHED: render normally from existing READY version.
- Media PURGING/PURGED: render unavailable.
- Reference UNRESOLVED: render unavailable.

New reference creation and replacement continue to require Media ACTIVE.

## Audit

Lifecycle operations write redacted entries to the existing audit table where available:

- `MEDIA_TRASH`
- `MEDIA_RESTORE`
- `MEDIA_PURGE_START`
- `MEDIA_PURGE_COMPLETE`
- `MEDIA_FORCE_PURGE`
- `MEDIA_PURGE_FAILED`

Never log R2 Access Key ID, Secret Access Key, Authorization headers or presigned URLs.

## Versioning / upgrade

Target plugin versions for this batch:

- `local_digieramedia = 2026090902`
- `tiny_digieramedia = 2026090902`
- release suffix `1.0.0-rc1-fasttrack-lifecycle`

No new table is expected because the current schema already includes Trash, status and purge metadata. `upgrade.php` still receives a `2026090902` savepoint so new AJAX services are registered through the normal Moodle upgrade path. The global `xmldb_local_digieramedia_upgrade()` entrypoint regression remains covered.

## TDD / verification gates

Automated RED→GREEN coverage before deployment:

1. Trash ACTIVE Media -> TRASHED and trash metadata saved; R2 delete not called.
2. Existing reference to TRASHED Media still renders.
3. New reference to TRASHED Media is rejected.
4. Restore -> ACTIVE, previous visibility restored, same Media UUID/current version.
5. Usage endpoint returns only accessible locations and correct live count.
6. Normal purge with live refs is rejected and R2 delete is not called.
7. Purge with zero refs deletes every physical version, marks Versions/Media PURGED and is idempotent.
8. Partial R2 failure leaves Media PURGING and retry deletes only remaining versions.
9. Force purge requires `purge`, marks live refs UNRESOLVED, then purges versions.
10. SigV4 DELETE source/runtime contract is present and secrets never reach browser code.
11. Existing upload, replace, Follow/Pin, marker round-trip and renderer tests remain green.
12. Moodle upgrade function remains globally callable.

## Two-node deployment gate

Use a pinned incremental helper, same production discipline as versioning:

- precheck Web01/Web02 CLI + R2 config,
- download pinned payload,
- source/runtime contract,
- two-node stage identity,
- snapshots,
- maintenance + cron quiesce,
- install both nodes,
- Moodle upgrade,
- DB/service/version verification,
- cache purge + PHP-FPM reload,
- two-node parity,
- R2 runtime configuration check,
- maintenance OFF + cron active.

The deploy helper must never perform a real R2 DELETE as a preflight.

## Browser acceptance

Use expendable RC1 test Media only.

1. Create/choose a Media with one live reference.
2. Usage panel shows the correct location/count.
3. Trash it: Library loses it, Trash shows it, existing course content still renders.
4. Attempt to create a new reference to the trashed Media: rejected/not offered.
5. Restore it: returns to Library with same UUID/version and existing content unchanged.
6. Trash an unused test Media, permanently delete it, verify R2 object no longer exists and UI shows it as purged/removed from Trash.
7. For force-purge acceptance, use a separate disposable Media/reference: confirm warning count, purge, verify marker remains but renderer shows `Học liệu hiện không khả dụng.`
8. Verify no unrelated PDF/image/video/audio/Office upload/versioning regressions.

## Explicitly deferred

Not part of this batch:

- true `Đã dùng gần đây` tracking,
- automatic stale-reference reconciliation/content rescanning,
- scheduled retention purge worker,
- multipart upload >100 MiB,
- backup/restore and Course Publisher acceptance,
- migration acceptance,
- bulk trash/purge UI.
