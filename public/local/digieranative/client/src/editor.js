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

function numericData(figure, name, fallback) {
    const value = Number.parseFloat(figure.getAttribute(name) || '');
    return Number.isFinite(value) ? value : fallback;
}

function clamp(value, min, max) {
    return Math.min(max, Math.max(min, value));
}

function cropInset(value) {
    return `${Math.max(0, value) * 100}%`;
}

function applyManagedImagePreview(document, figure, url) {
    const widthPercent = clamp(numericData(figure, 'data-width-percent', 100), 10, 100);
    const align = ['left', 'center', 'right'].includes(figure.getAttribute('data-align'))
        ? figure.getAttribute('data-align')
        : 'center';
    const rotation = [0, 90, 180, 270].includes(numericData(figure, 'data-rotation', 0))
        ? numericData(figure, 'data-rotation', 0)
        : 0;
    const cropX = clamp(numericData(figure, 'data-crop-x', 0), 0, 1);
    const cropY = clamp(numericData(figure, 'data-crop-y', 0), 0, 1);
    const cropW = clamp(numericData(figure, 'data-crop-w', 1), 0, 1);
    const cropH = clamp(numericData(figure, 'data-crop-h', 1), 0, 1);
    const cropRight = Math.max(0, 1 - (cropX + cropW));
    const cropBottom = Math.max(0, 1 - (cropY + cropH));

    figure.style.width = `${widthPercent}%`;
    figure.style.maxWidth = '100%';
    figure.style.boxSizing = 'border-box';
    figure.style.backgroundImage = '';
    figure.style.marginLeft = align === 'right' || align === 'center' ? 'auto' : '0px';
    figure.style.marginRight = align === 'left' || align === 'center' ? 'auto' : '0px';

    let image = figure.querySelector('img.dgn-image__preview');
    if (!image) {
        image = document.createElement('img');
        image.className = 'dgn-image__preview';
        const placeholder = figure.querySelector('.dgn-image__placeholder');
        if (placeholder) {
            placeholder.before(image);
        } else {
            figure.prepend(image);
        }
    }
    image.setAttribute('src', url);
    image.setAttribute('alt', figure.getAttribute('data-alt') || '');
    const title = figure.getAttribute('data-title');
    if (title) {
        image.setAttribute('title', title);
    } else {
        image.removeAttribute('title');
    }
    image.style.display = 'block';
    image.style.width = '100%';
    image.style.maxWidth = '100%';
    image.style.height = 'auto';
    image.style.objectFit = 'contain';
    image.style.transformOrigin = 'center center';
    image.style.transform = rotation ? `rotate(${rotation}deg)` : '';
    image.style.clipPath = cropX > 0 || cropY > 0 || cropRight > 0 || cropBottom > 0
        ? `inset(${cropInset(cropY)} ${cropInset(cropRight)} ${cropInset(cropBottom)} ${cropInset(cropX)})`
        : '';

    const placeholder = figure.querySelector('.dgn-image__placeholder');
    if (placeholder) {
        placeholder.style.display = 'none';
    }

    const caption = figure.getAttribute('data-caption') || '';
    let figcaption = figure.querySelector('figcaption.dgn-image__caption');
    if (caption) {
        if (!figcaption) {
            figcaption = document.createElement('figcaption');
            figcaption.className = 'dgn-image__caption';
            figure.append(figcaption);
        }
        figcaption.textContent = caption;
    } else if (figcaption) {
        figcaption.remove();
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
                figure.querySelector('img.dgn-image__preview')?.remove();
                const placeholder = figure.querySelector('.dgn-image__placeholder');
                if (placeholder) {
                    placeholder.style.display = '';
                }
                return;
            }
            figure.setAttribute('data-asset-url', url);
            applyManagedImagePreview(document, figure, url);
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