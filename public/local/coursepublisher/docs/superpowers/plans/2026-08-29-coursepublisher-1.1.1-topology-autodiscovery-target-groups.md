# Course Publisher 1.1.1 Topology Auto-Discovery & Target Groups Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the fixed 10/11/12 topology vocabulary with configurable Target Groups and add a fail-closed scanner that discovers Region/Unit/course candidates under a configured Moodle deployment root and bulk-registers safe target bindings without publishing content.

**Architecture:** Keep the 1.1.0 publishing engine unchanged. Add Target Group/rule/container configuration tables and a pure classification layer; a DB-backed discovery service scopes reads to the configured root, reconciles discovered Moodle IDs with existing Course Publisher topology, previews READY/EXISTING/UNMATCHED/CONFLICT states, then revalidates selected READY rows before calling existing `save_region()`, `save_school()`, and `save_target()` invariants.

**Tech Stack:** Moodle 5.1, PHP 8.3, XMLDB, Moodle Forms, Moodle DB API, existing Course Publisher services, PHPUnit/standalone PHP structural harnesses.

**Spec:** `docs/superpowers/specs/2026-08-29-coursepublisher-1.1.1-topology-autodiscovery-target-groups-design.md`

## Global Constraints

- Component remains `local_coursepublisher`; Moodle requirement remains `2025100600`.
- 1.1.0 Batch/job/backup-restore/origin-map/logical-placement behavior is not redesigned.
- Discovery is read-only until explicit bulk registration confirmation.
- Scanner never leaves the configured Program deployment root.
- Region identity and Unit identity use Moodle category IDs; generated codes are `AUTO_R_<categoryid>` and `AUTO_U_<categoryid>`.
- Cross-group ambiguity, duplicate Unit+Group course matches, wrong existing bindings, reused courses, invalid topology, and invalid regex fail closed.
- No force-register action, auto-cloning, auto-category creation, auto-publishing, fuzzy matching, or direct SQL writes.
- Existing 10/11/12 routes and historical Batch/job records remain readable.
- New group keys up to 64 characters work end-to-end.

---

### Task 1: Schema, migration, and structural regression harness

**Files:** `db/install.xml`, `db/upgrade.php`, `version.php`, `tests/target_group_schema_harness.php`.

**Produces:** `local_cp_target_group`, `local_cp_discovery_rule`, `local_cp_discovery_container`; `discoveryrootcategoryid`; expanded `gradekey`; additive `targetgroupid` mappings; 10/11/12 seed migration.

- [ ] Write a standalone structural test asserting the 1.1.1 tables/fields/version are absent.
- [ ] Run it and confirm RED.
- [ ] Implement XMLDB install schema and upgrade migration with deterministic 10/11/12 backfill and explicit unsupported-key failure.
- [ ] Run structural test, XML parse, and PHP lint until GREEN.

### Task 2: Target Group compatibility service and forms

**Files:** `classes/local/service.php`, `classes/form/program_form.php`, `classes/form/master_form.php`, `classes/form/target_form.php`, new `classes/form/target_group_form.php`, new `target_groups.php`, existing master/target/list screens.

**Produces:** `resolve_target_group()`, `target_group_options()`, `target_group_label()`, generalized `assert_grade()` syntax compatibility, Target Group CRUD and program root configuration.

- [ ] Add failing static/service harness assertions for no fixed `GRADES` dependency in active forms and for custom key support.
- [ ] Confirm RED.
- [ ] Implement Target Group CRUD and route master/target writes through canonical `targetgroupid` while preserving `gradekey`.
- [ ] Replace user-facing fixed grade selects/labels with program-aware Target Group options/labels.
- [ ] Re-run harness/lint GREEN.

### Task 3: Configurable course and container rule engine

**Files:** new `classes/local/discovery_matcher.php`, new rule/container forms, new `discovery_rules.php`, language files.

**Produces:** pure normalization, `contains`/`starts_with`/`regex`, rule validation, cross-group conflict semantics, configurable container patterns.

