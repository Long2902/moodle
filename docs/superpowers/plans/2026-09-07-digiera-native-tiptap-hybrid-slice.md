# DIGIERA Native Tiptap Hybrid Slice Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the first low-level ProseMirror editing slice with Tiptap 3 while preserving DIGIERA Native JSON, save/autosave, readonly, Moodle AMD and server contracts.

**Architecture:** Keep the existing DIGIERA Native canonical adapter/server validator/renderer. Introduce a small Tiptap client adapter that converts canonical worksheet JSON to Tiptap document JSON and back, mount Tiptap into the same host element, and keep the DIGIERA Ribbon as the presentation layer calling Tiptap commands. Do not migrate table/image/math/custom answer nodes in this first slice unless needed to preserve existing documents; unsupported custom nodes remain protected by the existing adapter path until their dedicated migration task.

**Tech Stack:** Moodle 5.1 plugin AMD, Vanilla JavaScript, Rollup, Vitest/jsdom, Tiptap 3.31.3, existing PHP Native validator/renderer.

**Spec:** `docs/superpowers/specs/2026-09-07-digiera-native-tiptap-hybrid-approved.md`

## Global Constraints

- No Moodle core changes.
- No Edwiser RemUI core/theme/navigation changes.
- No DB schema change.
- Canonical root stays `{"type":"worksheet","version":1,"content":[]}`.
- Existing PHP Native validator/renderer remain authoritative.
- Autosave/manual Save/revision/publish/session/attempt/submit/grading semantics remain unchanged.
- No CDN and no React runtime.
- Tiptap packages pinned to `3.31.3` for this slice.

---

### Task 1: Freeze Tiptap migration contracts

**Files:**
- Create: `public/local/digieranative/client/tests/tiptap-adapter.test.js`
- Create: `public/local/digieranative/client/tests/tiptap-mount.test.js`
- Modify: `public/local/digieranative/client/tests/ribbon-shell.test.js`

**Interfaces:**
- Consumes: existing canonical worksheet JSON and `mount(config)` API.
- Produces: frozen expectations for `toTiptapDocument()`, `toNativeDocumentFromTiptap()`, Tiptap mount, Ribbon Bold/Save and readonly behavior.

- [ ] Write RED tests asserting canonical paragraph/text/bold/italic/underline/heading/list round-trip.
- [ ] Write RED test asserting mount returns an editor facade with `state.doc`, `destroy()`, `setProps()` compatibility needed by current Moodle bridge.
- [ ] Update Ribbon contract to assert seven tabs and Tiptap-driven Bold active state.
- [ ] Run targeted tests; expected FAIL because Tiptap adapter/mount do not exist.
- [ ] Commit RED contracts.

### Task 2: Add minimal Tiptap dependencies and adapter

**Files:**
- Modify: `public/local/digieranative/client/package.json`
- Modify/generated on network-enabled runner: `public/local/digieranative/client/package-lock.json`
- Create: `public/local/digieranative/client/src/tiptap_adapter.js`

**Interfaces:**
- Produces: `toTiptapDocument(nativeDocument)` and `toNativeDocumentFromTiptap(tiptapJson, sourceDocument)`.

- [ ] Pin `@tiptap/core`, `@tiptap/pm`, `@tiptap/starter-kit` to `3.31.3`.
- [ ] Implement minimal lossless conversion for paragraph, text marks, heading, orderedList/bulletList/listItem.
- [ ] Preserve worksheet `version` and `meta` outside the Tiptap doc root.
- [ ] Reject unsupported unknown Tiptap nodes rather than silently dropping content.
- [ ] Run adapter tests GREEN.
- [ ] Commit adapter slice.

### Task 3: Mount Tiptap behind frozen Native API

**Files:**
- Modify: `public/local/digieranative/client/src/editor.js`
- Modify: `public/local/digieranative/client/src/index.js` only if export wiring is required.

