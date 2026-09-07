# DIGIERA UI/UX Visual Pass — Section 3 Route / Component Mapping Draft

Date: 2026-09-07
Branch: `digiera/preview-v01`
Status: **SECTION 1 APPROVED / SECTION 2 APPROVED / SECTION 3 DRAFT / PENDING OPERATOR APPROVAL / NO NEW UI CODE IMPLEMENTED / NO NEW DEPLOYMENT**

This snapshot extends without replacing previous UI/UX checkpoints.

## 1. Reference handling rule

The supplied HTML mockups are implementation references for plugin-owned main content only.

The following reference elements are excluded from production implementation:

- mock Moodle/RemUI sidebar;
- mock global header/search/user controls;
- Tailwind CDN;
- Font Awesome CDN;
- hard-coded demo identities/data;
- standalone fake routing/state logic.

Production uses real Moodle/RemUI shell and real plugin services.

## 2. Worksheet Library mapping

### 2.1 Library list / browser

Reference: `5.html` -> `view-list`.

Real route:

`/local/worksheetlibrary/index.php`

Owning component: `local_worksheetlibrary`.

Primary files:

- `public/local/worksheetlibrary/index.php`
- `public/local/worksheetlibrary/styles.css`
- existing `local_worksheetlibrary/browser` AMD lane

Preserved backend:

- `access_service`
- `folder_service`
- `version_service::create_item()`
- current folder/search/item retrieval

Target visual structure:

- page header + description;
- action toolbar;
- folder tree;
- worksheet results list/table;
- inspector/detail panel;
- empty/search/loading states.

### 2.2 Create worksheet modal

Reference: `5.html` -> `modal-create`.

Real route remains `index.php`; modal is presentation only.

Create action remains the existing `action=item` path into `version_service::create_item()`.

Target kinds remain:

- Native DIGIERA;
- HTML;
- PDF;
- Office compatibility.

No mock JavaScript may bypass Moodle sesskey/capability/service checks.

### 2.3 Worksheet detail / Native teacher editor

References:

- `5.html` -> `view-editor`;
- `2.html` / `4.html` -> Word-like document workspace visual inspiration only.

Real route:

`/local/worksheetlibrary/detail.php?itemid=...`

Owning components:

- page shell/metadata/version/publish: `local_worksheetlibrary`;
- editor/Ribbon/canvas: `local_digieranative`.

Primary files:

- `public/local/worksheetlibrary/detail.php`
- `public/local/worksheetlibrary/styles.css`
- `public/local/worksheetlibrary/amd/src/native_editor.js`
- `public/local/digieranative/styles.css`
- Native client/Ribbon AMD bundle

Preserved backend:

- Native JSON validator/renderer;
- optimistic revision lane;
- `local_worksheetlibrary_save_native_draft`;
- manual Save and autosave;
- immutable publish/version behavior.

Target visual structure:

- DIGIERA Native editor header, not ONLYOFFICE branding;
- Ribbon tabs/groups;
- gray workspace + paper/page canvas;
- teacher actions and save status;
- metadata/preview/version controls;
- responsive inspector where appropriate.

### 2.4 Preview / publish / success states

References:

- `5.html` -> `modal-publish`;
- `5.html` -> `modal-success`.

Real publish backend remains:

`/local/worksheetlibrary/manage.php` -> `version_service::publish()`.

The modal/toast/success layer is presentation only and must reflect the real publish result.

### 2.5 Version/history/binding/detail

Real route remains:

`/local/worksheetlibrary/detail.php?itemid=...`

Preserve:

- version list;
- new draft from current published version;
- copy/move;
- course/section bindings;
- immutable published-version semantics.

The current technical table/form presentation will be replaced with the approved card/history/detail visual language.

## 3. WorksheetGrader mapping

### 3.1 Sessions dashboard

Reference: `1.html` -> `screen-dashboard`.

Real route:

`/mod/worksheetgrader/sessions.php?id=CMID`

Primary renderer:

`public/mod/worksheetgrader/v12/sessions_render.php`

Preserved backend:

- current session records/status;
- submission/attempt counts;
- real navigation URLs;
- current create-session form/service.

Target visual structure:

- title/action header;
- statistics cards;
- filter/search toolbar;
- sessions table/list;
- status/progress/actions.

### 3.2 Create session — Step 1/4

Reference: `1.html` -> `screen-create-1`.

Real route remains the sessions workflow; visual may become a full step workspace inside plugin content.

Preserve real Moodle form validation and `session_manager` creation.

### 3.3 Choose worksheet — Step 2/4

Reference: `1.html` -> `screen-create-2` plus approved worksheet-picker PNG.

Real route:

