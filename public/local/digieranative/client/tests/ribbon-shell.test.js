/* @vitest-environment jsdom */
import fs from 'node:fs';import path from 'node:path';
import {describe,expect,it} from 'vitest';
import {mount} from '../src/index.js';

describe('DIGIERA Native Tiptap Word-like Ribbon',()=>{
 it('renders seven scoped tabs and wires Home Bold',()=>{
  const element=document.createElement('div');document.body.append(element);
  const editor=mount({element,documentJson:{type:'worksheet',version:1,content:[{type:'paragraph',content:[{type:'text',text:'DIGIERA'}]}]}});
  const tabs=Array.from(element.querySelectorAll('.dgn-ribbon-tab')).map(n=>n.textContent.trim());
  expect(tabs).toEqual(['File','Home','Insert','Layout','Review','Math','View']);
  editor.commands.setTextSelection({from:1,to:8});
  const bold=element.querySelector('[data-dgn-command="bold"]');expect(bold).not.toBeNull();bold.click();
  expect(editor.isActive('bold')).toBe(true);
  const css=fs.readFileSync(path.resolve(process.cwd(),'../styles.css'),'utf8');
  const start=css.indexOf('DIGIERA_NATIVE_RIBBON_START'),end=css.indexOf('DIGIERA_NATIVE_RIBBON_END');
  expect(start).toBeGreaterThan(-1);expect(end).toBeGreaterThan(start);
  expect(css.slice(start,end)).not.toMatch(/(^|[,{]\s*)(\.btn|\.card|\.navbar)\b/m);
  editor.destroy();element.remove();
 });
 it('makes editor and Ribbon read-only',()=>{
  const element=document.createElement('div');document.body.append(element);
  const editor=mount({element,readonly:true,documentJson:{type:'worksheet',version:1,content:[{type:'paragraph'}]}});
  expect(editor.isEditable).toBe(false);expect(element.querySelector('[data-dgn-command="bold"]').disabled).toBe(true);
  editor.destroy();element.remove();
 });
 it('passes canonical worksheet JSON to manual Save',()=>{
  const element=document.createElement('div');document.body.append(element);let saved=null;
  const editor=mount({element,documentJson:{type:'worksheet',version:1,meta:{title:'Save contract'},content:[{type:'paragraph',content:[{type:'text',text:'Save me'}]}]},save:json=>{saved=json;}});
  element.querySelector('[data-dgn-command="save"]').click();
  expect(saved?.type).toBe('worksheet');expect(saved?.version).toBe(1);expect(saved?.meta?.title).toBe('Save contract');expect(saved?.content?.[0]?.content?.[0]?.text).toBe('Save me');
  editor.destroy();element.remove();
 });
});
