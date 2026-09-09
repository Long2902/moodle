# DIGIERA Official Tiptap UI Phase 5 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the custom DIGIERA Ribbon with an official-Tiptap-based React editor surface, preserve DIGIERA worksheet nodes and save contracts, and fix multiline/preview semantic parity before re-deploying Phase 5.

**Architecture:** Keep Tiptap 3.x as the authoritative client editor and Native JSON as the persistence contract. Vendor the free MIT-licensed Tiptap Simple Editor/UI source patterns into `local_digieranative`, bundle React 18 + Tiptap locally into the existing Moodle AMD artifact, keep DIGIERA-specific nodes as custom extensions, normalize plain-text multiline input into block structure, and render preview from structured Native JSON instead of cloning editor DOM.

**Tech Stack:** Moodle 5.1, PHP 8.3, Edwiser RemUI 5.2.2, Tiptap 3.31.3, React 18, Rollup AMD, Vitest/JSDOM, existing PHP Native validator/renderer.

**Spec:** `docs/superpowers/specs/2026-09-09-digiera-tiptap-official-ui-phase5-design.md`

## Global Constraints

- Base production source is `3413597dd78b0cef6fbb1b5be59ac5b1ffe18504`.
- Work only on isolated branch/worktree until gates pass.
- Do not modify Moodle core, `theme/remui`, or unrelated plugins.
- Do not add runtime CDN dependencies.
- Canonical Native root remains `{"type":"worksheet","version":1,"content":[]}`.
- PHP validator/renderer remain server-authoritative.
- Existing optimistic revision autosave contract remains authoritative.
- Existing Native documents must remain readable.
- No DB schema migration. `DB_UPGRADE_REQUIRED=NO` is a hard release gate.
- Phase 5 remains the final phase; do not create Phase 6.

---

### Task 1: Freeze the official Tiptap UI reference and build boundary

**Files:**
- Create: `public/local/digieranative/client/vendor/tiptap-ui/PROVENANCE.md`
- Modify: `public/local/digieranative/client/package.json`
- Modify: `public/local/digieranative/client/package-lock.json`
- Modify: `public/local/digieranative/client/rollup.config.js`
- Test: `public/local/digieranative/client/tests/official-ui-build.test.js`

**Interfaces:**
- Consumes: existing `src/index.js` AMD entry and Tiptap 3.31.3 dependencies.
- Produces: a locally bundled React/Tiptap UI build path with no CDN and a reproducible record of which official Simple Editor/UI source was copied.

- [ ] **Step 1: Generate an official reference outside product source**

Run in the isolated worktree:

```bash
rm -rf /tmp/digiera-tiptap-simple-reference
npm create vite@latest /tmp/digiera-tiptap-simple-reference -- --template react
cd /tmp/digiera-tiptap-simple-reference
npx @tiptap/cli@latest add simple-editor
```

Record the CLI version, generated package versions, and source paths in `vendor/tiptap-ui/PROVENANCE.md`. Do not copy `node_modules` or generated Vite app scaffolding into Moodle.

- [ ] **Step 2: Write the failing build-contract test**

Create `tests/official-ui-build.test.js` asserting that the final source imports React/Tiptap React, does not import the legacy `createRibbon`, and contains no `http://`, `https://`, or CDN runtime references:

```js
import {readFileSync} from 'node:fs';
import {describe, expect, it} from 'vitest';

const editor = readFileSync(new URL('../src/editor.js', import.meta.url), 'utf8');

describe('official Tiptap UI build contract', () => {
  it('uses React/Tiptap React and removes the legacy ribbon runtime', () => {
    expect(editor).toMatch(/@tiptap\/react/);
    expect(editor).not.toMatch(/createRibbon/);
  });

  it('does not depend on a runtime CDN', () => {
    expect(editor).not.toMatch(/https?:\/\//);
  });
});
```

- [ ] **Step 3: Run RED**

```bash
cd public/local/digieranative/client
npm ci
npx vitest run tests/official-ui-build.test.js
```

Expected: FAIL because current `editor.js` imports/calls `createRibbon` and does not use `@tiptap/react`.

- [ ] **Step 4: Add the build dependencies**

Pin React 18 and Tiptap React to the current Tiptap line. Add only dependencies required by the selected free Simple Editor components, plus Rollup JSX/CJS support. Keep Tiptap packages on `3.31.3`; use React/ReactDOM `18.3.1` because current Tiptap UI docs recommend React 18 compatibility.

Required baseline additions:

```json
{
  "dependencies": {
    "@tiptap/react": "3.31.3",
    "react": "18.3.1",
    "react-dom": "18.3.1"
  },
  "devDependencies": {
    "@rollup/plugin-commonjs": "latest",
    "@rollup/plugin-replace": "latest",
    "@rollup/plugin-babel": "latest",
    "@babel/core": "latest",
    "@babel/preset-react": "latest"
  }
}
```

Resolve `latest` to exact versions in `package-lock.json`; never leave floating versions in the committed lockfile.

- [ ] **Step 5: Update Rollup for local React bundling**

Configure `nodeResolve`, `commonjs`, `replace({'process.env.NODE_ENV': JSON.stringify('production')})`, and Babel React transform; retain AMD output to `../amd/build/native_editor.min.js` and existing deterministic settings.

- [ ] **Step 6: Run GREEN build contract and deterministic build twice**

```bash
npm ci
npm run build
sha256sum ../amd/build/native_editor.min.js > /tmp/build1.sha
npm run build
sha256sum ../amd/build/native_editor.min.js > /tmp/build2.sha
diff -u /tmp/build1.sha /tmp/build2.sha
```

Expected after later Task 3 integration: exact same hash twice.

- [ ] **Step 7: Commit**

```bash
git add public/local/digieranative/client/package.json \
        public/local/digieranative/client/package-lock.json \
        public/local/digieranative/client/rollup.config.js \
        public/local/digieranative/client/vendor/tiptap-ui/PROVENANCE.md \
        public/local/digieranative/client/tests/official-ui-build.test.js
git commit -m "test(tiptap): establish official UI build contract"
```

---

### Task 2: Lock the observed multiline and preview defects with RED tests

**Files:**
- Modify: `public/local/digieranative/client/tests/paste-sanitization.test.js`
- Create: `public/local/digieranative/client/tests/preview-structure.test.js`
- Modify: `public/local/digieranative/client/tests/ribbon-shell.test.js` or replace with `official-toolbar.test.js`
- Test: the same files.

**Interfaces:**
- Produces: exact regression fixtures for the browser failure the user observed.

- [ ] **Step 1: Add the multiline paste fixture**

Use this exact text:

```text
PHASE 5 TEST
Đây là nội dung kiểm thử Native Editor.
Dòng kiểm tra autosave.
```

The test must assert the parsed result contains three paragraph nodes and no text node containing `\n`.

- [ ] **Step 2: Add the preview contract RED test**

Assert `local_worksheetlibrary/amd/src/browser.js` no longer uses:

```js
target.innerHTML = canvas.innerHTML;
```

and instead consumes structured Native JSON supplied by the mounted editor.

- [ ] **Step 3: Add the dead-control RED test**

Assert the final visible toolbar has no controls corresponding to legacy placeholders `layout-info`, `review-info`, or `view-info`.

- [ ] **Step 4: Run RED**

```bash
cd public/local/digieranative/client
npx vitest run tests/paste-sanitization.test.js tests/preview-structure.test.js tests/official-toolbar.test.js
```

Expected: FAIL on current plain-text `insertText(text)`, DOM-clone preview, and legacy placeholder toolbar.

- [ ] **Step 5: Commit RED tests only**

```bash
git add public/local/digieranative/client/tests \
        public/local/worksheetlibrary/amd/src/browser.js
git commit -m "test(tiptap): reproduce multiline and preview parity defects"
```

Do not change runtime behavior in this commit.

---

### Task 3: Replace the custom Ribbon with the official-Tiptap-based React editor surface

**Files:**
- Create: `public/local/digieranative/client/src/ui/official_editor.jsx`
- Create: `public/local/digieranative/client/src/ui/toolbar.jsx`
- Create: `public/local/digieranative/client/src/ui/digiera_insert_menu.jsx`
- Modify: `public/local/digieranative/client/src/editor.js`
- Modify: `public/local/digieranative/client/src/index.js`
- Modify: `public/local/digieranative/client/src/tiptap_extensions.js`
- Modify or retire from runtime: `public/local/digieranative/client/src/ribbon.js`
- Modify: `public/local/digieranative/styles.css`
- Test: `public/local/digieranative/client/tests/official-toolbar.test.js`
- Test: `public/local/digieranative/client/tests/mount-contract.test.js`

**Interfaces:**
- `mount(config)` remains the public Moodle AMD entry contract.
- `mount()` returns the underlying Tiptap editor instance for compatibility.
- `save(canonicalNativeJson)` and `onUpdate(canonicalNativeJson)` signatures remain unchanged.

- [ ] **Step 1: Vendor only the required free official UI source patterns**

From the generated reference, copy/adapt the MIT-licensed patterns needed for:

