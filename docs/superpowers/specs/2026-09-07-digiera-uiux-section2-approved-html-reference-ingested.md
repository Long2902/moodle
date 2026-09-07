# DIGIERA UI/UX Visual Pass — Section 2 Approved + HTML Reference Ingested

Date: 2026-09-07
Branch: `digiera/preview-v01`
Status: **SECTION 1 APPROVED / SECTION 2 APPROVED / HTML REFERENCES ACCEPTED / SECTION 3 MAPPING IN PROGRESS / NO NEW UI CODE IMPLEMENTED / NO NEW DEPLOYMENT**

This snapshot extends without replacing earlier DIGIERA Moodle Spec Kit checkpoints.

## 1. Operator approval

The operator approved Design Section 2: plugin-scoped component architecture and design system.

Locked implementation boundary:

- Moodle core remains untouched.
- Edwiser RemUI core/theme files remain untouched.
- Existing Moodle/RemUI global header, top navigation, drawers/sidebar navigation and floating controls remain unchanged.
- Only plugin-owned content inside Moodle main content is redesigned.
- CSS must remain plugin-scoped.
- Existing Preview business logic/data model remains authoritative unless a separately evidenced functional bug requires correction.

## 2. Approved presentation architecture

UI ownership remains split across existing components:

- `local_worksheetlibrary`: library, worksheet metadata/detail, versions/history, publish, course/section binding, library-side visual primitives.
- `local_digieranative`: Native Ribbon, document canvas, editor controls, Native readonly/preview presentation.
- `mod_worksheetgrader`: session list/wizard, worksheet picker, team setup, readiness/open, student attempt, grading.
- Moodle/RemUI: global application shell/navigation.

No new SPA or replacement navigation shell is approved.

## 3. HTML reference artifacts supplied by operator

The operator supplied five Gemini-rendered HTML files as visual implementation references:

```text
1.html  SHA256 9200abd8557e927bec2eccab68ba99453e0468ec239d4f7ede4ca33e30b062df
2.html  SHA256 47635201d4b6cde2aae4e39b621b8af6d71f5f03fa893e613fcd1257524b3dc6
3.html  SHA256 f67d6197e4daee2a5204f6d414d1b18bef2540a5af628f9bf00c3cac4f090f36
4.html  SHA256 47635201d4b6cde2aae4e39b621b8af6d71f5f03fa893e613fcd1257524b3dc6
5.html  SHA256 a8c3ef4bb556350f734de5e1c1b9758425fc8fda901d7fbe9b073adcc5e88ab7
```

Observed mapping baseline:

- `1.html`: Worksheet Grader session list and create-session multi-step flow.
- `2.html` / `4.html`: editor-oriented visual reference; these two supplied files are byte-identical in this ingestion snapshot.
- `3.html`: grading split-view visual reference.
- `5.html`: Worksheet Library list, Native editor, create worksheet modal, preview/publish modal, publish-success state.

## 4. How HTML references may be used

The HTML files are reference artifacts, not production source to copy wholesale.

Allowed:

- reuse visual geometry and hierarchy;
- reuse spacing, radii, panel proportions, toolbar grouping and interaction ideas;
- translate Tailwind utility intent into plugin-owned CSS/classes/templates;
- map mock controls onto existing Moodle/Preview services and AMD handlers.

Not allowed:

- copy the mock sidebar/header as a replacement for real Moodle/RemUI navigation;
- load Tailwind CDN in production;
- load Font Awesome CDN in production;
- replace Moodle capability/session/security patterns with mock JavaScript;
- introduce global CSS that targets RemUI navigation selectors;
- treat hard-coded demo data as production behavior.

## 5. Visual acceptance rule retained

Target is as close as practically possible to approved mockups/reference HTMLs.

Acceptance remains:

```text
reference mockup / approved HTML
 -> browser implementation at reference viewport
 -> screenshot
 -> overlay / visual-diff
 -> operator acceptance
```

No 100% pixel-perfect claim is allowed before browser evidence and operator acceptance.

## 6. Next design gate

Section 3 will map each approved screen/reference HTML to:

- real Moodle route;
- owning plugin;
- PHP/template file(s);
- AMD interaction module(s);
- CSS scope;
- preserved backend service;
- implementation order and visual acceptance checkpoint.

No production mutation is part of Section 3 design work.
