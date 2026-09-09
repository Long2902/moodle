# DIGIERA Phase 5 — Official Tiptap UI Migration Design

Date: 2026-09-09
Status: DESIGN APPROVED IN CHAT / WRITTEN SPEC REVIEW PENDING
Base production source: `3413597dd78b0cef6fbb1b5be59ac5b1ffe18504`
Working branch: `digiera/tiptap-v1-phase5-official-ui`

## 1. Problem statement

Phase 5 browser acceptance proved that the Native editor uses real Tiptap 3.x as its editing engine, but the visible Ribbon is a DIGIERA custom implementation. Several controls are placeholders or only partially model Word/Tiptap behavior. Browser acceptance also exposed a real structural bug: multiline plain-text input can be stored/rendered as newline characters inside a text node, while preview clones editor DOM into a different CSS context, causing lines to collapse into one paragraph.

The editor therefore passes low-level Tiptap contracts but does not yet meet the intended product quality for Word-like authoring, predictable rich-text behavior, or editor/preview parity.

## 2. Decision

Replace the custom Ribbon UI as the primary authoring surface with an editor UI based on the official Tiptap Simple Editor / Tiptap UI component patterns. Keep Tiptap as the authoritative client editing engine and keep DIGIERA-specific worksheet semantics as custom Tiptap extensions.

The official Tiptap UI layer is the baseline for generic rich-text behavior. DIGIERA only adds worksheet-domain features that Tiptap does not provide.

No Moodle core or RemUI code is modified. No runtime CDN is allowed. All editor code and dependencies are bundled into the plugin build.

## 3. Target architecture

```text
Moodle local_digieranative
  -> local bundled React editor application
       -> Tiptap Editor 3.x
            -> StarterKit / official extensions
            -> official Tiptap Simple Editor UI patterns/components
            -> DIGIERA worksheet extensions
                 - question
                 - short answer
                 - long answer
                 - answer table
                 - image asset hook
                 - math block / inline math
                 - page break / worksheet-specific nodes
       -> Native document adapter
       -> autosave/manual save callback
       -> preview renderer from editor JSON

local_worksheetlibrary
  -> mounts Native editor
  -> persists optimistic-revision Native JSON
  -> preview/publish use structured document JSON
  -> publish freezes immutable version
```

## 4. UI and behavior scope

### 4.1 Generic Tiptap behavior

The replacement editor surface must provide working, selection-aware controls for the generic authoring behaviors required in Phase 5:

- undo / redo
- bold / italic / underline / strike
- paragraph and heading selection
- bullet list / ordered list
- text alignment
- normal paragraph and hard-break semantics
- keyboard shortcuts supplied by Tiptap/ProseMirror
- link insert/edit/remove
- image insertion hook wired to DIGIERA assets

Search/replace is explicitly deferred from this Phase 5 migration. It may be added later without blocking completion of the approved 5-phase scope.

Controls must reflect active selection state where applicable. We must not keep visible placeholder controls that have no functional implementation.

### 4.2 DIGIERA-specific behavior

Preserve and integrate existing custom worksheet nodes/extensions:

- question
- shortAnswer
- longAnswer
- answerTable
- image
- mathBlock / mathInline
- teacher-note / rubric / page-break style native nodes already supported by the schema where applicable

DIGIERA-specific controls are presented as an additional toolbar group/menu adjacent to the official Tiptap controls. They do not need to imitate Microsoft Word if doing so would weaken usability or maintainability.

### 4.3 Save behavior

The editor JSON is the single client source of truth.

- autosave is triggered from Tiptap `onUpdate`
- manual save calls the same serializer/persistence path
- status indicates saving / saved / conflict / error
- reload after save must reconstruct the same Tiptap document
- optimistic revision semantics remain authoritative on the server

## 5. Multiline and document-structure correctness

Plain-text multiline paste/input must not be persisted as one text node containing newline characters.

Required normalization:

- normal Enter creates a paragraph boundary
- Shift+Enter creates a `hardBreak`
- multiline plain-text paste becomes Tiptap block structure, with each non-empty newline-delimited line becoming a paragraph and blank lines preserved as empty paragraphs
- HTML paste continues through sanitization and Tiptap/ProseMirror parsing

Regression fixture:

```text
PHASE 5 TEST
Đây là nội dung kiểm thử Native Editor.
Dòng kiểm tra autosave.
```

The editor, persisted JSON, preview, and reopened editor must all preserve the intended three-paragraph structure.

## 6. Preview and publish correctness

The current preview strategy of copying `canvas.innerHTML` is removed as the authoritative preview path.

Preview must be generated from the same structured document used by the editor:

1. obtain current Tiptap JSON
2. convert to canonical Native JSON
3. render preview from Native JSON using the existing DIGIERA semantic renderer contract
4. never depend on editor-only whitespace CSS to preserve document structure

Expected invariant:

