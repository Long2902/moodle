# DIGIERA Media System — RC1 Deployed / Smoke Pending Resume Point

Date: 2026-09-07
Target: Moodle 5.1 / Web01 + Web02
Status: RC1 Preview deployed successfully to both web nodes. End-to-end TinyMCE smoke is the next action. Do not merge to `main` yet.

## 1. Source of truth

- Repository: `Long2902/moodle`
- RC1 branch: `feature/digiera-media-v1-rc1-fasttrack`
- Product-green commit: `63a4f025b72cb0e77bc217466e8310f6fcee6c9f`
- Self-contained packaging/source commit: `6134eabe8fe28a7245beef995fa380161cec93b4`
- Workflow run: `34092280998`
- Artifact ID: `10007301834`
- Artifact ZIP SHA256: `7247b82a722d231b0c455a50a8745d5729e264dadd7f9c6bc89e0eecb62b66ee`
- Plugin TGZ SHA256: `64925842c37833029399d5a795bfdf773561a635b9d359f325f253b839d0d152`

## 2. Pre-deployment topology/evidence

- Web01: `vm-c47e0dd9`, established private address `10.0.10.11`.
- Web02: hostname `moodle-web02`, private address `10.0.10.12`.
- Moodle root on both nodes: `/var/www/moodle/public`.
- PHP CLI observed on Web01: PHP 8.3.6.
- Both nodes were a fresh install for the DIGIERA Media plugin set before deployment:
  - `local/digieramedia` absent.
  - `filter/digieramedia` absent.
  - `lib/editor/tiny/plugins/digieramedia` absent.
- Web01 rollback snapshot: `/root/DIGIERA_MEDIA_PRE_RC1_20260907-135653`.
- Web02 rollback snapshot: `/root/DIGIERA_MEDIA_PRE_RC1_WEB02_20260907-140349`.
- Web01 is the cron owner; Web02 cron timer/service was inactive before deployment.

## 3. Deployment execution evidence

The operator ran the controlled two-node deployment from Web01. Final deployment log path:

`/root/DIGIERA_MEDIA_RC1_DEPLOY_20260907-141326.log`

The initial attempt failed safely at the artifact identity gate because of an SSH/AWK shell-quoting error while extracting the Web02 SHA. It stopped with `MAINT_FLAG=0`, before cron stop, maintenance, code installation, or database upgrade. The deployment script was patched to use safer `cut` extraction for remote SHA/hash values and passed `bash -n` before the real deployment retry.

Successful retry evidence:

- Web01 TGZ SHA matched expected.
- Web02 TGZ SHA matched expected.
- `ARTIFACT_IDENTITY=PASS`.
- Fresh-install assertions passed on both nodes.
- Web01 cron timer stopped and current cron service quiesced: `CRON_QUIESCED=PASS`.
- Moodle maintenance enabled before code mutation.
- Web01 code installation passed: `WEB01_CODE_INSTALL=PASS`.
- Web02 code installation passed: `WEB02_CODE_INSTALL=PASS`.
- All three plugin version files on both nodes reported release `1.0.0-rc1-fasttrack`, version `2026090701`.
- Deterministic two-node code-set hash matched exactly:
  - Web01: `3f35109803218403f8ac51ceca420323f96f4456d4dff4b2abed30ca4220f7ac`
  - Web02: `3f35109803218403f8ac51ceca420323f96f4456d4dff4b2abed30ca4220f7ac`
  - `TWO_NODE_CODESET=PASS`.
- Moodle upgrade was run once on Web01 and completed successfully for:
  - `filter_digieramedia`
  - `local_digieramedia`
  - `tiny_digieramedia`
- `MOODLE_UPGRADE=PASS`.
- Runtime configuration set:
  - `cdnbaseurl=https://cdn.digiera.vn`
  - `pdfviewerurl=https://cdn.digiera.vn/pdfjs/web/viewer.html`
  - DIGIERA filter global state `1` / ON.
- Database plugin versions verified:
  - `local_digieramedia=2026090701`
  - `filter_digieramedia=2026090701`
  - `tiny_digieramedia=2026090701`
- Cache purge passed on both nodes.
- PHP-FPM reload passed on both nodes using `php8.3-fpm.service`.
- Final database/filter verification passed: `FINAL_DB_CHECK=PASS`, `FILTER_STATE=ON`.
- Moodle maintenance was disabled successfully.
- Web01 cron timer restarted and is active.
- Web02 cron timer remains inactive.
- Final deployment sentinel: `RC1_TWO_NODE_DEPLOY=PASS`.

## 4. Current deployed state

The RC1 Preview code is now installed identically on Web01 and Web02, the shared Moodle database has been upgraded once, configuration is applied, caches/FPM have been refreshed, maintenance is off, and cron ownership is restored correctly.

This is a successful deployment checkpoint, not yet an end-to-end functional smoke approval.

## 5. Next action — seed and TinyMCE smoke

Use the bundled helper on Web01:

`/root/DIGIERA_MEDIA_RC1_SMOKE_READY/digiera-stage-seed-pdf.php`

Seed one existing disposable PDF hosted on `https://cdn.digiera.vn/...pdf`. Expected seed output:

- `STAGE_SEED=PASS`
- `MEDIA_UUID=...`
- `VERSION_ID=...`
- `REFERENCE_UUID=...`
- `OBJECT_KEY=...`
- `MARKER=[[digiera-ref:...]]`

Then perform the RC1 functional smoke as Teacher/Admin:

1. Edit a Moodle Page using TinyMCE.
2. Confirm **Học liệu DIGIERA** is present.
3. Open Modal A and confirm the three-column layout without RemUI header/navigation regression.
4. Confirm the seeded READY/SHARED PDF appears in Library.
5. Select it and press **Chèn vào bài**.
6. Confirm a non-editable DIGIERA component is inserted at the cursor.
7. Save the Page normally.
8. Confirm the front-end renders via DIGIERA PDF.js using page-width.
9. Reopen the editor and save again; the same logical Reference must survive round-trip.
10. Repeat through Web01/Web02/load-balanced traffic.

## 6. Preview limitations still in force

Do not overclaim final V1. Still pending after successful smoke:

- live browser → R2 upload verification and UX completion;
- strict DRAFT → ACTIVE/STALE reference reconciliation;
- lifecycle management UI;
- migration canary/batch migration;
- remaining Backup/Restore/Course Publisher qualification matrix;
- broader production observability/operational hardening.

Do not merge to `main` until the two-node functional smoke evidence is captured and a new versioned checkpoint is written.
