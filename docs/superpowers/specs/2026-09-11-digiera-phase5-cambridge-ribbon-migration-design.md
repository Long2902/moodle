# DIGIERA Phase 5 — CambridgePlus Ribbon Migration Design

Status: DESIGN APPROVED IN CHAT / WRITTEN SPEC FOR USER REVIEW / NOT IMPLEMENTED
Date: 2026-09-11
Scope: Phase 5 only. This does not create a Phase 6.

## 1. Decision

The current custom DIGIERA/Tiptap toolbar will not receive further incremental UX patches. The approved direction is **Option B**:

- use the existing `CambridgePlus` Tiptap ribbon implementation as the primary editor/ribbon code source,
- adapt its visual composition to be compact and close to the Tiptap DOCX-editor demo shown by the user,
- keep DIGIERA Native JSON as the canonical document format,
- keep Moodle as the persistence/runtime platform,
- keep server PHP validator/renderer authoritative,
- keep current worksheet autosave, preview, publish, versioning, session, submission and grading flows,
- replace CambridgePlus-specific storage with a Moodle/DIGIERA asset adapter.

This is a migration of the editor shell and image interaction layer, not a replacement of the DIGIERA data model or Moodle workflow.

## 2. Frozen baselines

### DIGIERA Moodle

Current production canonical source after the last verified deploy:

`1b698d6a7b45a7200661df1305761910066fabcf`

Current Phase 5 development/spec branch at the time this design is written:

`digiera/tiptap-v1-phase5-official-ui`

Latest deployed Phase 5 gate already proved:

- focused green PASS,
- full Vitest 49/49 PASS,
- standalone validator PASS,
- standalone renderer PASS,
- TCPDF smoke PASS,
- deterministic AMD build PASS,
- PHP lint PASS,
- Web01/Web02 tree identity PASS,
- maintenance restored to 0,
- cron active,
- DB upgrade required: NO.

This migration must preserve those guarantees unless a new test explicitly proves an intentional change.

### CambridgePlus source

Pinned source repository:

`Long2902/CambridgePlus`

Pinned source commit:

`23739b6d71501e373ed06d819ca73a2f5360118e`

Primary source files to port/adapt:

- `apps/web/src/components/RibbonEditor.tsx`
- `apps/web/src/components/PictureEditorDialog.tsx`
- `apps/web/src/lib/CambridgeImageBlock.tsx`
- `apps/web/src/lib/inlineImageAssets.ts`
- relevant editor/image CSS and tests.

The migration must port working behavior from these files rather than re-create equivalent commands from memory.

## 3. Architecture

Target architecture:

```text
CambridgePlus Ribbon core
        |
        v
Tiptap 3 editor runtime
        |
        +--> DIGIERA-specific node controls
        |
        +--> Moodle ImageAdapter
        |
        v
DIGIERA Native adapter
        |
        v
Canonical Native JSON
        |
        +--> autosave/manual save
        +--> preview/publish/version
        +--> session/student attempt
        +--> server PHP validator/renderer
```

The client editor may use React/Tiptap state internally, but every persisted document must still serialize through the Native adapter.

Invariant:

`Editor semantics == Saved Native JSON == Preview semantics == Published semantics == Reopened semantics`

For attempts:

`Student editor semantics == Saved attempt JSON == Submitted snapshot semantics == Teacher review semantics`

## 4. Ribbon UX

The target visual language is the compact Tiptap DOCX-editor style shown by the user:

- single compact horizontal toolbar,
- icon-first controls,
- compact selects/dropdowns,
- no tall multi-row Office-style ribbon unless a control genuinely needs a second row,
- no dead or placeholder controls,
- active state visible,
- disabled state based on `editor.can()` where available,
- keyboard shortcuts remain Tiptap-native.

The initial control set is intentionally practical and is derived from the working CambridgePlus ribbon.

### Editing

- Undo
- Redo
- Clear formatting without destroying structural DIGIERA nodes

### Text

- Font family
- Font size
- Bold
- Italic
- Underline
- Strike
- Text color
- Highlight
- Paragraph
- Heading 1
- Heading 2
- Heading 3

### Paragraph

- Bullet list
- Ordered list
- Decrease indent
- Increase indent
- Align left
- Align center
- Align right
- Justify

### Insert

- Insert Picture
- Link / unlink
- Table insertion and basic row/column operations if compatible with current Native schema
- Horizontal rule
- Page break

### File/document actions

- Save now
- Print
- Download PDF

