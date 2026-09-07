import {
    setBlockType,
    toggleMark,
} from 'prosemirror-commands';
import {
    redo as historyRedo,
    undo as historyUndo,
} from 'prosemirror-history';
import {wrapInList} from 'prosemirror-schema-list';

import {
    schema,
    validateNodeAttrs,
} from './schema.js';

function currentTopLevelInsertPosition(state) {
    const {$from} = state.selection;

    if ($from.depth === 0) {
        return state.selection.to;
    }

    return $from.after(1);
}

function insertBlockAfterSelection(node) {
    return (state, dispatch) => {
        const position =
            currentTopLevelInsertPosition(state);

        if (dispatch) {
            dispatch(
                state.tr.insert(
                    position,
                    node,
                ),
            );
        }

        return true;
    };
}

function insertQuestion({
    id,
    text = '',
    points = null,
    align = null,
}) {
    const attrs = {
        id,
        points,
        align,
    };

    validateNodeAttrs(
        'question',
        attrs,
    );

    const content = text === ''
        ? undefined
        : schema.text(text);

    const question =
        schema.nodes.question.create(
            attrs,
            content,
        );

    return insertBlockAfterSelection(
        question,
    );
}

function insertMathBlock({
    source = '',
} = {}) {
    const attrs = {
        source,
    };

    validateNodeAttrs(
        'mathBlock',
        attrs,
    );

    const math = schema.nodes.mathBlock.create(
        attrs,
    );

    return insertBlockAfterSelection(math);
}

function insertImage({
    assetKey,
    alt = null,
    title = null,
    width = null,
    align = null,
}) {
    const attrs = {
        assetKey,
        alt,
        title,
        width,
        align,
    };

    validateNodeAttrs(
        'image',
        attrs,
    );

    const image = schema.nodes.image.create(
        attrs,
    );

    return insertBlockAfterSelection(image);
}

function insertTable({
    rows = 2,
    cols = 2,
} = {}) {
    if (
        !Number.isInteger(rows) ||
        !Number.isInteger(cols) ||
        rows < 1 ||
        cols < 1 ||
        rows > 20 ||
        cols > 20
    ) {
        throw new TypeError(
            'Table dimensions must be integers from 1 to 20',
        );
    }

    const tableRows = [];

    for (
        let rowIndex = 0;
        rowIndex < rows;
        rowIndex += 1
    ) {
        const cells = [];

        for (
            let colIndex = 0;
            colIndex < cols;
            colIndex += 1
        ) {
            cells.push(
                schema.nodes.tableCell.create(
                    {},
                    schema.nodes.paragraph.create(),
                ),
            );
        }

        tableRows.push(
            schema.nodes.tableRow.create(
                {},
                cells,
            ),
        );
    }

    const table = schema.nodes.table.create(
        {},
        tableRows,
    );

    return insertBlockAfterSelection(table);
}

function insertAnswerTable({
    questionId,
    rows = 2,
    cols = 2,
}) {
    const attrs = {
        questionId,
        rows,
        cols,
    };

    validateNodeAttrs(
        'answerTable',
        attrs,
    );

    return insertBlockAfterSelection(
        schema.nodes.answerTable.create(
            attrs,
        ),
    );
}

function insertShortAnswer({
    questionId,
}) {
    const attrs = {
        questionId,
        minHeight: 48,
        placeholder: null,
    };

    validateNodeAttrs(
        'shortAnswer',
        attrs,
    );

    return insertBlockAfterSelection(
        schema.nodes.shortAnswer.create(
            attrs,
        ),
    );
}

function insertLongAnswer({questionId}) {
    return (state, dispatch) => {
        let insertPos = null;

        state.doc.descendants((node, pos) => {
            if (
                insertPos === null &&
                node.type === schema.nodes.question &&
                node.attrs.id === questionId
            ) {
                insertPos = pos + node.nodeSize;
                return false;
            }

            return true;
        });

        if (insertPos === null) {
            return false;
        }

        const node = schema.nodes.longAnswer.create({
            questionId,
            minHeight: 160,
        });

        if (dispatch) {
            dispatch(
                state.tr.insert(insertPos, node),
            );
        }

        return true;
    };
}

const toggleBold = toggleMark(
    schema.marks.bold,
);

const toggleItalic = toggleMark(
    schema.marks.italic,
);

const toggleUnderline = toggleMark(
    schema.marks.underline,
);

const undo = historyUndo;
const redo = historyRedo;

const wrapOrderedList = wrapInList(
    schema.nodes.orderedList,
);

const wrapUnorderedList = wrapInList(
    schema.nodes.unorderedList,
);

const ALIGNABLE_NODE_NAMES = new Set([
    'paragraph',
    'heading',
    'question',
    'instruction',
]);

function setAlignment({align}) {
    return (state, dispatch) => {
        let transaction = state.tr;
        let changed = false;

        const applyAlignment = (node, pos) => {
            if (
                !ALIGNABLE_NODE_NAMES.has(
                    node.type.name,
                ) ||
                node.attrs.align === align
            ) {
                return;
            }

            transaction = transaction.setNodeMarkup(
                pos,
                undefined,
                {
                    ...node.attrs,
                    align,
                },
                node.marks,
            );

            changed = true;
        };

        if (state.selection.empty) {
            const {$from} = state.selection;

            for (
                let depth = $from.depth;
                depth > 0;
                depth -= 1
            ) {
                const node = $from.node(depth);

                if (
                    ALIGNABLE_NODE_NAMES.has(
                        node.type.name,
                    )
                ) {
                    applyAlignment(
                        node,
                        $from.before(depth),
                    );
                    break;
                }
            }
        } else {
            state.doc.nodesBetween(
                state.selection.from,
                state.selection.to,
                (node, pos) => {
                    applyAlignment(node, pos);
                },
            );
        }

        if (!changed) {
            return false;
        }

        if (dispatch) {
            dispatch(transaction);
        }

        return true;
    };
}

function setHeading({level}) {
    return setBlockType(
        schema.nodes.heading,
        {
            level,
        },
    );
}

export const commands = {
    insertLongAnswer,
    insertShortAnswer,
    insertAnswerTable,
    insertTable,
    insertImage,
    insertMathBlock,
    insertQuestion,
    toggleBold,
    toggleItalic,
    toggleUnderline,
    undo,
    redo,
    setHeading,
    setAlignment,
    wrapOrderedList,
    wrapUnorderedList,
};
