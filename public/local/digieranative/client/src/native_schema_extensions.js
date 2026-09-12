const FONT_FAMILIES = Object.freeze([
    'Arial',
    'Calibri',
    'Georgia',
    'Times New Roman',
    'Verdana',
]);

const FONT_SIZES = Object.freeze([
    10, 11, 12, 14, 16, 18, 20, 24, 28, 32, 36,
]);

function readFloat(dom, name, fallback = null) {
    const value = Number.parseFloat(dom.getAttribute(name) || '');
    return Number.isFinite(value) ? value : fallback;
}

function readInteger(dom, name, fallback = null) {
    const value = Number.parseInt(dom.getAttribute(name) || '', 10);
    return Number.isFinite(value) ? value : fallback;
}

function optionalDataAttr(attrs, name, value) {
    if (value !== null && value !== undefined && value !== '') {
        attrs[name] = String(value);
    }
    return attrs;
}

export const NATIVE_FONT_FAMILIES = FONT_FAMILIES;
export const NATIVE_FONT_SIZES = FONT_SIZES;
export const LEGACY_IMAGE_RUNTIME_DEFAULTS = Object.freeze({
    widthPercent: 100,
    align: 'center',
    cropX: 0,
    cropY: 0,
    cropW: 1,
    cropH: 1,
    rotation: 0,
});

export const nativeStyleMarkSpecs = {
    fontFamily: {
        attrs: {family: {}},
        toDOM(mark) {
            return ['span', {
                'data-dgn-font-family': mark.attrs.family,
                style: `font-family: ${mark.attrs.family};`,
            }, 0];
        },
        parseDOM: [{
            tag: 'span[data-dgn-font-family]',
            getAttrs(dom) {
                return {family: dom.getAttribute('data-dgn-font-family') || ''};
            },
        }],
    },
    fontSize: {
        attrs: {px: {}},
        toDOM(mark) {
            return ['span', {
                'data-dgn-font-size': String(mark.attrs.px),
                style: `font-size: ${mark.attrs.px}px;`,
            }, 0];
        },
        parseDOM: [{
            tag: 'span[data-dgn-font-size]',
            getAttrs(dom) {
                return {px: readInteger(dom, 'data-dgn-font-size')};
            },
        }],
    },
    highlight: {
        attrs: {color: {}},
        toDOM(mark) {
            return ['mark', {
                'data-dgn-highlight': mark.attrs.color,
                style: `background-color: ${mark.attrs.color};`,
            }, 0];
        },
        parseDOM: [{
            tag: 'mark[data-dgn-highlight]',
            getAttrs(dom) {
                return {color: dom.getAttribute('data-dgn-highlight') || ''};
            },
        }],
    },
};

export function extendNativeImageSpec(baseSpec) {
    const baseAttrs = baseSpec?.attrs || {};
    return {
        ...baseSpec,
        attrs: {
            ...baseAttrs,
            caption: {default: null},
            widthPercent: {default: null},
            cropX: {default: null},
            cropY: {default: null},
            cropW: {default: null},
            cropH: {default: null},
            rotation: {default: null},
            // Runtime-only preservation metadata. document_adapter strips this before Native JSON persistence.
            _nativeLegacyAttrs: {default: null},
        },
        toDOM(node) {
            const attrs = {
                class: 'dgn-image',
                'data-dgn-image': 'managed',
                'data-asset-key': node.attrs.assetKey,
            };
            optionalDataAttr(attrs, 'data-alt', node.attrs.alt);
            optionalDataAttr(attrs, 'data-title', node.attrs.title);
            optionalDataAttr(attrs, 'data-caption', node.attrs.caption);
            optionalDataAttr(attrs, 'data-width', node.attrs.width);
            optionalDataAttr(attrs, 'data-width-percent', node.attrs.widthPercent);
            optionalDataAttr(attrs, 'data-align', node.attrs.align);
            optionalDataAttr(attrs, 'data-crop-x', node.attrs.cropX);
            optionalDataAttr(attrs, 'data-crop-y', node.attrs.cropY);
            optionalDataAttr(attrs, 'data-crop-w', node.attrs.cropW);
            optionalDataAttr(attrs, 'data-crop-h', node.attrs.cropH);
            optionalDataAttr(attrs, 'data-rotation', node.attrs.rotation);
            return [
                'figure',
                attrs,
                ['span', {class: 'dgn-image__placeholder'}, node.attrs.alt || 'Image'],
            ];
        },
        parseDOM: [{
            tag: 'figure[data-dgn-image="managed"]',
            getAttrs(dom) {
                return {
                    assetKey: dom.getAttribute('data-asset-key') || '',
                    alt: dom.getAttribute('data-alt'),
                    title: dom.getAttribute('data-title'),
                    caption: dom.getAttribute('data-caption'),
                    width: readInteger(dom, 'data-width'),
                    widthPercent: readInteger(dom, 'data-width-percent'),
                    align: dom.getAttribute('data-align'),
                    cropX: readFloat(dom, 'data-crop-x'),
                    cropY: readFloat(dom, 'data-crop-y'),
                    cropW: readFloat(dom, 'data-crop-w'),
                    cropH: readFloat(dom, 'data-crop-h'),
                    rotation: readInteger(dom, 'data-rotation'),
                    _nativeLegacyAttrs: null,
                };
            },
        }],
    };
}

export function extendNodeSpecs(nodeSpecs) {
    return {
        ...nodeSpecs,
        image: extendNativeImageSpec(nodeSpecs.image),
    };
}

export function extendMarkSpecs(markSpecs) {
    return {
        ...markSpecs,
        ...nativeStyleMarkSpecs,
    };
}
