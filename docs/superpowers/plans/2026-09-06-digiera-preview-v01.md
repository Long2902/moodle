# DIGIERA Preview v0.1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver a testable end-to-end Native worksheet teaching workflow on Moodle 5.1 as quickly as safely possible.

**Architecture:** Materialize verified Native Editor M1C and Worksheet Platform V12 RC1 directly into the Moodle 5.1 `public/` plugin layout, then add the minimum persistence/session/attempt bridges required for a complete teacher→student→grading vertical slice. Collaboration, batch import, Office hardening and final portability/load/visual gates remain deferred.

**Tech Stack:** Moodle 5.1, PHP 8.3, XMLDB/DML, Moodle External API, ProseMirror Native JSON editor, AMD JS, PHPUnit/standalone contracts, GitHub Actions with disposable MariaDB.

**Spec:** `../specs/2026-09-06-digiera-preview-v01-design.md`

## Global Constraints

- Work only on `digiera/preview-v01`; baseline `v5.1.0` remains untouched.
- Direct Git blob/tree/commit materialization only; no base64 transport bootstrap.
- Production Moodle/DB untouched until a separately approved controlled preview deployment.
- Moodle/RemUI global navigation/header/theme chrome unchanged.
- Server-side Native validation authoritative.
- Submitted attempt snapshot immutable to grading.
- `FEATURE_BACKUP_MOODLE2` is not treated as portability proof.

---

### Task 1: Directly materialize the verified plugin baseline

**Files:**
- Create tree: `public/local/digieranative/**`
- Create tree: `public/local/worksheetlibrary/**`
- Create tree: `public/local/digieraoffice/**`
- Create tree: `public/mod/worksheetgrader/**`
- Test: `.github/workflows/digiera-preview-fast.yml`

**Interfaces:**
- Consumes: Native Core M1C artifact and V12 RC1 plugin artifacts.
- Produces: canonical GitHub source tree on `digiera/preview-v01`.

- [ ] **Step 1: Assemble source locally from verified artifacts**

Copy M1C `local/digieranative` and V12 RC1 `worksheetlibrary`, `digieraoffice`, `worksheetgrader` into Moodle 5.1 `public/` paths. Preserve V12 RC1 backup/moodle2 files.

- [ ] **Step 2: Run baseline static verification**

```bash
find public/local/digieranative public/local/worksheetlibrary public/local/digieraoffice public/mod/worksheetgrader -name '*.php' -print0 | xargs -0 -n1 php -l
python3 - <<'PY'
import xml.etree.ElementTree as ET
for path in ['public/local/worksheetlibrary/db/install.xml','public/mod/worksheetgrader/db/install.xml']:
    ET.parse(path)
print('XMLDB_PARSE=PASS')
PY
```

Expected: all PHP lint PASS, XMLDB parse PASS.

- [ ] **Step 3: Commit source atomically through Git tree API**

One commit must contain the complete plugin tree; no transport chunks.

- [ ] **Step 4: Add Fast CI**

Workflow must run PHP lint, XMLDB parse, Native standalone validator/renderer, `npm ci --ignore-scripts`, Vitest, deterministic AMD build and path/secret guards.

- [ ] **Step 5: Commit**

```bash
git commit -m "feat(preview): materialize worksheet vertical-slice baseline"
```

---

### Task 2: Add Native draft persistence and publish bridge

**Files:**
- Modify: `public/local/worksheetlibrary/db/install.xml`
- Create/Modify: `public/local/worksheetlibrary/db/upgrade.php`
- Create: `public/local/worksheetlibrary/classes/service/native_version_service.php`
- Create: `public/local/worksheetlibrary/classes/external/save_native_draft.php`
- Modify: `public/local/worksheetlibrary/db/services.php`
- Modify: `public/local/worksheetlibrary/detail.php`
- Create: `public/local/digieranative/client/src/autosave.js`
- Test: `public/local/worksheetlibrary/tests/native_version_service_test.php`

**Interfaces:**
- Consumes: `local_digieranative\document\validator` and `renderer::render_json()`.
- Produces: `native_version_service::save_draft(int $versionid, int $expectedrevision, string $nativejson, int $userid): stdClass`.

- [ ] **Step 1: Write failing persistence/conflict tests**

Tests assert successful revision `N→N+1`, stale `expectedrevision` conflict, server validation failure, and server-rendered HTML persistence.

- [ ] **Step 2: Run RED**

Expected failure because Native fields/service do not yet exist.

- [ ] **Step 3: Add XMLDB fields**

Add nullable `nativejson`, nullable `schemaversion`, `revision INT NOT NULL DEFAULT 0`, nullable `renderedhtml` to `wslib_version`, using XMLDB upgrade APIs only.

- [ ] **Step 4: Implement atomic save**

Use delegated transaction + row lock/current re-read. If current revision differs from expected revision, throw a typed conflict exception without mutation. Validate canonical JSON and render HTML server-side before updating.

- [ ] **Step 5: Wire External API + editor UI**

