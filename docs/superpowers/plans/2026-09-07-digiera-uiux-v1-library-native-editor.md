# DIGIERA UI/UX V1 — Library + Native Editor Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild the Worksheet Library and Native teacher-editor presentation layer to closely match the approved mockups/operator-supplied `5.html` while preserving all existing Moodle/Preview business logic and leaving Moodle/RemUI global navigation untouched.

**Architecture:** Keep `local_worksheetlibrary` as the page/workflow owner and `local_digieranative` as the Native editor owner. Replace inline engineering-style markup with plugin-scoped Moodle Mustache templates plus focused AMD modules and CSS; all create/save/publish/version/binding operations continue to use the existing sesskey/capability/service lanes. No database schema change is part of V1.

**Tech Stack:** Moodle 5.1 PHP/Output API/Mustache, AMD JavaScript, ProseMirror Native bundle, plugin-scoped CSS, Python standalone/static contract tests, PHP lint, Node syntax checks.

**Spec:** `docs/superpowers/specs/2026-09-07-digiera-uiux-section3-approved.md`

## Global Constraints

- Do not edit Moodle core.
- Do not edit Edwiser RemUI theme/core files.
- Do not alter Moodle/RemUI global header, top navigation, drawers/sidebar navigation or floating controls.
- Do not load Tailwind CDN or Font Awesome CDN in production.
- All new CSS must be scoped below plugin-owned wrappers such as `.digiera-workspace`, `.wslib-ui`, or `.dgn-editor`.
- Preserve `access_service`, Moodle capability checks, sesskey checks and current service/action endpoints.
- Preserve Native JSON schema/validator/renderer, optimistic revision, autosave/manual Save, publish/version semantics and legacy HTML/Office/PDF reachability.
- No DB schema migration and no DB upgrade in this visual milestone.
- Do not claim pixel-perfect acceptance until browser screenshot comparison and operator acceptance.

---

## File Structure

### Create

- `public/local/worksheetlibrary/templates/library_workspace.mustache` — Library main-content shell: title/actions, folder tree, filters, worksheet list and inspector.
- `public/local/worksheetlibrary/templates/create_worksheet_modal.mustache` — Create worksheet modal using the existing POST action lane.
- `public/local/worksheetlibrary/templates/detail_workspace.mustache` — Detail/editor workspace, version/history and side inspector.
- `public/local/worksheetlibrary/templates/publish_modal.mustache` — Publish confirmation/preview modal tied to the real publish form.
- `public/local/worksheetlibrary/templates/publish_success.mustache` — transient success presentation when a real publish succeeds.
- `public/local/worksheetlibrary/amd/src/ui_v1.js` — presentation-only modal/panel/folder/detail interactions; never replaces service/security logic.
- `public/local/worksheetlibrary/amd/build/ui_v1.min.js` — build mirror used by this Preview branch/package convention.
- `public/local/worksheetlibrary/tests/ui_v1_visual_contract.py` — executable static visual/safety contract for Library/detail/templates/CSS/AMD.
- `public/local/digieranative/tests/ui_v1_editor_visual_contract.py` — executable static contract for Native Ribbon/page-workspace presentation.

### Modify

- `public/local/worksheetlibrary/index.php` — build template context and render `library_workspace`; keep create-folder/create-item/search retrieval and redirects.
- `public/local/worksheetlibrary/detail.php` — build detail/editor template context and render `detail_workspace`; keep all real data/actions and Native AMD init.
- `public/local/worksheetlibrary/manage.php` — add a non-invasive publish-success redirect marker only; preserve existing service calls and sesskey/capability lane.
- `public/local/worksheetlibrary/styles.css` — replace/extend current scoped visual layer with approved design tokens/layout.
- `public/local/worksheetlibrary/amd/src/browser.js` and build mirror only if needed to hand off existing detail-loading behavior to `ui_v1` without changing backend semantics.
- `public/local/digieranative/styles.css` — polish Ribbon/page canvas/status bar under `.dgn-editor`/Library workspace scope.
- Native editor client source only if a visual hook/class is genuinely missing; do not alter document schema/save semantics.

