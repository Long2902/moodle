# DIGIERA Media RC1 — Insert Event Fix

Date: 2026-09-08
Branch: `feature/digiera-media-v1-rc1-fasttrack`

## Browser evidence

- Modal A visual smoke: PASS.
- User selected `DIGIERA RC1 Smoke PDF` and clicked `Chèn vào bài`.
- The only `service.php` response supplied was the library search response (`items/page/pagesize/total/canupload`), proving `local_digieramedia_create_reference` had not been called.

## Root cause

`tiny_digieramedia/amd/src/modal.js` extended `core/modal` while the footer relied on `data-action="save"` and the UI listened for `ModalEvents.save`.

In Moodle 5.1, `core/modal_save_cancel` is the modal subclass that registers the save/cancel lifecycle (`registerCloseOnSave()` / `registerCloseOnCancel()`). Therefore the visible Save button did not emit the save flow when the custom modal extended only the base `core/modal`.

## Fix

Product commit: `b9a54d768b9ac3550c9b6f94e531c5cbbeab2138`

Changes:

- `DigieraMediaModal` now extends `core/modal_save_cancel`.
- Existing custom template and approved 1280px / 92vw / 85vh sizing are preserved.
- Error/status region moved inside the scrollable center body so API/insert failures are visible.
- Tiny plugin version bumped to `2026090801`.
- Release: `1.0.0-rc1-fasttrack-insert-event-fix`.

Regression contract:

- `.digiera/tests/digiera-media-insert-event-contract.py`
- RED captured before product fix.
- GREEN conditions are satisfied by the product commit: ModalSaveCancel import/extension, save/cancel actions present, ModalEvents.save listener present, create-reference call present, status region visible inside body.

## Deployment helper

Helper commit: `b09e18f32d7f11d5974455c39be2c54f78039c93`

Helper path:

`.digiera/tools/digiera-media-rc1-insert-eventfix-deploy.sh`

The helper pins the product commit, snapshots both nodes, stages/verifies payload identity, quiesces cron, enables maintenance, deploys Web01/Web02, upgrades Moodle once, purges cache, reloads PHP-FPM, verifies two-node SHA parity, and returns service.

## Next smoke

1. Deploy helper on Web01.
2. Ctrl+F5 browser.
3. Open Modal A and select `DIGIERA RC1 Smoke PDF`.
4. Click `Chèn vào bài`.
5. Expected: a new AJAX request for `local_digieramedia_create_reference`, modal closes after success, DIGIERA reference component appears in TinyMCE.
6. Save page and verify PDF.js render / `page-width`.
7. Re-open edit page and verify round-trip reference persistence.
