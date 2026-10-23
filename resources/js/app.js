import './qr-card.js';

// Kudos Design Ops - Global Dirty Guard Manager

window.KudosDirtyGuard = {
    dirtyRegistry: new Map(),
    isConfirmModalOpen: false,

    register(id, isDirtyFn, element = null) {
        this.dirtyRegistry.set(id, { checkFn: isDirtyFn, el: element });
        this.updateGlobalState();
    },

    unregister(id) {
        this.dirtyRegistry.delete(id);
        this.updateGlobalState();
    },

    isDirty() {
        for (const [id, entry] of this.dirtyRegistry.entries()) {
            try {
                const checkFn = typeof entry === 'function' ? entry : entry?.checkFn;
                const el = typeof entry === 'object' ? entry?.el : null;

                // Auto-cleanup if registered DOM element is no longer attached to document
                if (el && !document.body.contains(el)) {
                    this.dirtyRegistry.delete(id);
                    continue;
                }

                const result = typeof checkFn === 'function' ? checkFn() : Boolean(checkFn);
                if (result) return true;
            } catch (e) {
                // Delete stale checks
                this.dirtyRegistry.delete(id);
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
        const entry = this.dirtyRegistry.get(checkId);
        const checkFn = typeof entry === 'function' ? entry : entry?.checkFn;
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

// Kudos Design Ops - Global Modal & Flyout Stack Layering Manager
window.KudosModalStack = {
    stack: [],
    baseZIndex: 300,

    /**
     * Register or bring a modal/flyout to the front of the stack
     * @param {string} id
     * @param {HTMLElement|string|null} elementOrSelector
     * @param {Function|null} closeCallback
     */
    register(id, elementOrSelector = null, closeCallback = null) {
        if (!id) return;

        const existing = this.stack.find(item => item.id === id);
        this.stack = this.stack.filter(item => item.id !== id);

        let selector = null;
        let el = null;

        if (typeof elementOrSelector === 'string') {
            selector = elementOrSelector;
            el = document.querySelector(selector);
        } else if (elementOrSelector instanceof HTMLElement) {
            el = elementOrSelector;
            if (el.id) {
                selector = '#' + el.id;
            } else if (el.dataset && el.dataset.modal) {
                selector = `[data-modal="${el.dataset.modal}"]`;
            }
        }

        const entry = {
            id,
            selector: selector || existing?.selector || null,
            el: el || existing?.el || null,
            close: closeCallback || existing?.close || null
        };

        this.stack.push(entry);
        this.recalculateZIndices();
        return this.getZIndex(id);
    },

    /**
     * Unregister a modal/flyout when closed
     * @param {string} id
     */
    unregister(id) {
        if (!id) return;
        this.stack = this.stack.filter(item => item.id !== id);
        this.recalculateZIndices();
    },

    /**
     * Bring an active modal/flyout to the front (highest z-index)
     * @param {string} id
     */
    bringToFront(id) {
        if (!id) return;
        const index = this.stack.findIndex(item => item.id === id);
        if (index !== -1) {
            const [item] = this.stack.splice(index, 1);
            this.stack.push(item);
            this.recalculateZIndices();
        }
    },

    /**
     * Recompute z-indices so the topmost item is always in front
     */
    recalculateZIndices() {
        // Prune any elements no longer attached to DOM
        this.stack = this.stack.filter(item => {
            if (item.selector) {
                const found = document.querySelector(item.selector);
                if (found) {
                    item.el = found;
                    return true;
                }
            }
            if (item.el && !document.body.contains(item.el)) {
                return false;
            }
            return true;
        });

        this.stack.forEach((item, index) => {
            const z = this.baseZIndex + (index * 20);
            const el = (item.selector ? document.querySelector(item.selector) : null) || item.el;
            if (el) {
                item.el = el;
                el.style.setProperty('z-index', z.toString(), 'important');
            }
        });
    },

    getZIndex(id) {
        const index = this.stack.findIndex(item => item.id === id);
        if (index === -1) return this.baseZIndex;
        return this.baseZIndex + (index * 20);
    },

    getTop() {
        return this.stack.length > 0 ? this.stack[this.stack.length - 1] : null;
    },

    isTop(id) {
        const top = this.getTop();
        return top && top.id === id;
    },

    handleEscape() {
        if (window.KudosDirtyGuard && window.KudosDirtyGuard.isConfirmModalOpen) {
            return false;
        }

        const top = this.getTop();
        if (top && typeof top.close === 'function') {
            top.close();
            return true;
        }
        return false;
    }
};

// Global Escape listener: intercepts Escape key and closes ONLY the topmost modal/flyout
window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' || e.key === 'Esc') {
        if (window.KudosDirtyGuard && window.KudosDirtyGuard.isConfirmModalOpen) {
            return;
        }
        if (window.KudosModalStack && window.KudosModalStack.stack.length > 0) {
            const handled = window.KudosModalStack.handleEscape();
            if (handled) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
            }
        }
    }
}, true);