### Layout

- A4
- Portrait / landscape
- normal / narrow / wide margins
- zoom

Layout persistence continues in Native root `meta.layout`. Zoom remains view-only unless an explicit later requirement changes that.

## 5. DIGIERA controls

DIGIERA-specific authoring remains a dedicated control group integrated into the same compact toolbar:

- Câu hỏi
- Trả lời ngắn
- Trả lời dài
- Ô trả lời
- Công thức

There will no longer be a second independent image implementation under DIGIERA. `Insert Picture` is the single image entry point for both generic worksheet content and DIGIERA-authored question content.

Smart-answer rules remain:

1. if selection is inside a question, bind the answer node to that question;
2. otherwise use the nearest valid preceding question;
3. if none exists, create a new question and then create the requested answer node;
4. never fail silently.

Every action must either change editor state, open an interaction, or show a visible error.

## 6. Image architecture

The CambridgePlus image interaction model is retained because it already solves the UX problems the user prefers:

1. capture current editor caret/selection position,
2. choose a local image,
3. decode dimensions,
4. open picture editor,
5. allow crop/layout/alt/caption operations,
6. create or replace the asset,
7. insert/update the structural image node at the captured location,
8. resolve preview through the asset adapter.

The CambridgePlus persistence implementation is **not** copied as-is.

### Moodle ImageAdapter

A new Moodle-facing adapter provides the same conceptual interface as CambridgePlus `RibbonImageContext`:

```text
createAsset(file)
replaceAsset(assetKey, file)
resolvePreview(assetKey)
onAssetRecord(asset)
```

Teacher worksheet context maps these operations to Moodle/DIGIERA worksheet file storage.

Student attempt context maps them to attempt-scoped Moodle/DIGIERA file storage.

Native document nodes continue to store a stable logical `assetKey`, never raw binary and never a transient blob URL.

Preview URLs are runtime-only and must never become canonical document data.

### Image node metadata

The migration may extend the existing Native image node only where required to preserve the approved CambridgePlus image UX. Candidate fields include:

- assetKey
- alt
- title/caption
- width/widthPercent
- align
- crop metadata
- rotation

Any new Native attribute requires synchronized changes in:

- JS schema,
- PHP server schema/validator,
- PHP renderer,
- Native adapter,
- preview renderer,
- tests.

No attribute may be introduced only on the client.

If the existing Native schema can represent a behavior without extension, schema expansion must be avoided.

## 7. Moodle integration boundaries

The migration must not replace these existing flows:

- worksheet draft persistence,
- autosave API,
- manual save API,
- publish/version workflow,
- preview modal contract,
- session creation,
- student save/submit,
- immutable submission snapshot,
- grading/feedback,
- Moodle permissions and capability checks.

The new editor must plug into the same boundaries using canonical Native JSON and context-specific asset callbacks.

No direct editor code may write DB records by itself.

## 8. PDF and Print

The existing approved behavior remains:

- `Print` opens browser print,
- `Download PDF` downloads a server-generated PDF directly,
- PDF content is generated from persisted/canonical Native data rather than screenshotting editor DOM,
- image assets are resolved server-side through the Moodle/DIGIERA asset layer,
- current TCPDF path remains acceptable unless tests prove it cannot represent a required feature.

The DOCX-editor visual reference does not imply adoption of Tiptap Pro DOCX/PDF conversion services.

## 9. Dependency policy

Allowed:

- Tiptap 3 packages already used or free/open packages required by the CambridgePlus ribbon,
- React/ReactDOM bundled locally,
- local icon package such as `lucide-react` if bundled into the Moodle AMD artifact,
- image-editing dependency already proven by CambridgePlus if it can be bundled locally and its license is acceptable.

Not allowed:

- runtime CDN dependencies,
- Tiptap Cloud as a required runtime dependency,
- Tiptap Pro/Team-only extensions unless separately licensed and explicitly approved later,
- external asset-storage dependency replacing Moodle File API,
- remote fonts required for basic editor operation.

All production JS/CSS must be locally bundled.

## 10. Code-port rule

This migration follows a strict reuse rule:

- if CambridgePlus already has a working Tiptap command flow, port/adapt it;
- if Tiptap already provides a stable command, call that command directly;
- custom command code is reserved for DIGIERA-specific structures and Moodle integration boundaries;
- do not keep two parallel implementations of the same feature.

Examples:

