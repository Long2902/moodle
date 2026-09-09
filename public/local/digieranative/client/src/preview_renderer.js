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
                HTMLAttributes: {
                    rel: 'noopener noreferrer nofollow',
                },
            },
            trailingNode: false,
        }),
        TextAlign.configure({types: ['heading', 'paragraph']}),
        ...createDigieraExtensions(),
    ];
}

export function renderPreview(nativeJson, targetElement) {
    if (targetElement === null || typeof targetElement !== 'object') {
        throw new TypeError('Preview target must be a DOM element');
    }

    const canonical = typeof nativeJson === 'string' ? JSON.parse(nativeJson) : nativeJson;
    const tiptapJson = fromNativeDocument(canonical);
    targetElement.innerHTML = generateHTML(tiptapJson, previewExtensions());
    targetElement.classList.add('dgn-preview');
    targetElement.querySelectorAll('[contenteditable]').forEach((node) => node.removeAttribute('contenteditable'));
    return targetElement;
}
