export function fromNativeDocument(documentJson) {
    if (
        documentJson === null ||
        typeof documentJson !== 'object' ||
        Array.isArray(documentJson)
    ) {
        throw new TypeError(
            'Native document must be an object',
        );
    }

    if (documentJson.type === 'doc') {
        return documentJson;
    }

    if (documentJson.type !== 'worksheet') {
        throw new TypeError(
            `Unsupported document root: ${documentJson.type}`,
        );
    }

    if (documentJson.version !== 1) {
        throw new TypeError(
            `Unsupported Native document version: ${documentJson.version}`,
        );
    }

    if (!Array.isArray(documentJson.content)) {
        throw new TypeError(
            'Native document content must be an array',
        );
    }

    return {
        type: 'doc',
        content: documentJson.content,
    };
}

export function toNativeDocument(
    prosemirrorJson,
    {
        version = 1,
        meta = undefined,
    } = {},
) {
    if (
        prosemirrorJson === null ||
        typeof prosemirrorJson !== 'object' ||
        Array.isArray(prosemirrorJson) ||
        prosemirrorJson.type !== 'doc'
    ) {
        throw new TypeError(
            'ProseMirror document must have doc root',
        );
    }

    const result = {
        type: 'worksheet',
        version,
        content: Array.isArray(
            prosemirrorJson.content,
        )
            ? prosemirrorJson.content
            : [],
    };

    if (meta !== undefined) {
        result.meta = meta;
    }

    return result;
}
