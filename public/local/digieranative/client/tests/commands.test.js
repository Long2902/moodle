import {describe, expect, it} from 'vitest';
import {TextSelection} from 'prosemirror-state';

import {createEditorState} from '../src/editor.js';
import {commands} from '../src/commands.js';

function applyCommand(state, command) {
    let nextState = state;

    const applied = command(
        state,
        (transaction) => {
            nextState = state.apply(transaction);
        },
    );

    expect(applied).toBe(true);

    return nextState;
}

describe('DIGIERA Native editor commands', () => {
    it('inserts a long answer linked to the requested question', () => {
        const state = createEditorState({
            type: 'doc',
            content: [
                {
                    type: 'question',
                    attrs: {
                        id: 'q1',
                    },
                    content: [
                        {
                            type: 'text',
                            text: 'Câu 1',
                        },
                    ],
                },
            ],
        });

        const nextState = applyCommand(
            state,
            commands.insertLongAnswer({
                questionId: 'q1',
            }),
        );

        const json = nextState.doc.toJSON();

        expect(json.content).toHaveLength(2);

        expect(json.content[0]).toMatchObject({
            type: 'question',
            attrs: {
                id: 'q1',
            },
        });

        expect(json.content[1]).toMatchObject({
            type: 'longAnswer',
            attrs: {
                questionId: 'q1',
            },
        });
    });
});

describe('DIGIERA Native Home ribbon formatting commands', () => {
    it('applies bold to the selected text', () => {
        let state = createEditorState({
            type: 'doc',
            content: [
                {
                    type: 'paragraph',
                    content: [
                        {
                            type: 'text',
                            text: 'DIGIERA',
                        },
                    ],
                },
            ],
        });

        expect(
            typeof commands.toggleBold,
            'commands.toggleBold must exist',
        ).toBe('function');

        state = state.apply(
            state.tr.setSelection(
                TextSelection.create(
                    state.doc,
                    1,
                    8,
                ),
            ),
        );

        const nextState = applyCommand(
            state,
            commands.toggleBold,
        );

        const textNode = nextState.doc.child(0).child(0);

        expect(
            textNode.marks.map((mark) => mark.type.name),
        ).toContain('bold');
    });
});

describe('DIGIERA Native Home ribbon italic command', () => {
    it('applies italic to the selected text', () => {
        let state = createEditorState({
            type: 'doc',
            content: [
                {
                    type: 'paragraph',
                    content: [
                        {
                            type: 'text',
                            text: 'DIGIERA',
                        },
                    ],
                },
            ],
        });

        expect(
            typeof commands.toggleItalic,
            'commands.toggleItalic must exist',
        ).toBe('function');

        state = state.apply(
            state.tr.setSelection(
                TextSelection.create(
                    state.doc,
                    1,
                    8,
                ),
            ),
        );

        const nextState = applyCommand(
            state,
            commands.toggleItalic,
        );

        const textNode = nextState.doc.child(0).child(0);

        expect(
            textNode.marks.map((mark) => mark.type.name),
        ).toContain('italic');
    });
});

describe('DIGIERA Native Home ribbon underline command', () => {
    it('applies underline to the selected text', () => {
        let state = createEditorState({
            type: 'doc',
            content: [
                {
                    type: 'paragraph',
                    content: [
                        {
                            type: 'text',
                            text: 'DIGIERA',
                        },
                    ],
                },
            ],
        });

        expect(
            typeof commands.toggleUnderline,
            'commands.toggleUnderline must exist',
        ).toBe('function');

        state = state.apply(
            state.tr.setSelection(
                TextSelection.create(
                    state.doc,
                    1,
                    8,
                ),
            ),
        );

        const nextState = applyCommand(
            state,
            commands.toggleUnderline,
        );

        const textNode = nextState.doc.child(0).child(0);

        expect(
            textNode.marks.map((mark) => mark.type.name),
        ).toContain('underline');
    });
});

describe('DIGIERA Native Home ribbon undo command', () => {
    it('undoes the most recent document change', () => {
        let state = createEditorState({
            type: 'doc',
            content: [
                {
                    type: 'paragraph',
                    content: [
                        {
                            type: 'text',
                            text: 'A',
                        },
                    ],
                },
            ],
        });

        state = state.apply(
            state.tr.insertText(
                'B',
                2,
            ),
        );

        expect(
            state.doc.textContent,
        ).toBe('AB');

        expect(
            typeof commands.undo,
            'commands.undo must exist',
        ).toBe('function');

        const nextState = applyCommand(
            state,
            commands.undo,
        );

        expect(
            nextState.doc.textContent,
        ).toBe('A');
    });
});

describe('DIGIERA Native Home ribbon redo command', () => {
    it('redoes the most recently undone document change', () => {
        let state = createEditorState({
            type: 'doc',
            content: [
                {
                    type: 'paragraph',
                    content: [
                        {
                            type: 'text',
                            text: 'A',
                        },
                    ],
                },
            ],
        });

        state = state.apply(
            state.tr.insertText(
                'B',
                2,
            ),
        );

        state = applyCommand(
            state,
            commands.undo,
        );

        expect(
            state.doc.textContent,
        ).toBe('A');

        expect(
            typeof commands.redo,
            'commands.redo must exist',
        ).toBe('function');

        const nextState = applyCommand(
            state,
            commands.redo,
        );

        expect(
            nextState.doc.textContent,
        ).toBe('AB');
    });
});

