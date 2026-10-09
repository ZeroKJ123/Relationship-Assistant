import { initializeDialogs, initializeTabs } from './modules/dialogs.js';

initializeDialogs(document);
initializeTabs(document);

function unlockForms() {
    document.querySelectorAll('[data-submit-lock]').forEach((form) => {
        delete form.dataset.submitting;
        form.querySelectorAll('button[type="submit"]').forEach((button) => { button.disabled = false; });
    });
}
document.querySelectorAll('[data-submit-lock]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (form.dataset.submitting === 'true') { event.preventDefault(); return; }
        form.dataset.submitting = 'true';
        form.querySelectorAll('button[type="submit"]').forEach((button) => { button.disabled = true; });
    });
});
window.addEventListener('pageshow', unlockForms);
