export function initializeDialogs(root) {
    const openers = new WeakMap();
    root.querySelectorAll('[data-open-dialog]').forEach((button) => {
        button.addEventListener('click', () => {
            const dialog = document.getElementById(button.dataset.openDialog);
            if (!(dialog instanceof HTMLDialogElement)) {
                console.warn('dialog.open_failed', { reason: 'missing_dialog' });
                return;
            }
            openers.set(dialog, button);
            dialog.showModal();
        });
    });
    root.querySelectorAll('dialog').forEach((dialog) => {
        dialog.querySelectorAll('[data-close-dialog]').forEach((button) => {
            button.addEventListener('click', () => dialog.close());
        });
        dialog.querySelectorAll('[data-confirm-form]').forEach((button) => {
            button.addEventListener('click', () => {
                const form = document.getElementById(button.dataset.confirmForm);
                if (!(form instanceof HTMLFormElement) || button.disabled || !form.reportValidity()) return;
                button.disabled = true;
                form.requestSubmit();
            });
        });
        dialog.addEventListener('close', () => {
            dialog.querySelectorAll('[data-confirm-form]').forEach((button) => { button.disabled = false; });
            openers.get(dialog)?.focus();
        });
    });
}

export function initializeTabs(root) {
    root.querySelectorAll('[data-tabs]').forEach((group) => {
        const tabs = [...group.querySelectorAll('[role="tab"]')];
        function selectTab(selected) {
            tabs.forEach((tab) => {
                const isSelected = tab === selected;
                tab.setAttribute('aria-selected', String(isSelected));
                tab.tabIndex = isSelected ? 0 : -1;
                const panel = document.getElementById(tab.getAttribute('aria-controls'));
                if (panel) panel.hidden = !isSelected;
            });
            selected.focus();
        }
        tabs.forEach((tab, index) => {
            tab.addEventListener('click', () => selectTab(tab));
            tab.addEventListener('keydown', (event) => {
                let next;
                if (event.key === 'ArrowRight') next = tabs[(index + 1) % tabs.length];
                if (event.key === 'ArrowLeft') next = tabs[(index - 1 + tabs.length) % tabs.length];
                if (event.key === 'Home') next = tabs[0];
                if (event.key === 'End') next = tabs[tabs.length - 1];
                if (!next) return;
                event.preventDefault();
                selectTab(next);
            });
        });
    });
}