Autosave and manual Save share the same endpoint. UI states are `Đang lưu…`, `Đã lưu`, `Xung đột phiên bản`; after conflict automatic writes stop until reload/resolution.

- [ ] **Step 6: Run GREEN and regression**

Expected: persistence/conflict tests PASS; legacy HTML/Office/PDF detail routes remain reachable.

- [ ] **Step 7: Commit**

```bash
git commit -m "feat(preview): persist Native worksheet drafts safely"
```

---

### Task 3: Complete the session→attempt→submit→grade vertical slice

**Files:**
- Modify: `public/mod/worksheetgrader/classes/service/worksheet_snapshot_service.php`
- Modify: `public/mod/worksheetgrader/classes/external/select_worksheet.php`
- Modify: `public/mod/worksheetgrader/v12/content.php`
- Modify: `public/mod/worksheetgrader/attempt.php`
- Modify: `public/mod/worksheetgrader/classes/external/save_attempt.php`
- Modify: `public/mod/worksheetgrader/classes/service/attempt_manager.php`
- Modify: `public/mod/worksheetgrader/grade.php`
- Test: `public/mod/worksheetgrader/tests/preview_vertical_slice_test.php`

**Interfaces:**
- Consumes: published Worksheet Library Native version (`nativejson`, `schemaversion`, `renderedhtml`).
- Produces: frozen session worksheet snapshot; mutable in-progress attempt; immutable submitted snapshot; grade + feedback.

- [ ] **Step 1: Write failing E2E service test**

Fixture sequence:

```text
create course + teacher + student
create Native worksheet + published version
create activity/session
select published worksheet
create team/member state
open session
save attempt Native answer
submit
attempt further mutation -> rejected
save grade + feedback
assert submitted snapshot unchanged
```

- [ ] **Step 2: Run RED**

Expected failure on missing Native snapshot/attempt bridge.

- [ ] **Step 3: Extend worksheet snapshot service**

Freeze canonical Native JSON and rendered HTML from the selected published version. Do not keep source library IDs as runtime dependencies after snapshot creation.

- [ ] **Step 4: Add Native attempt path**

Render Native editor for open attempts with collaboration disabled. Save progress writes only while attempt is mutable. Submit freezes logical revision and canonical answer JSON.

- [ ] **Step 5: Enforce immutable grading source**

Grade page/service reads frozen submitted state. Grading never rewrites attempt answer content.

- [ ] **Step 6: Run GREEN + existing V12 regressions**

Expected: new vertical-slice test PASS; existing team/session/backup source contracts continue to pass.

- [ ] **Step 7: Commit**

```bash
git commit -m "feat(preview): complete worksheet teaching vertical slice"
```

---

### Task 4: Add disposable Preview CI and deployment package gate

**Files:**
- Create: `.github/workflows/digiera-preview-e2e.yml`
- Create: `docs/superpowers/specs/2026-09-06-digiera-preview-v01-resume-point.md`

**Interfaces:**
- Consumes: complete plugin source from Tasks 1–3.
- Produces: CI evidence and a controlled-preview deployable package; no production claim.

- [ ] **Step 1: Configure disposable MariaDB/Moodle**

Use Moodle 5.1 checkout, PHP compatible with 5.1, isolated database and moodledata. Install plugins from the branch.

- [ ] **Step 2: Run DB upgrade/schema checks**

Run Moodle CLI install/upgrade and `check_database_schema.php`; assert Native fields exist.

- [ ] **Step 3: Run PHPUnit/E2E service slice**

Run Native persistence conflict test and Preview vertical-slice test.

- [ ] **Step 4: Package preview plugins**

Generate deterministic plugin archives/checksums for controlled preview deployment. Do not include secrets or `node_modules`.

- [ ] **Step 5: Record checkpoint**

Resume point must distinguish `CODE_READY`, `CI_VERIFIED`, `PREVIEW_DEPLOYED`, and `OPERATOR_VERIFIED`.

- [ ] **Step 6: Commit**

```bash
git commit -m "ci(preview): verify end-to-end worksheet preview"
```

---

### Task 5: Controlled operator preview smoke

**Files:** runtime only; no production-wide rollout.

**Interfaces:**
- Consumes: CI-verified preview packages.
- Produces: operator acceptance evidence for Preview v0.1.

- [ ] **Step 1: Deploy to controlled preview/pilot scope**

Use existing two-node safety rules if using production-adjacent infrastructure; shared DB upgrade runs once.

- [ ] **Step 2: Operator runs one complete workflow**

```text
Kho phiếu -> Native edit/save -> publish
-> create session -> select worksheet -> teams -> open
-> student edit/save/submit
-> teacher grade/feedback
```

- [ ] **Step 3: Verify RemUI shell unchanged**

Capture representative screenshots and confirm plugin-scoped UI only.

- [ ] **Step 4: Close Preview v0.1 checkpoint**

Only operator-confirmed runtime behavior is marked VERIFIED. Deferred hardening remains explicitly open.
