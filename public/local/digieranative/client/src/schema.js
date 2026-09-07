import {Schema} from 'prosemirror-model';
import {markSpecs, nodeSpecs} from './schema_specs.js';

export const schema = new Schema({
    nodes: nodeSpecs,
    marks: markSpecs,
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

    if (
        typeof value !== 'string' ||
        !SAFE_ID.test(value)
    ) {
        throw new TypeError(`${key} is invalid`);
    }
}

function assertAllowedNodeAttrs(type, attrs) {
    const nodetype = schema.nodes[type];

    if (!nodetype) {
        throw new TypeError(`Unknown Native node type: ${type}`);
    }

    if (
        attrs === null ||
        typeof attrs !== 'object' ||
        Array.isArray(attrs)
    ) {
        throw new TypeError('Node attrs must be an object');
    }

    const allowed = new Set(
        Object.keys(nodetype.spec.attrs ?? {}),
    );

    for (const key of Object.keys(attrs)) {
        if (!allowed.has(key)) {
            throw new TypeError(
                `Unexpected key in ${type} attrs: ${key}`,
            );
        }
    }
}

export function validateNodeAttrs(type, attrs = {}) {
    assertAllowedNodeAttrs(type, attrs);

    if (
        attrs.level !== undefined &&
        attrs.level !== null &&
        (
            !Number.isInteger(attrs.level) ||
            attrs.level < 1 ||
            attrs.level > 6
        )
    ) {
        throw new TypeError(
            'Heading level must be between 1 and 6',
        );
    }

    if (
        attrs.align !== undefined &&
        attrs.align !== null &&
        (
            typeof attrs.align !== 'string' ||
            !ALIGNMENTS.has(attrs.align)
        )
    ) {
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
        if (
            typeof attrs.assetKey !== 'string' ||
            !SAFE_ASSET.test(attrs.assetKey)
        ) {
            throw new TypeError(
                'Image assetKey is invalid',
            );
        }
    }

    if (
        attrs.points !== undefined &&
        attrs.points !== null &&
        (
            typeof attrs.points !== 'number' ||
            !Number.isFinite(attrs.points)
        )
    ) {
        throw new TypeError(
            'Question points must be numeric',
        );
    }

    for (const key of BOUNDED_INTEGER_ATTRS) {
        const value = attrs[key];

        if (
            value !== undefined &&
            value !== null &&
            (
                !Number.isInteger(value) ||
                value < 0 ||
                value > 10000
            )
        ) {
            throw new TypeError(
                `${key} must be a bounded integer`,
            );
        }
    }

    for (const key of BOUNDED_TEXT_ATTRS) {
        const value = attrs[key];

        if (
            value !== undefined &&
            value !== null &&
            (
                typeof value !== 'string' ||
                utf8ByteLength(value) > 2048
            )
        ) {
            throw new TypeError(
                `${key} must be bounded text`,
            );
        }
    }

    if (
        attrs.checked !== undefined &&
        attrs.checked !== null &&
        typeof attrs.checked !== 'boolean'
    ) {
        throw new TypeError(
            'checked must be boolean',
        );
    }

    return true;
}

export function validateMarkAttrs(type, attrs = {}) {
    const marktype = schema.marks[type];

    if (!marktype) {
        throw new TypeError('Unknown Native mark');
    }

    if (
        attrs === null ||
        typeof attrs !== 'object' ||
        Array.isArray(attrs)
    ) {
        throw new TypeError('Mark attrs must be an object');
    }

    if (type === 'textColor') {
        const keys = Object.keys(attrs);

        if (
            keys.some((key) => key !== 'color') ||
            typeof attrs.color !== 'string' ||
            !SAFE_COLOR.test(attrs.color)
        ) {
            throw new TypeError(
                'Invalid text color',
            );
        }

        return true;
    }

    if (Object.keys(attrs).length !== 0) {
        throw new TypeError(
            'This mark does not accept attrs',
        );
    }

    return true;
}
