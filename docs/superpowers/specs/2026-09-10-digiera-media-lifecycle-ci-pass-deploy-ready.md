# DIGIERA Media RC1 Lifecycle — CI PASS / Deploy Ready Checkpoint

Date: 2026-09-10 (UTC+7)
Branch: `feature/digiera-media-v1-rc1-lifecycle`
Status: **VERIFIED IN CI, NOT YET DEPLOYED TO WEB01/WEB02**

## Frozen revisions

- Lifecycle product commit: `b2acc33e36984dda5d8b6232af44884fa11304b6`
- Deploy-helper commit: `e875b1aa76a9890b7fe90d02d48e9ead2635503e`
- Helper: `.digiera/tools/digiera-media-rc1-lifecycle-deploy.sh`
- Helper pins `REF=b2acc33e36984dda5d8b6232af44884fa11304b6`
- Expected versions:
  - `local_digieramedia = 2026090902`
  - `tiny_digieramedia = 2026090902`
  - `filter_digieramedia = 2026090902`

## Fresh verification evidence

GitHub Actions workflow: `DIGIERA Media RC1 fast-track`

- Run number: `#88`
- Run ID: `34353053817`
- Head SHA: `e875b1aa76a9890b7fe90d02d48e9ead2635503e`
- Conclusion: **success**

The single `verify-package` job completed successfully. The following gates all reported success:

1. RC1 source contract and syntax gate
2. TinyMCE AMD build
3. Generated TinyMCE AMD runtime commit step
4. Moodle PHPUnit setup
5. DIGIERA Media runtime suite
6. Reproducible RC1 bundle build
7. Release archive verification
8. Artifact upload

Artifact:

- Name: `DIGIERA_MEDIA_MOODLE51_RC1_FASTTRACK`
- Artifact ID: `10104840320`
- Digest: `sha256:9489a6ebd1e58f0dd9565a8c6c6443aa63eed7e9bd5c34a088bdfab8210d552d`
- Expires: 2026-09-23

## RuntimeException regression fix

The failing R2 DELETE error path was traced to the wrong PHP class spelling `\runtime_exception`. Product and regression tests were corrected to use `\RuntimeException`.

The deploy helper explicitly rejects the old spelling and requires the corrected one before touching production files.

## Deploy-helper safety contract

The helper performs, in order:

- Web02 SSH and Moodle CLI preflight
- R2 config readability check as `www-data` on both nodes
- Download of the frozen product payload
- source/runtime contract checks
- PHP and JS syntax checks
- staged payload identity check between nodes
- pre-deployment snapshots on Web01 and Web02
- cron quiesce and maintenance enable
- Web01/Web02 install
- Moodle CLI upgrade
- DB plugin-version and external-service verification
- **non-destructive** R2 runtime verification only; it does not execute a real DELETE in preflight
- cache purge and PHP-FPM reload
- two-node SHA parity verification
- maintenance disable and cron restore

If an error occurs after maintenance is enabled, the helper intentionally leaves maintenance on and prints snapshot paths with `RECOVERY_REQUIRED=YES`; it does not automatically expose a partial deployment.

Successful terminal sentinel:

```text
DIGIERA_LIFECYCLE_DEPLOY=PASS
PINNED_PRODUCT_COMMIT=b2acc33e36984dda5d8b6232af44884fa11304b6
R2_DELETE_PREFLIGHT=NO_REAL_DELETE
NEXT=CTRL_F5_THEN_SMOKE_USAGE_TRASH_RESTORE_PURGE_WITH_DISPOSABLE_MEDIA
```

## Next action — production deployment

Run on Web01 as root. The helper itself is fetched from the exact verified commit, not from a moving branch ref:

```bash
set -Eeuo pipefail

cd /root
curl -fsSL \
  https://raw.githubusercontent.com/Long2902/moodle/e875b1aa76a9890b7fe90d02d48e9ead2635503e/.digiera/tools/digiera-media-rc1-lifecycle-deploy.sh \
  -o /root/digiera-media-rc1-lifecycle-deploy.sh

chmod 0700 /root/digiera-media-rc1-lifecycle-deploy.sh
bash -n /root/digiera-media-rc1-lifecycle-deploy.sh
/root/digiera-media-rc1-lifecycle-deploy.sh
```

Do not run destructive browser purge tests unless the deployment ends with `DIGIERA_LIFECYCLE_DEPLOY=PASS`.

## Browser smoke after deploy PASS

Use disposable test media for destructive checks:

1. Hard refresh (`Ctrl+F5`).
2. Verify media usage list/count.
3. Trash a test media item and verify it moves to Trash.
4. Restore it and verify ACTIVE rendering returns.
5. Confirm normal purge is blocked while references still exist.
6. Use a separate disposable media object for force purge.
7. Verify the R2 object is deleted and unresolved-reference rendering is safe/explicit.

## Scope discipline

- No merge to `main`.
- CI remains parallel and is not a gate for unrelated development work.
- This checkpoint records **CI verification only**. Production deployment and browser smoke remain pending until terminal/browser evidence is captured.
