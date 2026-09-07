import {describe, expect, it} from 'vitest';

describe('DIGIERA Native editor core public API', () => {
    it('exports mount and creates a ProseMirror editor state from Native JSON', async () => {
        const native = await import('../src/index.js');

        expect(typeof native.mount).toBe('function');
        expect(typeof native.createEditorState).toBe('function');

        const state = native.createEditorState({
            type: 'doc',
            content: [
                {
                    type: 'paragraph',
                    content: [
                        {
                            type: 'text',
                            text: 'Xin chào DIGIERA',
                        },
                    ],
                },
            ],
        });

        expect(state.doc.type.name).toBe('doc');
        expect(state.doc.textContent).toBe('Xin chào DIGIERA');
    });
});

describe('DIGIERA Native public command API', () => {
    it('exports worksheet commands through the editor entrypoint', async () => {
        const native = await import('../src/index.js');

        expect(
            native.commands,
            'index.js must expose the editor command registry',
        ).toBeDefined();

        expect(
            typeof native.commands.insertLongAnswer,
        ).toBe('function');
    });
});

describe('DIGIERA Native canonical worksheet adapter', () => {
    it('accepts canonical worksheet JSON when creating editor state', async () => {
        const native = await import('../src/index.js');

        const state = native.createEditorState({
            type: 'worksheet',
            version: 1,
            meta: {
                title: 'Phiếu học tập',
            },
            content: [
                {
                    type: 'paragraph',
                    content: [
                        {
                            type: 'text',
                            text: 'Canonical Native',
                        },
                    ],
                },
            ],
        });

        expect(
            state.doc.type.name,
        ).toBe('doc');

        expect(
            state.doc.textContent,
        ).toBe('Canonical Native');
    });
});
