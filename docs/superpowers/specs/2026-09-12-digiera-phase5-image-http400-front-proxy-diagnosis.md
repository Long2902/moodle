# DIGIERA Phase 5 — Image HTTP 400 front-proxy diagnosis

Date: 2026-09-12
Status: BROWSER IMAGE WIDTH PASS / LARGE IMAGE UPLOAD STILL BLOCKED BEFORE MOODLE / PHASE 5 NOT CLOSED

## Browser evidence

- Managed image runtime width fix is confirmed in production: a QR image set to 100% now expands to the expected content width.
- The larger scanned image still fails, but the browser now receives a readable error: `HTTP 400 ... Bad Request nginx` instead of a JSON parse exception.

## Node log evidence

- Web01 and Web02 both continue to record successful `POST /local/worksheetlibrary/native_asset.php` requests with HTTP 200 for normal image uploads.
- Neither Web01 nor Web02 records a matching `native_asset.php` HTTP 400 for the failed large-image attempt.
- The LMS requests arriving at both web nodes have source IP `10.0.30.53`, strongly indicating an upstream reverse proxy / load balancer in front of Web01/Web02.
- Web01/Web02 local Nginx body limits are 512 MiB or higher and are not the active blocker.
- Therefore the large-image HTTP 400 is being generated before the request reaches Web01/Web02, most likely by the upstream Nginx/reverse proxy at `10.0.30.53`.

## Current production baseline

- Canonical production source: `50860efddc0036743ba228e4df759a4f245340e3`
- Image hotfix package SHA256: `82c31507aa23b2ecd9d9851b9847149b0949c0546ec77a749cbe9e92746bb89a`
- Web01/Web02 identity: PASS
- Maintenance: OFF
- Web01 cron: active
- DB upgrade required: NO

## Next action

Inspect the upstream host that owns `10.0.30.53` and serves/proxies `lms.digiera.vn`:

1. Determine whether it runs Nginx/HAProxy/another proxy.
2. Inspect access/error logs for the exact failed `POST /local/worksheetlibrary/native_asset.php` request.
3. Inspect request-body/header/buffer limits and any WAF/modsecurity/body inspection rules on that host.
4. Fix only the upstream proxy layer if the evidence confirms it.
5. Retest the same large scan image.
6. Continue final student attempt -> save -> submit -> immutable teacher review -> grade/feedback acceptance only after image upload passes.

Do not mark Phase 5 PASS until the front-proxy upload blocker and remaining student E2E acceptance are cleared.