---

### Task 1: Add V1 visual and RemUI-safety contracts

**Files:**
- Create: `public/local/worksheetlibrary/tests/ui_v1_visual_contract.py`
- Create: `public/local/digieranative/tests/ui_v1_editor_visual_contract.py`

**Interfaces:**
- Consumes: current `index.php`, `detail.php`, `styles.css`, Native editor CSS/client.
- Produces: executable contracts used by all later V1 tasks.

- [ ] **Step 1: Write the failing Library visual contract**

The test must assert that the production tree contains:

```python
required_files = [
    'public/local/worksheetlibrary/templates/library_workspace.mustache',
    'public/local/worksheetlibrary/templates/create_worksheet_modal.mustache',
    'public/local/worksheetlibrary/templates/detail_workspace.mustache',
    'public/local/worksheetlibrary/templates/publish_modal.mustache',
    'public/local/worksheetlibrary/amd/src/ui_v1.js',
]
```

It must also assert:

```python
assert 'digiera-workspace' in library_template
assert 'wslib-ui' in library_template
assert 'data-action="open-create-worksheet"' in library_template
assert 'data-region="worksheet-list"' in library_template
assert 'data-region="worksheet-inspector"' in library_template
assert 'render_from_template' in index_php
assert "version_service::create_item" in index_php
assert "version_service::publish" in manage_php
```

Safety assertions must reject production use of:

```python
for forbidden in [
    'https://cdn.tailwindcss.com',
    'cdnjs.cloudflare.com/ajax/libs/font-awesome',
    'theme/remui',
]:
    assert forbidden not in combined_production_sources
```

CSS safety must reject unscoped navigation selectors such as `.nav`, `.nav-link`, `.secondary-navigation`, `.moremenu` unless every selector is prefixed by a plugin wrapper.

- [ ] **Step 2: Run Library contract and observe RED**

Run:

```bash
python3 public/local/worksheetlibrary/tests/ui_v1_visual_contract.py
```

Expected: FAIL because V1 Mustache templates and `ui_v1.js` do not yet exist.

- [ ] **Step 3: Write the failing Native editor visual contract**

Require stable visual hooks:

```python
for hook in [
    '.dgn-editor',
    '.dgn-ribbon',
    '.dgn-editor__workspace',
    '.dgn-editor__page',
    '.dgn-editor__status',
]:
    assert hook in native_css_or_source
```

Also require Ribbon labels/hooks for `File`, `Home`, `Insert`, `Layout`, `Review`, `Math`, `View` where implemented by the editor shell, while explicitly avoiding any ONLYOFFICE product branding in the Native workspace.

- [ ] **Step 4: Run Native contract and observe RED**

Run:

```bash
python3 public/local/digieranative/tests/ui_v1_editor_visual_contract.py
```

Expected: FAIL on one or more missing V1 visual hooks.

- [ ] **Step 5: Commit RED contracts**

```bash
git add public/local/worksheetlibrary/tests/ui_v1_visual_contract.py \
        public/local/digieranative/tests/ui_v1_editor_visual_contract.py
git commit -m "test(ui): define library and native visual contracts"
```

---

### Task 2: Build plugin-scoped visual system and Library browser

**Files:**
- Create: `public/local/worksheetlibrary/templates/library_workspace.mustache`
- Create: `public/local/worksheetlibrary/templates/create_worksheet_modal.mustache`
- Create: `public/local/worksheetlibrary/amd/src/ui_v1.js`
- Create: `public/local/worksheetlibrary/amd/build/ui_v1.min.js`
- Modify: `public/local/worksheetlibrary/index.php`
- Modify: `public/local/worksheetlibrary/styles.css`

**Interfaces:**
- Consumes: folder tree, `$items`, `$can`, existing `action=folder` and `action=item` POST lanes.
- Produces: `library_workspace` and create modal with data hooks consumed by `ui_v1.init()`.

