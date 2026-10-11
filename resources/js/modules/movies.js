export function initializeMovies(root) {
    root.querySelectorAll('[data-movie-swipe]').forEach((card) => {
        if (card.dataset.moviesInit1) return;
        card.dataset.moviesInit1 = 'true';
        let start = null;
        card.addEventListener('pointerdown', (event) => {
            if (!event.isPrimary || event.target.closest('button,input,a,label')) return;
            start = { x: event.clientX, y: event.clientY, id: event.pointerId };
            card.setPointerCapture(event.pointerId);
        });
        card.addEventListener('pointercancel', () => { start = null; });
        card.addEventListener('pointerup', (event) => {
            if (!start || start.id !== event.pointerId) return;
            const dx = event.clientX - start.x;
            const dy = event.clientY - start.y;
            start = null;
            const swipeThreshold = 70;
            if (Math.abs(dx) < swipeThreshold || Math.abs(dx) <= Math.abs(dy)) return;
            const button = card.querySelector(dx > 0 ? '[data-movie-like]' : '[data-movie-skip]');
            if (!button || button.disabled || navigator.onLine === false) return;
            button.form.requestSubmit(button);
        });
    });
}
