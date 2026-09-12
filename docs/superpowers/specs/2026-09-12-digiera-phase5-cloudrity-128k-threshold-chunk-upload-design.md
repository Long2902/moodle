# DIGIERA Phase 5 — Cloudrity 128 KiB threshold / chunk upload design

Date: 2026-09-12
Status: DESIGN LOCKED / RED PENDING
Phase: 5 only (no Phase 6)

## Browser/proxy evidence

Production browser still fails for the same scanned image with `HTTP 400 ... nginx` after the JPEG-normalize-and-retry hotfix.

A D1N-aware public multipart threshold probe against `lms.digiera.vn` established the real boundary:

- image payloads 1, 4, 8, 16, 32, 48, 64, 80, 96 and 112 KiB reach Moodle (HTTP 303 from Moodle through Cloudrity),
- 128 KiB and every tested larger payload (160, 192, 256, 384, 512, 768, 1024 KiB) are rejected by Cloudrity with `HTTP 400`, `text/html`, `400 Bad Request nginx`.

Because multipart boundaries and form fields add overhead, the safe direct-file threshold must remain below 128 KiB. The implementation target is 96 KiB for direct multipart uploads and 64 KiB per chunk for chunked uploads.

## Root cause

Cloudrity/front WAF has an effective request-body ceiling around 128 KiB for this route. Moodle, PHP-FPM and the origin Nginx are not the limiting layer. Browser re-encoding cannot solve this reliably because a normalized scan remains far above the proxy body ceiling.

## Design

### Client

1. Keep normal multipart upload for files <= 96 KiB.
2. For files > 96 KiB, use chunk upload immediately (do not intentionally trigger a Cloudrity 400 first).
3. If a <=96 KiB direct upload nevertheless receives upstream non-JSON HTTP 400, fall back to chunk upload once.
4. Shared Native client helper: `NativeEditor.uploadFileInChunks`.
5. Fixed chunk payload: 64 KiB (`65536` bytes), sequential requests.
6. Chunk request body is raw `application/octet-stream`; metadata is carried in query parameters.
7. Keep the product max at 5 MiB.

### Server

1. Shared accumulator service in `local_digieranative`: `classes/service/chunk_upload_service.php`.
2. Staging uses Moodle File API, not node-local `/tmp`, because requests may land on either Web01 or Web02.
3. File area: `local_digieranative/nativechunk`, system context, isolated by user + scope + random upload id.
4. Validate upload id, chunk index/count, declared total file size and actual chunk size.
5. Reassemble only when all chunks are present; hard cap final bytes at 5 MiB.
6. Delete staged chunks after successful reassembly and clean stale chunks opportunistically.
7. Teacher and student endpoints keep their existing authentication, capability and editability checks on every chunk request.
8. Final assembled bytes are MIME-sniffed server-side (PNG/JPEG/WebP only) before storing through Moodle File API.
9. Teacher create/replace and student create/replace preserve all existing asset-key semantics.

## Invariants

- No DB schema/version change.
- No Moodle core or RemUI mutation.
- No bypass of Moodle auth/capability/session rules.
- No node-local chunk state.
- Canonical Native JSON remains unchanged.
- Product image limit stays 5 MiB.
- Direct small-image path remains compatible.
- Print/PDF/autosave/ribbon semantics are out of scope and must not regress.

## TDD gate

RED must prove the following are currently absent:

- shared browser `uploadFileInChunks` helper,
- 96 KiB direct threshold + 64 KiB raw chunk path in both teacher/student bridges,
- shared File-API chunk accumulator,
- chunk handling in both PHP endpoints.

Only after observed RED may implementation start.
