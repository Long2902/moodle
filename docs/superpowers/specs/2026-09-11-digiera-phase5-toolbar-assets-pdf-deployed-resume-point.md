# DIGIERA Phase 5 — Toolbar / Assets / PDF deployed resume point

Date: 2026-09-11
Status: DEPLOYED / LOCAL GREEN PASS / BROWSER ACCEPTANCE PENDING

## Scope completed in this checkpoint

- Official-Tiptap-based editor toolbar expanded to tabs: Tệp, Home, Chèn, Bố cục, DIGIERA.
- File actions: manual save, print, direct PDF download.
- Insert actions: managed image, horizontal rule, page break, link.
- Layout actions: A4, portrait/landscape, normal/narrow/wide margins, view-only zoom.
- DIGIERA actions: question, smart short answer, smart long answer, answer table, managed image, math.
- Smart answer controls no longer fail silently when cursor is outside a question.
- Managed image flow added for worksheet editor and student attempt bridge.
- Link mark is part of the authoritative Native V1 schema and validated on both client/server, with unsafe schemes rejected.
- No Moodle core or RemUI mutation.
- No DB schema/version migration.

## TDD / local verification evidence

Previous RED evidence was observed for the approved toolbar/assets/PDF contracts.

Final local gate on Web01:

- Focused Phase 5 tests: PASS.
- Full Vitest: 13 files, 49 tests, PASS.
- `PHASE5_ASSETS_PDF_CONTRACT=PASS`.
- `TIPTAP_LINK_ROUNDTRIP_CONTRACT=PASS`.
- `VALIDATOR_STANDALONE_PASS`.
- `RENDERER_STANDALONE_PASS`.
- `TIPTAP_OFFICIAL_UI_STATIC_CONTRACT=PASS`.
- `TCPDF_SMOKE=PASS`.
- Deterministic AMD build: PASS.
- PHP lint: PASS.

## Immutable candidate / deployment evidence

Development artifact commit:

`f3d02564ebb106e08789495507da09758366811d`

Canonical product-only source commit:

`1b698d6a7b45a7200661df1305761910066fabcf`

Package SHA256:

`c22517673b709dd82cf5a3713e72162a784874054e0908b4c6673698b8b75adc`

Native AMD SHA256:

`b6197e4f3d0527f42f8f2966df5a32a530b976130c0a2c90874fcb112ea36cd6`

Final tree hashes, identical on Web01 and Web02:

- `local_digieranative`: `f1c5c8b4759fb59839b67fc746be5597f58151fa7c0a7bded7831cfdf452bb25`
- `local_worksheetlibrary`: `9f95c6f27e8d59cd57dcf5c6ea19c1bf70992c9019b46e1078ea692a79c19e6e`
- `mod_worksheetgrader`: `10dab2a05b252d3cd293496493518db64be70ecc936ee403778335670e234be3`

Deployment result:

- Web01 tree hashes: PASS.
- Web02 tree hashes: PASS.
- Two-node identity: PASS.
- Maintenance after deploy: 0.
- Web01 cron after deploy: active.
- DB upgrade required: NO.
- Production backups were created on both nodes before mutation.

## Current production state

Canonical branch `digiera/preview-v01` points to:

`1b698d6a7b45a7200661df1305761910066fabcf`

The code is deployed to both production nodes. This checkpoint is **not yet Phase 5 final PASS** because browser/runtime acceptance is still pending.

## Next actions — browser acceptance

Verify in production browser, after hard refresh:

1. Tệp: Lưu, In, Tải PDF.
2. Home: selection-aware formatting and link actions.
3. Chèn: image upload, horizontal rule, page break, link.
4. Bố cục: orientation and margins persist after refresh; zoom remains view-only.
5. DIGIERA: question, smart short/long answer, answer table, image, math all perform visible actions.
6. Teacher image survives autosave/manual save, refresh, preview and publish.
7. Student attempt image survives save/refresh/submit and is visible on teacher review.
8. PDF downloads directly and reflects Native content/layout.
9. Final functional E2E: publish -> session -> student save/submit -> teacher grade/feedback.

Only after the browser/runtime acceptance above is evidenced may Phase 5 be marked PASS and all five requested phases be declared complete.