```text
undo-redo-button
mark-button
heading-dropdown-menu
list-dropdown-menu
text-align-button
link-popover
button / toolbar primitives
```

Keep provenance and license headers. Do not bring code-block, cloud, collaboration, AI, comments, or version-history UI into Phase 5.

- [ ] **Step 2: Implement `OfficialEditor`**

Create one React host that uses `useEditor`/`EditorContent` from `@tiptap/react`, configured with the existing StarterKit line, `TextAlign`, link support required by the copied component, and `createDigieraExtensions()`.

The component must call:

```js
onUpdate(toNativeDocument(editor.getJSON(), {version, meta}))
```

from Tiptap update events and expose manual save through the same serializer.

- [ ] **Step 3: Implement selection-aware toolbar**

Required working controls in Phase 5:

```text
Undo / Redo
Paragraph / H1 / H2 / H3
Bold / Italic / Underline / Strike
Bullet list / Ordered list
Align Left / Center / Right / Justify
Link add/edit/remove
```

Every mark/block control must use Tiptap commands and active state (`editor.isActive(...)`). Do not render non-functional placeholders.

- [ ] **Step 4: Add DIGIERA insert group**

Wire existing domain actions to the underlying Tiptap editor for:

```text
Question
Short answer
Long answer
Answer table
Image request hook
Math request hook
```

Preserve the existing custom node JSON shape and existing `digiera-native:request-image` / `digiera-native:request-math` event contract where applicable.

- [ ] **Step 5: Preserve the public `mount()` contract**

`editor.js` becomes the imperative Moodle adapter: create a React root in the supplied element, mount `OfficialEditor`, retain a reference to the Tiptap editor, and return a compatibility facade/underlying instance sufficient for current tests and downstream consumers. Remove `createRibbon()` from the active runtime path.

- [ ] **Step 6: Scope styles to `.dgn-editor` / `.dgn-official-toolbar`**

Use the official Simple Editor visual hierarchy as baseline but keep all CSS plugin-scoped. Preserve paper-canvas sizing and RemUI isolation.

- [ ] **Step 7: Run toolbar and mount tests**

```bash
npx vitest run tests/official-toolbar.test.js tests/mount-contract.test.js tests/editor-core.test.js
```

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add public/local/digieranative/client/src \
        public/local/digieranative/styles.css \
        public/local/digieranative/client/tests
git commit -m "feat(tiptap): replace custom ribbon with official UI surface"
```

---

### Task 4: Fix plain-text multiline semantics at the parser boundary

**Files:**
- Modify: `public/local/digieranative/client/src/paste.js`
- Test: `public/local/digieranative/client/tests/paste-sanitization.test.js`
- Test: `public/local/digieranative/client/tests/tiptap-adapter-standalone.mjs`

**Interfaces:**
- `createPasteHandler()` remains the editor paste hook.
- Plain text becomes semantic block content before dispatch.

- [ ] **Step 1: Replace raw `insertText(text)` for multiline plain text**

For plain text containing line breaks, split normalized `\r\n?` to `\n`, create paragraph nodes for each line, and preserve blank lines as empty paragraphs. Replace selection with a ProseMirror Slice/Fragment built from schema nodes instead of inserting one raw text node containing newlines.

- [ ] **Step 2: Keep single-line paste simple**

A single line may continue to use normal text insertion so marks/selection behavior remains natural.

- [ ] **Step 3: Run the exact regression**

```bash
npx vitest run tests/paste-sanitization.test.js
node tests/tiptap-adapter-standalone.mjs
```

Expected: the exact three-line fixture serializes to three paragraph nodes and round-trips to Native JSON.

- [ ] **Step 4: Commit**

```bash
git add public/local/digieranative/client/src/paste.js \
        public/local/digieranative/client/tests/paste-sanitization.test.js
