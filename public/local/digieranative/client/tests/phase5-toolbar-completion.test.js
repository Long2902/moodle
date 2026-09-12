/* @vitest-environment jsdom */

import {describe, expect, it, vi} from 'vitest';
import {mount} from '../src/index.js';

const worksheet = content => ({type: 'worksheet', version: 1, content});

function click(element, selector) {
    const node = element.querySelector(selector);
    expect(node, `missing ${selector}`).not.toBeNull();
    node.click();
    return node;
}

function change(element, selector, value) {
    const node = element.querySelector(selector);
    expect(node, `missing ${selector}`).not.toBeNull();
    node.value = String(value);
    node.dispatchEvent(new Event('change', {bubbles: true}));
    return node;
}

describe('Phase 5 CambridgePlus toolbar completion', () => {
    it('provides one compact practical command surface without obsolete tabs', () => {
        const element = document.createElement('div');
        document.body.append(element);
        const editor = mount({element, documentJson: worksheet([{type: 'paragraph'}])});
        expect(element.querySelector('[data-dgn-cambridge-toolbar="1"]')).not.toBeNull();
        expect(element.querySelectorAll('[data-dgn-tab]')).toHaveLength(0);
        for (const id of [
            'save', 'print', 'download-pdf', 'undo', 'redo', 'clear-formatting',
            'font-family', 'font-size', 'bold', 'italic', 'underline', 'strike',
            'text-color', 'highlight', 'block-type', 'bullet-list', 'ordered-list',
            'indent-decrease', 'indent-increase', 'align-left', 'align-center', 'align-right', 'align-justify',
            'link', 'unlink', 'insert-picture', 'insert-table', 'add-row', 'add-column',
            'delete-row', 'delete-column', 'delete-table', 'horizontal-rule', 'page-break',
            'orientation-portrait', 'orientation-landscape', 'margin-normal', 'margin-narrow', 'margin-wide', 'zoom',
            'question', 'short-answer', 'long-answer', 'answer-table', 'math',
        ]) {
            expect(element.querySelector(`[data-dgn-command="${id}"]`), `missing ${id}`).not.toBeNull();
        }
        editor.destroy();
        element.remove();
    });

    it('routes File actions to real callbacks', () => {
        const element = document.createElement('div');
        document.body.append(element);
        const save = vi.fn();
        const print = vi.fn();
        const downloadPdf = vi.fn();
        const editor = mount({element, documentJson: worksheet([{type: 'paragraph'}]), save, print, downloadPdf});
        click(element, '[data-dgn-command="save"]');
        click(element, '[data-dgn-command="print"]');
        click(element, '[data-dgn-command="download-pdf"]');
        expect(save).toHaveBeenCalledTimes(1);
        expect(print).toHaveBeenCalledTimes(1);
        expect(downloadPdf).toHaveBeenCalledTimes(1);
        editor.destroy();
        element.remove();
    });

    it.each([
        ['short-answer', 'shortAnswer'],
        ['long-answer', 'longAnswer'],
        ['answer-table', 'answerTable'],
    ])('%s creates or resolves a Question instead of failing silently', (command, answerType) => {
        const element = document.createElement('div');
        document.body.append(element);
        const editor = mount({
            element,
            documentJson: worksheet([{type: 'paragraph', content: [{type: 'text', text: 'Outside question'}]}]),
        });
        click(element, `[data-dgn-command="${command}"]`);
        const json = editor.getJSON();
        const question = json.content.find(node => node.type === 'question');
        const answer = json.content.find(node => node.type === answerType);
        expect(question).toBeTruthy();
        expect(answer).toBeTruthy();
        expect(answer.attrs.questionId).toBe(question.attrs.id);
        editor.destroy();
        element.remove();
    });

    it('persists layout changes in canonical meta and keeps zoom view-only', () => {
        const element = document.createElement('div');
        document.body.append(element);
        let updated = null;
        const editor = mount({
            element,
            documentJson: {type: 'worksheet', version: 1, meta: {title: 'Layout'}, content: [{type: 'paragraph'}]},
            onUpdate: json => { updated = json; },
        });
        click(element, '[data-dgn-command="orientation-landscape"]');
        click(element, '[data-dgn-command="margin-narrow"]');
        expect(updated?.meta?.layout?.paper).toBe('A4');
        expect(updated?.meta?.layout?.orientation).toBe('landscape');
        expect(updated?.meta?.layout?.margin).toBe('narrow');
        change(element, '[data-dgn-command="zoom"]', '125');
        expect(element.querySelector('.dgn-canvas-host').style.getPropertyValue('--dgn-zoom')).toBe('1.25');
        expect(updated?.meta?.layout?.zoom).toBeUndefined();
        editor.destroy();
        element.remove();
    });

    it('routes Math through its real integration callback and exposes a single shared picture path', () => {
        const element = document.createElement('div');
        document.body.append(element);
        const requestMath = vi.fn(({insert}) => insert({source: 'x^2'}));
        const imageAdapter = {
            createAsset: vi.fn(), replaceAsset: vi.fn(), resolvePreview: vi.fn(), onAssetRecord: vi.fn(),
        };
        const editor = mount({element, documentJson: worksheet([{type: 'paragraph'}]), imageAdapter, requestMath});
        expect(element.querySelectorAll('[data-dgn-command="insert-picture"]')).toHaveLength(1);
        click(element, '[data-dgn-command="math"]');
        expect(requestMath).toHaveBeenCalledTimes(1);
        expect(editor.getJSON().content.some(node => node.type === 'mathBlock' && node.attrs.source === 'x^2')).toBe(true);
        editor.destroy();
        element.remove();
    });
});
