# DIGIERA Hybrid Tiptap + UI V1 — Fast Production Resume Point

Date: 2026-09-08
Branch: `digiera/preview-v01`
Status: **TIPTAP/UI V1 PRODUCT TESTS PASS / PRODUCT SOURCE NOT YET MATERIALIZED TO PREVIEW / NOT YET DEPLOYED / FAST PRODUCTION TRACK ACTIVE**

This checkpoint extends, and does not replace, all prior DIGIERA Preview/Native/UI/UX resume points.

## 1. Operator context and correction

The operator has already completed the earlier Native functional smoke for `SMOKE RC1 - Native 01`.

Previously confirmed runtime facts:

- Preview v0.1 is deployed on Web01/Web02.
- Worksheet Library is reachable.
- Native editor mounts and accepts content.
- Save baseline is functional.
- Native publish v1 was operator-confirmed.
- Published v1 state and `Tạo draft phiên bản mới` were observed.

Therefore the old smoke sequence `edit -> reload -> publish v1` MUST NOT be requested again as the next action.

The active workstream is now the new Hybrid Tiptap + UI Visual V1 implementation.

## 2. Approved Hybrid Tiptap architecture

The operator approved:

- Tiptap 3.x as the Native client editing engine on top of ProseMirror;
- DIGIERA-owned Word-like Ribbon and worksheet-specific node behavior;
- canonical Native root remains `{"type":"worksheet","version":1,"content":[]}`;
- existing PHP validator/renderer remains server-authoritative;
- autosave/manual Save optimistic revision lane remains authoritative;
- publish/version immutability remains authoritative;
- session snapshot, attempt save, submit freeze and grading contracts remain unchanged;
- HTML/PDF/Office compatibility remains reachable;
- no Moodle core mutation;
- no Edwiser RemUI core/theme/navigation mutation;
- no DB schema change for this UI/editor migration.

Approved Ribbon tabs:

```text
File | Home | Insert | Layout | Review | Math | View
```

## 3. UI Visual V1 scope already implemented in isolated workspace

Reference master:

- approved PNG mockups;
- operator-supplied Gemini HTML references;
- `5.html` is the primary reference for Worksheet Library + Native Editor + create/publish/success UI.

Implemented in isolated workspace:

- Library three-panel browser;
- action toolbar, search, folder tree, worksheet list/table and inspector;
- create worksheet modal;
- create folder modal;
- Native detail/editor workspace;
- seven-tab DIGIERA Ribbon visual contract;
- paper-style Native editing canvas;
- version/history/status inspector;
- preview/publish modal;
- publish-success state;
- copy/move/binding presentation using existing services;
- Library Native autosave bridge migrated to Native `onUpdate` callback;
- student Native attempt bridge migrated from legacy `setProps` coupling to `onUpdate` callback.

No fake Moodle/RemUI sidebar/header from reference HTML is included in production markup.

## 4. Product verification evidence already obtained

Passing Tiptap network CI evidence from isolated branch `digiera/tiptap-v1-ci`:

```text
npm package-lock regeneration=PASS
npm ci=PASS
Test Files 9 passed (9)
Tests 35 passed (35)
TIPTAP_ADAPTER_STANDALONE=PASS
TIPTAP_HYBRID_STATIC_CONTRACT=PASS
JS syntax=PASS
DETERMINISTIC_TIPTAP_BUILD=PASS
```

Passing run lineage includes successful network run:

```text
workflow: DIGIERA Tiptap V1 CI
run id: 34174884827
conclusion: success
```

Deterministic outputs:

```text
package-lock.json SHA256:
bd28c54a123ae1da0895c60dcc34db8710732e00cb8dffc9c6280810aab7b473

native_editor.min.js SHA256:
448f5386ae4213ecf0695c6a7591a3578a90b1c05db748b89e507fff9c7528f7
```

Final isolated local gate:

