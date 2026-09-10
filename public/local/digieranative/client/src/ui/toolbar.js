import React from 'react';

const h = React.createElement;

const DEFAULT_LAYOUT = Object.freeze({paper: 'A4', orientation: 'portrait', margin: 'normal'});
const TABS = [
    ['file', 'Tệp'],
    ['home', 'Home'],
    ['insert', 'Chèn'],
    ['layout', 'Bố cục'],
    ['digiera', 'DIGIERA'],
];

function nextQuestion(editor) {
    const used = new Set();
    editor.state.doc.descendants(node => {
        if (node.type.name === 'question' && node.attrs.id) {
            used.add(node.attrs.id);
        }
    });
    let number = 1;
    while (used.has(`q${number}`)) {
        number += 1;
    }
    return {id: `q${number}`, text: `Câu ${number}`};
}

function selectedQuestionId(editor) {
    const {$from} = editor.state.selection;
    for (let depth = $from.depth; depth > 0; depth -= 1) {
        const node = $from.node(depth);
        if (node.type.name === 'question') {
            return node.attrs.id || null;
        }
    }
    return null;
}

function nearestPrecedingQuestionId(editor) {
    const selected = selectedQuestionId(editor);
    if (selected) {
        return selected;
    }
    const cursor = editor.state.selection.from;
    let best = null;
    let bestPos = -1;
    editor.state.doc.descendants((node, pos) => {
        if (node.type.name === 'question' && node.attrs.id && pos <= cursor && pos >= bestPos) {
            best = node.attrs.id;
            bestPos = pos;
        }
    });
    return best;
}

function insertContent(editor, node) {
    return editor.chain().focus().insertContent(node).run();
}

function insertSmartAnswer(editor, type, attrs) {
    let questionId = nearestPrecedingQuestionId(editor);
    if (questionId) {
        return insertContent(editor, {type, attrs: {...attrs, questionId}});
    }
    const question = nextQuestion(editor);
    questionId = question.id;
    return editor.chain().focus().insertContent([
        {type: 'question', attrs: {id: questionId, points: null, align: null}, content: [{type: 'text', text: question.text}]},
        {type, attrs: {...attrs, questionId}},
    ]).run();
}

function emitHook(hostElement, name, detail) {
    const EventClass = hostElement.ownerDocument.defaultView.CustomEvent;
    hostElement.dispatchEvent(new EventClass(name, {bubbles: true, detail}));
}

function commandButton({id, label, title, active = false, disabled = false, onClick}) {
    return h('button', {
        key: id,
        type: 'button',
        className: `dgn-toolbar-button${active ? ' is-active' : ''}`,
        'data-dgn-command': id,
        'aria-label': title || label,
        'aria-pressed': active ? 'true' : 'false',
        disabled,
        onClick,
    }, label);
}

function group(label, children, key = label) {
    return h('div', {className: 'dgn-toolbar-group', key}, [
        h('div', {className: 'dgn-toolbar-group__controls', key: 'controls'}, children),
        h('div', {className: 'dgn-toolbar-group__label', key: 'label'}, label),
    ]);
}

function editLink(editor) {
    const current = editor.getAttributes('link')?.href || '';
    const promptFn = editor.view.dom.ownerDocument.defaultView.prompt;
    if (typeof promptFn !== 'function') {
        return false;
    }
    const value = promptFn('Liên kết', current || 'https://');
    if (value === null) {
        return false;
    }
    const href = value.trim();
    if (href === '') {
        return editor.chain().focus().extendMarkRange('link').unsetLink().run();
    }
    return editor.chain().focus().extendMarkRange('link').setLink({href}).run();
}

