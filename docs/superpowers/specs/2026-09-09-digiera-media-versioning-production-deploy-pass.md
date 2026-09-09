# DIGIERA Media RC1 Versioning — Production Deploy PASS

Date: 2026-09-09
Branch: `feature/digiera-media-v1-rc1-versioning`
Pinned product commit deployed: `156b601202746e7c567c0d09c1fbb5f280cdba0d`

## Verified production deployment evidence

User-provided Web01 deploy output confirmed all deploy gates passed:

- `VERSIONING_SOURCE_RUNTIME_CONTRACT=PASS`
- `WEB02_STAGE_IDENTITY=PASS`
- Pre-change snapshots created on both web nodes
- Maintenance enabled during install
- `WEB01_VERSIONING_FILES=PASS`
- `WEB02_VERSIONING_FILES=PASS`
- Moodle upgrade completed successfully for `local_digieramedia` and `tiny_digieramedia`
- DB versions: `LOCAL=2026090804`, `TINY=2026090804`
- Schema gate: `TARGETMEDIAID=YES`
- Service gate: `VERSION_SERVICE=YES`
- `VERSIONING_DB_CONTRACT=PASS`
- `CACHE_FPM_REFRESH=PASS`
- `TWO_NODE_VERSIONING_PARITY=PASS`
- `WEB01_R2_RUNTIME=PASS`
- `WEB02_R2_RUNTIME=PASS`
- `TWO_NODE_R2_RUNTIME=PASS`
- Maintenance disabled after deployment
- `CRON_TIMER=active`
- Final gate: `DIGIERA_VERSIONING_DEPLOY=PASS`

Snapshots:

- Web01: `/root/DIGIERA_MEDIA_PRE_VERSIONING_WEB01_20260909-121527.tgz`
- Web02: `/root/DIGIERA_MEDIA_PRE_VERSIONING_WEB02_20260909-121527.tgz`

## Current status

Server-side deployment of RC1 versioning is verified PASS on both Web01/Web02.

Browser acceptance is still required before marking the feature set fully verified:

1. Existing v1 media/reference with `FOLLOW_CURRENT`.
2. Replace file -> create v2 while preserving Media UUID.
3. Confirm history shows v2 current + v1 historical.
4. Confirm existing FOLLOW_CURRENT reference renders v2 without changing marker/reference UUID.
5. Create a PINNED_VERSION reference pinned to v1.
6. Replace again -> create v3.
7. Confirm FOLLOW_CURRENT renders v3 while PINNED_VERSION remains on v1.
8. Reopen editor and confirm version controls/metadata rehydrate correctly.

Do not mark versioning browser acceptance complete until the above evidence is captured.