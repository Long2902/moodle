import {commands} from './commands.js';
import {toNativeDocument} from './document_adapter.js';

export const RIBBON_TABS = [
    'File',
    'Home',
    'Insert',
    'Layout',
    'Review',
];

function runCommand(
    view,
    command,
) {
    if (
        !view ||
        typeof command !== 'function'
    ) {
        return false;
    }

    const applied = command(
        view.state,
        (transaction) => {
            view.dispatch(transaction);
        },
    );

    if (
        applied &&
        typeof view.focus === 'function'
    ) {
        view.focus();
    }

    return applied;
}

function selectedQuestionId(view) {
    if (!view) {
        return null;
    }

    const {$from} =
        view.state.selection;

    for (
        let depth = $from.depth;
        depth > 0;
        depth -= 1
    ) {
        const node =
            $from.node(depth);

        if (
            node.type.name ===
            'question'
        ) {
            return node.attrs.id;
        }
    }

    return null;
}

function nextQuestion(view) {
    const used = new Set();

    view.state.doc.descendants(
        (node) => {
            if (
                node.type.name ===
                'question'
            ) {
                used.add(
                    node.attrs.id,
                );
            }
        },
    );

    let number = 1;

    while (
        used.has(`q${number}`)
    ) {
        number += 1;
    }

    return {
        id: `q${number}`,
        text: `Câu ${number}`,
    };
}

function emitHook(
    element,
    name,
    detail,
) {
    const EventClass =
        element.ownerDocument
            .defaultView
            .CustomEvent;

    element.dispatchEvent(
        new EventClass(
            name,
            {
                bubbles: true,
                detail,
            },
        ),
    );
}

function controlDefinitions() {
    return {
        File: [
            {
                id: 'save',
                label: 'Save',
                group: 'File',
            },
        ],

        Home: [
            {
                id: 'undo',
                label: 'Undo',
                group: 'History',
                command:
                    commands.undo,
            },

            {
                id: 'redo',
                label: 'Redo',
                group: 'History',
                command:
                    commands.redo,
            },

            {
                id: 'bold',
                label: 'B',
                group: 'Font',
                command:
                    commands.toggleBold,
            },

            {
                id: 'italic',
                label: 'I',
                group: 'Font',
                command:
                    commands.toggleItalic,
            },

            {
                id: 'underline',
                label: 'U',
                group: 'Font',
                command:
                    commands.toggleUnderline,
            },

            {
                id: 'heading1',
                label: 'H1',
                group: 'Paragraph',

                command:
                    commands.setHeading({
                        level: 1,
                    }),
            },

            {
                id: 'heading2',
                label: 'H2',
                group: 'Paragraph',

                command:
                    commands.setHeading({
                        level: 2,
                    }),
            },

            {
                id: 'align-left',
                label: 'Left',
                group: 'Paragraph',

                command:
                    commands.setAlignment({
                        align: 'left',
                    }),
            },

            {
                id: 'align-center',
                label: 'Center',
                group: 'Paragraph',

                command:
                    commands.setAlignment({
                        align: 'center',
                    }),
            },

            {
                id: 'align-right',
                label: 'Right',
                group: 'Paragraph',

                command:
                    commands.setAlignment({
                        align: 'right',
                    }),
            },

            {
                id: 'ordered-list',
                label: '1.',
                group: 'Paragraph',

                command:
                    commands.wrapOrderedList,
            },

            {
                id: 'unordered-list',
                label: '•',
                group: 'Paragraph',

                command:
                    commands.wrapUnorderedList,
            },
        ],

        Insert: [
            {
                id: 'table',
                label: 'Table',
                group: 'Table',

                command:
                    commands.insertTable({
                        rows: 2,
                        cols: 2,
                    }),
            },

            {
                id: 'image',
                label: 'Image',
                group: 'Media',
            },

            {
                id: 'math',
                label: 'Math',
                group: 'Media',
            },

            {
                id: 'question',
                label: 'Question',
                group: 'Worksheet',
            },

            {
                id: 'short-answer',
                label: 'Short answer',
                group: 'Worksheet',
            },

            {
                id: 'long-answer',
                label: 'Long answer',
                group: 'Worksheet',
            },

            {
                id: 'answer-table',
                label: 'Answer table',
                group: 'Worksheet',
            },
        ],

        Layout: [
            {
                id: 'layout-info',
                label: 'Page',
                group: 'Layout',
            },
        ],

        Review: [
            {
                id: 'review-info',
                label: 'Review',
                group: 'Review',
            },
        ],
    };
}

