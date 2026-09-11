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

Current Phase 5 development/spec branch:

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

The initial control set is derived from the working CambridgePlus ribbon.

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
- Decrease list indent
- Increase list indent
- Align left
- Align center
- Align right
- Justify

Paragraph indent outside lists is not introduced by this migration.

### Insert

- Insert Picture
- Link / unlink
- Insert table
- Add/delete row
- Add/delete column
- Delete table
- Horizontal rule
- Page break

Table insertion must use `withHeaderRow: false` because current Native V1 has `table/tableRow/tableCell` but no `tableHeader`. No client-only `tableHeader` node may be persisted.

### File/document actions

- Save now
- Print
- Download PDF

### Layout

- A4
- Portrait / landscape
- normal / narrow / wide margins
- zoom

Layout persistence continues in Native root `meta.layout`. Zoom is view-only and is not written to Native JSON.

## 5. DIGIERA controls

DIGIERA-specific authoring remains a dedicated control group integrated into the same compact toolbar:

- Câu hỏi
- Trả lời ngắn
- Trả lời dài
- Ô trả lời
- Công thức

There will no longer be a second independent image implementation under DIGIERA. `Insert Picture` is the single image entry point for both generic worksheet content and DIGIERA-authored question content.

Smart-answer rules:

1. if selection is inside a question, bind the answer node to that question;
2. otherwise use the nearest valid preceding question;
3. if none exists, create a new question and then create the requested answer node;
4. never fail silently.

Every action must either change editor state, open an interaction, or show a visible error.

## 6. Native text-style persistence contract

CambridgePlus uses Tiptap `TextStyle`, `FontFamily`, `FontSize`, `Color` and `Highlight`. DIGIERA Native must not persist Tiptap-internal `textStyle` marks directly.

The Native adapter will map Tiptap style state to explicit Native marks:

- existing `textColor {color}` remains the canonical color mark,
- add `fontFamily {family}`,
- add `fontSize {px}`,
- add `highlight {color}`.

Server PHP schema, validator and renderer must add the same three mark types in the same change.

Exact validation:

- `fontFamily.family` allowlist: `Arial`, `Calibri`, `Georgia`, `Times New Roman`, `Verdana`;
- `fontSize.px` allowlist: `10, 11, 12, 14, 16, 18, 20, 24, 28, 32, 36`;
- `textColor.color` and `highlight.color`: exactly `#RRGGBB` hexadecimal;
- unset/default style is represented by absence of the corresponding Native mark.

Adapter rule:

```text
Tiptap textStyle.color      <-> Native textColor.color
Tiptap textStyle.fontFamily <-> Native fontFamily.family
Tiptap textStyle.fontSize   <-> Native fontSize.px
Tiptap highlight.color      <-> Native highlight.color
```

The renderer must emit escaped inline styles/classes only from these validated values. No arbitrary CSS value may be persisted.

This is a Native JSON schema extension only and does not require a database schema change.

## 7. Image architecture

The CambridgePlus image interaction model is retained:

1. capture current editor caret/selection position,
2. choose a local image,
3. decode dimensions,
4. open Picture Editor,
5. allow crop/layout/alt/caption operations,
6. create or replace the asset,
7. insert/update the structural image node at the captured location,
8. resolve preview through the asset adapter.

The CambridgePlus persistence implementation is **not** copied as-is.

### Moodle ImageAdapter

A Moodle-facing adapter provides the same conceptual interface as CambridgePlus `RibbonImageContext`:

```text
createAsset(file)
replaceAsset(assetKey, file)
resolvePreview(assetKey)
onAssetRecord(asset)
```

Teacher worksheet context maps these operations to Moodle/DIGIERA worksheet file storage.

Student attempt context maps them to attempt-scoped Moodle/DIGIERA file storage.

Native document nodes store a stable logical `assetKey`, never raw binary and never a transient blob URL.

Preview URLs are runtime-only and must never become canonical document data.

### Exact Native image persistence

The existing Native `image` node remains the only image node. It is extended to preserve CambridgePlus picture-edit semantics with these attributes:

- `assetKey`: required stable logical asset identifier;
- `alt`: bounded text;
- `title`: existing optional title retained for backward compatibility;
- `caption`: bounded text;
- `width`: existing optional integer retained for backward compatibility;
- `widthPercent`: integer `10..100`, default `100`;
- `align`: `left | center | right`, default `center` for newly inserted Cambridge-style images;
- `cropX`: number `0..1`, default `0`;
- `cropY`: number `0..1`, default `0`;
- `cropW`: number `0..1`, default `1`;
- `cropH`: number `0..1`, default `1`;
- `rotation`: one of `0 | 90 | 180 | 270`, default `0`.

Cross-field crop validation must require:

- `cropW > 0`,
- `cropH > 0`,
- `cropX + cropW <= 1`,
- `cropY + cropH <= 1`.

For old image nodes missing the new attributes, the adapter/server renderer must apply safe defaults without rewriting the document merely because it was opened.

Synchronized implementation is mandatory in:

- JS schema/validation,
- PHP schema/validator,
- PHP renderer,
- Native adapter,
- Tiptap image extension/node view,
- preview renderer,
- PDF image rendering,
- tests.

No image field may exist only on the client.

This is a Native JSON schema extension only and does not require a DB migration.

## 8. Moodle integration boundaries

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

The new editor plugs into the same boundaries using canonical Native JSON and context-specific asset callbacks.

No direct editor code may write DB records by itself.

## 9. PDF and Print

Existing approved behavior remains:

- `Print` opens browser print,
- `Download PDF` downloads a server-generated PDF directly,
- PDF content is generated from persisted/canonical Native data rather than screenshotting editor DOM,
- image assets are resolved server-side through the Moodle/DIGIERA asset layer,
- current TCPDF path remains unless tests prove it cannot represent a required feature.

The DOCX-editor visual reference does not imply adoption of Tiptap Pro DOCX/PDF conversion services.

## 10. Dependency policy

Allowed:

- Tiptap 3 packages already used or free/open packages required by the CambridgePlus ribbon,
- React/ReactDOM bundled locally,
- `lucide-react` bundled locally for toolbar icons,
- `react-image-crop` bundled locally for the ported Picture Editor after license/package audit.

Not allowed:

- runtime CDN dependencies,
- Tiptap Cloud as a required runtime dependency,
- Tiptap Pro/Team-only extensions unless separately licensed and explicitly approved later,
- external asset-storage dependency replacing Moodle File API,
- remote fonts required for basic editor operation.

All production JS/CSS must be locally bundled.

## 11. Code-port rule

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

## 12. Migration compatibility

Existing Native worksheets produced by current production must reopen without manual conversion.

Existing published versions and existing attempts must remain readable.

Old image nodes receive runtime defaults for newly introduced picture metadata.

A document opened and saved without touching a new feature must not suffer destructive normalization.

No DB schema migration is planned.

Hard gate:

`DB_UPGRADE_REQUIRED=NO`

If implementation unexpectedly requires `version.php`, `db/install.xml`, or `db/upgrade.php`, stop and return to design review instead of silently adding a DB upgrade.

## 13. TDD gates

Before implementation changes, add RED tests for these migration contracts.

Required RED/GREEN coverage:

1. CambridgePlus-derived toolbar exists and old custom active toolbar path is absent.
2. font family round-trips through Tiptap -> Native -> server -> reopen.
3. font size round-trips through Tiptap -> Native -> server -> reopen.
4. text color/highlight round-trip and reject arbitrary CSS values.
5. image insertion preserves captured caret/top-level semantic position.
6. image picker -> picture edit -> Moodle asset callback -> Native image node.
7. replace/crop/alt/caption update one image node rather than duplicate it.
8. old image nodes mount with safe runtime defaults and remain readable.
9. teacher image survives save, refresh, preview, PDF and publish.
10. student image survives save, refresh and submit, then appears in teacher review.
11. smart answer controls never fail silently.
12. existing three-paragraph multiline regression remains green.
13. links remain server-safe and survive Native round-trip.
14. layout remains stable across rapid sequential changes.
15. autosave and manual save use the same Native serializer.
16. table insertion never introduces unsupported `tableHeader` nodes.
17. legacy Native documents reopen without schema loss.

Full existing tests remain mandatory after focused migration tests.

## 14. Build and deployment gates

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

## 15. Browser acceptance

Phase 5 does not pass until browser acceptance proves all of the following.

Teacher editor:

- compact CambridgePlus-derived toolbar renders correctly,
- no legacy custom toolbar appears,
- font/size/color/highlight/format/list/alignment/link work,
- table insertion/editing works without unsupported node types,
- Insert Picture opens picker and Picture Editor,
- image appears at intended document position,
- crop/replace/alt/caption survive refresh,
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
- student image edit metadata persists,
- save survives refresh,
- submit succeeds.

Teacher review:

- submitted structure is immutable,
- student image resolves with expected crop/layout,
- grade and feedback work.

Only after these are visually verified may the project record:

```text
PHASE5=PASS
ALL_5_PHASES=COMPLETED
```

## 16. Explicitly out of scope

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

## 17. Rollback

The currently verified production canonical `1b698d6a7b45a7200661df1305761910066fabcf` remains the rollback baseline until the CambridgePlus-ribbon candidate passes browser acceptance.

Deployment rollback is code-only because this design requires no DB migration.

A new Phase 5 resume checkpoint must be written after successful deployment and again after final browser/E2E acceptance. Existing resume points must not be overwritten.
