export function initializeRequests(root) {
    root.querySelectorAll('[data-condition-form]').forEach((form) => {
        if (form.dataset.requestsInit1) return;
        form.dataset.requestsInit1 = 'true';
        const type = form.elements.namedItem('condition_type');
        const method = form.elements.namedItem('settlement_method_id');
        if (!type || !method) return;
        form.querySelector('[data-condition-choices]').hidden = false;
        form.querySelector('[data-native-condition-type]').hidden = true;
        const kinds = { money: 'money', wishlist: 'gift', kisses: 'kisses', action: 'action', sushi: 'custom', custom: 'custom', fuel: 'custom' };
        function updateFields(clear) {
            const kind = method.selectedOptions[0]?.dataset.kind;
            const active = kind === 'sushi' ? 'sushi' : type.value;
            form.querySelectorAll('[data-condition-fields]').forEach((group) => {
                const enabled = group.dataset.conditionFields === active;
                group.hidden = !enabled;
                group.querySelectorAll('input,select,textarea').forEach((field) => {
                    field.disabled = !enabled;
                    field.required = enabled && ['amount', 'currency', 'wishlist_choice', 'sushi_size'].includes(field.name);
                    if (!enabled && clear && field.name !== 'currency') field.value = '';
                    if (enabled && field.name === 'currency' && !field.value) field.selectedIndex = 0;
                });
            });
            form.querySelectorAll('[data-condition-choice]').forEach((button) => {
                button.setAttribute('aria-pressed', String(button.dataset.conditionChoice === active));
            });
            form.querySelector('[data-condition-fallback]').hidden = true;
        }
        method.addEventListener('change', () => {
            const kind = method.selectedOptions[0]?.dataset.kind;
            if (kind && kinds[kind]) type.value = kinds[kind];
            updateFields(true);
        });
        type.addEventListener('change', () => {
            method.value = '';
            updateFields(true);
        });
        form.querySelectorAll('[data-condition-choice]').forEach((button) => {
            button.addEventListener('click', () => {
                const choice = button.dataset.conditionChoice;
                const kind = choice === 'gift' ? 'wishlist' : choice;
                const option = [...method.options].find((option) => option.dataset.kind === kind);
                method.value = option?.value ?? '';
                type.value = choice === 'sushi' ? 'custom' : choice;
                updateFields(true);
            });
        });
        const initialKind = method.selectedOptions[0]?.dataset.kind;
        if (initialKind && kinds[initialKind]) type.value = kinds[initialKind];
        updateFields(false);
    });
    root.querySelectorAll('[data-request-editor]').forEach((form) => {
        if (form.dataset.requestsInit2) return;
        form.dataset.requestsInit2 = 'true';
        const type = form.elements.namedItem('type');
        if (!type || type.type === 'hidden') return;
        function updateRequestFields() {
            const isRating = type.value === 'rating';
            ['heart_price', 'is_very_wanted'].forEach((name) => {
                const field = form.elements.namedItem(name);
                if (!field) return;
                field.disabled = name === 'heart_price' ? type.value !== 'service' : isRating;
                if (isRating && field.type === 'checkbox') field.checked = false;
                if (field.disabled && field.type !== 'checkbox') field.value = '0';
            });
            const price = form.querySelector('[data-request-price]');
            const veryWanted = form.querySelector('[data-request-very-wanted]');
            const ratingHint = form.querySelector('[data-request-rating-hint]');
            if (price) price.hidden = type.value !== 'service';
            if (veryWanted) veryWanted.hidden = isRating;
            if (ratingHint) ratingHint.hidden = !isRating;
        }
        updateRequestFields();
        type.addEventListener('change', () => {
            updateRequestFields();
            const preset = form.elements.namedItem('request_template_id');
            if (preset) preset.value = '';
        });
    });
}
