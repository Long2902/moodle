import {readFileSync} from 'node:fs';
import {describe, expect, it} from 'vitest';

const editor = readFileSync(new URL('../src/editor.js', import.meta.url), 'utf8');
const official = readFileSync(new URL('../src/ui/official_editor.js', import.meta.url), 'utf8');
const ribbon = readFileSync(new URL('../src/ribbon.js', import.meta.url), 'utf8');

describe('Cambridge toolbar migration contract', () => {
    it('keeps Tiptap React while routing the active shell through CambridgeToolbar', () => {
        expect(editor).not.toMatch(/createRibbon/);
        expect(editor).toMatch(/OfficialEditor|@tiptap\/react/);
        expect(official).toMatch(/CambridgeToolbar/);
        // Retired toolbar module is ./toolbar.js. Do not reject the active
        // ./cambridge_toolbar.js merely because its filename also ends in toolbar.js.
        expect(official).not.toMatch(/from\s+['"]\.\/toolbar\.js['"]/);
    });

    it('does not expose known dead placeholder controls', () => {
        expect(ribbon).not.toMatch(/layout-info/);
        expect(ribbon).not.toMatch(/review-info/);
        expect(ribbon).not.toMatch(/view-info/);
    });
});
