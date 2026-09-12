import {describe, expect, it} from 'vitest';
import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
import * as NativeEditor from '../src/editor.js';

const here = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(here, '../../../..');
const teacherBridge = fs.readFileSync(
    path.join(repoRoot, 'local/worksheetlibrary/amd/src/native_editor.js'),
    'utf8'
);
const studentBridge = fs.readFileSync(
    path.join(repoRoot, 'mod/worksheetgrader/amd/src/native_attempt.js'),
    'utf8'
);

describe('Cloudrity image upload resilience contract', () => {
    it('exposes one shared browser image normalizer from the Native editor bundle', () => {
        expect(NativeEditor.normalizeImageForUpload).toBeTypeOf('function');
    });

    it('normalizes and retries only the upstream HTTP-400 image path in both Moodle bridges', () => {
        for (const source of [teacherBridge, studentBridge]) {
            expect(source).toContain('NativeEditor.normalizeImageForUpload');
            expect(source).toMatch(/HTTP\s*400|status\s*===\s*400|upstream/i);
            expect(source).toMatch(/retry|normaliz/i);
        }
    });
});
