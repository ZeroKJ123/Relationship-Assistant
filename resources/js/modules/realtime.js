import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

import { refreshContent } from './live-content.js';

export function initializeRealtime(root, initialize) {
    const key = root.querySelector('meta[name="realtime-key"]')?.content;
    if (!key || root.body.dataset.authenticated !== 'true') return;
    let pending = false;
    let inFlight = false;
    let timer;
    let retryTimer;
    let sequence = 0;
    async function refresh() {
        if (!pending || inFlight || !navigator.onLine || root.hidden) return;
        const revision = sequence;
        inFlight = true;
        try {
            const url = window.location.href;
            const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store', headers: { 'X-Live-Refresh': 'true' }, signal: AbortSignal.timeout(10000) });
            if (window.location.href !== url) return;
            if (response.redirected) { window.location.assign(response.url); return; }
            if ([401, 403].includes(response.status)) { window.location.reload(); return; }
            if (!response.ok) return;
            const incoming = new DOMParser().parseFromString(await response.text(), 'text/html');
            if (incoming.body.dataset.authenticated !== 'true' || incoming.body.dataset.userId !== root.body.dataset.userId) { window.location.reload(); return; }
            if (refreshContent(root, incoming, initialize)) pending = sequence !== revision;
        } catch {
            console.warn('realtime.refresh_failed', { reason: 'connection_or_response' });
        } finally {
            inFlight = false;
            if (pending) retryTimer = setTimeout(refresh, 3000);
        }
    }
    function schedule() {
        pending = true;
        sequence += 1;
        clearTimeout(timer);
        clearTimeout(retryTimer);
        timer = setTimeout(refresh, 250);
    }
    const echo = new Echo({ broadcaster: 'reverb', key, client: undefined, Pusher, wsHost: window.location.hostname, wsPort: Number(root.querySelector('meta[name="realtime-port"]')?.content ?? 8089), wssPort: Number(root.querySelector('meta[name="realtime-secure-port"]')?.content ?? 443), forceTLS: window.location.protocol === 'https:', enabledTransports: ['ws', 'wss'], disableStats: true, authEndpoint: '/broadcasting/auth', auth: { headers: { 'X-CSRF-TOKEN': root.querySelector('meta[name="csrf-token"]')?.content ?? '' } } });
    echo.private('application.updates').listen('.application.updated', schedule).subscribed(schedule);
    echo.connector.pusher.connection.bind('connected', schedule);
    root.addEventListener('input', (event) => { const form = event.target.closest('form'); if (form) form.dataset.liveDirty = 'true'; });
    root.addEventListener('change', (event) => { const form = event.target.closest('form'); if (form) form.dataset.liveDirty = 'true'; });
    root.addEventListener('close', schedule, true);
    root.addEventListener('visibilitychange', () => { if (!root.hidden) schedule(); });
    window.addEventListener('online', schedule);
    window.addEventListener('pageshow', schedule);
    window.addEventListener('pagehide', () => { clearTimeout(timer); clearTimeout(retryTimer); });
}