function specialAction(
    control,
    element,
    getView,
) {
    const view = getView();

    if (!view) {
        return false;
    }

    if (control.id === 'image') {
        emitHook(
            element,
            'digiera-native:request-image',
            {
                view,

                insert(attrs) {
                    return runCommand(
                        view,
                        commands.insertImage(
                            attrs,
                        ),
                    );
                },
            },
        );

        return true;
    }

    if (control.id === 'math') {
        emitHook(
            element,
            'digiera-native:request-math',
            {
                view,

                insert(attrs) {
                    return runCommand(
                        view,
                        commands.insertMathBlock(
                            attrs,
                        ),
                    );
                },
            },
        );

        return true;
    }

    if (
        control.id === 'question'
    ) {
        return runCommand(
            view,
            commands.insertQuestion(
                nextQuestion(view),
            ),
        );
    }

    const questionId =
        selectedQuestionId(view);

    if (!questionId) {
        return false;
    }

    if (
        control.id ===
        'short-answer'
    ) {
        return runCommand(
            view,
            commands.insertShortAnswer({
                questionId,
            }),
        );
    }

    if (
        control.id ===
        'long-answer'
    ) {
        return runCommand(
            view,
            commands.insertLongAnswer({
                questionId,
            }),
        );
    }

    if (
        control.id ===
        'answer-table'
    ) {
        return runCommand(
            view,
            commands.insertAnswerTable({
                questionId,
                rows: 2,
                cols: 2,
            }),
        );
    }

    return false;
}

export function createRibbon({
    element,
    getView,
    readonly = false,
    save = null,
    documentJson = null,
}) {
    if (
        !element ||
        !element.ownerDocument
    ) {
        return null;
    }

    const document =
        element.ownerDocument;

    const existing =
        element.querySelector(
            ':scope > .dgn-ribbon',
        );

    if (existing) {
        existing.remove();
    }

    element.classList.add(
        'dgn-editor',
    );

    const ribbon =
        document.createElement('div');

    ribbon.className =
        'dgn-ribbon';

    ribbon.setAttribute(
        'data-dgn-ribbon',
        '1',
    );

    const tabs =
        document.createElement('div');

    tabs.className =
        'dgn-ribbon-tabs';

    tabs.setAttribute(
        'role',
        'tablist',
    );

    const panels =
        document.createElement('div');

    panels.className =
        'dgn-ribbon-panels';

    const definitions =
        controlDefinitions();

    const panelMap =
        new Map();

    function activate(
        tabName,
    ) {
        for (
            const [
                name,
                panel,
            ]
            of panelMap
        ) {
            panel.hidden =
                name !== tabName;
        }

        for (
            const button
            of tabs.querySelectorAll(
                '.dgn-ribbon-tab',
            )
        ) {
            const active =
                button.dataset.tab ===
                tabName;

            button.setAttribute(
                'aria-selected',
                active
                    ? 'true'
                    : 'false',
            );
        }
    }

    for (
        const tabName
        of RIBBON_TABS
    ) {
        const tab =
            document.createElement(
                'button',
            );

        tab.type = 'button';

        tab.className =
            'dgn-ribbon-tab';

        tab.textContent =
            tabName;

        tab.dataset.tab =
            tabName;

        tab.setAttribute(
            'role',
            'tab',
        );

        tabs.append(tab);

        const panel =
            document.createElement(
                'div',
            );

        panel.className =
            'dgn-ribbon-panel';

        panel.dataset.tabPanel =
            tabName;

        const groups =
            new Map();

        for (
            const control
            of definitions[tabName]
        ) {
            if (
                !groups.has(
                    control.group,
                )
            ) {
                const group =
                    document.createElement(
                        'div',
                    );

                group.className =
                    'dgn-ribbon-group';

                const controls =
                    document.createElement(
                        'div',
                    );

                controls.className =
                    'dgn-ribbon-group__controls';

                const label =
                    document.createElement(
                        'div',
                    );

                label.className =
                    'dgn-ribbon-group__label';

                label.textContent =
                    control.group;

                group.append(
                    controls,
                    label,
                );

                panel.append(group);

                groups.set(
                    control.group,
                    controls,
                );
            }

            const button =
                document.createElement(
                    'button',
                );

            button.type = 'button';

            button.className =
                'dgn-ribbon-control';

            button.textContent =
                control.label;

            button.dataset.dgnCommand =
                control.id;

            button.setAttribute(
                'aria-label',
                control.label,
            );

            if (readonly) {
                button.disabled = true;
            }

            if (
                control.id === 'save' &&
                typeof save === 'function'
            ) {
                button.addEventListener(
                    'click',
                    () => {
                        const view =
                            getView();

                        if (!view) {
                            return;
                        }

                        const source =
                            documentJson &&
                            documentJson.type ===
                                'worksheet'
                                ? documentJson
                                : {};

                        const canonical =
                            toNativeDocument(
                                view.state.doc.toJSON(),
                                {
                                    version:
                                        source.version ||
                                        1,

                                    meta:
                                        source.meta,
                                },
                            );

                        save(canonical);
                    },
                );
            } else if (
                typeof
                    control.command ===
                'function'
            ) {
                button.addEventListener(
                    'click',
                    () => {
                        runCommand(
                            getView(),
                            control.command,
                        );
                    },
                );
            } else if (
                control.id !== 'save'
            ) {
                button.addEventListener(
                    'click',
                    () => {
                        specialAction(
                            control,
                            element,
                            getView,
                        );
                    },
                );
            }

            groups
                .get(control.group)
                .append(button);
        }

        panelMap.set(
            tabName,
            panel,
        );

        panels.append(panel);

        tab.addEventListener(
            'click',
            () => {
                activate(tabName);
            },
        );
    }

    ribbon.append(
        tabs,
        panels,
    );

    element.prepend(ribbon);

    activate('Home');

    return ribbon;
}
