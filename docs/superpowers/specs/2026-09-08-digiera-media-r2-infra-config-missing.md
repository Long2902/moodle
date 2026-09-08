# DIGIERA Media RC1 — R2 infrastructure config missing

Date: 2026-09-08
Branch: `feature/digiera-media-v1-rc1-fasttrack`

## Evidence

Live R2 single-PUT product/package/source contracts now pass, including committed AMD runtime builds and server/client upload contract. The deploy helper reached the real R2 signer preflight and stopped before snapshot/maintenance/install.

Web01 runtime probe as `www-data` reported:

- `R2_CONFIGURED=NO`
- `ENDPOINT_SET=NO`
- `BUCKET_SET=NO`
- `ACCESS_KEY_SET=NO`
- `SECRET_KEY_SET=NO`
- `R2_REQUIRE_CREDENTIALS=FAIL`
- `R2_FILE_EXISTS=NO`
- no `DIGIERA_R2_*` environment variables for root or `www-data`

Therefore the current blocker is infrastructure configuration, not Moodle code, packaging, CI, NFS, or browser CORS yet.

## Required next action

Provision server-only Cloudflare R2 configuration on both Web01 and Web02, preferably `/etc/digiera/r2.php`, containing endpoint/account ID, bucket, access key ID, secret access key, presign TTL and single-PUT maximum. The file must be readable by `www-data` and must never be committed to Git.

After both nodes report the same non-secret config summary, rerun signer preflight, then CORS preflight, then deploy the pinned product commit `465b2c884681d9179c2465c4d8dcf21797e99f4e`.

## Safety note

Do not paste R2 secret values into GitHub, Spec Kit, screenshots, CI logs, or chat output. Configuration secrets are server-only.
