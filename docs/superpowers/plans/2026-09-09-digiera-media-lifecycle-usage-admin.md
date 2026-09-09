# DIGIERA Media Lifecycle, Usage & Admin/KTV Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver RC1 usage locations, soft trash/restore, safe permanent Cloudflare R2 purge, force purge with live-reference protection, and complete the existing TinyMCE Admin/KTV lifecycle panel.

**Architecture:** Reuse the existing Media → immutable Version → Reference model and existing trash/status tables. Keep external AJAX classes thin; put lifecycle rules in `lifecycle_service`, usage resolution in `usage_service`, and physical delete in server-only `sigv4_client::delete_object()`. Trash is non-destructive and existing references keep rendering; permanent purge is server-side, locked, idempotent and reference-aware.

**Tech Stack:** Moodle 5.1 PHP 8.3, Moodle External API/AJAX, Moodle lock API, Moodle DB delegated transactions, TinyMCE AMD/RequireJS, Cloudflare R2 S3-compatible SigV4, PHPUnit/runtime source-contract tests, two-node Web01/Web02 deployment helper.

**Spec:** `docs/superpowers/specs/2026-09-09-digiera-media-lifecycle-usage-admin-design.md`

## Global Constraints

- Preserve marker contract `[[digiera-ref:UUID]]`; never rewrite markers to raw R2/CDN URLs.
- Preserve verified `FOLLOW_CURRENT` and `PINNED_VERSION` behavior.
- Trash must not physically delete R2 objects and must not break existing course content.
- Existing ACTIVE/DRAFT references to TRASHED Media continue rendering.
- New references, Replace and reference-version mutation require Media `ACTIVE`.
- `ACTIVE` + `DRAFT` references are live for lifecycle safety; `STALE` + `UNRESOLVED` are not.
- Normal purge is blocked while live references exist.
- Force purge requires explicit `local/digieramedia:purge`, re-counts server-side, marks live references `UNRESOLVED`, and keeps markers in Moodle content.
- Physical purge is server-side SigV4 DELETE only; no presigned browser DELETE and no secrets in AJAX responses/logs.
- R2 2xx and 404 are idempotent delete success; other failures keep Media `PURGING` and must be retryable.
- Target versions: `local_digieramedia = 2026090902`, `tiny_digieramedia = 2026090902`, release `1.0.0-rc1-fasttrack-lifecycle`.
- Keep `xmldb_local_digieramedia_upgrade()` in the global namespace.
- Never merge or deploy to `main`; implementation branch is `feature/digiera-media-v1-rc1-lifecycle`.
- Deployment helper must not execute any real R2 DELETE during preflight.

---

### Task 1: Lifecycle domain service and R2 DELETE

**Files:**
- Create: `public/local/digieramedia/classes/service/lifecycle_service.php`
- Modify: `public/local/digieramedia/classes/r2/sigv4_client.php`
- Modify: `public/local/digieramedia/classes/repository/reference_repository.php`
- Test: `public/local/digieramedia/tests/lifecycle_service_test.php`
- Test: `public/local/digieramedia/tests/r2_sigv4_delete_test.php`

**Interfaces:**
- Consumes: `client_interface::delete_object(string $bucket, string $key): void`, Media/Version/Reference/Trash tables, Moodle lock API.
- Produces: `lifecycle_service::trash(int $userid, context $context, string $mediauuid, string $reason=''): array`, `restore(...)`, `purge(..., bool $force=false): array`, `reference_repository::count_live(int $mediaid): int`, `mark_live_unresolved(int $mediaid, int $now): int`.

- [ ] **Step 1: Write failing lifecycle tests** covering ACTIVE→TRASHED without R2 delete, same UUID/current version, visibility→PRIVATE with `previousvisibility` saved, restore restoring visibility, normal purge blocked by live refs, zero-ref purge deleting all non-PURGED versions, partial-delete failure leaving `PURGING`, retry skipping already PURGED versions, and force purge marking live refs `UNRESOLVED`.
- [ ] **Step 2: Run lifecycle test and verify RED** with missing `lifecycle_service` / missing repository methods.
- [ ] **Step 3: Write failing SigV4 DELETE contract test** asserting DELETE canonical method, configured bucket validation, 2xx/404 success contract, and no browser-facing secret/presigned-delete path.
- [ ] **Step 4: Run SigV4 test and verify RED** because `delete_object()` currently throws `coding_exception`.
- [ ] **Step 5: Implement `reference_repository::count_live()` and `mark_live_unresolved()`** using status set `['ACTIVE','DRAFT']`.
- [ ] **Step 6: Implement `sigv4_client::delete_object()`** with the same `host;x-amz-content-sha256;x-amz-date` signed headers as HEAD, canonical method `DELETE`, empty payload SHA256, configured bucket/key validation, cURL timeouts, 2xx success, 404 success, other status/transport exception.
- [ ] **Step 7: Implement `lifecycle_service` minimal state machine** with one lifecycle lock per media, capability/ownership checks, soft trash transaction, restore transaction, purge state transition, per-version physical deletion + version tombstone updates, retry semantics, force-unresolve semantics, and redacted audit writes.
- [ ] **Step 8: Run targeted tests and verify GREEN.**
- [ ] **Step 9: Commit** `feat: add safe media lifecycle and R2 purge`.

