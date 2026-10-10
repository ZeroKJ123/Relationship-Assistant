export function initializeProductPhotos(root) {
    root.querySelectorAll('[data-product-photo]').forEach((container) => {
        const image = container.querySelector('img');
        const fallback = container.querySelector('[data-photo-fallback]');
        if (!image || !fallback) return;
        const showFallback = () => { image.hidden = true; fallback.hidden = false; };
        image.addEventListener('error', showFallback);
        if (image.complete && image.naturalWidth === 0) showFallback();
    });
}

export function initializePhotoEditors(root, selector) {
    root.querySelectorAll(selector).forEach((form) => {
        const photo = form.elements.namedItem('photo');
        const status = form.querySelector('[data-photo-status]');
        const preview = form.querySelector('[data-photo-preview]');
        const image = form.querySelector('[data-photo-image]');
        let previewUrl;
        function releasePreview() {
            if (previewUrl) URL.revokeObjectURL(previewUrl);
            previewUrl = undefined;
        }
        photo?.addEventListener('change', () => {
            releasePreview();
            const file = photo.files?.[0];
            preview.hidden = true;
            status.hidden = !file;
            photo.setCustomValidity('');
            if (!file) return;
            const maximumBytes = 4 * 1024 * 1024;
            if (file.size > maximumBytes || !['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                status.textContent = form.dataset.photoInvalid;
                photo.setCustomValidity(status.textContent);
                return;
            }
            status.textContent = form.dataset.photoChecking;
            previewUrl = URL.createObjectURL(file);
            image.src = previewUrl;
        });
        image?.addEventListener('load', () => { preview.hidden = false; status.textContent = form.dataset.photoReady; });
        image?.addEventListener('error', () => {
            preview.hidden = true;
            status.textContent = form.dataset.photoFailed;
            photo.setCustomValidity(status.textContent);
            releasePreview();
        });
        window.addEventListener('pagehide', releasePreview);
    });
}