```text
Editor semantic structure == Saved Native JSON == Preview semantic structure == Published semantic structure
```

Visual scaling may differ between editor and preview, but paragraphs, headings, lists, alignment, tables, worksheet nodes, and line breaks must not change meaning.

## 7. Packaging approach

The existing Rollup AMD build remains the Moodle delivery mechanism.

The replacement editor uses React/ReactDOM locally with official Tiptap UI component patterns. React/ReactDOM, Tiptap dependencies, and the required Rollup build support are bundled into `local_digieranative` and emitted as the existing Moodle AMD artifact. No external CDN or remote runtime dependency is introduced.

The output must remain loadable through Moodle AMD on Moodle 5.1 / PHP 8.3 / Edwiser RemUI 5.2.2.

Generated build output remains deterministic and is verified by build-twice hash comparison.

## 8. Source boundaries

Primary expected source changes:

- `public/local/digieranative/client/package.json`
- `public/local/digieranative/client/package-lock.json`
- `public/local/digieranative/client/rollup.config.js`
- `public/local/digieranative/client/src/editor.js`
- `public/local/digieranative/client/src/ribbon.js` — removed from primary runtime or retained only as a compatibility shim with no visible placeholder controls
- new React/Tiptap UI component files under `public/local/digieranative/client/src/`
- `public/local/digieranative/client/src/paste.js`
- `public/local/digieranative/styles.css`
- generated `public/local/digieranative/amd/build/native_editor.min.js`
- `public/local/worksheetlibrary/amd/src/browser.js` for structured preview integration
- generated worksheetlibrary AMD build if source changes
- tests under the two plugin test trees

No changes are planned to Moodle core, `theme/remui`, or unrelated plugins.

No DB schema migration is planned. `DB_UPGRADE_REQUIRED=NO` is a hard release gate. If implementation proves a DB migration is necessary, deployment stops and this design must be revised before any production action.

## 9. Compatibility constraints

Existing contracts remain authoritative unless explicitly replaced by this design:

- canonical Native root: `{"type":"worksheet","version":1,"content":[]}`
- PHP validator/renderer remain server-authoritative
- worksheet library optimistic revision persistence remains authoritative
- publish freezes immutable versions
- worksheet grader session/submission/grading behavior is unchanged
- backup/restore support in `mod_worksheetgrader` is not weakened

The migration must read existing Native documents created before this UI change.

## 10. TDD and regression gates

Implementation starts with failing tests for the observed defects and target behavior.

Minimum RED tests before implementation:

1. multiline plain-text paste produces multiple paragraph nodes, not one raw-newline text node
2. preview is generated from structured Native JSON rather than `canvas.innerHTML`
3. visible dead/placeholder Ribbon controls are absent from the final editor UI
4. selection-aware formatting controls invoke Tiptap commands and reflect active state
5. existing DIGIERA extension nodes still serialize/deserialize through the Native adapter
6. legacy Native documents created before this migration still mount successfully

GREEN gate requires:

- all existing Vitest tests pass
- new editor UI tests pass
- multiline regression passes
- save/autosave/reload persistence passes
- Native adapter round-trip passes
- deterministic build passes twice with identical output
- PHP lint and existing server validator/renderer tests pass
- no CDN references
- no core/RemUI diff
- no DB schema delta

## 11. Production rollout inside Phase 5

This is still Phase 5; there is no Phase 6.

Release sequence after source gates pass:

1. materialize verified product source onto canonical Phase 5 source
2. build immutable candidate package
3. reuse the already verified service-user deployment runner (`www-data` for Moodle CLI)
4. backup both Web01 and Web02
5. maintenance + cron safeguards
6. deploy identical package to both nodes
7. verify exact tree hashes on both nodes
8. restore runtime state
9. browser acceptance

Browser acceptance must re-check:

- library list
- create Native worksheet
- official-Tiptap-based editor UI
- multiline editing/paste
- autosave + refresh persistence
- manual save
- preview parity
- publish success
- session -> student save -> submit -> teacher grade/feedback

## 12. Rollback

Because this design has no DB migration, rollback is code-only using the predeploy backups on both nodes and the established rollback runner.

Rollback must be triggered if:

- Moodle bootstrap fails
- either node tree hash differs
- editor fails to mount
- existing Native documents fail to open
- save/publish contract regresses
- browser acceptance exposes a blocking regression

## 13. Phase 5 completion criteria

Phase 5 may be marked PASS only when all of the following are evidenced:

- official-Tiptap-based editor UI is running in production on both nodes
- generic formatting behavior works without visible dead/placeholder controls
- multiline text preserves structure through editor -> save -> preview -> publish
- autosave and manual save survive refresh
- both nodes are identical
- maintenance is off and Web01 cron is active after deployment
- end-to-end worksheet/session/student/grade flow passes

Only then report:

```text
PHASE5=PASS
ALL_5_PHASES=COMPLETED
```
