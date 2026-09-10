# DIGIERA Phase 5 Toolbar, Assets & PDF Design

Status: USER-DESIGN-APPROVED / SPEC-REVIEW-PENDING

## Context

The current Phase 5 production candidate already uses Tiptap as the editor engine, preserves multiline paragraph structure in Preview, autosaves, refreshes correctly, and publishes successfully. Browser acceptance identified two remaining product gaps:

1. The current toolbar is functionally too small for a worksheet authoring/answering workflow.
2. DIGIERA-specific controls are partially wired: Question works, while Short Answer / Long Answer / Answer Table depend on the selection being inside a Question and otherwise fail silently; Image and Math currently only emit hooks, and the Worksheet Library / Student Attempt bridges do not yet implement the corresponding interaction.

This spec extends Phase 5 only. It does not create Phase 6.

## Goals

- Keep Tiptap as the authoritative client editor engine.
- Provide a fuller, Word-like but Tiptap-native toolbar with only working controls.
- Make DIGIERA worksheet controls deterministic and non-silent.
- Support real image upload for both teacher worksheet drafts and student attempts.
- Provide Print and direct PDF download as separate actions.
- Add page-layout controls without DB schema migration.
- Keep Native JSON as the content contract and the PHP validator/renderer server-authoritative.

## Non-goals

- DOCX export.
- PPTX/XLSX export.
- Cloud collaboration/Yjs.
- AI authoring controls.
- Replacing Moodle file storage with external CDN/R2 in this Phase 5 closure.
- Introducing new DB tables or changing plugin versions solely for schema migration.

## Toolbar information architecture

The editor surface will expose grouped tabs/sections:

### File

- Save now
- Print
- Download PDF

`Save now` calls the same canonical Native JSON save path as autosave. `Print` invokes a print-optimized view. `Download PDF` requests a server-generated PDF from the latest saved Native JSON/version or attempt state, rather than rasterizing the editor screen.

### Home

- Undo / Redo
- Bold / Italic / Underline / Strike
- Paragraph / Heading 1 / Heading 2 / Heading 3
- Bullet list / Ordered list
- Align left / center / right / justify
- Link add/edit/remove

All controls use Tiptap commands and active state. No placeholder controls are rendered.

### Insert

- Image
- Horizontal rule
- Page break
- Link

Table insertion may remain outside this closure unless already safe in the current Native schema/runtime. No dead control will be shown.

### Layout

- Paper size: A4 (Phase 5 only)
- Orientation: portrait / landscape
- Margins: normal / narrow / wide
- Zoom: editor-view-only control

Persisted layout information is stored under Native document `meta.layout`, for example:

```json
{
  "meta": {
    "layout": {
      "paper": "A4",
      "orientation": "portrait",
      "margin": "normal"
    }
  }
}
```

Zoom is not persisted into published content unless explicitly needed; it is a local editing preference.

## DIGIERA controls

Visible controls:

- Question
- Short answer
- Long answer
- Answer table
- Image
- Math

### Smart answer-targeting rule

Short Answer, Long Answer and Answer Table must never fail silently.

When invoked:

1. If the selection is inside a Question node, use that Question ID.
2. Otherwise locate the nearest preceding Question in document order.
3. If none exists, create a new Question node at/near the current selection and use its generated Question ID.
4. Insert the requested answer node linked to that Question ID.
5. Move/focus selection to a sensible position after insertion.

If insertion is impossible because the document is read-only or invalid, show a visible, localized user message instead of doing nothing.

## Teacher image upload

Teacher-side image insertion must be real, not a placeholder event.

Flow:

1. User clicks Image in Insert or DIGIERA.
2. Open a local file picker restricted to safe image MIME types.
3. Upload through a Moodle external function owned by `local_worksheetlibrary`.
4. Validate capability, draft/version ownership, MIME type and size server-side.
5. Store the file in Moodle File API under a dedicated worksheet-native asset filearea keyed to the draft/version.
6. Return an opaque `assetKey` and preview URL.
7. Insert a Native `image` node referencing `assetKey`; never persist a transient blob/data URL.
8. Autosave/Save persists the JSON reference.
9. Refresh, Preview and Publish resolve the same asset.

Published versions remain immutable. Publication must freeze/retain the referenced asset set for that version.

## Student image upload

Student attempts use the same editor command but a different bridge/backend endpoint.

Flow:

1. Student clicks Image.
2. Upload is authorized against the active attempt and current user.
3. Store under `mod_worksheetgrader` File API area keyed to attempt.
4. Return `assetKey` plus preview URL.
5. Insert Native image node.
6. Autosave persists the JSON reference.
7. Refresh retains the image.
8. Submit freezes the attempt payload and its referenced assets so later teacher grading sees the submitted image unchanged.

Teacher and student upload endpoints remain separate so capability and ownership checks cannot be confused.

## Math interaction

The existing `digiera-native:request-math` event becomes a real bridge interaction. The minimal Phase 5 UI is a small modal/dialog to enter a formula source supported by the existing Native math node contract. Confirm inserts `mathInline` or `mathBlock` as supported by the current schema. Cancel performs no mutation.

