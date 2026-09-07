# DIGIERA Moodle Spec Kit — UI/UX Visual Contract Section 1 Approved

**Date:** 2026-09-07  
**Branch:** `digiera/preview-v01`  
**Status:** **DESIGN SECTION 1 APPROVED / SECTION 2 PENDING / NO CODE CHANGES / NO DEPLOYMENT CHANGE**

## Resume purpose

This checkpoint freezes the operator-approved visual scope for the next UI/UX architectural pass. It extends prior DIGIERA Worksheet/Native Editor specs and preserves their historical evidence.

## Operator-approved invariants

1. Approved V12 mockups plus the new Native mockup are the visual/product reference master.
2. The Native engine replaces ONLYOFFICE as the default editing engine, but the approved user-facing product flows remain visually/functionally consistent.
3. Moodle core must not be modified.
4. Edwiser RemUI core/theme files must not be modified.
5. Moodle/RemUI header, top navigation, drawers/sidebar navigation and global floating controls remain owned by Moodle/RemUI and must not be redesigned by DIGIERA plugins.
6. Only plugin-owned content inside Moodle main content is redesigned.
7. All DIGIERA visual styling must be plugin-scoped; no broad/global overrides for RemUI navigation selectors.
8. Existing Preview backend contracts remain intact: Native JSON, server validation/rendering, optimistic revision, autosave/manual Save, publish, session snapshot, attempt save/submit freeze, grading, legacy HTML/Office/PDF reachability.

## Approved visual screen scope

The UI/UX pass will cover:

1. Worksheet Library / Kho phiếu học tập.
2. Create worksheet flow and type selection.
3. Native DIGIERA editor workspace with Word-like Ribbon.
4. Worksheet detail, metadata, bindings, version/history, preview/publish states.
5. Create Session wizard.
6. Published worksheet picker.
7. Team/group setup / Team Studio.
8. Readiness / Open Session.
9. Student Native attempt workspace including save/submit/timer/sidebar behavior.
10. Teacher grading split view including feedback/actions.
11. Empty/loading/error/conflict/published/submitted/graded visual states.

## Accepted implementation direction

Preferred direction: rebuild the presentation layer inside the existing plugins while retaining existing backend/data/service contracts. Do not create a separate SPA shell and do not attempt to achieve the visual target by uncontrolled global CSS overrides.

## Visual acceptance rule

For each approved reference screen:

```text
reference PNG
  -> browser implementation at reference viewport
  -> screenshot
  -> overlay / visual-diff review
  -> operator acceptance
```

Acceptance covers geometry, spacing, typography hierarchy, Ribbon grouping, icons/buttons, cards/borders/radii, states and RemUI integration.

Do not claim `100% pixel-perfect` until browser comparison and operator acceptance exist.

## Current project state carried forward

```text
PREVIEW DEPLOYED:                YES
SMOKE IN PROGRESS:               YES
NATIVE LIBRARY EDITOR:           FUNCTIONAL BASELINE CONFIRMED
NATIVE PUBLISH:                  OPERATOR CONFIRMED
VISUAL POLISH:                   NOT IMPLEMENTED
UI/UX SECTION 1:                 APPROVED
UI/UX SECTION 2:                 PENDING APPROVAL
MOODLE CORE MODIFIED:            NO
REMUI CORE MODIFIED:             NO
GLOBAL NAVIGATION MODIFIED:      NO
```

## Next design gate

Define and approve Section 2: plugin-scoped component/design-system architecture, ownership boundaries, reusable visual tokens/components, screen composition strategy and visual-diff workflow.

No implementation begins until the architectural design is fully approved and a written implementation plan is created.
