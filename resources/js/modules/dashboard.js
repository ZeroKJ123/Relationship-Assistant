export function initializeDashboard(root) {
    const dashboard = root.querySelector('[data-dashboard]');
    if (!dashboard || dashboard.dataset.initialized) return;
    dashboard.dataset.initialized = 'true';
    dashboard.addEventListener('click', (event) => {
        const link = event.target.closest('a');
        if (!link || event.defaultPrevented || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        const status = dashboard.querySelector('[data-page-transition]');
        if (status) status.hidden = false;
    });
    if (!root.documentElement.dataset.dashboardPageshow) {
        root.documentElement.dataset.dashboardPageshow = 'true';
        window.addEventListener('pageshow', () => {
            const status = root.querySelector('[data-page-transition]');
            if (status) status.hidden = true;
        });
    }
}
