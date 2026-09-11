# DIGIERA Media RC1 — Backup/Restore + Course Publisher Production Design

Date: 2026-09-11

Status: DESIGN APPROVED IN CHAT; WRITTEN SPEC AWAITING FINAL USER REVIEW

Branch: `feature/digiera-media-v1-rc1-backup-coursepublisher`

Base checkpoint: `c22e0984ad24afdb052afd60e75c3119e0170277` (`Recent + Permissions` browser acceptance PASS)

Do not merge this branch to `main` as part of this work.

## 1. Goal

Finish the production-safe Moodle Backup/Restore path for `local_digieramedia` and prove compatibility with the real `local_coursepublisher` 1.1.1 production plugin supplied by the operator.

This batch must make DIGIERA Media references survive Moodle activity/section cloning without copying database-local IDs into backup files, without exposing R2 bucket/object keys or secrets, and without silently changing the selected media/version semantics.

Production acceptance requires both:

1. real Moodle backup/restore acceptance; and
2. real Course Publisher acceptance through its existing publish engines.

Static classes, feature flags, or unit tests alone are insufficient.

## 2. Course Publisher baseline frozen for this design

Operator-supplied artifact:

`local_coursepublisher_moodle51_1.1.1-production(1).zip`

Inspected archive SHA256:

`71e0b1334bf73c378a7c6986676069930188a99e55060af1baa3aa31794bdffb`

Plugin metadata from the archive:

- component: `local_coursepublisher`
- version: `2026082902`
- release: `1.1.1-production`
- Moodle requirement: `2025100600` (Moodle 5.1)

The supplied implementation already uses Moodle Core backup/restore as the generic publishing engine:

- generic activity validation calls `plugin_supports('mod', $modname, FEATURE_BACKUP_MOODLE2, false)`;
- `copy_generic_activity()` uses `backup_controller(TYPE_1ACTIVITY, ..., MODE_IMPORT)` then `restore_controller(..., MODE_SAMESITE, ..., TARGET_EXISTING_ADDING)`;
- delegated `mod_subsection` publishing backs up the subsection activity and relies on Moodle 5.1 to include its delegated section and child activities in the backup plan;
- batch publishing creates durable batch/target/job snapshots and runs publish work through adhoc tasks.

Therefore Course Publisher must not implement a second DIGIERA clone engine. It only carries the selected DIGIERA policy into the existing Moodle backup/restore call. All Media/Version/Reference/R2 behavior remains owned by `local_digieramedia`.

## 3. Current DIGIERA baseline and gaps

Existing foundations that must be reused:

- Moodle local-plugin backup structure exists under `local/digieramedia/backup/moodle2/`;
- the current manifest exports portable logical identifiers rather than R2 object keys;
- `clone_mode` already defines:
  - `SHARED_FOLLOW`
  - `SHARED_PINNED`
  - `INDEPENDENT`;
- `reference_remapper` already provides shared remapping foundations;
- `independent_copy_service` already provides server-side R2 copy, HEAD verification, locking and idempotent retry foundations;
- a real Moodle Page backup/restore integration test already proves the basic same-site Page path.

Production gaps to close:

1. The current backup plugin selects `ACTIVE` references by module context rather than collecting the markers actually stored in restored content. A UI-created reference can remain `DRAFT`, so valid saved content can otherwise be omitted from the backup.
2. The current restore plugin is hard-coded to `mod_page`.
3. The current restore path always selects `SHARED_FOLLOW`.
4. Restore still depends too heavily on the source Reference record existing in the live database. A portable backup manifest must contain enough logical data to reconstruct the target reference without relying on the source Reference row surviving until restore time.
5. `SHARED_PINNED` is not wired into a real Moodle restore operation.
6. `INDEPENDENT` is tested as a service but is not wired through a real Moodle restore/Course Publisher operation.
7. Course Publisher 1.1.1 has no DIGIERA clone-policy control or durable propagation field.
8. Production acceptance does not yet cover Page, Label/Text & media, Book Chapter, generic activity intro, normal Section, Batch fan-out, or delegated `mod_subsection`.

