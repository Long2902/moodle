# DIGIERA Media RC1 — Multi-format Live R2 Browser Smoke PASS

Date: 2026-09-08
Branch: `feature/digiera-media-v1-rc1-fasttrack`

## User-verified browser evidence

The user reported and supplied browser evidence that the live R2 upload + library + insert/render flow now passes for the following RC1 formats/workloads:

1. JPG/PNG
2. MP4 under the current 100 MiB single-PUT limit
3. MP3/audio (browser evidence also showed WAV in the library)
4. DOCX/PPTX-class Office upload/link behavior
5. Multiple-file upload

Previously confirmed in the same RC1 line:

- PDF upload to Cloudflare R2
- finalize/READY metadata
- TinyMCE insert
- stored marker `[[digiera-ref:<UUID>]]`
- save/reopen metadata rehydration
- PDF.js render through `cdn.digiera.vn`
- PDF.js page-width behavior

## Current browser smoke status

`LIVE R2 UPLOAD -> FINALIZE -> LIBRARY -> INSERT -> SAVE -> RENDER/LINK -> REOPEN = USER-VERIFIED PASS`

for PDF, image, video, audio, Office-link, and multi-file upload paths within the RC1 single-PUT size limit.

## Console findings from supplied screenshot

The visible Console errors do not point at DIGIERA Media modules. The screenshot showed:

- `Manifest: Line: 1, column: 1, Syntax error.` from `mobile.webmanifest.php` — site/PWA manifest issue, outside the DIGIERA Media upload/render path.
- `Unchecked runtime.lastError: IO error: .../MANIFEST-000001...` — browser/extension runtime class of error.
- `content-script.js` / `onboarding.js` errors around `otherControls` — injected/content-script class of error, not DIGIERA Media AMD modules.
- `tiny_autosave/id_page: Skipping draft restoration. The editor is not empty.` — warning/info behavior, not a DIGIERA Media failure.

No visible Console error in the supplied evidence referenced `digieramedia`, `upload_client`, `core/ajax`, the R2 presigned PUT, finalize service, or PDF.js.

## Remaining functional work after this checkpoint

Highest-value next batch:

1. Replace media / create new version
2. Version history and Follow Current / Pin Current Version controls
3. Usage locations
4. Trash / restore / permanent delete lifecycle
5. Admin/KTV panel wiring

Then acceptance verification for:

- backup/restore reference remapping
- Course Publisher modes
- role/capability matrix
- migration flows
- true per-user “recently used” semantics

## Scope boundary

This checkpoint does not enable multipart upload. Files above the current 100 MiB single-PUT limit remain intentionally deferred to a later multipart batch.
