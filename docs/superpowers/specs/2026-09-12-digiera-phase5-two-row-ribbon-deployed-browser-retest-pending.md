# DIGIERA Phase 5 — Two-row Cambridge Ribbon deployed / browser retest pending

Date: 2026-09-12
Status: DEPLOYED TO WEB01 + WEB02 / BROWSER RETEST PENDING / PHASE 5 NOT CLOSED

## Scope

UI-only follow-up requested by the user after the browser-output hotfix had already passed. The Cambridge ribbon was changed from one forced horizontal row to two explicit rows. Print, PDF download, autosave/revision lifecycle, Moodle File API asset handling, Native schema, and DB schema were intentionally unchanged.

## Evidence

- Red contract was observed first: the previous runtime still forced `flexWrap = 'nowrap'` on the whole ribbon.
- Two-row browser-output contract passed.
- Focused Cambridge tests passed 14/14.
- Full Vitest suite passed 60/60.
- Server validator/renderer, legacy contracts, Native draft/session/attempt/grading contracts and TCPDF smoke passed.
- Deterministic build and PHP lint passed.
- Two-node deploy passed and Web01/Web02 plugin tree hashes matched.
- Maintenance mode after deploy: 0.
- Cron after deploy: active.
- DB upgrade required: NO.

## Production identifiers

- Dev frozen source: `5afe622d09c8becde842a1f94a409b4cc941c749`
- Canonical production source: `ed32607768ab071f53c8383ae8872d785c1dcd56`
- Package SHA256: `43eeeef62e6919cd10ca27ea1cf56353987764b20940b004552d3ac12ef36675`
- Native AMD SHA256: `d1260e767153471df589ef5254ef48acd535b56f46b381efe739180d6f1b6431`

## Required browser retest

1. Ctrl+F5 on the teacher Native editor.
2. Confirm Cambridge ribbon visibly renders as exactly two rows.
3. Confirm status remains `Đã lưu`.
4. No need to repeat Print/PDF regression unless a problem is observed; those flows were preserved by the automated regression guard.

Do not mark Phase 5 complete until browser evidence confirms the requested two-row ribbon and the remaining final acceptance items are complete.
