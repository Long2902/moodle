# DIGIERA Media True Recent + Permission Matrix Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `Đã dùng gần đây` a real per-user media-use history and lock the RC1 capability matrix with automated backend acceptance tests.

**Architecture:** Add a dedicated `local_digieramedia_recent` table and a narrow `recent_service::touch()` writer. Existing TinyMCE Recent UI continues calling `search_media(tab=recent)`; only the backend query changes. Authorization stays capability-based and is verified through role/capability PHPUnit tests rather than hard-coded role names.

**Tech Stack:** Moodle 5.1 / PHP 8.3, XMLDB, Moodle external API, PHPUnit, GitHub Actions, two-node Web01/Web02 deployment helper.

**Spec:** `docs/superpowers/specs/2026-09-10-digiera-media-recent-permissions-design.md`

## Global Constraints

- Work only on `feature/digiera-media-v1-rc1-recent-permissions`; do not merge to `main`.
- `local_digieramedia = 2026091001` for this batch.
- Do not bump Tiny/filter unless their runtime files are modified.
- Recent is per-user even for users with `local/digieramedia:viewall`.
- Upload/select/preview does not touch Recent; successful create/update reference does.
- Trash preserves Recent rows but excludes non-ACTIVE media from Recent results; Restore makes preserved rows visible again.
- Successful purge removes Recent rows for the media.
- KTV is a custom capability bundle; no PHP/JS role-name checks.
- `local/digieramedia:purge` stays opt-in outside site-admin implicit access.
- UI hiding is not authorization; backend capability rejection must be tested.
- CI may run in parallel; deploy readiness requires fresh successful RC1 verification for frozen product/helper revisions.

---

### Task 1: Recent schema and writer service

**Files:**
- Modify: `public/local/digieramedia/db/install.xml`
- Modify: `public/local/digieramedia/db/upgrade.php`
- Modify: `public/local/digieramedia/version.php`
- Create: `public/local/digieramedia/classes/service/recent_service.php`
- Create: `public/local/digieramedia/tests/recent_service_test.php`

**Interfaces:**
- Produces: `recent_service::touch(int $userid, int $mediaid, int $contextid, string $action, ?int $usedat = null): void`
- Produces table: `local_digieramedia_recent(userid, mediaid, contextid, lastusedat, usecount, lastaction)` with unique `(userid, mediaid)` and index `(userid, lastusedat)`.

- [ ] **Step 1: Write failing PHPUnit tests**

Add tests proving first touch creates one row, second touch for the same user/media updates that row, increments `usecount`, changes context/action/time, and a second user gets an independent row.

```php
$service = new \local_digieramedia\service\recent_service();
$service->touch($usera->id, $mediaid, $context->id, 'CREATE_REFERENCE', 1000);
$service->touch($usera->id, $mediaid, $context->id, 'UPDATE_REFERENCE', 2000);
$this->assertEquals(1, $DB->count_records('local_digieramedia_recent', ['userid' => $usera->id, 'mediaid' => $mediaid]));
$row = $DB->get_record('local_digieramedia_recent', ['userid' => $usera->id, 'mediaid' => $mediaid], '*', MUST_EXIST);
$this->assertSame(2, (int)$row->usecount);
$this->assertSame(2000, (int)$row->lastusedat);
$this->assertSame('UPDATE_REFERENCE', (string)$row->lastaction);
```

- [ ] **Step 2: Run the focused test and capture RED**

Run through the RC1 PHPUnit environment:

```bash
vendor/bin/phpunit public/local/digieramedia/tests/recent_service_test.php
```

Expected before implementation: FAIL because `recent_service`/table does not exist.

- [ ] **Step 3: Implement schema + upgrade + service**

`install.xml` table fields:

```text
id bigint/int sequence primary
userid int not null
mediaid int not null FK local_digieramedia_media.id
contextid int not null default 0
lastusedat int not null default 0
usecount int not null default 0
lastaction char(32) not null
```

Indexes:

```text
unique user-media(userid,mediaid)
non-unique user-lastused(userid,lastusedat)
```

`recent_service::touch()` accepts only `CREATE_REFERENCE` and `UPDATE_REFERENCE`, sanitizes no user-supplied label, and uses one DB row per `(userid,mediaid)`.

Upgrade block:

```php
if ($oldversion < 2026091001) {
    // Create local_digieramedia_recent if missing.
    upgrade_plugin_savepoint(true, 2026091001, 'local', 'digieramedia');
}
```

Keep `xmldb_local_digieramedia_upgrade()` in global namespace.

- [ ] **Step 4: Run focused PHPUnit GREEN and PHP syntax checks**

```bash
vendor/bin/phpunit public/local/digieramedia/tests/recent_service_test.php
php -l public/local/digieramedia/classes/service/recent_service.php
php -l public/local/digieramedia/db/upgrade.php
```

Expected: PASS / no syntax errors.

- [ ] **Step 5: Commit**

```bash
git add public/local/digieramedia/db/install.xml public/local/digieramedia/db/upgrade.php public/local/digieramedia/version.php public/local/digieramedia/classes/service/recent_service.php public/local/digieramedia/tests/recent_service_test.php
git commit -m "feat: add per-user recent media history"
```

