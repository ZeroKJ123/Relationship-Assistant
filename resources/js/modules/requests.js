export function initializeRequests(root) {
    root.querySelectorAll('[data-condition-form]').forEach((form) => {
        const type = form.elements.namedItem('condition_type');
        const method = form.elements.namedItem('settlement_method_id');
        if (!type || !method) return;
        form.querySelector('[data-condition-choices]').hidden = false;
        form.querySelector('[data-native-condition-type]').hidden = true;
        const kinds = { money: 'money', wishlist: 'gift', kisses: 'kisses', action: 'action', sushi: 'custom', custom: 'custom' };
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
    root.querySelectorAll('[data-condition-section]').forEach((section) => {
        const content = section.querySelector('[data-condition-sheet]');
        const opener = section.querySelector('[data-condition-open]');
        if (!content || !opener || typeof HTMLDialogElement === 'undefined') return;
        opener.hidden = false;
        const dialog = document.createElement('dialog');
        dialog.id = content.id;
        dialog.className = 'sheet condition-sheet';
        dialog.setAttribute('aria-labelledby', 'condition-sheet-title');
        while (content.firstChild) dialog.append(content.firstChild);
        content.replaceWith(dialog);
        const closer = dialog.querySelector('[data-condition-close]');
        closer.hidden = false;
        opener.addEventListener('click', () => {
            dialog.showModal();
            opener.setAttribute('aria-expanded', 'true');
        });
        closer.addEventListener('click', () => dialog.close());
        dialog.addEventListener('close', () => {
            opener.setAttribute('aria-expanded', 'false');
            opener.focus();
        });
        if (section.dataset.conditionErrors === 'true') opener.click();
    });
    root.querySelectorAll('[data-request-editor]').forEach((form) => {
        const type = form.elements.namedItem('type');
        if (!type || type.type === 'hidden') return;
        type.addEventListener('change', () => {
            const preset = form.elements.namedItem('request_template_id');
            if (preset) preset.value = '';
        });
    });
}
