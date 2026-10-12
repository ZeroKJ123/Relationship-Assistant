import { initializeAppearance } from './modules/appearance.js';
import { initializeSelectionPresence } from './modules/selection-presence.js';
import { initializeCare } from './modules/care.js';
import { initializeMood } from './modules/mood.js';
import { initializeRealtime } from './modules/realtime.js';
import { initializeMovies } from './modules/movies.js';
import { initializePwa, initializeOfflineForms } from './modules/pwa.js';
import { initializeDuties } from './modules/duties.js';
import { initializeWishlist } from './modules/wishlist.js';
import { initializePhotoViewer, initializeProductPhotos } from './modules/photos.js';
import { initializeDashboard } from './modules/dashboard.js';
import { initializeDishes } from './modules/dishes.js';
import { initializeRequests } from './modules/requests.js';
import { initializeDialogs, initializeTabs } from './modules/dialogs.js';

function initializeInterface(root) {
    initializeAppearance(root);
    initializeOfflineForms(root);
    initializeMovies(root);
    initializeDialogs(root);
    initializeTabs(root);
    initializeDashboard(root);
    initializeDishes(root);
    initializeRequests(root);
    initializeDuties(root);
    initializeWishlist(root);
    initializeProductPhotos(root);
    initializePhotoViewer(root);
    initializeMood(root);
    initializeCare(root);
    initializeSelectionPresence(root, initializeInterface);
    lockForms(root);
}
initializePwa(document);
initializeInterface(document);
initializeRealtime(document, initializeInterface);

function unlockForms() {
    document.querySelectorAll('[data-submit-lock]').forEach((form) => {
        delete form.dataset.submitting;
        form.querySelectorAll('[data-submitter-value]').forEach((input) => { input.remove(); });
        form.querySelectorAll('button[type="submit"]').forEach((button) => { button.disabled = false; });
    });
}
function lockForms(root) {
root.querySelectorAll('[data-submit-lock]').forEach((form) => {
    if (form.dataset.lockInitialized) return;
    form.dataset.lockInitialized = 'true';
    form.addEventListener('submit', (event) => {
        if (event.defaultPrevented) { return; }
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
}
window.addEventListener('pageshow', unlockForms);
