import {readFileSync} from 'node:fs';
import {describe, expect, it} from 'vitest';

const editor = readFileSync(new URL('../src/editor.js', import.meta.url), 'utf8');
const ribbon = readFileSync(new URL('../src/ribbon.js', import.meta.url), 'utf8');

describe('official toolbar migration contract', () => {
    it('removes the legacy Ribbon from the active editor path', () => {
        expect(editor).not.toMatch(/createRibbon/);
        expect(editor).toMatch(/OfficialEditor|@tiptap\/react/);
    });

    it('does not expose known dead placeholder controls', () => {
        expect(ribbon).not.toMatch(/layout-info/);
        expect(ribbon).not.toMatch(/review-info/);
        expect(ribbon).not.toMatch(/view-info/);
    });
});
