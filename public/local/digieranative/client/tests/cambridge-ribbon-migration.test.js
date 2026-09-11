/* @vitest-environment jsdom */
import {describe, expect, it, vi} from 'vitest';

import {mount} from '../src/index.js';

function createHost() {
    const element = document.createElement('div');
    document.body.append(element);
    return element;
}

describe('Phase 5 CambridgePlus ribbon migration contract', () => {
    it('mounts one Cambridge-derived compact toolbar and retires the active legacy toolbar', () => {
        const element = createHost();
        const editor = mount({
            element,
            documentJson: {
                type: 'worksheet',
                version: 1,
                content: [{type: 'paragraph'}],
            },
        });

        expect(element.querySelector('[data-dgn-cambridge-toolbar="1"]')).not.toBeNull();
        expect(element.querySelector('[data-dgn-official-toolbar="1"]')).toBeNull();

        editor.destroy();
        element.remove();
    });

    it('exposes the approved practical control surface without dead duplicate image paths', () => {
        const element = createHost();
        const save = vi.fn();
        const print = vi.fn();
        const downloadPdf = vi.fn();
        const editor = mount({
            element,
            save,
            print,
            downloadPdf,
            documentJson: {
                type: 'worksheet',
                version: 1,
                content: [{type: 'paragraph'}],
            },
        });

        const requiredCommands = [
            'undo', 'redo', 'clear-formatting',
            'font-family', 'font-size', 'bold', 'italic', 'underline', 'strike',
            'text-color', 'highlight', 'block-type',
            'bullet-list', 'ordered-list', 'indent-decrease', 'indent-increase',
            'align-left', 'align-center', 'align-right', 'align-justify',
            'insert-picture', 'link', 'unlink', 'insert-table',
            'add-row', 'add-column', 'delete-row', 'delete-column', 'delete-table',
            'horizontal-rule', 'page-break',
            'save', 'print', 'download-pdf',
            'orientation-portrait', 'orientation-landscape',
            'margin-normal', 'margin-narrow', 'margin-wide', 'zoom',
            'question', 'short-answer', 'long-answer', 'answer-table', 'math',
        ];

        for (const command of requiredCommands) {
            expect(
                element.querySelector(`[data-dgn-command="${command}"]`),
                `missing command ${command}`,
            ).not.toBeNull();
        }

        expect(element.querySelectorAll('[data-dgn-command="insert-picture"]')).toHaveLength(1);
        expect(element.querySelector('[data-dgn-command="digiera-image"]')).toBeNull();

        editor.destroy();
        element.remove();
    });
});
