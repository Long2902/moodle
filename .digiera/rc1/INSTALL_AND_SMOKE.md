# DIGIERA Media System — Moodle 5.1 RC1 Fast-Track

## Preview scope

The first smoke path is TinyMCE → Học liệu DIGIERA → select an existing READY Media item → Chèn vào bài → normal Moodle Save → central filter render → reopen/edit/save. The bundled PDF seed helper can supply a disposable Media item.

Live browser-to-R2 upload is not claimed verified in this Preview RC. The modal keeps the upload lane visible, while the first smoke gate uses selection of existing/seeded Media. This follows the RC1 checkpoint whose minimum path permits upload or selection before insertion/render verification.

DRAFT References are renderable in this Preview RC so a newly inserted item does not break before the later strict DRAFT→ACTIVE reconciliation lane is completed. Final V1 must complete strict reference reconciliation, live R2 upload, lifecycle/migration, and the remaining Course Publisher matrix.

## Artifact contents

The GitHub Actions artifact contains:
- `DIGIERA_MEDIA_MOODLE51_RC1_FASTTRACK.tgz` — the three Moodle plugin trees only.
- `DIGIERA_MEDIA_MOODLE51_RC1_FASTTRACK.sha256` — portable SHA256 for the TGZ.
- `DIGIERA_MEDIA_MOODLE51_RC1_FASTTRACK.contents.txt` — archive inventory.
- `digiera-stage-seed-pdf.php` — staging helper for registering one existing PDF from `https://cdn.digiera.vn/...` as READY Media.
- `BUILD_INFO.txt` — exact source commit and workflow run.
- `INSTALL_AND_SMOKE.md` — this checklist.

The release tarball itself contains only:
- `local/digieramedia/`
- `filter/digieramedia/`
- `lib/editor/tiny/plugins/digieramedia/`

No R2 secret or production credential is packaged.

## Controlled deployment outline

1. Verify the published SHA256 before extraction.
2. Preserve the existing plugin trees on Web01/Web02 for rollback evidence.
3. Stop the Web01 Moodle cron timer and wait for the current cron service to finish.
4. Enable Moodle maintenance mode.
5. Install the identical three plugin trees on Web01 and Web02.
6. Compare a deterministic code-set SHA256 across both nodes.
7. Run Moodle `admin/cli/upgrade.php --non-interactive` once on Web01 as `www-data`.
8. Configure `cdnbaseurl=https://cdn.digiera.vn` and `pdfviewerurl=https://cdn.digiera.vn/pdfjs/web/viewer.html`; enable `filter_digieramedia` globally.
9. Purge Moodle caches and reload PHP-FPM on both nodes.
10. Disable maintenance and restart the Web01 cron timer.

## Seed one existing CDN PDF for the first smoke

Run on Web01 after Moodle upgrade, using a disposable/test PDF that already exists on the DIGIERA CDN:

```bash
php digiera-stage-seed-pdf.php \
  --config=/var/www/moodle/public/config.php \
  --url='https://cdn.digiera.vn/<path>/<file>.pdf' \
  --name='DIGIERA RC1 Smoke PDF'
```

The helper accepts only HTTPS URLs on `cdn.digiera.vn` and only `.pdf` paths. A successful run prints `STAGE_SEED=PASS`, `MEDIA_UUID`, `REFERENCE_UUID`, `OBJECT_KEY`, and a marker. The important RC1 path is then to open TinyMCE and select that READY Media item from **Học liệu DIGIERA**; the manual marker is only a diagnostic fallback.

## Smoke checklist

- [ ] Web01/Web02 code-set hashes are identical.
- [ ] TinyMCE shows Học liệu DIGIERA for an authorized Teacher/Admin.
- [ ] Modal A opens in three columns on desktop without changing RemUI navigation/header.
- [ ] Library search returns the seeded READY PDF, including `SHARED` media visibility.
- [ ] Chèn vào bài inserts a visual non-editable DIGIERA component.
- [ ] Normal Moodle Save succeeds.
- [ ] Saved Page renders through DIGIERA PDF.js with page-width / scrollMode=page / spread=none.
- [ ] Reopen editor and save again preserves the same logical Reference.
- [ ] Repeat requests through Web01 and Web02/load-balanced traffic.
- [ ] No global RemUI tab/navigation regression is visible.

A successful Preview smoke is not final V1 certification; it is the gate for the next RC batch: live R2 upload, strict reference reconciliation, essential lifecycle UI, migration canary, and remaining Backup/Restore/Course Publisher matrix.