### Task 2: Touch Recent only on successful use and query true Recent

**Files:**
- Modify: `public/local/digieramedia/classes/external/create_reference.php`
- Modify: `public/local/digieramedia/classes/external/update_reference_version.php`
- Modify: `public/local/digieramedia/classes/external/search_media.php`
- Create: `public/local/digieramedia/tests/recent_external_test.php`

**Interfaces:**
- Consumes: `recent_service::touch(...)` from Task 1.
- Produces: `search_media(tab=recent)` backed by `local_digieramedia_recent`, always constrained to `$USER->id`.

- [ ] **Step 1: Write failing external behavior tests**

Test cases:

```text
A creates reference to X -> A recent contains X
A then creates reference to Y at later timestamp -> order Y, X
B recent does not contain A's rows
A with viewall still sees only A's recent rows
trash X -> X excluded but recent row preserved
restore X -> X eligible again without new touch
```

Also assert an invalid/failed create-reference path does not create a Recent row.

- [ ] **Step 2: Run tests and capture RED**

```bash
vendor/bin/phpunit public/local/digieramedia/tests/recent_external_test.php
```

Expected: FAIL because `search_media(tab=recent)` still orders `m.timemodified` and create/update do not touch Recent.

- [ ] **Step 3: Add post-success touches**

In `create_reference.php`, call only after the reference insert succeeds:

```php
(new \local_digieramedia\service\recent_service())->touch(
    (int)$USER->id,
    (int)$media->id,
    (int)$context->id,
    'CREATE_REFERENCE'
);
```

In `update_reference_version.php`, add `global $USER;` and call after the reference update succeeds with `UPDATE_REFERENCE`.

- [ ] **Step 4: Replace recent search source**

For `tab === 'recent'`, join:

```sql
JOIN {local_digieramedia_recent} r
  ON r.mediaid = m.id
 AND r.userid = :recentuserid
```

Use `m.status = 'ACTIVE'`, preserve current visibility rules, search, page size, current-version join, and order:

```sql
ORDER BY r.lastusedat DESC, m.id DESC
```

Never remove the `r.userid = $USER->id` predicate when `viewall` is true.

- [ ] **Step 5: Run focused tests GREEN**

```bash
vendor/bin/phpunit public/local/digieramedia/tests/recent_service_test.php
vendor/bin/phpunit public/local/digieramedia/tests/recent_external_test.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add public/local/digieramedia/classes/external/create_reference.php public/local/digieramedia/classes/external/update_reference_version.php public/local/digieramedia/classes/external/search_media.php public/local/digieramedia/tests/recent_external_test.php
git commit -m "feat: make recent tab user-specific"
```

### Task 3: Purge cleanup for Recent

**Files:**
- Modify: `public/local/digieramedia/classes/service/lifecycle_service.php`
- Modify: `public/local/digieramedia/tests/lifecycle_service_test.php`

**Interfaces:**
- Purge success deletes `local_digieramedia_recent` rows for the media.
- Trash/restore do not delete or touch Recent rows.

- [ ] **Step 1: Add failing lifecycle regression tests**

Before purge, create Recent rows for two users against the disposable media. Assert Trash and Restore preserve them. On successful purge assert:

```php
$this->assertEquals(0, $DB->count_records('local_digieramedia_recent', ['mediaid' => $mediaid]));
```

For an R2 partial-delete failure, assert rows remain while media remains `PURGING` so retry state is not prematurely destroyed.

- [ ] **Step 2: Run RED**

```bash
vendor/bin/phpunit public/local/digieramedia/tests/lifecycle_service_test.php
```

Expected: purge-cleanup assertion fails before implementation.

- [ ] **Step 3: Implement cleanup only after all version deletes succeed**

Immediately before/with final `PURGED` metadata completion, delete:

```php
$DB->delete_records('local_digieramedia_recent', ['mediaid' => (int)$media->id]);
```

Do not perform this deletion in `trash()`, `restore()`, or the R2 failure catch path.

- [ ] **Step 4: Run lifecycle suite GREEN**

```bash
vendor/bin/phpunit public/local/digieramedia/tests/lifecycle_service_test.php
```

Expected: PASS including partial-delete retry coverage.

- [ ] **Step 5: Commit**

```bash
git add public/local/digieramedia/classes/service/lifecycle_service.php public/local/digieramedia/tests/lifecycle_service_test.php
git commit -m "test: preserve and purge recent lifecycle state"
```

### Task 4: Capability matrix acceptance

**Files:**
- Review/modify only if needed: `public/local/digieramedia/db/access.php`
- Create: `public/local/digieramedia/tests/permission_matrix_test.php`
- Modify endpoint code only when a test reveals a real authorization defect.

**Interfaces:**
- No role-name checks.
- Tests grant/revoke capabilities through Moodle role APIs and call the same service/external boundaries used by production.

- [ ] **Step 1: Write matrix tests before changing authorization code**

Create course context and roles/users representing:

```text
editingteacher archetype
a custom KTV role built from explicit DIGIERA capabilities
manager archetype
non-privileged user
```

