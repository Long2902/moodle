# DIGIERA Media RC1 Lifecycle — Deployed / Browser Smoke Pending

Date: 2026-09-10 (UTC+7)
Branch: `feature/digiera-media-v1-rc1-lifecycle`
Status: **DEPLOYED TO WEB01/WEB02; BROWSER SMOKE PENDING**

## Frozen revisions

- Product commit: `b2acc33e36984dda5d8b6232af44884fa11304b6`
- Verified deploy helper commit: `e875b1aa76a9890b7fe90d02d48e9ead2635503e`
- Expected versions:
  - `local_digieramedia = 2026090902`
  - `tiny_digieramedia = 2026090902`
  - `filter_digieramedia = 2026090902`

## Production deployment evidence supplied by operator

The two-node lifecycle deploy helper completed with all deployment gates passing:

- `WEB02_SSH=PASS`
- Web01/Web02 Moodle CLI PASS with dataroot `/mnt/moodledata`
- `TWO_NODE_R2_CONFIG_READABLE=PASS`
- `LIFECYCLE_SOURCE_RUNTIME_CONTRACT=PASS`
- `WEB02_STAGE_IDENTITY=PASS`
- pre-deploy snapshots created on both nodes
- maintenance enabled before install
- `WEB01_LIFECYCLE_FILES=PASS`
- `WEB02_LIFECYCLE_FILES=PASS`
- Moodle upgrade PASS for filter/local/tiny plugins
- DB versions all `2026090902`
- `GLOBAL_UPGRADE_FUNCTION=YES`
- lifecycle services present:
  - `GET_MEDIA_USAGE_SERVICE=YES`
  - `TRASH_MEDIA_SERVICE=YES`
  - `RESTORE_MEDIA_SERVICE=YES`
  - `PURGE_MEDIA_SERVICE=YES`
- `LIFECYCLE_DB_SERVICE_CONTRACT=PASS`
- `WEB01_R2_DELETE_RUNTIME=PASS`
- `WEB02_R2_DELETE_RUNTIME=PASS`
- R2 DELETE preflight was non-destructive
- `CACHE_FPM_REFRESH=PASS`
- `TWO_NODE_LIFECYCLE_PARITY=PASS`
- maintenance disabled after verification
- cron timer active

Terminal sentinel:

```text
DIGIERA_LIFECYCLE_DEPLOY=PASS
PINNED_PRODUCT_COMMIT=b2acc33e36984dda5d8b6232af44884fa11304b6
R2_DELETE_PREFLIGHT=NO_REAL_DELETE
NEXT=CTRL_F5_THEN_SMOKE_USAGE_TRASH_RESTORE_PURGE_WITH_DISPOSABLE_MEDIA
```

Snapshots:

- Web01: `/root/DIGIERA_MEDIA_PRE_LIFECYCLE_WEB01_20260910-150529.tgz`
- Web02: `/root/DIGIERA_MEDIA_PRE_LIFECYCLE_WEB02_20260910-150529.tgz`

## Browser acceptance sequence

Use disposable media for destructive tests.

1. Ctrl+F5 and open DIGIERA Media.
2. Select a media item with an existing reference; verify Usage Locations count/list.
3. Trash that media; verify it moves to Trash while existing rendered reference remains available.
4. Restore it; verify it returns to active library and reference still works.
5. Trash a disposable media item that still has a live reference; normal permanent delete must be blocked.
6. Confirm the two-step force-delete flow only on disposable media.
7. After force purge, verify media no longer returns to active/trash lists, version objects are gone from R2, and any old reference renders the safe unresolved message.
8. Check browser console/network for DIGIERA lifecycle/AJAX/R2 errors.

Do not mark browser lifecycle acceptance PASS until the above evidence is supplied.

## Scope discipline

- No merge to `main`.
- R2 DELETE was not executed during deploy preflight.
- Destructive purge acceptance must use disposable test media only.
