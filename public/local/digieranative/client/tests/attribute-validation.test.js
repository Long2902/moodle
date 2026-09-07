import {describe, expect, it} from 'vitest';

describe('DIGIERA Native V1 node attribute validation', () => {
    it('matches the server validation contract for node attrs', async () => {
        const native = await import('../src/schema.js');

        expect(typeof native.validateNodeAttrs).toBe('function');

        const valid = [
            ['paragraph', {align: 'left'}],
            ['heading', {level: 1, align: 'center'}],
            ['heading', {level: 6}],
            ['question', {id: 'q1', points: 2.5, align: 'justify'}],
            ['shortAnswer', {questionId: 'q1', minHeight: 0}],
            ['longAnswer', {
                questionId: 'q1',
                minHeight: 10000,
                placeholder: 'Nhập câu trả lời',
            }],
            ['checkbox', {
                questionId: 'q1',
                optionId: 'a',
                checked: false,
                label: 'Đáp án A',
            }],
            ['multipleChoice', {
                questionId: 'q1',
                selectionMode: 'single',
            }],
            ['answerTable', {
                questionId: 'q1',
                rows: 3,
                cols: 4,
            }],
            ['image', {
                assetKey: 'asset_01',
                width: 800,
                align: 'center',
                alt: 'Hình minh họa',
            }],
            ['orderedList', {order: 1}],
            ['tableCell', {colspan: 2, rowspan: 3}],
            ['rubricAnchor', {id: 'rubric:q1'}],
            ['mathInline', {source: 'x^2'}],
        ];

        for (const [type, attrs] of valid) {
            expect(
                () => native.validateNodeAttrs(type, attrs),
                `expected valid attrs for ${type}`,
            ).not.toThrow();
        }

        const invalid = [
            ['heading', {level: 0}],
            ['heading', {level: 7}],
            ['heading', {level: 1.5}],
            ['paragraph', {align: 'middle'}],

            ['question', {}],
            ['question', {id: ''}],
            ['question', {id: 'bad id'}],

            ['shortAnswer', {}],
            ['longAnswer', {questionId: 'bad id'}],
            ['checkbox', {questionId: ''}],
            ['multipleChoice', {}],
            ['answerTable', {}],

            ['rubricAnchor', {}],
            ['rubricAnchor', {id: 'bad id'}],

            ['image', {}],
            ['image', {assetKey: 'bad asset key'}],

            ['question', {id: 'q1', points: '2'}],

            ['longAnswer', {questionId: 'q1', minHeight: -1}],
            ['longAnswer', {questionId: 'q1', minHeight: 10001}],
            ['image', {assetKey: 'img1', width: 1.5}],
            ['orderedList', {order: '1'}],
            ['answerTable', {questionId: 'q1', rows: -1}],
            ['tableCell', {colspan: 10001}],

            ['checkbox', {questionId: 'q1', checked: 1}],

            ['paragraph', {unexpected: true}],
        ];

        for (const [type, attrs] of invalid) {
            expect(
                () => native.validateNodeAttrs(type, attrs),
                `expected invalid attrs for ${type}: ${JSON.stringify(attrs)}`,
            ).toThrow();
        }
    });

    it('enforces the PHP 2048-byte bounded-text contract', async () => {
        const native = await import('../src/schema.js');

        expect(typeof native.validateNodeAttrs).toBe('function');

        // ASCII: 2048 characters = 2048 UTF-8 bytes.
        expect(() => native.validateNodeAttrs('teacherOnlyNote', {
            label: 'a'.repeat(2048),
        })).not.toThrow();

        expect(() => native.validateNodeAttrs('teacherOnlyNote', {
            label: 'a'.repeat(2049),
        })).toThrow();

        // Vietnamese "ế" is multiple bytes in UTF-8, so byte count matters.
        expect(() => native.validateNodeAttrs('teacherOnlyNote', {
            label: 'ế'.repeat(1025),
        })).toThrow();
    });
});

describe('DIGIERA Native V1 mark attribute validation', () => {
    it('accepts only #RRGGBB text colors and attr-less basic marks', async () => {
        const native = await import('../src/schema.js');

        expect(typeof native.validateMarkAttrs).toBe('function');

        expect(() => native.validateMarkAttrs('textColor', {
            color: '#12AbEF',
        })).not.toThrow();

        expect(() => native.validateMarkAttrs('bold', {})).not.toThrow();
        expect(() => native.validateMarkAttrs('italic', {})).not.toThrow();
        expect(() => native.validateMarkAttrs('underline', {})).not.toThrow();
        expect(() => native.validateMarkAttrs('strike', {})).not.toThrow();

        for (const color of [
            '#FFF',
            'red',
            '#12345G',
            '123456',
            '',
        ]) {
            expect(() => native.validateMarkAttrs('textColor', {
                color,
            })).toThrow();
        }

        expect(() => native.validateMarkAttrs('textColor', {})).toThrow();

        expect(() => native.validateMarkAttrs('bold', {
            color: '#FFFFFF',
        })).toThrow();

        expect(() => native.validateMarkAttrs('unknownMark', {})).toThrow();
    });
});
