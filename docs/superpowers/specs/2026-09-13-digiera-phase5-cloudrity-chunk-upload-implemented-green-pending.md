# DIGIERA Phase 5 — Cloudrity chunk upload implemented, GREEN pending

## Status

Implementation is present on the development branch. Focused GREEN verification has not yet been supplied by the operator, so this checkpoint is **not** a production/deployed claim.

## Evidence leading to this change

Browser/public-path probing established a hard Cloudrity multipart boundary: image payloads through 112 KiB reached Moodle, while 128 KiB and above were rejected by Cloudrity with HTTP 400. The previous JPEG normalization retry therefore cannot reliably transport large images.

## Implemented design

- Direct multipart remains for files up to 96 KiB.
- Files above 96 KiB use sequential 64 KiB raw `application/octet-stream` chunks.
- `local_digieranative` exposes one shared browser `uploadFileInChunks()` helper.
- Teacher (`local_worksheetlibrary`) and student (`mod_worksheetgrader`) bridges use the same helper.
- Both endpoints accept `mode=chunk`, validate upload id/index/total/final size, and read raw bytes from `php://input`.
- `local_digieranative\service\chunk_upload_service` stages chunks in Moodle File API area `local_digieranative/nativechunk`, keyed per user and upload scope, so chunks can land on different web nodes.
- The accumulator re-reads staged chunks from shared File API, verifies ordering/final length, sniffs the final MIME, supports PNG/JPEG/WebP only, and cleans staged chunks.
- Final teacher/student assets are written with Moodle File API from assembled bytes.
- Product image cap remains 5 MiB.
- No DB schema/version migration is required.
- Existing small-file upstream-400 normalization fallback is preserved.

## TDD state

RED was confirmed before implementation:

- client chunk uploader missing;
- teacher/student chunk bridge missing;
- shared server chunk service missing.

Next required action: run the focused GREEN gate for client chunk contract, server chunk contract, teacher/student asset bridge contracts, endpoint/service PHP lint, and existing image regression tests.

## Production state

`PRODUCTION_MUTATION=NO` for this chunk implementation checkpoint. Production remains at the previously deployed canonical until GREEN and broader gates pass.
