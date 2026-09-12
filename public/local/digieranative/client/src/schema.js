import {Schema} from 'prosemirror-model';
import {markSpecs, nodeSpecs} from './schema_specs.js';
import {
    NATIVE_FONT_FAMILIES,
    NATIVE_FONT_SIZES,
    extendMarkSpecs,
    extendNodeSpecs,
} from './native_schema_extensions.js';

const linkMarkSpec = {
    attrs: {
        href: {},
        target: {default: null},
        rel: {default: null},
        class: {default: null},
    },
    inclusive: false,
    toDOM(mark) {
        const attrs = {href: mark.attrs.href};
        if (mark.attrs.target) attrs.target = mark.attrs.target;
        if (mark.attrs.rel) attrs.rel = mark.attrs.rel;
        if (mark.attrs.class) attrs.class = mark.attrs.class;
        return ['a', attrs, 0];
    },
    parseDOM: [{
        tag: 'a[href]',
        getAttrs(dom) {
            return {
                href: dom.getAttribute('href') || '',
                target: dom.getAttribute('target'),
                rel: dom.getAttribute('rel'),
                class: dom.getAttribute('class'),
            };
        },
    }],
};

const nativeNodeSpecs = extendNodeSpecs(nodeSpecs);
const nativeMarkSpecs = extendMarkSpecs(markSpecs);

export const schema = new Schema({
    nodes: nativeNodeSpecs,
    marks: {...nativeMarkSpecs, link: linkMarkSpec},
});

const SAFE_ID = /^[A-Za-z0-9._:-]{1,128}$/;
const SAFE_ASSET = /^[A-Za-z0-9_-]{1,128}$/;
const SAFE_COLOR = /^#[0-9A-Fa-f]{6}$/;

const ALIGNMENTS = new Set([
    'left',
    'center',
    'right',
    'justify',
]);

const IMAGE_ALIGNMENTS = new Set([
    'left',
    'center',
    'right',
]);

const ANSWER_NODES = new Set([
    'shortAnswer',
    'longAnswer',
    'checkbox',
    'multipleChoice',
    'answerTable',
]);

const BOUNDED_INTEGER_ATTRS = new Set([
    'minHeight',
    'width',
    'order',
    'rows',
    'cols',
    'colspan',
    'rowspan',
]);

const BOUNDED_TEXT_ATTRS = new Set([
    'placeholder',
    'label',
    'alt',
    'title',
    'caption',
    'variant',
    'optionId',
    'selectionMode',
    'source',
]);

function utf8ByteLength(value) {
    return new TextEncoder().encode(value).length;
}

function assertSafeId(attrs, key) {
    const value = attrs[key];
    if (typeof value !== 'string' || !SAFE_ID.test(value)) {
        throw new TypeError(`${key} is invalid`);
    }
}

function isSafeLinkUrl(value) {
    if (typeof value !== 'string' || value === '' || utf8ByteLength(value) > 2048 || /[\u0000-\u001f\u007f]/.test(value)) {
        return false;
    }
    if ((value.startsWith('/') && !value.startsWith('//')) || value.startsWith('#')) {
        return true;
    }
    try {
        const url = new URL(value);
        return ['http:', 'https:', 'mailto:', 'tel:'].includes(url.protocol);
    } catch {
        return false;
    }
}

function assertAllowedNodeAttrs(type, attrs) {
    const nodetype = schema.nodes[type];
    if (!nodetype) {
        throw new TypeError(`Unknown Native node type: ${type}`);
    }
    if (attrs === null || typeof attrs !== 'object' || Array.isArray(attrs)) {
        throw new TypeError('Node attrs must be an object');
    }
    const allowed = new Set(Object.keys(nodetype.spec.attrs ?? {}));
    for (const key of Object.keys(attrs)) {
        if (!allowed.has(key)) {
            throw new TypeError(`Unexpected key in ${type} attrs: ${key}`);
        }
    }
}

function validateOptionalUnitInterval(value, label, {positive = false} = {}) {
    if (value === undefined || value === null) {
        return;
    }
    if (typeof value !== 'number' || !Number.isFinite(value) || value < 0 || value > 1 || (positive && value <= 0)) {
        throw new TypeError(`${label} must be within the allowed range`);
    }
}

