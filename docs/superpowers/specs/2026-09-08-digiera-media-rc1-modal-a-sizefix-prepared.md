# DIGIERA Media System — RC1 Modal A Size Fix Prepared

Date: 2026-09-08
Target: Moodle 5.1 / Web01 + Web02
Status: PREPARED, NOT YET DEPLOYED. Do not mark browser smoke PASS until fresh browser evidence is received.

## Browser evidence

The first Modal A visual-parity deployment completed successfully on both nodes at Tiny plugin version `2026090703`, but browser smoke showed the modal remained approximately Bootstrap `modal-lg` width. The approved three-column content was therefore compressed, especially the center library grid.

## Root cause

Moodle core `Modal.getModal()` returns the `[data-region="modal"]` element, which is the actual `.modal-dialog`. The prior RC1 implementation added `tiny-digieramedia-dialog` later from `ui.js` and relied on stylesheet ordering for width. RemUI/Bootstrap modal sizing continued to constrain the dialog in the browser.

The size fix moves ownership of dialog sizing into `DigieraMediaModal.configure()` after `super.configure()` and sets the approved dimensions directly on the modal dialog:

- Bootstrap custom width: `--bs-modal-width: 1280px`
- width: `92vw`
- max width: `1280px`
- height: `85vh`

CSS now makes `.modal-content` fill the dialog and the modal body/three-column layout consume the available height without restoring global selectors.

## TDD evidence

A dedicated regression contract was added first:

`.digiera/tests/digiera-media-modal-size-contract.py`

RED was observed because the previous `modal.js` did not own the dialog class/dimensions. GREEN verification after the implementation reported:

- `MODAL_SIZE_CONTRACT=PASS`
- `node --check` source modal: PASS
- `node --check` runtime modal build: PASS
- PHP version lint: PASS
- `GREEN_VERIFICATION=PASS`

The legacy Modal A contract was then aligned from `1180px` to the newly approved `1280px` target.

## Source checkpoints

- RED test commit: `5be9053e5673b0fecc2d64e3676f6f7e39f55f9d`
- Size-fix product commit: `e435b8582e53361d58e98c4e7eda553b3a31c802`
- Final contract-alignment commit / deploy pin: `35adbd6466eb3ca958599522eadd8ab6ffa37f89`
- Tiny version to deploy: `2026090800`
- Release: `1.0.0-rc1-fasttrack-modal-a-sizefix`

## Deployment

Use `.digiera/tools/digiera-media-rc1-modal-sizefix-deploy.sh` from Web01. It snapshots the Tiny plugin on both nodes, downloads the pinned four-file payload, validates syntax/contracts, installs both nodes, runs the Moodle upgrade once on Web01, purges caches, reloads PHP-FPM, verifies DB version and SHA parity, then restores service.

## Next browser smoke

After deployment:
1. Hard refresh the Page edit screen.
2. Open **Học liệu DIGIERA**.
3. Confirm the modal is approximately 92% viewport width, capped at 1280px, and approximately 85% viewport height.
4. Confirm the center library column is no longer compressed and the three-column layout visually matches the approved HTML mockup much more closely.
5. Continue select → insert → save → PDF.js page-width → reopen round-trip smoke only after size/layout is accepted.