`/mod/worksheetgrader/content.php?id=CMID&sessionid=...`

Primary renderer:

`public/mod/worksheetgrader/v12/content.php`

AMD:

`mod_worksheetgrader/worksheet_picker`

Preserved backend:

- `local_worksheetlibrary::published_for_place(...)` integration;
- real course/section filtering;
- selection of published immutable worksheet version;
- real transition to teams step.

Target visual structure:

- 4-step progress header;
- left filters;
- center worksheet cards/list;
- right rich preview/metadata;
- sticky/back/continue actions.

### 3.4 Team Studio — Step 3/4

Reference: `1.html` -> `screen-create-3` plus approved Team Studio PNG.

Real route:

`/mod/worksheetgrader/teams.php?id=CMID&sessionid=...`

Primary renderer:

`public/mod/worksheetgrader/v12/teams.php`

AMD:

`mod_worksheetgrader/team_builder_v12`

Preserve:

- Moodle Groups import;
- copy previous teams;
- drag/drop membership;
- auto balance;
- real team IDs;
- no-reload save lane.

### 3.5 Readiness / open — Step 4/4

Reference: approved confirmation/open PNG.

Real route:

`/mod/worksheetgrader/ready.php?id=CMID&sessionid=...`

Preserved backend:

- `readiness_service::inspect()`;
- `readiness_service::open()`;
- freeze selected worksheet version;
- lock team membership/open semantics.

Target visual structure follows approved four-step confirmation mockup.

### 3.6 Student Native attempt

Reference: approved student worksheet PNG; old Office editor references may guide document-workspace geometry only.

Real route:

`/mod/worksheetgrader/attempt.php?id=CMID&sessionid=...`

AMD:

`mod_worksheetgrader/native_attempt`

Native presentation:

`local_digieranative`.

Preserved backend:

- frozen Native session snapshot;
- attempt autosave/manual Save lane;
- optimistic version protection;
- team/individual ownership;
- immutable submit/freeze;
- post-submit readonly behavior.

Target visual structure:

- Native worksheet/document workspace left;
- group/timer/guidance/save-submit panel right;
- real save state;
- submitted/readonly states.

### 3.7 Grading split view

Reference: `3.html`.

Real route:

`/mod/worksheetgrader/grade.php?id=CMID&sessionid=...&attemptid=...`

Preserved backend:

- submitted/graded attempt selection;
- Native immutable `submissionhtml`;
- grading manager;
- group grade/feedback/adjustment/publish grades;
- Office/PDF/HTML legacy rendering lanes.

Target visual structure:

- top grading/filter/progress bar;
- left immutable submission viewer;
- right submission info + rubric/score + feedback/actions;
- previous/next navigation.

## 4. Legacy compatibility boundary

Visual pass must retain reachability and behavior for:

- HTML worksheets;
- PDF worksheets;
- Office compatibility/fallback worksheets.

The Native teacher/student flows become the primary visual target; Office-specific pages remain compatibility UI rather than the product-wide visual baseline.

## 5. Implementation order

### Visual Milestone V1 — Library + Native teacher editor

Implement:

- Library list/browser;
- create modal;
- Native teacher editor workspace;
- preview/publish/success;
- detail/version/history presentation.

Reason: this is the fastest operator-visible comparison against the strongest HTML reference (`5.html`) and the current deployed smoke worksheet.

### Visual Milestone V2 — Session dashboard + 4-step wizard

Implement:

- session dashboard;
- step 1 information;
- step 2 picker;
- step 3 Team Studio;
- step 4 readiness/open.

### Visual Milestone V3 — Student Native attempt

Implement student Native workspace/sidebar/save-submit/read-only states.

### Visual Milestone V4 — Grading

Implement grading split view from `3.html` while preserving immutable submission and grading services.

### Visual Milestone V5 — Regression + visual acceptance

For every accepted screen:

- HTML/PHP/JS syntax/static gate;
- legacy route regression;
- browser screenshot at reference viewport;
- overlay/diff review;
- operator acceptance;
- only then mark the screen visually accepted.

## 6. Deployment discipline for visual pass

- Development occurs on GitHub/isolated workspace, not directly in `/var/www/moodle/public`.
- No DB schema change is expected for this visual pass.
- Package/static gates precede deployment.
- Web01/Web02 plugin trees must stay identical.
- Do not run a database upgrade unless a separately approved functional change truly requires a version/schema change.
- RemUI/core files remain untouched.

## 7. Approval gate

This Section 3 mapping is a draft until the operator explicitly approves it.

After approval, create a separate implementation plan with TDD/static/visual checkpoints and begin Visual Milestone V1 in an isolated workspace.
