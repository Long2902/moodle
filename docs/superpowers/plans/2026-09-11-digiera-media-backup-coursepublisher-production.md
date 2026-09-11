# DIGIERA Media RC1 Backup/Restore + Course Publisher Production Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make DIGIERA Media production-safe across Moodle 5.1 Backup/Restore and the operator-supplied `local_coursepublisher` 1.1.1 workflow, including Shared+Follow, Shared+Pinned, and Independent clone semantics.

**Architecture:** `local_digieramedia` owns marker discovery, portable manifests, restore adapters, clone semantics, Reference activation, and R2 copy behavior. `local_coursepublisher` remains a Moodle-Core backup/restore orchestrator and only persists/propagates a clone policy through an optional bridge. Backup truth is persisted marker content, not Reference status or context-wide queries.

**Tech Stack:** Moodle 5.1 PHP 8.3, Moodle Backup/Restore API, PHPUnit/advanced_testcase, XMLDB, Cloudflare R2 SigV4 server-side copy, Moodle adhoc tasks, existing DIGIERA repositories/services.

**Spec:** `docs/superpowers/specs/2026-09-11-digiera-media-backup-coursepublisher-production-design.md`

## Global Constraints

- Work only on `feature/digiera-media-v1-rc1-backup-coursepublisher`; do not merge to `main` in this plan.
- Base checkpoint is `c22e0984ad24afdb052afd60e75c3119e0170277` (`Recent + Permissions` browser acceptance PASS).
- Course Publisher baseline is the operator-supplied `local_coursepublisher_moodle51_1.1.1-production(1).zip`, SHA256 `71e0b1334bf73c378a7c6986676069930188a99e55060af1baa3aa31794bdffb`, component version `2026082902`, release `1.1.1-production`.
- New Course Publisher product version for this feature batch is `2026091101`, release `1.2.0-production`.
- New `local_digieramedia` product version for this feature batch is `2026091101`, release `1.0.0-rc1-fasttrack-backup-coursepublisher`.
- Do not bump TinyMCE or filter plugin versions unless their runtime payload actually changes.
- New backups write portable manifest v2. They must not contain database-local Media/Version/Reference ids, R2 bucket/object keys, presigned URLs, or credentials.
- Normal Moodle restore with no Course Publisher bridge scope defaults to `shared_follow`.
- Course Publisher is an optional integration. It must continue to work when `local_digieramedia` is absent.
- Course Publisher must never directly mutate DIGIERA Media tables or call R2.
- `shared_follow` is the default Course Publisher mode; `shared_pinned` and `independent` are explicit operator choices.
- Backup is read-only. Shared restore does not mutate R2. Independent restore may only server-side COPY and HEAD-verify; it never deletes/mutates the source object.
- Source Media allowed at restore: `ACTIVE` or `TRASHED` with READY effective version. `PURGING` and `PURGED` are rejected.
- Restored References are DRAFT until rewritten Moodle content is persisted, then become ACTIVE.
- CI is a parallel safety lane; do not block every small implementation step on a full workflow run.

---

## File Structure Map

### DIGIERA Media

- `public/local/digieramedia/classes/backup/reference_manifest.php` — portable manifest v2 validation/serialization.
- `public/local/digieramedia/classes/backup/reference_collector.php` — parse persisted marker content and resolve manifest entries.
- `public/local/digieramedia/classes/backup/content_record.php` — immutable description of one source/target content field.
- `public/local/digieramedia/classes/backup/content_adapter_interface.php` — adapter contract.
- `public/local/digieramedia/classes/backup/content_adapter_registry.php` — Page/Label/Book/generic-intro adapter dispatch.
- `public/local/digieramedia/classes/backup/adapter/page_adapter.php` — `{page}.content`.
- `public/local/digieramedia/classes/backup/adapter/label_adapter.php` — `{label}.intro`.
- `public/local/digieramedia/classes/backup/adapter/book_adapter.php` — `{book_chapters}.content` and chapter mapping.
- `public/local/digieramedia/classes/backup/adapter/generic_intro_adapter.php` — safe module-instance `intro` field.
- `public/local/digieramedia/classes/restore/clone_policy_scope.php` — process-local clone mode + operation id + target course context.
- `public/local/digieramedia/classes/integration/coursepublisher_bridge.php` — optional bridge entrypoint used by Course Publisher.
- `public/local/digieramedia/classes/restore/reference_remapper.php` — remap from manifest data, not live source Reference row.
- `public/local/digieramedia/classes/restore/independent_copy_service.php` — operation/target-scoped deterministic independent copies.
- `public/local/digieramedia/backup/moodle2/backup_local_digieramedia_plugin.class.php` — emit marker-driven manifest v2 with `set_source_array()`.
- `public/local/digieramedia/backup/moodle2/restore_local_digieramedia_plugin.class.php` — adapter-driven restore for all supported content types/modes.
- `public/local/digieramedia/tests/*` — focused manifest/adapter/remap/independent/real-backup integration tests.

