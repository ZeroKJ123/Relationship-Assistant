const dutyLifecycles = new Set();

export function initializeDuties(root) {
    for (const item of dutyLifecycles) { if (!item.form.isConnected) { item.controller.abort(); dutyLifecycles.delete(item); } }
    if (typeof HTMLDialogElement === 'undefined') return;
    root.querySelectorAll('[data-duty-confirmation]').forEach((form) => {
        if (form.dataset.dutiesInit1) return;
        form.dataset.dutiesInit1 = 'true';
        const checkbox = form.elements.namedItem('confirmation');
        const content = form.querySelector('[data-duty-confirmation-content]');
        if (!checkbox || !content) return;
        const dialog = document.createElement('dialog');
        dialog.className = 'sheet duty-confirmation';
        dialog.setAttribute('aria-labelledby', 'duty-confirmation-title');
        while (content.firstChild) dialog.append(content.firstChild);
        content.replaceWith(dialog);
        checkbox.required = false;
        dialog.querySelector('[data-duty-dialog-actions]').hidden = false;
        let submitter;
        let approved = false;
        form.addEventListener('submit', (event) => {
            if (approved) return;
            event.preventDefault();
            event.stopImmediatePropagation();
            submitter = event.submitter ?? form.querySelector('button[type="submit"]');
            const isPositive = submitter.value === '1';
            dialog.querySelector('[data-duty-question]').textContent = isPositive ? form.dataset.questionYes : form.dataset.questionNo;
            checkbox.checked = false;
            checkbox.required = true;
            dialog.showModal();
        }, { capture: true });
        dialog.querySelector('[data-duty-back]').addEventListener('click', () => dialog.close());
        dialog.querySelector('[data-duty-send]').addEventListener('click', (event) => {
            if (!form.reportValidity() || event.currentTarget.disabled) return;
            approved = true;
            event.currentTarget.disabled = true;
            dialog.close();
            form.requestSubmit(submitter);
        });
        dialog.addEventListener('close', () => {
            checkbox.required = false;
            if (!approved) checkbox.checked = false;
            submitter?.focus();
        });
        const controller = new AbortController();
        dutyLifecycles.add({ form, controller });
        window.addEventListener('pageshow', () => {
            approved = false;
            checkbox.checked = false;
            dialog.querySelector('[data-duty-send]').disabled = false;
        }, { signal: controller.signal });
    });
}