## 4. Architectural boundary

### 4.1 DIGIERA owns clone semantics

`local_digieramedia` owns:

- marker discovery;
- portable backup manifests;
- Media/version resolution;
- generation of new Reference UUIDs;
- version-mode semantics;
- independent R2 copies;
- restored Reference metadata/status;
- failure behavior.

`local_coursepublisher` owns only:

- operator selection of a DIGIERA clone policy;
- durable storage of that selection in the publish job/batch snapshot;
- opening a process-local DIGIERA integration scope around Moodle Core backup/restore;
- preserving its existing placement, routing, retry, batch and subsection behavior.

Course Publisher must never update DIGIERA Media tables directly and must never call R2 directly.

### 4.2 Optional bridge, not a hard dependency

Course Publisher must continue to operate when `local_digieramedia` is not installed.

The integration uses this optional class boundary:

`\local_digieramedia\integration\coursepublisher_bridge`

Course Publisher checks `class_exists()` before using it. If DIGIERA is absent, backup/restore executes exactly as Course Publisher 1.1.1 does today.

If DIGIERA exists, the bridge executes a callback inside a process-local clone-policy scope containing:

- selected clone mode;
- stable restore/publish operation id;
- target course identity when available.

Moodle backup and restore in Course Publisher execute synchronously inside the same worker PHP process, so process-local scope is sufficient for the Course Publisher path and avoids global mutable site settings.

Normal Moodle backup/restore outside Course Publisher has no bridge scope and therefore defaults to `SHARED_FOLLOW`.

## 5. Content adapter layer

Replace the Page-only restore logic with an explicit adapter registry. An adapter has one responsibility: expose stored content fields containing DIGIERA markers and write rewritten content back after Moodle has created the target records.

RC1 production adapter coverage is frozen as:

### 5.1 Page

- module: `page`
- source/target field: `{page}.content`
- entity type: `page`

### 5.2 Text and media area / Label

- module: `label`
- source/target field: `{label}.intro`
- entity type: `label`

### 5.3 Book chapters

- module: `book`
- fields: every `{book_chapters}.content` row belonging to the Book instance
- entity type: `book_chapter`
- source chapter identity must be carried in the backup manifest;
- restore must use Moodle restore mappings for Book chapter old-id -> new-id resolution rather than assuming IDs are preserved.

### 5.4 Generic activity `intro`

For a non-structural activity whose module instance table has a real `intro` field, the generic adapter may process that field.

This is intended to cover common editor-backed intro fields such as Assignment, Forum, URL and Resource without maintaining an arbitrary allow-list.

The generic adapter must:

- confirm the module instance table exists;
- confirm the `intro` column exists before use;
- skip modules with no `intro` field;
- not treat structural `subsection` data as a generic content table;
- update only the target module instance created by Moodle restore.

The adapter layer is intentionally bounded. RC1 does not scan arbitrary text columns across the database.

## 6. Marker-driven backup contract

Backup truth is the marker that is actually stored in content, not a context-wide query of Reference status.

For each adapter field:

1. parse `[[digiera-ref:REFERENCE_UUID]]` occurrences in stored order;
2. resolve each marker to a DIGIERA Reference and Media;
3. resolve the reference's effective READY version at backup time;
4. emit one manifest entry per marker occurrence;
5. fail the backup if a stored marker cannot be resolved safely.

A Reference may be `DRAFT` or `ACTIVE` when it is referenced by persisted content. The backup operation must not mutate status just to make backup succeed.

A DRAFT/ACTIVE Reference with no marker in the activity content is not included simply because it shares the module context.

Backup remains read-only with respect to DIGIERA DB/R2 state.

## 7. Portable manifest v2

Each marker occurrence must carry enough portable information to restore without reading the original Reference row after backup creation.

