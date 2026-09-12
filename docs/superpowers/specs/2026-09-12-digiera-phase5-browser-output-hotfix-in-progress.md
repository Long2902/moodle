# DIGIERA Phase 5 — Browser output hotfix in progress

Date: 2026-09-12
Status: BROWSER ACCEPTANCE FAILED / HOTFIX IMPLEMENTED ON DEV / NOT YET REDEPLOYED

## Production browser evidence

After the Cambridge ribbon deployment, production browser acceptance confirmed:

- Cambridge compact ribbon is active; retired toolbar is gone.
- Hard refresh restores teacher draft status to `Đã lưu`.
- Printing the editor page produces an invalid/blocked print document instead of the Native worksheet.
- PDF output fails with `TCPDF ERROR: Could not include font definition file: dejavusans`.
- Print/PDF navigation can leave the returning editor page with stale optimistic revision state and show `Xung đột phiên bản`.
- Cambridge ribbon still visually wraps into two rows instead of remaining one horizontal row.

Phase 5 MUST NOT be marked PASS until these browser defects are fixed and re-verified.

## Root cause

1. Teacher print callback directly called `window.print()` on the full Moodle detail page.
2. Teacher PDF callback used `window.location.assign()` after `saveNow()`, causing the editor document lifecycle/history to leave and return around an optimistic-revision save.
3. `pdf.php` selected TCPDF font `dejavusans`, but the deployed Moodle TCPDF package does not include that font definition. Moodle does include the Unicode FreeSans family.
4. Toolbar CSS had horizontal overflow, but `.dgn-cambridge-toolbar` itself was not a flex/no-wrap container; the unused `.dgn-cambridge-bar` carried those properties instead.

## Hotfix design

- PDF: save current Native draft, fetch PDF in-place, verify `application/pdf`, download via Blob/object URL. Do not navigate the editor page.
- Print: synchronously open a separate popup, save the draft, then navigate that popup to a dedicated server-rendered `print.php` route. The editor page never enters print/navigation lifecycle.
- Print route: authoritative PHP Native validator/renderer + Moodle File API asset URLs + A4/orientation/margin-aware `@page` CSS.
- PDF: use bundled `freesans` TCPDF font.
- Ribbon: enforce `display:flex; flex-wrap:nowrap` at generic Native editor runtime while retaining `overflow-x:auto` from scoped CSS.
- No DB schema/version change.

## TDD commits

RED contract:

`2e3605ce73a5d3b70a0258a0ee62d751d074d0a5`

GREEN implementation head before this checkpoint:

`0f6b38ca2e158c2b5b8998d825bec565e385b4fb`

New contract:

`public/local/worksheetlibrary/tests/native_output_browser_contract.py`

Expected GREEN markers:

- `NATIVE_OUTPUT_BROWSER_CONTRACT=PASS`
- `AMD_SRC_BUILD_PARITY=PASS`
- `PDF_IN_PLACE_DOWNLOAD=PASS`
- `PRINT_ISOLATED_WINDOW=PASS`
- `TCPDF_UNICODE_FONT=PASS`
- `CAMBRIDGE_SINGLE_ROW=PASS`

## Deployment status

Current production remains the previously deployed Cambridge candidate until this hotfix passes local gates and is redeployed to Web01/Web02.

`DB_UPGRADE_REQUIRED=NO`

## Next actions

1. Run RED commit contract and confirm expected failure.
2. Run GREEN head contract + focused visual tests + PHP lint.
3. Build deterministic `local_digieranative` AMD artifact.
4. Run full regression and TCPDF/font smoke.
5. Package from current production canonical baseline, backup both nodes, deploy identical trees, purge caches, verify cron/maintenance.
6. Browser acceptance: print, PDF, return-to-editor status, ribbon one-row.
7. Continue image/format/layout/student E2E acceptance.
8. Only after all browser/runtime checks PASS may Phase 5 and all five phases be closed.
