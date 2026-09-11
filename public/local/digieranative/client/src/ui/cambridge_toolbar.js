import React from 'react';
import {
    AlignCenter,
    AlignJustify,
    AlignLeft,
    AlignRight,
    Bold,
    Download,
    Eraser,
    FileDown,
    Highlighter,
    ImagePlus,
    IndentDecrease,
    IndentIncrease,
    Italic,
    Link2,
    List,
    ListOrdered,
    Printer,
    Redo2,
    Save,
    Sigma,
    Strikethrough,
    Table2,
    Underline,
    Undo2,
    Unlink,
} from 'lucide-react';
import {PictureEditorDialog, PICTURE_DEFAULTS} from './picture_editor.js';
import {NATIVE_FONT_FAMILIES, NATIVE_FONT_SIZES} from '../native_schema_extensions.js';

const h = React.createElement;
const DEFAULT_LAYOUT = Object.freeze({paper: 'A4', orientation: 'portrait', margin: 'normal'});

function button({id, title, active = false, disabled = false, onClick, icon = null, label = ''}) {
    return h('button', {
        key: id,
        type: 'button',
        className: `dgn-cambridge-button${active ? ' is-active' : ''}`,
        title,
        'aria-label': title,
        'aria-pressed': active ? 'true' : 'false',
        'data-dgn-command': id,
        disabled,
        onMouseDown: event => event.preventDefault(),
        onClick,
    }, [
        icon ? h(icon, {key: 'icon', size: 16, strokeWidth: 1.8}) : null,
        label ? h('span', {key: 'label', className: 'dgn-cambridge-button__label'}, label) : null,
    ]);
}

function separator(key) {
    return h('span', {key, className: 'dgn-cambridge-separator', 'aria-hidden': 'true'});
}

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

function currentTopLevelInsertPosition(editor) {
    const {$from} = editor.state.selection;
    return $from.depth === 0 ? editor.state.selection.to : $from.after(1);
}

function insertBlockAfterSelection(editor, node) {
    return editor.chain().focus().insertContentAt(currentTopLevelInsertPosition(editor), node, {updateSelection: true}).run();
}