### Course Publisher

- `public/local/coursepublisher/**` — exact tracked import of supplied 1.1.1 baseline before product edits.
- `public/local/coursepublisher/classes/local/digiera_integration.php` — optional wrapper that calls DIGIERA bridge only when installed.
- `public/local/coursepublisher/classes/form/batch_form.php` — Batch clone-policy selector.
- `public/local/coursepublisher/publish.php` — single-publish clone-policy selector + POST propagation.
- `public/local/coursepublisher/batch.php` — Batch request/hidden field propagation.
- `public/local/coursepublisher/classes/local/job_service.php` — job schema value, snapshots, idempotency, worker operation id.
- `public/local/coursepublisher/classes/local/batch_service.php` — Batch schema value and child inheritance.
- `public/local/coursepublisher/classes/local/content_publisher.php` — existing Core backup/restore remains unchanged except optional bridge wrapping at call boundaries.
- `public/local/coursepublisher/db/install.xml` / `db/upgrade.php` — `digieramode` fields.
- `public/local/coursepublisher/lang/{vi,en}/local_coursepublisher.php` — selector labels/help/status text.
- `public/local/coursepublisher/version.php` — `2026091101`, `1.2.0-production`.
- `public/local/coursepublisher/tests/*` — durable-policy, idempotency, bridge and integration tests.

---

### Task 1: Track the exact Course Publisher 1.1.1 baseline and add durable DIGIERA mode schema

**Files:**
- Create/import: `public/local/coursepublisher/**` from the supplied archive, unmodified first.
- Modify: `public/local/coursepublisher/db/install.xml`
- Modify: `public/local/coursepublisher/db/upgrade.php`
- Modify: `public/local/coursepublisher/version.php`
- Create: `public/local/coursepublisher/tests/digiera_mode_schema_test.php`

**Interfaces:**
- Consumes: supplied Course Publisher 1.1.1 archive.
- Produces: `local_cp_job.digieramode` and `local_cp_batch.digieramode`, both `char(32) NOT NULL DEFAULT 'shared_follow'`.

- [ ] **Step 1: Import the supplied baseline without product edits**

Extract the archive and copy the top-level `coursepublisher/` directory to `public/local/coursepublisher/`. Verify before committing:

```bash
sha256sum 'local_coursepublisher_moodle51_1.1.1-production(1).zip'
# Expected:
# 71e0b1334bf73c378a7c6986676069930188a99e55060af1baa3aa31794bdffb

grep -E "plugin->version|plugin->release" public/local/coursepublisher/version.php
# Expected baseline: 2026082902 / 1.1.1-production
```

Commit the exact baseline separately:

```bash
git add public/local/coursepublisher
git commit -m "chore: track Course Publisher 1.1.1 production baseline"
```

- [ ] **Step 2: Write the failing schema test**

Create `digiera_mode_schema_test.php`:

```php
<?php
namespace local_coursepublisher;

final class digiera_mode_schema_test extends \advanced_testcase {
    public function test_job_and_batch_store_digiera_mode(): void {
        global $DB;
        $dbman = $DB->get_manager();
        foreach (['local_cp_job', 'local_cp_batch'] as $tablename) {
            $table = new \xmldb_table($tablename);
            $field = new \xmldb_field('digieramode');
            $this->assertTrue($dbman->field_exists($table, $field));
        }
    }
}
```

- [ ] **Step 3: Run RED**

Run:

```bash
cd public
vendor/bin/phpunit local/coursepublisher/tests/digiera_mode_schema_test.php
```

Expected: FAIL because `digieramode` does not exist.

- [ ] **Step 4: Add XMLDB + upgrade path and version bump**

Add the same field to both tables in `install.xml`:

```xml
<FIELD NAME="digieramode" TYPE="char" LENGTH="32" NOTNULL="true" DEFAULT="shared_follow" />
```

Add upgrade block before savepoint `2026091101`:

```php
if ($oldversion < 2026091101) {
    foreach (['local_cp_job', 'local_cp_batch'] as $tablename) {
        $table = new xmldb_table($tablename);
        $field = new xmldb_field('digieramode', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, 'shared_follow');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
    }
    upgrade_plugin_savepoint(true, 2026091101, 'local', 'coursepublisher');
}
```

Update `version.php`:

```php
$plugin->version = 2026091101;
$plugin->release = '1.2.0-production';
```

- [ ] **Step 5: Run GREEN + XML parse**

