import {readFileSync} from 'node:fs';
import {describe, expect, it} from 'vitest';

const browser = readFileSync(
    new URL('../../../worksheetlibrary/amd/src/browser.js', import.meta.url),
    'utf8',
);

describe('structured preview contract', () => {
    it('does not clone editor innerHTML for publish preview', () => {
        expect(browser).not.toMatch(/target\.innerHTML\s*=\s*canvas\.innerHTML/);
    });

    it('renders preview through NativeEditor structured document API', () => {
        expect(browser).toMatch(/NativeEditor\.renderPreview/);
        expect(browser).toMatch(/nativejson|nativeJson|canonical/i);
    });
});