Manifest fields are frozen as:

- `source_reference_uuid`
- `media_uuid`
- `displayprofile`
- `source_versionmode`
- `effective_version_no`
- `alttext`
- `caption`
- `optionsjson`
- adapter/entity identity needed to locate the specific target content field, including source chapter id for Book
- occurrence ordinal where the same source Reference marker appears more than once in one logical field

`effective_version_no` is always recorded, including when the source reference is FOLLOW_CURRENT.

The backup manifest must not contain:

- database-local Media/Version/Reference numeric IDs;
- R2 bucket;
- R2 object key;
- presigned URLs;
- R2 credentials or secrets.

`pinned_version_no` from the v1 manifest is superseded by the unambiguous `effective_version_no` contract. Restore compatibility code may read the old field for older same-site backups if necessary, but new backups write v2.

The manifest is portable inside the same DIGIERA logical Media catalog. Cross-site binary transport into an unrelated Moodle installation is explicitly outside this batch.

## 8. Clone-mode semantics

### 8.1 SHARED_FOLLOW — default

Target behavior:

- new Reference UUID;
- same logical Media;
- no new Media row;
- no new Version row;
- no R2 copy;
- target reference mode is always `FOLLOW_CURRENT`;
- target `pinnedversionid = 0`.

This is the default for ordinary Moodle restore and Course Publisher when no explicit DIGIERA option is supplied.

A source PINNED reference restored under SHARED_FOLLOW intentionally becomes FOLLOW_CURRENT because clone policy controls target semantics.

### 8.2 SHARED_PINNED

Target behavior:

- new Reference UUID;
- same logical Media;
- no new Media/Version row;
- no R2 copy;
- resolve `media_uuid + effective_version_no` against the current DIGIERA DB;
- target mode is `PINNED_VERSION`;
- target `pinnedversionid` points to that exact READY version.

The restore must never silently fall back to Media current version when the snapshotted version cannot be resolved.

### 8.3 INDEPENDENT

Target behavior:

- new logical Media UUID;
- new Version v1;
- new Reference UUID;
- server-side R2 COPY of the source effective version;
- HEAD verification of the copied target object;
- target reference preserves the source FOLLOW/PIN intent using the new independent Version.

Within one publish/restore operation and one target course, the same source Media/effective-version pair maps to one independent target Media/Version and is reused by all restored References in that operation. This prevents duplicate R2 copies when the same Media appears in multiple activities or Book chapters.

Across different target courses or different publish operations, Independent mode produces distinct logical Media copies.

Independent identity must therefore be deterministic from a stable operation scope plus target course plus source Media UUID/effective version, rather than from an individual target module context alone.

Target Reference UUID generation remains occurrence-aware so distinct markers remain distinct References.

## 9. Media/version state rules during restore

Restore may use a source Media only when its state is safe for the selected operation.

Frozen RC1 rules:

- `ACTIVE`: allowed;
- `TRASHED`: allowed when the effective version remains READY, because existing DIGIERA lifecycle semantics keep trashed Media renderable and restorable;
- `PURGING`: rejected;
- `PURGED`: rejected;
- selected/effective Version must be `READY`.

A rejected source produces a restore failure with a precise DIGIERA reason. Restore must not silently select another Media/version.

## 10. Restore transaction/status semantics

For each target content record:

1. create/remap target References as `DRAFT`;
2. rewrite target marker text with target Reference UUIDs;
3. persist the rewritten Moodle target content;
4. update reference target metadata (`contextid`, `courseid`, `cmid`, component/entity/field); and
5. only then mark those restored References `ACTIVE`.

A failed content write must not leave a Reference ACTIVE while no persisted target content points to it.

Restore errors are fail-closed. No mode may silently downgrade to another mode.

## 11. Independent failure/retry behavior

The existing Independent Copy principles are preserved and strengthened for multi-activity operations.

Required behavior:

- acquire a deterministic restore-copy lock before creating/copying one independent Media mapping;
- if target R2 object already exists after an interrupted attempt, verify it with HEAD and reuse it;
- if R2 copy succeeds but DB transaction fails, retry must not copy another object;
- if DB work succeeds but the worker loses control before success is recorded, retry must resolve the deterministic target records instead of duplicating them;
- source R2 object is never deleted or modified;
- deploy itself performs no R2 mutation;
- live Independent acceptance uses disposable test Media/target course only.

## 12. Course Publisher UI and durable policy

Course Publisher adds the exact Vietnamese selector label `Xử lý học liệu DIGIERA` to both the single-publish screen in `publish.php` and the Batch form in `classes/form/batch_form.php`.

English language string: `DIGIERA media handling`.

Values:

- `shared_follow` — `Dùng chung + Theo phiên bản hiện tại` (default)
- `shared_pinned` — `Dùng chung + Ghim phiên bản hiện tại`
- `independent` — `Tạo bản sao học liệu độc lập`

The selector is safe even if the source contains no DIGIERA markers; it simply has no effect on non-DIGIERA content.

### 12.1 Durable schema

Add `digieramode` as `char(32)` with default `shared_follow` to:

- `local_cp_job`
- `local_cp_batch`

The mode must also be included in the relevant preflight/job JSON snapshots for auditability.

`local_cp_batch_target` does not need a separate column; its child job inherits the immutable mode from the parent Batch and records it in the child job row/snapshot.

### 12.2 Idempotency contract

DIGIERA mode is part of Course Publisher job identity.

Single-publish and Batch child idempotency keys must include `digieramode`, so the same source/target/placement published with a different DIGIERA mode cannot accidentally reuse an older job created under different semantics.

### 12.3 Worker propagation

`job_service` passes the persisted mode to the content-publisher call.

For:

- generic single Activity;
- each Activity in a normal Section publish;
- Batch child jobs;
- delegated `mod_subsection` tree restore;

Course Publisher executes the Moodle backup/restore controllers inside the optional DIGIERA bridge scope.

A stable integration operation id derives from the durable Course Publisher job plus target course. All activity restores belonging to that one job reuse the same operation id, which is required so Independent mode can deduplicate one source Media across multiple restored activities. Retry of the same job uses the same operation id; a different target/job uses a different id.

## 13. Course Publisher paths that must remain unchanged

The DIGIERA bridge must not alter Course Publisher's existing behavior for:

- route resolution;
- source fingerprints;
- target locking;
- placement and section insertion;
- source/target origin mapping;
- batch dispatch limits;
- manual-review rules after mutation failure;
- subsection delegated-section reconstruction;
- generic module backup capability preflight.

The bridge wraps the existing Moodle Core backup/restore call; it does not replace that call.

## 14. Source fingerprint interaction

Course Publisher currently fingerprints source content to protect publish consistency. DIGIERA marker text is part of the source activity content and therefore remains part of the normal source fingerprint.

The selected DIGIERA mode is not source content; it is execution policy and belongs in the idempotency key/durable job snapshot instead.

Changing DIGIERA mode after preflight requires a new preflight/job identity, not mutation of an existing queued job.

## 15. Test strategy — TDD gates

Implementation must proceed RED -> GREEN for each bounded slice.

### 15.1 Manifest tests

Prove:

- persisted DRAFT marker is included;
- ACTIVE marker is included;
- unreferenced DRAFT/ACTIVE row is excluded;
- unresolved stored marker fails backup;
- effective version number is captured for FOLLOW and PINNED;
- metadata fields survive manifest roundtrip;
- no local numeric ids/R2 location data enter manifest.

### 15.2 Adapter tests

Focused adapter tests for:

- Page content;
- Label intro;
- Book multiple chapters and old->new chapter mapping;
- generic intro activity;
- module without intro is safely ignored.

### 15.3 Shared remap tests

For both FOLLOW source and PINNED source:

- SHARED_FOLLOW always produces same Media + FOLLOW_CURRENT;
- SHARED_PINNED always uses same Media + exact effective version snapshot;
- missing version does not fall back to current.

### 15.4 Independent tests

Prove:

- one new Media/version per source Media/effective version per target publish operation;
- multiple references/activities reuse that one independent Media;
- R2 copy is one-time/idempotent;
- FOLLOW source copies current effective binary;
- PINNED source copies pinned effective binary;
- retry after copy-before-DB and DB-before-job-completion does not duplicate.

### 15.5 Real Moodle backup/restore integration

Use Moodle's real backup/restore controllers, not only fake repositories.

At minimum:

- Page;
- Label;
- Book with two chapters;
- one normal activity whose `intro` contains DIGIERA marker.

The default no-scope path must prove SHARED_FOLLOW. Programmatic integration tests may open an explicit clone-policy scope to exercise SHARED_PINNED and INDEPENDENT through the same real Moodle backup/restore controllers without adding a new Moodle Core restore UI setting.

Real restore acceptance verifies:

- source Reference UUID is not reused;
- target markers resolve;
- target context/course/cmid/entity/field are correct;
- source records are not mutated;
- correct mode semantics are observed.

### 15.6 Course Publisher code tests

Tests/harnesses must prove:

- selector values validate;
- default is `shared_follow`;
- direct publish job persists mode;
- Batch persists mode;
- child job inherits mode;
- idempotency differs by mode;
- optional bridge absence preserves existing Course Publisher behavior;
- bridge receives stable job operation id and selected mode;
- section/subsection paths propagate the mode.

## 16. Production browser/live acceptance matrix

After CI and two-node deployment, operator acceptance is required.

### 16.1 Moodle native restore

Using disposable targets and the normal Moodle backup/restore flow with no Course Publisher bridge scope:

- Page SHARED_FOLLOW PASS;
- Label SHARED_FOLLOW PASS;
- Book Chapter SHARED_FOLLOW PASS;
- generic intro SHARED_FOLLOW PASS.

Native Moodle restore has no new DIGIERA mode selector in this batch; its frozen default is SHARED_FOLLOW. Live SHARED_PINNED and INDEPENDENT acceptance is performed through Course Publisher, which is the operator-facing mode selector in scope.

### 16.2 Course Publisher single Activity

Publish a generic activity containing DIGIERA Media into a disposable target using:

- SHARED_FOLLOW;
- SHARED_PINNED;
- INDEPENDENT.

Verify placement and existing Course Publisher postconditions still pass.

For SHARED_PINNED, replace the source Media after publication and confirm the published target remains on the snapshotted version.

For INDEPENDENT, verify a real new R2 object exists and later replacement of the source Media does not change the published target Media.

### 16.3 Course Publisher normal Section

Publish a normal Section containing multiple supported editor-backed activities.

SHARED_FOLLOW is mandatory acceptance. At least one additional mode must be exercised on a disposable target.

All child markers must map and render.

### 16.4 Course Publisher Batch fan-out

Run a disposable multi-target Batch with SHARED_FOLLOW.

Verify every child job carries the same frozen mode and every target gets isolated new Reference UUIDs without leaking one target's References into another.

### 16.5 Course Publisher delegated subsection

Publish a `mod_subsection` whose delegated child activities include DIGIERA markers.

Verify Moodle restores the subsection tree and the local DIGIERA restore callbacks apply the same job mode to every included child activity.

## 17. Security invariants

The batch must preserve:

- no R2 secrets in browser, backup archive, DB job snapshots, logs, or manifests;
- no presigned DELETE;
- Independent copy is server-side only;
- no source R2 deletion during backup/restore/publish;
- capability enforcement remains with the existing Moodle/Course Publisher execution context;
- Course Publisher does not gain direct write access to DIGIERA internal tables;
- restore errors do not expose credentials/object signing material.

## 18. Deployment design

This batch changes two local plugins and must deploy them as one coordinated release under Moodle maintenance mode.