```bash
cd public
vendor/bin/phpunit local/coursepublisher/tests/digiera_mode_schema_test.php
php -r '$x=simplexml_load_file("local/coursepublisher/db/install.xml"); if(!$x){exit(1);} echo "XML=PASS\n";'
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add public/local/coursepublisher
git commit -m "feat: add durable DIGIERA clone mode to Course Publisher"
```

---

### Task 2: Replace manifest v1 with marker-driven portable manifest v2

**Files:**
- Modify: `public/local/digieramedia/classes/backup/reference_manifest.php`
- Modify: `public/local/digieramedia/classes/backup/reference_collector.php`
- Create: `public/local/digieramedia/classes/backup/content_record.php`
- Modify: `public/local/digieramedia/tests/backup_manifest_test.php`
- Modify: `public/local/digieramedia/version.php`

**Interfaces:**
- Produces: `reference_collector::collect_content(content_record $record): array`.
- Produces manifest fields: `source_reference_uuid`, `media_uuid`, `displayprofile`, `source_versionmode`, `effective_version_no`, `alttext`, `caption`, `optionsjson`, `adapter`, `source_entity_id`, `fieldname`, `occurrence`.
- Later tasks consume these exact keys.

- [ ] **Step 1: Write RED tests for v2 semantics**

Add tests proving:

```php
public function test_draft_marker_is_exported_but_unreferenced_rows_are_not(): void;
public function test_follow_reference_exports_effective_version_no(): void;
public function test_pinned_reference_exports_effective_version_no(): void;
public function test_unresolved_persisted_marker_fails_closed(): void;
public function test_manifest_never_exports_local_ids_or_r2_location(): void;
public function test_metadata_and_occurrence_survive_collection(): void;
```

Example assertion:

```php
$this->assertSame([
    'source_reference_uuid', 'media_uuid', 'displayprofile', 'source_versionmode',
    'effective_version_no', 'alttext', 'caption', 'optionsjson', 'adapter',
    'source_entity_id', 'fieldname', 'occurrence',
], array_keys($result[0]));
$this->assertArrayNotHasKey('bucket', $result[0]);
$this->assertArrayNotHasKey('objectkey', $result[0]);
$this->assertArrayNotHasKey('mediaid', $result[0]);
$this->assertArrayNotHasKey('versionid', $result[0]);
```

- [ ] **Step 2: Run RED**

```bash
cd public
vendor/bin/phpunit local/digieramedia/tests/backup_manifest_test.php
```

Expected: FAIL on missing v2 keys/marker-driven API.

- [ ] **Step 3: Add `content_record`**

Use one immutable DTO:

```php
final class content_record {
    public function __construct(
        public readonly string $adapter,
        public readonly int $sourceentityid,
        public readonly string $fieldname,
        public readonly string $content,
    ) {}
}
```

- [ ] **Step 4: Implement collector + v2 manifest**

`reference_collector::collect_content()` parses markers from `$record->content`; for each occurrence it resolves Reference → Media → effective Version and calls:

```php
reference_manifest::from_reference(
    $reference,
    $media,
    $version,
    $record->adapter,
    $record->sourceentityid,
    $record->fieldname,
    $occurrence
);
```

`reference_manifest` accepts DRAFT or ACTIVE References if the marker is persisted; it validates Media/Version ownership and READY effective version.

- [ ] **Step 5: Bump DIGIERA local version**

Update:

```php
$plugin->version = 2026091101;
$plugin->release = '1.0.0-rc1-fasttrack-backup-coursepublisher';
```

Do not bump Tiny/filter here.

- [ ] **Step 6: Run GREEN**

```bash
cd public
vendor/bin/phpunit local/digieramedia/tests/backup_manifest_test.php
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add public/local/digieramedia
git commit -m "feat: add marker-driven DIGIERA backup manifest v2"
```

---

### Task 3: Add Page/Label/Book/generic-intro content adapter registry and marker-driven backup source

**Files:**
- Create: `public/local/digieramedia/classes/backup/content_adapter_interface.php`
- Create: `public/local/digieramedia/classes/backup/content_adapter_registry.php`
- Create: `public/local/digieramedia/classes/backup/adapter/page_adapter.php`
- Create: `public/local/digieramedia/classes/backup/adapter/label_adapter.php`
- Create: `public/local/digieramedia/classes/backup/adapter/book_adapter.php`
- Create: `public/local/digieramedia/classes/backup/adapter/generic_intro_adapter.php`
- Modify: `public/local/digieramedia/backup/moodle2/backup_local_digieramedia_plugin.class.php`
- Create: `public/local/digieramedia/tests/content_adapter_test.php`

**Interfaces:**
- `content_adapter_interface::supports(string $modname): bool`
- `content_adapter_interface::source_records(int $instanceid): array`
- `content_adapter_interface::target_record(int $instanceid, int $sourceentityid, restore_local_plugin $plugin): ?content_record`
- `content_adapter_interface::persist_target(content_record $record, string $rewritten): void`
- `content_adapter_registry::for_module(string $modname): content_adapter_interface`

