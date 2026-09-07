import {history} from 'prosemirror-history';
import {keymap} from 'prosemirror-keymap';
import {baseKeymap} from 'prosemirror-commands';
import {EditorState} from 'prosemirror-state';
import {EditorView} from 'prosemirror-view';

import {fromNativeDocument} from './document_adapter.js';
import {keyboardBindings} from './keyboard.js';
import {createPasteHandler} from './paste.js';
import {createRibbon} from './ribbon.js';
import {schema} from './schema.js';

export function createEditorState(documentJson) {
    if (
        documentJson === null ||
        typeof documentJson !== 'object' ||
        Array.isArray(documentJson)
    ) {
        throw new TypeError(
            'Native document must be an object',
        );
    }

    const doc = schema.nodeFromJSON(
        fromNativeDocument(documentJson),
    );

    return EditorState.create({
        doc,
        plugins: [
            history(),
            keymap(keyboardBindings),
            keymap(baseKeymap),
        ],
    });
}

export function mount(config = {}) {
    if (
        config === null ||
        typeof config !== 'object' ||
        Array.isArray(config)
    ) {
        throw new TypeError(
            'Editor config must be an object',
        );
    }

    const {
        element,
        readonly = false,
        save = null,
        documentJson = {
            type: 'doc',
            content: [],
        },
    } = config;

    if (
        element === null ||
        typeof element !== 'object'
    ) {
        throw new TypeError(
            'Editor element must be a DOM element',
        );
    }

    const state = createEditorState(documentJson);

    let view = null;

    if (
        element.ownerDocument &&
        typeof element.querySelector === 'function'
    ) {
        createRibbon({
            element,
            readonly,
            save,
            documentJson,
            getView: () => view,
        });
    }

    view = new EditorView(element, {
        state,

        editable: () => !readonly,

        handlePaste:
            createPasteHandler(),

        attributes: {
            class: 'dgn-canvas',
        },
    });

    return view;
}
