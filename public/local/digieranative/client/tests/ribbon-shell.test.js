/* @vitest-environment jsdom */

import fs from 'node:fs';
import path from 'node:path';

import {
    describe,
    expect,
    it,
} from 'vitest';

import {
    TextSelection,
} from 'prosemirror-state';

import {
    mount,
} from '../src/index.js';

describe('DIGIERA Native Word-like Ribbon', () => {
    it('renders the five scoped Ribbon tabs and wires Home Bold', () => {
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

                        content: [
                            {
                                type: 'text',
                                text: 'DIGIERA',
                            },
                        ],
                    },
                ],
            },
        });

        const tabs = Array.from(
            element.querySelectorAll(
                '.dgn-ribbon-tab',
            ),
        ).map(
            (node) =>
                node.textContent.trim(),
        );

        expect(
            tabs,
            'Ribbon tabs must be File | Home | Insert | Layout | Review',
        ).toEqual([
            'File',
            'Home',
            'Insert',
            'Layout',
            'Review',
        ]);

        expect(
            element.classList.contains(
                'dgn-editor',
            ),
        ).toBe(true);

        expect(
            element.querySelector(
                '.dgn-ribbon',
            ),
        ).not.toBeNull();

        expect(
            view.dom.classList.contains(
                'dgn-canvas',
            ),
        ).toBe(true);

        view.dispatch(
            view.state.tr.setSelection(
                TextSelection.create(
                    view.state.doc,
                    1,
                    8,
                ),
            ),
        );

        const bold =
            element.querySelector(
                '[data-dgn-command="bold"]',
            );

        expect(bold).not.toBeNull();

        bold.click();

        expect(
            view.state.doc
                .child(0)
                .child(0)
                .marks
                .map(
                    (mark) =>
                        mark.type.name,
                ),
        ).toContain('bold');

        const css = fs.readFileSync(
            path.resolve(
                process.cwd(),
                '../styles.css',
            ),
            'utf8',
        );

        const start =
            css.indexOf(
                'DIGIERA_NATIVE_RIBBON_START',
            );

        const end =
            css.indexOf(
                'DIGIERA_NATIVE_RIBBON_END',
            );

        expect(start).toBeGreaterThan(-1);
        expect(end).toBeGreaterThan(start);

        const scoped =
            css.slice(start, end);

        expect(scoped).not.toMatch(
            /(^|[,{]\s*)(\.btn|\.card|\.navbar)\b/m,
        );

        view.destroy();
        element.remove();
    });
});

describe('DIGIERA Native readonly editor', () => {
    it('makes the canvas and Ribbon actions read-only', () => {
        const element =
            document.createElement('div');

        document.body.append(element);

        const view = mount({
            element,
            readonly: true,

            documentJson: {
                type: 'doc',

                content: [
                    {
                        type: 'paragraph',
                    },
                ],
            },
        });

        expect(
            view.dom.getAttribute(
                'contenteditable',
            ),
            'readonly canvas must not be editable',
        ).toBe('false');

        expect(
            element
                .querySelector(
                    '[data-dgn-command="bold"]',
                )
                .disabled,
        ).toBe(true);

        view.destroy();
        element.remove();
    });
});

describe('DIGIERA Native File save hook', () => {
    it('passes canonical worksheet JSON to the save callback', () => {
        const element =
            document.createElement('div');

        document.body.append(element);

        let saved = null;

        const view = mount({
            element,

            documentJson: {
                type: 'worksheet',
                version: 1,

                meta: {
                    title:
                        'Save contract',
                },

                content: [
                    {
                        type: 'paragraph',

                        content: [
                            {
                                type: 'text',
                                text: 'Save me',
                            },
                        ],
                    },
                ],
            },

            save(documentJson) {
                saved = documentJson;
            },
        });

        element
            .querySelector(
                '[data-dgn-command="save"]',
            )
            .click();

        expect(
            saved,
            'save callback must receive canonical worksheet JSON',
        ).not.toBeNull();

        expect(saved.type).toBe(
            'worksheet',
        );

        expect(saved.version).toBe(1);

        expect(
            saved.meta.title,
        ).toBe('Save contract');

        expect(
            saved.content[0]
                .content[0]
                .text,
        ).toBe('Save me');

        view.destroy();
        element.remove();
    });
});
