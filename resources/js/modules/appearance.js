export function conditionMatches(condition, state) {
    if (condition === 'always') return true;
    if (condition === 'no_partner') return !state.has_partner;
    if (condition === 'no_car') return !state.has_car;
    return state[condition] === true;
}

export function moveBuilderItem(item, direction) {
    const sibling = direction === 'up' ? item.previousElementSibling : item.nextElementSibling;
    if (!sibling) return false;
    if (direction === 'up') item.parentElement.insertBefore(item, sibling);
    if (direction === 'down') item.parentElement.insertBefore(sibling, item);
    return true;
}

export function renumberBuilderList(list) {
    [...list.children].forEach((item, index) => {
        item.querySelectorAll('[name]').forEach((field) => {
            field.name = field.name.replace(/layout\[(blocks|tiles)\]\[\d+\]/, `layout[${list.dataset.builderList}][${index}]`);
        });
    });
}

export function initializeAppearance(root) {
    root.querySelectorAll('[data-theme-editor]').forEach(bindThemeEditor);
    root.querySelectorAll('[data-builder]').forEach(bindBuilder);
}

function bindThemeEditor(form) {
    if (form.dataset.appearanceInitialized) return;
    form.dataset.appearanceInitialized = 'true';
    const preview = form.querySelector('[data-theme-preview]');
    const tokens = {primary:'violet', ink:'ink', muted:'muted', lilac:'lilac', butter:'butter', rose:'rose', background:'page-start', gradient:'page-end'};
    let imageUrl;
    const update = () => {
        form.querySelectorAll('[data-theme-color]').forEach((field) => preview.style.setProperty(`--color-${tokens[field.dataset.themeColor]}`, field.value));
        preview.style.setProperty('--page-angle', `${form.querySelector('[data-theme-angle]').value}deg`);
        preview.dataset.decoration = form.querySelector('[data-theme-decoration]').value;
        const existing = form.querySelector('.theme-upload-preview');
        const remove = form.querySelector('[name="remove_image"]')?.checked;
        const fallback = !remove && existing ? existing.getAttribute('src') : null;
        const source = imageUrl || fallback || (preview.dataset.decoration === 'clouds' ? '/images/clouds-hearts-v1.png' : null);
        preview.style.setProperty('--page-image', source ? `url("${source}")` : 'none');
    };
    form.addEventListener('input', update);
    form.querySelector('[data-theme-image]').addEventListener('change', (event) => {
        if (imageUrl) URL.revokeObjectURL(imageUrl);
        const file = event.target.files[0];
        imageUrl = file && /^image\/(png|jpeg|webp)$/.test(file.type) ? URL.createObjectURL(file) : undefined;
        update();
    });
    update();
}

