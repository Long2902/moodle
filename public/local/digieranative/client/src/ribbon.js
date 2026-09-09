/**
 * @deprecated Phase 5 retired the legacy seven-tab Ribbon from the active editor path.
 * Keep this tiny compatibility module so stale imports fail softly while downstream
 * code migrates to the official Tiptap React toolbar mounted by editor.js.
 */
export const RIBBON_TABS = [];

export function createRibbon() {
    return null;
}