### Task 2: Usage-location service

**Files:**
- Create: `public/local/digieramedia/classes/service/usage_service.php`
- Create: `public/local/digieramedia/classes/external/get_media_usage.php`
- Test: `public/local/digieramedia/tests/usage_service_test.php`

**Interfaces:**
- Consumes: Media UUID, reference registry, Moodle course/context/course-module APIs.
- Produces: `usage_service::get(int $userid, context $context, string $mediauuid): array` returning `mediauuid`, `mediastatus`, `livecount`, lifecycle capability booleans, trash metadata, and `usages[]` with reference UUID/status/course/activity/version mode/safe URL/fallback metadata.

- [ ] **Step 1: Write failing usage tests** for correct ACTIVE+DRAFT live count, PIN/FOLLOW metadata, course/activity labels when resolvable, inaccessible-context filtering, and system-wide view override.
- [ ] **Step 2: Run tests and verify RED** because service/endpoint do not exist.
- [ ] **Step 3: Implement `usage_service`** with per-reference visibility checks, safe `view.php?id=<cmid>` URLs where resolvable, course URL fallback, and no inaccessible labels/URLs.
- [ ] **Step 4: Implement thin `get_media_usage` external class** requiring `viewusage`, validating context/media UUID, and returning structured values without secrets.
- [ ] **Step 5: Run targeted tests and verify GREEN.**
- [ ] **Step 6: Commit** `feat: expose media usage locations`.

### Task 3: Lifecycle AJAX endpoints and library/trash contracts

**Files:**
- Create: `public/local/digieramedia/classes/external/trash_media.php`
- Create: `public/local/digieramedia/classes/external/restore_media.php`
- Create: `public/local/digieramedia/classes/external/purge_media.php`
- Modify: `public/local/digieramedia/classes/external/search_media.php`
- Modify: `public/local/digieramedia/classes/external/get_media_versions.php`
- Modify: `public/local/digieramedia/db/services.php`
- Test: `public/local/digieramedia/tests/lifecycle_external_contract_test.php`

**Interfaces:**
- Produces AJAX services `local_digieramedia_get_media_usage`, `local_digieramedia_trash_media`, `local_digieramedia_restore_media`, `local_digieramedia_purge_media`.
- `search_media` additionally returns capability flags needed to render lifecycle controls but does not authorize destructive operations.

- [ ] **Step 1: Write failing external/source contract test** asserting all four service names/classes, `purge` boolean parameter, server-side service delegation, and no credentials/Authorization/presigned delete in browser-facing PHP/JS.
- [ ] **Step 2: Run test and verify RED.**
- [ ] **Step 3: Implement three thin lifecycle external adapters** and add services.
- [ ] **Step 4: Harden `search_media` trash scope** so TRASHED Media is visible only to owner or system-wide management permissions; return `cantrash`, `canrestore`, `canpurge`, `canviewusage` summary flags.
- [ ] **Step 5: Extend `get_media_versions`** to read TRASHED media for authorized lifecycle/history views, while preserving ACTIVE-only Replace/version mutation elsewhere.
- [ ] **Step 6: Run tests and verify GREEN.**
- [ ] **Step 7: Commit** `feat: add lifecycle ajax contracts`.

### Task 4: Renderer safety while trashed

**Files:**
- Modify: `public/filter/digieramedia/classes/text_filter.php`
- Test: `public/filter/digieramedia/tests/text_filter_lifecycle_test.php`

**Interfaces:**
- Consumes Media status and Reference status.
- Produces renderer contract: ACTIVE/DRAFT ref + ACTIVE/TRASHED media renders READY selected version; PURGING/PURGED or UNRESOLVED renders unavailable.

- [ ] **Step 1: Write failing renderer lifecycle test** proving TRASHED Media still renders and PURGED/UNRESOLVED does not.
- [ ] **Step 2: Run test and verify RED** because current filter only fetches Media `status = ACTIVE`.
- [ ] **Step 3: Change renderer selection** to accept Media status `ACTIVE` or `TRASHED`; keep all other states unavailable.
- [ ] **Step 4: Run targeted + existing renderer tests and verify GREEN.**
- [ ] **Step 5: Commit** `fix: keep trashed media rendering safely`.

### Task 5: TinyMCE Admin/KTV lifecycle UI

**Files:**
- Modify: `public/lib/editor/tiny/plugins/digieramedia/amd/src/ui.js`
- Modify: `public/lib/editor/tiny/plugins/digieramedia/templates/modal.mustache` only if lifecycle confirmation markup requires a hidden region/input.
- Modify/build: `public/lib/editor/tiny/plugins/digieramedia/amd/build/ui.min.js`
- Test/contract: `.digiera/tests/digiera-media-lifecycle-ui-contract.sh`

