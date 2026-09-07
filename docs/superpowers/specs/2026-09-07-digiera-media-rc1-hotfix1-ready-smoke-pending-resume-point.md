# DIGIERA Media System — RC1 Hotfix1 Ready / Browser Smoke Pending

Date: 2026-09-07
Target: Moodle 5.1 / Web01 + Web02
Status: TinyMCE modal runtime defect root-caused and hotfix build verified. Deployment of hotfix1 to Web01/Web02 and browser smoke are pending. Do not merge to `main` yet.

## 1. Browser evidence

During RC1 smoke, the **Học liệu DIGIERA** TinyMCE button rendered correctly in both Page editors, but clicking it opened no modal. Chrome DevTools Console reproduced the failure reliably:

`Uncaught TypeError: (0, _modal.open) is not a function`

This proved Tiny plugin registration/configuration was active and localized the failure to the click action/module boundary before AJAX library search.

## 2. Root cause

RC1 source imported named `open` from `./modal` while the same module also default-exported `DigieraMediaModal`. Moodle's Grunt/Babel AMD output returned the modal default export as the module value, so `commands.min.js` attempted `_modal.open(editor)` against a class value with no `open` member.

This is a module-structure defect, not a RemUI, permission, seed-data, filter, or cache defect.

## 3. Fix

Hotfix1 follows Moodle Tiny plugin separation pattern:

- `modal.js`: only the default `DigieraMediaModal` class and modal registration.
- `ui.js`: owns the exported `open(editor)` interaction flow, AJAX library search, selection and reference insertion.
- `commands.js`: imports `open` from `./ui`.
- Tiny plugin version: `2026090702`.
- Tiny release: `1.0.0-rc1-fasttrack-hotfix1`.
- Regression contract asserts commands import UI, modal does not export `open`, and UI imports the modal default class.

## 4. Verification evidence

Verified source commit before publishing build outputs:

`a1a669348f359d5319c6d9ecdab83984580d4327`

GitHub Actions run:

`34096926472`

Job `verify-package`: SUCCESS for all steps, including source contract/syntax, TinyMCE AMD build, Moodle PHPUnit runtime suite, reproducible RC1 bundle, archive verification and artifact upload.

Verified artifact:

- Artifact ID: `10009035361`
- Artifact digest: `sha256:ac11e5f50fc974c8de84060ba2f17e03f6ab709e6a43a3567ddf59c0f22fd3d8`
- TGZ SHA256: `c3a31b526f8f127e71d0b27c802b9228dab5704eed7e8ebd6248b69cf4e0a23b`

Direct artifact inspection confirmed:

- `commands.min.js` depends on `./ui` and calls `_ui.open(editor)`.
- `modal.min.js` only exports the modal class.
- `ui.min.js` exports `open` and imports the modal class.
- `_modal.open` is absent from `commands.min.js`.

For fast-track deployment, the verified built AMD runtime files were published verbatim to immutable commit:

`823315c6abee280130a3fec3bd4c7cf3d173c4b0`

## 5. Exact hotfix file hashes

From the verified artifact:

- `version.php`: `456ee9dfc766f099d98daf962c8448d9706427793ecc3c2a815e930534b7ba67`
- `amd/src/commands.js`: `4649d48747c23ce8d81864858c1d1cef5f225c86efcd6d269a89a7dec0af6efe`
- `amd/src/modal.js`: `7a52690acacc3e2391d1e9e79061ad5c1d089f7760b0430b859a5684e0ebd3b7`
- `amd/src/ui.js`: `11c236f329281cf045327a5955c544fbdd3bcf13e04465f419da65304b9204b1`
- `amd/build/commands.min.js`: `61c7bd8fcc4395366f0af530b7c65b70b0b11c0d029f0945394db0226f3deb30`
- `amd/build/modal.min.js`: `df152a3ab03f07bf6c81e50f715a8c2664c68a7a50f4834678e888630185e3f1`
- `amd/build/ui.min.js`: `f3c4b71d05c3b7189ea4548f985c367415e4d928c53b1b0ce0973a9c973f7294`

## 6. Next actions

1. Apply the seven pinned hotfix files to Web01 and Web02 with SHA256 gates.
2. Run Moodle upgrade once on Web01 for Tiny plugin version `2026090702`.
3. Purge caches on both nodes and reload PHP-FPM.
4. Re-open the Page editor and click **Học liệu DIGIERA**.
5. Confirm Modal A opens and the seeded READY/SHARED PDF appears.
6. Continue insert → normal Save → PDF renderer → reopen/edit/save round-trip.
7. Repeat through load-balanced Web01/Web02 traffic.
8. Capture evidence and write a new versioned checkpoint before any merge to `main`.
