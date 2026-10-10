import test from 'node:test';
import assert from 'node:assert/strict';
import { initializeRequests } from '../../resources/js/modules/requests.js';

function editor(value = 'service') {
    const events = {};
    const fields = {
        type: { value, type: 'select-one', addEventListener(name, callback) { events[name] = callback; } },
        heart_price: { value: '25', type: 'number', disabled: false },
        is_very_wanted: { checked: true, type: 'checkbox', disabled: false },
        request_template_id: { value: '3' },
    };
    const groups = {
        '[data-request-price]': { hidden: false },
        '[data-request-very-wanted]': { hidden: false },
        '[data-request-rating-hint]': { hidden: true },
    };
    const form = { elements: { namedItem(name) { return fields[name]; } }, querySelector(selector) { return groups[selector]; } };
    initializeRequests({ querySelectorAll(selector) { return selector === '[data-request-editor]' ? [form] : []; } });
    return { fields, groups, change(value) { fields.type.value = value; events.change(); } };
}

test('ordinary requests retain their price and importance while hiding the rating explanation', () => {
    const f = editor();
    assert.equal(f.fields.heart_price.value, '25');
    assert.equal(f.fields.heart_price.disabled, false);
    assert.equal(f.fields.is_very_wanted.disabled, false);
    assert.equal(f.groups['[data-request-rating-hint]'].hidden, true);
});

test('changing to a rating removes charges importance and a stale template from submission', () => {
    const f = editor();
    f.change('rating');
    assert.equal(f.fields.heart_price.value, '0');
    assert.equal(f.fields.heart_price.disabled, true);
    assert.equal(f.fields.is_very_wanted.checked, false);
    assert.equal(f.fields.is_very_wanted.disabled, true);
    assert.equal(f.fields.request_template_id.value, '');
    assert.equal(f.groups['[data-request-price]'].hidden, true);
    assert.equal(f.groups['[data-request-very-wanted]'].hidden, true);
    assert.equal(f.groups['[data-request-rating-hint]'].hidden, false);
    f.change('service');
    assert.equal(f.fields.heart_price.disabled, false);
    assert.equal(f.fields.is_very_wanted.disabled, false);
});

test('gift and plain food requests never submit a price left over from a service', () => {
    for (const type of ['gift', 'food']) {
        const f = editor();
        f.change(type);
        assert.equal(f.fields.heart_price.value, '0');
        assert.equal(f.fields.heart_price.disabled, true);
        assert.equal(f.fields.is_very_wanted.disabled, false);
        assert.equal(f.groups['[data-request-price]'].hidden, true);
    }
});
