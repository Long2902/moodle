# DIGIERA Hybrid Tiptap + UI Visual V1 — Code Ready Checkpoint

Date: 2026-09-08
Branch: `digiera/preview-v01`
Status: **HYBRID TIPTAP NETWORK CI PASS / UI V1 LOCAL CODE READY / PRODUCT SOURCE MATERIALIZATION PENDING / NOT DEPLOYED / BROWSER VISUAL ACCEPTANCE PENDING**

This checkpoint extends without replacing all earlier DIGIERA Preview/UI/Native checkpoints.

## 1. Approved architecture retained

- Tiptap 3.x is the Native client editing engine layered on ProseMirror.
- DIGIERA owns the Word-like Ribbon and worksheet-specific node/answer behavior.
- Canonical Native root remains `{"type":"worksheet","version":1,"content":[]}`.
- Existing DIGIERA PHP validator/renderer remains server-authoritative.
- Existing autosave/manual Save optimistic revision lane remains authoritative.
- Publish/session snapshot/attempt/submit freeze/grading contracts remain authoritative.
- HTML/PDF/Office compatibility routes remain reachable.
- No Moodle core mutation.
- No Edwiser RemUI core/theme/navigation mutation.
- No DB schema change is required for this visual/editor migration.

## 2. Tiptap network CI evidence

Temporary isolated branch: `digiera/tiptap-v1-ci`.

Passing run:

- workflow: `DIGIERA Tiptap V1 CI`
- run id: `34174884827`
- head SHA: `bba0b55cb197f12e7770f7534fc51cf27a57c5c4`
- conclusion: `success`

Fresh network gate evidence:

```text
TIPTAP_V1_PATCH_APPLY=PASS
npm package-lock regeneration=PASS
npm ci=PASS
Test Files 9 passed (9)
Tests 35 passed (35)
TIPTAP_ADAPTER_STANDALONE=PASS
TIPTAP_HYBRID_STATIC_CONTRACT=PASS
JS syntax=PASS
DETERMINISTIC_TIPTAP_BUILD=PASS
```

Deterministic CI outputs:

```text
package-lock.json SHA256 = bd28c54a123ae1da0895c60dcc34db8710732e00cb8dffc9c6280810aab7b473
native_editor.min.js SHA256 = 448f5386ae4213ecf0695c6a7591a3578a90b1c05db748b89e507fff9c7528f7
```

Artifact:

- name: `digiera-tiptap-v1-ci-output`
- artifact id: `10036907447`
- artifact ZIP SHA256: `a9eace7c8a75c89a05f2b106ae6bb6acd48808ff46569ab98a6c70e9906b07de`

The artifact was ingested into the isolated V1 workspace and its two file hashes were reverified exactly.

## 3. Paste compatibility regression fixed

The first real Tiptap network run reached Vitest and reported one failure in paste sanitization. Root cause was not the sanitizer: sanitized HTML was parsed with the frozen legacy ProseMirror schema, then inserted into the Tiptap view whose schema uses different node type instances.

Fix: `parsePastedHtml()` accepts a target schema and the paste handler passes `view.state.schema`.

After the fix, all 35 Vitest tests passed, including paste sanitization.

## 4. UI Visual V1 local implementation

Reference master: approved mockups + operator-supplied Gemini `5.html` for Library/Native Editor visual geometry.

Implemented in isolated workspace:

- Library three-panel browser;
- action toolbar/search/folder tree/table/inspector;
- create worksheet modal and create folder modal;
- Native detail/editor workspace;
- seven-tab DIGIERA Ribbon visual contract: `File | Home | Insert | Layout | Review | Math | View`;
- paper-style Native editing canvas;
- version/history/status inspector;
- preview/publish modal;
- publish-success state;
- copy/move/binding presentation using real existing services;
- student Native attempt bridge migrated from legacy `setProps` coupling to Native `onUpdate` callback;
- Library Native autosave bridge migrated to the same `onUpdate` contract.

No fake Moodle/RemUI sidebar/header from the HTML reference is included in production markup.

## 5. Final isolated local code-ready gate

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

## 6. Generated AMD diff-check rule

The deterministic Tiptap Rollup AMD bundle contains whitespace from bundled third-party source/comments that triggers Git's whitespace checker. This is isolated to the generated bundle.

Evidence:

```text
AMD_DIFFCHECK_RC=2
SOURCE_DIFFCHECK_EXCLUDING_GENERATED_RC=0
GENERATED_BUNDLE_WHITESPACE_ISOLATED=PASS
```

Policy for this build artifact:

- all handwritten/source files remain under `git diff --check`;
- generated `public/local/digieranative/amd/build/native_editor.min.js` is excluded from whitespace-only diff checking;
- that generated file is instead protected by full test suite + deterministic build-twice `cmp` + exact SHA256 verification.

## 7. Next actions

1. Materialize the 29 product files atomically onto `digiera/preview-v01`.
2. Harden Preview Fast CI diff-check to exclude only the deterministic generated Native AMD file while preserving all source safety checks.
3. Run Preview Fast CI on the real product branch.
4. If green, build an UI-only two-node deployment package/runner with no DB upgrade.
5. Deploy Web01/Web02 identically, purge cache/reload PHP-FPM, restore runtime state.
6. Operator compares real browser screens against approved mockups/HTML reference; iterate visual overlay/diff until accepted.

Do not mark Visual V1 deployed or visually accepted until the corresponding runtime/browser evidence exists.
