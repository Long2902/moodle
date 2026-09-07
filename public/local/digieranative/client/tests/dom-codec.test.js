import {describe, expect, it} from 'vitest';
import {JSDOM} from 'jsdom';
import {
    DOMParser as ProseMirrorDOMParser,
    DOMSerializer,
} from 'prosemirror-model';

import {schema} from '../src/schema.js';

describe('DIGIERA Native schema DOM codec', () => {
    it('serializes and parses the full V1 editor schema', () => {
        const requiredNodes = [
            'paragraph',
            'heading',
            'orderedList',
            'unorderedList',
            'listItem',
            'table',
            'tableRow',
            'tableCell',
            'image',
            'horizontalRule',
            'pageBreak',
            'instruction',
            'question',
            'shortAnswer',
            'longAnswer',
            'checkbox',
            'multipleChoice',
            'answerTable',
            'teacherOnlyNote',
            'rubricAnchor',
            'mathBlock',
            'hardBreak',
            'mathInline',
        ];

        for (const name of requiredNodes) {
            expect(
                typeof schema.nodes[name].spec.toDOM,
                `${name} must define toDOM`,
            ).toBe('function');

            expect(
                Array.isArray(
                    schema.nodes[name].spec.parseDOM,
                ),
                `${name} must define parseDOM`,
            ).toBe(true);
        }

        for (const name of [
            'bold',
            'italic',
            'underline',
            'strike',
            'textColor',
        ]) {
            expect(
                typeof schema.marks[name].spec.toDOM,
                `${name} must define toDOM`,
            ).toBe('function');

            expect(
                Array.isArray(
                    schema.marks[name].spec.parseDOM,
                ),
                `${name} must define parseDOM`,
            ).toBe(true);
        }

        const dom = new JSDOM(
            '<!doctype html><body></body>',
        );

        const document =
            dom.window.document;

        const doc = schema.node(
            'doc',
            null,
            [
                schema.node(
                    'paragraph',
                    {
                        align: 'center',
                    },
                    [
                        schema.text(
                            'DIGIERA',
                            [
                                schema.marks.bold.create(),
                            ],
                        ),
                    ],
                ),
            ],
        );

        const wrapper =
            document.createElement('div');

        wrapper.append(
            DOMSerializer
                .fromSchema(schema)
                .serializeFragment(
                    doc.content,
                    {
                        document,
                    },
                ),
        );

        const parsed =
            ProseMirrorDOMParser
                .fromSchema(schema)
                .parse(wrapper);

        expect(
            parsed.textContent,
        ).toBe('DIGIERA');

        expect(
            parsed.child(0).attrs.align,
        ).toBe('center');

        expect(
            parsed.child(0)
                .child(0)
                .marks
                .map((mark) => mark.type.name),
        ).toContain('bold');
    });
});
