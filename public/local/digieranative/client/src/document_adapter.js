function mapNode(node, direction) {
    if (node === null || typeof node !== 'object' || Array.isArray(node)) {
        return node;
    }

    const mapped = {...node};
    if (direction === 'toTiptap' && mapped.type === 'unorderedList') {
        mapped.type = 'bulletList';
    } else if (direction === 'toNative' && mapped.type === 'bulletList') {
        mapped.type = 'unorderedList';
    }

    if (mapped.attrs && typeof mapped.attrs === 'object') {
        mapped.attrs = {...mapped.attrs};
        if (direction === 'toTiptap' && Object.prototype.hasOwnProperty.call(mapped.attrs, 'align')) {
            mapped.attrs.textAlign = mapped.attrs.align;
            delete mapped.attrs.align;
        } else if (direction === 'toNative' && Object.prototype.hasOwnProperty.call(mapped.attrs, 'textAlign')) {
            mapped.attrs.align = mapped.attrs.textAlign;
            delete mapped.attrs.textAlign;
        }
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
