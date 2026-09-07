import {beforeEach, describe, expect, it, vi} from 'vitest';

const viewSpy = vi.hoisted(() => ({
    calls: [],
}));

vi.mock('prosemirror-view', () => ({
    EditorView: class EditorView {
        constructor(target, options) {
            viewSpy.calls.push({
                target,
                options,
            });

            this.target = target;
            this.state = options.state;
        }

        destroy() {}
    },
}));

describe('DIGIERA Native frozen mount(config) API', () => {
    beforeEach(() => {
        viewSpy.calls.length = 0;
    });

    it('mounts into config.element and loads config.documentJson', async () => {
        const native = await import('../src/index.js');

        const element = {
            marker: 'editor-host',
        };

        const documentJson = {
            type: 'doc',
            content: [
                {
                    type: 'paragraph',
                    content: [
                        {
                            type: 'text',
                            text: 'Frozen mount contract',
                        },
                    ],
                },
            ],
        };

        const view = native.mount({
            element,
            documentJson,
        });

        expect(viewSpy.calls).toHaveLength(1);

        expect(
            viewSpy.calls[0].target,
            'EditorView must receive config.element',
        ).toBe(element);

        expect(
            view.state.doc.textContent,
            'mount must use config.documentJson',
        ).toBe('Frozen mount contract');
    });
});
