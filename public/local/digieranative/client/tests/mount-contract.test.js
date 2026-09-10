/* @vitest-environment jsdom */
import {describe,expect,it} from 'vitest';
import {mount} from '../src/index.js';

describe('DIGIERA Native Tiptap mount API',()=>{
 it('mounts Native worksheet JSON and exposes Tiptap editor state',()=>{
  const element=document.createElement('div');document.body.append(element);
  const editor=mount({element,documentJson:{type:'worksheet',version:1,content:[{type:'paragraph',content:[{type:'text',text:'Frozen mount contract'}]}]}});
  expect(editor.getJSON().type).toBe('doc');
  expect(editor.state.doc.textContent).toBe('Frozen mount contract');
  expect(element.querySelector('[data-dgn-official-toolbar="1"]')).not.toBeNull();
  expect(editor.view.dom.classList.contains('dgn-canvas')).toBe(true);
  editor.destroy();element.remove();
 });
 it('emits canonical Native worksheet JSON through onUpdate',()=>{
  const element=document.createElement('div');document.body.append(element);let updated=null;
  const editor=mount({element,documentJson:{type:'worksheet',version:1,meta:{title:'U'},content:[{type:'paragraph'}]},onUpdate:json=>{updated=json;}});
  editor.commands.setContent({type:'doc',content:[{type:'paragraph',content:[{type:'text',text:'Changed'}]}]},true);
  expect(updated?.type).toBe('worksheet');expect(updated?.meta?.title).toBe('U');expect(updated?.content?.[0]?.content?.[0]?.text).toBe('Changed');
  editor.destroy();element.remove();
 });
});
