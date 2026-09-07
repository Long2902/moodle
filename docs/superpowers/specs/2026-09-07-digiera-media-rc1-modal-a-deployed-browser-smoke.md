# DIGIERA Media System — RC1 Modal A deployed / Browser smoke pending

Date: 2026-09-07
Target: Moodle 5.1 / Web01 + Web02
Branch: `feature/digiera-media-v1-rc1-fasttrack`
Pinned product commit: `29252919624afa3ec856bfb5e0bacb931fd4149c`
Tiny plugin DB version: `2026090703`
Status: Modal A visual-parity payload deployed successfully to both web nodes. Browser smoke is the next gate. Do not merge to `main` yet.

## Deployment evidence

Observed deployment sentinels from Web01:

- `WEB02_SSH=PASS`
- `PINNED_DOWNLOAD=PASS`
- `MODAL_A_SOURCE_CONTRACT=PASS`
- `WEB02_STAGE_IDENTITY=PASS`
- `MAINTENANCE=ON`
- `WEB01_MODAL_A_FILES=PASS`
- `WEB02_MODAL_A_FILES=PASS`
- `MOODLE_UPGRADE=PASS`
- `CACHE_FPM_REFRESH=PASS`
- `DB_TINY_VERSION=2026090703`
- `TWO_NODE_MODAL_A_PARITY=PASS`
- `MAINTENANCE=OFF`
- `CRON_TIMER=active`
- `MODAL_A_DEPLOY=PASS`
- `PINNED_PRODUCT_COMMIT=29252919624afa3ec856bfb5e0bacb931fd4149c`

Rollback snapshots created automatically before deployment:

- Web01: `/root/DIGIERA_MEDIA_TINY_PRE_MODAL_A_WEB01_20260907-174136.tgz`
- Web02: `/root/DIGIERA_MEDIA_TINY_PRE_MODAL_A_WEB02_20260907-174136.tgz`

## UI contract currently deployed

- Header title: `Thư viện học liệu DIGIERA`
- The design badge `Phương án A – Modal 3 cột (Khuyến nghị)` is intentionally absent.
- Desktop Modal A width target: approximately 1180px.
- Three-column structure: 220px navigation / flexible library / 320px preview.
- Left navigation: Upload & Insert, Library, Recent, Trash.
- Center region: dropzone, upload-queue region, search, type filter, sort, media card grid.
- Right region: preview metadata and advanced Admin/KTV section.
- Existing select → create Reference → insert into TinyMCE flow remains the functional RC1 path.
- Live R2 upload is still not considered verified; the RC1 preview continues to prioritize the select/insert path.

## Next browser smoke

1. Hard-refresh a Moodle Page edit screen.
2. Click **Học liệu DIGIERA** and confirm the modal opens without a fresh Console error.
3. Confirm no design badge appears in the header.
4. Confirm the modal visually has three columns and does not disturb RemUI/Moodle navigation.
5. Confirm seeded `DIGIERA RC1 Smoke PDF` appears in Library.
6. Select it and confirm the right preview panel populates and **Chèn vào bài** becomes enabled.
7. Press **Chèn vào bài** and confirm a non-editable DIGIERA component is inserted into TinyMCE.
8. Save the Page and confirm the reference renders through the DIGIERA filter/PDF.js with `page-width`.
9. Reopen edit and save again to confirm marker/component round-trip.
10. Repeat through Web01/Web02/load-balanced requests.

If any step fails, capture the first fresh browser Console error and a full screenshot of the modal/page state before making another code change.
