// Kudos Design Ops - Global Dirty Guard Manager

window.KudosDirtyGuard = {
    dirtyRegistry: new Map(),
    isConfirmModalOpen: false,

    register(id, isDirtyFn) {
        this.dirtyRegistry.set(id, isDirtyFn);
        this.updateGlobalState();
    },

    unregister(id) {
        this.dirtyRegistry.delete(id);
        this.updateGlobalState();
    },

    isDirty() {
        for (const [id, checkFn] of this.dirtyRegistry.entries()) {
            try {
                const result = typeof checkFn === 'function' ? checkFn() : Boolean(checkFn);
                if (result) return true;
            } catch (e) {
                // Ignore stale checks
            }
        }
        return false;
    },

    updateGlobalState() {
        window.__hasUnsavedChanges = this.isDirty();
    },

    confirmIfDirty(actionCallback, options = {}) {
        if (this.isConfirmModalOpen) return;
        if (this.isDirty()) {
            this.openConfirmModal({
                title: options.title || '¿Descartar cambios sin guardar?',
                description: options.description || 'Tienes información o cambios editados sin guardar. Si sales ahora, estos cambios se perderán.',
                confirmText: options.confirmText || 'Sí, descartar cambios',
                cancelText: options.cancelText || 'Continuar editando',
                onConfirm: () => {
                    this.dirtyRegistry.clear();
                    this.updateGlobalState();
                    actionCallback();
                }
            });
        } else {
            actionCallback();
        }
    },

    confirmCheck(checkId, actionCallback, options = {}) {
        if (this.isConfirmModalOpen) return;
        const checkFn = this.dirtyRegistry.get(checkId);
        let isCheckDirty = false;
        if (checkFn) {
            try {
                isCheckDirty = typeof checkFn === 'function' ? checkFn() : Boolean(checkFn);
            } catch (e) {
                isCheckDirty = false;
            }
        }

        if (isCheckDirty) {
            this.openConfirmModal({
                title: options.title || '¿Descartar cambios sin guardar?',
                description: options.description || 'Tienes información editada sin guardar. Si sales ahora, se perderán los datos modificados.',
                confirmText: options.confirmText || 'Sí, descartar cambios',
                cancelText: options.cancelText || 'Continuar editando',
                onConfirm: () => {
                    this.unregister(checkId);
                    actionCallback();
                }
            });
        } else {
            actionCallback();
        }
    },

    openConfirmModal(modalOptions) {
        this.isConfirmModalOpen = true;
        window.dispatchEvent(new CustomEvent('open-dirty-confirm-modal', { detail: modalOptions }));
    },

    closeConfirmModal() {
        this.isConfirmModalOpen = false;
    }
};


// Global beforeunload warning for page refresh or tab close
window.addEventListener('beforeunload', (event) => {
    if (window.KudosDirtyGuard.isDirty()) {
        event.preventDefault();
        event.returnValue = 'Tienes cambios sin guardar.';
        return 'Tienes cambios sin guardar.';
    }
});

// Intercept internal link navigation if unsaved changes exist
document.addEventListener('click', (event) => {
    const link = event.target.closest('a[href]');
    if (!link) return;

    const href = link.getAttribute('href');
    if (!href || href.startsWith('#') || href.startsWith('javascript:') || link.getAttribute('target') === '_blank') {
        return;
    }

    if (window.KudosDirtyGuard && window.KudosDirtyGuard.isDirty()) {
        event.preventDefault();
        event.stopPropagation();
        window.KudosDirtyGuard.confirmIfDirty(() => {
            window.location.href = href;
        });
    }
}, true);

// Kudos Design Ops - Global Dropdown Navigation Manager
window.KudosDropdownNav = {
    handleKeydown(event, container, openSetter) {
        const key = event.key;
        if (!['ArrowDown', 'ArrowUp', 'Escape', 'Enter'].includes(key)) return;

        // Find the dropdown menu panel inside the container
        const panel = container.querySelector('[data-dropdown-panel]') || 
                      container.querySelector('[x-show]') ||
                      container.querySelector('.absolute');
        if (!panel) return;

        const getItems = () => {
            const elements = Array.from(panel.querySelectorAll('button, a, input[type="text"], [tabindex="0"], [role="option"]'));
            return elements.filter(el => {
                if (el.offsetParent === null && el.tagName !== 'BODY') return false;
                if (el.disabled) return false;
                const style = window.getComputedStyle(el);
                if (style.display === 'none' || style.visibility === 'hidden') return false;
                return true;
            });
        };

        const activeEl = document.activeElement;
        const trigger = container.querySelector('button, input') || container;

        if (key === 'Escape') {
            event.preventDefault();
            if (typeof openSetter === 'function') openSetter(false);
            if (trigger && typeof trigger.focus === 'function') trigger.focus();
            return;
        }

        const items = getItems();
        if (items.length === 0) return;

        const currentIndex = items.indexOf(activeEl);

        if (key === 'ArrowDown') {
            event.preventDefault();
            if (typeof openSetter === 'function') openSetter(true);

            let nextIndex = 0;
            if (currentIndex >= 0 && currentIndex < items.length - 1) {
                nextIndex = currentIndex + 1;
            } else if (currentIndex === items.length - 1) {
                nextIndex = 0;
            }

            const target = items[nextIndex];
            if (target) {
                target.focus();
                if (typeof target.scrollIntoView === 'function') {
                    target.scrollIntoView({ block: 'nearest' });
                }
            }
        } else if (key === 'ArrowUp') {
            event.preventDefault();
            let prevIndex = items.length - 1;
            if (currentIndex > 0) {
                prevIndex = currentIndex - 1;
            } else if (currentIndex === 0) {
                if (trigger && activeEl !== trigger && typeof trigger.focus === 'function') {
                    trigger.focus();
                    return;
                }
                prevIndex = items.length - 1;
            }

            const target = items[prevIndex];
            if (target) {
                target.focus();
                if (typeof target.scrollIntoView === 'function') {
                    target.scrollIntoView({ block: 'nearest' });
                }
            }
        } else if (key === 'Enter' && activeEl && activeEl !== trigger) {
            if (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA') return;
            event.preventDefault();
            activeEl.click();
        }
    }
};

const registerAlpineDropdown = () => {
    if (window.Alpine) {
        window.Alpine.directive('dropdown-nav', (el, { expression }, { evaluate }) => {
            const varName = expression || 'open';
            el.addEventListener('keydown', (e) => {
                if (['ArrowDown', 'ArrowUp', 'Escape', 'Enter'].includes(e.key)) {
                    const openSetter = (val) => {
                        try {
                            evaluate(`${varName} = ${val}`);
                        } catch (err) {
                            // Ignore if variable isn't present
                        }
                    };
                    window.KudosDropdownNav.handleKeydown(e, el, openSetter);
                }
            });
        });
    }
};

if (window.Alpine) {
    registerAlpineDropdown();
} else {
    document.addEventListener('alpine:init', registerAlpineDropdown);
}