Verify capability expectations:

```text
Teacher: view/insert/upload/viewusage/trashown yes; replace/manageversions/restore/purge no
Manager: management yes; purge no unless explicitly granted
KTV: granted view/insert/upload/viewusage/replace/manageversions/trash/restore/managevisibility; purge no initially
KTV after explicit purge grant: purge yes
Non-privileged: view/insert/upload/management no
```

Backend acceptance must invoke representative endpoints/services and assert `required_capability_exception` for denied operations, not merely `has_capability()` values.

- [ ] **Step 2: Run RED/GREEN honestly**

```bash
vendor/bin/phpunit public/local/digieramedia/tests/permission_matrix_test.php
```

If current code already satisfies part of the matrix, those assertions may pass immediately; any failing expectation is investigated before product changes. Do not weaken tests to fit implementation.

- [ ] **Step 3: Make only evidence-based authorization fixes**

Allowed fixes are restricted to capability checks/summary flags required by the approved matrix. Do not create roles or branch on role shortnames.

- [ ] **Step 4: Run the full local plugin PHPUnit set**

```bash
vendor/bin/phpunit public/local/digieramedia/tests
```

Expected: all DIGIERA local plugin tests PASS.

- [ ] **Step 5: Commit**

```bash
git add public/local/digieramedia/tests/permission_matrix_test.php public/local/digieramedia/db/access.php public/local/digieramedia/classes
# include only actually changed authorization files
git commit -m "test: lock DIGIERA permission matrix"
```

### Task 5: RC1 contract, CI and two-node deploy helper

**Files:**
- Modify: `.github/workflows/digiera-media-rc1.yml`
- Modify: `.digiera/tests/digiera-media-rc1-contract.py`
- Create: `.digiera/tools/digiera-media-rc1-recent-permissions-deploy.sh`
- Create: `docs/superpowers/specs/2026-09-10-digiera-media-recent-permissions-deploy-ready.md`

**Interfaces:**
- CI must run on `feature/digiera-media-v1-rc1-recent-permissions`.
- Helper pins the final product commit, expected local version `2026091001`, and does not touch R2 objects.

- [ ] **Step 1: Extend source contract**

Require:

```text
local_digieramedia_recent schema + indexes
recent_service::touch
CREATE_REFERENCE / UPDATE_REFERENCE touch wiring
recent query joins r.userid
recent order by lastusedat
purge cleanup
no role-name authorization checks
local version 2026091001
```

- [ ] **Step 2: Extend RC1 workflow branch/path support**

Add `feature/digiera-media-v1-rc1-recent-permissions` and the new helper path. Since Tiny runtime is unchanged, do not make an unnecessary Tiny version bump. Keep concurrency cancellation by branch.

- [ ] **Step 3: Run fresh RC1 CI and fix only evidence-backed failures**

Authoritative gates remain source/syntax, PHPUnit runtime suite, reproducible bundle, archive verification and artifact upload.

- [ ] **Step 4: Create pinned two-node helper**

Helper sequence:

```text
Web02 SSH + Moodle CLI preflight
stage frozen payload
source/PHP contract
snapshot both nodes
maintenance ON + cron quiesce
install Web01/Web02
Moodle upgrade as www-data
verify local=2026091001, recent table + indexes, global upgrade function
cache purge + FPM reload
SHA parity Web01/Web02
maintenance OFF + cron restore
```

No Cloudflare R2 DELETE or mutation occurs in this batch helper.

- [ ] **Step 5: Verify helper syntax and final pinned CI**

```bash
bash -n .digiera/tools/digiera-media-rc1-recent-permissions-deploy.sh
```

A fresh RC1 run must pass with the helper/product frozen revisions before deploy-ready is claimed.

- [ ] **Step 6: Record deploy-ready checkpoint**

Include exact product commit, helper commit, run id, expected DB schema/version, and browser acceptance sequence for User A/User B + Teacher/KTV.

- [ ] **Step 7: Commit**

```bash
git add .github/workflows/digiera-media-rc1.yml .digiera/tests/digiera-media-rc1-contract.py .digiera/tools/digiera-media-rc1-recent-permissions-deploy.sh docs/superpowers/specs/2026-09-10-digiera-media-recent-permissions-deploy-ready.md
git commit -m "ops: prepare recent permissions RC1 deploy"
```

## Final Verification Checklist

Before production deployment, verify:

```text
Recent service tests PASS
Recent external behavior tests PASS
Lifecycle regression PASS
Permission matrix backend tests PASS
Full DIGIERA runtime suite PASS
RC1 source/syntax gate PASS
Reproducible bundle PASS
Archive verification PASS
Deploy helper bash -n PASS
Tiny/filter versions unchanged unless their code changed
No merge to main
```

Browser acceptance after deploy:

```text
User A: insert X, then Y -> Recent Y then X
User B: does not see User A Recent
User A: Trash X -> absent from Recent; Restore -> reappears without new use
Teacher: no Replace/Restore/Purge; permitted insert/upload/usage remains functional
KTV: management controls available from its granted bundle
KTV purge: absent/blocked until explicit local/digieramedia:purge grant
```