- [ ] Write failing pure-PHP matcher harness for normalization, same-group multi-rule success, cross-group conflict, starts-with, regex and invalid regex.
- [ ] Confirm RED.
- [ ] Implement minimal pure matcher.
- [ ] Add CRUD services/forms/UI for discovery rules and container rules.
- [ ] Re-run harness/lint GREEN.

### Task 4: Topology discovery and reconciliation engine

**Files:** new `classes/local/discovery_service.php`, `tests/discovery_service_static_harness.php`.

**Produces:** root-scoped Region/container/Unit/course discovery, classification and statuses `READY_NEW`, `EXISTING`, `UNMATCHED`, `MISSING`, `CONFLICT_GROUP`, `CONFLICT_DUPLICATE_COURSE`, `CONFLICT_EXISTING_BINDING`, `CONFLICT_COURSE_REUSED`, `INVALID_TOPOLOGY`.

- [ ] Write failing structural/static harness for required status constants, root-bound checks, ID-based reuse, and no write calls during scan.
- [ ] Confirm RED.
- [ ] Implement batched DB reads and deterministic classification/reconciliation.
- [ ] Run static harness/lint GREEN.

### Task 5: Bulk registration with execution-time revalidation

**Files:** `classes/local/discovery_service.php`, discovery registration tests/harness.

**Produces:** explicit candidate token/snapshot data and `register_selected()` that revalidates topology/classification before reusing existing `service::save_region()`, `save_school()`, `save_target()`.

- [ ] Add failing harness assertions that bulk registration uses existing save services and never force-overwrites a target.
- [ ] Confirm RED.
- [ ] Implement per-candidate re-scan/revalidation and `CREATED` / `BECAME_EXISTING` / `FAILED_REVALIDATION` / `FAILED_WRITE` outcomes.
- [ ] Add discovery audit actions.
- [ ] Run harness/lint GREEN.

### Task 6: Discovery and configuration UI/navigation

**Files:** new `discovery.php`, new `classes/form/discovery_form.php`, `lib.php`, `styles.css`, EN/VI language files.

**Produces:** `Nhóm đích / Khối`, `Quy tắc nhận diện`, `Khám phá topology` navigation; root/container/group selection; preview table; READY-only checkboxes; explicit bulk registration POST with sesskey.

- [ ] Add failing string/nav parity harness for new pages/keys.
- [ ] Confirm RED.
- [ ] Implement pages and scoped styling using existing CP shell/capabilities.
- [ ] Re-run language parity, missing-string scan, JS/PHP lint GREEN.

### Task 7: Backward compatibility across health, preview, Batch, jobs

**Files:** `classes/local/service.php`, `classes/local/batch_service.php`, `classes/local/job_service.php`, batch/preview/jobs/list pages and forms.

**Produces:** dynamic Target Group labels/options while retaining `gradekey` route parameters and public signatures.

- [ ] Add regression harness assertions that legacy entrypoints/signatures remain and fixed `get_string('grade'.$key)` calls are removed from dynamic paths.
- [ ] Confirm RED.
- [ ] Replace hard-coded `GRADES` loops/filters with enabled Target Groups per Program and label helpers.
- [ ] Preserve old 10/11/12 URL/API behavior and job creation signatures.
- [ ] Run regression harness/lint GREEN.

### Task 8: Privacy/docs/release and complete verification

**Files:** `classes/privacy/provider.php` if required by new personal fields, `README.md`, `CHANGES.md`, `version.php`, packaging artifact.

**Produces:** 1.1.1 RC ZIP and verification report.

- [ ] Update docs with discovery safety boundary and Target Group workflow.
- [ ] Verify no new personal-data table requires provider changes beyond existing audit ownership model; update provider only if schema stores user-linked data.
- [ ] Run all standalone harnesses, PHP lint, XML parse, tasks parse, EN/VI parity, duplicate/missing language keys, legacy signature checks and ZIP integrity.
- [ ] If Moodle PHPUnit runtime is unavailable, state that limitation explicitly instead of claiming runtime coverage.
- [ ] Build `local_coursepublisher_moodle51_1.1.1-topology-autodiscovery-rc.zip`, validate ZIP root/integrity, and record SHA256/tree hash.
