export function initializePwa(root) {
    const banner = root.querySelector('[data-connection-status]');
    const updateConnection = () => { if (banner) { banner.hidden = navigator.onLine; } };
    updateConnection();
    window.addEventListener('online', updateConnection);
    window.addEventListener('offline', updateConnection);
    initializeOfflineForms(root);
    if (root.body?.dataset.authenticated === 'true') {
        window.addEventListener('pagehide', () => { root.body.hidden = true; });
        window.addEventListener('pageshow', (event) => {
            if (event.persisted) { window.location.reload(); return; }
            root.body.hidden = false;
        });
    }

    const help = root.querySelector('[data-pwa-help]');
    const button = root.querySelector('[data-pwa-install]');
    const status = root.querySelector('[data-pwa-status]');
    const isStandalone = () => window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
    if (help && isStandalone()) { help.hidden = true; }
    let installPrompt = null;
    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        installPrompt = event;
        if (button && !isStandalone()) { button.hidden = false; }
    });
    button?.addEventListener('click', async () => {
        if (!installPrompt || button.disabled) { return; }
        const prompt = installPrompt;
        installPrompt = null;
        button.disabled = true;
        try {
            await prompt.prompt();
            const choice = await prompt.userChoice;
            if (status) { status.textContent = choice.outcome === 'accepted' ? status.dataset.accepted : status.dataset.dismissed; }
        } catch {
            console.warn('pwa.install_failed');
            if (status) { status.textContent = status.dataset.failed; }
        } finally { button.hidden = true; button.disabled = false; }
    });
    window.addEventListener('appinstalled', () => {
        installPrompt = null;
        if (help) { help.hidden = true; }
        if (import.meta.env.DEV) { console.debug('pwa.installed'); }
    });
    if (!('serviceWorker' in navigator) || !window.isSecureContext || import.meta.env.DEV) { return; }
    navigator.serviceWorker.register('/sw.js', { scope: '/', updateViaCache: 'none' }).then((registration) => {
        registration.addEventListener('updatefound', () => {
            if (import.meta.env.DEV) { console.debug('pwa.update_found'); }
        });
    }).catch(() => { console.warn('pwa.registration_failed'); });
}

const offlineForms = new WeakSet();
export function initializeOfflineForms(root) {
    const banner = root.querySelector('[data-connection-status]');
    root.querySelectorAll('form[method="post"]').forEach((form) => {
        if (offlineForms.has(form)) return;
        offlineForms.add(form);
        form.addEventListener('submit', (event) => {
            if (navigator.onLine) { return; }
            event.preventDefault();
            if (banner) banner.hidden = false;
            banner?.scrollIntoView({ block: 'center' });
            banner?.focus();
        });
    });
}
