# DIGIERA Phase 5 CambridgePlus Ribbon Migration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the active custom DIGIERA toolbar with a CambridgePlus-derived compact Tiptap ribbon and picture workflow while preserving DIGIERA Native JSON, Moodle persistence, preview/publish/session behavior, and the no-DB-upgrade production contract.

**Architecture:** Port the proven CambridgePlus ribbon and picture-edit interaction patterns into `local_digieranative`, adapt them to the existing Native serializer and Tiptap extensions, and expose a context-neutral image adapter consumed by teacher worksheet and student attempt bridges. Native JSON and the PHP validator/renderer remain authoritative; Moodle File API remains the storage layer. The existing production canonical `1b698d6a7b45a7200661df1305761910066fabcf` is the rollback baseline until browser acceptance passes.

**Tech Stack:** Moodle 5.1, PHP 8.3, Tiptap 3.x, React/ReactDOM, Rollup AMD, Vitest/jsdom, Moodle File API, TCPDF, `lucide-react`, `react-image-crop`.

**Spec:** `docs/superpowers/specs/2026-09-11-digiera-phase5-cambridge-ribbon-migration-design.md`

## Global Constraints

- Scope remains Phase 5 only; do not create Phase 6.
- CambridgePlus source is pinned to `Long2902/CambridgePlus@23739b6d71501e373ed06d819ca73a2f5360118e`.
- DIGIERA production rollback baseline is `1b698d6a7b45a7200661df1305761910066fabcf`.
- Keep DIGIERA Native JSON canonical and PHP validator/renderer authoritative.
- Keep worksheet autosave/manual save, preview/publish/version, session, student submit, teacher review, grade and feedback boundaries unchanged.
- Use Moodle File API for teacher/student assets; do not port CambridgePlus IndexedDB/Vercel storage.
- No runtime CDN, no Tiptap Cloud requirement, no Tiptap Pro/Team-only runtime dependency.
- All JS/CSS/icons/image-crop code must be bundled locally.
- No Moodle core or RemUI mutation.
- Hard gate: `DB_UPGRADE_REQUIRED=NO`; if `version.php`, `db/install.xml`, or `db/upgrade.php` must change, stop and return to design review.
- No duplicate active toolbar/image implementations after migration.
- Every editor action either mutates state, opens an interaction, or shows a visible error; no silent controls.

---

## File Structure / Responsibility Map

### `local_digieranative` client

- `client/src/ui/cambridge_toolbar.js` — compact CambridgePlus-derived toolbar and DIGIERA command group only.
- `client/src/ui/picture_editor.js` — ported/adapted picture editor UI and crop/rotation/caption/alt state.
- `client/src/image_adapter.js` — context-neutral image adapter contract/helpers; no Moodle DB/storage writes.
- `client/src/editor.js` — Tiptap editor composition, Native serializer, toolbar/picture wiring.
- `client/src/document_adapter.js` — Tiptap <-> Native mapping for new text-style marks and image metadata.
- `client/src/schema.js` / `schema_specs.js` — Native client schema/validation parity.
- `client/src/tiptap_extensions.js` — Tiptap extensions for DIGIERA nodes, image block, styles/highlight/table behavior.
- `client/src/preview_renderer.js` — structured Native preview with image metadata/assets.
- `client/package.json` / `package-lock.json` — local-bundled dependencies only.
- `styles.css` — compact DOCX-like toolbar, picture dialog, image node view and paper layout.

### PHP authoritative Native layer

- `classes/document/schema.php` — mark registry parity.
- `classes/document/validator.php` — exact text-style/image metadata validation.
- `classes/document/renderer.php` — safe HTML rendering for styles/crop/layout/caption.

### Teacher worksheet bridge

- `local/worksheetlibrary/amd/src/native_editor.js` — implement teacher ImageAdapter through worksheet asset endpoint, preserve autosave/manual save serializer.
- `local/worksheetlibrary/native_asset.php` — worksheet create/replace/resolve asset actions.
- `local/worksheetlibrary/native_pdf.php` — keep PDF server path aligned with new image/style metadata.

### Student attempt bridge

- `mod/worksheetgrader/amd/src/native_attempt.js` — implement attempt ImageAdapter, preserve save/submit contract.
- `mod/worksheetgrader/native_attempt_asset.php` — attempt-scoped create/replace/resolve asset actions.

### Tests

- `client/tests/cambridge-ribbon-migration.test.js` — toolbar/control behavior and old-toolbar retirement.
- `client/tests/native-style-roundtrip.test.js` — font family/size/color/highlight adapter parity.
- `client/tests/cambridge-image-flow.test.js` — captured position, create/replace/crop/alt/caption behavior.
- `client/tests/legacy-native-compat.test.js` — old documents/images mount without destructive normalization.
- PHP standalone/static contracts extended for mark/image validation and renderer parity.