- [ ] **Step 1: Write adapter RED tests**

Cover:

```php
public function test_page_adapter_reads_content(): void;
public function test_label_adapter_reads_intro(): void;
public function test_book_adapter_returns_each_chapter_in_order(): void;
public function test_generic_intro_adapter_reads_real_intro_column(): void;
public function test_generic_intro_adapter_ignores_module_without_intro(): void;
```

For Book, create two chapters and assert two `content_record`s with source chapter ids.

- [ ] **Step 2: Run RED**

```bash
cd public
vendor/bin/phpunit local/digieramedia/tests/content_adapter_test.php
```

Expected: FAIL because adapter classes do not exist.

- [ ] **Step 3: Implement focused adapters**

Registry selection order must be:

```php
page -> page_adapter
label -> label_adapter
book -> book_adapter
subsection -> unsupported/no content adapter
default -> generic_intro_adapter when the module instance table has `intro`
```

Do not scan arbitrary columns.

- [ ] **Step 4: Change backup plugin to emit marker-derived rows**

In `define_module_plugin_structure()`, obtain current module name/instance id from the backup task, collect adapter records, pass each through `reference_collector`, flatten manifest rows, then:

```php
$reference->set_source_array($rows);
```

Extend the nested element field list to v2 fields. No `WHERE r.status = 'ACTIVE'` SQL remains.

- [ ] **Step 5: Run GREEN + existing manifest regression**

```bash
cd public
vendor/bin/phpunit local/digieramedia/tests/content_adapter_test.php
vendor/bin/phpunit local/digieramedia/tests/backup_manifest_test.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add public/local/digieramedia
git commit -m "feat: add DIGIERA backup content adapters"
```

---

### Task 4: Restore shared modes from manifest data and activate References only after content persistence

**Files:**
- Create: `public/local/digieramedia/classes/restore/clone_policy_scope.php`
- Create: `public/local/digieramedia/classes/integration/coursepublisher_bridge.php`
- Modify: `public/local/digieramedia/classes/restore/reference_remapper.php`
- Modify: `public/local/digieramedia/backup/moodle2/restore_local_digieramedia_plugin.class.php`
- Create: `public/local/digieramedia/tests/shared_restore_manifest_test.php`
- Expand: `public/local/digieramedia/tests/course_restore_integration_test.php`

**Interfaces:**
- `clone_policy_scope::current_mode(): string` returns `shared_follow` when no scope exists.
- `clone_policy_scope::current_operation_id(): string` returns a stable generated restore id outside Course Publisher.
- `coursepublisher_bridge::run(string $mode, string $operationid, int $targetcourseid, callable $callback): mixed`.
- `reference_remapper::remap_manifest_content(string $content, array $manifests, context $targetcontext, string $mode, string $operationid, int $userid): array`.

- [ ] **Step 1: Write RED tests for scope and manifest-only shared restore**

Tests must prove restore succeeds after the original Reference row is deleted after backup creation, because manifest v2 is sufficient.

Add exact semantics:

```php
// shared_follow
$this->assertSame($sourceMediaId, $target->mediaid);
$this->assertSame('FOLLOW_CURRENT', $target->versionmode);
$this->assertSame(0, (int)$target->pinnedversionid);

// shared_pinned
$this->assertSame($sourceMediaId, $target->mediaid);
$this->assertSame('PINNED_VERSION', $target->versionmode);
$this->assertSame($snapshottedVersionId, (int)$target->pinnedversionid);
```

Also assert missing `effective_version_no`/non-READY version fails instead of falling back.

- [ ] **Step 2: Run RED**

```bash
cd public
vendor/bin/phpunit local/digieramedia/tests/shared_restore_manifest_test.php
```

Expected: FAIL because restore still reads live source Reference and is Page-only/SHARED_FOLLOW-only.

- [ ] **Step 3: Implement process-local policy scope**

Use a stack so nested operations restore previous state in `finally`:

```php
public static function run(string $mode, string $operationid, int $targetcourseid, callable $callback): mixed {
    clone_mode::assert_valid($mode);
    self::$stack[] = compact('mode', 'operationid', 'targetcourseid');
    try {
        return $callback();
    } finally {
        array_pop(self::$stack);
    }
}
```

Map Course Publisher strings `shared_follow`, `shared_pinned`, `independent` to the existing clone mode constants.

- [ ] **Step 4: Refactor remapper to consume manifest v2**

Do not call `reference_repository::get_by_uuid($sourceuuid)` for restore semantics. Resolve Media by `media_uuid`, resolve Version by `(mediaid, effective_version_no)`, validate Media state and READY Version, then create target DRAFT Reference with manifest metadata.