**Interfaces:**
- AJAX calls: `get_media_usage`, `trash_media`, `restore_media`, `purge_media`.
- UI regions/actions: usage list/count, trash action, restore action, purge action, second-confirmation state.

- [ ] **Step 1: Write failing UI source-contract script** asserting usage endpoint calls, Trash/Restore/Purge actions, explicit destructive second confirmation, no browser DELETE, TRASHED state disables new-reference/Replace/Follow-Pin mutation, and current version-history UI remains present.
- [ ] **Step 2: Run contract and verify RED.**
- [ ] **Step 3: Add AJAX helpers and lifecycle state loading** to `ui.js`; load usage together with version history after selecting Media.
- [ ] **Step 4: Render ACTIVE lifecycle panel** with `Vị trí đang sử dụng (N)`, safe usage links, explanatory trash text and `Đưa vào thùng rác` when allowed.
- [ ] **Step 5: Render TRASHED panel** with badge, trash metadata, read-only version history, live usage count, Restore and Permanent Delete according to permissions; disable Save/new-reference/Replace/version-mode mutation.
- [ ] **Step 6: Implement explicit two-stage purge confirmation**; first click reveals exact irreversible warning based on server-returned live count, second click performs AJAX purge; force is only sent when livecount>0 and caller has purge capability.
- [ ] **Step 7: Refresh list/panel after trash/restore/purge** without reopening modal; Trash tab shows TRASHED item, restored item returns to Library, PURGED item disappears from Trash.
- [ ] **Step 8: Build AMD runtime file and run JS syntax/Grunt/lifecycle UI contract.**
- [ ] **Step 9: Commit** `feat: finish lifecycle admin panel`.

### Task 6: Versions, upgrade savepoint and regression suite

**Files:**
- Modify: `public/local/digieramedia/version.php`
- Modify: `public/lib/editor/tiny/plugins/digieramedia/version.php`
- Modify: `public/local/digieramedia/db/upgrade.php`
- Modify: `public/local/digieramedia/lang/en/local_digieramedia.php` only for new exception strings used by server code.
- Test: `.digiera/tests/digiera-media-lifecycle-source-contract.sh`

**Interfaces:**
- Produces plugin versions `2026090902` and global upgrade savepoint `2026090902`.

- [ ] **Step 1: Write failing lifecycle source-contract script** checking version numbers, global `xmldb_local_digieramedia_upgrade()`, savepoint `2026090902`, services, renderer TRASHED allowance, R2 DELETE implementation, and no secret exposure.
- [ ] **Step 2: Run contract and verify RED.**
- [ ] **Step 3: Bump local/tiny versions and release strings; add global savepoint block** `if ($oldversion < 2026090902) upgrade_plugin_savepoint(...)` without namespace declaration.
- [ ] **Step 4: Run PHP syntax, source contract, existing reference-version test, lifecycle/usage tests, and full DIGIERA runtime suite.**
- [ ] **Step 5: Commit** `chore: prepare lifecycle rc1 version`.

### Task 7: Two-node deployment helper and acceptance checkpoint

**Files:**
- Create: `.digiera/tools/digiera-media-rc1-lifecycle-deploy.sh`
- Create: `docs/superpowers/specs/2026-09-09-digiera-media-lifecycle-deploy-ready.md`

**Interfaces:**
- Pinned payload commit from this feature branch.
- Verifies Web01/Web02 version/service/source/runtime parity and R2 credentials only; never physically deletes an object in preflight.

- [ ] **Step 1: Write helper with fail trap, two-node CLI/R2 precheck, pinned downloads, source contract, stage hash parity, two-node snapshots, maintenance+cron quiesce, install, Moodle upgrade, service/version checks, cache/FPM refresh, two-node parity, R2 runtime read check, and return-to-service.**
- [ ] **Step 2: Add helper assertions** proving there is no invocation of lifecycle purge/R2 DELETE in any preflight section.
- [ ] **Step 3: Run `bash -n` and static helper contract; verify PASS.**
- [ ] **Step 4: Run/inspect CI workflow and fix only evidence-backed failures.**
- [ ] **Step 5: Write deploy-ready Spec Kit checkpoint** including commit SHA, automated evidence, exact two-node deploy command and expendable-media browser acceptance sequence.
- [ ] **Step 6: Commit** `ops: add lifecycle two-node deploy gate`.

## Self-review

- Spec coverage: usage, trash, restore, normal purge, force purge, renderer behavior, permission split, R2 DELETE, audit, UI and two-node deployment all have explicit tasks.
- Deferred items remain deferred: true Recent tracking, stale-reference reconciliation, scheduled retention purge, multipart >100 MiB, backup/Course Publisher acceptance, migration acceptance, bulk lifecycle UI.
- Type consistency: live refs are always ACTIVE+DRAFT; lifecycle calls use Media UUID; R2 deletion remains `delete_object(bucket,key): void`; plugin target version is consistently `2026090902`.
- Safety: no task permits browser-side DELETE, no normal purge with live refs, no silent marker removal, and no deploy preflight that destroys a real R2 object.
