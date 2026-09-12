# DIGIERA Phase 5 — Browser image regression diagnosis

Date: 2026-09-12
Status: BROWSER ACCEPTANCE BLOCKED / DIAGNOSIS COMPLETE FOR CLIENT RENDERING / UPSTREAM UPLOAD LIMIT CHECK PENDING

## Browser evidence

User confirmed the requested two-row Cambridge ribbon is correct and save state remains stable. During Picture Editor acceptance, two image regressions were observed:

1. An image can upload and insert, but `widthPercent=100` still appears very small in the editor.
2. Other images fail on Apply with `Unexpected token '<', '<html>...' is not valid JSON`.

The student attempt -> submit -> teacher review/grade flow also needs explicit UI acceptance guidance; the student UI is intentionally visible only to non-manager users in the activity view.

## Root-cause findings

### Runtime image sizing

Confirmed in `local_digieranative` client runtime:

- Native JSON persists `widthPercent`, crop, rotation and alignment correctly.
- The PHP renderer applies `widthPercent` to a real `<figure><img>`.
- The live Tiptap editor preview currently resolves the asset URL by setting `background-image` on the figure only.
- The editor CSS gives that figure only a small minimum height and the refresh path does not apply `widthPercent`, crop or rotation.

Therefore the editor's 100% control is not represented by the live preview surface. A RED Vitest contract was added to require a real runtime image preview and 100% figure width.

### HTML returned where JSON is expected

Confirmed in both teacher and student image bridges:

- `postAsset()` blindly calls `response.json()`.
- Any upstream HTML response therefore leaks as the browser error `Unexpected token '<'`.

Confirmed at endpoint boundaries:

- Teacher endpoint performs login/sesskey checks outside the JSON try/catch boundary.
- Student endpoint performs request parameter/sesskey checks outside the JSON try/catch boundary.

Because small images work while larger/different images fail, an upstream Nginx/PHP request-body limit below the product's 5 MiB cap is a strong hypothesis and must be measured on Web01/Web02 before changing infrastructure.

## RED contracts added

- `public/local/digieranative/client/tests/image-runtime-layout-regression.test.js`
- `public/local/worksheetlibrary/tests/native_image_upload_error_contract.py`

Do not mark Phase 5 complete until these browser image regressions are fixed, deployed to both nodes, and manually re-tested.