export function validateNodeAttrs(type, attrs = {}) {
    assertAllowedNodeAttrs(type, attrs);

    if (attrs.level !== undefined && attrs.level !== null && (!Number.isInteger(attrs.level) || attrs.level < 1 || attrs.level > 6)) {
        throw new TypeError('Heading level must be between 1 and 6');
    }

    if (attrs.align !== undefined && attrs.align !== null && (typeof attrs.align !== 'string' || !ALIGNMENTS.has(attrs.align))) {
        throw new TypeError('Invalid alignment');
    }

    if (type === 'question') {
        assertSafeId(attrs, 'id');
    }

    if (ANSWER_NODES.has(type)) {
        assertSafeId(attrs, 'questionId');
    }

    if (type === 'rubricAnchor') {
        assertSafeId(attrs, 'id');
    }

    if (type === 'image') {
        if (typeof attrs.assetKey !== 'string' || !SAFE_ASSET.test(attrs.assetKey)) {
            throw new TypeError('Image assetKey is invalid');
        }
        if (attrs.align !== undefined && attrs.align !== null && !IMAGE_ALIGNMENTS.has(attrs.align)) {
            throw new TypeError('Invalid image alignment');
        }
        if (attrs.widthPercent !== undefined && attrs.widthPercent !== null &&
                (!Number.isInteger(attrs.widthPercent) || attrs.widthPercent < 10 || attrs.widthPercent > 100)) {
            throw new TypeError('Image widthPercent must be between 10 and 100');
        }
        validateOptionalUnitInterval(attrs.cropX, 'cropX');
        validateOptionalUnitInterval(attrs.cropY, 'cropY');
        validateOptionalUnitInterval(attrs.cropW, 'cropW', {positive: true});
        validateOptionalUnitInterval(attrs.cropH, 'cropH', {positive: true});
        if (attrs.cropX !== undefined && attrs.cropX !== null && attrs.cropW !== undefined && attrs.cropW !== null && attrs.cropX + attrs.cropW > 1) {
            throw new TypeError('cropX + cropW must be <= 1');
        }
        if (attrs.cropY !== undefined && attrs.cropY !== null && attrs.cropH !== undefined && attrs.cropH !== null && attrs.cropY + attrs.cropH > 1) {
            throw new TypeError('cropY + cropH must be <= 1');
        }
        if (attrs.rotation !== undefined && attrs.rotation !== null && ![0, 90, 180, 270].includes(attrs.rotation)) {
            throw new TypeError('Invalid image rotation');
        }
    }

    if (attrs.points !== undefined && attrs.points !== null && (typeof attrs.points !== 'number' || !Number.isFinite(attrs.points))) {
        throw new TypeError('Question points must be numeric');
    }

    for (const key of BOUNDED_INTEGER_ATTRS) {
        const value = attrs[key];
        if (value !== undefined && value !== null && (!Number.isInteger(value) || value < 0 || value > 10000)) {
            throw new TypeError(`${key} must be a bounded integer`);
        }
    }

    for (const key of BOUNDED_TEXT_ATTRS) {
        const value = attrs[key];
        if (value !== undefined && value !== null && (typeof value !== 'string' || utf8ByteLength(value) > 2048)) {
            throw new TypeError(`${key} must be bounded text`);
        }
    }

    if (attrs.checked !== undefined && attrs.checked !== null && typeof attrs.checked !== 'boolean') {
        throw new TypeError('checked must be boolean');
    }

    return true;
}

export function validateMarkAttrs(type, attrs = {}) {
    const marktype = schema.marks[type];
    if (!marktype) {
        throw new TypeError('Unknown Native mark');
    }
    if (attrs === null || typeof attrs !== 'object' || Array.isArray(attrs)) {
        throw new TypeError('Mark attrs must be an object');
    }

    if (type === 'textColor' || type === 'highlight') {
        const keys = Object.keys(attrs);
        if (keys.some((key) => key !== 'color') || typeof attrs.color !== 'string' || !SAFE_COLOR.test(attrs.color)) {
            throw new TypeError(type === 'highlight' ? 'Invalid highlight color' : 'Invalid text color');
        }
        return true;
    }

    if (type === 'fontFamily') {
        if (Object.keys(attrs).some((key) => key !== 'family') || !NATIVE_FONT_FAMILIES.includes(attrs.family)) {
            throw new TypeError('Invalid font family');
        }
        return true;
    }

    if (type === 'fontSize') {
        if (Object.keys(attrs).some((key) => key !== 'px') || !Number.isInteger(attrs.px) || !NATIVE_FONT_SIZES.includes(attrs.px)) {
            throw new TypeError('Invalid font size');
        }
        return true;
    }

    if (type === 'link') {
        const allowed = new Set(['href', 'target', 'rel', 'class']);
        if (Object.keys(attrs).some(key => !allowed.has(key)) || !isSafeLinkUrl(attrs.href)) {
            throw new TypeError('Invalid link href');
        }
        for (const key of ['target', 'rel', 'class']) {
            if (attrs[key] !== undefined && attrs[key] !== null &&
                    (typeof attrs[key] !== 'string' || utf8ByteLength(attrs[key]) > 2048)) {
                throw new TypeError('Invalid link attribute');
            }
        }
        if (attrs.target !== undefined && attrs.target !== null && !['_blank', '_self'].includes(attrs.target)) {
            throw new TypeError('Invalid link target');
        }
        return true;
    }

    if (Object.keys(attrs).length !== 0) {
        throw new TypeError('This mark does not accept attrs');
    }
    return true;
}