describe('DIGIERA Native Home ribbon heading command', () => {
    it('turns the selected paragraph into a level 2 heading', () => {
        let state = createEditorState({
            type: 'doc',
            content: [
                {
                    type: 'paragraph',
                    content: [
                        {
                            type: 'text',
                            text: 'DIGIERA',
                        },
                    ],
                },
            ],
        });

        state = state.apply(
            state.tr.setSelection(
                TextSelection.create(
                    state.doc,
                    1,
                    8,
                ),
            ),
        );

        expect(
            typeof commands.setHeading,
            'commands.setHeading must exist',
        ).toBe('function');

        const nextState = applyCommand(
            state,
            commands.setHeading({
                level: 2,
            }),
        );

        expect(
            nextState.doc.child(0).type.name,
        ).toBe('heading');

        expect(
            nextState.doc.child(0).attrs.level,
        ).toBe(2);
    });
});

describe('DIGIERA Native Home ribbon alignment command', () => {
    it('centers the selected paragraph without changing its node type', () => {
        let state = createEditorState({
            type: 'doc',
            content: [
                {
                    type: 'paragraph',
                    content: [
                        {
                            type: 'text',
                            text: 'DIGIERA',
                        },
                    ],
                },
            ],
        });

        state = state.apply(
            state.tr.setSelection(
                TextSelection.create(
                    state.doc,
                    1,
                    8,
                ),
            ),
        );

        expect(
            typeof commands.setAlignment,
            'commands.setAlignment must exist',
        ).toBe('function');

        const nextState = applyCommand(
            state,
            commands.setAlignment({
                align: 'center',
            }),
        );

        expect(
            nextState.doc.child(0).type.name,
        ).toBe('paragraph');

        expect(
            nextState.doc.child(0).attrs.align,
        ).toBe('center');
    });
});

describe('DIGIERA Native Home ribbon ordered list command', () => {
    it('wraps the selected paragraph in an ordered list', () => {
        let state = createEditorState({
            type: 'doc',
            content: [
                {
                    type: 'paragraph',
                    content: [
                        {
                            type: 'text',
                            text: 'DIGIERA',
                        },
                    ],
                },
            ],
        });

        state = state.apply(
            state.tr.setSelection(
                TextSelection.create(
                    state.doc,
                    1,
                    8,
                ),
            ),
        );

        expect(
            typeof commands.wrapOrderedList,
            'commands.wrapOrderedList must exist',
        ).toBe('function');

        const nextState = applyCommand(
            state,
            commands.wrapOrderedList,
        );

        expect(
            nextState.doc.child(0).type.name,
        ).toBe('orderedList');

        expect(
            nextState.doc.child(0)
                .child(0)
                .type.name,
        ).toBe('listItem');

        expect(
            nextState.doc.textContent,
        ).toBe('DIGIERA');
    });
});

describe('DIGIERA Native Home ribbon unordered list command', () => {
    it('wraps the selected paragraph in an unordered list', () => {
        let state = createEditorState({
            type: 'doc',
            content: [
                {
                    type: 'paragraph',
                    content: [
                        {
                            type: 'text',
                            text: 'DIGIERA',
                        },
                    ],
                },
            ],
        });

        state = state.apply(
            state.tr.setSelection(
                TextSelection.create(
                    state.doc,
                    1,
                    8,
                ),
            ),
        );

        expect(
            typeof commands.wrapUnorderedList,
            'commands.wrapUnorderedList must exist',
        ).toBe('function');

        const nextState = applyCommand(
            state,
            commands.wrapUnorderedList,
        );

        expect(
            nextState.doc.child(0).type.name,
        ).toBe('unorderedList');

        expect(
            nextState.doc.child(0)
                .child(0)
                .type.name,
        ).toBe('listItem');

        expect(
            nextState.doc.textContent,
        ).toBe('DIGIERA');
    });
});

describe('DIGIERA Native Insert ribbon table command', () => {
    it('inserts a 2 by 3 table after the current block', () => {
        let state = createEditorState({
            type: 'doc',
            content: [
                {
                    type: 'paragraph',
                    content: [
                        {
                            type: 'text',
                            text: 'Before table',
                        },
                    ],
                },
            ],
        });

        state = state.apply(
            state.tr.setSelection(
                TextSelection.create(
                    state.doc,
                    1,
                ),
            ),
        );

        expect(
            typeof commands.insertTable,
            'commands.insertTable must exist',
        ).toBe('function');

        const nextState = applyCommand(
            state,
            commands.insertTable({
                rows: 2,
                cols: 3,
            }),
        );

        const table = nextState.doc.child(1);

        expect(table.type.name).toBe('table');
        expect(table.childCount).toBe(2);

        expect(
            table.child(0).type.name,
        ).toBe('tableRow');

        expect(
            table.child(0).childCount,
        ).toBe(3);

        expect(
            table.child(0)
                .child(0)
                .type.name,
        ).toBe('tableCell');
    });
});

