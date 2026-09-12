import {describe, expect, it} from 'vitest';

import {schema, validateMarkAttrs} from '../src/schema.js';
import {fromNativeDocument, toNativeDocument} from '../src/document_adapter.js';

const STYLE_MARKS = ['fontFamily', 'fontSize', 'highlight'];

describe('Phase 5 Native text style persistence contract', () => {
    it('exposes server-parity Native mark types for CambridgePlus text styles', () => {
        for (const name of STYLE_MARKS) {
            expect(schema.marks[name], `missing Native mark ${name}`).toBeDefined();
        }
    });

    it('maps Tiptap textStyle/highlight state to explicit Native marks', () => {
        const tiptap = {
            type: 'doc',
            content: [{
                type: 'paragraph',
                content: [{
                    type: 'text',
                    text: 'DIGIERA',
                    marks: [
                        {
                            type: 'textStyle',
                            attrs: {
                                color: '#123456',
                                fontFamily: 'Arial',
                                fontSize: '16px',
                            },
                        },
                        {type: 'highlight', attrs: {color: '#FFF2A8'}},
                    ],
                }],
            }],
        };

        const native = toNativeDocument(tiptap);
        const marks = native.content[0].content[0].marks;

        expect(marks).toEqual(expect.arrayContaining([
            {type: 'textColor', attrs: {color: '#123456'}},
            {type: 'fontFamily', attrs: {family: 'Arial'}},
            {type: 'fontSize', attrs: {px: 16}},
            {type: 'highlight', attrs: {color: '#FFF2A8'}},
        ]));
        expect(marks.some(mark => mark.type === 'textStyle')).toBe(false);

        const reopened = fromNativeDocument(native);
        const reopenedMarks = reopened.content[0].content[0].marks;
        expect(reopenedMarks.some(mark => mark.type === 'textStyle')).toBe(true);
        expect(reopenedMarks.some(mark => mark.type === 'highlight')).toBe(true);
    });

    it('rejects style values outside the approved allowlists', () => {
        expect(() => validateMarkAttrs('fontFamily', {family: 'Comic Sans MS'})).toThrow();
        expect(() => validateMarkAttrs('fontSize', {px: 13})).toThrow();
        expect(() => validateMarkAttrs('highlight', {color: 'red'})).toThrow();
    });
});