- [ ] **Step 1: Build template context in `index.php` without changing business actions**

Keep the existing action block and retrieval logic intact. Convert `$folders` and `$items` to escaped template-context arrays, including real URLs and metadata.

Use:

```php
$PAGE->requires->js_call_amd('local_worksheetlibrary/ui_v1', 'init');
```

Render with:

```php
echo $OUTPUT->render_from_template('local_worksheetlibrary/library_workspace', $contextdata);
```

- [ ] **Step 2: Implement `library_workspace.mustache` from `5.html` main-content geometry**

Required hierarchy:

```html
<div class="digiera-workspace wslib-ui" data-region="worksheet-library">
  <header class="wslib-ui__pagehead">...</header>
  <div class="wslib-ui__layout">
    <aside class="wslib-ui__folders">...</aside>
    <main class="wslib-ui__main" data-region="worksheet-list">...</main>
    <aside class="wslib-ui__inspector" data-region="worksheet-inspector">...</aside>
  </div>
  {{> local_worksheetlibrary/create_worksheet_modal }}
</div>
```

Do not include mock Moodle sidebar/header.

- [ ] **Step 3: Implement real create modal**

The modal form must POST to the current route with real fields:

```html
<input type="hidden" name="sesskey" value="{{sesskey}}">
<input type="hidden" name="folderid" value="{{folderid}}">
<input type="hidden" name="action" value="item">
```

Kinds:

```text
native / html / pdf / office
```

The default/primary visual choice is Native DIGIERA.

- [ ] **Step 4: Implement presentation-only AMD interactions**

`ui_v1.js` may:

- open/close modal;
- toggle folder panel on compact viewport;
- update selected worksheet row/inspector presentation from safe data attributes;
- preserve normal anchor/form submissions.

It must not create/publish/save content through an alternative endpoint.

- [ ] **Step 5: Implement plugin-scoped design tokens/CSS**

Root all V1 rules beneath `.digiera-workspace.wslib-ui` or existing `.wslib-*` wrappers. Match reference proportions: 12px-ish card radii, subtle borders/shadows, dense toolbar, 3-column Library desktop layout, primary blue hierarchy, neutral surface/background.

- [ ] **Step 6: Run Task 2 contract**

```bash
python3 public/local/worksheetlibrary/tests/ui_v1_visual_contract.py
php -l public/local/worksheetlibrary/index.php
node --check public/local/worksheetlibrary/amd/src/ui_v1.js
```

Expected: Library portion PASS; Native contract may still be RED.

- [ ] **Step 7: Commit Library browser**

```bash
git add public/local/worksheetlibrary/index.php \
        public/local/worksheetlibrary/styles.css \
        public/local/worksheetlibrary/templates \
        public/local/worksheetlibrary/amd/src/ui_v1.js \
        public/local/worksheetlibrary/amd/build/ui_v1.min.js
git commit -m "feat(ui): rebuild worksheet library workspace"
```

---

### Task 3: Rebuild detail page and Native teacher-editor workspace

**Files:**
- Create: `public/local/worksheetlibrary/templates/detail_workspace.mustache`
- Modify: `public/local/worksheetlibrary/detail.php`
- Modify: `public/local/worksheetlibrary/styles.css`
- Modify: `public/local/digieranative/styles.css`
- Modify Native editor shell source only if required for stable V1 hooks.

**Interfaces:**
- Consumes: `$item`, `$versions`, `$bindings`, `$courses`, `$sections`, `$nativedraft` and current Native AMD init payload.
- Produces: page-canvas editor shell, version/history cards and binding/manage inspector without changing service semantics.

- [ ] **Step 1: Preserve all real detail actions while replacing presentation assembly**

Keep:

```text
newdraft
copyitem
moveitem
bind
savehtml
upload
publish
```

