import { initializeDialogs, initializeTabs } from './modules/dialogs.js';

initializeDialogs(document);
initializeTabs(document);

function unlockForms() {
    document.querySelectorAll('[data-submit-lock]').forEach((form) => {
        delete form.dataset.submitting;
        form.querySelectorAll('[data-submitter-value]').forEach((input) => { input.remove(); });
        form.querySelectorAll('button[type="submit"]').forEach((button) => { button.disabled = false; });
    });
}
document.querySelectorAll('[data-submit-lock]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (form.dataset.submitting === 'true') { event.preventDefault(); return; }
        const submitter = event.submitter;
        if (submitter?.name) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = submitter.name;
            input.value = submitter.value;
            input.dataset.submitterValue = 'true';
            form.append(input);
        }
        form.dataset.submitting = 'true';
        form.querySelectorAll('button[type="submit"]').forEach((button) => { button.disabled = true; });
    });
});
window.addEventListener('pageshow', unlockForms);
