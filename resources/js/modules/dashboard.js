export function initializeDashboard(root) {
    const dashboard = root.querySelector('[data-dashboard]');
    if (!dashboard) return;
    dashboard.addEventListener('click', (event) => {
        const link = event.target.closest('a');
        if (!link || event.defaultPrevented || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        const status = dashboard.querySelector('[data-page-transition]');
        if (status) status.hidden = false;
    });
    window.addEventListener('pageshow', () => {
        const status = dashboard.querySelector('[data-page-transition]');
        if (status) status.hidden = true;
    });
}
