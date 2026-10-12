import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import { getSpinDelay } from '../../resources/js/modules/selection-presence.js';

function presenceFixture({ hidden = false, online = true } = {}) {
    let requests = 0;
    let timer;
    const node = { isConnected: true, dataset: { selectionPresence: '/presence', selectionStatus: 'waiting' } };
    const root = { hidden, querySelectorAll: () => node.isConnected ? [node] : [], querySelector: () => ({ content: 'test-csrf' }) };
    const context = { Map, Date, AbortController, AbortSignal, navigator: { onLine: online }, setTimeout: (fn) => { timer = fn; return 1; }, clearTimeout: () => { timer = undefined; }, fetch: async () => { requests++; return { ok: true, json: async () => ({ status: 'waiting', server_time: Date.now() }) }; } };
    vm.createContext(context);
    vm.runInContext(fs.readFileSync('resources/js/modules/selection-presence.js', 'utf8').replace(/^import .*;\n/gm, '').replaceAll('export function', 'function'), context);
    return { root, node, context, requests: () => requests, tick: () => timer?.() };
}

test('a page heartbeat is not sent by hidden or offline pages', async () => {
    for (const state of [{ hidden: true }, { online: false }]) {
        const f = presenceFixture(state);
        f.context.initializeSelectionPresence(f.root, () => {});
        await f.tick();
        assert.equal(f.requests(), 0);
        f.node.isConnected = false;
        f.context.initializeSelectionPresence(f.root, () => {});
    }
});

test('initializing the same page twice keeps one heartbeat loop and leaving cancels it', async () => {
    const f = presenceFixture();
    f.context.initializeSelectionPresence(f.root, () => {});
    f.context.initializeSelectionPresence(f.root, () => {});
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(f.requests(), 1);
    await f.tick();
    assert.equal(f.requests(), 2);
    f.node.isConnected = false;
    f.context.initializeSelectionPresence(f.root, () => {});
    await f.tick();
    assert.equal(f.requests(), 2);
});

test('late arrivals join the current animation timeline instead of beginning a new spin', () => {
    assert.equal(getSpinDelay(10000, 5000), 5);
    assert.equal(getSpinDelay(10000, 11500), -1.5);
    assert.equal(getSpinDelay(10000, 14000), -4);
});
