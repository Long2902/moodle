# DIGIERA Media RC1 — Live R2 PDF Browser Smoke PASS

Date: 2026-09-08
Branch: `feature/digiera-media-v1-rc1-fasttrack`

## Evidence confirmed by user browser screenshots

The first real browser upload smoke after the two-node R2 single-PUT deployment is confirmed PASS for a PDF file.

Observed flow:

1. Open TinyMCE DIGIERA Media modal in a Moodle Page edit form.
2. Upload `2023_So goc cap chung chi TA_Q11_T01_T01.pdf` (435.4 KB).
3. Upload progress reaches 100% and UI reports `Upload R2 hoàn tất. Học liệu đã sẵn sàng để chèn.`
4. Uploaded media is selected in the library and metadata panel shows PDF, 435.4 KB, COURSE scope, READY status.
5. Insert into TinyMCE succeeds and editor shows the DIGIERA non-editable component with the uploaded media name.
6. Stored source is a DIGIERA marker: `[[digiera-ref:<REFERENCE_UUID>]]`.
7. Save Page succeeds.
8. Front-end renders the uploaded PDF through the central PDF.js viewer.
9. Browser DevTools shows the viewer receiving a `file=` query that points to the R2/CDN object under `https://cdn.digiera.vn/digiera/courses/.../general/pdf/<uuid>.pdf`.
10. PDF.js toolbar displays width-fit behavior (`Vừa chiều rộng`), matching the renderer contract `#zoom=page-width&scrollMode=page&spread=none`.
11. Reopening edit mode rehydrates the marker back to the DIGIERA editor component with the real media name.

Current verified chain:

`CREATE UPLOAD SESSION -> DIRECT R2 PUT -> FINALIZE -> LIBRARY READY -> INSERT -> MARKER SAVE -> CDN -> PDF.JS RENDER -> REOPEN REHYDRATE = PASS`

## Current product/runtime target

- Product pin: `465b2c884681d9179c2465c4d8dcf21797e99f4e`
- `local_digieramedia`: `2026090803`
- `tiny_digieramedia`: `2026090803`
- R2 bucket: `digiera`
- Public delivery domain: `https://cdn.digiera.vn`
- Single-PUT maximum: 100 MiB

## Remaining verification / implementation boundary

Still to verify in browser:

- JPG/PNG/WebP image upload + render + reopen.
- MP4/WebM video upload + render + reopen (under 100 MiB for RC1 single-PUT).
- MP3/WAV/M4A audio upload + render + reopen.
- Office/generic file upload + link render.
- Search/type/sort behavior with broader library data.
- Multi-file sequential upload behavior.
- Capability differences for uploader vs viewer/admin roles.

Known incomplete or not yet wired in the current TinyMCE modal/API surface:

- Advanced Admin/KTV panel is placeholder UI for version history, usage locations, replace, and lifecycle administration.
- No exposed external APIs yet for replace-version, trash/restore, usage-location management, or lifecycle administration.
- `recent` currently shares the ACTIVE library query path; it is not yet a true recently-used feed.
- Trash can be queried, but the modal currently has no trash/restore action API.
- Multipart upload above 100 MiB is deliberately deferred.
- R2 delete/copy/multipart operations remain outside the current single-PUT RC batch.

Implemented foundations that still require end-to-end acceptance testing:

- Backup reference collector/manifest.
- Restore reference remapping / independent-copy modes.
- Course Publisher behavior across Shared + Follow Current / Pin / independent-copy policies.
- Migration/lifecycle operations and permissions matrix.

## Next recommended actions

1. Browser smoke JPG/PNG.
2. Browser smoke MP4 under 100 MiB.
3. Browser smoke audio + generic/office link.
4. Then implement/wire the lifecycle Admin/KTV surface (replace/version/usage/trash/restore) and verify backup/restore + Course Publisher end to end.
