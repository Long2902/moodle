# DIGIERA Preview v0.1 — Hybrid Vertical Slice Code-Ready Checkpoint

Date: 2026-09-07

Status: **LOCAL CODE READY / WEB01 MILESTONE GATE PENDING / GITHUB MATERIALIZATION PENDING / NOT DEPLOYED / NOT OPERATOR VERIFIED**

## 1. Workflow decision

Operator approved switching from per-task CI blocking to **Hybrid Fastlane**:

```text
isolated workspace
→ Task 1 frozen baseline
→ Task 2 Native persistence/publish bridge
→ Task 3 session→attempt→submit→grade bridge
→ one Web01 milestone build/gate
→ one GitHub Fast CI gate
→ controlled Preview package/deployment
→ operator smoke test
```

GitHub source of truth remains `Long2902/moodle`, branch `digiera/preview-v01`. Production Moodle and its shared DB remain untouched during implementation/materialization.

## 2. Frozen source baseline

The isolated workspace was assembled from the approved frozen artifacts:

```text
DIGIERA_NATIVE_CORE_CHECKPOINT_M1C_2026-09-06.zip
local_digieraoffice_moodle51_1.0.0-rc1.zip
local_worksheetlibrary_moodle51_1.0.0-rc1.zip
mod_worksheetgrader_moodle51_12.0.0-rc1.zip
```

No source was copied from `/var/www/moodle/public`.

## 3. Task 2 local implementation

Local checkpoint commit:

```text
520e661e873ff79b99c4af4ed3d2003a333eafa9
feat(preview): persist Native worksheet drafts safely
```

Implemented:

```text
wslib_version.nativejson
wslib_version.schemaversion
wslib_version.revision
wslib_version.renderedhtml
Native draft optimistic revision lock
server-side validator + renderer
structured Native revision conflict
External API save_native_draft
Native Worksheet Library editor
shared autosave/manual-save lane
conflict blocks future autosave until reload/resolution
Native worksheet create/draft/publish lifecycle
legacy HTML/Office/PDF routes retained
```

Fresh local evidence recorded before the commit included:

```text
PHP_LINT_FILES=106
XMLDB_PARSE=PASS
NATIVE_DRAFT_FIRST_SAVE=PASS
NATIVE_DRAFT_CONFLICT=PASS
NATIVE_DRAFT_VALIDATION=PASS
NATIVE_DRAFT_RENDER=PASS
AUTOSAVE_DEBOUNCE=PASS
MANUAL_SAVE=PASS
AUTOSAVE_CONFLICT_BLOCK=PASS
NATIVE_EXTERNAL_API_CONTRACT=PASS
NATIVE_DETAIL_CONTRACT=PASS
NATIVE_KIND_UI=PASS
LEGACY_ROUTE_MARKERS=PASS
VALIDATOR_STANDALONE_PASS
RENDERER_STANDALONE_PASS
NODE_SYNTAX=PASS
SCOPED_CSS_SAFETY=PASS
GIT_DIFF_CHECK=PASS
```

## 4. Task 3 local implementation

Local checkpoint commit:

```text
8f68124
feat(preview): complete worksheet teaching vertical slice
```

Implemented with existing V12 schema; no new Task 3 DB tables/fields were required:

```text
published Native version
→ server-validated session snapshot
→ frozen nativejson + renderedhtml in session metadata/content
→ attempt initialized from frozen snapshot
→ optimistic mutable Native answersjson while inprogress
→ autosave/manual Save through mod_worksheetgrader_save_attempt
→ structured Native revision conflict
→ submit flushes pending save then freezes canonical JSON
→ immutable submissionhtml generated server-side
→ all post-submit answer mutation rejected
→ grading renders immutable submissionhtml
→ grade/feedback cannot rewrite frozen answer/submission
```

Legacy HTML, Office and DocSpace paths remain separate.

Fresh full local Task 3 gate:

```text
PHP_LINT_FILES=107
PHP_LINT=PASS
XMLDB_PARSE=PASS
NATIVE_DRAFT_FIRST_SAVE=PASS
NATIVE_DRAFT_CONFLICT=PASS
NATIVE_DRAFT_VALIDATION=PASS
NATIVE_DRAFT_RENDER=PASS
AUTOSAVE_DEBOUNCE=PASS
MANUAL_SAVE=PASS
AUTOSAVE_CONFLICT_BLOCK=PASS
NATIVE_EXTERNAL_API_CONTRACT=PASS
NATIVE_DETAIL_CONTRACT=PASS
NATIVE_KIND_UI=PASS
NATIVE_SESSION_SNAPSHOT=PASS
NATIVE_ATTEMPT_SAVE=PASS
NATIVE_SUBMIT_FREEZE=PASS
POST_SUBMIT_MUTATION_REJECTED=PASS
NATIVE_GRADING_IMMUTABILITY=PASS
NATIVE_PREVIEW_UI_CONTRACT=PASS
VALIDATOR_STANDALONE_PASS
RENDERER_STANDALONE_PASS
JS_SYNTAX_FILES=43
NODE_SYNTAX=PASS
LEGACY_ROUTE_MARKERS=PASS
SCOPED_CSS_SAFETY=PASS
LEGACY_AUTOSAVE_CONFLICT_BEHAVIOR=PASS
GIT_DIFF_CHECK=PASS
TASK3_HYBRID_LOCAL_GATE=PASS
```

## 5. Important qualification

The isolated sandbox cannot reinstall the Native npm dependency tree because its offline npm cache lacks `xmlchars-2.2.0.tgz`. Therefore the following are deliberately **not claimed yet** for the new Task 2–3 source:

```text
Web01 npm ci: pending
full Native Vitest after Task 2–3: pending
fresh deterministic Native AMD build after autosave export: pending
GitHub Fast CI: pending
GitHub direct source materialization: pending
Moodle disposable DB install/upgrade: pending
controlled Preview deployment: pending
operator end-to-end smoke: pending
```

The Web01 milestone runner is the next evidence gate. It must run outside `/var/www/moodle/public` and hard-stop if executed on Web02.

## 6. Next exact milestone

```text
Web01 hostname guard
→ artifact/source manifest verification
→ fresh PHP/XMLDB/standalone gate
→ npm ci + Vitest
→ deterministic AMD build twice
→ final static/safety gate
→ deterministic Preview packages
→ atomic Git Data materialization to digiera/preview-v01
→ GitHub Fast CI
```

Only after those gates pass should the controlled Preview deployment/smoke step begin.
