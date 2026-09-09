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
import {createRibbon} from './ribbon.js';
import {schema} from './schema.js';
import {createDigieraExtensions} from './tiptap_extensions.js';

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

    const source = documentJson.type === 'worksheet' ? documentJson : {type: 'worksheet', version: 1, content: documentJson.content || []};
    const pasteHandler = createPasteHandler();
    const editor = new Editor({
        element,
        content: fromNativeDocument(documentJson),
        editable: !readonly,
        extensions: [
            StarterKit.configure({
                blockquote: false,
                code: false,
                codeBlock: false,
                link: false,
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
                onUpdate(toNativeDocument(current.getJSON(), {version: source.version || 1, meta: source.meta}));
            }
        },
    });

    if (element.ownerDocument && typeof element.querySelector === 'function') {
        createRibbon({
            element,
            readonly,
            save,
            documentJson: source,
            getEditor: () => editor,
        });
    }

    return editor;
}
