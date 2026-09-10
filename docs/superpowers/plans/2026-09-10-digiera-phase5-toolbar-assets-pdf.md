# DIGIERA Phase 5 Toolbar, Assets & PDF Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Complete Phase 5 by turning the current Tiptap toolbar into a fully working File/Home/Insert/Layout/DIGIERA surface, adding real teacher/student image upload, smart answer insertion, math interaction, print, and direct PDF download.

**Architecture:** Keep Tiptap 3.x and Native JSON as the client/persistence contract. Use plugin-scoped React toolbar controls that call Tiptap commands; use direct authenticated Moodle plugin endpoints for binary asset upload and PDF download so no external-service registration or DB upgrade is required. Store assets in Moodle File API, reference them by safe opaque asset keys, render PDF from server-authoritative Native JSON through Moodle's bundled TCPDF 6.10.0, and keep teacher/student authorization paths separate.

**Tech Stack:** Moodle 5.1, PHP 8.3, React 18, Tiptap 3.31.3, Moodle File API, TCPDF 6.10.0, Vitest/JSDOM, existing Native validator/renderer.

**Spec:** `docs/superpowers/specs/2026-09-10-digiera-phase5-toolbar-assets-pdf-design.md`

## Global Constraints

- Production base for this closure is `ee5f5aea90e205260db6e0174eb459ec048b989f`.
- Work only on `digiera/tiptap-v1-phase5-official-ui` / isolated Web01 checkout until local gates pass.
- No GitHub Actions wait; use local RED/GREEN + full gate on Web01.
- No Moodle core or `theme/remui` changes.
- No runtime CDN.
- Native root remains `{"type":"worksheet","version":1,"content":[]}`.
- PHP validator/renderer remain server-authoritative.
- No DB schema/version migration; `DB_UPGRADE_REQUIRED=NO` is a hard gate.
- Phase 5 remains the final phase.

---

### Task 1: Lock toolbar and smart DIGIERA behavior with RED tests

**Files:**
- Create: `public/local/digieranative/client/tests/phase5-toolbar-completion.test.js`
- Modify: `public/local/digieranative/client/tests/official-toolbar.test.js`
- Test: same files

**Interfaces:**
- Produces contract for tabs `file/home/insert/layout/digiera`, working File actions, insert actions, and non-silent answer insertion.

- [ ] Write tests asserting visible working controls for Save, Print, Download PDF, Image, Horizontal Rule, Page Break, layout controls, Question/Short/Long/Answer Table/Math.
- [ ] Add behavioral tests where Short Answer, Long Answer, and Answer Table are invoked with selection outside any Question; each must create/resolve a Question and insert a linked answer node.
- [ ] Run focused Vitest and verify RED against current toolbar.
- [ ] Commit tests only.

### Task 2: Implement tabbed Tiptap toolbar and smart answer targeting

**Files:**
- Modify: `public/local/digieranative/client/src/ui/toolbar.js`
- Modify: `public/local/digieranative/client/src/ui/official_editor.js`
- Modify: `public/local/digieranative/client/src/editor.js`
- Modify: `public/local/digieranative/styles.css`
- Test: `public/local/digieranative/client/tests/phase5-toolbar-completion.test.js`

**Interfaces:**
- `mount(config)` gains optional callbacks `print`, `downloadPdf`, `uploadImage`, `requestMath` while preserving existing save/onUpdate API.
- Toolbar owns mutable Native `meta.layout` through an editor-side metadata object; layout changes emit canonical `onUpdate`.

- [ ] Implement tab strip File/Home/Insert/Layout/DIGIERA with no dead controls.
- [ ] File: Save calls canonical save, Print callback, Download PDF callback.
- [ ] Home: preserve current Tiptap command/active-state controls.
- [ ] Insert: Image callback, horizontalRule node, pageBreak node, link.
- [ ] Layout: A4 display, portrait/landscape, normal/narrow/wide margins, local zoom; persist orientation/margin in `meta.layout` and emit canonical update.
- [ ] DIGIERA: implement nearest-preceding-question resolver; if none exists create a Question, then insert linked answer node. Image reuses upload callback. Math uses requestMath callback.
- [ ] Run focused tests GREEN.
- [ ] Commit runtime + tests.

### Task 3: Real teacher/student image assets

**Files:**
- Create: `public/local/worksheetlibrary/native_asset.php`
- Modify: `public/local/worksheetlibrary/lib.php`
- Modify: `public/local/worksheetlibrary/amd/src/native_editor.js`
- Create: `public/mod/worksheetgrader/native_asset.php`
- Modify: `public/mod/worksheetgrader/lib.php`
- Modify: `public/mod/worksheetgrader/amd/src/native_attempt.js`
- Create tests under each plugin for upload authorization/validation contract.

