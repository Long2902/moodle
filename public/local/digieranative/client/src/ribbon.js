import {toNativeDocument} from './document_adapter.js';

export const RIBBON_TABS = ['File', 'Home', 'Insert', 'Layout', 'Review', 'Math', 'View'];

function nextQuestion(editor) {
    const used = new Set();
    editor.state.doc.descendants((node) => {
        if (node.type.name === 'question') used.add(node.attrs.id);
    });
    let number = 1;
    while (used.has(`q${number}`)) number += 1;
    return {id: `q${number}`, text: `Câu ${number}`};
}

function selectedQuestionId(editor) {
    const {$from} = editor.state.selection;
    for (let depth = $from.depth; depth > 0; depth -= 1) {
        const node = $from.node(depth);
        if (node.type.name === 'question') return node.attrs.id;
    }
    return null;
}

function emitHook(element, name, detail) {
    const EventClass = element.ownerDocument.defaultView.CustomEvent;
    element.dispatchEvent(new EventClass(name, {bubbles: true, detail}));
}

function insertAfterSelection(editor, node) {
    return editor.chain().focus().insertContent(node).run();
}

function definitions() {
    return {
        File: [{id:'save', label:'Lưu', group:'Tệp'}],
        Home: [
            {id:'undo', label:'↶', group:'Lịch sử', run:e=>e.chain().focus().undo().run()},
            {id:'redo', label:'↷', group:'Lịch sử', run:e=>e.chain().focus().redo().run()},
            {id:'bold', label:'B', group:'Phông chữ', active:e=>e.isActive('bold'), run:e=>e.chain().focus().toggleBold().run()},
            {id:'italic', label:'I', group:'Phông chữ', active:e=>e.isActive('italic'), run:e=>e.chain().focus().toggleItalic().run()},
            {id:'underline', label:'U', group:'Phông chữ', active:e=>e.isActive('underline'), run:e=>e.chain().focus().toggleUnderline().run()},
            {id:'strike', label:'S', group:'Phông chữ', active:e=>e.isActive('strike'), run:e=>e.chain().focus().toggleStrike().run()},
            {id:'heading1', label:'H1', group:'Đoạn', active:e=>e.isActive('heading',{level:1}), run:e=>e.chain().focus().toggleHeading({level:1}).run()},
            {id:'heading2', label:'H2', group:'Đoạn', active:e=>e.isActive('heading',{level:2}), run:e=>e.chain().focus().toggleHeading({level:2}).run()},
            {id:'align-left', label:'≡', group:'Đoạn', run:e=>e.chain().focus().setTextAlign('left').run()},
            {id:'align-center', label:'≣', group:'Đoạn', run:e=>e.chain().focus().setTextAlign('center').run()},
            {id:'align-right', label:'≡', group:'Đoạn', run:e=>e.chain().focus().setTextAlign('right').run()},
            {id:'ordered-list', label:'1.', group:'Đoạn', active:e=>e.isActive('orderedList'), run:e=>e.chain().focus().toggleOrderedList().run()},
            {id:'unordered-list', label:'•', group:'Đoạn', active:e=>e.isActive('bulletList'), run:e=>e.chain().focus().toggleBulletList().run()},
        ],
        Insert: [
            {id:'table', label:'Bảng', group:'Bảng', run:e=>insertAfterSelection(e,{type:'table',content:[{type:'tableRow',content:[{type:'tableCell',content:[{type:'paragraph'}]},{type:'tableCell',content:[{type:'paragraph'}]}]},{type:'tableRow',content:[{type:'tableCell',content:[{type:'paragraph'}]},{type:'tableCell',content:[{type:'paragraph'}]}]}]})},
            {id:'image', label:'Ảnh', group:'Đa phương tiện'},
            {id:'question', label:'Câu hỏi', group:'Phiếu', run:e=>{const q=nextQuestion(e);return insertAfterSelection(e,{type:'question',attrs:{id:q.id,points:null,align:null},content:[{type:'text',text:q.text}]});}},
            {id:'short-answer', label:'Trả lời ngắn', group:'Phiếu'},
            {id:'long-answer', label:'Trả lời dài', group:'Phiếu'},
            {id:'answer-table', label:'Ô trả lời', group:'Phiếu'},
        ],
        Layout: [{id:'layout-info', label:'Trang', group:'Bố cục'}],
        Review: [{id:'review-info', label:'Xem lại', group:'Kiểm tra'}],
        Math: [{id:'math', label:'∑ Công thức', group:'Toán'}],
        View: [{id:'view-info', label:'100%', group:'Hiển thị'}],
    };
}

