# DIGIERA Media RC1 — R2 single-PUT two-node deploy PASS

Date: 2026-09-08
Branch: `feature/digiera-media-v1-rc1-fasttrack`
Pinned product commit: `465b2c884681d9179c2465c4d8dcf21797e99f4e`

## Verified evidence

The production-like two-node deploy helper completed successfully after fixing payload permissions / preflight execution.

Observed PASS sequence from Web01 operator log:

- `R2_PERMISSIONFIX_WRAPPER=PASS`
- `R2_SINGLEPUT_SOURCE_CONTRACT=PASS`
- Web01 R2 config/signer preflight PASS
- Web02 staged payload identity PASS
- Web02 R2 config/signer preflight PASS
- `R2_CORS_PREFLIGHT=PASS`
- Web01/Web02 snapshots created
- Maintenance enabled for install window
- `WEB01_R2_SINGLEPUT_FILES=PASS`
- `WEB02_R2_SINGLEPUT_FILES=PASS`
- Moodle CLI upgrade PASS
- `DB_LOCAL_VERSION=2026090803`
- `DB_TINY_VERSION=2026090803`
- `DB_VERSION=PASS`
- cache purge / PHP-FPM refresh PASS
- two-node file parity PASS
- Web01/Web02 runtime R2 config checks PASS
- maintenance disabled
- cron timer active
- `R2_SINGLEPUT_DEPLOY=PASS`

Snapshots:

- Web01: `/root/DIGIERA_MEDIA_PRE_R2_SINGLEPUT_WEB01_20260908-163340.tgz`
- Web02: `/root/DIGIERA_MEDIA_PRE_R2_SINGLEPUT_WEB02_20260908-163340.tgz`

## Root cause fixed in deploy path

The interactive shell still had `umask 077` from secure R2 secret-file creation. The older deploy helper inherited that umask, causing downloaded payload subdirectories/files to be created as `700/600` owned by root. The preflight then ran as `www-data`, which could not traverse/read the staged R2 PHP classes. The permission-fix wrapper normalises the payload to readable/traversable non-secret modes and passes required environment values correctly into `runuser`.

## Current state

Server-side deployment is complete and service has returned to normal operation. This checkpoint does **not** yet mark end-to-end browser upload as PASS.

## Next actions

1. Hard refresh Moodle browser (`Ctrl+F5`).
2. Open a TinyMCE editor containing the DIGIERA Media button.
3. Open the DIGIERA Media modal.
4. Upload a small PDF first.
5. Verify the network sequence: Moodle create-upload-session AJAX -> browser direct PUT to Cloudflare R2 -> Moodle finalize AJAX.
6. Verify upload progress, automatic media selection, preview, and success state.
7. Insert into content, save, verify PDF.js rendering, then reopen edit and verify metadata/reference round-trip.
8. Repeat with JPG/PNG.
9. Repeat with MP4 below 100 MiB.

If browser upload fails, diagnose the exact failing stage (create session vs R2 PUT vs finalize) before changing code/config.