- [ ] **Step 5: Refactor restore plugin to adapter registry**

`process_*` stores manifests grouped by `adapter + source_entity_id + fieldname`.

`after_restore_module()`:

1. resolves adapter for restored module;
2. finds target records, using restore mappings for Book chapter old-id → new-id;
3. remaps markers under `clone_policy_scope::current_mode()`;
4. persists rewritten content;
5. updates target Reference metadata;
6. marks References ACTIVE only after successful persistence.

- [ ] **Step 6: Expand real Moodle restore tests**

Add real backup/restore tests for Page + Label first, then Book + one generic intro activity (use Assignment if available in the test environment).

- [ ] **Step 7: Run GREEN**

```bash
cd public
vendor/bin/phpunit local/digieramedia/tests/shared_restore_manifest_test.php
vendor/bin/phpunit local/digieramedia/tests/course_restore_integration_test.php
```

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add public/local/digieramedia
git commit -m "feat: restore DIGIERA references through portable manifests"
```

---

### Task 5: Wire operation-scoped Independent copy through real restore

**Files:**
- Modify: `public/local/digieramedia/classes/restore/independent_copy_service.php`
- Modify: `public/local/digieramedia/classes/restore/reference_remapper.php`
- Modify: `public/local/digieramedia/backup/moodle2/restore_local_digieramedia_plugin.class.php`
- Modify: `public/local/digieramedia/tests/independent_copy_test.php`
- Create: `public/local/digieramedia/tests/independent_restore_integration_test.php`

**Interfaces:**
- `independent_copy_service::copy_manifest(array $manifest, context $targetcontext, string $operationid, int $targetcourseid, int $userid): array`.
- Deterministic Media key material: `operationid|targetcourseid|media_uuid|effective_version_no`.
- Target Reference identity additionally includes source Reference UUID + occurrence + target content identity.

- [ ] **Step 1: Write RED tests for cross-activity reuse and cross-job separation**

Prove:

```php
// Same job/target/source media+version across two References.
$this->assertSame($first['media']->uuid, $second['media']->uuid);
$this->assertCount(1, $r2->copies);

// Different target course or operation id.
$this->assertNotSame($first['media']->uuid, $other['media']->uuid);
```

Also test R2-copy-completed/DB-failed retry: pre-existing target object is HEAD-verified and not copied again.

- [ ] **Step 2: Run RED**

```bash
cd public
vendor/bin/phpunit local/digieramedia/tests/independent_copy_test.php
```

Expected: FAIL because current identity includes target context/source DB records rather than manifest operation scope.

- [ ] **Step 3: Implement operation-scoped deterministic identity**

Change target Media UUID and R2 key derivation to use stable operation + target course + portable source identity/effective version.

Target object key remains server-owned, e.g.:

```text
digiera/restores/<targetcourseid>/<target-media-uuid>/v1.<ext>
```

- [ ] **Step 4: Wire Independent into manifest remapper**

For `independent`, call `copy_manifest()` once per unique mapping key and create distinct target References that reuse the resulting independent Media/Version.

Preserve source Reference intent:

```text
FOLLOW_CURRENT source -> independent target FOLLOW_CURRENT
PINNED_VERSION source -> independent target PINNED_VERSION pinned to copied v1
```

- [ ] **Step 5: Add real restore integration test with fake R2 client seam**

The integration test must prove target Moodle content contains new Reference UUID, target Media UUID differs, Version v1 exists, and exactly one copy occurs for repeated same Media in one operation.

- [ ] **Step 6: Run GREEN**

```bash
cd public
vendor/bin/phpunit local/digieramedia/tests/independent_copy_test.php
vendor/bin/phpunit local/digieramedia/tests/independent_restore_integration_test.php
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add public/local/digieramedia
git commit -m "feat: wire independent DIGIERA restore copies"
```

---

### Task 6: Add Course Publisher clone-policy UI, snapshots, idempotency and optional bridge wrapper

**Files:**
- Create: `public/local/coursepublisher/classes/local/digiera_integration.php`
- Modify: `public/local/coursepublisher/classes/form/batch_form.php`
- Modify: `public/local/coursepublisher/publish.php`
- Modify: `public/local/coursepublisher/batch.php`
- Modify: `public/local/coursepublisher/classes/local/job_service.php`
- Modify: `public/local/coursepublisher/classes/local/batch_service.php`
- Modify: `public/local/coursepublisher/lang/vi/local_coursepublisher.php`
- Modify: `public/local/coursepublisher/lang/en/local_coursepublisher.php`
- Create: `public/local/coursepublisher/tests/digiera_mode_service_test.php`

**Interfaces:**
- Course Publisher values are exactly `shared_follow`, `shared_pinned`, `independent`.
- `digiera_integration::normalise(string $mode): string`.
- `digiera_integration::run(string $mode, string $operationid, int $targetcourseid, callable $callback): mixed`.
- If `\local_digieramedia\integration\coursepublisher_bridge` does not exist, `run()` calls `$callback()` unchanged.

- [ ] **Step 1: Write RED service/idempotency tests**

Prove:

```php
$this->assertSame('shared_follow', $job->digieramode);
$this->assertSame('shared_pinned', $batch->digieramode);
$this->assertNotSame(
    $jobFollow->idempotencykey,
    $jobPinned->idempotencykey
);
$this->assertSame('independent', json_decode($jobIndependent->preflightjson, true)['digieramode']);
```

For Batch child jobs, assert child `digieramode` equals parent Batch and appears in child snapshot/idempotency material.

- [ ] **Step 2: Run RED**

```bash
cd public
vendor/bin/phpunit local/coursepublisher/tests/digiera_mode_service_test.php
```

Expected: FAIL because mode is not accepted/stored.

- [ ] **Step 3: Implement optional integration wrapper**

```php
final class digiera_integration {
    public const SHARED_FOLLOW = 'shared_follow';
    public const SHARED_PINNED = 'shared_pinned';
    public const INDEPENDENT = 'independent';

