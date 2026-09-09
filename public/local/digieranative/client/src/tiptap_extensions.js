import {Mark, Node} from '@tiptap/core';
import {markSpecs, nodeSpecs} from './schema_specs.js';

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

function markExtension(name, spec) {
    return Mark.create({
        name,
        addAttributes() {
            return attributesFromSpec(spec);
        },
        parseHTML() {
            return (spec.parseDOM || []).map((rule) => ({...rule}));
        },
        renderHTML({mark}) {
            return spec.toDOM ? spec.toDOM(mark) : ['span', 0];
        },
    });
}

export function createDigieraExtensions() {
    const extensions = [];
    for (const [name, spec] of Object.entries(nodeSpecs)) {
        if (!PROVIDED_BY_STARTER.has(name) && name !== 'unorderedList') {
            extensions.push(nodeExtension(name, spec));
        }
    }
    // StarterKit provides the standard marks; keep only DIGIERA-specific textColor.
    extensions.push(markExtension('textColor', markSpecs.textColor));
    return extensions;
}