export function OfficialToolbar({
    editor,
    hostElement,
    readonly = false,
    saveNow = null,
    print = null,
    downloadPdf = null,
    uploadImage = null,
    requestMath = null,
    updateLayout = null,
    initialLayout = DEFAULT_LAYOUT,
    useTiptapEditorState,
}) {
    const [activeTab, setActiveTab] = React.useState('home');
    const [layout, setLayout] = React.useState({...DEFAULT_LAYOUT, ...(initialLayout || {})});
    const [zoom, setZoom] = React.useState(100);
    const state = useTiptapEditorState({
        editor,
        selector: ({editor: current}) => ({
            bold: current.isActive('bold'),
            italic: current.isActive('italic'),
            underline: current.isActive('underline'),
            strike: current.isActive('strike'),
            h1: current.isActive('heading', {level: 1}),
            h2: current.isActive('heading', {level: 2}),
            h3: current.isActive('heading', {level: 3}),
            bulletList: current.isActive('bulletList'),
            orderedList: current.isActive('orderedList'),
            alignLeft: current.isActive({textAlign: 'left'}),
            alignCenter: current.isActive({textAlign: 'center'}),
            alignRight: current.isActive({textAlign: 'right'}),
            alignJustify: current.isActive({textAlign: 'justify'}),
            link: current.isActive('link'),
        }),
    });

    const disabled = readonly;
    const blockValue = state.h1 ? 'h1' : state.h2 ? 'h2' : state.h3 ? 'h3' : 'paragraph';
    const applyLayout = patch => {
        const next = {...layout, ...patch, paper: 'A4'};
        setLayout(next);
        if (typeof updateLayout === 'function') {
            updateLayout(next);
        }
    };
    const applyZoom = value => {
        setZoom(value);
        const canvasHost = hostElement.querySelector('.dgn-canvas-host');
        if (canvasHost) {
            canvasHost.style.setProperty('--dgn-zoom', String(value / 100));
            canvasHost.dataset.zoom = String(value);
        }
    };
    const imageAction = id => commandButton({id, label: 'Ảnh', title: 'Chèn ảnh', disabled, onClick: () => {
        const insert = attrs => insertContent(editor, {type: 'image', attrs});
        if (typeof uploadImage === 'function') {
            uploadImage({editor, insert});
        } else {
            emitHook(hostElement, 'digiera-native:request-image', {editor, insert});
        }
    }});
    const mathAction = () => {
        const insert = attrs => insertContent(editor, {type: 'mathBlock', attrs});
        if (typeof requestMath === 'function') {
            requestMath({editor, insert});
        } else {
            emitHook(hostElement, 'digiera-native:request-math', {editor, insert});
        }
    };

    const filePanel = [
        group('Tệp', [
            commandButton({id: 'save', label: 'Lưu', title: 'Lưu ngay', disabled: disabled || typeof saveNow !== 'function', onClick: () => saveNow?.()}),
            commandButton({id: 'print', label: 'In', title: 'In phiếu', disabled: disabled || typeof print !== 'function', onClick: () => print?.()}),
            commandButton({id: 'download-pdf', label: 'Tải PDF', title: 'Tải PDF', disabled: disabled || typeof downloadPdf !== 'function', onClick: () => downloadPdf?.()}),
        ]),
    ];

    const homePanel = [
        group('Lịch sử', [
            commandButton({id: 'undo', label: '↶', title: 'Hoàn tác', disabled, onClick: () => editor.chain().focus().undo().run()}),
            commandButton({id: 'redo', label: '↷', title: 'Làm lại', disabled, onClick: () => editor.chain().focus().redo().run()}),
        ]),
        group('Định dạng', [
            commandButton({id: 'bold', label: 'B', title: 'Đậm', active: state.bold, disabled, onClick: () => editor.chain().focus().toggleBold().run()}),
            commandButton({id: 'italic', label: 'I', title: 'Nghiêng', active: state.italic, disabled, onClick: () => editor.chain().focus().toggleItalic().run()}),
            commandButton({id: 'underline', label: 'U', title: 'Gạch chân', active: state.underline, disabled, onClick: () => editor.chain().focus().toggleUnderline().run()}),
            commandButton({id: 'strike', label: 'S', title: 'Gạch ngang', active: state.strike, disabled, onClick: () => editor.chain().focus().toggleStrike().run()}),
        ]),
        group('Đoạn', [
            h('select', {
                key: 'block-type', className: 'dgn-toolbar-select', 'data-dgn-command': 'block-type',
                'aria-label': 'Kiểu đoạn', value: blockValue, disabled,
                onChange: event => event.target.value === 'paragraph'
                    ? editor.chain().focus().setParagraph().run()
                    : editor.chain().focus().setHeading({level: Number(event.target.value.slice(1))}).run(),
            }, [
                h('option', {key: 'p', value: 'paragraph'}, 'Đoạn văn'),
                h('option', {key: 'h1', value: 'h1'}, 'Tiêu đề 1'),
                h('option', {key: 'h2', value: 'h2'}, 'Tiêu đề 2'),
                h('option', {key: 'h3', value: 'h3'}, 'Tiêu đề 3'),
            ]),
            commandButton({id: 'bullet-list', label: '• Danh sách', active: state.bulletList, disabled, onClick: () => editor.chain().focus().toggleBulletList().run()}),
            commandButton({id: 'ordered-list', label: '1. Danh sách', active: state.orderedList, disabled, onClick: () => editor.chain().focus().toggleOrderedList().run()}),
        ]),
        group('Căn chỉnh', [
            commandButton({id: 'align-left', label: '≡', title: 'Căn trái', active: state.alignLeft, disabled, onClick: () => editor.chain().focus().setTextAlign('left').run()}),
            commandButton({id: 'align-center', label: '≣', title: 'Căn giữa', active: state.alignCenter, disabled, onClick: () => editor.chain().focus().setTextAlign('center').run()}),
            commandButton({id: 'align-right', label: '≡', title: 'Căn phải', active: state.alignRight, disabled, onClick: () => editor.chain().focus().setTextAlign('right').run()}),
            commandButton({id: 'align-justify', label: '☰', title: 'Căn đều', active: state.alignJustify, disabled, onClick: () => editor.chain().focus().setTextAlign('justify').run()}),
        ]),
        group('Liên kết', [
            commandButton({id: 'link', label: '🔗', title: 'Thêm/sửa liên kết', active: state.link, disabled, onClick: () => editLink(editor)}),
            commandButton({id: 'unlink', label: '⌫🔗', title: 'Xóa liên kết', disabled: disabled || !state.link, onClick: () => editor.chain().focus().extendMarkRange('link').unsetLink().run()}),
        ]),
    ];

    const insertPanel = [
        group('Chèn', [
            imageAction('image'),
            commandButton({id: 'horizontal-rule', label: 'Đường kẻ', disabled, onClick: () => editor.chain().focus().setHorizontalRule().run()}),
            commandButton({id: 'page-break', label: 'Ngắt trang', disabled, onClick: () => insertContent(editor, {type: 'pageBreak'})}),
            commandButton({id: 'insert-link', label: 'Liên kết', disabled, onClick: () => editLink(editor)}),
        ]),
    ];

    const layoutPanel = [
        group('Khổ giấy', [commandButton({id: 'paper-a4', label: 'A4', active: true, disabled: true})]),
        group('Hướng', [
            commandButton({id: 'orientation-portrait', label: 'Dọc', active: layout.orientation === 'portrait', disabled, onClick: () => applyLayout({orientation: 'portrait'})}),
            commandButton({id: 'orientation-landscape', label: 'Ngang', active: layout.orientation === 'landscape', disabled, onClick: () => applyLayout({orientation: 'landscape'})}),
        ]),
        group('Lề', [
            commandButton({id: 'margin-normal', label: 'Thường', active: layout.margin === 'normal', disabled, onClick: () => applyLayout({margin: 'normal'})}),
            commandButton({id: 'margin-narrow', label: 'Hẹp', active: layout.margin === 'narrow', disabled, onClick: () => applyLayout({margin: 'narrow'})}),
            commandButton({id: 'margin-wide', label: 'Rộng', active: layout.margin === 'wide', disabled, onClick: () => applyLayout({margin: 'wide'})}),
        ]),
        group('Thu phóng', [75, 100, 125, 150].map(value => commandButton({
            id: `zoom-${value}`, label: `${value}%`, active: zoom === value, disabled: false, onClick: () => applyZoom(value),
        }))),
    ];

    const digieraPanel = [
        group('DIGIERA', [
            commandButton({id: 'question', label: 'Câu hỏi', disabled, onClick: () => {
                const q = nextQuestion(editor);
                insertContent(editor, {type: 'question', attrs: {id: q.id, points: null, align: null}, content: [{type: 'text', text: q.text}]});
            }}),
            commandButton({id: 'short-answer', label: 'Trả lời ngắn', disabled, onClick: () => insertSmartAnswer(editor, 'shortAnswer', {minHeight: 48, placeholder: null})}),
            commandButton({id: 'long-answer', label: 'Trả lời dài', disabled, onClick: () => insertSmartAnswer(editor, 'longAnswer', {minHeight: 160, placeholder: null})}),
            commandButton({id: 'answer-table', label: 'Ô trả lời', disabled, onClick: () => insertSmartAnswer(editor, 'answerTable', {rows: 2, cols: 2})}),
            imageAction('digiera-image'),
            commandButton({id: 'math', label: '∑ Công thức', disabled, onClick: mathAction}),
        ]),
    ];

    const panels = {file: filePanel, home: homePanel, insert: insertPanel, layout: layoutPanel, digiera: digieraPanel};

    return h('div', {
        className: 'dgn-official-toolbar', role: 'toolbar', 'aria-label': 'Thanh công cụ Tiptap',
        'data-dgn-official-toolbar': '1',
    }, [
        h('div', {className: 'dgn-toolbar-tabs', role: 'tablist', key: 'tabs'}, TABS.map(([id, label]) => h('button', {
            key: id, type: 'button', role: 'tab', className: `dgn-toolbar-tab${activeTab === id ? ' is-active' : ''}`,
            'data-dgn-tab': id, 'aria-selected': activeTab === id ? 'true' : 'false', onClick: () => setActiveTab(id),
        }, label))),
        h('div', {className: 'dgn-toolbar-panels', key: 'panels'}, TABS.map(([id]) => h('div', {
            key: id, className: 'dgn-toolbar-panel', 'data-dgn-panel': id, hidden: activeTab !== id,
        }, panels[id]))),
    ]);
}
