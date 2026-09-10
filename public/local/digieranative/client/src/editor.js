import React from 'react';
import {flushSync} from 'react-dom';
import {createRoot} from 'react-dom/client';
import {useEditorState} from '@tiptap/react';
import {Editor} from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import TextAlign from '@tiptap/extension-text-align';

// Frozen low-level state factory retained for command/schema regression tests during migration.
import {history} from 'prosemirror-history';
import {keymap} from 'prosemirror-keymap';
import {baseKeymap} from 'prosemirror-commands';
import {EditorState} from 'prosemirror-state';

import {fromNativeDocument, toNativeDocument} from './document_adapter.js';
import {keyboardBindings} from './keyboard.js';
import {createPasteHandler} from './paste.js';
import {schema} from './schema.js';
import {createDigieraExtensions} from './tiptap_extensions.js';
import {OfficialEditor} from './ui/official_editor.js';

export function createEditorState(documentJson) {
    if (documentJson === null || typeof documentJson !== 'object' || Array.isArray(documentJson)) {
        throw new TypeError('Native document must be an object');
    }
    const doc = schema.nodeFromJSON(fromNativeDocument(documentJson));
    return EditorState.create({
        doc,
        plugins: [history(), keymap(keyboardBindings), keymap(baseKeymap)],
    });
}

export function mount(config = {}) {
    if (config === null || typeof config !== 'object' || Array.isArray(config)) {
        throw new TypeError('Editor config must be an object');
    }

    const {
        element,
        readonly = false,
        save = null,
        onUpdate = null,
        documentJson = {type: 'worksheet', version: 1, content: []},
    } = config;

    if (element === null || typeof element !== 'object') {
        throw new TypeError('Editor element must be a DOM element');
    }

    const source = documentJson.type === 'worksheet'
        ? documentJson
        : {type: 'worksheet', version: 1, content: documentJson.content || []};
    const pasteHandler = createPasteHandler();
    const document = element.ownerDocument;

    element.replaceChildren();
    element.classList.add('dgn-editor');

    const toolbarHost = document.createElement('div');
    toolbarHost.className = 'dgn-official-toolbar-host';
    const canvasHost = document.createElement('div');
    canvasHost.className = 'dgn-canvas-host';
    element.append(toolbarHost, canvasHost);

    const editor = new Editor({
        element: canvasHost,
        content: fromNativeDocument(documentJson),
        editable: !readonly,
        extensions: [
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
        ],
        editorProps: {
            attributes: {class: 'dgn-canvas'},
            handlePaste(view, event) {
                return pasteHandler(view, event);
            },
        },
        onUpdate({editor: current}) {
            if (typeof onUpdate === 'function') {
                onUpdate(toNativeDocument(current.getJSON(), {
                    version: source.version || 1,
                    meta: source.meta,
                }));
            }
        },
    });

    const reactRoot = createRoot(toolbarHost);
    flushSync(() => {
        reactRoot.render(React.createElement(OfficialEditor, {
            editor,
            hostElement: element,
            readonly,
            save,
            documentJson: source,
            useTiptapEditorState: useEditorState,
        }));
    });

    const originalDestroy = editor.destroy.bind(editor);
    let destroyed = false;
    editor.destroy = () => {
        if (destroyed) {
            return;
        }
        destroyed = true;
        reactRoot.unmount();
        originalDestroy();
    };

    return editor;
}
