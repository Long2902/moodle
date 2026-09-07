# DIGIERA Preview v0.1 — Operator Smoke UI Observation

Date: 2026-09-07

Status: **PREVIEW DEPLOYED / SMOKE IN PROGRESS / FUNCTIONAL UI BASELINE CONFIRMED / VISUAL POLISH DEFERRED / NOT YET OPERATOR VERIFIED END-TO-END**

## Operator evidence observed

The operator successfully opened the Worksheet Library, created a Native worksheet named `SMOKE RC1 - Native 01`, opened the detail page, and loaded the Native Editor.

Visible functional baseline from operator screenshots:

- Worksheet Library page renders correctly.
- Native worksheet row is visible with `NATIVE` kind and draft state.
- Native detail page renders.
- Native Ribbon editor mounts successfully.
- Editor accepts content.
- Save state shows `Đã lưu` in the UI.
- Publish action for v1 is visible.

## UI qualification

The current UI is intentionally a **Preview/RC functional shell** for end-to-end smoke testing, not the final product-polish pass.

Observed visual gaps to address after the end-to-end flow is verified:

- excessive empty vertical space in editor/detail page;
- Ribbon is functionally correct but visually sparse and text-heavy;
- typography, spacing, card hierarchy and density need polish;
- Worksheet Library needs stronger visual hierarchy and discovery/navigation;
- global Moodle/RemUI header/navigation must remain unchanged;
- future styling must remain plugin-scoped.

Do not treat current screenshots as final visual acceptance.

## Next smoke gates

Continue functional verification before UI polish:

1. autosave transition (`Đang lưu…` → `Đã lưu`);
2. manual Save;
3. reload persistence;
4. publish v1 and verify published content;
5. session selection of published Native worksheet;
6. team/student attempt save;
7. submit freeze;
8. grading/feedback;
9. regression HTML/Office/PDF.

Only after these pass should the Preview move to a dedicated UI/UX polish pass.
