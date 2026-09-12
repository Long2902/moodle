# DIGIERA Phase 5 — Browser output hotfix deployed, retest pending

Date: 2026-09-12
Status: HOTFIX DEPLOYED / LOCAL GREEN PASS / BROWSER RETEST PENDING

## Browser defects fixed in this hotfix

- PDF download no longer navigates the Native editor page away; it saves first, fetches the PDF in-place, then downloads through a Blob URL.
- Print no longer calls `window.print()` on the Moodle detail page; it opens a dedicated Native print route/window.
- TCPDF font changed from unavailable `dejavusans` to bundled Unicode `freesans`.
- Cambridge ribbon contract enforces a single horizontal row with horizontal overflow instead of wrapping.
- WorksheetLibrary AMD `src` and deployed `build` copies are required to match.

## Verification evidence before deployment

- Browser-output RED evidence: PASS.
- `NATIVE_OUTPUT_BROWSER_CONTRACT=PASS`.
- `AMD_SRC_BUILD_PARITY=PASS`.
- `PDF_IN_PLACE_DOWNLOAD=PASS`.
- `PRINT_ISOLATED_WINDOW=PASS`.
- `TCPDF_UNICODE_FONT=PASS`.
- `CAMBRIDGE_SINGLE_ROW=PASS`.
- Focused Vitest: 12/12 PASS.
- Full Vitest: 60/60 PASS.
- Validator/renderer/static contracts: PASS.
- Native draft/service/session/attempt/grading standalone gates: PASS.
- TCPDF smoke: PASS.
- Deterministic AMD build: PASS.
- PHP lint: PASS.

## Deployment evidence

Development frozen source:

`8056c438949537fee59d9a001fd3e29e7ca7baa7`

Canonical production source:

`af15a1c0e676e5a59d66efe9b103732693d6d92e`

Package SHA256:

`49b24a4437618cc8ea17172560a728200732eeeaba6657db780264ee788e06e7`

Native AMD SHA256:

`f4d63440a933d1c61ad21f1e33ff6de65db84a83c18f30aa05ab21c7bd9a3472`

Two-node tree hashes are identical:

- local_digieranative: `bc7b64e7e1ad5c6fb3315330752021c6fd3f1b8fc629b05584f12ec6d23c0c8e`
- local_worksheetlibrary: `a25a92716bf979f5a5cd9180aaaac31fbad24a277fefffa3d43ba9df79667182`
- mod_worksheetgrader: `2ef15398ca041939271db5a31b593a9252d4fe0e8545ca53d77d94d13eecb58b`

Runtime/deploy state:

- Web01 tree hashes: PASS.
- Web02 tree hashes: PASS.
- Two-node identity: PASS.
- Maintenance after deploy: 0.
- Web01 cron after deploy: active.
- DB upgrade required: NO.
- Production backups created before mutation.

## Required browser retest before Phase 5 final PASS

After hard refresh on the teacher Native editor:

1. Ribbon remains a single horizontal row; overflow scroll is allowed, wrap is not.
2. Print opens the dedicated worksheet print view and prints Native content, not the Moodle detail page.
3. PDF downloads successfully without a TCPDF font error.
4. Returning/focusing the editor after Print/PDF leaves save status at `Đã lưu`; no revision conflict is triggered by output actions.
5. Continue remaining browser acceptance for formatting, image editor/save-refresh-preview, layout persistence, DIGIERA controls, student save/submit, and teacher immutable review/grade.

Do not mark Phase 5 PASS until browser/runtime retest evidence is available.
