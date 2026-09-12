# Change log

## 1.1.1-production — 2026-08-29

- Promoted the 1.1.1 topology auto-discovery line to the production baseline after live validation.
- Live-validated configurable Target Group `CDS`, recognition rules, topology discovery, bulk registration and idempotent re-scan across Thái Nguyên, Ninh Bình and Hà Nội topology.
- Live Batch #2: one source Activity (`CMID #22384`, source course `#27`) fan-out to 8 configured CDS targets; all 8 child jobs completed with `mutationstate=committed`, 0 failed, 0 manual review.
- Live negative validation: duplicate origin mapping on the Đồng Hỷ target is blocked before mutation while the remaining targets stay READY.
- Live Batch #4: 8 selected, 7 READY completed and committed, 1 BLOCKED due to an existing origin mapping, 0 failed, 0 manual review.
- Discovery UI hardening from RC2–RC5 retained: safe `gradekey` index migration, summary cards, post-filter counters, unmatched-course visibility toggle, and matched-course counter.
- No publishing-engine redesign from 1.1.0: target locks, dispatcher, Moodle Core backup/restore, logical placement, origin mapping and mutation-state semantics remain unchanged.

## 1.1.1-topology-autodiscovery-rc1 — 2026-08-29

- Generalised fixed Grade `10/11/12` routing into configurable Program-scoped Target Groups while preserving the historical `gradekey` route/API field for backward compatibility.
- Added deterministic upgrade mapping for existing master, target and Batch records; unsupported active legacy keys fail the upgrade instead of being guessed.
- Added Target Group UI with immutable technical keys and per-group master-course support.
- Added configurable course-recognition rules over Moodle `fullname`/`shortname` using `contains`, `starts_with`, or validated `regex` matching.
- Added configurable topology-container rules, seeded for `Khối THPT`, `Khối Liên Cấp`, and `Trung tâm`.
- Added an explicit per-Program discovery root so scanner eligibility cannot escape the deployment subtree.
- Added root-scoped Region → container → School/Unit discovery using Moodle category IDs as identity and stable generated codes `AUTO_R_<categoryid>` / `AUTO_U_<categoryid>`.
- Added preview statuses for READY, existing, unmatched, missing, cross-group ambiguity, duplicate Unit+Group courses, conflicting existing bindings, reused target courses, invalid topology, and excluded master courses.
- Added bulk registration with Select-all-READY, server-side re-scan/revalidation, per-candidate transaction isolation, and reuse of existing topology/target save invariants.
- Discovery never creates Moodle categories/courses and never publishes content; 1.1.0 Batch fan-out, locks, backup/restore, logical placement, origin mapping and mutation-state handling remain unchanged.
- Added fresh-install container seeds and automatic conservative `10/11/12` Target Group/rule seeds for newly created Programs.

## 1.1.0-production — 2026-08-29

- Added multi-target fan-out from one master Activity/Section to many configured target bindings across schools and regions.
- Added durable Batch and Batch Target snapshots so READY, BLOCKED, failed and manual-review outcomes remain independently auditable.
- Added automatic logical placement derived from master structure plus a single manual logical override resolved independently per target; cross-course Moodle IDs are not used as portable placement identifiers.
- Added deterministic fail-closed placement resolution: zero or ambiguous matches are blocked rather than guessed.
- Added a short-lived scheduled dispatcher with a global ceiling of 10 Batch child jobs in queued/running state; existing Web01-only Moodle cron topology remains unchanged.
- Reused the existing one-target worker, target lock, Moodle Core backup/restore and postcondition engine for every child job.
- Added mutation-state tracking (`none`, `started`, `committed`, `uncertain`, legacy `unknown`) and restricted Batch retry to failures proven to have made no target mutation.
- Added pause/resume/cancel, recheck-blocked and retry-safe Batch actions.
- Added source fingerprint and target logical-placement revalidation immediately before mutation; source-wide change pauses the Batch.
- Added durable origin mapping for successful publication and duplicate-publication blocking while update/sync behavior remains out of scope for 1.1.0.
- Added Batch dashboard/detail UI, target selection by configured Program + Grade bindings, per-region select-all/search, and Batch references on child-job views.
- Extended Moodle privacy metadata/export/anonymisation for Batch creator records.

## 1.0.0-production — 2026-08-28

