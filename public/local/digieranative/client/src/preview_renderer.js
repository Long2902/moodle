import {generateHTML} from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import TextAlign from '@tiptap/extension-text-align';

import {fromNativeDocument} from './document_adapter.js';
import {createDigieraExtensions} from './tiptap_extensions.js';

function previewExtensions() {
    return [
        StarterKit.configure({
            blockquote: false,
            code: false,
            codeBlock: false,
            link: {
                openOnClick: false,
                autolink: true,
                linkOnPaste: true,
                HTMLAttributes: {rel: 'noopener noreferrer nofollow'},
            },
            trailingNode: false,
        }),
        TextAlign.configure({types: ['heading', 'paragraph']}),
        ...createDigieraExtensions(),
    ];
}

function safeAssetUrl(value) {
    if (typeof value !== 'string' || value === '') {
        return null;
    }
    if (value.startsWith('/') && !value.startsWith('//')) {
        return value;
    }
    try {
        const url = new URL(value, 'https://invalid.local');
        return ['http:', 'https:'].includes(url.protocol) ? value : null;
    } catch {
        return null;
    }
}

export function renderPreview(nativeJson, targetElement, assetUrls = {}) {
    if (targetElement === null || typeof targetElement !== 'object') {
        throw new TypeError('Preview target must be a DOM element');
    }

    const canonical = typeof nativeJson === 'string' ? JSON.parse(nativeJson) : nativeJson;
    const tiptapJson = fromNativeDocument(canonical);
    targetElement.innerHTML = generateHTML(tiptapJson, previewExtensions());
    targetElement.classList.add('dgn-preview');
    targetElement.querySelectorAll('[contenteditable]').forEach(node => node.removeAttribute('contenteditable'));

    targetElement.querySelectorAll('figure[data-asset-key]').forEach(figure => {
        const key = figure.getAttribute('data-asset-key') || '';
        const url = safeAssetUrl(assetUrls?.[key]);
        if (!url) {
            return;
        }
        const img = targetElement.ownerDocument.createElement('img');
        img.src = url;
        img.alt = figure.getAttribute('data-alt') || '';
        const title = figure.getAttribute('data-title');
        if (title) {
            img.title = title;
        }
        const width = Number.parseInt(figure.getAttribute('data-width') || '', 10);
        if (Number.isFinite(width) && width > 0) {
            img.width = width;
        }
        img.loading = 'lazy';
        figure.replaceChildren(img);
    });

    return targetElement;
}
