# DIGIERA Media RC1 — R2 runtime build packaging fix

Date: 2026-09-08
Branch: `feature/digiera-media-v1-rc1-fasttrack`

## Trigger

The first two-node live R2 deploy attempt failed during pinned payload download before maintenance mode was entered. The final visible error was GitHub raw HTTP 404.

## Root cause

The deploy helper correctly required both TinyMCE runtime AMD build files:

- `amd/build/ui.min.js`
- `amd/build/upload_client.min.js`

However product commit `342e40304fe02bc18be000a10bda3d5d70643ee9` contained the new source module `amd/src/upload_client.js` but did **not** contain `amd/build/upload_client.min.js`. Its existing `amd/build/ui.min.js` was also still the pre-live-upload runtime and did not depend on `./upload_client`.

Therefore production packaging was incomplete even though source-level contracts and syntax were valid.

This failure was not caused by Moodle, Cloudflare R2, credentials, NFS, CORS, or the Web01/Web02 nodes.

## Regression contract

Commit `c459180ca9a5d9e6e19b25f09a56f1f688ca744b` adds a contract requiring the committed production runtime to include and wire:

- `amd/build/upload_client.min.js`
- `amd/build/ui.min.js`
- `tiny_digieramedia/upload_client`
- `local_digieramedia_create_upload_session`
- `local_digieramedia_finalize_upload`
- `XMLHttpRequest`
- `./upload_client` dependency from UI
- `UploadClient.uploadFile`

The missing build file made this contract RED at the failing baseline.

## Fix

Product commit:

`465b2c884681d9179c2465c4d8dcf21797e99f4e`

adds the live R2 runtime AMD module and updates the committed UI runtime build so Moodle production can execute the same live upload path already present in source.

Local syntax/contract probes before commit:

- `node --check ui.min.js` = PASS
- `node --check upload_client.min.js` = PASS
- UI runtime contains `UploadClient.uploadFile` = PASS
- upload runtime contains create-session/finalize service names = PASS

## Deploy recovery

Do not use the failed helper invocation pinned to `342e403...` again.

Recovery wrapper commit:

`cfda121c6a12633387f0e0cdf2724d4cde5af35f`

File:

`.digiera/tools/digiera-media-rc1-r2-singleput-runtimefix-deploy.sh`

The wrapper reuses the already-reviewed two-node R2 deploy helper but rewrites its product pin to `465b2c884681d9179c2465c4d8dcf21797e99f4e` before execution.

The failed first attempt stopped in download step 2, before snapshot/maintenance/install/upgrade, so no rollback is required for that attempt.

## Next action

Run the runtime-fix deploy wrapper on Web01 and inspect these layers in order:

1. pinned payload download,
2. Web01/Web02 R2 config + signer preflight,
3. CORS preflight,
4. two-node install + Moodle upgrade as `www-data`,
5. DB version `2026090803`,
6. cache/FPM refresh and parity,
7. browser live upload smoke for PDF, image, then MP4.

CI remains parallel and non-blocking until final production-candidate gate.