git commit -m "fix(tiptap): preserve multiline plain-text structure"
```

---

### Task 5: Replace DOM-clone preview with structured Native preview

**Files:**
- Create: `public/local/digieranative/client/src/preview_renderer.js`
- Modify: `public/local/digieranative/client/src/index.js`
- Modify: `public/local/worksheetlibrary/amd/src/native_editor.js`
- Modify: `public/local/worksheetlibrary/amd/src/browser.js`
- Modify: `public/local/worksheetlibrary/detail.php`
- Generate: `public/local/worksheetlibrary/amd/build/native_editor.min.js`
- Generate: `public/local/worksheetlibrary/amd/build/browser.min.js` if this build exists in the plugin workflow
- Test: `public/local/digieranative/client/tests/preview-structure.test.js`

**Interfaces:**
- `NativeEditor.renderPreview(nativeJson, targetElement)` becomes the client preview API.
- Worksheet library stores the latest canonical Native JSON from `onUpdate` and provides it to publish preview.

- [ ] **Step 1: Implement `renderPreview(nativeJson, targetElement)`**

Use the existing Native adapter/schema specs to serialize structured nodes to DOM. Paragraphs must become `<p>`, `hardBreak` becomes `<br>`, headings/lists/alignment remain semantic. Do not use whitespace-only rendering tricks as the source of structure.

- [ ] **Step 2: Export preview renderer from `src/index.js`**

```js
export {renderPreview} from './preview_renderer.js';
```

- [ ] **Step 3: Publish current structured JSON from the mounted editor**

In `local_worksheetlibrary/amd/src/native_editor.js`, keep `latestCanonical` updated on every `onUpdate`, and dispatch a plugin-scoped event such as:

```js
element.dispatchEvent(new CustomEvent('wslib:native-document', {
  bubbles: true,
  detail: {nativejson: latestCanonical}
}));
```

Also expose the initial canonical document before the first edit.

- [ ] **Step 4: Replace `populatePublishPreview()`**

Remove `canvas.innerHTML` cloning. `browser.js` must read the latest canonical Native JSON and call `NativeEditor.renderPreview(...)` into `[data-region="publish-preview"]`.

- [ ] **Step 5: Run preview regression**

```bash
cd public/local/digieranative/client
npx vitest run tests/preview-structure.test.js
```

Expected for the user fixture: preview DOM contains three paragraph elements in order.

- [ ] **Step 6: Commit**

```bash
git add public/local/digieranative/client/src/preview_renderer.js \
        public/local/digieranative/client/src/index.js \
        public/local/worksheetlibrary/amd/src/native_editor.js \
        public/local/worksheetlibrary/amd/src/browser.js \
        public/local/worksheetlibrary/detail.php \
        public/local/digieranative/client/tests/preview-structure.test.js
git commit -m "fix(tiptap): render publish preview from Native JSON"
```

---

### Task 6: Verify save/autosave/reload and legacy Native compatibility

**Files:**
- Modify: `public/local/digieranative/client/tests/mount-contract.test.js`
- Modify: `public/local/digieranative/client/tests/autosave-standalone.mjs`
- Create: `public/local/digieranative/client/tests/legacy-native-open.test.js`
- Modify only if tests prove necessary: `public/local/digieranative/client/src/document_adapter.js`

**Interfaces:**
- Existing Native V1 JSON remains readable.
- `save` and `onUpdate` callbacks keep canonical Native JSON, not HTML.

- [ ] **Step 1: Add legacy document fixture**

Use an existing Native document containing paragraph, heading, ordered/unordered list, alignment, and at least one DIGIERA custom node. Mount it and assert no schema/migration exception.

- [ ] **Step 2: Assert autosave/manual save share the same canonical serializer**

The same editor state must produce byte-equivalent JSON object structure for both callback paths (ignoring JSON string whitespace).

- [ ] **Step 3: Simulate reload**

Serialize current Native JSON, destroy/unmount editor, remount from serialized JSON, and compare `toNativeDocument(editor.getJSON())` to the saved object.

- [ ] **Step 4: Run compatibility gate**

```bash
npx vitest run tests/mount-contract.test.js tests/legacy-native-open.test.js
node tests/autosave-standalone.mjs
node tests/tiptap-adapter-standalone.mjs
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add public/local/digieranative/client/tests \
        public/local/digieranative/client/src/document_adapter.js
