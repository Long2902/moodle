# DIGIERA Media System — RC1 Modal A Visual Parity

Date: 2026-09-07
Branch: `feature/digiera-media-v1-rc1-fasttrack`
Status: Source/build updated; browser smoke deployment pending.

## User-approved UI decision

The approved Modal A mockup remains the visual baseline, with one explicit adjustment:

- REMOVE the badge `Phương án A – Modal 3 cột (Khuyến nghị)` from the production modal header.
- Keep the header title `Thư viện học liệu DIGIERA`, icon and normal Moodle close control.
- Preserve Moodle/Edwiser RemUI shell and navigation.

## Implemented in this checkpoint

- Modal width target up to ~1180px and responsive fallbacks.
- Three-column desktop layout: 220px navigation / flexible library / 320px preview.
- Left navigation with Upload, Library, Recent and Trash states.
- Approved upload/dropzone visual block.
- Upload queue region retained for later live R2 wiring.
- Search + type filter + sort controls.
- Three-column media card grid with selected state, media-type treatment, size and modified date.
- Right preview panel with file summary/metadata and advanced Admin/KTV disclosure.
- Footer keeps `Chỉ lưu vào thư viện` and `Chèn vào bài` actions.
- Existing TinyMCE search/select/create-reference/insert flow is preserved.
- AMD runtime build `ui.min.js` refreshed together with `amd/src/ui.js`.
- Tiny plugin revision bumped to `2026090703`, release `1.0.0-rc1-fasttrack-modal-a`.

## Fast-track boundary

This visual-parity batch does NOT claim live Cloudflare R2 upload verified. The RC1 continues to prioritize the already defined minimum smoke path: open Modal A -> select seeded/available Media -> create Reference -> insert -> save -> filter render -> reopen/edit/save.

## Verification evidence available at checkpoint

- Branch advanced from hotfix checkpoint `87c3a714...` by six commits with only the expected modal contract/source/build/version files changed.
- Repository re-read confirms the approved production template contains no `Phương án A` badge.
- Template contains dropzone, upload queue, type filter, sort, media list, preview and advanced panel regions.
- Scoped CSS contains the 220px / flexible / 320px desktop grid and max-width 1180px, without global Moodle/RemUI selector ownership.
- `amd/src/ui.js` and runtime `amd/build/ui.min.js` were both updated in this batch.

## Next action

Deploy the exact RC1 Tiny plugin revision to Web01/Web02, purge Moodle caches/reload PHP-FPM as already defined by the RC1 deployment helper, then continue browser smoke on the real Page editor. Do not merge to `main` yet.