- bold uses Tiptap `toggleBold`,
- alignment uses `setTextAlign`,
- table operations use Tiptap table commands,
- link uses Tiptap link commands with DIGIERA URL validation at persistence/server boundaries,
- picture UX is ported from CambridgePlus and connected to Moodle via ImageAdapter.

The existing Phase 5 custom toolbar becomes retired compatibility code and must not remain an active second toolbar path.

## 11. Migration compatibility

Existing Native worksheets produced by the current production build must reopen without manual conversion.

Existing published versions and existing attempts must remain readable.

If image metadata is extended, old image nodes must receive safe defaults when mounted.

A document opened and saved without touching a new feature must not suffer destructive normalization.

No DB schema migration is planned.

Hard gate:

`DB_UPGRADE_REQUIRED=NO`

If implementation unexpectedly requires `version.php`, `db/install.xml`, or `db/upgrade.php`, stop and return to design review instead of silently adding a DB upgrade.

## 12. TDD gates

Before implementation changes, add RED tests for the new migration contracts.

Required RED/GREEN coverage:

1. CambridgePlus-style toolbar control contract exists and old custom active toolbar path is absent.
2. font family and font size round-trip where supported by Native schema.
3. color/highlight round-trip or are explicitly disabled until server parity exists; no client-only persistence.
4. image insertion preserves captured caret/top-level semantic position.
5. image picker -> picture edit -> Moodle asset callback -> Native image node.
6. replace/crop/alt/caption operations update one structural image node rather than duplicate it.
7. teacher image survives save, refresh, preview and publish.
8. student image survives save, refresh and submit, then appears in teacher review.
9. smart answer controls never fail silently.
10. existing three-paragraph multiline regression remains green.
11. links remain server-safe and survive Native round-trip.
12. layout remains stable across rapid sequential changes.
13. autosave and manual save use the same Native serializer.
14. PDF renders persisted content and managed images.
15. legacy Native documents reopen without schema loss.

Full existing tests remain mandatory after focused migration tests.

## 13. Build and deployment gates

Development loop remains local on Web01 isolated source/worktree; do not wait for GitHub Actions unless specifically needed.

Before production mutation:

- focused migration tests PASS,
- full Vitest PASS,
- standalone validator PASS,
- standalone renderer PASS,
- static contracts PASS,
- deterministic AMD build twice PASS,
- PHP lint PASS,
- no runtime CDN PASS,
- no core/RemUI mutation PASS,
- no DB/version delta PASS,
- product-only package deterministic PASS.

Deployment remains package-bound to both nodes:

- backup Web01 and Web02,
- maintenance on,
- cron stopped on Web01 only,
- identical package to both nodes,
- tree hashes equal expected package trees,
- Moodle bootstrap both nodes,
- purge caches,
- restore maintenance off,
- restore cron active,
- automatic code rollback on post-mutation failure.

## 14. Browser acceptance

Phase 5 does not pass until browser acceptance proves all of the following:

Teacher editor:

- compact CambridgePlus-derived toolbar renders correctly,
- no legacy custom toolbar appears,
- font/format/list/alignment/link work,
- Insert Picture opens picker and picture editor,
- inserted image appears at intended document position,
- crop/replace/alt/caption behaviors work where included,
- save and autosave survive refresh,
- layout survives refresh,
- Preview matches editor semantics,
- Print opens correctly,
- PDF downloads and displays content/images,
- Publish succeeds.

Student attempt:

- worksheet opens,
- editable answer behavior works,
- student image insert works,
- save survives refresh,
- submit succeeds.

Teacher review:

- submitted structure is immutable,
- student image resolves,
- grade and feedback work.

Only after these are visually verified may the project record:

```text
PHASE5=PASS
ALL_5_PHASES=COMPLETED
```

## 15. Explicitly out of scope

This migration does not add:

- Tiptap Team/Pro DOCX editor services,
- Tiptap Cloud collaboration,
- Yjs/Hocuspocus,
- DOCX import/export,
- Search/Replace,
- full Office/ONLYOFFICE hardening,
- unrelated Moodle theme/core changes,
- DB schema migration.

These may be separate future work and must not block this Phase 5 migration.

## 16. Rollback

The currently verified production canonical `1b698d6a7b45a7200661df1305761910066fabcf` remains the rollback baseline until the CambridgePlus-ribbon candidate passes browser acceptance.

Deployment rollback is code-only because this design requires no DB migration.

A new Phase 5 resume checkpoint must be written after successful deployment and again after final browser/E2E acceptance. Existing resume points must not be overwritten.
