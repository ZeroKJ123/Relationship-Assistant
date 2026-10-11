export function getFormKey(form) {
    return `${form.getAttribute('action') ?? ''}|${form.getAttribute('method') ?? ''}|${form.getAttribute('id') ?? ''}|${form.querySelector('[name="_method"]')?.value ?? ''}|${form.querySelector('input[type="hidden"][name="response"],input[type="hidden"][name="decision"]')?.value ?? ''}|${form.querySelector('button[type="submit"][name]')?.value ?? ''}`;
}

export function refreshContent(root, incoming, initialize) {
    if (root.body?.dataset.userId !== incoming.body?.dataset.userId) return false;
    const current = root.querySelector('#main-content');
    const next = incoming.querySelector('#main-content');
    if (!current || !next || root.querySelector('dialog[open]') || root.querySelector('[data-submitting="true"],[data-sending="true"]')) return false;
    const active = root.activeElement;
    const selection = active && typeof active.selectionStart === 'number' ? [active.selectionStart, active.selectionEnd] : null;
    const preserved = new Map();
    current.querySelectorAll('form').forEach((form) => {
        if (form.matches('[data-mood-request]')) return;
        if (form.dataset.liveDirty === 'true' || (active?.matches('input,textarea,select') && form.contains(active))) preserved.set(getFormKey(form), form);
    });
    next.querySelectorAll('form').forEach((form) => {
        const previous = preserved.get(getFormKey(form));
        if (previous) form.replaceWith(previous);
    });
    const openDetails = new Set([...current.querySelectorAll('details[open]')].map((node) => node.querySelector('summary')?.textContent));
    next.querySelectorAll('details').forEach((node) => { if (openDetails.has(node.querySelector('summary')?.textContent)) node.open = true; });
    const selectedTabs = [...current.querySelectorAll('[role="tab"][aria-selected="true"]')].map((tab) => tab.getAttribute('aria-controls'));
    current.querySelectorAll('[data-live-preserve]').forEach((node) => {
        const replacement = next.querySelector(`[data-live-preserve="${node.dataset.livePreserve}"]`);
        if (replacement) replacement.replaceWith(node);
    });
    current.replaceChildren(...next.childNodes);
    const navigation = root.querySelector('.bottom-navigation');
    const nextNavigation = incoming.querySelector('.bottom-navigation');
    if (navigation && nextNavigation) navigation.replaceWith(nextNavigation);
    initialize(root);
    selectedTabs.forEach((id) => {
        const tab = root.querySelector(`[role="tab"][aria-controls="${id}"]`);
        const group = tab?.closest('[data-tabs]');
        if (!group) return;
        group.querySelectorAll('[role="tab"]').forEach((item) => {
            const selected = item === tab;
            item.setAttribute('aria-selected', String(selected));
            item.tabIndex = selected ? 0 : -1;
            const panel = root.getElementById(item.getAttribute('aria-controls'));
            if (panel) panel.hidden = !selected;
        });
    });
    if (active?.isConnected) { active.focus({ preventScroll: true }); if (selection) active.setSelectionRange(...selection); }
    return true;
}