---

### Task 1: Lock migration contracts with RED tests

**Files:**
- Create: `public/local/digieranative/client/tests/cambridge-ribbon-migration.test.js`
- Create: `public/local/digieranative/client/tests/native-style-roundtrip.test.js`
- Create: `public/local/digieranative/client/tests/cambridge-image-flow.test.js`
- Create: `public/local/digieranative/client/tests/legacy-native-compat.test.js`
- Modify: `public/local/digieranative/tests/tiptap_hybrid_static_contract.py`
- Modify: `public/local/digieranative/tests/standalone_validator.php`
- Modify: `public/local/digieranative/tests/standalone_renderer.php`

**Interfaces:**
- Consumes: approved spec and existing `mount()`, `fromNativeDocument()`, `toNativeDocument()` APIs.
- Produces: executable RED contracts for Tasks 2-7.

- [ ] **Step 1: Add toolbar migration RED test**

Test must assert the active editor contains one compact toolbar marker such as `data-dgn-cambridge-toolbar="1"`, exposes Undo/Redo/font family/font size/color/highlight/list/alignment/link/table/picture/File/Layout/DIGIERA actions, and no active `data-dgn-official-toolbar="1"` legacy path.

- [ ] **Step 2: Add text-style round-trip RED test**

Create Native text with `fontFamily`, `fontSize`, `textColor`, `highlight`, convert Native -> Tiptap -> Native, and assert exact values survive. Add negative cases for arbitrary family, unsupported size, invalid hex color.

- [ ] **Step 3: Add image-flow RED test**

Use a mocked ImageAdapter and assert: captured position is retained across async picker/editor flow; create inserts one image node; replace/crop/alt/caption update the same node; no blob URL enters Native JSON.

- [ ] **Step 4: Add legacy compatibility RED test**

Mount current-production Native JSON with old image attrs only, assert safe runtime defaults are available, and assert save without touching image metadata does not inject/destructively rewrite absent fields.

- [ ] **Step 5: Extend PHP RED contracts**

Add standalone validation/renderer cases for new marks and image fields, including all exact allowlists/ranges/cross-field crop constraints from the spec.

- [ ] **Step 6: Run focused RED gate**

Run:

```bash
cd public/local/digieranative/client
npx vitest run \
  tests/cambridge-ribbon-migration.test.js \
  tests/native-style-roundtrip.test.js \
  tests/cambridge-image-flow.test.js \
  tests/legacy-native-compat.test.js
cd ..
php tests/standalone_validator.php
php tests/standalone_renderer.php
python3 tests/tiptap_hybrid_static_contract.py
```

Expected: failures specifically for missing Cambridge toolbar, style/image schema parity and picture workflow; existing unrelated tests must not be the reason for RED.

- [ ] **Step 7: Commit RED evidence**

```bash
git add public/local/digieranative/client/tests public/local/digieranative/tests
git commit -m "test(phase5): lock Cambridge ribbon migration contracts [skip ci]"
```

---

### Task 2: Extend Native text-style schema end-to-end

**Files:**
- Modify: `public/local/digieranative/client/src/schema.js`
- Modify: `public/local/digieranative/client/src/schema_specs.js`
- Modify: `public/local/digieranative/client/src/document_adapter.js`
- Modify: `public/local/digieranative/client/src/tiptap_extensions.js`
- Modify: `public/local/digieranative/classes/document/schema.php`
- Modify: `public/local/digieranative/classes/document/validator.php`
- Modify: `public/local/digieranative/classes/document/renderer.php`
- Modify: `public/local/digieranative/client/package.json`
- Modify: `public/local/digieranative/client/package-lock.json`

**Interfaces:**
- Consumes: Native marks `textColor` and link safety rules.
- Produces: `fontFamily {family}`, `fontSize {px}`, `highlight {color}` plus adapter parity.

- [ ] **Step 1: Add exact mark definitions**

Implement allowlists exactly: families `Arial`, `Calibri`, `Georgia`, `Times New Roman`, `Verdana`; sizes `10,11,12,14,16,18,20,24,28,32,36`; colors `#RRGGBB`.

- [ ] **Step 2: Add Tiptap extensions/dependencies**

Use official/free `@tiptap/extension-text-style`, Color, FontFamily, FontSize and Highlight packages. Bundle locally; no runtime URLs.

- [ ] **Step 3: Implement adapter mappings**