## PDF download

The approved UX is:

- `Print`: separate button using print-specific styling and browser print dialog.
- `Download PDF`: direct `.pdf` response/download.

PDF generation requirements:

- Source of truth is saved Native JSON, not cloned editor DOM and not a screenshot.
- Preserve paragraphs, headings, marks, lists, alignment, horizontal rules, page breaks, DIGIERA question/answer boxes and images.
- Respect `meta.layout` for A4 orientation and margins.
- Use the same server-side Native rendering semantics wherever possible to avoid Preview/PDF divergence.
- Filename is sanitized from worksheet/item name or attempt context.
- Require appropriate view permission.
- PDF generation failures return a clear error; they must not mutate the document.

Implementation should prefer an already-present Moodle/server PDF facility if one exists in the deployment. If no suitable renderer is present, the implementation may bundle a compatible server-side PDF library inside the plugin only after checking license and deterministic deployment impact. This choice must be verified on Web01 before production deployment.

## Print behavior

Print uses a plugin-scoped print stylesheet:

- Hide Moodle chrome, inspector, toolbar and action buttons.
- Print only the worksheet content.
- Respect paper orientation/margins from Native metadata when browser support permits.
- Never alter or save document state merely because Print was invoked.

## Native JSON and validation

No DB migration is allowed. `DB_UPGRADE_REQUIRED=NO` remains a hard gate.

The root document remains:

```json
{"type":"worksheet","version":1,"content":[]}
```

Existing documents without `meta.layout` must continue to load with defaults:

- A4
- portrait
- normal margins

The PHP validator remains authoritative. If stricter `meta.layout` validation is introduced, it must be backward-compatible and reject unknown dangerous values while allowing existing metadata.

## Error handling

No visible control may silently do nothing.

- Missing Question context: auto-resolve/create Question.
- Image too large/invalid MIME/upload failure: localized visible error.
- Math invalid/cancel: no mutation; invalid formula shows visible error if validation exists.
- PDF generation failure: visible error page/message with no data mutation.
- Save conflict: retain existing optimistic revision conflict behavior.
- Read-only mode: mutation controls disabled.

## Security

- Moodle sesskey / external function security rules apply.
- Server-side capability checks are mandatory for uploads and PDF access.
- Never trust client MIME alone; inspect file metadata/server MIME.
- Disallow SVG unless explicitly sanitized; Phase 5 default is raster formats such as PNG/JPEG/WebP.
- Enforce file size limits.
- Asset keys are opaque/safe identifiers accepted by the Native validator.
- File serving uses pluginfile and normal Moodle access checks; no public unauthenticated asset URL is introduced.

## TDD acceptance contracts

RED tests must be added before runtime implementation for:

- Smart Short Answer / Long Answer / Answer Table insertion without preselected Question.
- No DIGIERA mutation control silently failing.
- Teacher Image hook has a real Worksheet Library bridge.
- Student Image hook has a real Worksheet Grader bridge.
- Invalid image upload rejected server-side.
- Valid image upload round-trips through save + reload.
- Submitted student image remains visible in immutable submission/grading view.
- File toolbar exposes Save, Print and Download PDF.
- Layout controls update canonical `meta.layout` and survive save/reload.
- PDF endpoint requires permission and returns `application/pdf` on success.
- Preview/PDF preserve multiline structure.
- No CDN runtime dependency.
- No Moodle core or RemUI modifications.
- No DB/version schema change.

## Fastlane development/deployment

To minimize operator work and avoid slow GitHub Actions:

1. Work in the existing isolated `/root/digiera-phase5-official-ui` checkout on Web01.
2. `git fetch/reset` only; do not reclone the Moodle repository.
3. Run focused RED/GREEN Vitest/PHP tests locally.
4. Run full local gate, PHP lint and deterministic client build.
5. Create product-only canonical commit with `[skip ci]`.
6. Package deterministically.
7. Verify currently deployed tree hashes before mutation.
8. Backup Web01/Web02 plugin trees.
9. Deploy identical package to both nodes.
10. Purge Moodle caches as `www-data`; restore cron/maintenance state.
11. Verify exact two-node tree identity.
12. Browser acceptance.

Any failure before production mutation stops the fastlane. Any failure after mutation triggers automatic code rollback.

## Browser acceptance

Teacher:

- Save, Print and Download PDF controls visible and functional.
- Insert Image uploads and survives refresh.
- Layout controls visibly affect paper and survive refresh where persisted.
- Each DIGIERA answer control works even when selection is outside a Question.
- Math interaction inserts usable content.
- Preview preserves semantic layout.
- Publish succeeds.
- Downloaded PDF preserves the same essential content/layout.

Student:

- Image upload works inside attempt.
- Autosave + refresh preserve text and image.
- Submit succeeds.
- Submitted text/image is immutable in teacher grading view.

Only after these browser/E2E checks and the existing session/grading flow pass may Phase 5 be closed.

## Release status rule

Do not mark `PHASE5=PASS` until production deploy, teacher acceptance, student attempt acceptance, PDF check, submit immutability and grading/feedback are all evidenced.
