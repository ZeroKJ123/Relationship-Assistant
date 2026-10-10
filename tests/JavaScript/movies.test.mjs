import test from 'node:test';
import assert from 'node:assert/strict';
import { initializeMovies } from '../../resources/js/modules/movies.js';

function fixture() {
    const handlers = {};
    const submitted = [];
    const capture = [];
    const form = { requestSubmit(button) { submitted.push(button.name); } };
    const like = { name: 'like', disabled: false, form };
    const skip = { name: 'skip', disabled: false, form };
    const card = {
        addEventListener(name, callback) { handlers[name] = callback; },
        querySelector(selector) { return selector === '[data-movie-like]' ? like : skip; },
        setPointerCapture(id) { capture.push(id); },
    };
    initializeMovies({ querySelectorAll() { return [card]; } });
    const event = (x, y = 0, interactive = false) => ({ clientX: x, clientY: y, pointerId: 1, isPrimary: true, target: { closest() { return interactive; } } });
    return { handlers, submitted, capture, like, event };
}

test('horizontal swipes submit the same explicit like or skip buttons as keyboard actions', () => {
    const f = fixture();
    f.handlers.pointerdown(f.event(100));
    f.handlers.pointerup(f.event(200));
    f.handlers.pointerdown(f.event(200));
    f.handlers.pointerup(f.event(100));
    assert.deepEqual(f.submitted, ['like', 'skip']);
    assert.deepEqual(f.capture, [1, 1]);
});

test('scrolling taps cancelled gestures and controls never cast unintended votes', () => {
    const f = fixture();
    f.handlers.pointerdown(f.event(100));
    f.handlers.pointerup(f.event(110));
    f.handlers.pointerdown(f.event(100));
    f.handlers.pointerup(f.event(200, 200));
    f.handlers.pointerdown(f.event(100));
    f.handlers.pointercancel();
    f.handlers.pointerup(f.event(200));
    f.handlers.pointerdown(f.event(100, 0, true));
    f.handlers.pointerup(f.event(200));
    assert.deepEqual(f.submitted, []);
});

test('a disabled submit button prevents another swipe while a vote is being sent', () => {
    const f = fixture();
    f.like.disabled = true;
    f.handlers.pointerdown(f.event(100));
    f.handlers.pointerup(f.event(200));
    assert.deepEqual(f.submitted, []);
});
