# DIGIERA Media System — RC1 Hotfix1 Deployed / Browser Smoke Pending

Date: 2026-09-07
Target: Moodle 5.1 / Web01 + Web02
Status: TinyMCE runtime hotfix1 deployed successfully to both nodes. Browser smoke must continue before RC1 can be considered smoke-approved. Do not merge to `main` yet.

## Root cause fixed

Browser smoke exposed:

`Uncaught TypeError: (0, _modal.open) is not a function`

The RC1 AMD build mixed a default Modal class export with a named `open()` export in `modal.js`. The built AMD module resolved to the default export, so `commands.js` could not call `_modal.open()` at runtime.

Fix pattern now matches Moodle Tiny plugins:
- `modal.js` only exports the Modal class.
- interaction/open logic lives in `ui.js`.
- `commands.js` imports and calls `open()` from `./ui`.

## Verified source/build

- RC1 branch: `feature/digiera-media-v1-rc1-fasttrack`
- Runtime hotfix build commit: `823315c6abee280130a3fec3bd4c7cf3d173c4b0`
- CI source/hotfix commit: `a1a669348f359d5319c6d9ecdab83984580d4327`
- Workflow run: `34096926472`
- Artifact ID: `10009035361`
- Tiny plugin version: `2026090702`
- Release: `1.0.0-rc1-fasttrack-hotfix1`

CI verification completed successfully for source contract/syntax, TinyMCE AMD build, Moodle PHPUnit runtime suite, bundle build, archive verification and artifact upload.

Built-runtime inspection confirmed:
- `commands.min.js` depends on `./ui` and calls `_ui.open(editor)`.
- `modal.min.js` only exports `DigieraMediaModal`.
- `ui.min.js` exports `open`.
- `_modal.open` is absent from the hotfix runtime path.

## Two-node deployment evidence

Deployment helper: `.digiera/tools/digiera-media-rc1-hotfix1-deploy.sh`

Observed deployment sentinels:
- `WEB02_SSH=PASS`
- `HOTFIX_DOWNLOAD_IDENTITY=PASS`
- `WEB02_STAGE_IDENTITY=PASS`
- `WEB01_HOTFIX_FILES=PASS`
- `WEB02_HOTFIX_FILES=PASS`
- `MOODLE_UPGRADE=PASS`
- `CACHE_FPM_REFRESH=PASS`
- `DB_TINY_VERSION=2026090702`
- `HOTFIX_RUNTIME_CONTRACT=PASS`
- `MAINTENANCE=OFF`
- `CRON_TIMER=active`
- `HOTFIX1_DEPLOY=PASS`
- `PINNED_COMMIT=823315c6abee280130a3fec3bd4c7cf3d173c4b0`

Rollback snapshots created before hotfix:
- Web01: `/root/DIGIERA_MEDIA_TINY_PRE_HOTFIX1_WEB01_20260907-152358.tgz`
- Web02: `/root/DIGIERA_MEDIA_TINY_PRE_HOTFIX1_WEB02_20260907-152358.tgz`

## Next action

Continue browser smoke immediately:
1. Hard-refresh the Moodle Page edit screen.
2. Click **Học liệu DIGIERA** and confirm Modal A opens.
3. Confirm three-column layout without RemUI navigation regression.
4. Confirm seeded `DIGIERA RC1 Smoke PDF` appears in Library.
5. Select and press **Chèn vào bài**.
6. Confirm the visual non-editable DIGIERA component is inserted.
7. Save normally and confirm PDF.js render uses page-width.
8. Reopen/edit/save to confirm reference round-trip.
9. Repeat through Web01/Web02/load-balanced traffic.

If the modal still fails, capture the first fresh Console error after a hard refresh before making another code change.
