# DIGIERA Media RC1 — Reopen Metadata Rehydrate Fix

Date: 2026-09-08
Branch: `feature/digiera-media-v1-rc1-fasttrack`

## Browser evidence

The end-to-end insert/save/render path passed, and reopening the Page showed a non-editable DIGIERA component instead of the raw marker. However the reopened component label was `Học liệu DIGIERA: Học liệu DIGIERA` instead of the real media name `DIGIERA RC1 Smoke PDF`.

Therefore round-trip marker rehydration was structurally working but metadata rehydration was incomplete.

## Root cause

`amd/src/reference_component.js` converted every stored marker `[[digiera-ref:<uuid>]]` synchronously into a component using the hard-coded placeholder name `Học liệu DIGIERA`. The stored marker intentionally contains only the logical reference UUID, so the real media name cannot be reconstructed without resolving that UUID through the DIGIERA Media database.

## Fix

Product pin: `a6320632387860352e948ad1ebaad1e1add2c292`

- Added AJAX service `local_digieramedia_resolve_references`.
- Added `local_digieramedia\external\resolve_references` to resolve reference UUIDs to media metadata inside the current editor context.
- `BeforeSetContent` now creates a neutral `Đang tải…` component shell.
- `SetContent` batches all reference UUIDs and hydrates the real media name/type into the TinyMCE component.
- Added runtime `amd/build/reference_component.min.js`.
- Bumped `local_digieramedia` and `tiny_digieramedia` to `2026090802`; Tiny now depends on local `2026090802`.
- Added regression contract `test_reopen_rehydrates_real_media_metadata`.

## Deployment constraint discovered during prior hotfix

`$CFG->dataroot=/mnt/moodledata` is NFSv4 with root-squash. Moodle CLI must run as `www-data`; root remains appropriate for code copy, SSH, snapshots and service management. Do not chmod/chown the shared moodledata to work around root-squash.

NFS-safe deploy helper:

`.digiera/tools/digiera-media-rc1-rehydrate-metadata-deploy.sh`

Helper commit: `9c1f6a01f02d8282757c318800e5cf6128e30dae`

## Required browser verification after deploy

1. Hard refresh the Page edit screen.
2. Reopen the previously saved Page containing `[[digiera-ref:2d4756c5-7253-42ee-afaf-08d3b4c6d927]]`.
3. TinyMCE must show `Học liệu DIGIERA: DIGIERA RC1 Smoke PDF`.
4. Source mode must still show exactly the logical marker, not hydrated metadata HTML.
5. Save again and confirm PDF.js rendering remains `page-width`.

Do not mark full round-trip PASS until step 3 is browser-confirmed.
