import {describe, expect, it} from 'vitest';

describe('DIGIERA Native V1 ProseMirror schema', () => {
    it('parses a question followed by a linked long-answer block', async () => {
        const native = await import('../src/schema.js');

        const doc = native.schema.nodeFromJSON({
            type: 'doc',
            content: [
                {
                    type: 'question',
                    attrs: {id: 'q1'},
                    content: [
                        {type: 'text', text: 'Câu 1'},
                    ],
                },
                {
                    type: 'longAnswer',
                    attrs: {
                        questionId: 'q1',
                        minHeight: 160,
                    },
                },
            ],
        });

        expect(doc.childCount).toBe(2);
        expect(doc.child(0).type.name).toBe('question');
        expect(doc.child(0).attrs.id).toBe('q1');
        expect(doc.child(1).type.name).toBe('longAnswer');
        expect(doc.child(1).attrs.questionId).toBe('q1');
        expect(doc.child(1).attrs.minHeight).toBe(160);
    });
});

describe('DIGIERA Native V1 schema parity', () => {
    it('exposes every V1 node type defined by the server schema', async () => {
        const native = await import('../src/schema.js');

        const expectedNodes = [
            'answerTable',
            'checkbox',
            'doc',
            'hardBreak',
            'heading',
            'horizontalRule',
            'image',
            'instruction',
            'listItem',
            'longAnswer',
            'mathBlock',
            'mathInline',
            'multipleChoice',
            'orderedList',
            'pageBreak',
            'paragraph',
            'question',
            'rubricAnchor',
            'shortAnswer',
            'table',
            'tableCell',
            'tableRow',
            'teacherOnlyNote',
            'text',
            'unorderedList',
        ].sort();

        expect(Object.keys(native.schema.nodes).sort()).toEqual(expectedNodes);
    });

    it('exposes every V1 mark type defined by the server schema', async () => {
        const native = await import('../src/schema.js');

        const expectedMarks = [
            'bold',
            'fontFamily',
            'fontSize',
            'highlight',
            'italic',
            'link',
            'strike',
            'textColor',
            'underline',
        ].sort();

        expect(Object.keys(native.schema.marks).sort()).toEqual(expectedMarks);
    });
});

describe('DIGIERA Native V1 attribute parity', () => {
    it('exposes the exact server-approved persisted attrs for every attributed node', async () => {
        const native = await import('../src/schema.js');

        const expected = {
            paragraph: ['align'],
            heading: ['align', 'level'],
            question: ['align', 'id', 'points'],
            instruction: ['align', 'variant'],
            shortAnswer: ['minHeight', 'placeholder', 'questionId'],
            longAnswer: ['minHeight', 'placeholder', 'questionId'],
            checkbox: ['checked', 'label', 'optionId', 'questionId'],
            multipleChoice: ['questionId', 'selectionMode'],
            answerTable: ['cols', 'questionId', 'rows'],
            image: [
                'align', 'alt', 'assetKey', 'caption', 'cropH', 'cropW', 'cropX', 'cropY',
                'rotation', 'title', 'width', 'widthPercent',
            ],
            orderedList: ['order'],
            teacherOnlyNote: ['label'],
            rubricAnchor: ['id'],
            mathInline: ['source'],
            mathBlock: ['source'],
            tableCell: ['colspan', 'rowspan'],
        };

        for (const [name, attrs] of Object.entries(expected)) {
            // Runtime-only attrs are allowed for editor bookkeeping but must never be
            // considered part of the persisted/server Native contract. The document
            // adapter strips _nativeLegacyAttrs before emitting Native JSON.
            const actual = Object.keys(
                native.schema.nodes[name].spec.attrs ?? {},
            ).filter(key => !key.startsWith('_')).sort();

            expect(
                actual,
                `attrs mismatch for node ${name}`,
            ).toEqual([...attrs].sort());
        }
    });

    it('matches the server-approved mark attrs', async () => {
        const native = await import('../src/schema.js');

        expect(
            Object.keys(native.schema.marks.textColor.spec.attrs ?? {}).sort(),
        ).toEqual(['color']);
        expect(
            Object.keys(native.schema.marks.highlight.spec.attrs ?? {}).sort(),
        ).toEqual(['color']);
        expect(
            Object.keys(native.schema.marks.fontFamily.spec.attrs ?? {}).sort(),
        ).toEqual(['family']);
        expect(
            Object.keys(native.schema.marks.fontSize.spec.attrs ?? {}).sort(),
        ).toEqual(['px']);
        expect(
            Object.keys(native.schema.marks.link.spec.attrs ?? {}).sort(),
        ).toEqual(['class', 'href', 'rel', 'target']);

        for (const name of ['bold', 'italic', 'underline', 'strike']) {
            expect(
                Object.keys(native.schema.marks[name].spec.attrs ?? {}),
                `${name} must not accept attrs`,
            ).toEqual([]);
        }
    });
});
