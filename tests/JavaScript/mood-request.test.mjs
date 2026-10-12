import test from 'node:test';
import assert from 'node:assert/strict';
import { bindMoodRequest } from '../../resources/js/modules/mood-request.js';

function fixture(selfUrl) {
    let submit; let timer; const sent = [];
    const form = { action: '/partner', dataset: selfUrl ? { moodSelf: selfUrl } : {}, addEventListener: (name, callback) => { submit = callback; } };
    bindMoodRequest(form, (url) => sent.push(url), (callback) => { timer = callback; return 1; }, () => { timer = undefined; });
    return { sent, press: () => submit({ preventDefault() {} }), elapse: () => timer?.() };
}

test('a single press asks the partner only after the double press window closes', () => {
    const f = fixture('/self');
    f.press();
    assert.deepEqual(f.sent, []);
    f.elapse();
    assert.deepEqual(f.sent, ['/partner']);
});

test('a double press sends one question to yourself and never asks the partner', () => {
    const f = fixture('/self');
    f.press(); f.press(); f.elapse();
    assert.deepEqual(f.sent, ['/self']);
    f.press(); f.elapse();
    assert.deepEqual(f.sent, ['/self', '/partner']);
});

test('without a self address every press asks the partner immediately', () => {
    const f = fixture();
    f.press();
    assert.deepEqual(f.sent, ['/partner']);
});
