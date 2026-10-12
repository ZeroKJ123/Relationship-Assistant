const photoLifecycles = new Set();

export function initializeProductPhotos(root) {
    for (const item of photoLifecycles) { if (!item.form.isConnected) { item.release(); item.controller.abort(); photoLifecycles.delete(item); } }
    root.querySelectorAll('[data-product-photo]').forEach((container) => {
        if (container.dataset.photosInit1) return;
        container.dataset.photosInit1 = 'true';
        const image = container.querySelector('img');
        const fallback = container.querySelector('[data-photo-fallback]');
        if (!image || !fallback) return;
        const showFallback = () => { image.hidden = true; fallback.hidden = false; const link = image.closest('[data-photo-open]'); if (link) link.hidden = true; };
        image.addEventListener('error', showFallback);
        if (image.complete && image.naturalWidth === 0) showFallback();
    });
}

let viewer;

/** Одно окно просмотра на страницу: создаётся при первом открытии фото. */
function getPhotoViewer(closeLabel) {
    if (viewer?.isConnected) return viewer;
    viewer = document.createElement('dialog');
    viewer.className = 'photo-viewer';
    const image = document.createElement('img');
    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'photo-viewer__close';
    close.textContent = '×';
    close.setAttribute('aria-label', closeLabel);
    viewer.append(image, close);
    viewer.addEventListener('click', (event) => { if (event.target !== image) viewer.close(); });
    document.body.append(viewer);

    return viewer;
}

export function initializePhotoViewer(root) {
    if (typeof HTMLDialogElement === 'undefined') return;
    root.querySelectorAll('[data-photo-open]').forEach((link) => {
        if (link.dataset.photosInit3) return;
        link.dataset.photosInit3 = 'true';
        link.addEventListener('click', (event) => {
            if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            event.preventDefault();
            const dialog = getPhotoViewer(link.dataset.photoCloseLabel ?? '');
            const image = dialog.querySelector('img');
            image.src = link.href;
            image.alt = link.querySelector('img')?.alt ?? '';
            dialog.showModal();
        });
    });
}

export function initializePhotoEditors(root, selector) {
    root.querySelectorAll(selector).forEach((form) => {
        if (form.dataset.photosInit2) return;
        form.dataset.photosInit2 = 'true';
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
        const controller = new AbortController();
        photoLifecycles.add({ form, controller, release: releasePreview });
        window.addEventListener('pagehide', releasePreview, { signal: controller.signal });
    });
}
