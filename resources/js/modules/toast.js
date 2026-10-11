let toastTimer;
export function showToast(message, root = document) {
    const toast = root.querySelector('[data-toast]');
    if (!toast) return;
    clearTimeout(toastTimer);
    toast.textContent = message;
    toast.hidden = false;
    toastTimer = setTimeout(() => { toast.hidden = true; }, 2000);
}