// Kudos Design Ops - Global Loading Overlay Manager with Debounce and Zero-lag Animation
let lastUserInteractionTime = 0;
const registerUserInteraction = () => {
    lastUserInteractionTime = Date.now();
};
window.addEventListener('pointerdown', registerUserInteraction, { capture: true, passive: true });
window.addEventListener('keydown', registerUserInteraction, { capture: true, passive: true });
window.addEventListener('drop', registerUserInteraction, { capture: true, passive: true });

function isUserTyping() {
    const active = document.activeElement;
    if (!active) return false;
    return ['INPUT', 'TEXTAREA', 'SELECT'].includes(active.tagName) || active.isContentEditable;
}

function isBackgroundCommit(commit) {
    const calls = commit?.calls || [];
    // If every method called is $refresh or empty, it's a wire:poll or background refresh
    if (calls.length > 0 && calls.every(c => c.method === '$refresh')) {
        return true;
    }
    // If no calls and no updates (heartbeat/passive commit)
    if (calls.length === 0 && Object.keys(commit?.updates || {}).length === 0) {
        return true;
    }
    return false;
}

const activeCommits = new Set();

window.KudosLoader = {
    timer: null,
    safetyTimer: null,
    element: null,

    getEl() {
        if (!this.element || !document.body.contains(this.element)) {
            this.element = document.getElementById('kudos-global-loader');
        }
        return this.element;
    },

    show() {
        const el = this.getEl();
        if (!el) return;

        el.classList.remove('opacity-0', 'pointer-events-none', 'invisible');
        el.classList.add('opacity-100', 'pointer-events-auto');

        const card = el.querySelector('[data-loader-card]');
        if (card) {
            card.classList.remove('scale-95');
            card.classList.add('scale-100');
        }
    },

    hide() {
        clearTimeout(this.timer);
        this.timer = null;
        clearTimeout(this.safetyTimer);
        this.safetyTimer = null;
        activeCommits.clear();

        const el = this.getEl();
        if (!el) return;

        el.classList.remove('opacity-100', 'pointer-events-auto');
        el.classList.add('opacity-0', 'pointer-events-none');

        const card = el.querySelector('[data-loader-card]');
        if (card) {
            card.classList.remove('scale-100');
            card.classList.add('scale-95');
        }

        setTimeout(() => {
            if (activeCommits.size === 0 && el.classList.contains('opacity-0')) {
                el.classList.add('invisible');
            }
        }, 150);
    },

    syncState(debounceMs = 120) {
        if (activeCommits.size === 0) {
            this.hide();
            return;
        }

        if (isUserTyping()) {
            return;
        }

        if (!this.timer && !this.isVisible()) {
            this.timer = setTimeout(() => {
                if (activeCommits.size > 0) {
                    this.show();
                }
            }, debounceMs);

            clearTimeout(this.safetyTimer);
            this.safetyTimer = setTimeout(() => {
                this.hide();
            }, 8000);
        }
    },

    isVisible() {
        const el = this.getEl();
        return el && el.classList.contains('opacity-100');
    }
};

window.addEventListener('kudos:loading-start', () => {
    const id = Symbol('manual');
    activeCommits.add(id);
    window.KudosLoader.syncState(0);
});

window.addEventListener('kudos:loading-stop', () => {
    window.KudosLoader.hide();
});

// Update user interaction timestamp when opening an order detail modal
window.addEventListener('open-order-detail', () => {
    lastUserInteractionTime = Date.now();
});

