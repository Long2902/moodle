import {readFileSync} from 'node:fs';
import {describe, expect, it} from 'vitest';

const editor = readFileSync(new URL('../src/editor.js', import.meta.url), 'utf8');

describe('official Tiptap UI build contract', () => {
    it('uses Tiptap React and removes the legacy ribbon runtime', () => {
        expect(editor).toMatch(/@tiptap\/react/);
        expect(editor).not.toMatch(/createRibbon/);
    });

    it('does not depend on a runtime CDN or remote module import', () => {
        // HTTPS values are valid runtime data (for example managed image URLs),
        // so the contract must reject remote code dependencies rather than every URL literal.
        expect(editor).not.toMatch(/(?:from\s+|import\s*\()\s*['"]https?:\/\//);
        expect(editor).not.toMatch(/(?:cdn\.jsdelivr\.net|unpkg\.com|esm\.sh|cdn\.skypack\.dev)/);
    });
});
