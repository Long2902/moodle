# DIGIERA Media RC1 — End-to-End Browser Smoke PASS

Date: 2026-09-08
Branch: `feature/digiera-media-v1-rc1-fasttrack`

## Status

The browser smoke for the RC1 select/insert/render/reopen path is now confirmed PASS by user-provided browser evidence.

Confirmed flow:

1. Open TinyMCE DIGIERA Media modal.
2. Select existing media `DIGIERA RC1 Smoke PDF`.
3. Create DIGIERA reference and insert into TinyMCE.
4. TinyMCE component displays `Học liệu DIGIERA: DIGIERA RC1 Smoke PDF`.
5. Stored source uses the marker contract `[[digiera-ref:<REFERENCE_UUID>]]`.
6. Save Page succeeds.
7. Front-end filter renders the PDF through PDF.js using page-width behavior.
8. Reopen Edit Page.
9. Marker is rehydrated back into a non-editable DIGIERA component.
10. Reference metadata hydration restores the real media name `DIGIERA RC1 Smoke PDF` rather than the generic fallback label.

Final end-to-end status:

`SELECT -> CREATE REFERENCE -> INSERT -> SAVE -> PDF.JS RENDER -> REOPEN -> METADATA REHYDRATE = PASS`

## Runtime versions

Current rehydrate batch target:

- `local_digieramedia`: `2026090802`
- `tiny_digieramedia`: `2026090802`
- Product pin used for the rehydrate metadata batch: `a6320632387860352e948ad1ebaad1e1add2c292`

## Operational finding retained

Moodle dataroot is `/mnt/moodledata` on NFSv4 (`10.0.20.21:/srv/moodledata`) with root-squash behavior. Moodle CLI operations must therefore run as the web user (`www-data`), not root. Root remains appropriate for code deployment, SSH, systemd operations, and PHP-FPM reloads.

Do not chmod/chown moodledata to work around root-squash.

## Scope boundary

This checkpoint confirms the existing-media RC1 path. Live R2 upload is still outside this PASS unless separately configured and smoke-tested.

## Next recommended actions

1. Freeze this browser-smoke baseline as the RC1 preview checkpoint.
2. Continue with live R2 upload verification/configuration.
3. Then verify replace/version lifecycle, permissions, backup/restore, and Course Publisher flows against the same two-node environment.