// Listen to Livewire lifecycle hooks to re-apply z-indexes after Livewire DOM morphs and manage global loading
const attachLivewireModalHooks = () => {
    if (!window.Livewire) return;

    if (typeof window.Livewire.hook === 'function') {
        window.Livewire.hook('commit', ({ commit, succeed, fail }) => {
            // 1. Ignore background polling (wire:poll) and $refresh completely
            if (isBackgroundCommit(commit)) {
                return;
            }

            // 2. Must be tied to a recent human interaction (within 1.5s of click, keydown, or drop)
            const timeSinceInteraction = Date.now() - lastUserInteractionTime;
            if (timeSinceInteraction > 1500) {
                return;
            }

            // 3. Ignore if user is actively typing in an input
            if (isUserTyping()) {
                return;
            }

            // Valid user-triggered action: track this commit
            const commitId = Symbol('commit');
            activeCommits.add(commitId);
            window.KudosLoader.syncState(100);

            const complete = () => {
                activeCommits.delete(commitId);
                window.KudosLoader.syncState();
            };

            if (typeof succeed === 'function') {
                succeed(() => {
                    complete();
                    window.KudosModalStack?.recalculateZIndices();
                });
            }

            if (typeof fail === 'function') {
                fail(complete);
            }
        });

        window.Livewire.hook('morph.updated', () => {
            window.KudosModalStack?.recalculateZIndices();
        });
    }

    document.addEventListener('livewire:navigating', () => {
        const navId = Symbol('nav');
        activeCommits.add(navId);
        window.KudosLoader.syncState(100);
    });

    document.addEventListener('livewire:navigated', () => {
        window.KudosLoader.hide();
    });
};

if (window.Livewire) {
    attachLivewireModalHooks();
} else {
    document.addEventListener('livewire:init', attachLivewireModalHooks);
}



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
            if (link.hasAttribute('wire:navigate') && window.Livewire && typeof window.Livewire.navigate === 'function') {
                window.Livewire.navigate(href);
            } else {
                window.location.href = href;
            }
        });
    }
}, true);

// Reset guards and recalculate overlays on wire:navigate page transitions
document.addEventListener('livewire:navigated', () => {
    if (window.KudosDirtyGuard) {
        window.KudosDirtyGuard.dirtyRegistry.clear();
        window.KudosDirtyGuard.updateGlobalState();
    }
    if (window.KudosModalStack) {
        window.KudosModalStack.recalculateZIndices();
    }
});

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
        // Alpine v3 has no built-in $cleanup magic; expose the element-bound cleanup utility
        // so callbacks run when the component's root element is removed from the DOM.
        window.Alpine.magic('cleanup', (el, { cleanup }) => (callback) => cleanup(callback));

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

