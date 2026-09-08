# DIGIERA Media RC1 — R2 deploy source-contract assertion fix

Date: 2026-09-08
Branch: `feature/digiera-media-v1-rc1-fasttrack`

## Observed failure

The runtime-build-fixed R2 single-PUT deploy downloaded all 17 pinned product files successfully, including:

- `amd/build/ui.min.js`
- `amd/build/upload_client.min.js`

It then exited before `R2_SINGLEPUT_SOURCE_CONTRACT=PASS` and before snapshot/maintenance/install.

Therefore no production code was changed by this failed attempt.

## Root cause

The deploy helper asserted that the literal `Content-Type` must exist in `amd/src/upload_client.js`.

That assertion is incorrect. The browser client intentionally consumes `session.requiredheaders` and applies each header dynamically. The server-side `upload_session_service` owns the header contract and returns:

```php
'requiredheaders' => [
    ['name' => 'Content-Type', 'value' => $record->expectedmimetype],
],
```

So `Content-Type` belongs to the server upload-session contract, not as a hard-coded browser-client literal.

## Fix

Added wrapper:

- `.digiera/tools/digiera-media-rc1-r2-singleput-contractfix-deploy.sh`

The wrapper:

1. pins product commit `465b2c884681d9179c2465c4d8dcf21797e99f4e`,
2. downloads the known R2 deploy helper,
3. replaces the false `Content-Type` client assertion with a server-contract assertion against `upload_session_service.php`,
4. adds line-number reporting to helper failures,
5. validates the patched helper with `bash -n`,
6. executes the corrected helper.

Wrapper commit: `84625ac057c54a9a1ae9f96df97773d54d9cf84f`.

## Next action

Run the contract-fix wrapper on Web01. If source contract passes, continue through R2 config/signing preflight, CORS preflight, two-node install, Moodle upgrade, cache purge, runtime verification, then browser upload smoke.