function bindBuilder(form) {
    if (form.dataset.appearanceInitialized) return;
    form.dataset.appearanceInitialized = 'true';
    const frame = form.querySelector('[data-builder-preview]');
    const status = form.querySelector('[data-builder-status]');
    let drag;
    let textSequence = 0;
    const state = () => Object.fromEntries([...form.querySelectorAll('[data-preview-condition]')].map((field) => [field.dataset.previewCondition, field.checked]));
    const read = (item, prop) => {
        const field = item.querySelector(`[data-prop="${prop}"]`);
        return field?.type === 'checkbox' ? field.checked : (field?.value ?? '');
    };
    const update = () => {
        form.querySelectorAll('[data-builder-list]').forEach(renumberBuilderList);
        const doc = frame.contentDocument;
        if (!doc?.querySelector('main')) return;
        const conditions = state();
        const blockList = form.querySelector('[data-builder-list="blocks"]');
        const ids = new Set();
        [...blockList.children].forEach((item) => {
            const id = item.dataset.itemId;
            ids.add(id);
            let block = doc.querySelector(`[data-preview-block="${id}"]`);
            if (!block && read(item, 'type') === 'text') {
                block = doc.createElement('section');
                block.dataset.previewBlock = id;
                block.className = 'dashboard-block';
                const card = doc.createElement('div'); card.className = 'card section-space';
                const title = doc.createElement('h2'); title.dataset.previewTextTitle = '';
                const text = doc.createElement('p'); text.dataset.previewText = ''; text.className = 'request-description';
                card.append(title, text); block.append(card);
            }
            if (!block) return;
            block.hidden = !read(item, 'enabled') || !conditionMatches(read(item, 'condition'), conditions);
            const tone = read(item, 'tone');
            block.className = `dashboard-block${tone === 'plain' ? '' : ` card card--${tone}`}`;
            const title = block.querySelector('[data-preview-title]');
            if (title) title.textContent = read(item, 'title');
            const textTitle = block.querySelector('[data-preview-text-title]');
            if (textTitle) textTitle.textContent = read(item, 'title');
            const text = block.querySelector('[data-preview-text]');
            if (text) text.textContent = read(item, 'text');
            const empty = block.querySelector('[data-preview-empty]');
            if (empty) empty.hidden = block.querySelector('[data-preview-body]').children.length > 0;
            doc.querySelector('main').append(block);
        });
        doc.querySelectorAll('[data-preview-block]').forEach((block) => { if (!ids.has(block.dataset.previewBlock)) block.remove(); });
        const tileList = form.querySelector('[data-builder-list="tiles"]');
        [...tileList.children].forEach((item) => {
            const tile = doc.querySelector(`[data-preview-tile="${item.dataset.itemId}"]`);
            if (!tile) return;
            tile.hidden = !read(item, 'enabled') || !conditionMatches(read(item, 'condition'), conditions);
            const tone = read(item, 'tone');
            tile.className = `card${tone === 'plain' ? '' : ` card--${tone}`}`;
            const label = tile.querySelector('strong');
            if (!label.dataset.originalTitle) label.dataset.originalTitle = label.textContent;
            label.textContent = read(item, 'title') || label.dataset.originalTitle;
            tile.parentElement.append(tile);
        });
        const grid = doc.querySelector('.dashboard-tiles');
        if (grid) grid.style.setProperty('--tile-columns', form.querySelector('[data-builder-columns]').value);
        frame.dataset.width = form.querySelector('[data-preview-width]').value;
        status.textContent = 'Предпросмотр обновлён. Сохраните изменения, чтобы применить их на сайте.';
    };
    frame.addEventListener('load', () => {
        frame.contentDocument.addEventListener('click', (event) => {
            event.preventDefault();
            const target = event.target.closest('[data-preview-tile], [data-preview-block]');
            if (!target) return;
            const id = target.dataset.previewTile || target.dataset.previewBlock;
            const list = target.dataset.previewTile ? 'tiles' : 'blocks';
            const item = form.querySelector(`[data-builder-list="${list}"] [data-item-id="${id}"]`);
            form.querySelectorAll('.builder-item--selected').forEach((node) => node.classList.remove('builder-item--selected'));
            item?.classList.add('builder-item--selected');
            item?.scrollIntoView({behavior:'smooth', block:'center'});
            item?.querySelector('[data-prop="condition"]')?.focus({preventScroll:true});
        });
        update();
    });
    form.addEventListener('input', update);
    form.addEventListener('change', update);
    form.addEventListener('click', (event) => {
        const move = event.target.closest('[data-move]');
        if (move) { moveBuilderItem(move.closest('[data-builder-item]'), move.dataset.move); update(); }
        const remove = event.target.closest('[data-remove-text]');
        if (remove) { remove.closest('[data-builder-item]').remove(); update(); }
        if (event.target.closest('[data-add-text]')) {
            if (form.querySelectorAll('[data-builder-list="blocks"] > *').length >= 20) {
                status.textContent = 'Можно добавить не более 20 блоков.'; return;
            }
            const fragment = form.querySelector('[data-text-template]').content.cloneNode(true);
            const item = fragment.querySelector('[data-builder-item]');
            const id = `text-${Date.now()}-${textSequence++}`;
            item.dataset.itemId = id;
            item.querySelector('[data-prop="id"]').value = id;
            form.querySelector('[data-builder-list="blocks"]').append(fragment);
            update();
        }
    });
    form.addEventListener('pointerdown', (event) => {
        const handle = event.target.closest('[data-drag-handle]');
        if (!handle || event.button !== 0) return;
        const item = handle.closest('[data-builder-item]');
        drag = {handle, item, list:item.parentElement, original:[...item.parentElement.children], x:event.clientX, y:event.clientY};
        form.setPointerCapture(event.pointerId);
        item.classList.add('builder-item--dragging');
    });
    form.addEventListener('pointermove', (event) => {
        if (!drag || Math.abs(event.clientX-drag.x) + Math.abs(event.clientY-drag.y) < 6) return;
        event.preventDefault();
        const over = document.elementFromPoint(event.clientX,event.clientY)?.closest('[data-builder-item]');
        if (!over || over === drag.item || over.parentElement !== drag.list) return;
        const rect = over.getBoundingClientRect();
        const reference = event.clientY < rect.top + rect.height/2 ? over : over.nextSibling;
        if (reference === drag.item.nextSibling) return;
        drag.list.insertBefore(drag.item, reference);
        update();
    });
    const finish = (cancelled) => {
        if (!drag) return;
        if (cancelled) drag.original.forEach((item) => drag.list.append(item));
        drag.item.classList.remove('builder-item--dragging');
        drag = null;
        update();
    };
    form.addEventListener('pointerup', () => finish(false));
    form.addEventListener('pointercancel', () => finish(true));
    form.addEventListener('lostpointercapture', () => finish(false));
    form.addEventListener('submit', () => form.querySelectorAll('[data-builder-list]').forEach(renumberBuilderList));
    update();
}