**Interfaces:**
- POST endpoints accept `sesskey`, target id (`versionid` or `attemptid`), and multipart `image`; return JSON `{ok, assetKey, url, alt}`.
- Fileareas: `local_worksheetlibrary/nativeasset` keyed by version id; `mod_worksheetgrader/nativeattemptasset` keyed by attempt id.
- Only PNG/JPEG/WebP, max 5 MiB; SVG rejected.

- [ ] RED tests require real bridge callbacks and server MIME/size checks.
- [ ] Implement authenticated direct endpoints with `require_login()`, `require_sesskey()`, capability/ownership checks, MIME inspection, opaque key filename, File API storage, JSON response.
- [ ] Extend pluginfile callbacks to serve the two fileareas with access checks.
- [ ] Bridge toolbar Image to hidden file picker + `fetch(FormData)`; insert returned assetKey/url metadata and display localized error on failure.
- [ ] Ensure teacher and student paths are distinct.
- [ ] Run JS/PHP focused tests GREEN and PHP lint.
- [ ] Commit.

### Task 4: Math, preview asset resolution and print

**Files:**
- Modify: `public/local/worksheetlibrary/amd/src/native_editor.js`
- Modify: `public/mod/worksheetgrader/amd/src/native_attempt.js`
- Modify: `public/local/digieranative/client/src/preview_renderer.js`
- Modify: `public/local/digieranative/styles.css`
- Modify: `public/local/worksheetlibrary/styles.css`

**Interfaces:**
- Math callback prompts for formula source and inserts `mathBlock` when non-empty.
- Preview renderer can resolve image asset URLs supplied in Native image attrs/runtime map without persisting blob/data URLs.

- [ ] RED test math button mutates document only on non-empty confirmed input.
- [ ] Implement teacher/student math callbacks with visible cancellation/error semantics.
- [ ] Render managed images in editor/preview using runtime resolved URL while persisting only assetKey.
- [ ] Add plugin-scoped `@media print` rules that hide Moodle/editor chrome and print only worksheet content.
- [ ] Run focused GREEN tests.
- [ ] Commit.

### Task 5: Direct PDF download from saved Native JSON

**Files:**
- Create: `public/local/worksheetlibrary/pdf.php`
- Modify: `public/local/worksheetlibrary/amd/src/native_editor.js`
- Create: `public/local/worksheetlibrary/tests/pdf_contract_test.php`

**Interfaces:**
- GET `pdf.php?versionid=...` requires login + view access, loads saved version Native JSON, resolves version assets, calls `local_digieranative\document\renderer`, and streams `application/pdf` attachment through bundled TCPDF 6.10.0.

- [ ] RED contract test requires permission guard, Native renderer use, TCPDF include, `application/pdf`, and attachment filename.
- [ ] Implement server PDF endpoint using `/lib/tcpdf/tcpdf.php`, A4 orientation/margins from safe `meta.layout` defaults, UTF-8 font supported by TCPDF, rendered HTML, and no document mutation.
- [ ] Wire Download PDF callback to save-now first, then navigate to PDF endpoint after successful save.
- [ ] Wire Print separately to `window.print()`.
- [ ] Run PHP contract/lint GREEN.
- [ ] Commit.

### Task 6: Full local gate, deterministic package, two-node deploy, browser E2E

**Files:** generated AMD builds + versioned Spec Kit resume point after evidence.

**Interfaces:** same product-only deployment pattern used by prior Phase 5 fastlane.

- [ ] Run all Vitest, standalone adapter/autosave, Native PHP validator/renderer, new PHP contract tests, static contract, and PHP lint.
- [ ] Build AMD twice and require identical SHA256.
- [ ] Verify no `version.php`, `db/install.xml`, `db/upgrade.php`, Moodle core, or RemUI delta.
- [ ] Create product-only canonical commit with `[skip ci]`, deterministic package, backup Web01/Web02, deploy identical trees, purge caches as `www-data`, restore maintenance/cron, verify two-node identity.
- [ ] Browser teacher acceptance: smart answers, image+refresh, math, layout+refresh, preview, print, direct PDF, publish.
- [ ] Browser student acceptance: image+autosave+refresh+submit; teacher sees immutable submitted image; grade+feedback.
- [ ] Create a new versioned Phase 5 resume point with exact source/package/tree hashes and browser evidence; only then mark `PHASE5=PASS`.