    public static function run(string $mode, string $operationid, int $targetcourseid, callable $callback): mixed {
        $mode = self::normalise($mode);
        $bridge = '\\local_digieramedia\\integration\\coursepublisher_bridge';
        if (!class_exists($bridge)) {
            return $callback();
        }
        return $bridge::run($mode, $operationid, $targetcourseid, $callback);
    }
}
```

- [ ] **Step 4: Add Vietnamese-first UI selector**

Batch form adds `digieramode` select. `publish.php` renders the same selector on confirmation form and reads it with `PARAM_ALPHANUMEXT`.

VI labels:

```text
Xử lý học liệu DIGIERA
Dùng chung + Theo phiên bản hiện tại
Dùng chung + Ghim phiên bản hiện tại
Tạo bản sao học liệu độc lập
```

EN equivalents must maintain language-key parity.

If DIGIERA is absent, hide the visible selector and submit `shared_follow` as the safe default.

- [ ] **Step 5: Persist mode in jobs/batches/snapshots/idempotency**

Extend:

```php
job_service::create_or_reuse(..., array $placement = [], string $digieramode = 'shared_follow')
```

Normalize mode before hashing. Include it in `idempotency_key()`, record, preflight snapshot, logs.

`batch_service::preflight()` carries mode; `create_from_preflight()` writes it; `create_for_batch_target()` copies it into child job and child idempotency material.

- [ ] **Step 6: Run GREEN + language parity**

```bash
cd public
vendor/bin/phpunit local/coursepublisher/tests/digiera_mode_service_test.php
php -r '
$en=require_string_manager()->load_component_strings("local_coursepublisher", "en");
$vi=require_string_manager()->load_component_strings("local_coursepublisher", "vi");
$missing=array_diff_key($en,$vi)+array_diff_key($vi,$en);
if($missing){var_export(array_keys($missing)); exit(1);} echo "LANG_PARITY=PASS\n";
'
```

If direct CLI string-manager bootstrap is awkward, use the existing Course Publisher language parity harness pattern instead of ad-hoc parsing.

- [ ] **Step 7: Commit**

```bash
git add public/local/coursepublisher
git commit -m "feat: add DIGIERA clone policy to Course Publisher"
```

---

### Task 7: Wrap Course Publisher real-copy boundaries with the DIGIERA bridge

**Files:**
- Modify: `public/local/coursepublisher/classes/local/job_service.php`
- Modify: `public/local/coursepublisher/classes/local/content_publisher.php` only if a small callback seam is required.
- Create: `public/local/coursepublisher/tests/digiera_bridge_test.php`
- Expand: `public/local/coursepublisher/tests/batch_service_test.php`

**Interfaces:**
- Stable operation id: `coursepublisher-job:<jobid>:attempt:<attemptno>` must remain the same for retry of the same durable child attempt; if existing retry semantics reuse the same attempt number, use job request id instead: `coursepublisher-job:<jobid>:<requestid>`. Choose one stable representation and test it.
- Every real Core backup/restore call for a DIGIERA-aware job runs inside `digiera_integration::run($job->digieramode, $operationid, $job->targetcourseid, $callback)`.

- [ ] **Step 1: Write RED bridge-boundary tests**

Inject/spy the bridge wrapper seam and assert it is invoked for:

```text
MODE_ACTIVITY_GENERIC_PLACEMENT
MODE_SECTION_GENERIC_PEER (each restored child uses same operation id)
MODE_SUBSECTION_TREE_PLACEMENT
Batch child job through the same worker path
```

Also assert absent DIGIERA bridge still executes the Core callback exactly once.

- [ ] **Step 2: Run RED**

```bash
cd public
vendor/bin/phpunit local/coursepublisher/tests/digiera_bridge_test.php
```

Expected: FAIL because worker currently calls `content_publisher` directly.

- [ ] **Step 3: Add one worker helper, not duplicated wrappers**

Add to `job_service`:

```php
private static function run_with_digiera_policy(\stdClass $job, callable $callback): mixed {
    $operationid = 'coursepublisher-job:' . (int)$job->id . ':' . (string)$job->requestid;
    return digiera_integration::run(
        (string)$job->digieramode,
        $operationid,
        (int)$job->targetcourseid,
        $callback
    );
}
```

Wrap only the existing mutation callback; do not replace Course Publisher placement, locks, manifest, route checks or recovery logic.

For peer Section, all child Core restores from the same job use the same operation id so Independent mode deduplicates the same source Media/version across activities.

- [ ] **Step 4: Keep the Core publisher API intact**

Preferred call shape:

```php
$copy = self::run_with_digiera_policy($job, fn() => content_publisher::copy_generic_activity(...));
```

For delegated subsection:

```php
$copy = self::run_with_digiera_policy($job, fn() => content_publisher::copy_delegated_subsection(...));
```

Do not add DIGIERA-specific R2/DB code to `content_publisher.php`.

- [ ] **Step 5: Run GREEN and Course Publisher regressions**

```bash
cd public
vendor/bin/phpunit local/coursepublisher/tests/digiera_bridge_test.php
vendor/bin/phpunit local/coursepublisher/tests/batch_service_test.php
vendor/bin/phpunit local/coursepublisher/tests/dispatcher_test.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add public/local/coursepublisher
git commit -m "feat: bridge Course Publisher restore to DIGIERA clone policy"
```

---

### Task 8: Full real-backup integration matrix, RC1 contracts, package and two-node deploy helper

**Files:**
- Expand/create: `public/local/digieramedia/tests/course_restore_integration_test.php`
- Create: `public/local/digieramedia/tests/coursepublisher_restore_integration_test.php`
- Create/update: `.digiera/contracts/digiera-media-backup-coursepublisher-contract.php`
- Modify: `.github/workflows/digiera-media-rc1.yml`
- Create: `.digiera/tools/digiera-media-rc1-backup-coursepublisher-deploy.sh`
- Create: `docs/superpowers/specs/2026-09-11-digiera-media-backup-coursepublisher-deploy-ready.md`

**Interfaces:**
- Final deploy pins both product trees: `local_digieramedia@<product-commit>` and `local_coursepublisher@<product-commit>`.
- Helper terminal contract:

```text
DIGIERA_BACKUP_COURSEPUBLISHER_DEPLOY=PASS
DIGIERA_LOCAL_VERSION=2026091101
COURSEPUBLISHER_VERSION=2026091101
TWO_NODE_FILE_PARITY=PASS
R2_DEPLOY_MUTATION=NONE
```

- [ ] **Step 1: Add real Moodle integration matrix**

Use Moodle's real backup/restore controllers, not service-only mocks, and prove:

```text
Page: shared_follow + shared_pinned
Label/Text&Media: shared_follow + shared_pinned
Book with >=2 chapters: shared_follow + shared_pinned
Generic intro activity: shared_follow + shared_pinned
Independent: disposable Page with repeated same Media -> one target Media/R2 copy per operation
```

For every restored content item assert:

```text
source Reference UUID absent from target content
target Reference UUID exists
context/course/cmid/entity/field target metadata correct
renderer-selectable Media/Version state valid
source records unchanged
```

- [ ] **Step 2: Add Course Publisher integration test using its real worker/service path**

Create a configured source/target route fixture and exercise at least generic Activity through `job_service` with each DIGIERA mode. Section/Batch/subsection can remain focused Course Publisher service tests in CI if the full Moodle fixture becomes prohibitively slow, but live acceptance below remains mandatory for all four paths.

- [ ] **Step 3: Run focused suite**

```bash
cd public
vendor/bin/phpunit local/digieramedia/tests/backup_manifest_test.php
vendor/bin/phpunit local/digieramedia/tests/content_adapter_test.php
vendor/bin/phpunit local/digieramedia/tests/shared_restore_manifest_test.php
vendor/bin/phpunit local/digieramedia/tests/independent_copy_test.php
vendor/bin/phpunit local/digieramedia/tests/course_restore_integration_test.php
vendor/bin/phpunit local/digieramedia/tests/coursepublisher_restore_integration_test.php
vendor/bin/phpunit local/coursepublisher/tests/digiera_mode_schema_test.php
vendor/bin/phpunit local/coursepublisher/tests/digiera_mode_service_test.php
vendor/bin/phpunit local/coursepublisher/tests/digiera_bridge_test.php
```

Expected: all PASS.

- [ ] **Step 4: Extend RC1 source/runtime contract**

Contract must reject:

```text
old manifest-only pinned_version_no output
context-wide ACTIVE-only backup query
restore hard-code `mod_page` only
Course Publisher job/batch schema without digieramode
Course Publisher idempotency that omits digieramode
direct Course Publisher references to local_digieramedia DB tables or R2 client
browser-side R2 copy/delete
```

Contract must require:

```text
marker-driven manifest v2
adapter registry
optional bridge class
shared_follow/shared_pinned/independent values
both version numbers == 2026091101
deploy helper bash -n PASS
```

- [ ] **Step 5: Run full RC1 CI**

Trigger the existing `DIGIERA Media RC1 fast-track` workflow. Required evidence:

```text
source contracts PASS
PHP syntax PASS
JS syntax/build PASS
full DIGIERA runtime PHPUnit suite PASS
Course Publisher focused PHPUnit/harness suite PASS
reproducible package PASS
artifact upload PASS
```

Do not call the batch deploy-ready until the fresh workflow is completed with conclusion `success`.

- [ ] **Step 6: Create the two-node helper**

Helper must:

1. preflight Web02 SSH + Moodle CLI on both nodes;
2. verify `/etc/digiera/r2.php` readable by Moodle user without printing secrets;
3. download frozen product files only;
4. snapshot both `local/digieramedia` and `local/coursepublisher` on Web01/Web02;
5. stop cron if active and enable maintenance;
6. install both plugin trees on both nodes;
7. run `admin/cli/upgrade.php` as `www-data`;
8. verify versions/schema/bridge classes;
9. purge cache + reload PHP-FPM;
10. verify two-node SHA parity for both plugins;
11. disable maintenance and restore prior cron state in trap/finalization;
12. perform **no R2 copy/delete during deploy**.

- [ ] **Step 7: Run helper syntax and non-destructive source contract**

```bash
bash -n .digiera/tools/digiera-media-rc1-backup-coursepublisher-deploy.sh
php .digiera/contracts/digiera-media-backup-coursepublisher-contract.php
```

Expected: PASS.

- [ ] **Step 8: Commit deploy-ready checkpoint**

Record exact product pin(s), workflow run/job/artifact ids, checksums, plugin versions and remaining live acceptance steps.

```bash
git add .digiera .github docs public/local/digieramedia public/local/coursepublisher
git commit -m "ops: prepare DIGIERA Course Publisher production acceptance"
```

- [ ] **Step 9: Deploy Web01/Web02 and perform live acceptance**

Live acceptance is mandatory before `PRODUCTION_ACCEPTANCE=PASS`:

```text
1. Moodle normal backup/restore: Page/Label/Book/generic-intro with shared_follow.
2. Course Publisher single generic Activity with shared_follow.
3. Course Publisher single Activity with shared_pinned; replace source Media afterwards and prove clone stays pinned.
4. Course Publisher Independent disposable Activity; prove new Media/Version/Reference and real R2 object copy.
5. Course Publisher normal peer Section containing repeated same Media; Independent creates one Media copy per target/job.
6. Batch fan-out to >=2 disposable targets; each target gets its own independent Media identity.
7. mod_subsection tree containing DIGIERA child content; markers and target metadata restore correctly.
8. Verify non-DIGIERA publish behavior is unchanged.
9. Verify source Media/References/R2 objects are unchanged by all Shared modes and never deleted by Independent.
```

Only after this evidence may the final checkpoint say:

```text
MOODLE_BACKUP_RESTORE_PRODUCTION=PASS
COURSEPUBLISHER_SINGLE=PASS
COURSEPUBLISHER_SECTION=PASS
COURSEPUBLISHER_BATCH=PASS
COURSEPUBLISHER_SUBSECTION=PASS
DIGIERA_SHARED_FOLLOW=PASS
DIGIERA_SHARED_PINNED=PASS
DIGIERA_INDEPENDENT=PASS
PRODUCTION_ACCEPTANCE=PASS
```

---

## Self-Review Result

- Spec coverage: all architecture, manifest v2, adapters, three clone modes, Course Publisher durable propagation/idempotency, optional bridge, retry/safety, two-node deployment and live acceptance requirements map to Tasks 1-8.
- Placeholder scan: no TBD/TODO/"implement later" steps remain.
- Type consistency: Course Publisher strings are always `shared_follow`, `shared_pinned`, `independent`; DIGIERA bridge maps them to existing clone-mode constants. `digieramode` is the durable DB/job/batch field everywhere.
- Scope: one integrated production-acceptance batch is justified because Course Publisher policy propagation is not useful without the DIGIERA restore semantics it selects, and the acceptance criterion is end-to-end compatibility between these two components.
