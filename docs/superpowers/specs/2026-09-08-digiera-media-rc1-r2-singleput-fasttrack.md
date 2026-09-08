# DIGIERA Media RC1 — Live R2 single-PUT fast-track checkpoint

Date: 2026-09-08
Branch: `feature/digiera-media-v1-rc1-fasttrack`

## Goal

Move the approved DIGIERA Media RC1 from existing-media-only smoke coverage to the live browser -> Cloudflare R2 -> Moodle metadata upload path without turning GitHub CI into a per-step development gate.

## Fast-track decision

Use a hybrid release loop:

1. implement and target-verify one coherent product batch;
2. push the batch once;
3. let GitHub CI run in parallel;
4. deploy a pinned product SHA to Web01/Web02;
5. run real browser smoke against the production-like two-node environment;
6. require consolidated targeted tests + browser smoke + important CI checks before the final production candidate is declared ready.

## Existing browser baseline

The pre-R2 path is already browser-smoke proven:

`SELECT -> CREATE REFERENCE -> INSERT -> SAVE -> PDF.JS RENDER -> REOPEN -> METADATA REHYDRATE = PASS`

The approved Modal A visual layout remains unchanged.

## Live R2 vertical slice

Product batch originally landed at `7255db3eae37adecd242336755949c94cd9a31a2` and includes:

- server-only Cloudflare R2 configuration;
- SigV4 presigned single-PUT URLs;
- upload session authorization;
- file extension/MIME/size policy;
- direct browser `XMLHttpRequest` PUT to R2;
- server-side `HEAD` verification of size, Content-Type and ETag;
- user/context-bound and lock-protected idempotent finalize;
- Media + Version metadata commit only after verification;
- UI progress, error state and auto-selection after upload.

Current RC single-PUT default maximum is 100 MiB. Multipart is intentionally out of this batch.

## Security audit result

The production-critical path was audited before deployment:

- upload session requires `local/digieramedia:upload` in the editor context;
- teacher upload global policy is enforced at session creation;
- filename is basename-normalized and allowed by a strict extension/MIME policy;
- R2 secrets remain server-side and are not returned by AJAX;
- browser receives only a short-lived presigned URL and required Content-Type header;
- SigV4 signs `content-type;host` for PUT;
- target bucket is restricted to the configured DIGIERA bucket;
- finalize is bound to the creating user and exact Moodle context;
- finalize verifies R2 object size, Content-Type and ETag before database commit;
- finalize is lock-protected and idempotent through `committedmediaid`.

Cloudflare's browser presigned-URL model requires bucket CORS allowing the LMS origin, PUT and Content-Type. The deploy helper performs a non-blocking CORS preflight and prints `R2_CORS_PREFLIGHT=PASS` or `WARN` without exposing the presigned URL.

## CI diagnosis

Workflow run `34185568013` failed only during TinyMCE AMD Grunt lint. All RC1 contract tests and PHP/JS syntax checks passed before the failure.

Root cause: four `max-len` ESLint violations in `amd/src/ui.js` (lines >132 characters), not an R2 runtime failure.

Formatting-only lint fix product commit:

`342e40304fe02bc18be000a10bda3d5d70643ee9`

No upload behavior was changed by this lint fix.

## Deployment helper

Helper commit:

`68c572dd02ff08a76c6acde6ea9d58737875dcf4`

Helper path:

`.digiera/tools/digiera-media-rc1-r2-singleput-deploy.sh`

Pinned product commit inside helper:

`342e40304fe02bc18be000a10bda3d5d70643ee9`

Important operational rules incorporated in the helper:

- Moodle CLI runs as `www-data` because `/mnt/moodledata` is NFSv4 with root-squash;
- root remains responsible only for file deployment, SSH and systemd operations;
- no chmod/chown workaround is applied to moodledata;
- R2 credentials are preflighted on both Web01 and Web02 before maintenance mode;
- R2 endpoint must resolve as HTTPS during production preflight;
- the presigned URL is never echoed;
- both plugins are snapshotted on both nodes before installation;
- DB versions must resolve to `2026090803` for both `local_digieramedia` and `tiny_digieramedia`;
- caches are purged and PHP-FPM reloaded on both nodes;
- final SHA parity is required across both nodes;
- maintenance is disabled and cron restored on success/failure paths.

## Browser smoke required next

After successful deploy:

1. hard-refresh editor;
2. upload a small PDF (<100 MiB);
3. confirm upload progress -> R2 verification -> media auto-selected;
4. insert, save, render and reopen;
5. repeat with JPG/PNG;
6. repeat with MP4 below the current 100 MiB single-PUT cap.

For each uploaded file, verify it appears in the library and can be inserted after reopening the modal.

## Release boundary

Do not declare final production readiness from source review alone. Final production candidate still requires:

- successful two-node R2 deploy sentinels;
- real browser upload smoke for PDF + image + MP4;
- existing insert/save/render/reopen regression still passing;
- permissions/security and backup/Course Publisher final gate;
- important CI checks reviewed before final release declaration.