**Interfaces:**
- Keep public `mount(config)` signature.
- Return facade exposes `state.doc.toJSON()`, `destroy()`, `setProps({dispatchTransaction})` compatibility expected by `local_worksheetlibrary/native_editor.js` until that bridge is migrated in Task 5.

- [ ] Write/confirm RED mount compatibility test.
- [ ] Instantiate `new Editor({element, extensions:[StarterKit], content})`.
- [ ] Implement the smallest facade needed by frozen consumers.
- [ ] Preserve readonly through Tiptap `editable` configuration.
- [ ] Run mount/readonly tests GREEN.
- [ ] Commit mount slice.

### Task 4: Rewire DIGIERA Ribbon to Tiptap commands

**Files:**
- Modify: `public/local/digieranative/client/src/ribbon.js`
- Modify: `public/local/digieranative/styles.css` only for active/disabled visual state.

**Interfaces:**
- `createRibbon({element, getEditor, readonly, save, documentJson})`.
- Bold: `editor.chain().focus().toggleBold().run()`.
- Undo/redo/list/heading use Tiptap commands where available.
- Save serializes through `toNativeDocumentFromTiptap(editor.getJSON(), documentJson)`.

- [ ] Verify Ribbon test RED against old low-level command path.
- [ ] Replace standard command plumbing with Tiptap command calls.
- [ ] Update active state on selection/transaction events using `editor.isActive()`.
- [ ] Keep seven approved tabs.
- [ ] Run Ribbon/Save/readonly tests GREEN.
- [ ] Commit Ribbon slice.

### Task 5: Migrate Moodle autosave bridge without changing server API

**Files:**
- Modify: `public/local/worksheetlibrary/amd/src/native_editor.js`
- Mirror/build AMD only after source is green.

**Interfaces:**
- Existing external function remains `local_worksheetlibrary_save_native_draft`.
- Existing payload remains `{versionid, expectedrevision, nativejson}`.
- Existing `createAutosaveController` remains unchanged.

- [ ] Add RED static/behavior contract that bridge reads canonical JSON from the Tiptap facade and calls the same autosave controller.
- [ ] Replace direct ProseMirror transaction interception with Tiptap update callback/facade event.
- [ ] Preserve status labels and conflict blocking.
- [ ] Run autosave/manual Save contracts GREEN.
- [ ] Commit bridge migration.

### Task 6: Network-enabled dependency/build gate

**Files:**
- Generated: `public/local/digieranative/client/package-lock.json`
- Generated: `public/local/digieranative/amd/build/native_editor.min.js`

**Interfaces:**
- Exact package versions pinned to `3.31.3`.

- [ ] Run `npm install --package-lock-only` or clean install on Web01/CI to regenerate lock deterministically.
- [ ] Run `npm ci`.
- [ ] Run full Vitest suite.
- [ ] Run Rollup build twice and compare AMD SHA256.
- [ ] Run PHP/static/legacy contracts.
- [ ] Commit generated lock/build only after deterministic gate passes.

### Task 7: Rebase Visual Milestone V1 and materialize atomically

**Files:**
- Include the already GREEN V1 Library/detail/templates/CSS/JS changed set.
- Include Tiptap client slice from Tasks 1–6.

**Interfaces:**
- No DB migration.
- GitHub source commit is one atomic materialization turn.

- [ ] Re-run V1 visual contracts and Tiptap contracts together.
- [ ] Confirm changed files do not include Moodle core or RemUI.
- [ ] Create one atomic commit on `digiera/preview-v01`.
- [ ] Confirm Fast CI PASS.
- [ ] Create UI-only two-node deployment package.
- [ ] Deploy identical plugin trees without `admin/cli/upgrade.php`.
- [ ] Run browser screenshot/visual comparison against approved HTML/mockups.

## Self-review

- Spec coverage: client Tiptap migration, server preservation, Ribbon, autosave, deterministic build and no-DB deployment all mapped.
- Placeholder scan: none.
- Type/interface consistency: public `mount(config)` and server save payload remain frozen through migration.
