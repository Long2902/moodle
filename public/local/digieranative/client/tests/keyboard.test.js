import {
    describe,
    expect,
    it,
} from 'vitest';

import {
    TextSelection,
} from 'prosemirror-state';

import {
    createEditorState,
} from '../src/editor.js';

describe('DIGIERA Native editor keyboard map', () => {
    it('registers Mod-B and applies Bold through the keyboard binding', async () => {
        const keyboard =
            await import(
                '../src/keyboard.js'
            ).catch(
                () => ({}),
            );

        expect(
            typeof keyboard
                .keyboardBindings?.['Mod-b'],
            'Mod-b keyboard binding must exist',
        ).toBe('function');

        let state =
            createEditorState({
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
            state.plugins.some(
                (plugin) =>
                    typeof
                        plugin.props
                            .handleKeyDown ===
                    'function',
            ),
            'editor state must install a keymap plugin',
        ).toBe(true);

        state = state.apply(
            state.tr.setSelection(
                TextSelection.create(
                    state.doc,
                    1,
                    8,
                ),
            ),
        );

        const applied =
            keyboard
                .keyboardBindings[
                    'Mod-b'
                ](
                    state,
                    (transaction) => {
                        state =
                            state.apply(
                                transaction,
                            );
                    },
                );

        expect(applied).toBe(true);

        expect(
            state.doc
                .child(0)
                .child(0)
                .marks
                .map(
                    (mark) =>
                        mark.type.name,
                ),
        ).toContain('bold');
    });
});
