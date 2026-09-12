# DIGIERA Phase 5 — Cloudrity D1N POST-body diagnosis

Date: 2026-09-12
Status: CLOUDRITY CONFIRMED IN FRONT OF LMS / LARGE SCAN UPLOAD STILL BLOCKED / PHASE 5 NOT CLOSED

## New evidence

A D1N-aware probe was executed against public `https://lms.digiera.vn`.

- Cloudrity challenge cookie `D1N` was successfully extracted (`D1N_PRESENT=YES`).
- Reusing that cookie cleared the JavaScript challenge for a GET request: the request reached the Moodle origin path and returned the expected Moodle redirect/auth behaviour rather than a Cloudrity challenge page.
- Multipart POST requests carrying the D1N cookie returned `HTTP 400`, `Content-Type: text/html; charset=utf-8,gbk`, `Server: Cloudrity`, with an nginx `400 Bad Request` body for every probe size from 128 KiB through 4.8 MiB.
- Therefore the public path is definitely being rejected by Cloudrity before Moodle/PHP for this probe shape.

## Important interpretation

The D1N-aware size probe used an additional multipart `padding` field to vary request size, so it is not byte-for-byte equivalent to the real browser upload request. Because the real browser can successfully upload small QR images through the public path, this probe does **not** prove that Cloudrity blocks all multipart POSTs or that there is a simple body-size threshold at 128 KiB.

Combined with browser evidence (small QR upload succeeds, larger scan upload receives `HTTP 400 ... nginx`), the strongest current hypothesis is Cloudrity/WAF request-body inspection or a multipart/body rule that is triggered by specific payload characteristics rather than Moodle/Web01/Web02 limits.

## Confirmed non-causes

- Web01/Web02 Nginx body limits are 512 MiB or higher.
- PHP `upload_max_filesize` and `post_max_size` are 512 MiB.
- Origin `native_asset.php` accepts normal multipart uploads and logs successful HTTP 200 requests.
- Managed-image width rendering is fixed and browser-confirmed.

## Recommended resolution path

1. Preferred infrastructure fix: in Cloudrity/WAF, exempt or relax request-body inspection for `POST /local/worksheetlibrary/native_asset.php` while retaining TLS/DDoS controls; origin still enforces login, sesskey, author permission, draft state, <=5 MiB and PNG/JPEG/WebP MIME.
2. Application resilience fallback if Cloudrity policy cannot be changed immediately: client-side decode + re-encode selected images to a clean canonical image before upload (strip EXIF/metadata and normalize bytes), with a single automatic retry only when the first upload is rejected by the upstream `HTTP 400 ... Cloudrity/nginx` signature.
3. Lock the fallback with RED -> GREEN tests for teacher and student image adapters before deployment.
4. Retest the exact scan file in browser after the fix.
5. Continue student attempt -> save -> submit -> immutable teacher review -> grade/feedback acceptance.

Do not mark Phase 5 PASS until large-image upload and student E2E are browser-verified.
