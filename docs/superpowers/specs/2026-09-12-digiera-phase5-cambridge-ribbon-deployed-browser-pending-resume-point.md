# DIGIERA Phase 5 — Cambridge Ribbon deployed / browser acceptance pending

Date: 2026-09-12
Status: DEPLOYED / FULL LOCAL GREEN PASS / TWO-NODE VERIFIED / BROWSER ACCEPTANCE PENDING

## Scope frozen in this checkpoint

This remains Phase 5. No Phase 6 is opened.

The approved CambridgePlus-derived Tiptap ribbon migration is implemented and deployed, including:

- Compact CambridgePlus-derived ribbon as the active editor shell.
- Tiptap 3 formatting/list/alignment/link/table commands.
- Native fontFamily, fontSize, textColor and highlight round-trip contract.
- Cambridge-style Picture Editor flow with crop, rotation, width, alignment, alt and caption metadata.
- Stable Native image `assetKey` persistence; runtime preview URLs are not canonical JSON.
- Teacher Worksheet ImageAdapter backed by Moodle File API.
- Student Attempt ImageAdapter backed by Moodle File API.
- Stable-key image replace semantics.
- Legacy Native image runtime defaults without destructive canonical rewrite on open.
- Existing Native validator/renderer remain authoritative.
- Existing autosave/manual save/publish/session/submission/grading workflows remain in place.
- No Moodle core or RemUI mutation.
- No DB schema/version migration for this migration.

## TDD and local verification evidence

Runbook pin:

`7bd6ee70948ee7a76536170209c496617020f452`

Development green artifact commit:

`0b8895ec4de4aaf27949d90b81523069591deea3`

Visual TDD evidence:

- `CAMBRIDGE_VISUAL_TDD_RED=PASS`
- Focused Cambridge gate: 13 files / 36 tests PASS.
- Full Vitest: 18 files / 60 tests PASS.
- Teacher ImageAdapter contract: PASS.
- Student ImageAdapter contract: PASS.
- `VALIDATOR_STANDALONE_PASS`.
- `RENDERER_STANDALONE_PASS`.
- `TIPTAP_CAMBRIDGE_UI_STATIC_CONTRACT=PASS`.
- Legacy route markers: PASS.
- Native XMLDB / upgrade / kind contracts: PASS.
- Native external API / detail / kind UI contracts: PASS.
- Worksheet Library V1 runtime contract: PASS.
- Native Preview UI contract: PASS.
- Native draft first-save / conflict / validation / render / asset URL resolution: PASS.
- Native session snapshot / attempt asset clone / save / submit asset resolution / immutable submit / grading immutability: PASS.
- `TCPDF_SMOKE=PASS`.
- Deterministic AMD build: PASS.
- PHP lint: PASS.
- package-lock sync: PASS.

## Immutable candidate / package evidence

Canonical product source commit deployed:

`a20a92a007f4e077081df197efed45e98776d2ad`

Package SHA256:

`c34b393bba5acf80dd607d8876ac3f6999c2a42d543c1352cfb681149cffde40`

Native AMD SHA256:

`f8c74f9ac29343bdb15c759ec25ed55e4def37d5345c62eee07e1e9b75b0f7ee`

DB upgrade required:

`NO`

## Production deployment evidence

Pre-mutation backups:

- Web01: `/root/digiera-p5-cambridge-pre-20260912-170433`
- Web02: `/root/digiera-p5-cambridge-pre-20260912-170433`

Final production tree hashes:

Web01:

- `local_digieranative`: `1094a9a9fe2b495a064ca60bd0129bfa8672dfc6bc8a2837cb9953e6b01256b4`
- `local_worksheetlibrary`: `8c74a415d365ee8703ae9745cbb4ab4066867efbf5a050a46e9ce90edf644a09`
- `mod_worksheetgrader`: `2ef15398ca041939271db5a31b593a9252d4fe0e8545ca53d77d94d13eecb58b`

Web02:

- `local_digieranative`: `1094a9a9fe2b495a064ca60bd0129bfa8672dfc6bc8a2837cb9953e6b01256b4`
- `local_worksheetlibrary`: `8c74a415d365ee8703ae9745cbb4ab4066867efbf5a050a46e9ce90edf644a09`
- `mod_worksheetgrader`: `2ef15398ca041939271db5a31b593a9252d4fe0e8545ca53d77d94d13eecb58b`

Deployment result:

- Web01 tree hashes: PASS.
- Web02 tree hashes: PASS.
- Two-node identity: PASS.
- Maintenance after deploy: `0`.
- Web01 cron after deploy: `active`.
- DB upgrade required: `NO`.
- `PHASE5_CAMBRIDGE_DEPLOY=PASS`.

## Current source state

Development branch `digiera/tiptap-v1-phase5-official-ui` green artifact before this checkpoint:

`0b8895ec4de4aaf27949d90b81523069591deea3`

Canonical product branch `digiera/preview-v01` deployed product commit:

`a20a92a007f4e077081df197efed45e98776d2ad`

The code is deployed to both production nodes. This checkpoint is **not yet Phase 5 final PASS** because production browser/runtime acceptance is still pending.

## Next actions — browser acceptance

Teacher worksheet editor:

1. Hard refresh and confirm Cambridge compact ribbon is visible and the previous custom toolbar is absent.
2. Verify selection-aware bold/italic/underline/strike, font family, font size, text color and highlight.
3. Verify paragraph/H1/H2/H3, bullet/ordered list, list indent, left/center/right/justify.
4. Verify safe link/unlink and table insert/add/delete row/column/delete table; no unsupported tableHeader node is introduced.
5. Verify Picture opens the Cambridge-style Picture Editor and inserts at the captured caret.
6. Verify crop, rotation, width/alignment, alt, caption and image replacement survive Save Now + refresh.
7. Verify PNG/JPEG/WebP under 5 MiB; SVG/unsupported/oversize must fail visibly.
8. Verify A4 orientation/margins persist after refresh; zoom is view-only.
9. Verify DIGIERA question, short answer, long answer, answer table and math perform visible actions and never silently fail.
10. Verify autosave and manual Save use the same canonical Native serializer.
11. Verify Preview semantics match editor semantics.
12. Verify direct PDF download reflects saved Native content/layout/images.
13. Publish successfully.

Student attempt:

14. Open the published worksheet in a session.
15. Verify editing and answer controls work.
16. Insert an image, edit metadata/crop, save, refresh and confirm it survives.
17. Submit and confirm post-submit editing is blocked.

Teacher review:

18. Open the submitted attempt and confirm structure/content/image/crop/layout resolve correctly.
19. Save grade + feedback and confirm frozen student submission is not rewritten.

Only after the production browser/runtime evidence above is captured may this final state be declared:

`PHASE5=PASS`
`ALL_5_PHASES=COMPLETED`
`PRODUCTION_WEB01_WEB02=VERIFIED`
`DB_UPGRADE_REQUIRED=NO`

After browser acceptance, create a new final resume checkpoint. Do not overwrite this file or earlier checkpoints.