function insertSmartAnswer(editor, type, attrs) {
    let questionId = nearestPrecedingQuestionId(editor);
    if (questionId) {
        return insertBlockAfterSelection(editor, {type, attrs: {...attrs, questionId}});
    }
    const question = nextQuestion(editor);
    questionId = question.id;
    return editor.chain().focus().insertContentAt(currentTopLevelInsertPosition(editor), [
        {type: 'question', attrs: {id: questionId, points: null, align: null}, content: [{type: 'text', text: question.text}]},
        {type, attrs: {...attrs, questionId}},
    ], {updateSelection: true}).run();
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

function stateSelector({editor}) {
    return {
        bold: editor.isActive('bold'),
        italic: editor.isActive('italic'),
        underline: editor.isActive('underline'),
        strike: editor.isActive('strike'),
        bulletList: editor.isActive('bulletList'),
        orderedList: editor.isActive('orderedList'),
        alignLeft: editor.isActive({textAlign: 'left'}),
        alignCenter: editor.isActive({textAlign: 'center'}),
        alignRight: editor.isActive({textAlign: 'right'}),
        alignJustify: editor.isActive({textAlign: 'justify'}),
        link: editor.isActive('link'),
        h1: editor.isActive('heading', {level: 1}),
        h2: editor.isActive('heading', {level: 2}),
        h3: editor.isActive('heading', {level: 3}),
        textStyle: editor.getAttributes('textStyle') || {},
        highlight: editor.getAttributes('highlight') || {},
    };
}

export function CambridgeToolbar({
    editor,
    hostElement,
    readonly = false,
    saveNow = null,
    print = null,
    downloadPdf = null,
    imageAdapter = null,
    requestMath = null,
    updateLayout = null,
    initialLayout = DEFAULT_LAYOUT,
    useTiptapEditorState,
}) {
    const state = useTiptapEditorState({editor, selector: stateSelector});
    const disabled = readonly;
    const [layout, setLayout] = React.useState({...DEFAULT_LAYOUT, ...(initialLayout || {})});
    const layoutRef = React.useRef(layout);
    const [zoom, setZoom] = React.useState(100);
    const imageInputRef = React.useRef(null);
    const capturedPosRef = React.useRef(null);
    const [picture, setPicture] = React.useState(null);
    const [pictureBusy, setPictureBusy] = React.useState(false);
    const [pictureError, setPictureError] = React.useState('');

    const applyLayout = patch => {
        const next = {...layoutRef.current, ...patch, paper: 'A4'};
        layoutRef.current = next;
        setLayout(next);
        updateLayout?.(next);
    };

    const applyZoom = value => {
        setZoom(value);
        const canvasHost = hostElement.querySelector('.dgn-canvas-host');
        if (canvasHost) {
            canvasHost.style.setProperty('--dgn-zoom', String(value / 100));
            canvasHost.dataset.zoom = String(value);
        }
    };

    const choosePicture = () => {
        if (!imageAdapter) {
            setPictureError('Kho ảnh chưa sẵn sàng.');
            return;
        }
        capturedPosRef.current = editor.state.selection.from;
        setPictureError('');
        imageInputRef.current?.click();
    };

    const submitPicture = async edits => {
        if (!picture) {
            return;
        }
        setPictureBusy(true);
        setPictureError('');
        try {
            await editor.insertPictureAsset({
                capturedPos: picture.capturedPos,
                file: picture.file,
                edits,
            });
            setPicture(null);
        } catch (error) {
            setPictureError(error instanceof Error ? error.message : String(error));
        } finally {
            setPictureBusy(false);
        }
    };

    const currentBlock = state.h1 ? '1' : state.h2 ? '2' : state.h3 ? '3' : 'paragraph';
    const currentFont = state.textStyle.fontFamily || '';
    const currentSize = String(state.textStyle.fontSize || '').replace(/px$/, '');

    const controls = [
        button({id: 'save', title: 'Lưu ngay', icon: Save, disabled: disabled || typeof saveNow !== 'function', onClick: () => saveNow?.()}),
        button({id: 'print', title: 'In', icon: Printer, disabled: disabled || typeof print !== 'function', onClick: () => print?.()}),
        button({id: 'download-pdf', title: 'Tải PDF', icon: FileDown, disabled: disabled || typeof downloadPdf !== 'function', onClick: () => downloadPdf?.()}),
        separator('s1'),
        button({id: 'undo', title: 'Hoàn tác', icon: Undo2, disabled: disabled || !editor.can().undo(), onClick: () => editor.chain().focus().undo().run()}),
        button({id: 'redo', title: 'Làm lại', icon: Redo2, disabled: disabled || !editor.can().redo(), onClick: () => editor.chain().focus().redo().run()}),
        button({id: 'clear-formatting', title: 'Xóa định dạng', icon: Eraser, disabled, onClick: () => editor.chain().focus().unsetAllMarks().unsetTextAlign().run()}),
        separator('s2'),
        h('select', {
            key: 'font-family',
            className: 'dgn-cambridge-select dgn-cambridge-select--font',
            'data-dgn-command': 'font-family',
            'aria-label': 'Phông chữ',
            value: currentFont,
            disabled,
            onChange: event => event.target.value
                ? editor.chain().focus().setFontFamily(event.target.value).run()
                : editor.chain().focus().unsetFontFamily().run(),
        }, [h('option', {key: 'default', value: ''}, 'Default'), ...NATIVE_FONT_FAMILIES.map(family => h('option', {key: family, value: family}, family))]),
        h('select', {
            key: 'font-size',
            className: 'dgn-cambridge-select dgn-cambridge-select--size',
            'data-dgn-command': 'font-size',
            'aria-label': 'Cỡ chữ',
            value: currentSize,
            disabled,
            onChange: event => event.target.value
                ? editor.chain().focus().setFontSize(`${event.target.value}px`).run()
                : editor.chain().focus().unsetFontSize().run(),
        }, [h('option', {key: 'default', value: ''}, 'Size'), ...NATIVE_FONT_SIZES.map(size => h('option', {key: size, value: String(size)}, String(size)))]),
        button({id: 'bold', title: 'Đậm', icon: Bold, active: state.bold, disabled, onClick: () => editor.chain().focus().toggleBold().run()}),
        button({id: 'italic', title: 'Nghiêng', icon: Italic, active: state.italic, disabled, onClick: () => editor.chain().focus().toggleItalic().run()}),
        button({id: 'underline', title: 'Gạch chân', icon: Underline, active: state.underline, disabled, onClick: () => editor.chain().focus().toggleUnderline().run()}),
        button({id: 'strike', title: 'Gạch ngang', icon: Strikethrough, active: state.strike, disabled, onClick: () => editor.chain().focus().toggleStrike().run()}),
        h('label', {key: 'text-color', className: 'dgn-cambridge-color', title: 'Màu chữ'}, [
            h('span', {key: 'label'}, 'A'),
            h('input', {
                key: 'input', type: 'color', 'data-dgn-command': 'text-color',
                value: state.textStyle.color || '#1f2937', disabled,
                onChange: event => editor.chain().focus().setColor(event.target.value.toUpperCase()).run(),
            }),
        ]),
        h('label', {key: 'highlight', className: 'dgn-cambridge-color', title: 'Tô sáng'}, [
            h(Highlighter, {key: 'icon', size: 15}),
            h('input', {
                key: 'input', type: 'color', 'data-dgn-command': 'highlight',
                value: state.highlight.color || '#FFF2A8', disabled,
                onChange: event => editor.chain().focus().setHighlight({color: event.target.value.toUpperCase()}).run(),
            }),
        ]),
        h('select', {
            key: 'block-type', className: 'dgn-cambridge-select', 'data-dgn-command': 'block-type',
            'aria-label': 'Kiểu đoạn', value: currentBlock, disabled,
            onChange: event => event.target.value === 'paragraph'
                ? editor.chain().focus().setParagraph().run()
                : editor.chain().focus().setHeading({level: Number(event.target.value)}).run(),
        }, [
            h('option', {key: 'p', value: 'paragraph'}, 'Normal'),
            h('option', {key: '1', value: '1'}, 'Heading 1'),
            h('option', {key: '2', value: '2'}, 'Heading 2'),
            h('option', {key: '3', value: '3'}, 'Heading 3'),
        ]),
        separator('s3'),
        button({id: 'bullet-list', title: 'Danh sách dấu đầu dòng', icon: List, active: state.bulletList, disabled, onClick: () => editor.chain().focus().toggleBulletList().run()}),
        button({id: 'ordered-list', title: 'Danh sách đánh số', icon: ListOrdered, active: state.orderedList, disabled, onClick: () => editor.chain().focus().toggleOrderedList().run()}),
        button({id: 'indent-decrease', title: 'Giảm thụt lề danh sách', icon: IndentDecrease, disabled, onClick: () => editor.chain().focus().liftListItem('listItem').run()}),
        button({id: 'indent-increase', title: 'Tăng thụt lề danh sách', icon: IndentIncrease, disabled, onClick: () => editor.chain().focus().sinkListItem('listItem').run()}),
        button({id: 'align-left', title: 'Căn trái', icon: AlignLeft, active: state.alignLeft, disabled, onClick: () => editor.chain().focus().setTextAlign('left').run()}),
        button({id: 'align-center', title: 'Căn giữa', icon: AlignCenter, active: state.alignCenter, disabled, onClick: () => editor.chain().focus().setTextAlign('center').run()}),
        button({id: 'align-right', title: 'Căn phải', icon: AlignRight, active: state.alignRight, disabled, onClick: () => editor.chain().focus().setTextAlign('right').run()}),
        button({id: 'align-justify', title: 'Căn đều', icon: AlignJustify, active: state.alignJustify, disabled, onClick: () => editor.chain().focus().setTextAlign('justify').run()}),
        separator('s4'),
        button({id: 'link', title: 'Thêm/sửa liên kết', icon: Link2, active: state.link, disabled, onClick: () => editLink(editor)}),
        button({id: 'unlink', title: 'Xóa liên kết', icon: Unlink, disabled: disabled || !state.link, onClick: () => editor.chain().focus().extendMarkRange('link').unsetLink().run()}),
        button({id: 'insert-picture', title: 'Chèn ảnh', icon: ImagePlus, disabled, onClick: choosePicture}),
        button({id: 'insert-table', title: 'Chèn bảng 3×3', icon: Table2, disabled, onClick: () => editor.chain().focus().insertTable({rows: 3, cols: 3, withHeaderRow: false}).run()}),
        button({id: 'add-row', title: 'Thêm hàng', label: '+Row', disabled, onClick: () => editor.chain().focus().addRowAfter().run()}),
        button({id: 'add-column', title: 'Thêm cột', label: '+Col', disabled, onClick: () => editor.chain().focus().addColumnAfter().run()}),
        button({id: 'delete-row', title: 'Xóa hàng', label: '−Row', disabled, onClick: () => editor.chain().focus().deleteRow().run()}),
        button({id: 'delete-column', title: 'Xóa cột', label: '−Col', disabled, onClick: () => editor.chain().focus().deleteColumn().run()}),
        button({id: 'delete-table', title: 'Xóa bảng', label: '×Table', disabled, onClick: () => editor.chain().focus().deleteTable().run()}),
        button({id: 'horizontal-rule', title: 'Đường kẻ ngang', label: '—', disabled, onClick: () => editor.chain().focus().setHorizontalRule().run()}),
        button({id: 'page-break', title: 'Ngắt trang', icon: Download, disabled, onClick: () => insertBlockAfterSelection(editor, {type: 'pageBreak'})}),
        separator('s5'),
        button({id: 'question', title: 'Câu hỏi DIGIERA', label: 'Câu hỏi', disabled, onClick: () => {
            const q = nextQuestion(editor);
            insertBlockAfterSelection(editor, {type: 'question', attrs: {id: q.id, points: null, align: null}, content: [{type: 'text', text: q.text}]});
        }}),
        button({id: 'short-answer', title: 'Trả lời ngắn', label: 'Ngắn', disabled, onClick: () => insertSmartAnswer(editor, 'shortAnswer', {minHeight: 48, placeholder: null})}),
        button({id: 'long-answer', title: 'Trả lời dài', label: 'Dài', disabled, onClick: () => insertSmartAnswer(editor, 'longAnswer', {minHeight: 160, placeholder: null})}),
        button({id: 'answer-table', title: 'Ô trả lời', label: 'Ô trả lời', disabled, onClick: () => insertSmartAnswer(editor, 'answerTable', {rows: 2, cols: 2})}),
        button({id: 'math', title: 'Công thức', icon: Sigma, disabled, onClick: () => {
            if (typeof requestMath === 'function') {
                requestMath({editor, insert: attrs => insertBlockAfterSelection(editor, {type: 'mathBlock', attrs})});
                return;
            }
            const promptFn = editor.view.dom.ownerDocument.defaultView.prompt;
            const source = typeof promptFn === 'function' ? promptFn('Công thức', '') : null;
            if (source && source.trim()) {
                insertBlockAfterSelection(editor, {type: 'mathBlock', attrs: {source: source.trim()}});
            }
        }}),
        separator('s6'),
        button({id: 'orientation-portrait', title: 'Trang dọc', label: 'Dọc', active: layout.orientation === 'portrait', disabled, onClick: () => applyLayout({orientation: 'portrait'})}),
        button({id: 'orientation-landscape', title: 'Trang ngang', label: 'Ngang', active: layout.orientation === 'landscape', disabled, onClick: () => applyLayout({orientation: 'landscape'})}),
        button({id: 'margin-normal', title: 'Lề thường', label: 'Lề thường', active: layout.margin === 'normal', disabled, onClick: () => applyLayout({margin: 'normal'})}),
        button({id: 'margin-narrow', title: 'Lề hẹp', label: 'Lề hẹp', active: layout.margin === 'narrow', disabled, onClick: () => applyLayout({margin: 'narrow'})}),
        button({id: 'margin-wide', title: 'Lề rộng', label: 'Lề rộng', active: layout.margin === 'wide', disabled, onClick: () => applyLayout({margin: 'wide'})}),
        h('select', {
            key: 'zoom', className: 'dgn-cambridge-select dgn-cambridge-select--zoom', 'data-dgn-command': 'zoom',
            'aria-label': 'Thu phóng', value: String(zoom),
            onChange: event => applyZoom(Number(event.target.value)),
        }, [75, 100, 125, 150].map(value => h('option', {key: value, value: String(value)}, `${value}%`))),
    ];

    return h(React.Fragment, null, [
        h('div', {
            key: 'toolbar',
            className: 'dgn-cambridge-toolbar',
            role: 'toolbar',
            'aria-label': 'DIGIERA document toolbar',
            'data-dgn-cambridge-toolbar': '1',
        }, controls),
        h('input', {
            key: 'image-input', ref: imageInputRef, type: 'file', accept: 'image/*', hidden: true,
            onChange: event => {
                const file = event.target.files?.[0];
                event.target.value = '';
                if (!file) return;
                setPicture({file, capturedPos: capturedPosRef.current ?? editor.state.selection.from});
            },
        }),
        h(PictureEditorDialog, {
            key: 'picture-editor',
            open: Boolean(picture),
            file: picture?.file || null,
            initial: PICTURE_DEFAULTS,
            busy: pictureBusy,
            error: pictureError,
            onCancel: () => { if (!pictureBusy) setPicture(null); },
            onSubmit: submitPicture,
        }),
        pictureError && !picture ? h('div', {key: 'picture-error', className: 'dgn-picture-inline-error', role: 'alert'}, pictureError) : null,
    ]);
}
