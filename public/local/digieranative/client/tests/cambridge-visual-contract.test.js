import {readFileSync} from 'node:fs';
import {describe, expect, it} from 'vitest';

const css = readFileSync(new URL('../../styles.css', import.meta.url), 'utf8');
const pictureEditor = readFileSync(new URL('../src/ui/picture_editor.js', import.meta.url), 'utf8');
const officialEditor = readFileSync(new URL('../src/ui/official_editor.js', import.meta.url), 'utf8');

describe('Phase 5 Cambridge visual migration contract', () => {
    it('ships scoped Cambridge ribbon and picture-editor styling instead of the retired toolbar skin', () => {
        const start = css.indexOf('DIGIERA_NATIVE_CAMBRIDGE_RIBBON_UI_START');
        const end = css.indexOf('DIGIERA_NATIVE_CAMBRIDGE_RIBBON_UI_END');
        expect(start).toBeGreaterThan(-1);
        expect(end).toBeGreaterThan(start);
        const scoped = css.slice(start, end);
        for (const selector of [
            '.dgn-cambridge-toolbar',
            '.dgn-cambridge-group',
            '.dgn-cambridge-button',
            '.dgn-cambridge-select',
            '.dgn-picture-dialog',
            '.dgn-picture-preview',
            '.ReactCrop',
        ]) {
            expect(scoped).toContain(selector);
        }
        expect(scoped).not.toMatch(/(^|[,{]\s*)(\.btn|\.card|\.navbar)\b/m);
    });

    it('uses the locally bundled react-image-crop interaction for visual crop editing', () => {
        expect(pictureEditor).toMatch(/from ['"]react-image-crop['"]/);
        expect(pictureEditor).toMatch(/ReactCrop/);
        expect(pictureEditor).toMatch(/onChange/);
        expect(pictureEditor).toMatch(/percentCrop/);
    });

    it('keeps Cambridge toolbar as the only active React editor shell', () => {
        expect(officialEditor).toMatch(/CambridgeToolbar/);
        expect(officialEditor).not.toMatch(/Toolbar\s*from ['"].*toolbar\.js/);
    });
});
