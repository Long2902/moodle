# DIGIERA Media RC1 — Insert + Render Browser Smoke PASS

Date: 2026-09-08
Branch: `feature/digiera-media-v1-rc1-fasttrack`

## Scope

This checkpoint records the successful browser smoke after the Modal Save-event hotfix and NFS/root-squash deployment recovery.

## Deployment/finalize evidence

- Moodle CLI is required to run as `www-data` because `$CFG->dataroot` is `/mnt/moodledata` on NFSv4 and root is root-squashed.
- Web01 and Web02 both reported Tiny plugin code version `2026090801`.
- Moodle upgrade completed successfully for `tiny_digieramedia`.
- DB plugin version verified as `2026090801`.
- Moodle caches were purged on Web01 and Web02.
- PHP-FPM was reloaded on Web01 and Web02.
- Four-file parity between nodes passed.
- Maintenance was disabled and cron timer returned active.
- Final sentinel: `DIGIERA_INSERT_EVENTFIX_FINALIZE=PASS`.

## Browser smoke evidence

- Selecting `DIGIERA RC1 Smoke PDF` and clicking **Chèn vào bài** now inserts a visible non-editable DIGIERA component in TinyMCE.
- TinyMCE source view stores the marker contract as `[[digiera-ref:<REFERENCE_UUID>]]` inside the editor HTML.
- Saving the Page renders the referenced PDF on the Moodle view page through PDF.js.
- The PDF viewer opens at width-fit (`page-width` / `Vừa chiều rộng`) as intended.

## Status

- `MODAL_VISUAL_SMOKE=PASS`
- `INSERT_EVENT_SMOKE=PASS`
- `MARKER_STORAGE_SMOKE=PASS`
- `PDFJS_RENDER_SMOKE=PASS`
- `PAGE_WIDTH_RENDER=PASS`
- `DB_TINY_VERSION=2026090801`
- `TWO_NODE_PARITY=PASS`
- `MAINTENANCE=OFF`
- `CRON_TIMER=active`

## Remaining RC1 smoke item

Open the saved Page again in edit mode and confirm the stored marker is rehydrated back into the same DIGIERA component without duplication or corruption. If that round-trip passes, the minimum select-existing-media → create reference → insert → save → render → reopen path is browser-verified end-to-end.
