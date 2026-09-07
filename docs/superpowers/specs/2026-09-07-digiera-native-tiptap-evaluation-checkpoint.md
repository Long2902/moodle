# DIGIERA Native Editor — Tiptap Evaluation Checkpoint

Date: 2026-09-07
Branch: `digiera/preview-v01`
Status: **RESEARCH COMPLETE / HYBRID TIPTAP CLIENT RECOMMENDED / OPERATOR APPROVAL PENDING / UI V1 SOURCE MATERIALIZATION PAUSED / PRODUCTION UNCHANGED**

This checkpoint extends without replacing earlier DIGIERA Native/UI specifications.

## 1. Trigger

Before atomically materializing Visual Milestone V1 source, the operator asked whether DIGIERA Native should reuse Tiptap instead of continuing to maintain a low-level ProseMirror editor/Ribbon implementation manually.

## 2. Current client baseline

The current Native client directly depends on low-level ProseMirror packages (`prosemirror-model`, `state`, `view`, `commands`, `history`, `keymap`, `tables`, `schema-list`). The Native client owns its own schema/commands/Ribbon wiring.

Locked existing contracts remain:

- canonical root: `{"type":"worksheet","version":1,"content":[]}`;
- server-side Native validation remains authoritative;
- optimistic revision/save conflict semantics remain authoritative;
- autosave and manual Save share the existing save lane;
- publish creates immutable published versions;
- session snapshot/attempt/submit/grading semantics remain unchanged;
- legacy HTML/PDF/Office routes remain reachable;
- Moodle core and Edwiser RemUI core/navigation remain untouched.

## 3. Tiptap findings

Tiptap 3.x is a headless framework built on ProseMirror, not a separate incompatible editor engine. It supports Vanilla JavaScript with normal npm + Rollup/Webpack/Vite bundling.

Open-source capabilities directly useful to DIGIERA include:

- `StarterKit`: document/paragraph/text/headings/bold/italic/underline/lists/history and common editing primitives;
- `TableKit`: table/tableRow/tableHeader/tableCell;
- `Image`: image node/rendering/resizing (Moodle File API upload still must remain custom);
- `Mathematics`: KaTeX-backed inline/block math;
- `TextAlign`;
- `TextStyleKit`: font family, size, color/background, line height;
- `UniqueID`;
- extension/node/mark APIs for DIGIERA-specific worksheet nodes such as question/answer blocks;
- event/command APIs (`editor.chain()`, `editor.isActive()`, selection/update events) that can replace much low-level command/state plumbing.

Tiptap itself is headless. Vanilla JS still requires our own toolbar/Ribbon presentation. Tiptap UI Components exist and many open-source components are MIT licensed, but the current official UI component stack is optimized for React. For Moodle AMD/plain JavaScript, those components are best treated as source/interaction/visual references rather than introducing React into the Moodle plugin.

Tiptap core is MIT licensed. `ueberdosis/tiptap-php` is also MIT licensed.

## 4. PHP package finding

Official `ueberdosis/tiptap-php` can convert Tiptap-compatible JSON ↔ HTML and manipulate/sanitize content, but its own README notes that it does not fully implement all ProseMirror schema constraints.

Recommendation for Preview/V1: **do not make `tiptap-php` authoritative yet**. Keep the existing DIGIERA PHP validator/renderer authoritative and avoid adding a new server Composer dependency during the visual/editor migration.

## 5. Evaluated approaches

### A — Continue direct ProseMirror

Pros:
- no migration;
- current contracts already proven.

Cons:
- DIGIERA continues owning low-level commands, history/keymap/table wiring, active-state logic and more manual editor tests;
- slower to add Word-like formatting features.

### B — Full Tiptap rewrite including PHP utility

Pros:
- maximum reuse of Tiptap APIs.

Cons:
- unnecessarily reopens server validation/rendering/persistence contracts;
- introduces PHP Composer/server dependency risk;
- largest migration and regression surface.

Not recommended for Preview V1.

### C — Hybrid Tiptap client + DIGIERA server contracts (RECOMMENDED)

Client:
- use Tiptap 3.x headless Editor on top of ProseMirror;
- use open-source extensions for standard rich-text behavior;
- implement DIGIERA-specific question/answer nodes as custom Tiptap extensions;
- keep a DIGIERA-owned Word-like Ribbon matching approved mockups;
- drive Ribbon commands/active/disabled state through Tiptap APIs;
- keep Moodle AMD/Rollup integration, no CDN, no React runtime.

Server/data:
- preserve canonical Native `worksheet` wrapper and schema version;
- preserve existing PHP validator/renderer and Moodle File API;
- preserve autosave/manual Save/revision/publish/snapshot/attempt/submit/grading services;
- no DB schema change required for the migration target.

## 6. V1 impact

The already prepared Library/detail/publish visual work remains useful and should be retained.

The Native editor portion of V1 should be rebased before GitHub source materialization:

- replace low-level standard ProseMirror plumbing with Tiptap extensions/commands;
- preserve DIGIERA-specific canonical adapter and security validation;
- rebuild the Word-like Ribbon against Tiptap editor APIs;
- keep approved seven-tab visual contract: `File | Home | Insert | Layout | Review | Math | View`;
- rerun existing autosave/manual Save/frozen mount/Native JSON contracts plus new Tiptap adapter contracts.

## 7. Materialization gate

No V1 product source should be atomically materialized to `digiera/preview-v01` until the operator approves or rejects the recommended Hybrid Tiptap approach.

Current deployed Preview remains unchanged.
