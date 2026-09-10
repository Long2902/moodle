# Tiptap UI provenance

This Phase 5 editor migration uses Tiptap 3.31.3 as the editing engine and follows the interaction/component patterns of Tiptap's free Simple Editor / UI component examples.

- Reference family: Tiptap Simple Editor / UI Components
- Runtime packages are bundled locally; no CDN is used.
- No cloud, AI, collaboration, comments, or paid UI features are included.
- DIGIERA keeps its own worksheet-domain nodes and persistence contract.
- The implementation in `src/ui/` is an adapted Moodle-specific implementation; it does not depend on remote runtime assets.

Pinned runtime versions:

- `@tiptap/core` 3.31.3
- `@tiptap/react` 3.31.3
- `@tiptap/starter-kit` 3.31.3
- `@tiptap/extension-text-align` 3.31.3
- `react` 18.3.1
- `react-dom` 18.3.1

The official Tiptap packages and their source retain their upstream licenses. DIGIERA code remains isolated in this plugin tree.
