# DIGIERA Phase 5 — Cloudrity image hotfix deployed, browser retest pending

Date: 2026-09-12
Status: CODE + FULL GATE + TWO-NODE DEPLOY PASS / SAME SCAN IMAGE BROWSER RETEST PENDING / PHASE 5 NOT CLOSED

## Evidence

- Cloudrity normalization/retry focused preflight: PASS.
- Full fastlane: 9/9 PASS.
- Full Vitest: 20 files / 63 tests PASS.
- Validator/renderer/static Native contracts: PASS.
- Native draft/session/attempt/submit/grading immutability gates: PASS.
- TCPDF smoke: PASS.
- Deterministic build: PASS.
- PHP lint: PASS.
- Post-deploy Cloudrity retry guard: PASS.
- Teacher and student image bridges: PASS.

## Production state

- Dev final source: `57dcd7ac31866c9b5ad9f0b658a923499a1ff383`
- Canonical production source: `d8b39b43433de760a6160b6b2c1d35b3279c85e9`
- Package SHA256: `8bdf0324193fc59e3dea7bf0494aec774def9d9b0a71941f897e05e780ed8034`
- Native AMD SHA256: `81ef187c7ef2e37e1102d171edc52f4d50d2370aeb08afa9faf6008808202195`
- Web01/Web02 tree hashes identical: PASS.
- Maintenance after deploy: OFF.
- Web01 cron after deploy: active.
- DB upgrade required: NO.

## Implemented hotfix behavior

- Original image upload remains the first attempt.
- On upstream non-JSON HTTP 400 path associated with Cloudrity/WAF, browser normalizes the image and retries once.
- Shared Native helper re-encodes to clean JPEG, strips metadata/EXIF, paints white background, applies bounded dimensions, and preserves the 5 MiB product cap.
- Moodle JSON HTTP 400 responses are not masked by this retry path.
- Same shared helper is used by teacher worksheet editor and student attempt editor.

## Browser acceptance next

Retest the exact scan image that previously produced `HTTP 400 ... nginx`:

1. Hard refresh the worksheet editor.
2. Insert the same scan image.
3. Confirm the image is accepted and rendered.
4. Wait for `Đã lưu`, hard refresh, and confirm image persistence.
5. If it still fails, capture the exact visible error; do not change Web01/Web02 Nginx/PHP based on speculation.

After image acceptance passes, continue the final student E2E:
student attempt -> autosave -> refresh persistence -> submit -> immutable submission -> teacher review/grade -> published feedback.

Do not mark Phase 5 PASS until browser image acceptance and student E2E are both confirmed.
