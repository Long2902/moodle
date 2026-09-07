import {
    DOMParser as ProseMirrorDOMParser,
} from 'prosemirror-model';

import {
    schema,
} from './schema.js';

const BLOCKED_TAGS = new Set([
    'script',
    'style',
    'iframe',
    'object',
    'embed',
    'link',
    'meta',
]);

const SAFE_ATTRIBUTES = new Set([
    'alt',
    'title',
    'colspan',
    'rowspan',
    'start',
    'href',
    'src',
]);

const SAFE_DATA_ATTRIBUTES = new Set([
    'data-align',
    'data-dgn-image',
    'data-asset-key',
    'data-alt',
    'data-title',
    'data-width',
    'data-dgn-instruction',
    'data-variant',
    'data-question-id',
    'data-points',
    'data-digiera-answer',
    'data-min-height',
    'data-placeholder',
    'data-dgn-checkbox',
    'data-option-id',
    'data-checked',
    'data-label',
    'data-dgn-multiple-choice',
    'data-selection-mode',
    'data-rows',
    'data-cols',
    'data-dgn-teacher-note',
    'data-dgn-rubric-anchor',
    'data-dgn-math-block',
    'data-dgn-math-inline',
    'data-source',
    'data-dgn-page-break',
    'data-dgn-text-color',
]);

const SAFE_ASSET_KEY =
    /^[A-Za-z0-9_-]{1,128}$/;

function isSafeUrl(value) {
    if (
        typeof value !== 'string' ||
        value === ''
    ) {
        return false;
    }

    if (value.startsWith('/')) {
        return true;
    }

    try {
        const url =
            new URL(value);

        return (
            url.protocol === 'http:' ||
            url.protocol === 'https:' ||
            url.protocol ===
                'dgn-asset:'
        );
    } catch {
        return false;
    }
}

function sanitizeClassList(
    element,
) {
    const classes =
        element
            .getAttribute('class')
            ?.split(/\s+/)
            .filter(Boolean)
            .filter(
                (name) =>
                    name.startsWith(
                        'dgn-',
                    ),
            ) || [];

    if (classes.length === 0) {
        element.removeAttribute(
            'class',
        );

        return;
    }

    element.setAttribute(
        'class',
        classes.join(' '),
    );
}

function sanitizeAttributes(
    element,
) {
    for (
        const attribute
        of Array.from(
            element.attributes,
        )
    ) {
        const name =
            attribute.name
                .toLowerCase();

        const value =
            attribute.value;

        if (
            name.startsWith('on') ||
            name === 'style' ||
            name === 'srcdoc'
        ) {
            element.removeAttribute(
                attribute.name,
            );

            continue;
        }

        if (name === 'class') {
            sanitizeClassList(
                element,
            );

            continue;
        }

        if (
            SAFE_DATA_ATTRIBUTES.has(
                name,
            )
        ) {
            if (
                name ===
                    'data-asset-key' &&
                !SAFE_ASSET_KEY.test(
                    value,
                )
            ) {
                element.removeAttribute(
                    attribute.name,
                );
            }

            continue;
        }

        if (
            !SAFE_ATTRIBUTES.has(
                name,
            )
        ) {
            element.removeAttribute(
                attribute.name,
            );

            continue;
        }

        if (
            (
                name === 'href' ||
                name === 'src'
            ) &&
            !isSafeUrl(value)
        ) {
            element.removeAttribute(
                attribute.name,
            );
        }
    }
}

export function sanitizePastedHtml(
    html,
    document,
) {
    const root =
        document.createElement(
            'div',
        );

    root.innerHTML =
        String(html || '');

    for (
        const element
        of Array.from(
            root.querySelectorAll('*'),
        )
    ) {
        const tag =
            element.tagName
                .toLowerCase();

        if (
            BLOCKED_TAGS.has(tag)
        ) {
            element.remove();

            continue;
        }

        sanitizeAttributes(
            element,
        );
    }

    return root;
}

export function parsePastedHtml(
    html,
    document,
) {
    const root =
        sanitizePastedHtml(
            html,
            document,
        );

    return ProseMirrorDOMParser
        .fromSchema(schema)
        .parseSlice(
            root,
            {
                preserveWhitespace:
                    false,
            },
        );
}

export function createPasteHandler() {
    return (
        view,
        event,
    ) => {
        const clipboard =
            event.clipboardData;

        if (!clipboard) {
            return false;
        }

        const html =
            clipboard.getData(
                'text/html',
            );

        if (html) {
            const document =
                view.dom.ownerDocument;

            const slice =
                parsePastedHtml(
                    html,
                    document,
                );

            view.dispatch(
                view.state.tr
                    .replaceSelection(
                        slice,
                    )
                    .scrollIntoView(),
            );

            if (
                typeof
                    event.preventDefault ===
                'function'
            ) {
                event.preventDefault();
            }

            return true;
        }

        const text =
            clipboard.getData(
                'text/plain',
            );

        if (!text) {
            return false;
        }

        view.dispatch(
            view.state.tr
                .insertText(text)
                .scrollIntoView(),
        );

        if (
            typeof
                event.preventDefault ===
            'function'
        ) {
            event.preventDefault();
        }

        return true;
    };
}