Map Tiptap style attrs <-> explicit Native marks without persisting generic `textStyle` or arbitrary CSS.

- [ ] **Step 4: Implement PHP validator/renderer parity**

Server validates identical allowlists and renders escaped validated inline styles only.

- [ ] **Step 5: Run style-focused GREEN tests**

```bash
cd public/local/digieranative/client
npx vitest run tests/native-style-roundtrip.test.js tests/schema.test.js tests/attribute-validation.test.js
cd ..
php tests/standalone_validator.php
php tests/standalone_renderer.php
```

Expected: all PASS.

- [ ] **Step 6: Commit**

```bash
git add public/local/digieranative
 git commit -m "feat(phase5): add Native text style parity for Cambridge ribbon [skip ci]"
```

---

### Task 3: Extend Native image metadata and structural image extension

**Files:**
- Modify: `public/local/digieranative/client/src/schema.js`
- Modify: `public/local/digieranative/client/src/schema_specs.js`
- Modify: `public/local/digieranative/client/src/document_adapter.js`
- Modify: `public/local/digieranative/client/src/tiptap_extensions.js`
- Modify: `public/local/digieranative/client/src/preview_renderer.js`
- Modify: `public/local/digieranative/classes/document/validator.php`
- Modify: `public/local/digieranative/classes/document/renderer.php`
- Create: `public/local/digieranative/client/src/ui/picture_editor.js`
- Create: `public/local/digieranative/client/src/image_adapter.js`

**Interfaces:**
- Consumes: existing Native `image.assetKey` and runtime asset URL mapping.
- Produces: image attrs `caption`, `widthPercent`, crop rectangle and rotation with safe legacy defaults; ImageAdapter interface.

- [ ] **Step 1: Implement exact image metadata validation**

Add `widthPercent 10..100`, `cropX/cropY/cropW/cropH 0..1`, positive crop dimensions, sum bounds, rotation `0|90|180|270`, align `left|center|right`, bounded caption.

- [ ] **Step 2: Preserve legacy documents non-destructively**

Runtime defaults may be supplied to the Tiptap view, but `toNativeDocument()` must not materialize missing new attrs unless user edits the image or creates a new Cambridge-style image.

- [ ] **Step 3: Port PictureEditor interaction from CambridgePlus**

Adapt the pinned CambridgePlus PictureEditor state model: crop, rotation, widthPercent, align, alt, caption. Use `react-image-crop` locally bundled.

- [ ] **Step 4: Define ImageAdapter**

Expose async methods:

```js
createAsset(file)
replaceAsset(assetKey, file)
resolvePreview(assetKey)
onAssetRecord(asset)
```

The client core treats these as callbacks and never calls Moodle DB/storage APIs itself.

- [ ] **Step 5: Add structural image node behavior**

Capture editor position before file selection; insert at captured top-level semantic position after asset creation; update same node on replace/crop edits.

- [ ] **Step 6: Update preview/rendering**

Preview and PHP renderer must apply width/alignment/crop/rotation/caption from validated Native attrs and resolve asset URL separately.

- [ ] **Step 7: Run image-focused GREEN tests**

```bash
cd public/local/digieranative/client
npx vitest run tests/cambridge-image-flow.test.js tests/legacy-native-compat.test.js tests/dom-codec.test.js
cd ..
php tests/standalone_validator.php
php tests/standalone_renderer.php
```

Expected: all PASS.

- [ ] **Step 8: Commit**

```bash
git add public/local/digieranative
 git commit -m "feat(phase5): port Cambridge picture workflow to Native image nodes [skip ci]"
```

---

### Task 4: Port CambridgePlus compact ribbon and retire active legacy toolbar

**Files:**
- Create: `public/local/digieranative/client/src/ui/cambridge_toolbar.js`
- Modify: `public/local/digieranative/client/src/ui/official_editor.js`
- Modify: `public/local/digieranative/client/src/editor.js`
- Modify: `public/local/digieranative/client/src/ui/toolbar.js`
- Modify: `public/local/digieranative/client/src/ribbon.js`
- Modify: `public/local/digieranative/styles.css`
- Modify: `public/local/digieranative/client/package.json`
- Modify: `public/local/digieranative/client/package-lock.json`

**Interfaces:**
- Consumes: ImageAdapter, Native serializer, layout callbacks, Tiptap editor instance.
- Produces: one active compact toolbar and picture UI.

- [ ] **Step 1: Port the working CambridgePlus command flows**

Use direct Tiptap commands for Undo/Redo, font, size, marks, color/highlight, headings, lists, list indent, alignment, link/unlink and table operations. Do not copy Cambridge-specific paragraph-label behavior.

