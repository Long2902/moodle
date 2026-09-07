import assert from 'node:assert/strict';
import {createAutosaveController} from '../src/autosave.js';

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

const statuses = [];
const payloads = [];
let revision = 0;
const controller = createAutosaveController({
  delay: 5,
  onStatus: (status) => statuses.push(status),
  save: async (payload) => {
    payloads.push(payload);
    revision += 1;
    return {ok: true, conflict: false, revision};
  },
});

controller.schedule({nativejson: '{"a":1}', revision: 0});
controller.schedule({nativejson: '{"a":2}', revision: 0});
await sleep(25);
assert.equal(payloads.length, 1, 'debounce must collapse rapid edits');
assert.equal(payloads[0].nativejson, '{"a":2}');
assert.equal(controller.revision(), 1);
assert.ok(statuses.includes('saving'));
assert.ok(statuses.includes('saved'));

await controller.saveNow({nativejson: '{"a":3}', revision: controller.revision()});
assert.equal(payloads.length, 2, 'manual Save must use same save lane');
assert.equal(controller.revision(), 2);

const conflictStatuses = [];
let calls = 0;
const conflictController = createAutosaveController({
  delay: 5,
  onStatus: (status) => conflictStatuses.push(status),
  save: async () => {
    calls += 1;
    return {ok: false, conflict: true, revision: 9};
  },
});
await conflictController.saveNow({nativejson: '{}', revision: 0});
assert.equal(conflictController.blocked(), true, 'conflict must block future automatic writes');
conflictController.schedule({nativejson: '{"later":1}', revision: 9});
await sleep(20);
assert.equal(calls, 1, 'blocked autosave must not write again');
assert.ok(conflictStatuses.includes('conflict'));

console.log('AUTOSAVE_DEBOUNCE=PASS');
console.log('MANUAL_SAVE=PASS');
console.log('AUTOSAVE_CONFLICT_BLOCK=PASS');