window.cleanWoNumber = function(val) {
    if (!val) return '';
    let str = String(val).trim();
    return str.replace(/^(wo|#)[\s#\-:]*/i, '').trim() || str;
};

window.copyWoToClipboard = function(rawWoNumber, event = null) {
    if (event) {
        if (typeof event.stopPropagation === 'function') event.stopPropagation();
        if (typeof event.preventDefault === 'function') event.preventDefault();
    }
    
    const numberToCopy = window.cleanWoNumber(rawWoNumber);
    if (!numberToCopy) return;

    const doCopy = () => {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(numberToCopy);
        } else {
            return new Promise((resolve, reject) => {
                try {
                    const ta = document.createElement('textarea');
                    ta.value = numberToCopy;
                    ta.style.position = 'fixed';
                    ta.style.opacity = '0';
                    document.body.appendChild(ta);
                    ta.select();
                    document.execCommand('copy');
                    document.body.removeChild(ta);
                    resolve();
                } catch (err) {
                    reject(err);
                }
            });
        }
    };

    doCopy().then(() => {
        window.dispatchEvent(new CustomEvent('toast', {
            detail: {
                message: `WO #${numberToCopy} copiado al portapapeles`,
                type: 'success'
            }
        }));
    }).catch(err => {
        console.error('Error al copiar WO:', err);
    });
};

// Kudos Design Ops - Browser & Audio Notification Manager
window.KudosNotifier = {
    audioCtx: null,
    seenIds: new Set(),

    getSoundEnabled() {
        return localStorage.getItem('kudos_sound_enabled') !== 'false';
    },

    setSoundEnabled(enabled) {
        localStorage.setItem('kudos_sound_enabled', enabled ? 'true' : 'false');
        window.dispatchEvent(new CustomEvent('kudos-sound-preference-changed', { detail: { enabled } }));
    },

    getPermissionState() {
        if (!('Notification' in window)) return 'unsupported';
        return Notification.permission; // 'default', 'granted', 'denied'
    },

    async requestPermission() {
        if (!('Notification' in window)) {
            return 'unsupported';
        }
        try {
            const permission = await Notification.requestPermission();
            if (permission === 'granted') {
                this.playChime();
                this.showSystemNotification({
                    title: '🔔 Kudos DOES',
                    body: '¡Notificaciones del sistema y sonido activados correctamente!',
                });
            }
            window.dispatchEvent(new CustomEvent('kudos-notification-permission-changed', { detail: { permission } }));
            return permission;
        } catch (err) {
            console.error('Error requesting notification permission:', err);
            return 'denied';
        }
    },

    audioElement: null,

    playChime() {
        if (!this.getSoundEnabled()) return;

        // 1. Try playing custom audio file (/sounds/notification.mp3) if available
        try {
            if (!this.audioElement) {
                this.audioElement = new Audio('/sounds/notification.mp3');
            }
            this.audioElement.currentTime = 0;
            const playPromise = this.audioElement.play();
            if (playPromise !== undefined) {
                playPromise.catch(() => {
                    // If custom audio file fails to load or 404s, fall back to synthesized chime
                    this.playSynthesizedChime();
                });
                return;
            }
        } catch (e) {
            // Fall back to synthesized chime
        }

        this.playSynthesizedChime();
    },

    playSynthesizedChime() {
        try {
            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
            if (!AudioContextClass) return;

            if (!this.audioCtx) {
                this.audioCtx = new AudioContextClass();
            }

            if (this.audioCtx.state === 'suspended') {
                this.audioCtx.resume();
            }

            const ctx = this.audioCtx;
            const now = ctx.currentTime;

            // Tone 1: D5 (587.33 Hz) - Crisp initial ping
            const osc1 = ctx.createOscillator();
            const gain1 = ctx.createGain();
            osc1.type = 'sine';
            osc1.frequency.setValueAtTime(587.33, now);
            gain1.gain.setValueAtTime(0.12, now);
            gain1.gain.exponentialRampToValueAtTime(0.0001, now + 0.35);
            osc1.connect(gain1);
            gain1.connect(ctx.destination);
            osc1.start(now);
            osc1.stop(now + 0.35);

            // Tone 2: A5 (880 Hz) - Pleasant harmonic finish, slightly delayed
            const osc2 = ctx.createOscillator();
            const gain2 = ctx.createGain();
            osc2.type = 'sine';
            osc2.frequency.setValueAtTime(880, now + 0.08);
            gain2.gain.setValueAtTime(0.15, now + 0.08);
            gain2.gain.exponentialRampToValueAtTime(0.0001, now + 0.55);
            osc2.connect(gain2);
            gain2.connect(ctx.destination);
            osc2.start(now + 0.08);
            osc2.stop(now + 0.55);
        } catch (e) {
            console.warn('Could not play notification chime:', e);
        }
    },

    showSystemNotification({ id, title, body, orderId, isUrgent }) {
        if (!('Notification' in window) || Notification.permission !== 'granted') {
            return;
        }

        try {
            const options = {
                body: body || '',
                icon: '/favicon-192x192.png',
                badge: '/favicon-32x32.png',
                tag: id ? `kudos-${id}` : `kudos-${Date.now()}`,
                renotify: true,
                silent: true, // Audio handled by playChime for consistency
            };

            const notif = new Notification(title || 'Kudos DOES', options);

            notif.onclick = () => {
                window.focus();
                if (orderId && window.Livewire) {
                    window.Livewire.dispatch('open-order-detail', { orderId: orderId });
                }
                notif.close();
            };
        } catch (e) {
            console.error('Error creating Notification:', e);
        }
    },

    handleIncomingNotification(detail) {
        if (!detail) return;
        const id = detail.id ? String(detail.id) : null;

        // Dedup: avoid duplicate alerts within same session / multi-tabs
        if (id) {
            if (this.seenIds.has(id)) return;
            this.seenIds.add(id);

            const lastNotified = localStorage.getItem('kudos_last_notified_id');
            const lastNotifiedAt = Number(localStorage.getItem('kudos_last_notified_at') || 0);
            if (lastNotified === id && (Date.now() - lastNotifiedAt < 10000)) {
                return; // Another tab handled this notification within 10s
            }
            localStorage.setItem('kudos_last_notified_id', id);
            localStorage.setItem('kudos_last_notified_at', String(Date.now()));
        }

        // 1. Play sound
        this.playChime();

        // 2. Display OS Notification if permitted
        if ('Notification' in window && Notification.permission === 'granted') {
            this.showSystemNotification(detail);
        }
    }
};

// Global event listener for Livewire dispatched 'desktop-notification'
window.addEventListener('desktop-notification', (event) => {
    const detail = event.detail?.[0] || event.detail;
    if (window.KudosNotifier) {
        window.KudosNotifier.handleIncomingNotification(detail);
    }
});

