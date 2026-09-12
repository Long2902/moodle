# Course Publisher 1.1.0 Multi-target Fan-out Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extend `local_coursepublisher` 1.0.0 from exact one-target publishing to controlled one-source-to-many-target Batch publishing across configured schools/regions while preserving the existing single-target worker and safety model.

**Architecture:** Add durable Batch and per-target snapshot layers above the existing `local_cp_job` engine. Logical placement is portable and resolved independently to target-specific Moodle IDs; one child job still mutates exactly one target course. A scheduled dispatcher exposes at most 10 Batch jobs to Moodle's adhoc queue, with mutation-state safety, origin mapping, retry/recheck/pause/cancel controls, and an additive Batch UI.

**Tech Stack:** Moodle 5.1, PHP 8.3, XMLDB, Moodle Forms, scheduled/adhoc tasks, Lock API, Moodle Core backup/restore, privacy API, PHPUnit/`advanced_testcase`, AMD JavaScript.

**Spec:** `/mnt/data/COURSEPUBLISHER_1.1.0_MULTITARGET_FANOUT_DESIGN_2026-08-29.md`

## Global Constraints

- Component remains `local_coursepublisher`; Moodle requirement remains `2025100600`.
- Release/version: `1.1.0-production` / `2026082901`.
- Targets come only from configured `local_cp_target` bindings for Program + Grade.
- Every selected target is snapshotted; blocked targets remain visible and cannot be forced.
- Existing 1.0.0 single-target workflow stays functional.
- One child job mutates one target and keeps the existing `target_<courseid>` lock.
- No daemon, no Web02 cron, and no infrastructure changes.
- Bulk preflight is read-only; cross-course IDs are never the logical placement contract.
- Resolver is deterministic/fail-closed; no fuzzy matching or first-match fallback.
- Batch jobs start `waiting`; active Batch `queued + running` global ceiling is 10.
- Safe retry only from `mutationstate=none`; `uncertain`/legacy `unknown` are not auto-retried.
- Origin mappings are written only after successful postconditions; current mapping blocks duplicate publish.
- Source fingerprint and logical placement are revalidated immediately before mutation.

---

## File Structure

### New files

- `classes/local/batch_status.php` — Batch/target state constants.
- `classes/local/logical_placement.php` — pure normalization and unique-candidate matching.
- `classes/local/logical_placement_resolver.php` — source logical contracts and target physical resolution.
- `classes/local/origin_map_service.php` — mapping lookup/duplicate guard/persistence.
- `classes/local/batch_service.php` — discovery, preflight, creation, reconciliation and actions.
- `classes/task/dispatch_batches_task.php`, `db/tasks.php` — controlled dispatcher.
- `classes/form/batch_form.php`, `batch.php`, `batches.php`, `batch_view.php` — Batch UI.
- `amd/src/batch_selector.js`, `amd/build/batch_selector.min.js` — selection/search helper.
- `tests/logical_placement_test.php`, `tests/batch_service_test.php`, `tests/dispatcher_test.php`, `tests/origin_map_test.php`.

### Modified files

- `db/install.xml`, `db/upgrade.php`, `version.php`.
- `classes/local/job_status.php`, `classes/local/job_service.php`, `classes/local/service.php`, `classes/local/content_publisher.php`.
- `classes/task/publish_job_task.php`, `classes/privacy/provider.php`.
- `lib.php`, `jobs.php`, `styles.css`, EN/VI language files, `README.md`, `CHANGES.md`.

---

### Task 1: Schema and state model

**Produces:** three new tables; job Batch/mutation fields; `batch_status`; `waiting` and `manual_review` job states.

- [ ] Add failing structural assertions for absent Batch tables/fields.
- [ ] Implement exact Design §8 XMLDB schema and `2026082901` upgrade migration.
- [ ] Add state constants/helpers and version bump.
- [ ] Parse `install.xml`, lint PHP, and record a tree-hash checkpoint.

### Task 2: Pure logical-placement model

**Produces:**

```php
logical_placement::normalise_text(string $value): string
logical_placement::choose_unique_candidate(array $signature, array $candidates): array
```

- [ ] Write failing tests for normalization, unique structural match, and ambiguous duplicate blocking.
- [ ] Run a standalone pure-PHP harness and verify the missing implementation fails.
- [ ] Implement exact deterministic matching using type/name/module/ordinal/parent/neighbors.
- [ ] Re-run harness and lint; ties must remain `ambiguous`.

### Task 3: DB-backed logical placement resolver

**Produces:**

```php
logical_placement_resolver::build_source_contract(string $type, int $sourceid, int $sourcecourseid, string $publishmode): array
logical_placement_resolver::resolve_for_target(array $contract, int $targetcourseid, string $publishmode): array
```