### 18.1 Source handling

The supplied Course Publisher 1.1.1 artifact is the frozen source baseline for this integration. Before product edits, its `coursepublisher/` tree is imported unchanged into the isolated branch at:

`public/local/coursepublisher/`

The import commit must record the source ZIP SHA256 `71e0b1334bf73c378a7c6986676069930188a99e55060af1baa3aa31794bdffb`.

After that baseline import, only integration-required modifications are allowed; unrelated Course Publisher refactors are out of scope.

### 18.2 Versioning

Both modified plugins require version bumps.

The implementation plan will pin exact version/release values before product commits are frozen. Version numbers must be monotonically greater than:

- `local_digieramedia = 2026091001`
- `local_coursepublisher = 2026082902`

### 18.3 Two-node deployment helper

One pinned helper must:

- preflight Web02 SSH and Moodle CLI on both nodes;
- verify R2 config readability without printing secrets;
- snapshot both `local/digieramedia` and `local/coursepublisher` on Web01/Web02;
- stop cron only if active;
- enable maintenance;
- stage frozen payloads on both nodes;
- verify staged identity;
- install both plugins;
- run Moodle upgrade as `www-data`;
- verify Course Publisher new schema fields and plugin versions;
- purge caches;
- reload PHP-FPM;
- verify two-node file parity for both plugins;
- disable maintenance and restore prior cron state.

Deploy helper performs `R2_MUTATION=NONE`.

Independent R2 copying occurs only during explicit post-deploy acceptance.

## 19. Rollback/recovery

Pre-deploy snapshots of both plugin trees are mandatory.

Course Publisher schema additions are additive fields with safe default `shared_follow`; older code may ignore them if file rollback is temporarily required.

No destructive DB rollback is attempted automatically.

If Moodle upgrade fails:

- remain in maintenance;
- do not run live acceptance;
- preserve logs/snapshots;
- restore code only according to verified recovery steps.

If post-deploy browser acceptance fails while site runtime is otherwise healthy, mark the batch `DEPLOYED / ACCEPTANCE FAILED`, keep the evidence, and fix forward from the isolated branch rather than silently declaring compatibility.

## 20. Explicit non-goals for this batch

Out of scope:

- arbitrary scanning of every text column in Moodle;
- rewriting Course Publisher routing/topology architecture;
- migration of legacy non-DIGIERA embedded URLs into markers;
- cross-site restore to a different Moodle installation with no matching logical Media catalog;
- multipart upload work;
- UI redesign of DIGIERA Modal A;
- a new native Moodle restore UI for choosing DIGIERA clone mode;
- new Course Publisher topology/discovery features.

## 21. Definition of Done

The batch is complete only when all of the following are true:

1. marker-driven portable manifest v2 is GREEN;
2. Page/Label/Book/generic-intro adapters are GREEN;
3. SHARED_FOLLOW real Moodle restore is GREEN;
4. SHARED_PINNED real-controller integration tests are GREEN;
5. INDEPENDENT real-controller/service path is GREEN and idempotent;
6. Course Publisher 1.1.1 integration policy is durably propagated through direct job, Batch and subsection paths;
7. full DIGIERA/Course Publisher CI is GREEN;
8. two-node pinned deploy helper PASSes;
9. Moodle native SHARED_FOLLOW browser acceptance PASSes;
10. Course Publisher single Activity PASSes in all three modes;
11. normal Section PASSes;
12. Batch fan-out PASSes;
13. delegated subsection PASSes;
14. real Independent smoke confirms copied R2 object while source remains unchanged;
15. a final versioned Spec Kit checkpoint records exact deployed product commits, versions, helper output and browser evidence.

Only then may this batch be labeled:

`BACKUP_RESTORE_PRODUCTION = PASS`

`COURSE_PUBLISHER_COMPATIBLE = PASS`

`DEPLOYED = YES`

`BROWSER_VERIFIED = YES`
