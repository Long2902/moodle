# DIGIERA Media RC1 Lifecycle — Browser Acceptance PASS

Date: 2026-09-10 (UTC+7)
Branch: `feature/digiera-media-v1-rc1-lifecycle`
Status: **BROWSER ACCEPTANCE PASS**

## Frozen revisions

- Product commit: `b2acc33e36984dda5d8b6232af44884fa11304b6`
- Verified deploy helper commit: `e875b1aa76a9890b7fe90d02d48e9ead2635503e`
- Deployed plugin versions:
  - `local_digieramedia = 2026090902`
  - `tiny_digieramedia = 2026090902`
  - `filter_digieramedia = 2026090902`

## Deployment evidence

The two-node deployment had already completed successfully with:

- source/runtime contract PASS
- Web01/Web02 staged identity PASS
- snapshots created on both nodes
- Moodle upgrade PASS
- DB/service contract PASS
- non-destructive R2 DELETE runtime preflight PASS on both nodes
- cache/FPM refresh PASS
- two-node parity PASS
- maintenance OFF
- cron timer active
- terminal sentinel `DIGIERA_LIFECYCLE_DEPLOY=PASS`

## Browser acceptance evidence supplied by operator

The operator completed the lifecycle smoke sequence and supplied screenshots plus an independent check of Cloudflare R2.

### Usage Locations

PASS.

Observed:

- `Vị trí đang sử dụng (5)` displayed for an existing media item.
- Entries showed course/activity labels and pinned-version detail where applicable (`ghim v1`).
- Lifecycle panel explained that moving to Trash would not delete the R2 object and would not break existing usage.

### Trash

PASS.

Observed:

- Media moved to the `Thùng rác` tab.
- Success message: `Đã đưa học liệu vào thùng rác. File R2 chưa bị xóa.`
- Media remained visible as a Trash item.
- Lifecycle metadata showed Trash state, deletion date/user/reason.
- Restore and permanent-delete actions were available.

### Restore

PASS.

Observed:

- Restore completed with success message `Đã khôi phục học liệu.`
- Media returned to the active library.
- Media status returned to ready/active.
- Existing version history remained visible after restore.

### Permanent Delete confirmation

PASS.

Observed on a trashed media item with a live usage count:

- usage count remained visible (`Vị trí đang sử dụng (1)`).
- purge entered a second confirmation state.
- UI presented `Không xóa` and `Xác nhận xóa vĩnh viễn` actions.
- This satisfied the two-step destructive-action requirement.

### Force Purge / R2 deletion

PASS.

Observed:

- purge completed with UI success message `Đã xóa vĩnh viễn học liệu khỏi Cloudflare R2.`
- the purged media no longer appeared in the Trash result list.
- the operator independently checked Cloudflare R2 and confirmed the physical object had actually been deleted.

This is the required real destructive R2 acceptance evidence; deployment preflight itself remained non-destructive.

## Acceptance result

The lifecycle batch is accepted for the tested production-like environment:

- Usage Locations: PASS
- Trash: PASS
- Restore: PASS
- Version history read-only while trashed: PASS by observed UI
- Two-step permanent-delete confirmation: PASS
- Live-usage-aware force purge UI: PASS
- Server-side R2 DELETE: PASS
- Physical object removed from Cloudflare R2: PASS by operator verification

Automated regression coverage from the verified RC1 run remains the evidence for safe unresolved-reference rendering and partial-delete retry/error handling. No separate screenshot of an unresolved rendered reference was captured in this browser acceptance set.

## Lifecycle batch state

`DIGIERA_MEDIA_LIFECYCLE_BROWSER_ACCEPTANCE=PASS`

The batch can now be treated as deployed and browser-verified. No merge to `main` has been performed.

## Next RC1 work

Continue with the remaining RC1 gaps outside the completed lifecycle batch, prioritizing:

1. true per-user Recent behavior
2. permission/capability matrix acceptance for Admin/KTV/Teacher/non-privileged roles
3. backup/restore production acceptance and Course Publisher compatibility
4. migration/legacy-content acceptance
5. alt/caption metadata UX if retained in RC1 scope
6. >100 MiB multipart upload remains explicitly deferred unless RC1 scope is expanded
