import { initializePhotoEditors } from './photos.js';

export function initializeDishes(root) {
    initializePhotoEditors(root, '[data-dish-editor]');
    root.querySelectorAll('[data-dish-editor]').forEach((form) => {
        if (form.dataset.dishesInit1) return;
        form.dataset.dishesInit1 = 'true';
        const kind = form.elements.namedItem('kind');
        const price = form.elements.namedItem('heart_price');
        const priceGroup = form.querySelector('[data-dish-price]');
        const note = form.querySelector('[data-dish-free-note]');
        if (!kind || !price || !priceGroup || !note) return;
        function updatePrice() {
            const ordinary = kind.value === 'ordinary';
            if (ordinary) price.value = '0';
            price.readOnly = ordinary;
            priceGroup.hidden = ordinary;
            note.hidden = !ordinary;
        }
        kind.addEventListener('change', updatePrice);
        updatePrice();

    });
}
