import React from 'react';
import {flushSync} from 'react-dom';
import {createRoot} from 'react-dom/client';
import {useEditorState} from '@tiptap/react';
import {Editor} from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Color from '@tiptap/extension-color';
import Highlight from '@tiptap/extension-highlight';
import {TableKit} from '@tiptap/extension-table';
import TextAlign from '@tiptap/extension-text-align';
import {TextStyle, FontFamily, FontSize} from '@tiptap/extension-text-style';

// Frozen low-level state factory retained for command/schema regression tests during migration.
import {history} from 'prosemirror-history';
import {keymap} from 'prosemirror-keymap';
import {baseKeymap} from 'prosemirror-commands';
import {EditorState} from 'prosemirror-state';

import {fromNativeDocument, toNativeDocument} from './document_adapter.js';
import {assertImageAdapter, insertPictureAsset, updatePictureAsset} from './image_adapter.js';
import {keyboardBindings} from './keyboard.js';
import {createPasteHandler} from './paste.js';
import {schema} from './schema.js';
import {createDigieraExtensions} from './tiptap_extensions.js';
import {OfficialEditor} from './ui/official_editor.js';

const DEFAULT_LAYOUT = Object.freeze({paper: 'A4', orientation: 'portrait', margin: 'normal'});

function normalizeLayout(value) {
    const source = value && typeof value === 'object' && !Array.isArray(value) ? value : {};
    return {
        paper: 'A4',
        orientation: source.orientation === 'landscape' ? 'landscape' : 'portrait',
        margin: ['normal', 'narrow', 'wide'].includes(source.margin) ? source.margin : 'normal',
    };
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
        print = null,
        downloadPdf = null,
        uploadImage = null,
        imageAdapter = null,
        requestMath = null,
        assetUrls = {},
        documentJson = {type: 'worksheet', version: 1, content: []},
    } = config;

    if (element === null || typeof element !== 'object') {
        throw new TypeError('Editor element must be a DOM element');
    }

    const boundImageAdapter = assertImageAdapter(imageAdapter);
    const source = documentJson.type === 'worksheet'
        ? documentJson
        : {type: 'worksheet', version: 1, content: documentJson.content || []};
    const metadata = source.meta && typeof source.meta === 'object' && !Array.isArray(source.meta)
        ? structuredClone(source.meta)
        : {};
    metadata.layout = normalizeLayout(metadata.layout || DEFAULT_LAYOUT);
    const runtimeAssetUrls = {...(assetUrls || {})};

    const pasteHandler = createPasteHandler();
    const document = element.ownerDocument;

    element.replaceChildren();
    element.classList.add('dgn-editor');
    element.dataset.orientation = metadata.layout.orientation;
    element.dataset.margin = metadata.layout.margin;

    const toolbarHost = document.createElement('div');
    toolbarHost.className = 'dgn-official-toolbar-host';
    const canvasHost = document.createElement('div');
    canvasHost.className = 'dgn-canvas-host';
    canvasHost.style.setProperty('--dgn-zoom', '1');
    element.append(toolbarHost, canvasHost);

    let editor = null;
    const serialize = () => toNativeDocument(editor.getJSON(), {
        version: source.version || 1,
        meta: structuredClone(metadata),
    });
    const refreshAssetPreviews = urls => {
        Object.assign(runtimeAssetUrls, urls || {});
        element.querySelectorAll('figure[data-asset-key]').forEach(figure => {
            const key = figure.getAttribute('data-asset-key') || '';
            const url = safeAssetUrl(runtimeAssetUrls[key]);
            if (!url) {
                figure.removeAttribute('data-asset-url');
                figure.style.removeProperty('background-image');
                return;
            }
            figure.setAttribute('data-asset-url', url);
            figure.style.backgroundImage = `url("${url.replaceAll('"', '%22')}")`;
            const placeholder = figure.querySelector('.dgn-image__placeholder');
            if (placeholder) {
                placeholder.style.visibility = 'hidden';
            }
        });
    };
    const notifyUpdate = () => {
        refreshAssetPreviews();
        if (typeof onUpdate === 'function' && editor) {
            onUpdate(serialize());
        }
    };
    const saveNow = () => typeof save === 'function' ? save(serialize()) : null;
    const updateLayout = next => {
        metadata.layout = normalizeLayout(next);
        element.dataset.orientation = metadata.layout.orientation;
        element.dataset.margin = metadata.layout.margin;
        notifyUpdate();
        return {...metadata.layout};
    };

    editor = new Editor({
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
                    HTMLAttributes: {rel: 'noopener noreferrer nofollow'},
                },
                trailingNode: false,
            }),
            TextStyle,
            FontFamily,
            FontSize,
            Color,
            Highlight.configure({multicolor: true}),
            TextAlign.configure({types: ['heading', 'paragraph', 'tableCell']}),
            TableKit.configure({table: {resizable: true}}),
            ...createDigieraExtensions(),
        ],
        editorProps: {
            attributes: {class: 'dgn-canvas'},
            handlePaste(view, event) {
                return pasteHandler(view, event);
            },
        },
        onUpdate() {
            notifyUpdate();
        },
    });

    editor.getNativeJSON = serialize;
    editor.updateNativeLayout = updateLayout;
    editor.refreshAssetPreviews = refreshAssetPreviews;
    editor.getAssetUrls = () => ({...runtimeAssetUrls});
    editor.insertPictureAsset = options => insertPictureAsset(editor, boundImageAdapter, options);
    editor.updatePictureAsset = (assetKey, edits = {}, file = null) =>
        updatePictureAsset(editor, boundImageAdapter, assetKey, edits, file);
    editor.openPictureEditor = (detail = {}) => {
        const EventClass = document.defaultView.CustomEvent;
        element.dispatchEvent(new EventClass('digiera-native:open-picture-editor', {
            bubbles: true,
            detail: {
                editor,
                capturedPos: editor.state.selection.from,
                ...detail,
            },
        }));
        return true;
    };
    refreshAssetPreviews();

    const reactRoot = createRoot(toolbarHost);
    flushSync(() => {
        reactRoot.render(React.createElement(OfficialEditor, {
            editor,
            hostElement: element,
            readonly,
            saveNow,
            print,
            downloadPdf,
            uploadImage,
            imageAdapter: boundImageAdapter,
            requestMath,
            updateLayout,
            initialLayout: metadata.layout,
            useTiptapEditorState: useEditorState,
        }));
    });

    // Keep the compact Cambridge ribbon on one horizontal row. The base stylesheet
    // supplies overflow-x:auto; these runtime layout properties also protect
    // installations where an older cached stylesheet is still present briefly.
    const cambridgeToolbar = toolbarHost.querySelector('.dgn-cambridge-toolbar');
    if (cambridgeToolbar) {
        cambridgeToolbar.style.display = 'flex';
        cambridgeToolbar.style.flexWrap = 'nowrap';
        cambridgeToolbar.style.alignItems = 'center';
        cambridgeToolbar.style.gap = '.18rem';
        cambridgeToolbar.style.minHeight = '2.75rem';
        cambridgeToolbar.style.padding = '.35rem .45rem';
    }

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
