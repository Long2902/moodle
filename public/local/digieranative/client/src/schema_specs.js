const SAFE_ALIGNMENTS = new Set([
    'left',
    'center',
    'right',
    'justify',
]);

function optionalDataAttr(
    attrs,
    name,
    value,
) {
    if (
        value !== null &&
        value !== undefined &&
        value !== ''
    ) {
        attrs[name] = String(value);
    }

    return attrs;
}

function alignmentAttrs(
    base,
    align,
) {
    const attrs = {
        ...base,
    };

    if (
        SAFE_ALIGNMENTS.has(align)
    ) {
        attrs['data-align'] = align;
    }

    return attrs;
}

function readAlignment(dom) {
    const align =
        dom.getAttribute('data-align');

    return SAFE_ALIGNMENTS.has(align)
        ? align
        : null;
}

function readInteger(
    dom,
    name,
    fallback = null,
) {
    const value = Number.parseInt(
        dom.getAttribute(name) || '',
        10,
    );

    return Number.isFinite(value)
        ? value
        : fallback;
}

function readBoolean(
    dom,
    name,
) {
    const value =
        dom.getAttribute(name);

    if (value === 'true') {
        return true;
    }

    if (value === 'false') {
        return false;
    }

    return null;
}

export const nodeSpecs = {
    doc: {
        content: 'block*',
    },

    paragraph: {
        group: 'block',
        content: 'inline*',
        attrs: {
            align: {default: null},
        },

        toDOM(node) {
            return [
                'p',
                alignmentAttrs(
                    {
                        class: 'dgn-paragraph',
                    },
                    node.attrs.align,
                ),
                0,
            ];
        },

        parseDOM: [
            {
                tag: 'p',
                getAttrs(dom) {
                    return {
                        align:
                            readAlignment(dom),
                    };
                },
            },
        ],
    },

    heading: {
        group: 'block',
        content: 'inline*',

        attrs: {
            level: {default: null},
            align: {default: null},
        },

        toDOM(node) {
            const level =
                Number.isInteger(
                    node.attrs.level,
                ) &&
                node.attrs.level >= 1 &&
                node.attrs.level <= 6
                    ? node.attrs.level
                    : 2;

            return [
                `h${level}`,
                alignmentAttrs(
                    {
                        class: 'dgn-heading',
                    },
                    node.attrs.align,
                ),
                0,
            ];
        },

        parseDOM: [
            1, 2, 3, 4, 5, 6,
        ].map((level) => ({
            tag: `h${level}`,
            getAttrs(dom) {
                return {
                    level,
                    align:
                        readAlignment(dom),
                };
            },
        })),
    },

    orderedList: {
        group: 'block',
        content: 'listItem+',

        attrs: {
            order: {default: null},
        },

        toDOM(node) {
            const attrs = {
                class:
                    'dgn-list dgn-list--ordered',
            };

            if (
                Number.isInteger(
                    node.attrs.order,
                ) &&
                node.attrs.order > 1
            ) {
                attrs.start =
                    String(node.attrs.order);
            }

            return [
                'ol',
                attrs,
                0,
            ];
        },

        parseDOM: [
            {
                tag: 'ol',

                getAttrs(dom) {
                    return {
                        order:
                            readInteger(
                                dom,
                                'start',
                                null,
                            ),
                    };
                },
            },
        ],
    },

    unorderedList: {
        group: 'block',
        content: 'listItem+',

        toDOM() {
            return [
                'ul',
                {
                    class:
                        'dgn-list dgn-list--unordered',
                },
                0,
            ];
        },

        parseDOM: [
            {
                tag: 'ul',
            },
        ],
    },

    listItem: {
        content: 'block+',

        toDOM() {
            return [
                'li',
                {
                    class:
                        'dgn-list-item',
                },
                0,
            ];
        },

        parseDOM: [
            {
                tag: 'li',
            },
        ],
    },

    table: {
        group: 'block',
        content: 'tableRow+',

        toDOM() {
            return [
                'table',
                {
                    class: 'dgn-table',
                },
                [
                    'tbody',
                    0,
                ],
            ];
        },

        parseDOM: [
            {
                tag: 'table',
                contentElement: 'tbody',
            },
        ],
    },

    tableRow: {
        content: 'tableCell+',

        toDOM() {
            return [
                'tr',
                0,
            ];
        },

        parseDOM: [
            {
                tag: 'tr',
            },
        ],
    },

    tableCell: {
        content: 'block+',

        attrs: {
            colspan: {default: null},
            rowspan: {default: null},
        },

        toDOM(node) {
            const attrs = {};

            optionalDataAttr(
                attrs,
                'colspan',
                node.attrs.colspan,
            );

            optionalDataAttr(
                attrs,
                'rowspan',
                node.attrs.rowspan,
            );

            return [
                'td',
                attrs,
                0,
            ];
        },

        parseDOM: [
            {
                tag: 'td',

                getAttrs(dom) {
                    return {
                        colspan:
                            readInteger(
                                dom,
                                'colspan',
                                null,
                            ),

                        rowspan:
                            readInteger(
                                dom,
                                'rowspan',
                                null,
                            ),
                    };
                },
            },

            {
                tag: 'th',

                getAttrs(dom) {
                    return {
                        colspan:
                            readInteger(
                                dom,
                                'colspan',
                                null,
                            ),

                        rowspan:
                            readInteger(
                                dom,
                                'rowspan',
                                null,
                            ),
                    };
                },
            },
        ],
    },

    image: {
        group: 'block',
        atom: true,
        selectable: true,

        attrs: {
            assetKey: {},
            alt: {default: null},
            title: {default: null},
            width: {default: null},
            align: {default: null},
        },

        toDOM(node) {
            const attrs =
                alignmentAttrs(
                    {
                        class: 'dgn-image',
                        'data-dgn-image':
                            'managed',
                        'data-asset-key':
                            node.attrs.assetKey,
                    },
                    node.attrs.align,
                );

            optionalDataAttr(
                attrs,
                'data-alt',
                node.attrs.alt,
            );

            optionalDataAttr(
                attrs,
                'data-title',
                node.attrs.title,
            );

            optionalDataAttr(
                attrs,
                'data-width',
                node.attrs.width,
            );

            return [
                'figure',
                attrs,
                [
                    'span',
                    {
                        class:
                            'dgn-image__placeholder',
                    },
                    node.attrs.alt ||
                        'Image',
                ],
            ];
        },

        parseDOM: [
            {
                tag:
                    'figure[data-dgn-image="managed"]',

                getAttrs(dom) {
                    return {
                        assetKey:
                            dom.getAttribute(
                                'data-asset-key',
                            ) || '',

                        alt:
                            dom.getAttribute(
                                'data-alt',
                            ),

                        title:
                            dom.getAttribute(
                                'data-title',
                            ),

                        width:
                            readInteger(
                                dom,
                                'data-width',
                                null,
                            ),

                        align:
                            readAlignment(dom),
                    };
                },
            },
        ],
    },

    horizontalRule: {
        group: 'block',
        atom: true,

        toDOM() {
            return [
                'hr',
                {
                    class:
                        'dgn-horizontal-rule',
                },
            ];
        },

        parseDOM: [
            {
                tag: 'hr',
            },
        ],
    },

    pageBreak: {
        group: 'block',
        atom: true,

        toDOM() {
            return [
                'div',
                {
                    class:
                        'dgn-page-break',
                    'data-dgn-page-break':
                        '1',
                },
            ];
        },

        parseDOM: [
            {
                tag:
                    'div[data-dgn-page-break="1"]',
            },
        ],
    },

    instruction: {
        group: 'block',
        content: 'inline*',

        attrs: {
            variant: {default: null},
            align: {default: null},
        },

        toDOM(node) {
            const attrs =
                alignmentAttrs(
                    {
                        class:
                            'dgn-instruction',
                        'data-dgn-instruction':
                            '1',
                    },
                    node.attrs.align,
                );

            optionalDataAttr(
                attrs,
                'data-variant',
                node.attrs.variant,
            );

            return [
                'div',
                attrs,
                0,
            ];
        },

        parseDOM: [
            {
                tag:
                    'div[data-dgn-instruction="1"]',

                getAttrs(dom) {
                    return {
                        variant:
                            dom.getAttribute(
                                'data-variant',
                            ),

                        align:
                            readAlignment(dom),
                    };
                },
            },
        ],
    },

    question: {
        group: 'block',
        content: 'inline*',

        attrs: {
            id: {},
            points: {default: null},
            align: {default: null},
        },

        toDOM(node) {
            const attrs =
                alignmentAttrs(
                    {
                        class:
                            'dgn-question',
                        'data-question-id':
                            node.attrs.id,
                    },
                    node.attrs.align,
                );

            optionalDataAttr(
                attrs,
                'data-points',
                node.attrs.points,
            );

            return [
                'div',
                attrs,
                0,
            ];
        },

        parseDOM: [
            {
                tag: 'div.dgn-question',

                getAttrs(dom) {
                    const points =
                        Number.parseFloat(
                            dom.getAttribute(
                                'data-points',
                            ) || '',
                        );

                    return {
                        id:
                            dom.getAttribute(
                                'data-question-id',
                            ) || '',

                        points:
                            Number.isFinite(
                                points,
                            )
                                ? points
                                : null,

                        align:
                            readAlignment(dom),
                    };
                },
            },
        ],
    },

    shortAnswer: {
        group: 'block',
        atom: true,
        selectable: true,

        attrs: {
            questionId: {},
            minHeight: {default: null},
            placeholder: {default: null},
        },

        toDOM(node) {
            const attrs = {
                class:
                    'dgn-answer dgn-answer--short',

                'data-digiera-answer':
                    'short',

                'data-question-id':
                    node.attrs.questionId,
            };

            optionalDataAttr(
                attrs,
                'data-min-height',
                node.attrs.minHeight,
            );

            optionalDataAttr(
                attrs,
                'data-placeholder',
                node.attrs.placeholder,
            );

            return [
                'div',
                attrs,
            ];
        },

        parseDOM: [
            {
                tag:
                    'div[data-digiera-answer="short"]',

                getAttrs(dom) {
                    return {
                        questionId:
                            dom.getAttribute(
                                'data-question-id',
                            ) || '',

                        minHeight:
                            readInteger(
                                dom,
                                'data-min-height',
                                null,
                            ),

                        placeholder:
                            dom.getAttribute(
                                'data-placeholder',
                            ),
                    };
                },
            },
        ],
    },

    longAnswer: {
        group: 'block',
        atom: true,
        selectable: true,

        attrs: {
            questionId: {},
            minHeight: {default: 160},
            placeholder: {default: null},
        },

        toDOM(node) {
            const attrs = {
                class:
                    'dgn-answer dgn-answer--long',

                'data-digiera-answer':
                    'long',

                'data-question-id':
                    node.attrs.questionId,

                'data-min-height':
                    String(
                        node.attrs.minHeight,
                    ),
            };

            optionalDataAttr(
                attrs,
                'data-placeholder',
                node.attrs.placeholder,
            );

            return [
                'div',
                attrs,
            ];
        },

        parseDOM: [
            {
                tag:
                    'div[data-digiera-answer="long"]',

                getAttrs(dom) {
                    return {
                        questionId:
                            dom.getAttribute(
                                'data-question-id',
                            ) || '',

                        minHeight:
                            readInteger(
                                dom,
                                'data-min-height',
                                160,
                            ),

                        placeholder:
                            dom.getAttribute(
                                'data-placeholder',
                            ),
                    };
                },
            },
        ],
    },

    checkbox: {
        group: 'block',
        atom: true,
        selectable: true,

        attrs: {
            questionId: {},
            optionId: {default: null},
            checked: {default: null},
            label: {default: null},
        },

        toDOM(node) {
            const attrs = {
                class: 'dgn-checkbox',
                'data-dgn-checkbox': '1',
                'data-question-id':
                    node.attrs.questionId,
            };

            optionalDataAttr(
                attrs,
                'data-option-id',
                node.attrs.optionId,
            );

            optionalDataAttr(
                attrs,
                'data-checked',
                node.attrs.checked,
            );

            optionalDataAttr(
                attrs,
                'data-label',
                node.attrs.label,
            );

            return [
                'div',
                attrs,
                node.attrs.label || '☐',
            ];
        },

        parseDOM: [
            {
                tag:
                    'div[data-dgn-checkbox="1"]',

                getAttrs(dom) {
                    return {
                        questionId:
                            dom.getAttribute(
                                'data-question-id',
                            ) || '',

                        optionId:
                            dom.getAttribute(
                                'data-option-id',
                            ),

                        checked:
                            readBoolean(
                                dom,
                                'data-checked',
                            ),

                        label:
                            dom.getAttribute(
                                'data-label',
                            ),
                    };
                },
            },
        ],
    },

    multipleChoice: {
        group: 'block',
        content: 'checkbox*',

        attrs: {
            questionId: {},
            selectionMode: {default: null},
        },

        toDOM(node) {
            const attrs = {
                class:
                    'dgn-multiple-choice',

                'data-dgn-multiple-choice':
                    '1',

                'data-question-id':
                    node.attrs.questionId,
            };

            optionalDataAttr(
                attrs,
                'data-selection-mode',
                node.attrs.selectionMode,
            );

            return [
                'div',
                attrs,
                0,
            ];
        },

        parseDOM: [
            {
                tag:
                    'div[data-dgn-multiple-choice="1"]',

                getAttrs(dom) {
                    return {
                        questionId:
                            dom.getAttribute(
                                'data-question-id',
                            ) || '',

                        selectionMode:
                            dom.getAttribute(
                                'data-selection-mode',
                            ),
                    };
                },
            },
        ],
    },

    answerTable: {
        group: 'block',
        atom: true,
        selectable: true,

        attrs: {
            questionId: {},
            rows: {default: null},
            cols: {default: null},
        },

        toDOM(node) {
            const attrs = {
                class:
                    'dgn-answer-table',

                'data-digiera-answer':
                    'table',

                'data-question-id':
                    node.attrs.questionId,
            };

            optionalDataAttr(
                attrs,
                'data-rows',
                node.attrs.rows,
            );

            optionalDataAttr(
                attrs,
                'data-cols',
                node.attrs.cols,
            );

            return [
                'div',
                attrs,
                'Bảng trả lời',
            ];
        },

        parseDOM: [
            {
                tag:
                    'div[data-digiera-answer="table"]',

                getAttrs(dom) {
                    return {
                        questionId:
                            dom.getAttribute(
                                'data-question-id',
                            ) || '',

                        rows:
                            readInteger(
                                dom,
                                'data-rows',
                                null,
                            ),

                        cols:
                            readInteger(
                                dom,
                                'data-cols',
                                null,
                            ),
                    };
                },
            },
        ],
    },

    teacherOnlyNote: {
        group: 'block',
        content: 'inline*',

        attrs: {
            label: {default: null},
        },

        toDOM(node) {
            const attrs = {
                class:
                    'dgn-teacher-note',
                'data-dgn-teacher-note':
                    '1',
            };

            optionalDataAttr(
                attrs,
                'data-label',
                node.attrs.label,
            );

            return [
                'aside',
                attrs,
                0,
            ];
        },

        parseDOM: [
            {
                tag:
                    'aside[data-dgn-teacher-note="1"]',

                getAttrs(dom) {
                    return {
                        label:
                            dom.getAttribute(
                                'data-label',
                            ),
                    };
                },
            },
        ],
    },

    rubricAnchor: {
        group: 'block',
        atom: true,

        attrs: {
            id: {},
        },

        toDOM(node) {
            return [
                'div',
                {
                    class:
                        'dgn-rubric-anchor',

                    'data-dgn-rubric-anchor':
                        node.attrs.id,
                },
            ];
        },

        parseDOM: [
            {
                tag:
                    'div[data-dgn-rubric-anchor]',

                getAttrs(dom) {
                    return {
                        id:
                            dom.getAttribute(
                                'data-dgn-rubric-anchor',
                            ) || '',
                    };
                },
            },
        ],
    },

    mathBlock: {
        group: 'block',
        atom: true,

        attrs: {
            source: {default: null},
        },

        toDOM(node) {
            return [
                'div',
                {
                    class:
                        'dgn-math-block',

                    'data-dgn-math-block':
                        '1',

                    'data-source':
                        node.attrs.source || '',
                },

                node.attrs.source || '',
            ];
        },

        parseDOM: [
            {
                tag:
                    'div[data-dgn-math-block="1"]',

                getAttrs(dom) {
                    return {
                        source:
                            dom.getAttribute(
                                'data-source',
                            ),
                    };
                },
            },
        ],
    },

    text: {
        group: 'inline',
    },

    hardBreak: {
        inline: true,
        group: 'inline',
        atom: true,

        toDOM() {
            return ['br'];
        },

        parseDOM: [
            {
                tag: 'br',
            },
        ],
    },

    mathInline: {
        inline: true,
        group: 'inline',
        atom: true,

        attrs: {
            source: {default: null},
        },

        toDOM(node) {
            return [
                'span',
                {
                    class:
                        'dgn-math-inline',

                    'data-dgn-math-inline':
                        '1',

                    'data-source':
                        node.attrs.source || '',
                },

                node.attrs.source || '',
            ];
        },

        parseDOM: [
            {
                tag:
                    'span[data-dgn-math-inline="1"]',

                getAttrs(dom) {
                    return {
                        source:
                            dom.getAttribute(
                                'data-source',
                            ),
                    };
                },
            },
        ],
    },
};

