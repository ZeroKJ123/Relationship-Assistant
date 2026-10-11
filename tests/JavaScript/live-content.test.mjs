import test from 'node:test';
import assert from 'node:assert/strict';
import { getFormKey, refreshContent } from '../../resources/js/modules/live-content.js';
import { showToast } from '../../resources/js/modules/toast.js';

function form(action, dirty = false) {
    return { dataset: { liveDirty: String(dirty) }, matches() { return false; }, getAttribute(name) { return name === 'action' ? action : name === 'method' ? 'post' : null; }, querySelector() { return null; }, contains() { return false; }, replaceWith(node) { this.replacement = node; } };
}

test('a new unrelated form does not change the key of a draft being edited', () => {
    assert.equal(getFormKey(form('/profile')), getFormKey(form('/profile')));
});

test('a live update preserves the original draft node and updates other page content', () => {
    const draft = form('/profile', true);
    draft.value = 'Unsent personal draft';
    const incomingDraft = form('/profile');
    const main = { querySelectorAll(selector) { return selector === 'form' ? [draft] : []; }, replaceChildren(...nodes) { this.updated = nodes; } };
    const next = { querySelectorAll(selector) { return selector === 'form' ? [form('/new'), incomingDraft] : []; }, childNodes: ['updated mood'] };
    const root = { querySelector(selector) { return selector === '#main-content' ? main : null; }, activeElement: null };
    const incoming = { querySelector(selector) { return selector === '#main-content' ? next : null; } };
    let initialized = 0;
    assert.equal(refreshContent(root, incoming, () => { initialized++; }), true);
    assert.equal(incomingDraft.replacement, draft);
    assert.equal(draft.value, 'Unsent personal draft');
    assert.deepEqual(main.updated, ['updated mood']);
    assert.equal(initialized, 1);
});

test('an open confirmation blocks content replacement until it is closed', () => {
    const root = { querySelector(selector) { return selector === 'dialog[open]' ? {} : selector === '#main-content' ? {} : null; } };
    assert.equal(refreshContent(root, { querySelector() { return {}; } }, () => { throw new Error('Must wait'); }), false);
});

test('the mood toast disappears after two seconds and a new toast resets its timer', (context) => {
    context.mock.timers.enable({ apis: ['setTimeout'] });
    const toast = { hidden: true };
    const root = { querySelector() { return toast; } };
    showToast('Запрос отправлен', root);
    assert.equal(toast.hidden, false);
    context.mock.timers.tick(1500);
    showToast('Ещё один запрос', root);
    context.mock.timers.tick(1500);
    assert.equal(toast.hidden, false);
    context.mock.timers.tick(500);
    assert.equal(toast.hidden, true);
});

test('the mood request card keeps receiving new data after its button was clicked', () => {
    const card = form('/mood/prompts', true);
    card.matches = () => true;
    const incomingCard = form('/mood/prompts');
    const main = { querySelectorAll(selector) { return selector === 'form' ? [card] : []; }, replaceChildren() {} };
    const next = { querySelectorAll(selector) { return selector === 'form' ? [incomingCard] : []; }, childNodes: [] };
    const root = { activeElement: null, querySelector(selector) { return selector === '#main-content' ? main : null; } };
    assert.equal(refreshContent(root, { querySelector(selector) { return selector === '#main-content' ? next : null; } }, () => {}), true);
    assert.equal(incomingCard.replacement, undefined);
});

test('a live response for another signed-in account cannot preserve or display the previous draft', () => {
    const root = { body: { dataset: { userId: '1' } } };
    const incoming = { body: { dataset: { userId: '2' } } };
    assert.equal(refreshContent(root, incoming, () => { throw new Error('Must reload the session'); }), false);
});
