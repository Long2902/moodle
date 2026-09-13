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

describe('Cloudrity chunk upload client contract', () => {
    it('exposes one shared sequential chunk uploader from the Native editor bundle', () => {
        expect(NativeEditor.uploadFileInChunks).toBeTypeOf('function');
    });

    it('uses a 96 KiB direct threshold and 64 KiB octet-stream chunks in teacher and student bridges', () => {
        for (const source of [teacherBridge, studentBridge]) {
            expect(source).toContain('NativeEditor.uploadFileInChunks');
            expect(source).toMatch(/96\s*\*\s*1024|98304/);
            expect(source).toMatch(/64\s*\*\s*1024|65536/);
            expect(source).toContain('application/octet-stream');
            expect(source).toMatch(/mode[^\n]{0,80}chunk/i);
        }
    });
});