git commit -m "test(tiptap): protect save reload and legacy Native compatibility"
```

---

### Task 7: Full source gate and immutable candidate

**Files:**
- Modify/generated: `public/local/digieranative/amd/build/native_editor.min.js`
- Modify/generated: worksheet library AMD build files changed by Tasks 3–5
- Create: Phase 5 gate workflow/tooling only on isolated packaging branch, not canonical product branch.

**Interfaces:**
- Produces: one exact canonical product commit and one immutable candidate package bound to that commit.

- [ ] **Step 1: Run all client tests**

```bash
cd public/local/digieranative/client
npm ci
npm test
```

Expected: all existing tests plus new Phase 5 tests PASS.

- [ ] **Step 2: Build twice deterministically**

```bash
npm run build
sha256sum ../amd/build/native_editor.min.js > /tmp/phase5-ui-build-1.sha
npm run build
sha256sum ../amd/build/native_editor.min.js > /tmp/phase5-ui-build-2.sha
diff -u /tmp/phase5-ui-build-1.sha /tmp/phase5-ui-build-2.sha
```

Expected: no diff.

- [ ] **Step 3: Run PHP/static/source gates from the established Phase 2/5 harness**

Required PASS evidence:

```text
PHP_LINT=PASS
Native validator/renderer=PASS
save/autosave=PASS
session/attempt/submit/grading immutability=PASS
no CDN=PASS
no core/RemUI diff=PASS
DB_UPGRADE_REQUIRED=NO
```

- [ ] **Step 4: Verify source scope**

```bash
git diff --name-only 3413597dd78b0cef6fbb1b5be59ac5b1ffe18504...HEAD
```

Only approved product roots and Spec Kit/plan files may differ. Product materialization onto canonical must include only product files under:

```text
public/local/digieranative/**
public/local/worksheetlibrary/**
public/mod/worksheetgrader/**   # only if a real regression requires it; otherwise unchanged
```

- [ ] **Step 5: Materialize verified product source to canonical Phase 5 source**

Create one atomic product commit after gates pass. Do not include workflow/tooling branch files.

- [ ] **Step 6: Build immutable candidate with existing service-user deploy/rollback templates**

Candidate must bind:

```text
SOURCE_COMMIT_SHA=<new canonical SHA>
PACKAGE_SHA256=<computed exact SHA>
MANIFEST_SHA256=<computed exact SHA>
DB_UPGRADE_REQUIRED=NO
```

- [ ] **Step 7: Commit/checkpoint Spec Kit**

Write a new versioned resume point with source SHA, CI run IDs, package hashes, tree hashes, and explicit `NOT DEPLOYED` until production evidence exists.

---

### Task 8: Two-node deploy and final Phase 5 browser/E2E acceptance

**Files:**
- No direct development in `/var/www/moodle/public`.
- Deployment uses the immutable artifact and verified deploy/rollback runner.

**Interfaces:**
- Web01: `vm-c47e0dd9` / `10.0.10.11`
- Web02: `10.0.10.12`
- Moodle root: `/var/www/moodle/public`
- Moodle CLI user: `www-data`
- Cron: Web01 only.

- [ ] **Step 1: Deploy exact candidate from Web01**

Reuse the proven Phase 4/5 runner flow: immutable hash verification, backup both nodes, maintenance, stop Web01 cron if active, deploy identical package to both nodes, tree-hash verify, PHP lint, purge caches, safe PHP-FPM reload, restore maintenance/cron state.

- [ ] **Step 2: Verify post-deploy runtime**

Required output:

```text
WEB01_TREE_HASHES=PASS
WEB02_TREE_HASHES=PASS
TWO_NODE_IDENTITY=PASS
CRON_AFTER=active
MAINTENANCE_AFTER=0
DB_UPGRADE_REQUIRED=NO
DEPLOY_STATUS=PASS
```

- [ ] **Step 3: Browser visual acceptance**

Re-check the five accepted product views, with the editor view now showing the official-Tiptap-based toolbar instead of the old seven-tab custom Ribbon.

- [ ] **Step 4: Exact multiline acceptance**

Enter/paste:

```text
PHASE 5 TEST
Đây là nội dung kiểm thử Native Editor.
Dòng kiểm tra autosave.
```

Verify all four states preserve three semantic paragraphs:

```text
Editor
Refresh/reopened editor
Preview
Published version
```

- [ ] **Step 5: Formatting behavior acceptance**

Verify selection-aware bold/italic/underline/strike, heading dropdown, ordered/bullet lists, alignment, link editing, undo/redo, normal Enter, and Shift+Enter. No visible dead controls are allowed.

- [ ] **Step 6: Save acceptance**

Edit text, wait for autosave, refresh, verify content; edit again, trigger manual save, refresh, verify content. Confirm save status returns to `Đã lưu` and conflict/error status remains explicit when induced by stale revision tests.

- [ ] **Step 7: End-to-end worksheet flow**

Run:

```text
Teacher: library -> create/edit Native -> save -> publish -> create session -> choose worksheet -> teams -> open
Student: open -> edit -> save -> refresh -> submit
Teacher: open immutable submission -> grade -> feedback
Student: verify grade/feedback
```

- [ ] **Step 8: Final Spec Kit checkpoint and completion verdict**

Only after all evidence is present, write a new resume/final checkpoint and report:

```text
PHASE5=PASS
ALL_5_PHASES=COMPLETED
```

If any browser/E2E item fails, fix it inside Phase 5, return to the relevant TDD task, rebuild, repackage, redeploy, and retest. Do not create Phase 6.