- [ ] Add advanced tests for Activity/Section anchors, missing one anchor, contradictions, delegated subsection, manual override.
- [ ] Implement source signature readers and target candidate readers.
- [ ] Translate logical `after predecessor` Activity placement to existing physical `before next CM` or `end` semantics.
- [ ] Validate every physical result through existing `content_publisher` validators; unresolved/ambiguous is blocked.

### Task 4: Origin map service

**Produces:**

```php
origin_map_service::find_current(...): ?stdClass
origin_map_service::assert_not_already_published(...): void
origin_map_service::record_job_success(stdClass $job, array $mappings): void
```

- [ ] Write failing current/stale/duplicate/Section-child mapping tests.
- [ ] Implement current target-object validation and duplicate guard.
- [ ] Persist mappings only after proven success.

### Task 5: Job refactor and mutation-state safety

**Preserves:** `job_service::create_or_reuse(...)` continues exact single-target create+queue behavior.

**Adds:**

```php
job_service::create_for_batch_target(int $batchtargetid, array $context): stdClass
job_service::set_mutation_state(int $jobid, string $state): void
```

- [ ] Add regression test that one-target creation still queues; Batch child starts `waiting` without adhoc task.
- [ ] Separate record creation from queueing without changing old public behavior.
- [ ] New real-copy jobs start `mutationstate=none`.
- [ ] Persist `started` immediately before first target write and `committed` only after postconditions + origin mapping.
- [ ] Convert exceptions after `started` to `uncertain + manual_review`; pre-mutation failures remain `none`.
- [ ] Synchronize child transitions to Batch Target state/counters.

### Task 6: Batch service — discovery, preflight and creation

**Produces:**

```php
batch_service::eligible_targets(int $programid, string $gradekey): array
batch_service::preflight(array $request): array
batch_service::create_from_preflight(array $preflight, int $userid): stdClass
batch_service::reconcile(int $batchid): stdClass
batch_service::recheck_blocked(int $batchid): stdClass
batch_service::retry_safe_failures(int $batchid): stdClass
batch_service::pause(int $batchid, string $reason=''): void
batch_service::resume(int $batchid): void
batch_service::cancel(int $batchid): void
```

- [ ] Test configured-only target selection and disabled entity exclusion.
- [ ] Build common source preflight once (master/source/manifest/fingerprint/logical contract).
- [ ] Run independent target route/origin/placement preflight.
- [ ] In one transaction create Batch + every target snapshot + waiting child jobs only for ready targets.
- [ ] Implement reconciliation, blocked recheck, safe retry, pause/resume/cancel; never add post-snapshot targets.

### Task 7: Controlled fair dispatcher

**Produces:** `\local_coursepublisher\task\dispatch_batches_task` scheduled every minute.

- [ ] Test waiting-only dispatch, global/per-Batch ceilings, paused/cancelled exclusion, and oldest eligible fairness.
- [ ] Implement one short `inspect -> dispatch -> exit` invocation with global active ceiling 10.
- [ ] Add `db/tasks.php`; no sleep loop and no new worker service.

### Task 8: Execution-time Batch revalidation

- [ ] Test source fingerprint change and target-only structural change.
- [ ] Before child mutation revalidate Batch state, topology, target course, source fingerprint, manifest, logical placement and origin duplicate status.
- [ ] Source-wide fingerprint mismatch pauses Batch and stops new dispatch.
- [ ] Target-only mismatch affects only that target before mutation.
- [ ] Preserve old single-target runtime validation path.

### Task 9: Multi-target UI and dashboard

- [ ] Add EN/VI keys first and keep exact parity.
- [ ] Add separate Batch page (do not replace `preview.php`) with Program/Grade/source/publish mode/auto-vs-override inputs.
- [ ] Render configured targets grouped by Region with select-all, per-region select-all and client search.
- [ ] Implement read-only bulk preflight and explicit confirmation; no force-blocked action.
- [ ] Implement `batches.php`, `batch_view.php` and capability+sesskey-protected actions.
- [ ] Show Batch references on child jobs; add scoped CSS/AMD helper.

### Task 10: Privacy, docs and release metadata

- [ ] Export `local_cp_batch` rows for the user and anonymize Batch `userid` to 0 on deletion requests.
- [ ] Update README/CHANGES with fan-out boundaries and safety rules.
- [ ] Confirm version/release/maturity metadata.

### Task 11: Verification and packaging

- [ ] Lint all PHP.
- [ ] Parse XMLDB and lint scheduled task metadata.
- [ ] Verify EN/VI key parity and duplicate-key absence.
- [ ] Run standalone resolver tests.
- [ ] Run Moodle PHPUnit only if a Moodle 5.1 test environment exists; otherwise report limitation explicitly.
- [ ] Build and integrity-test RC ZIP first.
- [ ] Static regression review: old entrypoints/public signatures remain; no infrastructure/Web02 cron changes.
- [ ] Only after available verification passes, build `local_coursepublisher_moodle51_1.1.0-production.zip`, run `unzip -t`, and record SHA256.