and preserve `local_worksheetlibrary/native_editor` initialization for Native draft with `versionid`, `revision`, `nativejson`.

- [ ] **Step 2: Build `detail_workspace.mustache`**

Desktop structure:

```html
<div class="digiera-workspace wslib-ui wslib-ui--detail">
  <header class="wslib-editor-head">...</header>
  <section class="wslib-editor-layout">
    <main class="wslib-editor-stage">
      <div class="wslib-native-toolbar">...</div>
      <div id="wslib-native-editor-{{draftid}}" data-region="native-editor"></div>
    </main>
    <aside class="wslib-editor-inspector">...</aside>
  </section>
  <section class="wslib-version-history">...</section>
</div>
```

The Native editor itself remains owned by `local_digieranative`.

- [ ] **Step 3: Polish Native Ribbon/workspace without changing ProseMirror behavior**

Add/normalize stable classes under `.dgn-editor`:

```text
.dgn-ribbon
.dgn-ribbon__tabs
.dgn-ribbon__group
.dgn-editor__workspace
.dgn-editor__page
.dgn-editor__status
```

Use gray workspace + centered white page, restrained shadow, Word-like ribbon spacing, save state/status bar and viewport-safe overflow.

- [ ] **Step 4: Preserve accessibility and state semantics**

Save status remains `aria-live=polite`; readonly/submitted states remain visually distinct; keyboard focus remains visible.

- [ ] **Step 5: Run editor contracts**

```bash
python3 public/local/digieranative/tests/ui_v1_editor_visual_contract.py
python3 public/local/worksheetlibrary/tests/ui_v1_visual_contract.py
php -l public/local/worksheetlibrary/detail.php
```

Expected: PASS.

- [ ] **Step 6: Commit detail/editor workspace**

```bash
git add public/local/worksheetlibrary/detail.php \
        public/local/worksheetlibrary/templates/detail_workspace.mustache \
        public/local/worksheetlibrary/styles.css \
        public/local/digieranative/styles.css \
        public/local/digieranative/client/src
git commit -m "feat(ui): polish native worksheet editor workspace"
```

---

### Task 4: Add preview/publish confirmation and real success state

**Files:**
- Create: `public/local/worksheetlibrary/templates/publish_modal.mustache`
- Create: `public/local/worksheetlibrary/templates/publish_success.mustache`
- Modify: `public/local/worksheetlibrary/detail.php`
- Modify: `public/local/worksheetlibrary/manage.php`
- Modify: `public/local/worksheetlibrary/amd/src/ui_v1.js`
- Modify: `public/local/worksheetlibrary/amd/build/ui_v1.min.js`
- Modify: `public/local/worksheetlibrary/styles.css`

**Interfaces:**
- Consumes: real draft/version identifiers and current `version_service::publish()` call.
- Produces: confirmation modal + success presentation only after real publish redirect.

- [ ] **Step 1: Add publish modal bound to the existing real publish form**

Required hidden fields remain:

```text
sesskey
action=publish
return=detail
itemid
versionid
```

Do not publish via a mock-only client state.

- [ ] **Step 2: Add real publish-success redirect marker in `manage.php`**

After successful `publish`, redirect to detail with a safe marker such as:

```text
published=1
publishedversion=<integer>
```

No marker is emitted on exception.

- [ ] **Step 3: Render success toast/card only from the real marker**

Match `5.html` success-state visual language while using real version information.

- [ ] **Step 4: Verify publish backend preservation**

Static contract must still find:

```php
\local_worksheetlibrary\service\version_service::publish(...)
```

and `require_sesskey()`/`require_author()` in the action lane.

- [ ] **Step 5: Run V1 contracts/lint/syntax**

```bash
python3 public/local/worksheetlibrary/tests/ui_v1_visual_contract.py
python3 public/local/digieranative/tests/ui_v1_editor_visual_contract.py
php -l public/local/worksheetlibrary/detail.php
php -l public/local/worksheetlibrary/manage.php
node --check public/local/worksheetlibrary/amd/src/ui_v1.js
```

