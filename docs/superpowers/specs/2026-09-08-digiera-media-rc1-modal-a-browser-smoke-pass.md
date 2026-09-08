# DIGIERA Media System — RC1 Modal A browser smoke PASS

Date: 2026-09-08
Branch: `feature/digiera-media-v1-rc1-fasttrack`

## Confirmed in browser

- Modal opens from TinyMCE.
- Approved large sizing is correct in browser: approximately 1280px max width, 92vw, 85vh.
- Three-column layout is visually usable and no longer compressed.
- Header shows `Thư viện học liệu DIGIERA` and does not show the removed design badge.
- Left navigation, upload/dropzone area, search/filter/sort controls, media card grid, right-side preview panel and footer actions are visible and aligned with the approved Modal A direction.
- Seed item `DIGIERA RC1 Smoke PDF` is visible/selectable and its preview metadata is shown.

## Status

`VISUAL_BROWSER_SMOKE=PASS`

This confirms the modal-size regression introduced by the earlier Bootstrap/Moodle default dialog width is fixed.

## Next RC1 smoke actions

1. Click `Chèn vào bài` for `DIGIERA RC1 Smoke PDF`.
2. Confirm TinyMCE inserts a DIGIERA reference component.
3. Save the Page activity.
4. Confirm stored marker renders through `filter_digieramedia` as PDF.js using `page-width`.
5. Re-open Edit Page and confirm the reference round-trips back into the non-editable DIGIERA component.
6. Record any insert/render/round-trip defect as the next RC1 blocker and fix it on the fast-track branch.