- Promoted the validated generic publishing engine to the production baseline.
- Removed development/pilot/foundation explanatory banners and redundant operator help from the normal UI.
- Removed the diagnostic FOUNDATION action from the normal publishing screen; historical foundation jobs remain readable.
- Kept explicit mutation confirmation, preflight errors, unsupported-module reasons and manual-review warnings.
- Generic activity publishing is capability-driven through Moodle `FEATURE_BACKUP_MOODLE2`.
- Supports exact activity placement and peer-section placement before/after existing sections.
- Supports Moodle delegated `mod_subsection` either preserved as a subsection tree or promoted to a normal peer section.
- Retains background adhoc tasks, target locking, idempotency, execution-time revalidation, audit and no-blind-retry safety.

## 0.2.3-case2-section-page-placement-pilot — 2026-08-27

- Keeps validated exact Activity placement from 0.2.2.
- Adds a deliberately narrow real whole-Section pilot for **standard Page-only sections**.
- Operator chooses exact new-section position: beginning of course, end of course, or immediately before an existing standard section.
- Creates the target section with Moodle Core `course_create_section()` and updates safe metadata with `course_update_section()`.
- Copies Page activities one-by-one through the existing Moodle Core backup/restore path and appends them to the new section in source sequence order.
- Adds `local_cp_job.targetsectionid` so the created target section identity is durably recorded before activity copying begins.
- Strong postconditions require the new section's full activity sequence to exactly equal recorded target CMIDs in source order.
- Blocks Section 0, delegated/subsection sections, section summaries with embedded `@@PLUGINFILE@@` files, and any non-Page module before target-section creation.
- Failed/uncertain real Section jobs remain manual-review only and are not blindly retried.
- Quiz and other module types remain blocked for whole-Section real copy until their dependency handling is validated.

## 0.2.0-case2-job-foundation — 2026-08-26

- Added durable CASE 2 job, item and job-event tables with an upgrade path from 0.1.2.
- Added exact-route enqueue from a successful preview when one School is explicitly selected.
- Added Moodle adhoc-task execution, target-course locking, execution-time topology/source revalidation and soft lock retry.
- Added source fingerprints and idempotency-key reuse to prevent duplicate equivalent jobs.
- Added Section/Activity manifest scaffolding and item-level state records.
- Added a real Jobs page with job states, items and event audit.
- Updated privacy metadata/export/anonymisation for job tables.
- Deliberately performs NO course-content mutation; successful foundation jobs mark item scaffolds as skipped/not copied.


## 0.1.2-case1-ui-polish — 2026-08-25

- UI-only polish release on top of the functionally validated 0.1.1 CASE 1 baseline.
- Expanded Course Publisher page width on RemUI/Boost-style layouts using plugin-scoped `path-local-coursepublisher` selectors.
- Increased sidebar/navigation, heading, form-control, KPI, table, badge, detail-panel and helper-text sizing for desktop readability.
- Increased card spacing and content breathing room while keeping responsive breakpoints for smaller screens.
- Kept all Course Publisher styling inside the plugin; no RemUI core files or site-wide theme Custom CSS are required.
- No routing, topology, dry-run, capability, persistence, publish/copy or CASE 2 engine logic changes.

## 0.1.1-case1-hardening — 2026-08-25

- Reworked the operator UI around the approved hybrid Dashboard + Table/Details RemUI mockup direction.
- Added responsive plugin-local navigation, KPI cards, health summaries, configuration-detail panels and status badges.
- Reworded Vietnamese UX around `Khóa học nguồn`, `Khóa học đích`, `Kiểm tra cấu hình`, and `Chạy thử tuyến`.
- Confirmed and documented Section dry-run support using `course_sections.id` from `/course/section.php?id=...`.
- Kept Activity dry-run support using CMID / `course_modules.id`.
- Added explicit wrong-grade source feedback showing actual vs expected Source Course.
- Reject activities pending deletion.
- Added `local/coursepublisher:preview` as a read-only CASE 1 capability; kept `publish` reserved for CASE 2.
- Require Region category mappings.
- Require School category mappings and enforce School descendant-of-Region topology.
- Fail closed when a School has no valid category.
- Enforce Target Course inside the School category subtree.
- Prevent active Target Course reuse across routes.
- Prevent active Source/Target role overlap.
- Prevent one Source Course from representing multiple grades within the same Program.
- Hardened Topology Health to re-check invariants and show operator-facing reasons.
- Course selectors now include category paths to reduce ambiguity on large sites.
- No real content publish/copy functionality added.