Expected: PASS.

- [ ] **Step 6: Commit publish UX**

```bash
git add public/local/worksheetlibrary/manage.php \
        public/local/worksheetlibrary/detail.php \
        public/local/worksheetlibrary/templates \
        public/local/worksheetlibrary/amd \
        public/local/worksheetlibrary/styles.css
git commit -m "feat(ui): add worksheet preview and publish UX"
```

---

### Task 5: V1 regression/static/package gate and checkpoint

**Files:**
- Modify/Create only test/checkpoint artifacts; no additional product behavior unless a failing gate identifies a real defect.
- Create: `docs/superpowers/specs/2026-09-07-digiera-uiux-v1-code-ready.md` after evidence exists.

**Interfaces:**
- Consumes: completed V1 product tree.
- Produces: code-ready evidence; no deployment claim.

- [ ] **Step 1: Run all Worksheet Library/Native standalone/static tests**

```bash
python3 public/local/worksheetlibrary/tests/ui_v1_visual_contract.py
python3 public/local/digieranative/tests/ui_v1_editor_visual_contract.py
python3 public/local/worksheetlibrary/tests/native_external_api_contract.py
python3 public/local/worksheetlibrary/tests/native_detail_contract.py
python3 public/local/worksheetlibrary/tests/native_kind_ui_contract.py
```

Use actual existing filenames from the branch if a historical test filename differs; do not invent PASS.

- [ ] **Step 2: Run PHP lint over the four Preview plugins**

```bash
find public/local/digieranative public/local/digieraoffice public/local/worksheetlibrary public/mod/worksheetgrader \
  -name '*.php' -print0 | xargs -0 -n1 php -l
```

Expected: all files `No syntax errors detected`.

- [ ] **Step 3: Run JS syntax and Native tests/build**

```bash
find public/local/digieranative public/local/worksheetlibrary public/mod/worksheetgrader \
  -path '*/amd/src/*.js' -o -path '*/client/src/*.js' | while read f; do node --check "$f"; done
```

Run the existing Native Vitest/build lane in the build-capable environment and verify deterministic bundle output before package/deploy.

- [ ] **Step 4: Run scoped-CSS safety scan**

Reject direct edits outside the four DIGIERA plugins/docs/tools and reject global navigation selectors introduced by V1.

- [ ] **Step 5: Package only after gates pass**

Package the changed plugin trees from source-of-truth commit. No DB-upgrade step is expected for V1.

- [ ] **Step 6: Create V1 code-ready Spec Kit checkpoint**

Record exact commit, test counts/output, package hashes, explicit non-claims and next browser deployment/screenshot acceptance gate.

- [ ] **Step 7: Commit checkpoint**

```bash
git add docs/superpowers/specs/2026-09-07-digiera-uiux-v1-code-ready.md
git commit -m "docs(spec): checkpoint UI V1 code ready"
```

---

## Self-review

### Spec coverage

- Library list/create/detail/history/publish: Tasks 2–4.
- Native Word-like editor visual pass: Task 3.
- RemUI/core isolation: Global Constraints + Tasks 1/5 safety tests.
- Existing backend preservation: every task explicitly consumes existing service/action lanes.
- No DB migration: Global Constraints + Task 5.
- Visual acceptance: Task 5 ends at code-ready; browser screenshot/overlay/operator acceptance remains the next deployment gate, so no premature pixel-perfect claim.

### Placeholder scan

No TBD/TODO/"similar to" placeholders are part of the execution steps.

### Type/interface consistency

`ui_v1.init()` is presentation-only throughout; `local_worksheetlibrary/native_editor` remains the Native persistence bridge; `version_service::publish()` remains the publish backend.

## Execution mode

Operator has already approved Hybrid Fastlane / inline execution for this project. Execute this plan with `superpowers:executing-plans` in an isolated workspace, using TDD RED→GREEN contracts and checkpointing after each accepted milestone.
