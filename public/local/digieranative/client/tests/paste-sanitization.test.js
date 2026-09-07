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
            typeof view.props.handlePaste,
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
            view.props.handlePaste(
                view,
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
});
