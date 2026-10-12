import { refreshContent } from './live-content.js';

const tasks = new Map();
let clockOffset;
export function getServerTime(fallback) {
    return clockOffset === undefined ? fallback : Date.now() + clockOffset;
}
export function getSpinDelay(start, serverTime) {
    return (start - serverTime) / 1000;
}

export function initializeSelectionPresence(root, initialize) {
    for (const [node, controller] of tasks) {
        if (!node.isConnected) { controller.abort(); tasks.delete(node); }
    }
    root.querySelectorAll('[data-selection-presence]').forEach((node) => {
        if (tasks.has(node)) return;
        const controller = new AbortController();
        tasks.set(node, controller);
        let timer;
        const tick = async () => {
            if (!node.isConnected || controller.signal.aborted) return;
            try {
                if (root.hidden || !navigator.onLine) return;
                const sentAt = Date.now();
                const response = await fetch(node.dataset.selectionPresence, { method: 'POST', credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': root.querySelector('meta[name="csrf-token"]').content }, signal: AbortSignal.any([controller.signal, AbortSignal.timeout(5000)]) });
                if (!response.ok) { node.textContent = 'Не удалось проверить присутствие. Проверьте соединение.'; return; }
                const data = await response.json();
                clockOffset = data.server_time - (sentAt + Date.now()) / 2;
                node.textContent = 'Ожидание партнёра для прокрутки рулетки. Оба должны согласиться и открыть эту страницу.';
                if (data.status === node.dataset.selectionStatus) return;
                const page = await fetch(window.location.href, { credentials: 'same-origin', cache: 'no-store', signal: AbortSignal.any([controller.signal, AbortSignal.timeout(5000)]) });
                if (!page.ok || page.redirected) return;
                const incoming = new DOMParser().parseFromString(await page.text(), 'text/html');
                refreshContent(root, incoming, initialize);
            } catch (error) {
                if (!controller.signal.aborted) node.textContent = 'Соединение прервалось. Ожидаем восстановления.';
            } finally {
                if (!controller.signal.aborted) timer = setTimeout(tick, 1500);
            }
        };
        controller.signal.addEventListener('abort', () => clearTimeout(timer));
        tick();
    });
}
