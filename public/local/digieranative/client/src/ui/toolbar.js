import React from 'react';

import {toNativeDocument} from '../document_adapter.js';

const h = React.createElement;

function nextQuestion(editor) {
    const used = new Set();
    editor.state.doc.descendants((node) => {
        if (node.type.name === 'question') {
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
            return node.attrs.id;
        }
    }
    return null;
}

function insertContent(editor, node) {
    return editor.chain().focus().insertContent(node).run();
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
    save = null,
    documentJson = null,
    useTiptapEditorState,
}) {
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

    const history = [
        commandButton({id: 'undo', label: '↶', title: 'Hoàn tác', disabled, onClick: () => editor.chain().focus().undo().run()}),
        commandButton({id: 'redo', label: '↷', title: 'Làm lại', disabled, onClick: () => editor.chain().focus().redo().run()}),
    ];

    const marks = [
        commandButton({id: 'bold', label: 'B', title: 'Đậm', active: state.bold, disabled, onClick: () => editor.chain().focus().toggleBold().run()}),
        commandButton({id: 'italic', label: 'I', title: 'Nghiêng', active: state.italic, disabled, onClick: () => editor.chain().focus().toggleItalic().run()}),
        commandButton({id: 'underline', label: 'U', title: 'Gạch chân', active: state.underline, disabled, onClick: () => editor.chain().focus().toggleUnderline().run()}),
        commandButton({id: 'strike', label: 'S', title: 'Gạch ngang', active: state.strike, disabled, onClick: () => editor.chain().focus().toggleStrike().run()}),
    ];

    const blocks = [
        h('select', {
            key: 'block-type',
            className: 'dgn-toolbar-select',
            'data-dgn-command': 'block-type',
            'aria-label': 'Kiểu đoạn',
            value: blockValue,
            disabled,
            onChange: (event) => {
                const value = event.target.value;
                if (value === 'paragraph') {
                    editor.chain().focus().setParagraph().run();
                } else {
                    editor.chain().focus().setHeading({level: Number(value.slice(1))}).run();
                }
            },
        }, [
            h('option', {key: 'p', value: 'paragraph'}, 'Đoạn văn'),
            h('option', {key: 'h1', value: 'h1'}, 'Tiêu đề 1'),
            h('option', {key: 'h2', value: 'h2'}, 'Tiêu đề 2'),
            h('option', {key: 'h3', value: 'h3'}, 'Tiêu đề 3'),
        ]),
        commandButton({id: 'bullet-list', label: '• Danh sách', title: 'Danh sách dấu đầu dòng', active: state.bulletList, disabled, onClick: () => editor.chain().focus().toggleBulletList().run()}),
        commandButton({id: 'ordered-list', label: '1. Danh sách', title: 'Danh sách đánh số', active: state.orderedList, disabled, onClick: () => editor.chain().focus().toggleOrderedList().run()}),
    ];

    const alignment = [
        commandButton({id: 'align-left', label: '≡', title: 'Căn trái', active: state.alignLeft, disabled, onClick: () => editor.chain().focus().setTextAlign('left').run()}),
        commandButton({id: 'align-center', label: '≣', title: 'Căn giữa', active: state.alignCenter, disabled, onClick: () => editor.chain().focus().setTextAlign('center').run()}),
        commandButton({id: 'align-right', label: '≡', title: 'Căn phải', active: state.alignRight, disabled, onClick: () => editor.chain().focus().setTextAlign('right').run()}),
        commandButton({id: 'align-justify', label: '☰', title: 'Căn đều', active: state.alignJustify, disabled, onClick: () => editor.chain().focus().setTextAlign('justify').run()}),
    ];

    const link = [
        commandButton({id: 'link', label: '🔗', title: 'Thêm hoặc sửa liên kết', active: state.link, disabled, onClick: () => editLink(editor)}),
        commandButton({id: 'unlink', label: '⌫🔗', title: 'Xóa liên kết', disabled: disabled || !state.link, onClick: () => editor.chain().focus().extendMarkRange('link').unsetLink().run()}),
    ];

    const worksheet = [
        commandButton({id: 'question', label: 'Câu hỏi', disabled, onClick: () => {
            const q = nextQuestion(editor);
            insertContent(editor, {type: 'question', attrs: {id: q.id, points: null, align: null}, content: [{type: 'text', text: q.text}]});
        }}),
        commandButton({id: 'short-answer', label: 'Trả lời ngắn', disabled, onClick: () => {
            const questionId = selectedQuestionId(editor);
            if (questionId) {
                insertContent(editor, {type: 'shortAnswer', attrs: {questionId, minHeight: 48, placeholder: null}});
            }
        }}),
        commandButton({id: 'long-answer', label: 'Trả lời dài', disabled, onClick: () => {
            const questionId = selectedQuestionId(editor);
            if (questionId) {
                insertContent(editor, {type: 'longAnswer', attrs: {questionId, minHeight: 160}});
            }
        }}),
        commandButton({id: 'answer-table', label: 'Ô trả lời', disabled, onClick: () => {
            const questionId = selectedQuestionId(editor);
            if (questionId) {
                insertContent(editor, {type: 'answerTable', attrs: {questionId, rows: 2, cols: 2}});
            }
        }}),
        commandButton({id: 'image', label: 'Ảnh', disabled, onClick: () => emitHook(hostElement, 'digiera-native:request-image', {
            editor,
            insert: (attrs) => insertContent(editor, {type: 'image', attrs}),
        })}),
        commandButton({id: 'math', label: '∑ Công thức', disabled, onClick: () => emitHook(hostElement, 'digiera-native:request-math', {
            editor,
            insert: (attrs) => insertContent(editor, {type: 'mathBlock', attrs}),
        })}),
    ];

    const file = [
        commandButton({id: 'save', label: 'Lưu', title: 'Lưu ngay', disabled: disabled || typeof save !== 'function', onClick: () => {
            if (typeof save === 'function') {
                save(toNativeDocument(editor.getJSON(), {
                    version: documentJson?.version || 1,
                    meta: documentJson?.meta,
                }));
            }
        }}),
    ];

    return h('div', {
        className: 'dgn-official-toolbar',
        role: 'toolbar',
        'aria-label': 'Thanh công cụ Tiptap',
        'data-dgn-official-toolbar': '1',
    }, [
        group('Tệp', file),
        group('Lịch sử', history),
        group('Định dạng', marks),
        group('Đoạn', blocks),
        group('Căn chỉnh', alignment),
        group('Liên kết', link),
        group('DIGIERA', worksheet),
    ]);
}
