import assert from 'node:assert/strict';
import {fromNativeDocument, toNativeDocument} from '../src/document_adapter.js';

const native = {
  type: 'worksheet', version: 1, meta: {title: 'T'},
  content: [{type:'unorderedList', content:[{type:'listItem', content:[{type:'paragraph', content:[{type:'text', text:'A'}]}]}]}],
};
const tiptap = fromNativeDocument(native);
assert.equal(tiptap.type, 'doc');
assert.equal(tiptap.content[0].type, 'bulletList');
const roundtrip = toNativeDocument(tiptap, {version: 1, meta: native.meta});
assert.equal(roundtrip.type, 'worksheet');
assert.equal(roundtrip.content[0].type, 'unorderedList');
assert.deepEqual(roundtrip.meta, native.meta);
console.log('TIPTAP_ADAPTER_STANDALONE=PASS');
