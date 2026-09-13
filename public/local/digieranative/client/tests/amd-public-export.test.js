import {readFileSync} from 'node:fs';
import {describe, expect, it} from 'vitest';

const entry = readFileSync(new URL('../src/index.js', import.meta.url), 'utf8');
const amd = readFileSync(new URL('../../amd/build/native_editor.min.js', import.meta.url), 'utf8');

describe('DIGIERA Native AMD public export contract', () => {
    it('publishes uploadFileInChunks from the Rollup entry point', () => {
        expect(entry).toContain('uploadFileInChunks');
    });

    it('keeps uploadFileInChunks as a named public export in the built AMD module', () => {
        expect(amd).toContain('uploadFileInChunks');
    });
});
