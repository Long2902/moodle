import {readFileSync} from 'node:fs';
import {describe, expect, it} from 'vitest';

const editor = readFileSync(new URL('../src/editor.js', import.meta.url), 'utf8');

describe('official Tiptap UI build contract', () => {
    it('uses Tiptap React and removes the legacy ribbon runtime', () => {
        expect(editor).toMatch(/@tiptap\/react/);
        expect(editor).not.toMatch(/createRibbon/);
    });

    it('does not depend on a runtime CDN', () => {
        expect(editor).not.toMatch(/https?:\/\//);
    });
});
