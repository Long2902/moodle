# DIGIERA Media V1 Phase 3: Production Deploy-Ready Checkpoint

- **Date**: 2026-09-12
- **Branch**: `feature/digiera-media-v1-rc1-backup-coursepublisher`
- **Frozen PRODUCT_COMMIT**: `f8d825352d4bea24db80717c0ccd274dfdf16733`
- **Origin Sync Status**: `origin/feature/digiera-media-v1-rc1-backup-coursepublisher` == `f8d825352d4bea24db80717c0ccd274dfdf16733` (VERIFIED)
- **Local Verification**: 55 tests / 310 assertions — 100% PASS
- **Deploy Script**: `.digiera/tools/digiera-media-phase3-production-deploy.sh` (Static syntax verification: `bash -n` PASS)

---

## 1. Production Topology & Preconditions

- **Web01**: Node operator connects to (Moodle cron timer host).
- **Web02**: `10.0.10.12` (SSH batch key access verified).
- **Moodle Runtime Root**: `/var/www/moodle/public`
- **Moodle Dataroot**: `/mnt/moodledata` (NFS shared mount).
- **Moodle User**: `www-data` (owns all Moodle CLI commands).
- **Execution User**: `root` (owns service management, file staging, and SSH).
- **R2 Configuration**: `/etc/digiera/r2.php` (must be readable on both nodes, secrets never echoed).
- **Cron Policy**: `moodle-cron.timer` runs strictly on Web01. Quiesced during deploy, restored after cache purge.

---

## 2. Deploy Script Architecture

The script `.digiera/tools/digiera-media-phase3-production-deploy.sh` executes the following sequence:

1. **Precheck**:
   - Verify Web02 SSH connectivity (`ssh -n -o BatchMode=yes root@10.0.10.12 true`).
   - Verify Moodle CLI and dataroot `/mnt/moodledata` on Web01 and Web02.
   - Verify `/etc/digiera/r2.php` readability on both nodes without leaking secrets.
2. **Download & Stage Pinned Payload**:
   - Pull exact GitHub tarball for frozen commit `f8d825352d4bea24db80717c0ccd274dfdf16733`.
   - Strip tests and docs from the staging area (`tests/`, `docs/`).
   - Validate source contract (files exist, classes present, PHP syntax check).
3. **Stage Web02 & Verify Hash Identity**:
   - Tar-pipe staging payload to Web02.
   - Verify SHA256 identity between Web01 and Web02 staging payloads before any code mutation.
4. **Take Versioned Snapshots**:
   - Web01 snapshot: `/root/DIGIERA_MEDIA_PRE_PHASE3_WEB01_<timestamp>.tgz`
   - Web02 snapshot: `/root/DIGIERA_MEDIA_PRE_PHASE3_WEB02_<timestamp>.tgz`
5. **Quiesce Cron & Enable Maintenance Mode**:
   - Stop `moodle-cron.timer` on Web01.
   - Enable Moodle maintenance mode via `admin/cli/maintenance.php`.
6. **Install Runtime**:
   - Synchronize `local/digieramedia`, `lib/editor/tiny/plugins/digieramedia`, `filter/digieramedia`, and `local/coursepublisher`.
   - Set ownership to `www-data:www-data`, permissions to `0755` dirs / `0644` files on both nodes.
7. **Database Upgrade & Cache Refresh**:
   - Run `php admin/cli/upgrade.php --non-interactive` as `www-data`.
   - Purge caches on both nodes via `admin/cli/purge_caches.php`.
   - Gracefully reload PHP-FPM service on both nodes.
8. **Runtime Verification**:
   - Verify `ui.min.js` AMD bundle exists and matches byte-for-byte between Web01 and Web02.
   - Verify whole-tree file parity across all deployed plugins between Web01 and Web02.
9. **Disable Maintenance & Restore Cron**:
   - Disable Moodle maintenance mode.
   - Restart and verify `moodle-cron.timer` is `active`.
   - Verify zero failed systemd units.
   - Clean up staging temporary directories.
10. **Trap / Rollback Safety**:
    - On any step failure, script aborts, prints `STEP_FAILED_LINE` and snapshot locations, and exits with non-zero code.

---

## 3. Operator Deployment Command

To be run on **Web01** as `root`:

```bash
curl -fsSL "https://raw.githubusercontent.com/Long2902/moodle/f8d825352d4bea24db80717c0ccd274dfdf16733/.digiera/tools/digiera-media-phase3-production-deploy.sh" -o /root/digiera-media-phase3-production-deploy.sh && chmod +x /root/digiera-media-phase3-production-deploy.sh && /root/digiera-media-phase3-production-deploy.sh
```
