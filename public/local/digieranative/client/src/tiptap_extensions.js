import {Node} from '@tiptap/core';
import {nodeSpecs} from './schema_specs.js';
import {extendNodeSpecs} from './native_schema_extensions.js';

const PROVIDED_BY_STARTER = new Set([
    'doc', 'paragraph', 'heading', 'orderedList', 'listItem',
    'horizontalRule', 'text', 'hardBreak',
]);

function attributesFromSpec(spec) {
    const result = {};
    for (const [name, definition] of Object.entries(spec.attrs || {})) {
        result[name] = {default: definition.default ?? null};
    }
    return result;
}

function nodeExtension(name, spec) {
    return Node.create({
        name,
        group: spec.group,
        content: spec.content,
        inline: spec.inline,
        atom: spec.atom,
        selectable: spec.selectable,
        draggable: spec.draggable,
        defining: spec.defining,
        isolating: spec.isolating,
        addAttributes() {
            return attributesFromSpec(spec);
        },
        parseHTML() {
            return (spec.parseDOM || []).map((rule) => ({...rule}));
        },
        renderHTML({node}) {
            return spec.toDOM ? spec.toDOM(node) : ['div', 0];
        },
    });
}

export function createDigieraExtensions() {
    const extensions = [];
    const extendedNodes = extendNodeSpecs(nodeSpecs);
    for (const [name, spec] of Object.entries(extendedNodes)) {
        if (!PROVIDED_BY_STARTER.has(name) && name !== 'unorderedList') {
            extensions.push(nodeExtension(name, spec));
        }
    }
    return extensions;
}
