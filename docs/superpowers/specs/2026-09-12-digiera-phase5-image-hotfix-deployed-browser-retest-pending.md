# DIGIERA Phase 5 — Image hotfix deployed / browser retest pending

Date: 2026-09-12
Status: DEPLOYED TO WEB01 + WEB02 / FULL LOCAL GATE PASS / BROWSER IMAGE RETEST PENDING / PHASE 5 NOT CLOSED

## Scope

This checkpoint records the image-runtime and image-upload hotfix that followed browser acceptance findings in the teacher Native editor.

The hotfix addresses:

- Native managed images rendering too small even when `widthPercent = 100`.
- Teacher/student ImageAdapter upload bridges blindly parsing HTML as JSON and surfacing `Unexpected token '<'` instead of a useful server error.
- Teacher/student image endpoints returning JSON consistently for request/session/parameter failures by placing request validation inside the JSON exception boundary.

The previously accepted Print/PDF lifecycle fix and user-approved two-row Cambridge ribbon are preserved and guarded by regression contracts.

## Verification evidence before deployment

- Image hotfix preflight: PASS.
- `image-runtime-layout-regression.test.js`: PASS.
- `cambridge-image-flow.test.js`: PASS.
- `legacy-native-compat.test.js`: PASS.
- `NATIVE_IMAGE_UPLOAD_ERROR_CONTRACT=PASS`.
- Teacher ImageAdapter contract: PASS.
- Student attempt ImageAdapter contract: PASS.
- PHP lint for both image endpoints: PASS.
- Full Vitest suite: 19 files / 61 tests PASS.
- Server validator/renderer/static contracts: PASS.
- Native draft/session/attempt/submission/grading standalone gates: PASS.
- TCPDF smoke: PASS.
- Deterministic AMD build: PASS.
- Full 9/9 production fastlane: PASS.

## Production identifiers

- Hotfix input PIN: `1f31df6da49c3da27fba454bfd239895b327d41c`
- Frozen dev source after generated artifacts: `5c7af2cf9f9171d5704c1a1dfe74c67e46c5e6bc`
- Previous canonical production baseline: `ed32607768ab071f53c8383ae8872d785c1dcd56`
- New canonical production source: `50860efddc0036743ba228e4df759a4f245340e3`
- Package SHA256: `82c31507aa23b2ecd9d9851b9847149b0949c0546ec77a749cbe9e92746bb89a`
- Native AMD SHA256: `dbf554e2e2a94f9426d6d3d3853b7a9c81113e70e2c4f073ebe547196eb8cd18`

Two-node tree hashes are identical:

- `local_digieranative`: `441c80edca9a1f1fcb730a6ff8231a83e1b46bac4b4fff1989faeeb9fa641062`
- `local_worksheetlibrary`: `0e775b38ad7a1d45f07d2f4b68b49075c9700d225c19ab3fe7a785f77eeae1ce`
- `mod_worksheetgrader`: `a022bc4de85bb20a3676c4b22475e9b8da77e58b0ca969368df4900b4a5aeeb7`

Runtime state after deploy:

- Web01/Web02 identity: PASS.
- Maintenance after deploy: `0`.
- Web01 cron after deploy: `active`.
- DB upgrade required: NO.
- Production backups created before mutation.

## Post-build regression guard

- `NATIVE_OUTPUT_BROWSER_CONTRACT=PASS`.
- `PDF_IN_PLACE_DOWNLOAD=PASS`.
- `PRINT_ISOLATED_WINDOW=PASS`.
- `TCPDF_UNICODE_FONT=PASS`.
- `CAMBRIDGE_TWO_ROW=PASS`.
- Teacher/student AMD src/build parity: PASS.

## Required browser retest

Before closing Phase 5:

1. Hard refresh the teacher Native editor.
2. Insert the QR/small image used during prior testing with size set to 100%; verify it renders at the expected full content width.
3. Insert the larger scanned image that previously produced `Unexpected token '<'`; verify it now uploads or, if rejected, presents a readable HTTP/server error instead of a JSON parse exception.
4. Save and hard-refresh; verify the managed image, width/crop/alignment/caption survive reopen.
5. Continue final student E2E: attempt save -> submit -> immutable teacher review -> grade/feedback.
6. Investigate/clear the previously observed `active database transaction detected during request shutdown` warning before final Phase 5 closure if it can still be reproduced.

Do not mark Phase 5 PASS until browser/runtime evidence completes the remaining acceptance items.
