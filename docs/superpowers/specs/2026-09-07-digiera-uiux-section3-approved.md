# DIGIERA UI/UX Visual Pass — Section 3 Approved

Date: 2026-09-07
Branch: `digiera/preview-v01`
Status: **SECTION 1 APPROVED / SECTION 2 APPROVED / SECTION 3 APPROVED / IMPLEMENTATION PLAN NEXT / NO NEW UI CODE IMPLEMENTED / NO NEW DEPLOYMENT**

This snapshot extends without replacing:

- `docs/superpowers/specs/2026-09-07-digiera-uiux-visual-contract-section1-approved.md`
- `docs/superpowers/specs/2026-09-07-digiera-uiux-section2-approved-html-reference-ingested.md`
- `docs/superpowers/specs/2026-09-07-digiera-uiux-section3-route-component-map-draft.md`

## Operator approval

The operator explicitly approved the Section 3 route/component mapping and requested implementation to continue.

## Locked screen-to-route map

### Worksheet Library / Native teacher flow

- Library browser: `/local/worksheetlibrary/index.php`
- Create worksheet modal: presentation layer on `index.php`, preserving the existing create service lane
- Worksheet detail + Native teacher editor: `/local/worksheetlibrary/detail.php?itemid=...`
- Preview/publish/success: presentation layer around the existing immutable publish service
- Version/history/binding: `detail.php` + existing services

Primary visual reference: operator-supplied `5.html` plus the approved PNG mockups.

### WorksheetGrader teacher flow

- Sessions dashboard: `/mod/worksheetgrader/sessions.php?id=CMID`
- Step 1 — session info: existing session creation flow
- Step 2 — worksheet picker: `/mod/worksheetgrader/content.php?id=CMID&sessionid=...`
- Step 3 — Team Studio: `/mod/worksheetgrader/teams.php?id=CMID&sessionid=...`
- Step 4 — readiness/open: `/mod/worksheetgrader/ready.php?id=CMID&sessionid=...`

Primary visual reference: operator-supplied `1.html` plus approved PNG mockups.

### Student flow

- Native attempt: `/mod/worksheetgrader/attempt.php?id=CMID&sessionid=...`
- Existing Native save/autosave/submit/freeze lane remains authoritative.

### Grading flow

- Grading split view: `/mod/worksheetgrader/grade.php?id=CMID&sessionid=...&attemptid=...`
- Immutable Native `submissionhtml` remains the rendering source for submitted Native work.

Primary visual reference: operator-supplied `3.html`.

## Locked implementation order

1. Visual Milestone V1 — Worksheet Library + Native teacher editor + preview/publish/history visual layer.
2. Visual Milestone V2 — Sessions dashboard + 4-step teacher wizard.
3. Visual Milestone V3 — Student Native attempt workspace.
4. Visual Milestone V4 — Grading split view.
5. Visual Milestone V5 — regression + browser screenshot + overlay/diff + operator acceptance.

## Non-negotiable safety boundary

- No Moodle core edits.
- No Edwiser RemUI core/theme edits.
- No global navigation/header/drawer changes.
- No Tailwind CDN or Font Awesome CDN in production.
- All DIGIERA styling remains plugin-scoped.
- Existing permissions, sesskey checks, services, Native schema/validator/renderer, save/publish/snapshot/attempt/grading semantics remain authoritative.
- No DB schema change is expected for this visual pass unless a separately evidenced functional defect requires one and the operator approves it.
- Development happens in an isolated workspace; production changes only after gates and controlled deployment.

## Acceptance rule

For each accepted screen:

```text
approved PNG/HTML reference
 -> browser implementation at reference viewport
 -> screenshot
 -> overlay / side-by-side visual diff
 -> operator acceptance
```

Do not claim 100% pixel-perfect until browser comparison and operator acceptance exist.

## Next action

Create and commit the detailed implementation plan, then begin Visual Milestone V1 using TDD/static contracts in an isolated workspace.
