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

function openTab(element, name) {
    click(element, `[data-dgn-tab="${name}"]`);
}

describe('Phase 5 completed Tiptap toolbar', () => {
    it('provides working File, Home, Insert, Layout and DIGIERA tabs', () => {
        const element = document.createElement('div');
        document.body.append(element);
        const editor = mount({element, documentJson: worksheet([{type: 'paragraph'}])});
        const tabs = Array.from(element.querySelectorAll('.dgn-toolbar-tab')).map(node => node.dataset.dgnTab);
        expect(tabs).toEqual(['file', 'home', 'insert', 'layout', 'digiera']);
        for (const id of ['save', 'print', 'download-pdf', 'image', 'horizontal-rule', 'page-break',
            'orientation-portrait', 'orientation-landscape', 'question', 'short-answer', 'long-answer',
            'answer-table', 'math']) {
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
        openTab(element, 'file');
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
        openTab(element, 'digiera');
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
        openTab(element, 'layout');
        click(element, '[data-dgn-command="orientation-landscape"]');
        click(element, '[data-dgn-command="margin-narrow"]');
        expect(updated?.meta?.layout?.paper).toBe('A4');
        expect(updated?.meta?.layout?.orientation).toBe('landscape');
        expect(updated?.meta?.layout?.margin).toBe('narrow');
        click(element, '[data-dgn-command="zoom-125"]');
        expect(element.querySelector('.dgn-canvas-host').style.getPropertyValue('--dgn-zoom')).toBe('1.25');
        expect(updated?.meta?.layout?.zoom).toBeUndefined();
        editor.destroy();
        element.remove();
    });

    it('routes Image and Math through real integration callbacks', async () => {
        const element = document.createElement('div');
        document.body.append(element);
        const uploadImage = vi.fn(({insert}) => insert({assetKey: 'asset_123', alt: 'Uploaded'}));
        const requestMath = vi.fn(({insert}) => insert({source: 'x^2'}));
        const editor = mount({element, documentJson: worksheet([{type: 'paragraph'}]), uploadImage, requestMath});
        openTab(element, 'insert');
        click(element, '[data-dgn-command="image"]');
        openTab(element, 'digiera');
        click(element, '[data-dgn-command="math"]');
        await Promise.resolve();
        expect(uploadImage).toHaveBeenCalledTimes(1);
        expect(requestMath).toHaveBeenCalledTimes(1);
        expect(editor.getJSON().content.some(node => node.type === 'image' && node.attrs.assetKey === 'asset_123')).toBe(true);
        expect(editor.getJSON().content.some(node => node.type === 'mathBlock' && node.attrs.source === 'x^2')).toBe(true);
        editor.destroy();
        element.remove();
    });
});