## 0.1.0-case1 — 2026-08-22

- Initial installable Case 1 build.
- Production-safe topology registry and routing dry-run only.
- No source-to-target content writes.

## 0.2.1-case2-page-copy-pilot — 2026-08-26

- Keeps the validated 0.2.0 CASE 2 job foundation.
- Adds a deliberately narrow real mutation pilot for **Activity + mod_page only**.
- Uses Moodle Core `backup::TYPE_1ACTIVITY` + `backup::MODE_IMPORT` and `restore_controller` with `TARGET_EXISTING_ADDING`; no direct writes to Moodle activity/content tables.
- Adds an explicit second confirmation screen before a real-copy job can be queued.
- Records the resulting target CMID on the durable job item.
- Blocks automatic retry after a real-copy failure/uncertain outcome to avoid duplicate content.
- Real Section publishing and Quiz publishing remain disabled.

## 0.2.2-case2-target-placement-pilot — 2026-08-27

- Extends the real Page-copy pilot with an explicit target placement contract.
- Operator selects the exact target course section and insertion point before queueing.
- Supported insertion points: start of section, end of section, or immediately before an existing target CMID.
- Placement is stored in the durable job preflight snapshot and included in the idempotency key.
- Worker revalidates the selected target section/anchor immediately before mutation.
- After Moodle Core backup/restore creates the Page, Moodle Core `moveto_module()` places it in the requested section/sequence position.
- Strong postconditions verify both target section membership and exact sequence position.
- No direct writes to Moodle course content/sequence tables are introduced by Course Publisher.
- Whole-Section real copy remains intentionally disabled until exact activity placement is production-validated.


## 0.2.4-case2-section-page-quiz-pilot

- Keeps Foundation, Page placement, and 0.2.3 Page-only section jobs backward compatible.
- Adds `section_mixed` jobs for standard sections containing Moodle Page + Quiz.
- Quiz mutation delegates to Moodle Core `TYPE_1ACTIVITY` backup/restore.
- Validates target Quiz instance and restored quiz-slot count before success.
- Creates the destination section at the operator-selected section position.
- Copies mixed Page/Quiz items in exact source sequence order.
- Verifies final target section sequence exactly matches durable target CMIDs.
- Real-mutation failures remain manual-review/no-auto-retry to avoid duplicates.
- Renames the Foundation UI action to make **NO COPY** explicit.
- Adds a single-Quiz exact-placement pilot so the production Quiz path can be validated before whole mixed-section copy.

## 0.2.5-case2-delegated-subsection-pilot

- Detects Moodle 5.1 delegated sections backed by `mod_subsection` instead of treating them as unsupported standard sections.
- Adds a dedicated real-copy mode for delegated subsections.
- Uses Moodle Core `TYPE_1ACTIVITY` backup/restore on the delegating `mod_subsection` CM; Core automatically includes the delegated section and its child activities in the backup plan.
- Places the restored subsection activity into an explicitly selected standard parent section/activity position.
- Verifies the restored delegated-section child count, Page/Quiz order, and Quiz slot structure.
- Keeps FOUNDATION diagnostic jobs explicitly non-mutating.
- No DB schema change.

## 0.2.6-case2-generic-peer-section (2026-08-28)

- Added generic single-activity publishing for installed module types that declare `FEATURE_BACKUP_MOODLE2`.
- Removed the Page/Quiz allow-list from delegated-subsection tree restore; child modules are preflighted dynamically for Moodle backup support.
- Added a new peer-section output mode: a normal or delegated source section can be copied as a **normal same-level target section**.
- Peer-section placement now supports **start, end, immediately before, and immediately after** an existing normal target section.
- A delegated subsection source can now be either:
  - preserved as a delegated subsection inside a selected parent section; or
  - promoted/flattened into a normal same-level section.
- Generic peer-section copy preserves immediate source order and handles nested `mod_subsection` children through Moodle Core subtree backup/restore.
- Unsupported installed modules are blocked before target mutation with the exact module type reported.
- No direct writes to Moodle Core content tables were introduced; mutation continues through Moodle Core backup/restore/course APIs.
