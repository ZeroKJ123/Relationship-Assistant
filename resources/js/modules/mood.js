import lottie from 'lottie-web/build/player/lottie_light.min.js';

import { bindMoodRequest } from './mood-request.js';
import { showToast } from './toast.js';

const animations = new Set();

export function initializeMood(root) {
    for (const item of animations) {
        if (!item.container.isConnected) { item.animation.destroy(); animations.delete(item); }
    }
    root.querySelectorAll('[data-mood-request]').forEach((form) => {
        if (form.dataset.moodInitialized) return;
        form.dataset.moodInitialized = 'true';
        bindMoodRequest(form, async (url) => {
            if (form.dataset.sending || !navigator.onLine) return;
            form.dataset.sending = 'true';
            const button = form.querySelector('button');
            button.disabled = true;
            try {
                const response = await fetch(url, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: AbortSignal.timeout(10000) });
                const data = await response.json();
                if (!response.ok) throw new Error(data.message ?? 'Не удалось отправить запрос');
                showToast(data.message);
            } catch {
                showToast('Не удалось отправить запрос. Проверьте соединение.');
            } finally {
                delete form.dataset.sending;
                button.disabled = false;
            }
        });
    });
    root.querySelectorAll('[data-mood-animation]').forEach(async (container) => {
        if (container.dataset.animationInitialized) return;
        container.dataset.animationInitialized = 'true';
        try {
            const response = await fetch(container.dataset.moodAnimation, { credentials: 'same-origin', cache: 'no-store' });
            if (!response.ok) throw new Error('Sticker unavailable');
            if (!container.isConnected) return;
            const animationData = await response.json();
            if (!container.isConnected) return;
            const animation = lottie.loadAnimation({ container, renderer: 'svg', loop: true, autoplay: !window.matchMedia('(prefers-reduced-motion: reduce)').matches, animationData, rendererSettings: { progressiveLoad: true } });
            animations.add({ container, animation });
        } catch {
            container.textContent = 'Стикер недоступен';
        }
    });
    root.querySelectorAll('[data-request-topic]').forEach((topics) => {
        if (topics.dataset.topicInitialized) return;
        topics.dataset.topicInitialized = 'true';
        topics.addEventListener('change', (event) => {
            const choice = event.target;
            if (topics.closest('[data-request-editor]') && ['service','gift','food','rating'].includes(choice.value)) return;
            const destination = choice.dataset?.url;
            if (destination) window.location.assign(destination);
        });
    });
    const toast = root.querySelector('[data-toast]');
    if (toast?.dataset.initialMessage) {
        showToast(toast.dataset.initialMessage, root);
        delete toast.dataset.initialMessage;
    }
}
