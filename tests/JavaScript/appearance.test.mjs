import test from 'node:test';
import assert from 'node:assert/strict';
import { conditionMatches, moveBuilderItem, renumberBuilderList } from '../../resources/js/modules/appearance.js';

test('visibility preview handles positive and inverse conditions including missing data', () => {
    const state = {has_partner:true, has_car:false, positive_balance:true, has_duties:false, has_request:false};
    assert.equal(conditionMatches('always', state), true);
    assert.equal(conditionMatches('has_partner', state), true);
    assert.equal(conditionMatches('no_partner', state), false);
    assert.equal(conditionMatches('has_car', state), false);
    assert.equal(conditionMatches('no_car', state), true);
    assert.equal(conditionMatches('positive_balance', state), true);
    assert.equal(conditionMatches('has_duties', state), false);
    assert.equal(conditionMatches('has_request', state), false);
    assert.equal(conditionMatches('unknown', state), false);
});

function listFixture() {
    const items = ['first', 'second', 'third'].map((id) => ({id}));
    const parent = {
        children:items,
        insertBefore(item, target) {
            this.children.splice(this.children.indexOf(item), 1);
            this.children.splice(this.children.indexOf(target), 0, item);
        },
    };
    for (const item of items) {
        item.parentElement = parent;
        Object.defineProperty(item, 'previousElementSibling', {get:() => parent.children[parent.children.indexOf(item)-1]});
        Object.defineProperty(item, 'nextElementSibling', {get:() => parent.children[parent.children.indexOf(item)+1]});
    }
    return {parent, items:[...items]};
}

test('keyboard move controls reorder blocks without dropping neighboring items', () => {
    const {parent, items} = listFixture();
    assert.equal(moveBuilderItem(items[2], 'up'), true);
    assert.deepEqual(parent.children.map((item) => item.id), ['first', 'third', 'second']);
    assert.equal(moveBuilderItem(items[0], 'down'), true);
    assert.deepEqual(parent.children.map((item) => item.id), ['third', 'first', 'second']);
});

test('move controls preserve the first and last block at the list boundaries', () => {
    const {parent, items} = listFixture();
    assert.equal(moveBuilderItem(items[0], 'up'), false);
    assert.equal(moveBuilderItem(items[2], 'down'), false);
    assert.deepEqual(parent.children.map((item) => item.id), ['first', 'second', 'third']);
});

test('serialized order is updated after moving blocks including hidden checkbox fallbacks', () => {
    const firstFields = [{name:'layout[blocks][7][id]'}, {name:'layout[blocks][7][enabled]'}, {name:'layout[blocks][7][enabled]'}];
    const secondFields = [{name:'layout[blocks][2][title]'}];
    const list = {dataset:{builderList:'blocks'}, children:[{querySelectorAll:() => firstFields}, {querySelectorAll:() => secondFields}]};
    renumberBuilderList(list);
    assert.deepEqual(firstFields.map((field) => field.name), ['layout[blocks][0][id]', 'layout[blocks][0][enabled]', 'layout[blocks][0][enabled]']);
    assert.equal(secondFields[0].name, 'layout[blocks][1][title]');
});

test('pointer dragging keeps capture on the stable form and cancellation restores the original order', async () => {
    const { initializeAppearance } = await import('../../resources/js/modules/appearance.js');
    const {parent, items} = listFixture();
    const listeners = new Map();
    const frame = {addEventListener:() => {}, contentDocument:null};
    let captures = 0;
    let insertions = 0;
    const insert = parent.insertBefore.bind(parent);
    parent.insertBefore = (...args) => { insertions++; insert(...args); };
    parent.dataset = {builderList:'blocks'};
    parent.append = (item) => { parent.children.splice(parent.children.indexOf(item), 1); parent.children.push(item); };
    for (const item of items) {
        item.querySelectorAll = () => [];
        item.classList = {add:() => {}, remove:() => {}};
        item.getBoundingClientRect = () => ({top:70, height:100});
        Object.defineProperty(item, 'nextSibling', {get:() => item.nextElementSibling ?? null});
    }
    const handle = {closest:() => items[0]};
    const form = {
        dataset:{},
        querySelector:(selector) => selector==='[data-builder-preview]' ? frame : {},
        querySelectorAll:() => [parent],
        addEventListener:(name, callback) => listeners.set(name, callback),
        setPointerCapture:() => captures++,
    };
    const previousDocument = globalThis.document;
    globalThis.document = {elementFromPoint:() => ({closest:() => items[1]})};
    try {
        initializeAppearance({querySelectorAll:(selector) => selector==='[data-builder]' ? [form] : []});
        listeners.get('pointerdown')({target:{closest:() => handle},button:0,pointerId:1,clientX:0,clientY:0});
        assert.equal(captures, 1);
        listeners.get('pointermove')({clientX:10,clientY:80,preventDefault:() => {}});
        assert.equal(insertions, 0);
        listeners.get('pointermove')({clientX:10,clientY:160,preventDefault:() => {}});
        assert.deepEqual(parent.children.map(item => item.id), ['second','first','third']);
        listeners.get('pointercancel')();
        assert.deepEqual(parent.children.map(item => item.id), ['first','second','third']);
    } finally {
        globalThis.document = previousDocument;
    }
});
