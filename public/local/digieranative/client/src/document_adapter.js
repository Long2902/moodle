import {LEGACY_IMAGE_RUNTIME_DEFAULTS} from './native_schema_extensions.js';

const NATIVE_STYLE_MARKS = new Set(['textColor', 'fontFamily', 'fontSize']);
const TEXT_ALIGN_TYPES = new Set(['paragraph', 'heading']);

function nativeMarksToTiptap(marks) {
    if (!Array.isArray(marks)) {
        return marks;
    }
    const textStyle = {};
    const result = [];
    for (const mark of marks) {
        if (!mark || typeof mark !== 'object') {
            continue;
        }
        if (mark.type === 'textColor') {
            textStyle.color = mark.attrs?.color;
        } else if (mark.type === 'fontFamily') {
            textStyle.fontFamily = mark.attrs?.family;
        } else if (mark.type === 'fontSize') {
            textStyle.fontSize = Number.isInteger(mark.attrs?.px) ? `${mark.attrs.px}px` : undefined;
        } else {
            result.push({...mark, attrs: mark.attrs ? {...mark.attrs} : mark.attrs});
        }
    }
    const cleanStyle = Object.fromEntries(Object.entries(textStyle).filter(([, value]) => value !== undefined && value !== null && value !== ''));
    if (Object.keys(cleanStyle).length > 0) {
        result.unshift({type: 'textStyle', attrs: cleanStyle});
    }
    return result;
}

function tiptapMarksToNative(marks) {
    if (!Array.isArray(marks)) {
        return marks;
    }
    const result = [];
    for (const mark of marks) {
        if (!mark || typeof mark !== 'object') {
            continue;
        }
        if (mark.type === 'textStyle') {
            const attrs = mark.attrs || {};
            if (typeof attrs.color === 'string' && attrs.color !== '') {
                result.push({type: 'textColor', attrs: {color: attrs.color}});
            }
            if (typeof attrs.fontFamily === 'string' && attrs.fontFamily !== '') {
                result.push({type: 'fontFamily', attrs: {family: attrs.fontFamily}});
            }
            if (typeof attrs.fontSize === 'string' && /^\d+px$/.test(attrs.fontSize)) {
                result.push({type: 'fontSize', attrs: {px: Number.parseInt(attrs.fontSize, 10)}});
            }
        } else if (!NATIVE_STYLE_MARKS.has(mark.type)) {
            result.push({...mark, attrs: mark.attrs ? {...mark.attrs} : mark.attrs});
        }
    }
    return result;
}

function imageToTiptapAttrs(attrs) {
    const original = attrs && typeof attrs === 'object' ? {...attrs} : {};
    const hasCambridgeMetadata = [
        'caption', 'widthPercent', 'cropX', 'cropY', 'cropW', 'cropH', 'rotation',
    ].some((key) => Object.prototype.hasOwnProperty.call(original, key));
    if (hasCambridgeMetadata) {
        return original;
    }
    return {
        ...original,
        ...LEGACY_IMAGE_RUNTIME_DEFAULTS,
        _nativeLegacyAttrs: original,
    };
}

function imageToNativeAttrs(attrs) {
    const source = attrs && typeof attrs === 'object' ? {...attrs} : {};
    const legacy = source._nativeLegacyAttrs;
    delete source._nativeLegacyAttrs;
    if (legacy && typeof legacy === 'object') {
        const matchesDefaults = Object.entries(LEGACY_IMAGE_RUNTIME_DEFAULTS).every(([key, value]) => source[key] === value);
        const unchangedOriginals = Object.entries(legacy).every(([key, value]) => source[key] === value);
        const extraMeaningful = Object.keys(source).some((key) =>
            !Object.prototype.hasOwnProperty.call(legacy, key) &&
            !Object.prototype.hasOwnProperty.call(LEGACY_IMAGE_RUNTIME_DEFAULTS, key) &&
            source[key] !== null && source[key] !== undefined && source[key] !== ''
        );
        if (matchesDefaults && unchangedOriginals && !extraMeaningful) {
            return {...legacy};
        }
    }
    return source;
}

function mapNode(node, direction) {
    if (node === null || typeof node !== 'object' || Array.isArray(node)) {
        return node;
    }

    const mapped = {...node};
    if (direction === 'toTiptap' && mapped.type === 'unorderedList') {
        mapped.type = 'bulletList';
    } else if (direction === 'toNative' && mapped.type === 'bulletList') {
        mapped.type = 'unorderedList';
    } else if (direction === 'toNative' && mapped.type === 'tableHeader') {
        mapped.type = 'tableCell';
    }

    if (mapped.attrs && typeof mapped.attrs === 'object') {
        mapped.attrs = {...mapped.attrs};
        if (TEXT_ALIGN_TYPES.has(mapped.type)) {
            if (direction === 'toTiptap' && Object.prototype.hasOwnProperty.call(mapped.attrs, 'align')) {
                mapped.attrs.textAlign = mapped.attrs.align;
                delete mapped.attrs.align;
            } else if (direction === 'toNative' && Object.prototype.hasOwnProperty.call(mapped.attrs, 'textAlign')) {
                mapped.attrs.align = mapped.attrs.textAlign;
                delete mapped.attrs.textAlign;
            }
        }
    }

    if (mapped.type === 'image') {
        mapped.attrs = direction === 'toTiptap'
            ? imageToTiptapAttrs(mapped.attrs)
            : imageToNativeAttrs(mapped.attrs);
    }

    if (Array.isArray(mapped.marks)) {
        mapped.marks = direction === 'toTiptap'
            ? nativeMarksToTiptap(mapped.marks)
            : tiptapMarksToNative(mapped.marks);
    }

    if (Array.isArray(mapped.content)) {
        mapped.content = mapped.content.map((child) => mapNode(child, direction));
    }

    return mapped;
}

export function fromNativeDocument(documentJson) {
    if (documentJson === null || typeof documentJson !== 'object' || Array.isArray(documentJson)) {
        throw new TypeError('Native document must be an object');
    }
    if (documentJson.type === 'doc') {
        return mapNode(documentJson, 'toTiptap');
    }
    if (documentJson.type !== 'worksheet') {
        throw new TypeError(`Unsupported document root: ${documentJson.type}`);
    }
    if (documentJson.version !== 1) {
        throw new TypeError(`Unsupported Native document version: ${documentJson.version}`);
    }
    if (!Array.isArray(documentJson.content)) {
        throw new TypeError('Native document content must be an array');
    }
    return mapNode({type: 'doc', content: documentJson.content}, 'toTiptap');
}

export function toNativeDocument(prosemirrorJson, {version = 1, meta = undefined} = {}) {
    if (prosemirrorJson === null || typeof prosemirrorJson !== 'object' || Array.isArray(prosemirrorJson) || prosemirrorJson.type !== 'doc') {
        throw new TypeError('ProseMirror document must have doc root');
    }
    const normalized = mapNode(prosemirrorJson, 'toNative');
    const result = {
        type: 'worksheet',
        version,
        content: Array.isArray(normalized.content) ? normalized.content : [],
    };
    if (meta !== undefined) {
        result.meta = meta;
    }
    return result;
}