- [ ] **Step 2: Add compact DOCX-like composition**

Single horizontal icon-first toolbar with compact selects and overflow behavior. Use locally bundled `lucide-react` icons. Preserve visible active/disabled states.

- [ ] **Step 3: Integrate File/Layout controls**

Wire Save, Print, Download PDF, A4 orientation/margin/zoom callbacks. Keep zoom view-only and layout persisted in `meta.layout`.

- [ ] **Step 4: Integrate DIGIERA group**

Keep Question/Short/Long/Answer Table/Math smart behavior. Use the single shared Insert Picture action instead of a second DIGIERA image path.

- [ ] **Step 5: Integrate TableKit safely**

Insert with `withHeaderRow: false`; basic row/column operations must never create `tableHeader`.

- [ ] **Step 6: Retire the old active toolbar path**

`ui/toolbar.js` and `ribbon.js` may remain compatibility shims only; `editor.js` mounts only `cambridge_toolbar.js`.

- [ ] **Step 7: Run toolbar-focused GREEN tests**

```bash
cd public/local/digieranative/client
npx vitest run \
  tests/cambridge-ribbon-migration.test.js \
  tests/official-toolbar.test.js \
  tests/mount-contract.test.js \
  tests/ribbon-shell.test.js \
  tests/phase5-toolbar-completion.test.js
```

Update superseded old-toolbar assertions only where the approved migration intentionally changes them; do not weaken persistence/command coverage.

- [ ] **Step 8: Commit**

```bash
git add public/local/digieranative
 git commit -m "feat(phase5): replace active toolbar with CambridgePlus ribbon core [skip ci]"
```

---

### Task 5: Implement teacher Moodle ImageAdapter and PDF parity

**Files:**
- Modify: `public/local/worksheetlibrary/amd/src/native_editor.js`
- Modify: `public/local/worksheetlibrary/native_asset.php`
- Modify: `public/local/worksheetlibrary/native_pdf.php`
- Modify: `public/local/worksheetlibrary/amd/src/browser.js` if preview asset map needs metadata wiring
- Add/modify worksheetlibrary static/PHP tests under `public/local/worksheetlibrary/tests/`

**Interfaces:**
- Consumes: `createAsset`, `replaceAsset`, `resolvePreview`, `onAssetRecord` callback contract.
- Produces: teacher worksheet asset adapter with capability/context checks.

- [ ] **Step 1: Add RED teacher asset integration tests**

Assert create/replace reject invalid context/capability/mime/size, preserve stable logical assetKey semantics, and return preview mapping separately from Native JSON.

- [ ] **Step 2: Implement teacher adapter callbacks**

Bridge browser file picker/PictureEditor into Moodle worksheet file storage through `native_asset.php`. Never store blob/data URLs in Native JSON.

- [ ] **Step 3: Make replace semantics explicit**

Replacing bytes for an existing image must preserve the logical assetKey referenced by the document unless the endpoint explicitly returns a replacement key and the same image node is atomically updated.

- [ ] **Step 4: Align preview and PDF**

Preview asset map and `native_pdf.php` resolve the stored asset and apply image metadata/caption. PDF still renders from persisted Native JSON.

- [ ] **Step 5: Verify autosave/manual-save serializer identity**

Both paths must call the same current editor Native serializer after text/image/layout edits.

- [ ] **Step 6: Run teacher integration tests/PHP lint**

Expected: all PASS with no DB/version file change.

- [ ] **Step 7: Commit**

```bash
git add public/local/worksheetlibrary public/local/digieranative
 git commit -m "feat(phase5): connect Cambridge picture flow to worksheet Moodle assets [skip ci]"
```

---

### Task 6: Implement student attempt ImageAdapter and immutable review parity

**Files:**
- Modify: `public/mod/worksheetgrader/amd/src/native_attempt.js`
- Modify: `public/mod/worksheetgrader/native_attempt_asset.php`
- Add/modify grader tests under `public/mod/worksheetgrader/tests/`

**Interfaces:**
- Consumes: same ImageAdapter callback contract and canonical Native attempt JSON.
- Produces: attempt-scoped image flow whose submitted snapshot remains immutable/readable by teacher review.

- [ ] **Step 1: Add RED attempt asset tests**

Cover create/replace permissions/context, save-refresh persistence, submitted snapshot references and teacher review resolution.

- [ ] **Step 2: Wire attempt ImageAdapter**

Use attempt-scoped Moodle file storage; callbacks must not mutate submitted snapshots after submission.

- [ ] **Step 3: Preserve answer editing behavior**

