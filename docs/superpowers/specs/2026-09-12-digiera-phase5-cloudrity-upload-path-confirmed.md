# DIGIERA Phase 5 — Cloudrity upload path confirmed

Date: 2026-09-12
Status: CLOUDRITY CONFIRMED ON PUBLIC LMS PATH / SIZE THRESHOLD NOT YET ISOLATED / PHASE 5 NOT CLOSED

## New probe evidence

A multipart POST probe was sent to the same LMS upload endpoint through three routes:

- PUBLIC: `https://lms.digiera.vn/...`
- WEB01 direct: `10.0.10.11` via `--resolve`
- WEB02 direct: `10.0.10.12` via `--resolve`

Probe sizes: 128 KiB, 512 KiB, 1 MiB, 1.5 MiB, 2 MiB, 3 MiB, 4 MiB, 4.8 MiB.

### Public route

Every PUBLIC request was intercepted before Moodle and returned:

- HTTP 200
- `Server: Cloudrity`
- `Content-Type: text/html; charset=utf-8,gbk`
- JavaScript challenge that sets `D1N=...` and reloads the page

This confirms `lms.digiera.vn` is actively protected/terminated by Viettel Cloudrity/WAAP on the public path.

The probe without a valid Cloudrity browser challenge cookie therefore cannot yet identify a body-size threshold: all PUBLIC probes are challenged before normal request handling.

### Direct Web01 route

Every payload up to 4.8 MiB reached Web01 and returned Moodle/nginx response HTTP 303. This proves the origin path accepts multipart bodies at least up to the plugin's 5 MiB ceiling; local Web01 Nginx/PHP body limits are not the blocker.

### Direct Web02 route

Direct HTTPS connection from Web01 to `10.0.10.12:443` failed with curl code 7 in this probe. This does not affect the existing two-node production deployment evidence because Web02 is otherwise reachable for deployment/SSH and receives LMS traffic through the front path.

## Current diagnosis

- Managed image width in the Native editor: PASS in production.
- Moodle image endpoint and origin-side body limits: PASS.
- Large scan browser upload: still FAIL with Cloudrity/nginx HTTP 400 before the request reaches Moodle origin.
- Public LMS path definitely traverses Cloudrity WAF/anti-bot.
- Exact blocking dimension is not yet isolated: could be Cloudrity body-size policy, multipart/file inspection, WAF rule, or anti-bot/request classification.

## Next diagnostic

Repeat the public multipart probe after first satisfying the Cloudrity JavaScript D1N challenge automatically. Compare the challenged public path against direct Web01 across increasing multipart sizes. If the challenged public route begins returning origin/Moodle responses and then changes to Cloudrity HTTP 400 at a specific size, that gives the exact Cloudrity size threshold. If it passes all sizes, retest with the actual problematic scan image to identify a file-content/multipart WAF rule.

Do not modify Web01/Web02 Nginx/PHP configuration based on current evidence.

Do not mark Phase 5 PASS until large-image upload and final student attempt -> submit -> immutable teacher review -> grading acceptance are complete.
