# DIGIERA Media RC1 — Versioning / Replace deploy-ready checkpoint

Date: 2026-09-09
Branch: `feature/digiera-media-v1-rc1-versioning`

## Status

The Replace + Version History + FOLLOW_CURRENT / PINNED_VERSION vertical slice is code-complete and CI-verified. It has **not yet been deployed to Web01/Web02** at this checkpoint.

## Product baseline

- Product/runtime commit: `156b601202746e7c567c0d09c1fbb5f280cdba0d`
- Deploy helper commit: `76586c07d9945180a198c724443a3ca4edb75daf`
- CI helper verification commit: `065c005baaff460c3039b7091312b892aaa48088`
- Local plugin version: `2026090804`
- Tiny plugin version: `2026090804`

## Implemented behavior

- Replace uploads directly to R2 while preserving the logical Media UUID.
- Replacement creates immutable `Version N+1` and advances `currentversionid`.
- Existing references using `FOLLOW_CURRENT` render the newest current version.
- References using `PINNED_VERSION` keep rendering the selected pinned version.
- Admin/KTV advanced panel exposes version history, current version, Follow/Pin controls, and Replace action subject to capabilities.
- Replacement is constrained to the same media type family.
- Upload sessions can target an existing media item through `targetmediaid` / `replacemediauuid`.
- New AJAX service: `local_digieramedia_get_media_versions`.

## Fresh verification evidence

GitHub Actions workflow: `DIGIERA Media RC1 fast-track`, run `34313173141` / run number `46`.

Result: **SUCCESS** on 2026-09-09.

Verified steps all succeeded:

1. RC1 source contract and syntax gate, including `bash -n .digiera/tools/digiera-media-rc1-versioning-deploy.sh`.
2. TinyMCE AMD build.
3. Moodle PHPUnit initialization.
4. DIGIERA Media runtime suite.
5. Reproducible RC1 bundle build.
6. Release archive verification.
7. Artifact upload.

The source contract includes the versioning/replace contract. The renderer runtime test verifies that `FOLLOW_CURRENT` moves to a newer version while a pinned reference remains on its pinned version.

## Deploy helper

Path:

`/.digiera/tools/digiera-media-rc1-versioning-deploy.sh`

Pinned helper URL should use commit `76586c07d9945180a198c724443a3ca4edb75daf`.

The helper:

- prechecks Web02 SSH and Moodle CLI on both nodes;
- verifies `/etc/digiera/r2.php` readability on both nodes;
- downloads the exact product commit `156b601202746e7c567c0d09c1fbb5f280cdba0d`;
- verifies source/runtime contracts and JS/PHP syntax;
- stages identical payload on Web02;
- snapshots current DIGIERA plugins on both nodes;
- stops cron timer when active and enables maintenance;
- installs Web01/Web02 files;
- runs Moodle upgrade as `www-data`;
- verifies DB plugin versions, `targetmediaid`, and the version-history service registration;
- purges caches and reloads PHP-FPM;
- verifies two-node file parity and R2 runtime configuration;
- disables maintenance and restores cron timer state.

## Browser acceptance after deploy

Use one existing PDF media item:

1. Insert a `FOLLOW_CURRENT` reference to version v1.
2. Use **Thay thế file** to upload a replacement PDF, creating v2.
3. Confirm the old FOLLOW_CURRENT reference now renders v2.
4. Confirm history shows v2 as current and v1 retained.
5. Create a `PINNED_VERSION` reference pinned to v1.
6. Replace again to create v3.
7. Confirm FOLLOW_CURRENT renders v3 while PINNED_VERSION still renders v1.
8. Re-open editor and confirm reference metadata round-trips correctly.

## Next action

Deploy the pinned helper from Web01, paste the complete terminal output, then perform the browser acceptance sequence above. Do not merge to `main` during this checkpoint.
