/* @vitest-environment jsdom */

import {
    describe,
    expect,
    it,
} from 'vitest';

import {
    mount,
} from '../src/index.js';

describe('DIGIERA Native paste sanitization', () => {
    it('sanitizes HTML before inserting it into the editor', () => {
        const element =
            document.createElement('div');

        document.body.append(element);

        const view = mount({
            element,

            documentJson: {
                type: 'worksheet',
                version: 1,

                content: [
                    {
                        type: 'paragraph',
                    },
                ],
            },
        });

        expect(
            typeof view.view.props.handlePaste,
            'editor must install sanitized paste handler',
        ).toBe('function');

        const dirty = `
            <p
                onclick="alert(1)"
                style="position:fixed;color:red"
            >
                Hello
                <strong>World</strong>
                <script>alert(1)</script>
                <a href="javascript:alert(2)">!</a>
                <img src="javascript:alert(3)">
            </p>
        `;

        let prevented = false;

        const event = {
            clipboardData: {
                getData(type) {
                    if (
                        type ===
                        'text/html'
                    ) {
                        return dirty;
                    }

                    if (
                        type ===
                        'text/plain'
                    ) {
                        return 'Hello World !';
                    }

                    return '';
                },
            },

            preventDefault() {
                prevented = true;
            },
        };

        const applied =
            view.view.props.handlePaste(
                view.view,
                event,
            );

        expect(applied).toBe(true);
        expect(prevented).toBe(true);

        expect(
            view.state.doc.textContent,
        ).toContain('Hello');

        expect(
            view.state.doc.textContent,
        ).toContain('World');

        let boldFound = false;

        view.state.doc.descendants(
            (node) => {
                if (
                    node.isText &&
                    node.text?.includes(
                        'World',
                    ) &&
                    node.marks.some(
                        (mark) =>
                            mark.type.name ===
                            'bold',
                    )
                ) {
                    boldFound = true;
                }
            },
        );

        expect(boldFound).toBe(true);

        expect(
            element.innerHTML,
        ).not.toMatch(/<script/i);

        expect(
            element.innerHTML,
        ).not.toMatch(/onclick=/i);

        expect(
            element.innerHTML,
        ).not.toMatch(/style="position:fixed/i);

        expect(
            element.innerHTML,
        ).not.toMatch(/javascript:/i);

        view.destroy();
        element.remove();
    });

    it('converts multiline plain text into semantic paragraph blocks', () => {
        const element = document.createElement('div');
        document.body.append(element);

        const editor = mount({
            element,
            documentJson: {
                type: 'worksheet',
                version: 1,
                content: [{type: 'paragraph'}],
            },
        });

        const fixture = [
            'PHASE 5 TEST',
            'Đây là nội dung kiểm thử Native Editor.',
            'Dòng kiểm tra autosave.',
        ].join('\n');

        const event = {
            clipboardData: {
                getData(type) {
                    if (type === 'text/html') return '';
                    if (type === 'text/plain') return fixture;
                    return '';
                },
            },
            preventDefault() {},
        };

        const applied = editor.view.props.handlePaste(editor.view, event);
        expect(applied).toBe(true);

        const json = editor.getJSON();
        const paragraphs = json.content.filter(node => node.type === 'paragraph');
        expect(paragraphs).toHaveLength(3);
        expect(paragraphs.map(node => node.content?.[0]?.text || '')).toEqual([
            'PHASE 5 TEST',
            'Đây là nội dung kiểm thử Native Editor.',
            'Dòng kiểm tra autosave.',
        ]);

        let rawNewlineTextNode = false;
        editor.state.doc.descendants(node => {
            if (node.isText && /\r|\n/.test(node.text || '')) {
                rawNewlineTextNode = true;
            }
        });
        expect(rawNewlineTextNode).toBe(false);

        editor.destroy();
        element.remove();
    });
});