function specialAction(control, element, editor) {
    if (control.id === 'image') {
        emitHook(element, 'digiera-native:request-image', {editor, insert:(attrs)=>insertAfterSelection(editor,{type:'image',attrs})});
        return true;
    }
    if (control.id === 'math') {
        emitHook(element, 'digiera-native:request-math', {editor, insert:(attrs)=>insertAfterSelection(editor,{type:'mathBlock',attrs})});
        return true;
    }
    const questionId = selectedQuestionId(editor);
    if (!questionId) return false;
    if (control.id === 'short-answer') return insertAfterSelection(editor,{type:'shortAnswer',attrs:{questionId,minHeight:48,placeholder:null}});
    if (control.id === 'long-answer') return insertAfterSelection(editor,{type:'longAnswer',attrs:{questionId,minHeight:160}});
    if (control.id === 'answer-table') return insertAfterSelection(editor,{type:'answerTable',attrs:{questionId,rows:2,cols:2}});
    return false;
}

export function createRibbon({element, getEditor, readonly=false, save=null, documentJson=null}) {
    const document = element.ownerDocument;
    element.querySelector(':scope > .dgn-ribbon')?.remove();
    element.classList.add('dgn-editor');
    const ribbon=document.createElement('div'); ribbon.className='dgn-ribbon'; ribbon.dataset.dgnRibbon='1';
    const tabs=document.createElement('div'); tabs.className='dgn-ribbon-tabs dgn-ribbon__tabs'; tabs.setAttribute('role','tablist');
    const panels=document.createElement('div'); panels.className='dgn-ribbon-panels';
    const panelMap=new Map();
    const defs=definitions();

    const refresh=()=>{
        const editor=getEditor(); if(!editor) return;
        ribbon.querySelectorAll('[data-dgn-command]').forEach(button=>{
            const control=button._dgnControl;
            const active=typeof control?.active==='function' && control.active(editor);
            button.classList.toggle('is-active',Boolean(active));
            button.setAttribute('aria-pressed',active?'true':'false');
        });
    };

    const activate=(tabName)=>{
        for(const [name,panel] of panelMap) panel.hidden=name!==tabName;
        tabs.querySelectorAll('.dgn-ribbon-tab').forEach(button=>button.setAttribute('aria-selected',button.dataset.tab===tabName?'true':'false'));
    };

    for(const tabName of RIBBON_TABS){
        const tab=document.createElement('button'); tab.type='button'; tab.className='dgn-ribbon-tab'; tab.textContent=tabName; tab.dataset.tab=tabName; tab.setAttribute('role','tab'); tabs.append(tab);
        const panel=document.createElement('div'); panel.className='dgn-ribbon-panel'; panel.dataset.tabPanel=tabName;
        const groups=new Map();
        for(const control of defs[tabName]){
            if(!groups.has(control.group)){
                const group=document.createElement('div'); group.className='dgn-ribbon-group';
                const controls=document.createElement('div'); controls.className='dgn-ribbon-group__controls';
                const label=document.createElement('div'); label.className='dgn-ribbon-group__label'; label.textContent=control.group;
                group.append(controls,label); panel.append(group); groups.set(control.group,controls);
            }
            const button=document.createElement('button'); button.type='button'; button.className='dgn-ribbon-control'; button.textContent=control.label; button.dataset.dgnCommand=control.id; button._dgnControl=control; button.setAttribute('aria-label',control.label);
            if(readonly) button.disabled=true;
            button.addEventListener('click',()=>{
                const editor=getEditor(); if(!editor||readonly) return;
                if(control.id==='save' && typeof save==='function'){
                    save(toNativeDocument(editor.getJSON(),{version:documentJson?.version||1,meta:documentJson?.meta}));
                } else if(typeof control.run==='function') control.run(editor); else specialAction(control,element,editor);
                refresh();
            });
            groups.get(control.group).append(button);
        }
        panelMap.set(tabName,panel); panels.append(panel); tab.addEventListener('click',()=>activate(tabName));
    }
    ribbon.append(tabs,panels); element.prepend(ribbon); activate('Home');
    const editor=getEditor(); editor?.on?.('selectionUpdate',refresh); editor?.on?.('transaction',refresh); refresh();
    return ribbon;
}