describe('DIGIERA Native Insert ribbon image hook', () => {
    it('inserts a managed image block after the current block', () => {
        let state = createEditorState({
            type: 'doc',
            content: [
                {
                    type: 'paragraph',
                    content: [
                        {
                            type: 'text',
                            text: 'Before image',
                        },
                    ],
                },
            ],
        });

        state = state.apply(
            state.tr.setSelection(
                TextSelection.create(
                    state.doc,
                    1,
                ),
            ),
        );

        expect(
            typeof commands.insertImage,
            'commands.insertImage must exist',
        ).toBe('function');

        const nextState = applyCommand(
            state,
            commands.insertImage({
                assetKey: 'asset_001',
                alt: 'Hình minh hoạ',
            }),
        );

        const image = nextState.doc.child(1);

        expect(image.type.name).toBe('image');

        expect(
            image.attrs.assetKey,
        ).toBe('asset_001');

        expect(
            image.attrs.alt,
        ).toBe('Hình minh hoạ');
    });
});

describe('DIGIERA Native Insert ribbon math hook', () => {
    it('inserts a math block after the current block', () => {
        let state = createEditorState({
            type: 'doc',
            content: [
                {
                    type: 'paragraph',
                    content: [
                        {
                            type: 'text',
                            text: 'Before math',
                        },
                    ],
                },
            ],
        });

        state = state.apply(
            state.tr.setSelection(
                TextSelection.create(
                    state.doc,
                    1,
                ),
            ),
        );

        expect(
            typeof commands.insertMathBlock,
            'commands.insertMathBlock must exist',
        ).toBe('function');

        const nextState = applyCommand(
            state,
            commands.insertMathBlock({
                source: 'x^2 + y^2',
            }),
        );

        const math = nextState.doc.child(1);

        expect(math.type.name).toBe('mathBlock');

        expect(
            math.attrs.source,
        ).toBe('x^2 + y^2');
    });
});

describe('DIGIERA Native Insert ribbon question command', () => {
    it('inserts a question block with a stable id', () => {
        let state = createEditorState({
            type: 'doc',
            content: [
                {
                    type: 'paragraph',
                    content: [
                        {
                            type: 'text',
                            text: 'Before question',
                        },
                    ],
                },
            ],
        });

        state = state.apply(
            state.tr.setSelection(
                TextSelection.create(
                    state.doc,
                    1,
                ),
            ),
        );

        expect(
            typeof commands.insertQuestion,
            'commands.insertQuestion must exist',
        ).toBe('function');

        const nextState = applyCommand(
            state,
            commands.insertQuestion({
                id: 'q2',
                text: 'Câu 2',
            }),
        );

        const question = nextState.doc.child(1);

        expect(question.type.name).toBe('question');

        expect(
            question.attrs.id,
        ).toBe('q2');

        expect(
            question.textContent,
        ).toBe('Câu 2');
    });
});

describe('DIGIERA Native Insert ribbon short answer command', () => {
    it('inserts a short answer linked to the requested question', () => {
        const state = createEditorState({
            type: 'doc',
            content: [
                {
                    type: 'question',
                    attrs: {
                        id: 'q3',
                    },
                    content: [
                        {
                            type: 'text',
                            text: 'Câu 3',
                        },
                    ],
                },
            ],
        });

        expect(
            typeof commands.insertShortAnswer,
            'commands.insertShortAnswer must exist',
        ).toBe('function');

        const nextState = applyCommand(
            state,
            commands.insertShortAnswer({
                questionId: 'q3',
            }),
        );

        expect(
            nextState.doc.child(1).type.name,
        ).toBe('shortAnswer');

        expect(
            nextState.doc.child(1)
                .attrs.questionId,
        ).toBe('q3');

        expect(
            nextState.doc.child(1)
                .attrs.minHeight,
        ).toBe(48);
    });
});

describe('DIGIERA Native Insert ribbon answer table command', () => {
    it('inserts an answer table linked to the requested question', () => {
        const state = createEditorState({
            type: 'doc',
            content: [
                {
                    type: 'question',
                    attrs: {
                        id: 'q4',
                    },
                    content: [
                        {
                            type: 'text',
                            text: 'Câu 4',
                        },
                    ],
                },
            ],
        });

        expect(
            typeof commands.insertAnswerTable,
            'commands.insertAnswerTable must exist',
        ).toBe('function');

        const nextState = applyCommand(
            state,
            commands.insertAnswerTable({
                questionId: 'q4',
                rows: 3,
                cols: 4,
            }),
        );

        const answer =
            nextState.doc.child(1);

        expect(
            answer.type.name,
        ).toBe('answerTable');

        expect(
            answer.attrs.questionId,
        ).toBe('q4');

        expect(answer.attrs.rows).toBe(3);
        expect(answer.attrs.cols).toBe(4);
    });
});
