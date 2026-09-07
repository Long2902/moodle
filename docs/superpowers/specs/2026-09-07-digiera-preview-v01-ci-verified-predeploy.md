# DIGIERA Preview v0.1 — CI-Verified Predeploy Checkpoint

Date: 2026-09-07

Status: **CODE_READY / WEB01_MILESTONE_VERIFIED / GITHUB_FAST_CI_VERIFIED / PACKAGE_READY / NOT DEPLOYED / NOT OPERATOR VERIFIED**

## 1. Source of truth

Repository: `Long2902/moodle`

Branch: `digiera/preview-v01`

Materialized vertical-slice commit:

```text
fd5485114996e9ac26edee6c587ef88529df7d89
feat(preview): materialize hybrid worksheet vertical slice
```

CI hardening commits:

```text
cf1318480b4565e3992bc069cbb54e5efa2126cd
ci(preview): harden hybrid fast gate

6d9df051919f53fde6c830f1269962300bebc34a
ci(preview): make source safety scan executable
```

## 2. Web01 milestone evidence

Web01 host: `vm-c47e0dd9`

Milestone result:

```text
HYBRID_MILESTONE=PASS
WEB01_HOST_GUARD=PASS
BUNDLE_MANIFEST=PASS
ISOLATED_STAGE=PASS
LOCAL_VERTICAL_SLICE_GATE=PASS
NPM_CI=PASS
NATIVE_VITEST=PASS
DETERMINISTIC_BUILD=PASS
AMD_SHA256=80257648be58c3d7b682233d92ae36fa47f10225117840cdf10fcceaebcf0619
AMD_BYTES=622202
PACKAGE_GATE=PASS
GITHUB_COMMIT=fd5485114996e9ac26edee6c587ef88529df7d89
PRODUCTION_MUTATION=NO
```

Fresh Native Vitest result on Web01:

```text
Test Files 9 passed (9)
Tests 34 passed (34)
```

Preview package checksums:

```text
96e3c31fbf32ac714532dd9ba8c4902a3cb6a6351d08877bb5851aeeb8c1be4b  local_digieranative_moodle51_preview-v0.1.zip
701e39f090a9c45d8bee33f72fd9e9e0078335ab906efe9f9ed9ab26b2789a63  local_digieraoffice_moodle51_preview-v0.1.zip
e817d73abf1965a2d6e7096e2c2978f91b0397b76d999c424fadc12fb360fb2d  local_worksheetlibrary_moodle51_preview-v0.1.zip
f2c1c8a71b773ef63fadebd2185ea2c7515a85abfa01ca100d17eafab9c8aaf4  mod_worksheetgrader_moodle51_preview-v0.1.zip
```

Packages remain on Web01 under `/root/digiera-preview-hybrid-milestone/packages`.

## 3. GitHub Fast CI evidence

Run 1 (`34077500097`) exercised all source/test/build lanes and failed only at CI harness `git diff --check HEAD~1 HEAD` because checkout depth was 1.

Run 2 (`34078255521`) passed after setting `fetch-depth: 2`, but log review found the shell credential-regex itself was malformed. Its `success` status is therefore not used as final safety evidence.

Run 3 (`34078923329`) is the authoritative Fast CI gate after replacing the fragile shell credential regex with an executable Python source scanner.

Run 3 final evidence:

```text
fast-gate=SUCCESS
PHP_LINT_FILES=107
XMLDB_PARSE=PASS
VALIDATOR_STANDALONE_PASS
RENDERER_STANDALONE_PASS
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
JS_SYNTAX_FILES=43
Test Files=9 passed
Tests=34 passed
DETERMINISTIC_BUILD=PASS
AMD_SHA256=80257648be58c3d7b682233d92ae36fa47f10225117840cdf10fcceaebcf0619
AMD_BYTES=622202
npm audit=0 vulnerabilities
SOURCE_CONTENT_SCAN=PASS
SOURCE_SAFETY_GUARDS=PASS
```

## 4. Deployment gate decision

All code/package/CI gates required before controlled Preview deployment are now satisfied.

The next operation is a **controlled two-node Preview deployment** with these safety rules:

```text
Web01/Web02 preflight before mutation
verify exact four package SHA256 values
snapshot existing four plugin trees on both nodes
maintenance mode
stop cron scheduling on both nodes
stage identical package source on both nodes
copy identical plugin trees to both nodes
run Moodle DB upgrade exactly once on Web01
verify Native schema fields
purge caches
verify Web01/Web02 plugin tree identity
restore cron state
leave maintenance mode
```

If failure occurs before DB upgrade starts, file trees may be restored from the predeploy snapshots. If failure occurs after DB upgrade starts, automatic schema rollback is prohibited; maintenance remains enabled for diagnosis.

## 5. Remaining acceptance gates

```text
PREVIEW_DEPLOYED=PENDING
TWO_NODE_TREE_IDENTITY=PENDING
DB_UPGRADE_ONCE=PENDING
NATIVE_LIBRARY_EDITOR_RUNTIME=PENDING
AUTOSAVE_RUNTIME=PENDING
MANUAL_SAVE_RUNTIME=PENDING
OPTIMISTIC_REVISION_RUNTIME=PENDING
SESSION_PICKER_RUNTIME=PENDING
TEAM_FLOW_RUNTIME=PENDING
ATTEMPT_SAVE_RUNTIME=PENDING
SUBMIT_FREEZE_RUNTIME=PENDING
GRADING_FLOW_RUNTIME=PENDING
LEGACY_HTML_OFFICE_PDF_RUNTIME=PENDING
OPERATOR_VERIFIED=PENDING
```

No production/runtime claim is made until deployment evidence and operator smoke evidence are captured.