Image/ribbon migration must not break short/long/table answer editing, save or submit.

- [ ] **Step 4: Verify teacher review**

Submitted student images and metadata resolve for teacher review while canonical submission content stays immutable.

- [ ] **Step 5: Run grader integration tests/PHP lint**

Expected: all PASS.

- [ ] **Step 6: Commit**

```bash
git add public/mod/worksheetgrader public/local/digieranative
 git commit -m "feat(phase5): connect Cambridge picture flow to student attempts [skip ci]"
```

---

### Task 7: Full local candidate gate and deterministic package

**Files:**
- Modify only generated AMD build files after all source tests pass.
- Create/update a Phase 5 fastlane runbook under `docs/superpowers/runbooks/` if needed.

**Interfaces:**
- Consumes: completed Tasks 1-6.
- Produces: immutable product-only candidate package and exact hashes, without production mutation until all gates pass.

- [ ] **Step 1: Run focused migration suite**

Run all new migration tests plus old multiline/link/layout/autosave/preview tests.

- [ ] **Step 2: Run full Vitest suite**

```bash
cd public/local/digieranative/client
npm test
```

Expected: all PASS.

- [ ] **Step 3: Run server/static gates**

Run standalone validator/renderer, static contract, PHP lint over all three plugin roots, JS syntax checks and TCPDF smoke.

- [ ] **Step 4: Run deterministic AMD build twice**

Hash and byte-compare `native_editor.min.js`; freeze worksheet/grader AMD bridges from reviewed source.

- [ ] **Step 5: Scope gate**

Reject any core/RemUI file, any runtime CDN, and any `version.php`, `db/install.xml`, `db/upgrade.php` delta. Assert `DB_UPGRADE_REQUIRED=NO`.

- [ ] **Step 6: Materialize product-only canonical candidate**

Start from production canonical `1b698d6a7b45a7200661df1305761910066fabcf`, copy only:

```text
public/local/digieranative
public/local/worksheetlibrary
public/mod/worksheetgrader
```

Commit candidate locally, then build deterministic `git archive | gzip -n` package twice and compare bytes.

- [ ] **Step 7: Record source/package/tree hashes**

Record canonical candidate SHA, package SHA256, native AMD SHA256 and tree hashes for all three plugin roots.

---

### Task 8: Two-node deploy, browser/E2E acceptance and Phase 5 closure

**Files:**
- Create: new Phase 5 resume checkpoint under `docs/superpowers/specs/` after deploy.
- Create: final Phase 5 acceptance checkpoint after browser/E2E PASS.

**Interfaces:**
- Consumes: immutable Task 7 package/hashes.
- Produces: verified Web01/Web02 production and final `PHASE5=PASS` only after browser/E2E evidence.

- [ ] **Step 1: Verify exact production baseline**

Both nodes must exactly match current canonical product trees before deploy; Moodle bootstrap passes; maintenance=0; Web01 cron active.

- [ ] **Step 2: Backup both nodes and stage package**

Create timestamped code backups; verify package SHA on Web02 before mutation.

- [ ] **Step 3: Deploy package-bound candidate**

Enable maintenance, stop cron on Web01, rsync exact three plugin roots to both nodes, verify expected tree hashes, PHP lint and Moodle bootstrap both nodes, purge caches/reload FPM, restore maintenance=0 and cron active. Automatic rollback on any post-mutation failure.

- [ ] **Step 4: Fast-forward canonical only after runtime deploy PASS**

Move `digiera/preview-v01` only if production and package hashes agree.

- [ ] **Step 5: Teacher browser acceptance**

Verify compact Cambridge-derived toolbar; font/size/color/highlight/format/list/alignment/link; table without header-node incompatibility; Insert Picture -> Picture Editor -> insert/crop/replace/alt/caption; save/autosave/refresh; layout; Preview; Print; PDF; Publish.

- [ ] **Step 6: Student/teacher E2E acceptance**

Session -> student answer/image -> save -> refresh -> submit -> teacher immutable review -> image/crop/layout -> grade/feedback.

- [ ] **Step 7: Write deployed checkpoint**

Record local gate evidence, canonical/package/tree hashes, no-DB-upgrade and browser status. Do not overwrite earlier resume points.

- [ ] **Step 8: Write final acceptance checkpoint and close Phase 5**

Only when browser/E2E evidence passes, record:

```text
PHASE5=PASS
ALL_5_PHASES=COMPLETED
PRODUCTION_WEB01_WEB02=VERIFIED
DB_UPGRADE_REQUIRED=NO
```

Commit final checkpoint with `[skip ci]`.
