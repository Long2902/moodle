# DIGIERA Native Editor — Hybrid Tiptap Client Design APPROVED

Date: 2026-09-07
Branch: `digiera/preview-v01`
Status: **APPROVED / IMPLEMENTATION AUTHORIZED / PRODUCTION UNCHANGED**

This design extends without replacing prior DIGIERA Native/UI specifications.

## Decision

The operator approved **Hybrid Tiptap Client + Existing DIGIERA Server Contracts**.

## Locked architecture

### Client editor

Use Tiptap 3.x headless Editor in Vanilla JavaScript bundled by the existing Rollup pipeline.

Pin the first migration slice to the current synchronized Tiptap 3 release line:

- `@tiptap/core` 3.31.3
- `@tiptap/pm` 3.31.3
- `@tiptap/starter-kit` 3.31.3

Additional extensions may be added only when a concrete Ribbon feature needs them. The first migration slice must stay minimal.

### Server/data contracts preserved

The following remain authoritative and unchanged:

- canonical Native root `{"type":"worksheet","version":1,"content":[]}`;
- PHP Native validator and renderer;
- Moodle File API ownership;
- autosave/manual Save shared save lane;
- optimistic revision conflict handling;
- publish/version immutability;
- session snapshot;
- attempt save;
- submit freeze;
- grading immutable submission flow;
- legacy HTML/PDF/Office reachability.

No DB schema change is approved for this migration.

### UI ownership

Tiptap is the editing engine only. DIGIERA keeps the approved Word-like Ribbon and plugin-scoped visual system.

Approved Ribbon tabs remain:

`File | Home | Insert | Layout | Review | Math | View`

Moodle core, Edwiser RemUI core/theme files and global navigation remain untouched.

## Migration boundary

### Replace/reduce

- direct low-level ProseMirror editor construction;
- standard bold/italic/underline/heading/list/history command plumbing;
- direct active-state plumbing where Tiptap already provides `editor.isActive()`;
- direct command chains where Tiptap provides `editor.chain()` / `editor.commands`.

### Preserve/adapt

- canonical Native adapter;
- DIGIERA question/answer node semantics;
- server-side Native validation rules;
- autosave API contract;
- manual Save callback contract;
- readonly contract;
- existing Moodle AMD bridge.

## First vertical slice acceptance

Before migrating the full Native feature set, prove one real slice:

1. mount Tiptap into the existing Native host element;
2. load canonical DIGIERA Native JSON through an adapter;
3. preserve paragraph/text + bold/italic/underline + heading + ordered/bullet list;
4. keep the seven-tab DIGIERA Ribbon shell;
5. Home/Bold invokes Tiptap command and active state;
6. File/Save returns canonical DIGIERA Native worksheet JSON;
7. readonly disables mutation controls;
8. autosave controller contract remains unchanged;
9. Rollup produces the Moodle AMD bundle;
10. existing server/static contracts continue to pass.

Only after this slice is green should table/image/math/custom worksheet nodes be migrated incrementally.

## Test discipline

Migration is TDD-first.

- RED adapter/mount/Ribbon/save contracts before Tiptap production code.
- Run existing Native tests after each green slice.
- No claim of completion until npm/Vitest/Rollup runs on a network-enabled runner and deterministic build/static gates pass.

## Deployment discipline

- develop in isolated workspace;
- atomically materialize source to GitHub once the slice is locally/static green;
- run Fast CI and deterministic build;
- no DB upgrade;
- deploy identical plugin trees to Web01/Web02;
- visual browser acceptance remains separate.
