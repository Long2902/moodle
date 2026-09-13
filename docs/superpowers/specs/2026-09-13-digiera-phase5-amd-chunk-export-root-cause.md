# DIGIERA Phase 5 — AMD chunk export root cause

Date: 2026-09-13

## Browser/runtime evidence

The production AMD module `local_digieranative/native_editor` loads successfully, but `NativeEditor.uploadFileInChunks` is `undefined` at runtime. The module currently exposes 11 public keys including `normalizeImageForUpload`, `mount`, `schema`, `renderPreview`, and autosave/document APIs, but not the chunk uploader.

## Source/build evidence

- `client/src/editor.js` re-exports `uploadFileInChunks` from `chunk_upload.js`.
- `client/src/index.js` is the Rollup entry point and exports `normalizeImageForUpload` from `editor.js`, but omits `uploadFileInChunks`.
- `rollup.config.js` builds `src/index.js` into `amd/build/native_editor.min.js` as an AMD module.
- Therefore Rollup correctly tree-shakes the chunk uploader out of the public AMD API even though teacher/student bridges call `NativeEditor.uploadFileInChunks(...)`.

## Root cause

The chunk uploader was added below `editor.js` but was not added to the public export list in `src/index.js`, which is the actual AMD build entry point.

## TDD action

A RED contract was added to require both:

1. `src/index.js` publishes the `uploadFileInChunks` symbol; and
2. the built AMD artifact contains the named public export `uploadFileInChunks`.

No PHP chunk protocol, Moodle File API staging, Cloudrity threshold logic, Print/PDF, autosave, DB schema, Moodle core, or RemUI changes are required for this fix.

Next: run the RED contract, then add the missing entry-point export, rebuild, run focused/full gates, and only then deploy the AMD hotfix to both web nodes.