export const markSpecs = {
    bold: {
        toDOM() {
            return [
                'strong',
                0,
            ];
        },

        parseDOM: [
            {
                tag: 'strong',
            },
            {
                tag: 'b',
            },
        ],
    },

    italic: {
        toDOM() {
            return [
                'em',
                0,
            ];
        },

        parseDOM: [
            {
                tag: 'em',
            },
            {
                tag: 'i',
            },
        ],
    },

    underline: {
        toDOM() {
            return [
                'u',
                0,
            ];
        },

        parseDOM: [
            {
                tag: 'u',
            },
        ],
    },

    strike: {
        toDOM() {
            return [
                's',
                0,
            ];
        },

        parseDOM: [
            {
                tag: 's',
            },
            {
                tag: 'del',
            },
        ],
    },

    textColor: {
        attrs: {
            color: {},
        },

        toDOM(mark) {
            return [
                'span',
                {
                    'data-dgn-text-color':
                        mark.attrs.color,

                    style:
                        `color: ${mark.attrs.color};`,
                },
                0,
            ];
        },

        parseDOM: [
            {
                tag:
                    'span[data-dgn-text-color]',

                getAttrs(dom) {
                    return {
                        color:
                            dom.getAttribute(
                                'data-dgn-text-color',
                            ),
                    };
                },
            },
        ],
    },
};