```text
PHP_LINT_FILES=97
PHP_LINT=PASS
AUTOSAVE_DEBOUNCE=PASS
MANUAL_SAVE=PASS
AUTOSAVE_CONFLICT_BLOCK=PASS
TIPTAP_ADAPTER_STANDALONE=PASS
TIPTAP_HYBRID_STATIC_CONTRACT=PASS
WSLIB_UI_V1_VISUAL_CONTRACT=PASS
NATIVE_DRAFT_FIRST_SAVE=PASS
NATIVE_DRAFT_CONFLICT=PASS
NATIVE_DRAFT_VALIDATION=PASS
NATIVE_DRAFT_RENDER=PASS
NATIVE_SESSION_SNAPSHOT=PASS
NATIVE_ATTEMPT_SAVE=PASS
NATIVE_SUBMIT_FREEZE=PASS
POST_SUBMIT_MUTATION_REJECTED=PASS
NATIVE_GRADING_IMMUTABILITY=PASS
NATIVE_PREVIEW_UI_CONTRACT=PASS
JS_SYNTAX_FILES=37
JS_SYNTAX=PASS
TIPTAP_NETWORK_CI_ARTIFACT_HASH=PASS
REMUI_CORE_NAV_SAFETY=PASS
SOURCE_DIFF_CHECK=PASS
PRODUCT_PATH_SCOPE=PASS
PRODUCT_CHANGED_FILES=29
V1_TIPTAP_UI_CODE_READY_LOCAL=PASS
```

## 5. Current blocker is NOT product logic

The product source is still not on `digiera/preview-v01` because the temporary CI workflow attempted to self-commit verified source after tests.

Runs 8–11 repeatedly showed:

- patch apply PASS;
- dependency install PASS;
- Native tests PASS;
- static contracts PASS;
- JS syntax PASS;
- deterministic Rollup PASS;
- failure only at the final `Commit verified product source and generated outputs` step.

Run 11:

```text
run id: 34210778152
product tests/build through step 11: PASS
step 12 self-commit/persist source: FAIL
```

The deterministic generated AMD bundle contains third-party whitespace that may fail Git whitespace checking. This generated file is already protected by deterministic build-twice comparison and exact SHA verification.

Therefore CI self-commit is no longer a release blocker.

## 6. Current branch state

Source of truth repository:

```text
Long2902/moodle
```

Preview branch:

```text
digiera/preview-v01
```

Verified Preview HEAD before this resume commit:

```text
c3d8ba24e1d5438c64e3f8f8e8ddae9a8631c77f
docs(spec): checkpoint Tiptap UI V1 code ready
```

At that HEAD:

```text
29 PRODUCT FILES MATERIALIZED TO PREVIEW = NO
TIPTAP/UI V1 DEPLOYED                   = NO
PRODUCTION DB MUTATION FOR UI V1        = NO
WEB01/WEB02 UI V1 FILE DEPLOY           = NO
```

Existing previously deployed Preview remains active and unchanged.

## 7. Fast Production Track decision

The operator explicitly requested the fastest practical path to a deployable Production Candidate.

Locked execution strategy from this checkpoint:

```text
DO NOT keep fixing CI self-commit as a blocker.
DO NOT rerun the old Native publish smoke.
DO NOT run a DB upgrade for this UI/Tiptap visual migration.

NEXT:
1. Materialize the already-verified 29 product files directly onto `digiera/preview-v01`.
2. Exclude only deterministic generated `public/local/digieranative/amd/build/native_editor.min.js` from whitespace-only diff checking.
3. Keep all handwritten/source files under normal diff/static safety checks.
4. Run one final source/package gate.
5. Build the UI-only Production Candidate package.
6. Produce one controlled Web01 deployment command block.
7. Deploy identical code to Web01/Web02.
8. Purge Moodle caches and reload PHP runtime as required.
9. Do NOT run `admin/cli/upgrade.php` unless an independently evidenced version/schema change appears; current approved design expects none.
10. Operator tests the NEW UI/Tiptap browser implementation against the approved mockup/HTML reference.
```

## 8. Deployment safety boundary

Permanent rules remain:

```text
- Never develop directly in /var/www/moodle/public.
- Preserve existing production Preview until deployment package is ready.
- Backup target plugin directories before replacement.
- Web01/Web02 plugin trees must be identical after deploy.
- Web01 remains the only cron node.
- Moodle core stays untouched.
- Edwiser RemUI core/theme/navigation stays untouched.
- All DIGIERA CSS stays plugin-scoped.
- No secrets in source/logs/Spec Kit.
- DEPLOYED and VERIFIED remain separate claims.
```

UI-only target plugin paths:

```text
public/local/digieranative
public/local/worksheetlibrary
public/mod/worksheetgrader
```

`local_digieraoffice` is not part of the Native UI V1 product change unless final materialized diff proves otherwise.

## 9. Exact operator action boundary

At this resume point the operator should NOT yet run production commands.

The next operator interaction must happen only after:

```text
PREVIEW_PRODUCT_MATERIALIZED=PASS
FINAL_SOURCE_GATE=PASS
PRODUCTION_CANDIDATE_PACKAGE=PASS
DEPLOY_RUNNER_READY=PASS
```

Then provide one copy/paste command block for Web01 which:

- preflights Web01 -> Web02 SSH;
- verifies Moodle root/runtime state;
- creates plugin backups;
- stages identical package on both nodes;
- enables maintenance only during the controlled replacement window;
- stops/restores Web01 cron safely;
- deploys identical code to both nodes;
- verifies PHP syntax/tree identity;
- skips DB upgrade for this UI-only migration;
- purges caches;
- restores maintenance/cron state;
- prints a final PASS/FAIL summary.

## 10. New browser acceptance after deployment

Do NOT repeat the old published-v1 smoke as the main test.

New acceptance is:

```text
Worksheet Library new UI
-> compare Library geometry/hierarchy to approved `5.html`/PNG
-> open existing Native worksheet or create a disposable draft
-> confirm Tiptap-based Native editor mounts
-> confirm seven-tab Ribbon appears
-> perform one small edit
-> confirm autosave/manual Save regression still works
-> refresh once to prove persistence after the NEW deployment
-> inspect preview/publish UI presentation
-> capture screenshots for visual diff
-> confirm RemUI global navigation/header unchanged
```

Publish itself needs to be retested only if the new deployment shows evidence of a publish regression; the earlier publish baseline is already operator-confirmed.

## 11. Exact continuation instruction for the next session/turn

Continue from this checkpoint without redesigning or repeating old smoke work:

```text
Materialize the 29 already-verified Hybrid Tiptap + UI V1 product files directly to `digiera/preview-v01`.
Treat temporary CI self-commit failure as non-blocking because product tests/build already passed.
Run one final source/package gate, build the UI-only Production Candidate and produce the exact controlled Web01 deploy block.
Do not run DB upgrade for this UI-only migration unless a real schema/version delta is detected.
Do not ask the operator to repeat the old Native publish smoke before the new UI is deployed.
```

## 12. Snapshot summary

```text
PREVIOUS PREVIEW DEPLOYED                 = YES
OLD NATIVE SAVE/PUBLISH BASELINE          = OPERATOR CONFIRMED
UI/UX SECTIONS 1-3                        = APPROVED
HYBRID TIPTAP DESIGN                      = APPROVED
TIPTAP NETWORK TESTS                      = PASS (35/35)
DETERMINISTIC TIPTAP AMD BUILD            = PASS
UI V1 ISOLATED LOCAL GATE                 = PASS
VERIFIED PRODUCT FILE COUNT               = 29
PRODUCT FILES ON PREVIEW BRANCH           = NO
UI V1 PRODUCTION CANDIDATE PACKAGE         = NOT YET BUILT
UI V1 WEB01/WEB02 DEPLOY                  = NOT YET DONE
UI V1 BROWSER VISUAL ACCEPTANCE           = NOT YET DONE
DB UPGRADE REQUIRED BY APPROVED UI V1     = NO
CURRENT BLOCKER CLASS                     = MATERIALIZATION / RELEASE PACKAGING
NEXT ACTION                               = DIRECT MATERIALIZE -> FINAL GATE -> PACKAGE -> DEPLOY
```
