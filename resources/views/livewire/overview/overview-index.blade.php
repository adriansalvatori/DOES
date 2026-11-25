@php
    $toneClasses = [
        'danger' => 'bg-danger-soft text-danger font-medium',
        'warn' => 'bg-warn-soft text-warn font-medium',
        'info' => 'bg-info-soft text-info font-medium',
        'neutral' => 'text-ink-2',
    ];
    $allGlobals = $substatuses->filter(function ($item) {
        $name = $item instanceof \App\Models\Substatus ? $item->name : ($item instanceof \App\Enums\Substatus ? $item->value : (string) $item);
        if (in_array($name, ['OVERDUE', 'ALMOST OVERDUE'], true)) {
            return false;
        }
        if ($item instanceof \App\Models\Substatus) {
            return (bool) $item->is_global;
        }
        $enum = \App\Enums\Substatus::tryFrom($name);
        return $enum ? $enum->isGlobal() : false;
    });

    $coreEnums = collect(\App\Enums\Substatus::cases())->filter(fn($e) => $e->isGlobal() && !in_array($e->value, ['OVERDUE', 'ALMOST OVERDUE'], true));
    foreach ($coreEnums as $coreEnum) {
        $exists = $allGlobals->contains(function ($item) use ($coreEnum) {
            $name = $item instanceof \App\Models\Substatus ? $item->name : ($item instanceof \App\Enums\Substatus ? $item->value : (string) $item);
            return $name === $coreEnum->value;
        });
        if (!$exists) {
            $allGlobals->push($coreEnum);
        }
    }

    $preferredOrder = ['URGENTE' => 1, 'TICKET' => 2, 'POTENTIAL CUSTOMER' => 3, 'EXTERNO' => 4];
    $globalFlagsList = $allGlobals->sortBy(function ($item) use ($preferredOrder) {
        $name = $item instanceof \App\Models\Substatus ? $item->name : ($item instanceof \App\Enums\Substatus ? $item->value : (string) $item);
        $modelOrder = ($item instanceof \App\Models\Substatus && $item->sort_order) ? $item->sort_order : 999;
        return $preferredOrder[$name] ?? $modelOrder;
    })->values();

    $globalFlagsData = [];
    foreach ($globalFlagsList as $flagItem) {
        $flagValue = $flagItem instanceof \App\Models\Substatus ? $flagItem->name : ($flagItem instanceof \App\Enums\Substatus ? $flagItem->value : (string) $flagItem);
        $flagModel = ($flagItem instanceof \App\Models\Substatus) ? $flagItem : (($substatuses->first() instanceof \App\Models\Substatus) ? $substatuses->firstWhere('name', $flagValue) : null);
        $flagEnum = \App\Enums\Substatus::tryFrom($flagValue);
        $flagLabel = $flagEnum?->label() ?? ($flagModel?->name ?? $flagValue);

        $flagVars = match($flagValue) {
            'URGENTE', \App\Enums\Substatus::URGENTE->value => [
                'bg' => 'var(--cc-urgent-bg-light)',
                'text' => 'var(--cc-urgent-text-dark)',
                'border' => 'var(--cc-urgent-border)',
                'solid' => 'var(--cc-urgent-solid)',
            ],
            'TICKET', \App\Enums\Substatus::TICKET->value => [
                'bg' => 'var(--cc-camila-bg-light)',
                'text' => 'var(--cc-camila-text-dark)',
                'border' => 'var(--cc-camila-border)',
                'solid' => 'var(--cc-camila-solid)',
            ],
            'POTENTIAL CUSTOMER', \App\Enums\Substatus::POTENTIAL_CUSTOMER->value => [
                'bg' => 'var(--cc-todo-today-bg-light)',
                'text' => 'var(--cc-todo-today-text-dark)',
                'border' => 'var(--cc-todo-today-border)',
                'solid' => 'var(--cc-todo-today-solid)',
            ],
            'EXTERNO', \App\Enums\Substatus::EXTERNO->value => [
                'bg' => 'var(--cc-designer-external-bg-light)',
                'text' => 'var(--cc-designer-external-text-dark)',
                'border' => 'var(--cc-designer-external-border)',
                'solid' => 'var(--cc-designer-external-solid)',
            ],
            default => null,
        };

        if ($flagVars) {
            $flagBg = $flagVars['bg'];
            $flagText = $flagVars['text'];
            $flagBorder = $flagVars['border'];
            $flagSolid = $flagVars['solid'];
        } else {
            $hex = $flagModel?->color ?: ($flagModel?->bg_color ?: '#6B7280');
            $pal = \App\Models\Substatus::derivePaletteFromColor($hex, 'light');
            $flagBg = $pal['bg_color'];
            $flagText = $pal['text_color'];
            $flagBorder = $pal['border_color'];
            $flagSolid = $pal['color'];
        }

        $globalFlagsData[$flagValue] = [
            'name' => $flagValue,
            'label' => $flagLabel,
            'short' => \Illuminate\Support\Str::limit($flagLabel, 3, ''),
            'bg' => $flagBg,
            'text' => $flagText,
            'border' => $flagBorder,
            'solid' => $flagSolid,
        ];
    }

    $substatusStyleMap = [];
    foreach (\App\Models\Substatus::all() as $sItem) {
        $borderColor = $sItem->border_color ?: $sItem->bg_color;
        $substatusStyleMap[$sItem->name] = "background-color: {$sItem->bg_color}; color: {$sItem->text_color}; border-color: {$borderColor};";
    }
    foreach (\App\Enums\Substatus::cases() as $case) {
        if (!isset($substatusStyleMap[$case->value])) {
            $pal = \App\Models\Substatus::resolvePalette($case->value);
            $substatusStyleMap[$case->value] = $pal['inline'];
        }
    }

    $processFlatList = [];
    foreach ($groupedProcessSubstatuses as $g) {
        foreach ($g['items'] as $subItem) {
            $val = $subItem instanceof \App\Models\Substatus ? $subItem->name : $subItem->value;
            $enum = \App\Enums\Substatus::tryFrom($val);
            if ($enum && $enum->isGlobal()) continue;
            if ($subItem instanceof \App\Models\Substatus && $subItem->is_global) continue;
            $lbl = $enum?->label() ?? $val;
            $sty = ($subItem instanceof \App\Models\Substatus && $subItem->bg_color && $subItem->text_color)
                ? "background-color: {$subItem->bg_color}; color: {$subItem->text_color}; border-color: {$subItem->border_color};"
                : ($enum?->customBadgeStyle() ?? '');
            $processFlatList[] = [
                'value' => $val,
                'label' => $lbl,
                'style' => $sty,
            ];
        }
    }
@endphp

<div 
    x-data="{
        activeDateEdit: null,
        openDateInput(type, orderId, inputEl) {
            this.activeDateEdit = type + '_' + orderId;
            this.$nextTick(() => {
                if (inputEl) {
                    inputEl.focus();
                    if (inputEl.showPicker) {
                        try { inputEl.showPicker(); } catch (e) {}
                    }
                }
            });
        },
        closeDateInput() {
            if (this.activeDateEdit) {
                const inputEl = document.querySelector('[data-date-input=\'' + this.activeDateEdit + '\']');
                if (inputEl) {
                    if (!inputEl.dataset.initial) {
                        inputEl.value = '';
                    } else {
                        inputEl.value = inputEl.dataset.initial;
                    }
                    inputEl.blur();
                }
                this.activeDateEdit = null;
            }
        },
        ordersState: {{ \Illuminate\Support\Js::from($ordersState ?? []) }},
        archivedSubstatusNames: {{ \Illuminate\Support\Js::from(\App\Models\Substatus::getArchivedNames()) }},
        isArchivedSubstatus(name) {
            if (!name) return false;
            const clean = String(name).toUpperCase().trim();
            return this.archivedSubstatusNames.some(s => String(s).toUpperCase().trim() === clean);
        },
        lastServerTimestamp: {{ now()->timestamp }},
        isSyncing: false,
        lastSyncTime: '',
        pollTimer: null,
        init() {
            window.__overviewWire = $wire;
            this.lastSyncTime = new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            this.startPolling();
            document.addEventListener('pointerdown', (e) => {
                if (this.activeDateEdit) {
                    const container = e.target.closest('[data-date-container=\'' + this.activeDateEdit + '\']');
                    if (!container) {
                        this.closeDateInput();
                    }
                }
            });
            document.addEventListener('visibilitychange', () => {
                const isModalOpen = Boolean(window.Alpine && Alpine.store && (
                    (Alpine.store('installationModal') && Alpine.store('installationModal').isOpen) ||
                    (Alpine.store('substatusModal') && Alpine.store('substatusModal').isOpen)
                ));
                if (!document.hidden && !isModalOpen) {
                    this.syncState();
                }
            });
            window.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && !e.altKey && (e.key === 'f' || e.key === 'F' || e.code === 'KeyF')) {
                    const isModalOpen = Boolean(
                        document.querySelector('[data-modal=order-detail]') ||
                        (window.Alpine && Alpine.store && (
                            (Alpine.store('installationModal') && Alpine.store('installationModal').isOpen) ||
                            (Alpine.store('substatusModal') && Alpine.store('substatusModal').isOpen)
                        )) ||
                        document.querySelector('[role=dialog]:not([aria-hidden=true])')
                    );
                    if (!isModalOpen) {
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                        this.focusSearch();
                        return;
                    }
                }
                if (e.key === 'Escape' && this.activeDateEdit) {
                    this.closeDateInput();
                    return;
                }
                if (e.key === 'Escape' && !this.activeMenu && this.selectedRowId) {
                    this.selectedRowId = null;
                    return;
                }
                if (!this.activeMenu) return;

                const isArrowDown = e.key === 'ArrowDown' || e.key === 'Down';
                const isArrowUp = e.key === 'ArrowUp' || e.key === 'Up';
                const isEnter = e.key === 'Enter';
                const isEscape = e.key === 'Escape';

                if (isArrowDown || isArrowUp || isEnter || isEscape) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();

                    if (isArrowDown) this.navigateMenu(1);
                    else if (isArrowUp) this.navigateMenu(-1);
                    else if (isEnter) this.selectActiveMenuItem();
                    else if (isEscape) this.handleMenuEscape();
                }
            }, true);
        },
        startPolling() {
            if (this.pollTimer) clearInterval(this.pollTimer);
            this.pollTimer = setInterval(() => {
                const isModalOpen = Boolean(window.Alpine && Alpine.store && (
                    (Alpine.store('installationModal') && Alpine.store('installationModal').isOpen) ||
                    (Alpine.store('substatusModal') && Alpine.store('substatusModal').isOpen)
                ));
                if (!document.hidden && !$wire.editingOrderId && !this.activeMenu && !isModalOpen) {
                    this.syncState();
                }
            }, 15000);
        },
        getVisibleOrderIds() {
            return Object.keys(this.ordersState).map(id => parseInt(id)).filter(id => !isNaN(id));
        },
        async syncState(force = false) {
            const ids = this.getVisibleOrderIds();
            if (ids.length === 0) return;
            try {
                this.isSyncing = true;
                const res = await $wire.pollOrdersState(ids, force ? null : this.lastServerTimestamp);
                if (res && res.has_changes && res.orders) {
                    this.lastServerTimestamp = res.timestamp || this.lastServerTimestamp;
                    for (const id in res.orders) {
                        this.ordersState[id] = Object.assign(this.ordersState[id] || {}, res.orders[id]);
                    }
                    this.lastSyncTime = new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                } else if (res && res.timestamp) {
                    this.lastServerTimestamp = res.timestamp;
                    this.lastSyncTime = new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                }
            } catch (e) {
                // Silently ignore transient network disconnect
            } finally {
                setTimeout(() => { this.isSyncing = false; }, 300);
            }
        },
        async manualSync() {
            this.isSyncing = true;
            await this.syncState(true);
            $wire.refreshOverview();
            setTimeout(() => { this.isSyncing = false; }, 400);
        },
        selectedRowId: null,
        selectRow(orderId) {
            this.selectedRowId = (this.selectedRowId === orderId ? null : orderId);
        },
        isNoRealizadaSubstatus(name) {
            if (!name) return false;
            const clean = String(name).toUpperCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
            return clean.includes('NO REALIZADA');
        },
        isMutedRow(orderId) {
            const st = this.ordersState[orderId];
            if (!st) return false;
            const sub = st.substatus ? String(st.substatus).toUpperCase().trim() : '';
            return this.isNoRealizadaSubstatus(sub) || st.core_status === 'ARCHIVED' || Boolean(st.is_archived) || this.isArchivedSubstatus(sub);
        },
        getRowClass(orderId, defaultClass) {
            if (this.selectedRowId === orderId) return 'bg-line text-ink';
            if (!this.ordersState[orderId]) return defaultClass;
            return this.isMutedRow(orderId) ? 'text-ink-3 hover:bg-sunken' : 'hover:bg-sunken';
        },
        getStickyCellClass(orderId, defaultClass) {
            if (this.selectedRowId === orderId) return 'bg-line text-ink group-hover:bg-line';
            if (!this.ordersState[orderId]) return defaultClass;
            return this.isMutedRow(orderId) ? 'bg-surface text-ink-3 group-hover:bg-sunken' : 'bg-surface text-ink group-hover:bg-sunken';
        },
        getStickyWoClass(orderId, defaultClass) {
            return this.getStickyCellClass(orderId, defaultClass);
        },
        toneClasses: {{ \Illuminate\Support\Js::from($toneClasses) }},
        substatusTones: {{ \Illuminate\Support\Js::from(\App\Livewire\Overview\OverviewIndex::substatusToneMap()) }},
        toneClass(name) {
            const tone = this.substatusTones[String(name || '').toUpperCase().trim()] || 'neutral';
            return this.toneClasses[tone] || this.toneClasses.neutral;
        },
        isPastDate(str) {
            if (!str) return false;
            const d = new Date();
            const today = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
            return str < today;
        },
        personInitials(name) {
            return String(name || '').trim().split(/\s+/).filter(Boolean).slice(0, 2).map(w => w.charAt(0)).join('').toUpperCase();
        },
        personStyle(hex) {
            const m = /^#?([0-9a-f]{6})$/i.exec(hex || '');
            if (!m) return '';
            const lin = c => { c = c / 255; return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4); };
            const n = parseInt(m[1], 16);
            const lum = 0.2126 * lin((n >> 16) & 255) + 0.7152 * lin((n >> 8) & 255) + 0.0722 * lin(n & 255);
            return 'background-color: #' + m[1] + '; color: ' + (lum > 0.4 ? '#1f1f1d' : '#ffffff') + ';';
        },
        estDisplay(orderId, raw) {
            const st = this.ordersState[orderId] || {};
            const prefix = (st.review_status === 'CS' || st.review_status === 'CAMILA') ? 'INV' : 'EST';
            const clean = String(raw || '').replace(/^(INV|EST)\s*#?\s*/i, '').trim();
            return clean ? prefix + ' ' + clean : prefix;
        },
        // Record data (WO, estimate/invoice numbers) asks before saving, showing old and new values.
        confirmWo(el, orderId) {
            const val = el.value.trim();
            if (!val || el.dataset.busy) return;
            el.dataset.busy = '1';
            KudosConfirmChange({ field: 'WO', from: '', to: val }).then(ok => {
                if (ok) { $wire.quickUpdateField(orderId, 'wo_number', val); } else { el.value = ''; }
            }).finally(() => { delete el.dataset.busy; });
        },
        confirmEstimate(el, orderId) {
            const val = el.value.trim();
            const from = el.dataset.initial || '';
            if (el.dataset.busy) return;
            if (val === from) { el.value = this.estDisplay(orderId, from); return; }
            el.dataset.busy = '1';
            el.value = this.estDisplay(orderId, from);
            KudosConfirmChange({ field: @js(__('Estimate / Invoice')), from: from || '—', to: val || '—' }).then(ok => {
                if (ok) {
                    el.dataset.initial = val;
                    $wire.quickUpdateField(orderId, 'estimate_invoice_number', val);
                    el.value = this.estDisplay(orderId, val);
                }
            }).finally(() => { delete el.dataset.busy; });
        },
        activeMenu: null,
        targetOrderId: null,
        targetSubstatus: null,
        targetInstallationType: null,
        targetInstallationTypes: [],
        targetFlags: [],
        menuStyle: '',
        menuSearch: '',
        menuActiveIndex: 0,
        activeTriggerEl: null,
        matchesMenuSearch(text) {
            if (!this.menuSearch || !this.menuSearch.trim()) return true;
            if (!text) return false;
            const clean = (str) => str.toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
            return clean(text).includes(clean(this.menuSearch));
        },
        groupHasMatches(items) {
            if (!this.menuSearch || !this.menuSearch.trim()) return true;
            if (!items || !items.length) return false;
            const clean = (str) => str.toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
            const q = clean(this.menuSearch);
            return items.some(item => clean(item).includes(q));
        },
        getVisibleMenuItems() {
            if (!this.$refs.popoverContainer) return [];
            const buttons = Array.from(this.$refs.popoverContainer.querySelectorAll('[data-menu-item]'));
            return buttons.filter(el => {
                if (!el) return false;
                return el.offsetWidth > 0 || el.offsetHeight > 0 || el.getClientRects().length > 0;
            });
        },
        highlightActiveMenuItem() {
            const items = this.getVisibleMenuItems();
            if (!items.length) return;
            if (this.menuActiveIndex < 0 || this.menuActiveIndex >= items.length) {
                this.menuActiveIndex = 0;
            }
            items.forEach((item, idx) => {
                if (idx === this.menuActiveIndex) {
                    item.classList.add('menu-item-active');
                } else {
                    item.classList.remove('menu-item-active');
                }
            });
        },
        navigateMenu(direction) {
            const items = this.getVisibleMenuItems();
            if (!items.length) return;

            this.menuActiveIndex = (this.menuActiveIndex + direction + items.length) % items.length;
            this.highlightActiveMenuItem();
            const activeEl = items[this.menuActiveIndex];
            if (activeEl) {
                const container = activeEl.closest('.overflow-y-auto');
                if (container) {
                    const elTop = activeEl.offsetTop;
                    const elBottom = elTop + activeEl.offsetHeight;
                    const containerTop = container.scrollTop;
                    const containerBottom = containerTop + container.clientHeight;

                    if (elTop < containerTop) {
                        container.scrollTop = elTop;
                    } else if (elBottom > containerBottom) {
                        container.scrollTop = elBottom - container.clientHeight;
                    }
                }
            }
        },
        selectActiveMenuItem() {
            const items = this.getVisibleMenuItems();
            if (items.length && this.menuActiveIndex >= 0 && this.menuActiveIndex < items.length) {
                const activeEl = items[this.menuActiveIndex];
                if (activeEl) {
                    const searchEl = this.$refs.popoverContainer ? this.$refs.popoverContainer.querySelector('[data-menu-search]') : null;
                    if (searchEl && (activeEl === searchEl || activeEl.hasAttribute('data-menu-search') || activeEl.tagName === 'INPUT')) {
                        searchEl.focus({ preventScroll: true });
                    } else {
                        activeEl.click();
                    }
                }
            }
        },
        handleMenuEscape() {
            const searchEl = this.$refs.popoverContainer ? this.$refs.popoverContainer.querySelector('[data-menu-search]') : null;
            if (searchEl && document.activeElement === searchEl) {
                if (this.$refs.popoverContainer) {
                    this.$refs.popoverContainer.focus({ preventScroll: true });
                } else {
                    searchEl.blur();
                }
                return;
            }
            if (this.menuSearch && this.menuSearch.trim().length > 0) {
                this.menuSearch = '';
                this.menuActiveIndex = 0;
                if (this.$refs.popoverContainer) {
                    this.$refs.popoverContainer.focus({ preventScroll: true });
                }
                this.$nextTick(() => this.highlightActiveMenuItem());
            } else {
                this.closeMenu();
            }
        },
        onMenuSearchInput() {
            this.menuActiveIndex = 0;
            this.$nextTick(() => this.highlightActiveMenuItem());
        },
        focusSearch() {
            if (this.activeDateEdit) {
                this.closeDateInput();
            }
            if (this.activeMenu) {
                this.closeMenu();
            }
            const input = this.$refs.overviewSearchInput || document.getElementById('overview-search-input');
            if (input) {
                input.focus();
                input.select();
            }
        },
        handleGlobalKeydown(e) {
            if ((e.ctrlKey || e.metaKey) && !e.altKey && (e.key === 'f' || e.key === 'F' || e.code === 'KeyF')) {
                const isModalOpen = Boolean(
                    document.querySelector('[data-modal=order-detail]') ||
                    (window.Alpine && Alpine.store && (
                        (Alpine.store('installationModal') && Alpine.store('installationModal').isOpen) ||
                        (Alpine.store('substatusModal') && Alpine.store('substatusModal').isOpen)
                    )) ||
                    document.querySelector('[role=dialog]:not([aria-hidden=true])')
                );
                if (!isModalOpen) {
                    e.preventDefault();
                    e.stopPropagation();
                    this.focusSearch();
                    return;
                }
            }

            if (!this.activeMenu) return;
            if (this.activeMenu === 'installation' && e.target && e.target.closest('[data-tag-input]')) {
                return;
            }

            if (e.key === 'ArrowDown' || e.key === 'Down') {
                e.preventDefault();
                e.stopPropagation();
                this.navigateMenu(1);
            } else if (e.key === 'ArrowUp' || e.key === 'Up') {
                e.preventDefault();
                e.stopPropagation();
                this.navigateMenu(-1);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                e.stopPropagation();
                this.selectActiveMenuItem();
            } else if (e.key === 'Escape') {
                e.preventDefault();
                e.stopPropagation();
                this.handleMenuEscape();
            } else if (e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
                const searchEl = this.$refs.popoverContainer ? this.$refs.popoverContainer.querySelector('[data-menu-search]') : null;
                if (searchEl && document.activeElement !== searchEl) {
                    searchEl.focus({ preventScroll: true });
                }
            } else if (e.key === 'Backspace') {
                const searchEl = this.$refs.popoverContainer ? this.$refs.popoverContainer.querySelector('[data-menu-search]') : null;
                if (searchEl && document.activeElement !== searchEl) {
                    searchEl.focus({ preventScroll: true });
                }
            }
        },
        updateActiveFromHover(el) {
            const items = this.getVisibleMenuItems();
            const idx = items.indexOf(el);
            if (idx !== -1 && idx !== this.menuActiveIndex) {
                this.menuActiveIndex = idx;
                this.highlightActiveMenuItem();
            }
        },
        updateMenuPosition() {
            if (!this.activeMenu || !this.activeTriggerEl) return;
            if (!document.body.contains(this.activeTriggerEl)) return;
            const rect = this.activeTriggerEl.getBoundingClientRect();
            const spaceBelow = window.innerHeight - rect.bottom;

            let menuHeight = 240;
            let menuWidth = 220;
            if (this.activeMenu === 'substatus') { menuHeight = 460; menuWidth = 295; }
            if (this.activeMenu === 'designer') { menuHeight = 280; menuWidth = 230; }
            if (this.activeMenu === 'installation') { menuHeight = 310; menuWidth = 330; }
            if (this.activeMenu === 'review') { menuHeight = 170; menuWidth = 210; }

            const openUp = spaceBelow < menuHeight && rect.top > menuHeight;
            let left = Math.min(Math.max(10, rect.left), window.innerWidth - menuWidth - 15);

            if (openUp) {
                const bottom = window.innerHeight - rect.top + 4;
                this.menuStyle = `position: fixed; left: ${left}px; bottom: ${bottom}px; width: ${menuWidth}px; max-height: ${Math.min(menuHeight, rect.top - 15)}px; z-index: 99999;`;
            } else {
                const top = rect.bottom + 4;
                this.menuStyle = `position: fixed; left: ${left}px; top: ${top}px; width: ${menuWidth}px; max-height: ${Math.min(menuHeight, spaceBelow - 15)}px; z-index: 99999;`;
            }
        },
        openMenu(type, orderId, triggerEl, extraData = {}) {
            if (this.activeMenu === type && this.targetOrderId === orderId) {
                this.closeMenu();
                return;
            }
            this.activeMenu = type;
            this.targetOrderId = orderId;
            this.activeTriggerEl = triggerEl;
            this.menuSearch = '';
            this.menuActiveIndex = 0;
            const st = this.ordersState[orderId] || {};
            this.targetSubstatus = st.substatus !== undefined ? st.substatus : (extraData.substatus !== undefined ? extraData.substatus : null);
            const rawTypes = st.installation_types !== undefined 
                ? st.installation_types 
                : (extraData.installationTypes !== undefined 
                    ? extraData.installationTypes 
                    : (st.installation_type ? [st.installation_type] : (extraData.installationType ? [extraData.installationType] : [])));
            this.targetInstallationTypes = Array.isArray(rawTypes) ? [...rawTypes] : (rawTypes ? [rawTypes] : []);
            this.targetInstallationType = this.targetInstallationTypes.length > 0 ? this.targetInstallationTypes[0] : null;
            this.targetFlags = Array.isArray(st.flags) ? st.flags : (Array.isArray(extraData.flags) ? extraData.flags : []);

            this.updateMenuPosition();

            this.$nextTick(() => {
                if (this.activeMenu === 'installation') {
                    const tagInput = this.$refs.popoverContainer ? this.$refs.popoverContainer.querySelector('[data-tag-input]') : null;
                    if (tagInput) {
                        tagInput.focus({ preventScroll: true });
                        return;
                    }
                }
                if (this.$refs.popoverContainer) {
                    this.$refs.popoverContainer.focus({ preventScroll: true });
                }
                this.highlightActiveMenuItem();
            });
        },
        closeMenu() {
            this.activeMenu = null;
            this.targetOrderId = null;
            this.targetSubstatus = null;
            this.targetInstallationType = null;
            this.targetInstallationTypes = [];
            this.targetFlags = [];
            this.menuSearch = '';
            this.menuActiveIndex = 0;
            this.activeTriggerEl = null;
        },
        setDesigner(dId, dName, badgeStyle, inlineStyle, hex) {
            const orderId = this.targetOrderId;
            this.closeMenu();
            if (orderId) {
                if (!this.ordersState[orderId]) this.ordersState[orderId] = {};
                this.ordersState[orderId].designer_id = dId;
                this.ordersState[orderId].designer_hex = hex || null;
                this.ordersState[orderId].designer_edited = true;
                if (!dId) dName = null;
                this.ordersState[orderId].designer_name = dName;
                $wire.updateDesigner(orderId, dId);
            }
        },
        installationTypesMap: {{ \Illuminate\Support\Js::from($installationTypes->keyBy('name')->map(fn($t) => [
            'bg' => $t->bg_color,
            'text' => $t->text_color,
            'border' => $t->border_color ?: $t->bg_color,
        ])) }},
        substatusesMap: {{ \Illuminate\Support\Js::from($substatusStyleMap) }},
        flagsMap: {{ \Illuminate\Support\Js::from($globalFlagsData) }},
        getOrderSubstatus(orderId, fallbackSubstatus = null) {
            const st = this.ordersState[orderId];
            if (st && st.substatus !== undefined) return st.substatus;
            return fallbackSubstatus;
        },
        getOrderSubstatusLabel(orderId, fallbackLabel = '—') {
            const st = this.ordersState[orderId];
            if (st && st.substatus_label !== undefined) return st.substatus_label;
            return fallbackLabel;
        },
        getOrderSubstatusStyle(orderId, fallbackStyle = '') {
            const st = this.ordersState[orderId];
            if (st && st.substatus_style !== undefined && st.substatus_style !== null) {
                return st.substatus_style;
            }
            if (st && st.substatus && this.substatusesMap[st.substatus]) {
                return this.substatusesMap[st.substatus];
            }
            return fallbackStyle;
        },
        getOrderFlags(orderId, fallbackFlags = []) {
            const st = this.ordersState[orderId];
            if (st && st.flags !== undefined) {
                return Array.isArray(st.flags) ? st.flags : [];
            }
            return Array.isArray(fallbackFlags) ? fallbackFlags : [];
        },
        getFlagStyle(flagName) {
            if (!flagName) return '';
            const f = this.flagsMap[flagName] || this.flagsMap[String(flagName).toUpperCase()];
            if (f && f.bg) {
                return `background-color: ${f.bg}; color: ${f.text}; border: 1px solid ${f.border};`;
            }
            return 'background-color: var(--cc-camila-bg-light); color: var(--cc-camila-text-dark); border: 1px solid var(--cc-camila-border);';
        },
        getFlagLabel(flagName) {
            const f = this.flagsMap[flagName] || this.flagsMap[String(flagName).toUpperCase()];
            return (f && f.label) ? f.label : flagName;
        },
        getFlagShort(flagName) {
            const f = this.flagsMap[flagName] || this.flagsMap[String(flagName).toUpperCase()];
            return (f && f.short) ? f.short : String(flagName).slice(0, 3);
        },
        getOrderInstallationTypes(orderId, fallbackTypes = []) {
            const st = this.ordersState[orderId];
            if (st && st.installation_types !== undefined) {
                return Array.isArray(st.installation_types) ? st.installation_types : (st.installation_types ? [st.installation_types] : []);
            }
            if (st && st.installation_type !== undefined) {
                return st.installation_type ? [st.installation_type] : [];
            }
            if (Array.isArray(fallbackTypes)) return fallbackTypes;
            return fallbackTypes ? [fallbackTypes] : [];
        },
        getFirstMatchingInstallationType() {
            if (!this.menuSearch || !this.menuSearch.trim()) return null;
            const clean = (str) => str.toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
            const q = clean(this.menuSearch);
            const types = this.allInstallationTypesList || [];
            const unselected = types.filter(t => !this.isInstallationTypeSelected(t));
            const matchUnselected = unselected.find(t => clean(t).includes(q));
            if (matchUnselected) return matchUnselected;
            return types.find(t => clean(t).includes(q)) || null;
        },
        addPrimaryMatchInstallationType() {
            if (this.menuSearch && this.menuSearch.trim()) {
                const match = this.getFirstMatchingInstallationType();
                if (match) {
                    this.toggleInstallationType(match);
                    this.menuSearch = '';
                    this.$nextTick(() => {
                        const input = this.$refs.popoverContainer ? this.$refs.popoverContainer.querySelector('[data-tag-input]') : null;
                        if (input) input.focus();
                    });
                    return;
                }
            } else {
                this.closeMenu();
            }
        },
        removeLastInstallationType() {
            if (this.targetInstallationTypes && this.targetInstallationTypes.length > 0) {
                const last = this.targetInstallationTypes[this.targetInstallationTypes.length - 1];
                this.toggleInstallationType(last);
            }
        },
        onTagInputBackspace(e) {
            if (!this.menuSearch && (!e.target.selectionStart || e.target.selectionStart === 0)) {
                this.removeLastInstallationType();
            }
        },
        isInstallationTypeSelected(name) {
            if (!name || !this.targetInstallationTypes) return false;
            const upper = String(name).toUpperCase().trim();
            return this.targetInstallationTypes.some(t => String(t).toUpperCase().trim() === upper);
        },
        toggleInstallationType(name) {
            const orderId = this.targetOrderId;
            if (!orderId || !name) return;
            const upper = String(name).toUpperCase().trim();
            let current = [...(this.targetInstallationTypes || [])];
            const idx = current.findIndex(t => String(t).toUpperCase().trim() === upper);
            if (idx !== -1) {
                current.splice(idx, 1);
            } else {
                current.push(upper);
            }
            this.targetInstallationTypes = current;
            this.targetInstallationType = current.length > 0 ? current[0] : null;
            if (!this.ordersState[orderId]) this.ordersState[orderId] = {};
            this.ordersState[orderId].installation_types = current;
            this.ordersState[orderId].installation_type = current.length > 0 ? current[0] : null;
            $wire.toggleInstallationType(orderId, upper);
        },
        clearAllInstallationTypes() {
            const orderId = this.targetOrderId;
            if (!orderId) return;
            this.targetInstallationTypes = [];
            this.targetInstallationType = null;
            if (!this.ordersState[orderId]) this.ordersState[orderId] = {};
            this.ordersState[orderId].installation_types = [];
            this.ordersState[orderId].installation_type = null;
            $wire.clearInstallationTypes(orderId);
        },
        getInstallationStyle(type) {
            if (!type) return '';
            const direct = this.installationTypesMap[type];
            if (direct && direct.bg) {
                return `background-color: ${direct.bg}; color: ${direct.text}; border-color: ${direct.border};`;
            }
            const lower = String(type).toLowerCase().trim();
            for (const [k, v] of Object.entries(this.installationTypesMap)) {
                if (k.toLowerCase().trim() === lower && v.bg) {
                    return `background-color: ${v.bg}; color: ${v.text}; border-color: ${v.border};`;
                }
            }
            return 'background-color: #f5f5f4; color: #44403c; border-color: #e7e5e4;';
        },
        getInstallationClass(type) {
            if (!type) return 'bg-transparent text-stone-400 hover:bg-stone-50';
            if (this.getInstallationStyle(type)) return 'border shadow-2xs font-semibold';
            return 'bg-stone-100 text-stone-700 font-semibold';
        },
        getReviewCellClass(status) {
            return 'bg-transparent text-ink';
        },
        getReviewCellStyle(status) {
            return '';
        },
        getReviewIconColor(status) {
            if (status === 'CS' || status === 'CAMILA') return 'text-ink hover:bg-sunken';
            return 'text-ink-3 hover:text-ink hover:bg-sunken';
        },
        getReviewIconStyle(status) {
            return '';
        },
        setReviewStatus(status) {
            const orderId = this.targetOrderId;
            this.closeMenu();
            if (orderId) {
                this.ordersState[orderId] = Object.assign({}, this.ordersState[orderId] || {}, { review_status: status });
                const prefix = (status === 'CS' || status === 'CAMILA') ? 'INV' : 'EST';
                const inputEl = document.querySelector('[data-est-input=\'' + orderId + '\']');
                if (inputEl) {
                    const raw = (inputEl.dataset.initial || inputEl.value || '').replace(/^(INV|EST)\s*#?\s*/i, '').trim();
                    inputEl.value = raw ? `${prefix} ${raw}` : prefix;
                }
                $wire.updateReviewStatus(orderId, status);
            }
        },
        setInstallationType(type) {
            const orderId = this.targetOrderId;
            this.closeMenu();
            if (orderId) {
                const upperType = type ? type.toUpperCase() : null;
                const arr = upperType ? [upperType] : [];
                this.ordersState[orderId] = Object.assign({}, this.ordersState[orderId] || {}, { 
                    installation_type: upperType,
                    installation_types: arr 
                });
                $wire.updateInstallationType(orderId, upperType);
            }
        },
        setSubstatus(status, label, style) {
            const orderId = this.targetOrderId;
            this.closeMenu();
            if (orderId) {
                if (!this.ordersState[orderId]) this.ordersState[orderId] = {};
                const upperStatus = status ? status.toUpperCase().trim() : status;
                const upperLabel = label ? label.toUpperCase().trim() : label;
                this.ordersState[orderId].substatus = upperStatus;
                if (label !== undefined) this.ordersState[orderId].substatus_label = upperLabel;
                if (style !== undefined) this.ordersState[orderId].substatus_style = style;
                if (this.isArchivedSubstatus(upperStatus)) {
                    this.ordersState[orderId].core_status = 'ARCHIVED';
                    this.ordersState[orderId].is_archived = true;
                }
                $wire.updateSubstatus(orderId, upperStatus);
            }
        },
        toggleGlobalFlag(flag) {
            const orderId = this.targetOrderId;
            this.closeMenu();
            if (orderId) {
                if (this.ordersState[orderId]) {
                    const flags = this.ordersState[orderId].flags || [];
                    const idx = flags.indexOf(flag);
                    if (idx !== -1) flags.splice(idx, 1);
                    else flags.push(flag);
                    this.ordersState[orderId].flags = flags;
                }
                $wire.toggleFlag(orderId, flag);
            }
        }
    }"
    @keydown.window="handleGlobalKeydown($event)"
    @scroll.passive="if (activeMenu) updateMenuPosition()"
    @scroll.window.passive="if (activeMenu) updateMenuPosition()"
    @resize.window.passive="if (activeMenu) updateMenuPosition()"
    @order-installation-changed.window="if (ordersState[$event.detail.orderId]) { ordersState[$event.detail.orderId].installation_types = $event.detail.types; ordersState[$event.detail.orderId].installation_type = $event.detail.types.length > 0 ? $event.detail.types[0] : null; }"
    @order-substatus-changed.window="
        if (!ordersState[$event.detail.orderId]) ordersState[$event.detail.orderId] = {};
        if ($event.detail.substatus !== undefined) ordersState[$event.detail.orderId].substatus = $event.detail.substatus;
        if ($event.detail.substatusLabel !== undefined) ordersState[$event.detail.orderId].substatus_label = $event.detail.substatusLabel;
        if ($event.detail.substatusStyle !== undefined) ordersState[$event.detail.orderId].substatus_style = $event.detail.substatusStyle;
        if ($event.detail.flags !== undefined) ordersState[$event.detail.orderId].flags = [...$event.detail.flags];
        if (isArchivedSubstatus($event.detail.substatus) || $event.detail.isArchived) {
            ordersState[$event.detail.orderId].core_status = 'ARCHIVED';
            ordersState[$event.detail.orderId].is_archived = true;
        }
    "
    class="h-full w-full max-w-full flex flex-col min-h-0 overflow-hidden">

    @php
        $fieldBase = 'ui-input';
        $tabBase = 'px-3 py-1 rounded-control text-body font-medium transition cursor-pointer inline-flex items-center gap-1.5';
        $tabOn = 'bg-surface text-ink border border-line';
        $tabOff = 'border border-transparent text-ink-2 hover:text-ink';
        $toggleBase = 'ui-chip';
        $toggleOn = '';
        $toggleOff = '';
        $hasActiveFilters = !empty($appliedFilters) || $search || $filterWo || $filterClient || $filterDesigner || $filterReviewStatus || $filterInstallation || $filterDateRange || $filterUrgent || $filterWithDueDate;
    @endphp

    <!-- Header, view tabs and filters -->
    <div class="px-4 sm:px-6 pt-4 pb-3 space-y-3 shrink-0">
        <x-ui.page-header :title="__('Overview')" />

        <div class="flex flex-wrap items-center gap-3">
            <!-- View mode: one neutral segmented control -->
            <div class="inline-flex items-center gap-0.5 bg-sunken p-0.5 rounded-card flex-wrap" role="tablist">
                <button type="button" role="tab" aria-selected="{{ $activeTab === 'all' ? 'true' : 'false' }}" wire:click="setTab('all')" class="{{ $tabBase }} {{ $activeTab === 'all' ? $tabOn : $tabOff }}">
                    {{ __('All') }}
                </button>
                <button type="button" role="tab" aria-selected="{{ $activeTab === 'workspace' ? 'true' : 'false' }}" wire:click="setTab('workspace')" class="{{ $tabBase }} {{ $activeTab === 'workspace' ? $tabOn : $tabOff }}">
                    <span>{{ __('Active Orders') }}</span>
                    <span class="text-ink-3 font-normal tabular-nums">{{ $totalWorkspaceCount }}</span>
                </button>
                <button type="button" role="tab" aria-selected="{{ $activeTab === 'production' ? 'true' : 'false' }}" wire:click="setTab('production')" class="{{ $tabBase }} {{ $activeTab === 'production' ? $tabOn : $tabOff }}">
                    <span>{{ __('In Production') }}</span>
                    <span class="text-ink-3 font-normal tabular-nums">{{ $inProductionCount }}</span>
                </button>
                <button type="button" role="tab" aria-selected="{{ $activeTab === 'archived' ? 'true' : 'false' }}" wire:click="setTab('archived', 'all')" class="{{ $tabBase }} {{ $activeTab === 'archived' ? $tabOn : $tabOff }}">
                    <span>{{ __('Archived') }}</span>
                    <span class="text-ink-3 font-normal tabular-nums">{{ $totalArchivedCount }}</span>
                </button>
                <button type="button" role="tab" aria-selected="{{ $activeTab === 'backlog' ? 'true' : 'false' }}" wire:click="setTab('backlog')" class="{{ $tabBase }} {{ $activeTab === 'backlog' ? $tabOn : $tabOff }}">
                    <span>{{ __('Backlog') }}</span>
                    <span class="text-ink-3 font-normal tabular-nums">{{ $totalBacklogCount }}</span>
                </button>
            </div>

            <!-- Quick toggles -->
            <div class="inline-flex items-center gap-1.5 flex-wrap">
                <button type="button" wire:click="toggleFilter('urgent')" aria-pressed="{{ $filterUrgent ? 'true' : 'false' }}" class="{{ $toggleBase }} {{ $filterUrgent ? $toggleOn : $toggleOff }}">
                    <span>{{ __('Urgent') }}</span>
                    @if(($urgentCount ?? 0) > 0)
                        <span class="{{ $filterUrgent ? 'text-white/70' : 'text-ink-3' }} font-normal tabular-nums">{{ $urgentCount }}</span>
                    @endif
                </button>
                <button type="button" wire:click="toggleFilter('withDueDate')" aria-pressed="{{ $filterWithDueDate ? 'true' : 'false' }}" class="{{ $toggleBase }} {{ $filterWithDueDate ? $toggleOn : $toggleOff }}">
                    <span>{{ __('With due date') }}</span>
                    @if(($withDueDateCount ?? 0) > 0)
                        <span class="{{ $filterWithDueDate ? 'text-white/70' : 'text-ink-3' }} font-normal tabular-nums">{{ $withDueDateCount }}</span>
                    @endif
                </button>
            </div>
        </div>

        <!-- Filters row: quiet, identical fields -->
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-2.5 items-center">
            <div class="col-span-2 sm:col-span-1 md:col-span-1 relative">
                <x-lucide-search class="w-3.5 h-3.5 text-ink-3 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none z-10" />
                <input
                    x-ref="overviewSearchInput"
                    id="overview-search-input"
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    @keydown.escape="$el.blur()"
                    placeholder="{{ __('Search all...') }}"
                    title="{{ __('Search the overview (Ctrl+F / ⌘F)') }}"
                    class="{{ $fieldBase }} pl-8"
                >
            </div>

            <input
                type="text"
                wire:model.live.debounce.300ms="filterWo"
                placeholder="{{ __('WO filter (e.g. 1234)') }}"
                class="{{ $fieldBase }}"
            >

            <select wire:model.live="filterClient" class="ui-select">
                <option value="">{{ __('All clients') }}</option>
                @foreach($clients as $c)
                    <option value="{{ is_object($c) ? $c->id : $c }}">{{ is_object($c) ? $c->name : $c }}</option>
                @endforeach
            </select>

            <select wire:model.live="filterDesigner" class="ui-select">
                <option value="">{{ __('All Designers') }}</option>
                @foreach($filterDesigners as $d)
                    <option value="{{ is_object($d) ? $d->id : $d }}">{{ is_object($d) ? $d->name : $d }}</option>
                @endforeach
            </select>

            <select wire:model.live="filterReviewStatus" class="ui-select">
                <option value="">{{ __('Any estimate review') }}</option>
                <option value="CS">{{ __('Reviewed by CS') }}</option>
                <option value="CAMILA">{{ __('Reviewed by Camila') }}</option>
                <option value="NONE">{{ __('No review') }}</option>
            </select>

            <select wire:model.live="filterInstallation" class="ui-select">
                <option value="">{{ __('Any installation') }}</option>
                @foreach($installationTypes as $instType)
                    <option value="{{ $instType->name }}">{{ $instType->name }}</option>
                @endforeach
                <option value="NONE">{{ __('Empty (no info)') }}</option>
            </select>
        </div>

        <!-- Active filters: shown once, as removable chips plus "Limpiar" -->
        @if($hasActiveFilters)
            <div class="flex items-center gap-1.5 flex-wrap">
                @foreach($appliedFilters as $filter)
                    <span class="inline-flex items-center gap-1.5 pl-2 pr-1 py-0.5 rounded-control text-meta bg-sunken text-ink-2 border border-line">
                        <span class="text-ink-3">{{ $filter['label'] }}:</span>
                        <span class="font-medium text-ink max-w-[160px] truncate" title="{{ $filter['value'] }}">{{ $filter['value'] }}</span>
                        <button type="button" wire:click="clearFilter('{{ $filter['key'] }}')" class="text-ink-3 hover:text-ink hover:bg-line rounded p-0.5 transition cursor-pointer" title="{{ __('Remove filter') }}" aria-label="{{ __('Remove filter') }}">
                            <x-lucide-x class="w-3 h-3" />
                        </button>
                    </span>
                @endforeach
                <button type="button" wire:click="resetFilters" class="px-2 py-0.5 rounded-control text-meta font-medium text-ink-2 hover:text-ink hover:bg-sunken transition cursor-pointer">
                    {{ __('Clear') }}
                </button>
            </div>
        @endif
    </div>

    <!-- Single Unified Orders Data Grid (11 Columns Layout) -->
    <div 
        x-data="{ 
            scrollTableHorizontally(delta) {
                if (this.$refs.tableScrollWrapper) {
                    this.$refs.tableScrollWrapper.scrollBy({ left: delta, behavior: 'smooth' });
                }
            },
            scrollTableTo(pos) {
                if (this.$refs.tableScrollWrapper) {
                    this.$refs.tableScrollWrapper.scrollTo({ left: pos, behavior: 'smooth' });
                }
            }
        }"
        class="bg-white border-y border-stone-200 w-full relative flex-1 min-h-0 flex flex-col">
        <div 
            x-ref="tableScrollWrapper" 
            class="w-full flex-1 min-h-0 overflow-auto custom-horizontal-scrollbar custom-vertical-scrollbar">
            <table x-ref="ordersTable" class="w-full min-w-[2379px] table-fixed text-left text-body border-collapse">
                <thead class="sticky top-0 z-20 bg-stone-50 shadow-2xs">
                    <tr class="bg-sunken border-b border-line text-meta font-medium normal-case tracking-normal text-ink-3">
                        <!-- 1. Fecha Procesado en Producción (Sticky Left: 0px) -->
                        <th 
                            style="width: 112px; left: 0px;"
                            class="sticky top-0 left-0 z-30 bg-stone-50 border-b border-r border-stone-200 py-2.5 px-1 select-none"
                            {{-- TEMPORAL: wire:click="sortByColumn('production_processed_at')" class="cursor-pointer hover:bg-stone-100 transition-colors" --}}>
                            <div class="flex items-center justify-between gap-0.5 w-full pointer-events-none overflow-hidden">
                                <span class="truncate" title="{{ __('Processed in production') }}">{{ __('Processed') }}</span>
                                {{-- TEMPORAL: <x-lucide-arrow-up-down class="w-3 h-3 text-stone-400 shrink-0" /> --}}
                            </div>
                        </th>

                        <!-- 2. Order Due Date (Sticky Left: 85px) -->
                        <th 
                            style="width: 112px; left: 112px;"
                            class="sticky top-0 z-30 bg-stone-50 border-b border-r border-stone-200 py-2.5 px-1 select-none"
                            {{-- TEMPORAL: wire:click="sortByColumn('delivery_due_date')" class="cursor-pointer hover:bg-stone-100 transition-colors" --}}>
                            <div class="flex items-center justify-between gap-0.5 w-full pointer-events-none overflow-hidden">
                                <span class="truncate" title="{{ __('Delivery due date') }}">{{ __('Due date') }}</span>
                                {{-- TEMPORAL: <x-lucide-arrow-up-down class="w-3 h-3 text-stone-400 shrink-0" /> --}}
                            </div>
                        </th>

                        <!-- 3. WO # (Sticky Left: 170px) -->
                        <th 
                            style="width: 100px; left: 224px;"
                            class="sticky top-0 z-30 bg-stone-50 border-b border-r border-stone-200 py-2.5 pl-3 sm:pl-4 pr-1 select-none"
                            {{-- TEMPORAL: wire:click="sortByColumn('wo_number')" class="cursor-pointer hover:bg-stone-100 transition-colors" --}}>
                            <div class="flex items-center justify-between gap-0.5 w-full pointer-events-none overflow-hidden">
                                <span class="truncate" title="{{ __('WO #') }}">{{ __('WO #') }}</span>
                                {{-- TEMPORAL: <x-lucide-arrow-up-down class="w-3 h-3 text-stone-400 shrink-0" /> --}}
                            </div>
                        </th>

                        <!-- 4. Client (Sticky Left: 255px) -->
                        <th 
                            style="width: 260px; left: 324px;"
                            class="sticky top-0 z-30 bg-stone-50 border-b border-r border-stone-200 py-2.5 px-1.5 select-none"
                            {{-- TEMPORAL: wire:click="sortByColumn('company_name')" class="cursor-pointer hover:bg-stone-100 transition-colors" --}}>
                            <div class="flex items-center justify-between gap-0.5 w-full pointer-events-none overflow-hidden">
                                <span class="truncate" title="{{ __('Client') }}">{{ __('Client') }}</span>
                                {{-- TEMPORAL: <x-lucide-arrow-up-down class="w-3 h-3 text-stone-400 shrink-0" /> --}}
                            </div>
                        </th>

                        <!-- 5. Order Name (Sticky Left: 455px) -->
                        <th 
                            style="width: 400px; left: 584px;"
                            class="sticky top-0 z-30 bg-stone-50 border-b border-r border-stone-200 shadow-[2px_0_4px_-2px_rgba(0,0,0,0.08)] py-2.5 px-2 select-none"
                            {{-- TEMPORAL: wire:click="sortByColumn('task_name')" class="cursor-pointer hover:bg-stone-100 transition-colors" --}}>
                            <div class="flex items-center justify-between gap-0.5 w-full pointer-events-none overflow-hidden">
                                <span class="truncate" title="{{ __('Order name') }}">{{ __('Order name') }}</span>
                                {{-- TEMPORAL: <x-lucide-arrow-up-down class="w-3 h-3 text-stone-400 shrink-0" /> --}}
                            </div>
                        </th>

                        <!-- 6. Designer (Slimmer: 85px) -->
                        <th 
                            style="width: 130px;"
                            class="sticky top-0 z-20 bg-stone-50 border-b border-stone-200 py-2.5 px-1 select-none">
                            <div class="flex items-center justify-between gap-0.5 w-full pointer-events-none overflow-hidden">
                                <span class="truncate" title="{{ __('Designer') }}">{{ __('Designer') }}</span>
                            </div>
                        </th>

                        <!-- 7. Nota Producción / Instalación -->
                        <th 
                            style="width: 400px;"
                            class="sticky top-0 z-20 bg-stone-50 border-b border-stone-200 py-2.5 px-1.5 select-none">
                            <div class="flex items-center justify-between gap-0.5 w-full pointer-events-none overflow-hidden">
                                <span class="truncate inline-flex items-center gap-1" title="{{ __('Production / installation note') }}"><span>{{ __('Prod./inst. note') }}</span></span>
                            </div>
                        </th>

                        <!-- 8. Estimado / Invoice -->
                        <th 
                            style="width: 130px;"
                            class="sticky top-0 z-20 bg-stone-50 border-b border-stone-200 py-2.5 px-1 select-none">
                            <div class="flex items-center justify-between gap-0.5 w-full pointer-events-none overflow-hidden">
                                <span class="truncate" title="{{ __('Estimate / Invoice') }}">{{ __('Est. / Inv.') }}</span>
                            </div>
                        </th>

                        <!-- 8.5. Fecha Email -->
                        <th 
                            style="width: 85px;"
                            class="sticky top-0 z-20 bg-stone-50 border-b border-stone-200 py-2.5 px-1 select-none"
                            {{-- TEMPORAL: wire:click="sortByColumn('email_date')" class="cursor-pointer hover:bg-stone-100 transition-colors" --}}>
                            <div class="flex items-center justify-between gap-0.5 w-full pointer-events-none overflow-hidden">
                                <span class="truncate" title="{{ __('Email date') }}">{{ __('Email') }}</span>
                                {{-- TEMPORAL: <x-lucide-arrow-up-down class="w-3 h-3 text-stone-400 shrink-0" /> --}}
                            </div>
                        </th>

                        <!-- 9. Instalación -->
                        <th 
                            style="width: 170px;"
                            class="sticky top-0 z-20 bg-stone-50 border-b border-stone-200 py-2.5 px-1 select-none">
                            <div class="flex items-center justify-between gap-0.5 w-full pointer-events-none overflow-hidden">
                                <span class="truncate" title="{{ __('Installation') }}">{{ __('Installation') }}</span>
                            </div>
                        </th>

                        <!-- 10. CHECK MARK -->
                        <th 
                            style="width: 50px;"
                            class="sticky top-0 z-20 bg-stone-50 border-b border-stone-200 py-2.5 px-0.5 text-center select-none">
                            <div class="flex items-center justify-center gap-0.5 w-full pointer-events-none overflow-hidden">
                                <x-lucide-check class="w-3.5 h-3.5 text-stone-400" />
                            </div>
                        </th>

                        <!-- 11. Nota de Entrega -->
                        <th 
                            style="width: 240px;"
                            class="sticky top-0 z-20 bg-stone-50 border-b border-stone-200 py-2.5 px-1.5 select-none">
                            <div class="flex items-center justify-between gap-0.5 w-full pointer-events-none overflow-hidden">
                                <span class="truncate inline-flex items-center gap-1" title="{{ __('Delivery note') }}"><span>{{ __('Delivery') }}</span></span>
                            </div>
                        </th>

                        <!-- 12. Subestatus -->
                        <th 
                            style="width: 170px;"
                            class="sticky top-0 z-20 bg-stone-50 border-b border-stone-200 py-2.5 pl-1.5 pr-4 sm:pr-6 select-none"
                            {{-- TEMPORAL: wire:click="sortByColumn('substatus')" class="cursor-pointer hover:bg-stone-100 transition-colors" --}}>
                            <div class="flex items-center justify-between gap-0.5 w-full pointer-events-none overflow-hidden">
                                <span class="truncate" title="{{ __('Substatuses') }}">{{ __('Substatuses') }}</span>
                                {{-- TEMPORAL: <x-lucide-arrow-up-down class="w-3 h-3 text-stone-400 shrink-0" /> --}}
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100 font-medium text-meta">
                    @php ob_start(); @endphp
                    @forelse($orders as $order)
                        @php
                            $subVal = $order->substatus?->value ?? (is_string($order->substatus) ? $order->substatus : null);
                            $subClean = mb_strtoupper(trim((string) $subVal));
                            $isNoRealizada = str_contains($subClean, 'NO REALIZADA');

                            $isArchivedSub = \App\Models\Substatus::isArchivedSubstatus($subVal);

                            $isArchived = ! $isNoRealizada && (
                                $order->isArchived() 
                                || ($order->core_status === \App\Enums\CoreStatus::ARCHIVED) 
                                || ($order->core_status?->value === 'ARCHIVED') 
                                || ($order->core_status === 'ARCHIVED')
                                || !empty($order->archived_at)
                                || $isArchivedSub
                            );


                            $isMuted = $isNoRealizada || $isArchived;
                            // One color signal per row: no stage tints. Archived / not-done rows just use muted text.
                            $rowStyle = $isMuted ? 'text-ink-3 hover:bg-sunken' : 'hover:bg-sunken';
                            $stickyCellBg = $isMuted
                                ? 'bg-surface text-ink-3 group-hover:bg-sunken'
                                : 'bg-surface text-ink group-hover:bg-sunken';
                            $stickyWoStyle = $stickyCellBg;
                        @endphp
                        <tr 
                            data-order-id="{{ $order->id }}"
                            @click="if (!$event.target.closest('button, a, input, select, textarea, [data-popover-trigger]')) selectRow({{ $order->id }})"
                            :class="getRowClass({{ $order->id }}, '{{ $rowStyle }}')"
                            class="group relative cursor-pointer {{ $rowStyle }}">
                            <!-- 1. Fecha Procesado en Producción (Sticky Left: 0px) -->
                            @php
                                $procDateVal = $order->production_processed_at ? $order->production_processed_at->format('Y-m-d') : '';
                                $procDateDisplay = $order->production_processed_at ? $order->production_processed_at->format('d/m/Y') : '—';
                                $hasProcDate = !empty($procDateVal);
                            @endphp
                            <td 
                                style="width: 112px; left: 0px;"
                                :class="getStickyCellClass({{ $order->id }}, '{{ $stickyCellBg }}')"
                                class="sticky left-0 z-10 {{ $stickyCellBg }} border-r border-stone-200 py-1 px-1 truncate transition-colors">
                                <div class="w-full flex items-center min-w-0" data-date-container="proc_{{ $order->id }}">
                                    @if(!$hasProcDate)
                                        <button 
                                            type="button" 
                                            x-show="activeDateEdit !== 'proc_{{ $order->id }}'"
                                            @click.stop="openDateInput('proc', {{ $order->id }}, $el.nextElementSibling)" 
                                            class="w-full text-left text-stone-400 hover:text-stone-700 text-meta font-mono px-1 py-0.5 rounded-sm cursor-pointer"
                                            title="{{ __('No date - click to set the processed date') }}">
                                            —
                                        </button>
                                    @endif
                                    <input 
                                        @if(!$hasProcDate) x-show="activeDateEdit === 'proc_{{ $order->id }}'" x-cloak style="display: none;" @endif
                                        type="date"
                                        data-date-input="proc_{{ $order->id }}"
                                        data-initial="{{ $procDateVal }}"
                                        value="{{ $procDateVal }}"
                                        @input.stop
                                        @keydown.escape.stop.prevent="closeDateInput()"
                                        @blur="closeDateInput()"
                                        @change.stop="
                                            if ($el.value !== $el.dataset.initial) {
                                                $el.dataset.initial = $el.value;
                                                $wire.quickUpdateField({{ $order->id }}, 'production_processed_at', $el.value);
                                            }
                                            closeDateInput();
                                        "
                                        class="w-full bg-transparent hover:bg-stone-100/60 focus:bg-white text-meta text-stone-700 font-mono px-1 py-0.5 rounded-sm border-0 border-b border-transparent focus:border-stone-400 focus:outline-none focus:ring-0 cursor-pointer"
                                        title="{{ $procDateDisplay }} ({{ __('Processed in production - click to edit') }})"
                                    >
                                </div>
                            </td>

                            <!-- 2. Order Due Date (Sticky Left: 85px) -->
                            @php
                                $dueDateVal = $order->delivery_due_date ? $order->delivery_due_date->format('Y-m-d') : '';
                                $dueDateDisplay = $order->delivery_due_date ? $order->delivery_due_date->format('d/m/Y') : '—';
                                $isDueDateFilled = !empty($dueDateVal);
                            @endphp
                            <td 
                                style="width: 112px; left: 112px;"
                                :class="getStickyCellClass({{ $order->id }}, '{{ $stickyCellBg }}')"
                                class="sticky z-10 {{ $stickyCellBg }} border-r border-stone-200 py-1 px-1.5 truncate transition-colors">
                                <div class="w-full flex items-center min-w-0" data-date-container="due_{{ $order->id }}">
                                    @if(!$isDueDateFilled)
                                        <button 
                                            type="button" 
                                            x-show="activeDateEdit !== 'due_{{ $order->id }}'"
                                            @click.stop="openDateInput('due', {{ $order->id }}, $el.nextElementSibling)" 
                                            class="w-full text-left text-stone-400 hover:text-stone-700 text-meta font-mono px-1.5 py-0.5 rounded-sm cursor-pointer"
                                            title="{{ __('No due date - click to set the delivery date') }}">
                                            —
                                        </button>
                                    @endif
                                    <input 
                                        @if(!$isDueDateFilled) x-show="activeDateEdit === 'due_{{ $order->id }}'" x-cloak style="display: none;" @endif
                                        type="date"
                                        data-date-input="due_{{ $order->id }}"
                                        data-initial="{{ $dueDateVal }}"
                                        value="{{ $dueDateVal }}"
                                        @input.stop
                                        @keydown.escape.stop.prevent="closeDateInput()"
                                        @blur="closeDateInput()"
                                        @change.stop="
                                            const val = $el.value;
                                            if (val) {
                                                $el.dataset.initial = val;
                                                if (ordersState[{{ $order->id }}]) {
                                                    ordersState[{{ $order->id }}].delivery_due_date = val;
                                                }
                                                $wire.quickUpdateField({{ $order->id }}, 'delivery_due_date', val);
                                            } else {
                                                $el.dataset.initial = '';
                                                $el.value = '';
                                                if (ordersState[{{ $order->id }}]) {
                                                    ordersState[{{ $order->id }}].delivery_due_date = null;
                                                }
                                                $wire.quickUpdateField({{ $order->id }}, 'delivery_due_date', '');
                                            }
                                            closeDateInput();
                                        "
                                        class="w-full text-meta tabular-nums px-1.5 py-0.5 rounded-control border-0 cursor-pointer bg-transparent hover:bg-sunken focus:bg-surface focus:outline-none focus:ring-0 {{ $isDueDateFilled && $order->isOverdue() ? 'text-danger font-medium' : 'text-ink-2' }}"
                                        :class="isPastDate(ordersState[{{ $order->id }}]?.delivery_due_date || '{{ $dueDateVal }}') && !isMutedRow({{ $order->id }}) ? 'text-danger font-medium' : 'text-ink-2'"
                                        title="{{ $dueDateDisplay }} ({{ __('Delivery due date - click to edit') }})"
                                    >
                                </div>
                            </td>

                            <!-- 3. WO # (Sticky Left: 170px) -->
                            <td 
                                style="width: 100px; left: 224px;"
                                :class="getStickyCellClass({{ $order->id }}, '{{ $stickyCellBg }}')"
                                class="sticky z-10 {{ $stickyCellBg }} border-r border-stone-200 py-1 pl-3 sm:pl-4 pr-1 truncate transition-colors">
                                <div class="flex items-center gap-1 overflow-hidden truncate">
                                    @if($order->hasNoWo())
                                        <input
                                            type="text"
                                            value=""
                                            placeholder="+ WO"
                                            @input.stop
                                            @keydown.enter.stop.prevent="$el.blur()"
                                            @keydown.escape.stop.prevent="$el.value = ''; $el.blur()"
                                            @blur="confirmWo($el, {{ $order->id }})"
                                            class="w-full bg-transparent hover:bg-sunken focus:bg-surface text-ink placeholder-ink-3 text-body px-1.5 py-0.5 rounded-control border border-dashed border-line-strong focus:border-ink focus:outline-none focus:ring-0 transition-colors truncate"
                                            title="{{ __('No WO - click to add a WO number') }}"
                                        >
                                    @else
                                        <x-wo-badge :number="$order->wo_number" />
                                    @endif
                                </div>
                            </td>

                            <!-- 4. Cliente (Sticky Left: 255px) -->
                            <td 
                                style="width: 260px; left: 324px;"
                                :class="getStickyCellClass({{ $order->id }}, '{{ $stickyCellBg }}')"
                                class="sticky z-10 {{ $stickyCellBg }} border-r border-stone-200 py-1 px-1.5 truncate transition-colors">
                                <div class="flex items-center gap-2 min-w-0">
                                    <button
                                        type="button"
                                        wire:click="$dispatch('open-order-detail', { orderId: {{ $order->id }} })"
                                        class="font-semibold text-ink hover:underline truncate cursor-pointer text-left min-w-0 shrink"
                                        title="{{ $order->company_name ?: ($order->client?->name ?? '—') }}">
                                        {{ $order->company_name ?: ($order->client?->name ?? '—') }}
                                    </button>
                                    <x-orders.location :location="$order->location_text" class="shrink-0 max-w-[45%]" />
                                </div>
                            </td>

                            <!-- 5. Order Name (Sticky Left: 455px) -->
                            <td 
                                style="width: 400px; left: 584px;"
                                :class="getStickyCellClass({{ $order->id }}, '{{ $stickyCellBg }}')"
                                class="sticky z-10 {{ $stickyCellBg }} border-r border-stone-200 shadow-[2px_0_4px_-2px_rgba(0,0,0,0.08)] py-1 px-2 truncate transition-colors">
                                <button 
                                    type="button" 
                                    wire:click="$dispatch('open-order-detail', { orderId: {{ $order->id }} })"
                                    class="text-ink-2 font-medium hover:text-ink hover:underline truncate cursor-pointer text-left w-fit max-w-full block"
                                    title="{{ $order->clean_task_name }}">
                                    {{ $order->clean_task_name }}
                                </button>
                            </td>

                            <!-- 6. Designer (Full Color Cell, Slimmer) -->
                            <td class="py-1 px-1.5 truncate">
                                <button
                                    type="button"
                                    data-popover-trigger="designer"
                                    @click.stop="openMenu('designer', {{ $order->id }}, $el)"
                                    :title="ordersState[{{ $order->id }}]?.designer_name || '{{ addslashes($order->designer_name) }}'"
                                    class="w-full text-left cursor-pointer border-none bg-transparent py-0.5 rounded-control hover:bg-sunken min-w-0"
                                    title="{{ $order->designer_name }}">
                                    <span x-show="!ordersState[{{ $order->id }}]?.designer_edited">
                                        <x-ui.person :person="$order->primary_designer" show-name size="sm" />
                                    </span>
                                    <span x-show="ordersState[{{ $order->id }}]?.designer_edited" x-cloak style="display: none;" class="inline-flex items-center gap-2 min-w-0 max-w-full">
                                        <template x-if="ordersState[{{ $order->id }}]?.designer_name">
                                            <span class="inline-flex items-center gap-2 min-w-0">
                                                <span class="w-5 h-5 text-[9px] rounded-full inline-grid place-items-center font-semibold shrink-0 select-none" :class="ordersState[{{ $order->id }}]?.designer_hex ? '' : 'bg-stone-200 text-ink-2'" :style="personStyle(ordersState[{{ $order->id }}]?.designer_hex)" x-text="personInitials(ordersState[{{ $order->id }}]?.designer_name)"></span>
                                                <span class="text-body text-ink truncate" x-text="ordersState[{{ $order->id }}]?.designer_name"></span>
                                            </span>
                                        </template>
                                        <template x-if="!ordersState[{{ $order->id }}]?.designer_name">
                                            <span class="inline-flex items-center gap-2 min-w-0">
                                                <span class="w-5 h-5 rounded-full inline-grid place-items-center border border-dashed border-line-strong text-ink-3 shrink-0"><x-lucide-user class="w-3 h-3" aria-hidden="true" /></span>
                                                <span class="text-body text-ink-3 truncate">{{ __('Unassigned') }}</span>
                                            </span>
                                        </template>
                                    </span>
                                </button>
                            </td>
                            <!-- 7. Nota Producción / Instalación -->
                            <td class="py-1 px-1.5 truncate">
                                <input 
                                    type="text" 
                                    data-initial="{{ $order->production_note ?? '' }}"
                                    value="{{ $order->production_note ?? '' }}"
                                    placeholder="—"
                                    @input.stop
                                    @keydown.enter.stop.prevent="$el.blur()"
                                    @keydown.escape.stop.prevent="$el.value = $el.dataset.initial; $el.blur()"
                                    @blur="
                                        const val = $el.value.trim();
                                        if (val !== $el.dataset.initial) {
                                            $el.dataset.initial = val;
                                            $wire.quickUpdateField({{ $order->id }}, 'production_note', val);
                                        }
                                    "
                                    class="w-full bg-transparent hover:bg-stone-100/70 focus:bg-white text-meta text-stone-700 italic px-1.5 py-0.5 rounded-sm border-0 border-b border-transparent focus:border-stone-400 focus:outline-none focus:ring-0 truncate placeholder-stone-400 focus:text-stone-900 focus:not-italic"
                                    title="{{ $order->production_note ?: __('Click to edit production note') }}"
                                >
                            </td>

                            <!-- 8. Estimado / Invoice (Clean grid cell styling, NO inner box borders) -->
                            @php
                                $isRevised = !empty($order->review_status);
                                $prefix = $isRevised ? 'INV' : 'EST';
                                $cleanNum = $order->estimate_invoice_number ? trim(preg_replace('/^(INV|EST)\s*#?\s*/i', '', $order->estimate_invoice_number)) : '';
                                $displayText = $cleanNum ? "{$prefix} {$cleanNum}" : $prefix;
                            @endphp
                            <td 
                                :class="getReviewCellClass(ordersState[{{ $order->id }}]?.review_status !== undefined ? ordersState[{{ $order->id }}].review_status : '{{ addslashes($order->review_status ?? '') }}')"
                                :style="getReviewCellStyle(ordersState[{{ $order->id }}]?.review_status !== undefined ? ordersState[{{ $order->id }}].review_status : '{{ addslashes($order->review_status ?? '') }}')"
                                class="py-1 px-1 truncate"
                            >
                                <div class="flex items-center justify-between gap-0.5 w-full py-0.5 truncate">
                                    <input 
                                        data-est-input="{{ $order->id }}"
                                        type="text" 
                                        data-initial="{{ $order->estimate_invoice_number ?? '' }}"
                                        value="{{ $displayText }}"
                                        placeholder="{{ $prefix }}"
                                        @input.stop
                                        @focus="if ($el.value === 'EST' || $el.value === 'INV') { $el.value = ''; } else { $el.value = $el.dataset.initial || '{{ $cleanNum }}'; }"
                                        @keydown.enter.stop.prevent="$el.blur()"
                                        @keydown.escape.stop.prevent="$el.value = '{{ $displayText }}'; $el.blur()"
                                        @blur="confirmEstimate($el, {{ $order->id }})"
                                        class="flex-1 min-w-0 bg-transparent hover:bg-sunken focus:bg-surface text-meta tabular-nums font-semibold text-ink px-1 py-0.5 rounded-sm border-0 border-b border-transparent focus:border-stone-400 focus:outline-none focus:ring-0 transition-colors truncate"
                                        title="{{ $displayText }} ({{ __('Click to edit') }})"
                                    >

                                    <button 
                                        type="button"
                                        data-popover-trigger="review"
                                        @click.stop="openMenu('review', {{ $order->id }}, $el)"
                                        :class="getReviewIconColor(ordersState[{{ $order->id }}]?.review_status !== undefined ? ordersState[{{ $order->id }}].review_status : '{{ addslashes($order->review_status ?? '') }}')"
                                        :style="getReviewIconStyle(ordersState[{{ $order->id }}]?.review_status !== undefined ? ordersState[{{ $order->id }}].review_status : '{{ addslashes($order->review_status ?? '') }}')"
                                        class="p-0.5 rounded cursor-pointer shrink-0 transition flex items-center justify-center border-none"
                                        title="{{ __('Change review status') }}">
                                        <x-lucide-check-square class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </td>

                            <!-- 8.5. Fecha Email -->
                            @php
                                $emailDateVal = $order->email_date ? $order->email_date->format('Y-m-d') : '';
                                $emailDateDisplay = $order->email_date ? $order->email_date->format('d/m/Y') : '—';
                                $hasEmailDate = !empty($emailDateVal);
                            @endphp
                            <td class="py-1 px-1.5 truncate">
                                <div class="w-full flex items-center min-w-0">
                                    @if(!$hasEmailDate)
                                        <button 
                                            type="button" 
                                            x-show="activeDateEdit !== 'email_{{ $order->id }}'"
                                            @click="openDateInput('email', {{ $order->id }}, $el.nextElementSibling)" 
                                            class="w-full text-left text-stone-400 hover:text-stone-700 text-meta font-mono px-1 py-0.5 rounded-sm cursor-pointer"
                                            title="{{ __('No email date - click to set') }}">
                                            —
                                        </button>
                                    @endif
                                    <input 
                                        @if(!$hasEmailDate) x-show="activeDateEdit === 'email_{{ $order->id }}'" x-cloak style="display: none;" @endif
                                        type="date"
                                        data-initial="{{ $emailDateVal }}"
                                        value="{{ $emailDateVal }}"
                                        @input.stop
                                        @blur="if (!$el.value) closeDateInput();"
                                        @change.stop="
                                            if ($el.value !== $el.dataset.initial) {
                                                $el.dataset.initial = $el.value;
                                                $wire.quickUpdateField({{ $order->id }}, 'email_date', $el.value);
                                            }
                                            closeDateInput();
                                        "
                                        class="w-full bg-transparent hover:bg-stone-100/60 focus:bg-white text-meta text-stone-700 font-mono px-1 py-0.5 rounded-sm border-0 border-b border-transparent focus:border-stone-400 focus:outline-none focus:ring-0 cursor-pointer"
                                        title="{{ $emailDateDisplay }} ({{ __('Click to edit') }})"
                                    >
                                </div>
                            </td>

                            <!-- 9. Instalación (Concept A: Micro-Pills with Overflow Counter) -->
                            @php
                                $orderInstTypes = $order->installation_types_list;
                                $hasInstTypes = !empty($orderInstTypes);
                                $instSummaryTitle = $hasInstTypes ? implode(', ', $orderInstTypes) : __('No info');
                            @endphp
                            <td class="py-1 px-1 text-meta bg-transparent hover:bg-stone-50/80">
                                <button 
                                    type="button"
                                    @click.stop="Alpine.store('installationModal').open({ orderId: {{ $order->id }}, wo: '{{ addslashes($order->wo_number ?? '') }}', company: '{{ addslashes($order->company_name ?? '') }}', types: getOrderInstallationTypes({{ $order->id }}, {{ \Illuminate\Support\Js::from($orderInstTypes) }}) })"
                                    class="w-full text-left cursor-pointer flex items-center justify-between gap-1 py-0.5 px-0.5 rounded border border-transparent hover:border-stone-200 transition bg-transparent overflow-hidden"
                                    title="{{ __('Installation: :types (click to change)', ['types' => $instSummaryTitle]) }}">
                                    
                                    <div class="flex items-center gap-1 overflow-hidden min-w-0 flex-1">
                                        <template x-if="getOrderInstallationTypes({{ $order->id }}, {{ \Illuminate\Support\Js::from($orderInstTypes) }}).length === 0">
                                            <span class="text-stone-400 font-normal truncate block pl-0.5">—</span>
                                        </template>

                                        <template x-if="getOrderInstallationTypes({{ $order->id }}, {{ \Illuminate\Support\Js::from($orderInstTypes) }}).length === 1">
                                            <span 
                                                class="truncate font-medium text-meta px-1.5 py-0.5 rounded-control block border border-line bg-sunken text-ink-2 leading-tight"
                                                x-text="getOrderInstallationTypes({{ $order->id }}, {{ \Illuminate\Support\Js::from($orderInstTypes) }})[0]">
                                                {{ $orderInstTypes[0] ?? '—' }}
                                            </span>
                                        </template>

                                        <template x-if="getOrderInstallationTypes({{ $order->id }}, {{ \Illuminate\Support\Js::from($orderInstTypes) }}).length === 2">
                                            <div class="flex items-center gap-1 overflow-hidden min-w-0">
                                                <span 
                                                    class="truncate font-medium text-meta px-1.5 py-0.5 rounded-control block border border-line bg-sunken text-ink-2 leading-tight shrink"
                                                    x-text="getOrderInstallationTypes({{ $order->id }}, {{ \Illuminate\Support\Js::from($orderInstTypes) }})[0]">
                                                    {{ $orderInstTypes[0] ?? '' }}
                                                </span>
                                                <span 
                                                    class="truncate font-medium text-meta px-1.5 py-0.5 rounded-control block border border-line bg-sunken text-ink-2 leading-tight shrink"
                                                    x-text="getOrderInstallationTypes({{ $order->id }}, {{ \Illuminate\Support\Js::from($orderInstTypes) }})[1]">
                                                    {{ $orderInstTypes[1] ?? '' }}
                                                </span>
                                            </div>
                                        </template>

                                        <template x-if="getOrderInstallationTypes({{ $order->id }}, {{ \Illuminate\Support\Js::from($orderInstTypes) }}).length > 2">
                                            <div class="flex items-center gap-1 overflow-hidden min-w-0">
                                                <span 
                                                    class="truncate font-medium text-meta px-1.5 py-0.5 rounded-control block border border-line bg-sunken text-ink-2 leading-tight shrink"
                                                    x-text="getOrderInstallationTypes({{ $order->id }}, {{ \Illuminate\Support\Js::from($orderInstTypes) }})[0]">
                                                    {{ $orderInstTypes[0] ?? '' }}
                                                </span>
                                                <span 
                                                    class="font-medium text-meta px-1.5 py-0.5 rounded-control bg-sunken text-ink-3 border border-line shrink-0 leading-tight"
                                                    x-text="'+' + (getOrderInstallationTypes({{ $order->id }}, {{ \Illuminate\Support\Js::from($orderInstTypes) }}).length - 1)"
                                                    :title="getOrderInstallationTypes({{ $order->id }}, {{ \Illuminate\Support\Js::from($orderInstTypes) }}).slice(1).join(', ')">
                                                    +{{ count($orderInstTypes) - 1 }}
                                                </span>
                                            </div>
                                        </template>
                                    </div>

                                    <x-lucide-chevron-down class="w-2.5 h-2.5 shrink-0 opacity-40 text-stone-500" />
                                </button>
                            </td>

                            <!-- 10. CHECK MARK (Standalone Toggle Checkbox) -->
                            <td class="py-1 px-0.5 text-center">
                                <input 
                                    type="checkbox" 
                                    @click.stop="$wire.toggleOverviewChecked({{ $order->id }})"
                                    {{ $order->overview_checked ? 'checked' : '' }}
                                    class="w-3.5 h-3.5 rounded border-stone-300 text-stone-900 focus:ring-stone-900 cursor-pointer"
                                >
                            </td>

                            <!-- 11. Nota de Entrega -->
                            <td class="py-1 px-1.5 truncate">
                                <input 
                                    type="text" 
                                    data-initial="{{ $order->delivery_note ?? '' }}"
                                    value="{{ $order->delivery_note ?? '' }}"
                                    placeholder="—"
                                    @input.stop
                                    @keydown.enter.stop.prevent="$el.blur()"
                                    @keydown.escape.stop.prevent="$el.value = $el.dataset.initial; $el.blur()"
                                    @blur="
                                        const val = $el.value.trim();
                                        if (val !== $el.dataset.initial) {
                                            $el.dataset.initial = val;
                                            $wire.quickUpdateField({{ $order->id }}, 'delivery_note', val);
                                        }
                                    "
                                    class="w-full bg-transparent hover:bg-stone-100/70 focus:bg-white text-meta text-stone-700 italic px-1.5 py-0.5 rounded-sm border-0 border-b border-transparent focus:border-stone-400 focus:outline-none focus:ring-0 transition-colors truncate placeholder-stone-400 focus:text-stone-900 focus:not-italic"
                                    title="{{ $order->delivery_note ?: __('Click to edit delivery note') }}"
                                >
                            </td>

                            <!-- 12. Subestatus -->
                            @php
                                $subVal = $order->substatus?->value ?? (is_string($order->substatus) ? $order->substatus : null);
                                $subEnum = $subVal ? \App\Enums\Substatus::tryFrom($subVal) : null;
                                $subModel = ($subVal && ($substatuses->first() instanceof \App\Models\Substatus)) ? $substatuses->firstWhere('name', $subVal) : null;
                                $subLabel = $subEnum?->label() ?? ($subVal ?: '—');
                                
                                $subInlineStyle = '';
                                if ($subModel && !empty($subModel->bg_color) && !empty($subModel->text_color)) {
                                    $subInlineStyle = "background-color: {$subModel->bg_color}; color: {$subModel->text_color}; border-color: {$subModel->border_color};";
                                } elseif ($subEnum) {
                                    $subInlineStyle = $subEnum->getInlineBadgeStyle();
                                }

                                $subTone = \App\Livewire\Overview\OverviewIndex::substatusTone($subVal);
                            @endphp
                            <td class="py-1 pl-1.5 pr-4 sm:pr-6 truncate">
                                <button 
                                    type="button"
                                    @click.stop="Alpine.store('substatusModal').open({ 
                                        orderId: {{ $order->id }}, 
                                        wo: '{{ addslashes($order->wo_number ?? '') }}', 
                                        company: '{{ addslashes($order->company_name ?? '') }}', 
                                        substatus: getOrderSubstatus({{ $order->id }}, '{{ addslashes($subVal ?? '') }}'),
                                        substatusLabel: getOrderSubstatusLabel({{ $order->id }}, '{{ addslashes($subLabel) }}'),
                                        substatusStyle: getOrderSubstatusStyle({{ $order->id }}, '{{ addslashes($subInlineStyle) }}'),
                                        flags: getOrderFlags({{ $order->id }}, {{ \Illuminate\Support\Js::from($order->flags ?? []) }})
                                    })"
                                    class="w-full text-left cursor-pointer flex items-center justify-between gap-0.5 border-none bg-transparent py-0.5 truncate"
                                    title="{{ __('Substatus and flags') }}: {{ $subLabel }} ({{ __('Click to change') }})">
                                    <div class="flex items-center gap-0.5 overflow-hidden truncate">
                                        <span class="truncate text-body block px-1.5 rounded-control {{ $toneClasses[$subTone] }}" :class="toneClass(getOrderSubstatus({{ $order->id }}, '{{ addslashes($subVal ?? '') }}'))" x-text="getOrderSubstatusLabel({{ $order->id }}, '{{ addslashes($subLabel) }}')">{{ $subLabel }}</span>
                                        @if($order->isOverdue())
                                            <span class="px-1 rounded-control text-meta font-semibold {{ $toneClasses['danger'] }} shrink-0" title="{{ __('Overdue') }}">!</span>
                                        @elseif($order->isDueToday())
                                            <span class="px-1 rounded-control text-meta font-semibold {{ $toneClasses['warn'] }} shrink-0" title="{{ __('Due today') }}">{{ __('DUE') }}</span>
                                        @endif
                                        <template x-for="fName in getOrderFlags({{ $order->id }}, {{ \Illuminate\Support\Js::from($order->flags ?? []) }})" :key="fName">
                                            <span 
                                                x-show="fName !== 'OVERDUE' && fName !== 'ALMOST OVERDUE'"
                                                class="px-1 rounded-control text-meta font-semibold shrink-0"
                                                :class="toneClass(fName)"
                                                :title="'Flag ' + getFlagLabel(fName)"
                                                x-text="getFlagShort(fName)">
                                            </span>
                                        </template>
                                    </div>
                                    <x-lucide-chevron-down class="w-2.5 h-2.5 opacity-60 shrink-0" />
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="13" class="py-8 text-center text-stone-400 font-medium">
                                {{ __('No orders found for this view.') }}
                            </td>
                        </tr>
                    @endforelse
                    @php echo \App\Support\CompactHtml::compact(ob_get_clean()); @endphp
                </tbody>
            </table>

            <!-- Infinite Scroll on Demand Loader -->
            @if(!empty($hasMore))
                <div 
                    wire:key="overview-chunk-loader-{{ $loadedCount }}"
                    x-data="{ loading: false }"
                    x-intersect.threshold.20="if (!loading) { loading = true; $wire.loadNextChunk(); }"
                    class="py-3 px-4 bg-stone-50 border-t border-stone-200 flex flex-col sm:flex-row items-center justify-center gap-2 text-body text-ink-2">
                    <div class="flex items-center gap-2">
                        <x-lucide-loader-2 class="w-4 h-4 animate-spin text-ink-3 shrink-0" />
                        <span>{{ __('Loading more orders as you scroll...') }} ({{ count($orders) }} / {{ $totalFilteredCount }})</span>
                    </div>
                    <button 
                        type="button" 
                        wire:click="loadNextChunk" 
                        class="text-meta font-medium text-ink hover:underline cursor-pointer sm:ml-3">
                        {{ __('Load next orders now') }}
                    </button>
                </div>
            @endif
        </div>

        <!-- Pagination Links Bar or All Loaded Status -->
        @if(method_exists($orders, 'hasPages') && $orders->hasPages())
            <div class="px-4 sm:px-6 py-3 border-t border-stone-200 bg-[#f7f7f5] flex flex-col sm:flex-row items-center justify-between gap-3">
                <div class="text-xs text-stone-500 font-medium">
                    {{ __('Showing') }} <span class="font-semibold text-stone-900">{{ $orders->firstItem() }}</span> {{ __('to') }} <span class="font-semibold text-stone-900">{{ $orders->lastItem() }}</span> {{ __('of') }} <span class="font-semibold text-stone-900">{{ $orders->total() }}</span> {{ __('orders') }}
                </div>
                <div>
                    {{ $orders->links() }}
                </div>
            </div>
        @else
            <div class="px-6 py-2.5 border-t border-line bg-canvas flex items-center gap-3">
                <div class="text-meta text-ink-3 flex items-center gap-2">
                    <span>{{ __('Showing') }} <strong class="text-stone-900 font-semibold">{{ count($orders) }}</strong> {{ __('of') }} <strong class="text-stone-900 font-semibold">{{ $totalFilteredCount }}</strong> {{ __('orders') }}</span>
                </div>
            </div>
        @endif
    </div>

    <!-- Single Centralized Unified Popover Container (No teleport leaks, max 1 active popover) -->
    <div 
        x-ref="popoverContainer"
        x-show="activeMenu !== null"
        tabindex="-1"
        @keydown.down.prevent.stop="navigateMenu(1)"
        @keydown.up.prevent.stop="navigateMenu(-1)"
        @keydown.enter.prevent.stop="selectActiveMenuItem()"
        @keydown.escape.prevent.stop="handleMenuEscape()"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        @click.outside="if (!$event.target.closest('[data-popover-trigger]')) closeMenu()"
        @wheel.stop
        :style="menuStyle"
        class="bg-white shadow-2xl border border-stone-200 rounded-xl p-2 z-[99999] flex flex-col overflow-hidden text-stone-900 overscroll-contain outline-none"
        style="display: none;">

        <!-- 1. Designer Popover Content -->
        <template x-if="activeMenu === 'designer'">
            <div class="flex flex-col h-full min-h-0 space-y-1.5">
                <div class="px-2 py-0.5 text-meta font-semibold text-ink-3 tracking-wider border-b border-stone-100 pb-1 flex items-center justify-between shrink-0">
                    <span>{{ __('Select designer') }}</span>
                </div>

                <!-- Search input -->
                <div class="px-1 shrink-0">
                    <div class="relative flex items-center">
                        <x-lucide-search class="w-3.5 h-3.5 text-stone-400 absolute left-2 pointer-events-none" />
                        <input 
                            data-menu-search
                            data-menu-item
                            @mouseenter="updateActiveFromHover($el)"
                            x-model="menuSearch"
                            @input="onMenuSearchInput()"
                            type="text" 
                            placeholder="{{ __('Search designer...') }}" 
                            class="w-full pl-7 pr-6 py-1 text-xs bg-stone-50 hover:bg-stone-100/80 focus:bg-white border border-stone-200 focus:border-stone-400 rounded-lg text-stone-800 placeholder-stone-400 outline-none transition"
                        >
                        <button 
                            x-show="menuSearch" 
                            @click="menuSearch = ''; $el.previousElementSibling.focus()" 
                            type="button" 
                            class="absolute right-2 text-stone-400 hover:text-stone-600 text-xs">✕</button>
                    </div>
                </div>

                <div @wheel.stop class="space-y-0.5 flex-1 min-h-0 overflow-y-auto overscroll-contain pr-0.5 custom-vertical-scrollbar">
                    <button 
                        type="button"
                        data-menu-item
                        @mouseenter="updateActiveFromHover($el)"
                        x-show="matchesMenuSearch(@js(mb_strtolower(__('Unassigned'))))"
                        @click="setDesigner(null, null, '', '', null)"
                        class="w-full text-left px-2 py-1.5 rounded-lg text-xs hover:bg-stone-100 text-stone-500 cursor-pointer">
                        -- {{ __('Unassigned') }} --
                    </button>
                    @foreach($designers as $d)
                        @php 
                            $desObj = is_object($d) ? $d : null; 
                            $dName = is_object($d) ? $d->name : $d; 
                            $dId = is_object($d) ? $d->id : $d;
                        @endphp
                        <button 
                            type="button"
                            data-menu-item
                            @mouseenter="updateActiveFromHover($el)"
                            x-show="matchesMenuSearch('{{ addslashes($dName) }}')"
                            @click="setDesigner({{ $dId }}, '{{ addslashes($dName) }}', '', '', {{ \Illuminate\Support\Js::from($desObj?->hex_color) }})"
                            class="w-full text-left px-2 py-1.5 rounded-lg text-xs hover:bg-stone-100 flex items-center justify-between font-semibold text-stone-800 cursor-pointer">
                            <span class="flex items-center gap-1.5">
                                @if($desObj)
                                    <span class="w-2 h-2 rounded-full shrink-0" style="{{ $desObj->dot_inline_style }}"></span>
                                @endif
                                <span>{{ $dName }}</span>
                            </span>
                        </button>
                    @endforeach
                </div>
            </div>
        </template>

        <!-- 2. Review Status Popover Content -->
        <template x-if="activeMenu === 'review'">
            <div class="flex flex-col h-full min-h-0 space-y-1">
                <div class="px-2 py-0.5 text-meta font-semibold text-ink-3">{{ __('Review status') }}</div>
                <button 
                    type="button"
                    data-menu-item
                    @mouseenter="updateActiveFromHover($el)"
                    @click="setReviewStatus('CS')"
                    class="w-full text-left px-2.5 py-1.5 rounded text-xs text-ink font-medium hover:bg-sunken transition cursor-pointer">
                    {{ __('Reviewed by CS') }}
                </button>
                <button 
                    type="button"
                    data-menu-item
                    @mouseenter="updateActiveFromHover($el)"
                    @click="setReviewStatus('CAMILA')"
                    class="w-full text-left px-2.5 py-1.5 rounded text-xs font-semibold transition cursor-pointer"
                    style="background-color: var(--cc-camila-bg-light); color: var(--cc-camila-text-dark); border: 1px solid var(--cc-camila-border);">
                    {{ __('Reviewed by Camila') }}
                </button>
                <button 
                    type="button"
                    data-menu-item
                    @mouseenter="updateActiveFromHover($el)"
                    @click="setReviewStatus(null)"
                    class="w-full text-left px-2.5 py-1.5 rounded text-xs bg-stone-50 text-stone-600 hover:bg-stone-100 border border-stone-200 transition cursor-pointer">
                    {{ __('No review (Blank / EST)') }}
                </button>
            </div>
        </template>
    </div>

    <!-- Dedicated Centered Installation Tags Modal Teleported to Body -->
    <template x-teleport="body">
        <div 
            x-data
            x-show="$store.installationModal && $store.installationModal.isOpen"
            x-cloak
            @open-installation-modal.window="$store.installationModal && $store.installationModal.open($event.detail)"
            @keydown.escape.window="if ($store.installationModal && $store.installationModal.isOpen) $store.installationModal.close()"
            @click.self="$store.installationModal && $store.installationModal.close()"
            class="fixed inset-0 z-[999999] flex items-center justify-center p-4"
            style="display: none;">
            
            <!-- Backdrop (clicking dark area closes) -->
            <div 
                x-show="$store.installationModal && $store.installationModal.isOpen"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 bg-stone-900/60 backdrop-blur-xs cursor-pointer"
                @click="$store.installationModal.close()">
            </div>

            <!-- Modal Dialog Container (click inside does NOT close) -->
            <div 
                x-show="$store.installationModal && $store.installationModal.isOpen"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95 translate-y-2"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                x-transition:leave-end="opacity-0 scale-95 translate-y-2"
                @click.stop
                class="relative z-10 bg-white rounded-2xl shadow-2xl border border-stone-200 w-full max-w-lg overflow-hidden flex flex-col max-h-[90vh]">
                
                <!-- Modal Header -->
                <div class="px-5 py-4 border-b border-stone-100 flex items-center justify-between bg-stone-50/70">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-sunken text-ink-2 flex items-center justify-center border border-line">
                            <x-lucide-tags class="w-5 h-5 text-ink-2" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-semibold text-stone-900">{{ __('Installation type') }}</h3>
                                <span 
                                    x-show="$store.installationModal && $store.installationModal.types.length > 0" 
                                    class="text-meta bg-sunken text-ink-2 border border-line px-2 py-0.5 rounded-full font-semibold"
                                    x-text="$store.installationModal ? ($store.installationModal.types.length + ' ' + ($store.installationModal.types.length === 1 ? '{{ __('selected') }}' : '{{ __('selected') }}')) : ''">
                                </span>
                            </div>
                            <p class="text-xs text-stone-500 mt-0.5 flex items-center gap-1.5 truncate">
                                <span class="font-mono font-semibold text-stone-700" x-text="($store.installationModal && $store.installationModal.orderWo) || @js(__('No WO'))"></span>
                                <span class="text-stone-300">•</span>
                                <span class="truncate" x-text="($store.installationModal && $store.installationModal.orderCompany) || @js(__('No Client'))"></span>
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <a 
                            href="{{ route('settings.installation-types') }}" 
                            @click.stop="$store.installationModal.close()" 
                            wire:navigate 
                            class="text-xs text-stone-400 hover:text-stone-700 hover:underline flex items-center gap-1 py-1 px-2 rounded hover:bg-stone-100 transition cursor-pointer"
                            title="{{ __('Manage types and colors') }}">
                            <x-lucide-settings class="w-3.5 h-3.5" />
                            <span class="hidden sm:inline">{{ __('Settings') }}</span>
                        </a>
                        <button 
                            type="button" 
                            @click="$store.installationModal.close()" 
                            class="text-stone-400 hover:text-stone-700 hover:bg-stone-200/60 p-1.5 rounded-lg transition cursor-pointer"
                            title="{{ __('Close') }}">
                            <x-lucide-x class="w-5 h-5" />
                        </button>
                    </div>
                </div>

                <!-- Modal Body -->
                <div class="p-5 overflow-y-auto space-y-4 custom-vertical-scrollbar flex-1 min-h-0">
                    <!-- 1. Input Box con Tags Seleccionados -->
                    <div>
                        <label class="block text-xs font-semibold text-stone-600 tracking-wider mb-1.5">
                            {{ __('Assigned tags') }}
                        </label>
                        <div 
                            onclick="document.getElementById('installation-modal-tag-input')?.focus()"
                            class="p-2 bg-surface border border-line focus-within:border-line-strong rounded-card transition flex flex-wrap items-center gap-1.5 min-h-[46px] cursor-text">
                            
                            <!-- Pills seleccionadas -->
                            <template x-for="type in ($store.installationModal ? $store.installationModal.types : [])" :key="type">
                                <span 
                                    :style="$store.installationModal.getStyle(type)"
                                    class="inline-flex items-center gap-1.5 pl-2.5 pr-1.5 py-1 rounded-lg text-xs font-semibold border shadow-2xs leading-tight animate-in fade-in zoom-in-95 duration-100">
                                    <span x-text="type"></span>
                                    <button 
                                        type="button" 
                                        @click.stop="$store.installationModal.toggleType(type)" 
                                        class="hover:bg-black/15 active:bg-black/25 rounded-full p-0.5 text-current cursor-pointer transition inline-flex items-center justify-center"
                                        title="{{ __('Remove') }}">
                                        <x-lucide-x class="w-3 h-3 stroke-[2.5]" />
                                    </button>
                                </span>
                            </template>

                            <!-- Input de texto -->
                            <input 
                                id="installation-modal-tag-input"
                                type="text" 
                                x-model="$store.installationModal.search"
                                @keydown.enter.prevent.stop="$store.installationModal.addMatch()"
                                @keydown.backspace="$store.installationModal.onBackspace($event)"
                                placeholder="{{ __('Type to search or press Enter to add...') }}" 
                                class="flex-1 min-w-[160px] bg-transparent border-0 p-1 text-xs text-stone-800 placeholder-stone-400 focus:outline-none focus:ring-0 leading-tight"
                            >
                        </div>
                        <p class="text-meta text-stone-400 mt-1">
                            <span class="font-medium text-stone-500">{{ __('Tip:') }}</span> {!! __('Type the name and press :enter to add. Press :backspace to remove the last one.', ['enter' => '<kbd class="px-1 py-0.5 text-meta bg-stone-100 border border-stone-200 rounded font-mono text-stone-600">Enter</kbd>', 'backspace' => '<kbd class="px-1 py-0.5 text-meta bg-stone-100 border border-stone-200 rounded font-mono text-stone-600">Backspace</kbd>']) !!}
                        </p>
                    </div>

                    <!-- 2. Catálogo de Tipos Disponibles (Chips clickeables) -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-semibold text-stone-600 tracking-wider">
                                <span x-text="$store.installationModal && $store.installationModal.search ? '{{ __('Matching suggestions') }}' : '{{ __('Available options') }}'"></span>
                            </span>
                            <template x-if="$store.installationModal && $store.installationModal.getFirstMatch() && $store.installationModal.search">
                                <span class="text-xs text-ink-3 font-medium">
                                    {{ __('Press ↵ to add') }} <strong class="font-semibold underline" x-text="$store.installationModal.getFirstMatch()"></strong>
                                </span>
                            </template>
                        </div>

                        <div class="flex flex-wrap gap-1.5 p-1 bg-stone-50/50 rounded-xl border border-stone-150 max-h-[220px] overflow-y-auto custom-vertical-scrollbar">
                            @foreach($installationTypes as $instType)
                                <button 
                                    type="button"
                                    x-show="$store.installationModal && $store.installationModal.matchesSearch('{{ addslashes($instType->name) }}')"
                                    @click.stop="$store.installationModal.toggleType('{{ addslashes($instType->name) }}')"
                                    :class="[
                                        $store.installationModal && $store.installationModal.isSelected('{{ addslashes($instType->name) }}') 
                                            ? 'ring-2 ring-stone-900/40 font-semibold shadow-sm scale-[1.02]' 
                                            : 'opacity-85 hover:opacity-100 hover:scale-[1.02] shadow-2xs',
                                        $store.installationModal && $store.installationModal.getFirstMatch() === '{{ addslashes($instType->name) }}' && $store.installationModal.search
                                            ? 'ring-2 ring-emerald-500 scale-[1.04]' 
                                            : ''
                                    ]"
                                    style="background-color: {{ $instType->bg_color }}; color: {{ $instType->text_color }}; border-color: {{ $instType->border_color }};"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-semibold border transition cursor-pointer select-none">
                                    <template x-if="$store.installationModal && $store.installationModal.isSelected('{{ addslashes($instType->name) }}')">
                                        <x-lucide-check class="w-3.5 h-3.5 stroke-[3]" />
                                    </template>
                                    <template x-if="!$store.installationModal || !$store.installationModal.isSelected('{{ addslashes($instType->name) }}')">
                                        <x-lucide-plus class="w-3.5 h-3.5 opacity-60" />
                                    </template>
                                    <span>{{ $instType->name }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="px-5 py-3 border-t border-stone-100 bg-stone-50/80 flex items-center justify-between">
                    <div>
                        <button 
                            type="button"
                            x-show="$store.installationModal && $store.installationModal.types.length > 0"
                            @click.stop="$store.installationModal.clearAll()"
                            class="text-xs text-red-600 hover:text-red-800 hover:bg-red-50 px-2.5 py-1.5 rounded-lg transition flex items-center gap-1.5 font-semibold cursor-pointer">
                            <x-lucide-trash-2 class="w-3.5 h-3.5" />
                            <span>{{ __('Clear selection') }}</span>
                        </button>
                    </div>

                    <div class="flex items-center gap-2">
                        <button 
                            type="button" 
                            @click.stop="$store.installationModal.close()" 
                            class="px-4 py-2 text-xs font-semibold text-white bg-ink hover:bg-black rounded-xl shadow-xs transition cursor-pointer flex items-center gap-1.5">
                            <x-lucide-check class="w-3.5 h-3.5 stroke-[2.5]" />
                            <span>{{ __('Save and close') }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </template>

    <!-- Dedicated Centered Substatus & Flags Modal Teleported to Body -->
    <template x-teleport="body">
        <div 
            x-data
            x-show="$store.substatusModal && $store.substatusModal.isOpen"
            x-cloak
            @open-substatus-modal.window="$store.substatusModal && $store.substatusModal.open($event.detail)"
            @keydown.escape.window="if ($store.substatusModal && $store.substatusModal.isOpen) $store.substatusModal.close()"
            @click.self="$store.substatusModal && $store.substatusModal.close()"
            class="fixed inset-0 z-[999999] flex items-center justify-center p-4"
            style="display: none;">
            
            <!-- Backdrop (clicking dark area closes) -->
            <div 
                x-show="$store.substatusModal && $store.substatusModal.isOpen"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 bg-stone-900/60 backdrop-blur-xs cursor-pointer"
                @click="$store.substatusModal.close()">
            </div>

            <!-- Modal Dialog Container (click inside does NOT close) -->
            <div 
                x-show="$store.substatusModal && $store.substatusModal.isOpen"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95 translate-y-2"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                x-transition:leave-end="opacity-0 scale-95 translate-y-2"
                @click.stop
                class="relative z-10 bg-white rounded-2xl shadow-2xl border border-stone-200 w-full max-w-md overflow-hidden flex flex-col max-h-[85vh]">
                
                <!-- Modal Header -->
                <div class="px-4 py-3 border-b border-stone-100 flex items-center justify-between bg-stone-50/70">
                    <div class="flex items-center gap-2 min-w-0">
                        <div class="w-7 h-7 rounded-lg bg-sunken text-ink-2 flex items-center justify-center shrink-0">
                            <x-lucide-activity class="w-4 h-4 text-ink-2" />
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5">
                                <h3 class="text-xs font-semibold text-stone-900 truncate">{{ __('Substatus and flags') }}</h3>
                                <template x-if="$store.substatusModal && $store.substatusModal.substatus">
                                    <span 
                                        class="text-meta px-1.5 py-0.2 rounded-full font-semibold border truncate"
                                        :style="$store.substatusModal.substatusStyle"
                                        x-text="$store.substatusModal.substatusLabel || $store.substatusModal.substatus">
                                    </span>
                                </template>
                            </div>
                            <p class="text-meta text-stone-500 flex items-center gap-1 truncate">
                                <span class="font-mono font-semibold text-stone-700" x-text="($store.substatusModal && $store.substatusModal.orderWo) || @js(__('No WO'))"></span>
                                <span class="text-stone-300">•</span>
                                <span class="truncate" x-text="($store.substatusModal && $store.substatusModal.orderCompany) || @js(__('No Client'))"></span>
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-1.5 shrink-0">
                        <a 
                            href="{{ route('settings.substatuses') }}" 
                            @click.stop="$store.substatusModal.close()" 
                            wire:navigate 
                            class="text-meta text-stone-400 hover:text-stone-700 flex items-center gap-1 py-1 px-1.5 rounded hover:bg-stone-100 transition cursor-pointer"
                            title="{{ __('Manage substatuses and colors') }}">
                            <x-lucide-settings class="w-3.5 h-3.5" />
                            <span class="hidden sm:inline">{{ __('Settings') }}</span>
                        </a>
                        <button 
                            type="button" 
                            @click="$store.substatusModal.close()" 
                            class="text-stone-400 hover:text-stone-700 hover:bg-stone-200/60 p-1 rounded-lg transition cursor-pointer"
                            title="{{ __('Close') }}">
                            <x-lucide-x class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                <!-- Modal Body -->
                <div class="p-4 overflow-y-auto space-y-3 custom-vertical-scrollbar flex-1 min-h-0">
                    <!-- 1. Buscador minimalista -->
                    <div class="relative flex items-center">
                        <x-lucide-search class="w-3.5 h-3.5 text-stone-400 absolute left-2.5 pointer-events-none" />
                        <input 
                            id="substatus-modal-search-input"
                            type="text" 
                            x-model="$store.substatusModal.search"
                            @keydown.enter.prevent.stop="$store.substatusModal.addMatch()"
                            placeholder="{{ __('Search substatus or flag...') }}" 
                            class="w-full bg-stone-100/80 hover:bg-stone-100 focus:bg-white text-xs text-stone-800 placeholder-stone-400 rounded-lg pl-8 pr-7 py-1.5 border border-transparent focus:border-line-strong transition focus:outline-none"
                        >
                        <button 
                            x-show="$store.substatusModal && $store.substatusModal.search" 
                            @click.stop="$store.substatusModal.search = ''; document.getElementById('substatus-modal-search-input')?.focus()" 
                            type="button" 
                            class="absolute right-2 text-stone-400 hover:text-stone-600 p-0.5 rounded transition cursor-pointer"
                            title="{{ __('Clear search') }}">
                            <x-lucide-x class="w-3 h-3" />
                        </button>
                    </div>

                    <!-- 2. Estado asignado actualmente (chips activos sin caja si no hay selección) -->
                    <div 
                        x-show="$store.substatusModal && ($store.substatusModal.substatus || ($store.substatusModal.flags && $store.substatusModal.flags.length > 0))"
                        class="flex flex-wrap items-center gap-1.5 pb-0.5">
                        <!-- Subestatus principal -->
                        <template x-if="$store.substatusModal && $store.substatusModal.substatus">
                            <span 
                                :style="$store.substatusModal.substatusStyle"
                                class="inline-flex items-center gap-1 pl-2 pr-1 py-0.5 rounded-md text-meta font-semibold border shadow-2xs leading-tight animate-in fade-in zoom-in-95 duration-100">
                                <span x-text="$store.substatusModal.substatusLabel || $store.substatusModal.substatus"></span>
                                <button 
                                    type="button" 
                                    @click.stop="$store.substatusModal.clearSubstatus()" 
                                    class="hover:bg-black/15 active:bg-black/25 rounded-full p-0.5 text-current cursor-pointer transition inline-flex items-center justify-center"
                                    title="{{ __('Remove substatus') }}">
                                    <x-lucide-x class="w-2.5 h-2.5 stroke-[2.5]" />
                                </button>
                            </span>
                        </template>

                        <!-- Banderas activas -->
                        <template x-for="flag in ($store.substatusModal ? $store.substatusModal.flags : [])" :key="flag">
                            <span 
                                :style="$store.substatusModal.getFlagStyle(flag)"
                                class="inline-flex items-center gap-1 pl-2 pr-1 py-0.5 rounded-md text-meta font-semibold border shadow-2xs leading-tight animate-in fade-in zoom-in-95 duration-100">
                                <span class="w-1.5 h-1.5 rounded-full shrink-0" :style="'background-color: ' + $store.substatusModal.getFlagSolid(flag)"></span>
                                <span x-text="$store.substatusModal.getFlagLabel(flag)"></span>
                                <button 
                                    type="button" 
                                    @click.stop="$store.substatusModal.toggleFlag(flag)" 
                                    class="hover:bg-black/15 active:bg-black/25 rounded-full p-0.5 text-current cursor-pointer transition inline-flex items-center justify-center"
                                    title="{{ __('Remove flag') }}">
                                    <x-lucide-x class="w-2.5 h-2.5 stroke-[2.5]" />
                                </button>
                            </span>
                        </template>
                    </div>

                    <!-- 3. Banderas Globales (Compactas, sin cajas gigantes) -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-meta font-semibold text-stone-500 tracking-wider flex items-center gap-1">
                                <x-lucide-flag class="w-3 h-3 text-ink-3" />
                                <span>{{ __('Global flags') }}</span>
                            </span>
                            <span class="text-meta text-stone-400">
                                {{ __('Multiple selection') }}
                            </span>
                        </div>
                        <div class="grid grid-cols-2 gap-1.5">
                            @foreach($globalFlagsData as $flag)
                                <button 
                                    type="button"
                                    x-show="!$store.substatusModal || $store.substatusModal.matchesSearch('{{ addslashes($flag['label']) }}') || $store.substatusModal.matchesSearch('{{ addslashes($flag['name']) }}')"
                                    @click.stop="$store.substatusModal.toggleFlag('{{ addslashes($flag['name']) }}')"
                                    :class="[
                                        $store.substatusModal && $store.substatusModal.isFlagSelected('{{ addslashes($flag['name']) }}') 
                                            ? 'ring-1 ring-stone-900/30 font-semibold shadow-2xs' 
                                            : 'bg-stone-50 hover:bg-stone-100/90 text-stone-700',
                                        $store.substatusModal && $store.substatusModal.getFirstMatch() && $store.substatusModal.getFirstMatch().value === '{{ addslashes($flag['name']) }}' && $store.substatusModal.search
                                            ? 'ring-2 ring-amber-500' 
                                            : ''
                                    ]"
                                    :style="$store.substatusModal && $store.substatusModal.isFlagSelected('{{ addslashes($flag['name']) }}') 
                                        ? 'background-color: {{ $flag['bg'] }}; color: {{ $flag['text'] }}; border-color: {{ $flag['border'] }}; font-weight: 700;' 
                                        : 'border-color: #e7e5e4;'"
                                    class="text-left px-2.5 py-1.5 rounded-lg text-xs font-semibold border transition cursor-pointer flex items-center justify-between select-none">
                                    <div class="flex items-center gap-2 truncate">
                                        <span class="w-2 h-2 rounded-full shrink-0" style="background-color: {{ $flag['solid'] }};"></span>
                                        <span class="truncate">{{ $flag['label'] }}</span>
                                    </div>
                                    <template x-if="$store.substatusModal && $store.substatusModal.isFlagSelected('{{ addslashes($flag['name']) }}')">
                                        <x-lucide-check class="w-3.5 h-3.5 stroke-[2.5] shrink-0" />
                                    </template>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <!-- 4. Clasificación de Proceso (1 Selección) -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-meta font-semibold text-stone-500 tracking-wider flex items-center gap-1">
                                <x-lucide-layers class="w-3 h-3 text-stone-400" />
                                <span>{{ __('Process classification (pick one)') }}</span>
                            </span>
                            <template x-if="$store.substatusModal && $store.substatusModal.getFirstMatch() && $store.substatusModal.getFirstMatch().type === 'substatus' && $store.substatusModal.search">
                                <span class="text-meta text-ink-3 font-medium">
                                    ↵ <strong class="font-semibold underline" x-text="$store.substatusModal.getFirstMatch().label"></strong>
                                </span>
                            </template>
                        </div>

                        <!-- Opción "Sin Subestatus" -->
                        <div class="mb-2" x-show="!$store.substatusModal || $store.substatusModal.matchesSearch(@js(mb_strtolower(__('No substatus')))) || $store.substatusModal.matchesSearch(@js(mb_strtolower(__('None'))))">
                            <button 
                                type="button"
                                @click.stop="$store.substatusModal.clearSubstatus()"
                                :class="!$store.substatusModal || !$store.substatusModal.substatus 
                                    ? 'bg-stone-800 text-white font-semibold shadow-2xs' 
                                    : 'bg-stone-100 hover:bg-stone-200/80 text-stone-600 font-medium'"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-meta transition cursor-pointer">
                                <template x-if="!$store.substatusModal || !$store.substatusModal.substatus">
                                    <x-lucide-check class="w-3 h-3 stroke-[2.5]" />
                                </template>
                                <span class="italic">-- {{ __('No Substatus') }} --</span>
                            </button>
                        </div>

                        <!-- Categorías con Chips de Subestatus DIRECTAS (sin caja dentro de caja) -->
                        <div class="space-y-2.5">
                            @foreach($groupedProcessSubstatuses as $groupKey => $group)
                                @php
                                    $searchTerms = collect($group['items'])->flatMap(function ($subItem) {
                                        $val = $subItem instanceof \App\Models\Substatus ? $subItem->name : $subItem->value;
                                        $enum = \App\Enums\Substatus::tryFrom($val);
                                        $lbl = $enum?->label() ?? $val;
                                        return [$val, $lbl];
                                    })->push($group['title'])->values();
                                @endphp
                                <div x-show="!$store.substatusModal || $store.substatusModal.groupHasMatches({{ \Illuminate\Support\Js::from($searchTerms->all()) }})" class="space-y-1">
                                    <div class="flex items-center gap-1.5 text-meta font-semibold tracking-wider text-stone-400">
                                        <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ $group['dot'] }}"></span>
                                        <span class="truncate">{{ $group['title'] }}</span>
                                    </div>

                                    <div class="flex flex-wrap gap-1">
                                        @foreach($group['items'] as $subItem)
                                            @php
                                                $itemValue = $subItem instanceof \App\Models\Substatus ? $subItem->name : $subItem->value;
                                                $itemEnum = \App\Enums\Substatus::tryFrom($itemValue);
                                                if ($itemEnum && $itemEnum->isGlobal()) continue;
                                                if ($subItem instanceof \App\Models\Substatus && $subItem->is_global) continue;
                                                $itemLabel = $itemEnum?->label() ?? $itemValue;
                                                
                                                if ($subItem instanceof \App\Models\Substatus && $subItem->bg_color && $subItem->text_color) {
                                                    $itemStyle = "background-color: {$subItem->bg_color}; color: {$subItem->text_color}; border-color: {$subItem->border_color};";
                                                } else {
                                                    $itemStyle = $itemEnum?->customBadgeStyle() ?? '';
                                                }

                                                $itemTone = \App\Livewire\Overview\OverviewIndex::substatusTone($itemValue);
                                                $itemFallbackClass = $itemTone === 'neutral' ? 'bg-sunken text-ink-2 border-line' : $toneClasses[$itemTone].' border-transparent';
                                            @endphp
                                            
                                            <button 
                                                type="button"
                                                x-show="!$store.substatusModal || $store.substatusModal.matchesSearch('{{ addslashes($itemLabel) }}') || $store.substatusModal.matchesSearch('{{ addslashes($itemValue) }}') || $store.substatusModal.matchesSearch('{{ addslashes($group['title']) }}')"
                                                @click.stop="$store.substatusModal.setSubstatus('{{ addslashes($itemValue) }}', '{{ addslashes($itemLabel) }}', '{{ addslashes($itemStyle) }}')"
                                                :class="[
                                                    $store.substatusModal && $store.substatusModal.isSelected('{{ addslashes($itemValue) }}') 
                                                        ? 'ring-2 ring-stone-900/40 font-semibold shadow-sm scale-[1.02]' 
                                                        : 'opacity-90 hover:opacity-100 hover:scale-[1.01]',
                                                    $store.substatusModal && $store.substatusModal.getFirstMatch() && $store.substatusModal.getFirstMatch().value === '{{ addslashes($itemValue) }}' && $store.substatusModal.search
                                                        ? 'ring-2 ring-amber-500 scale-[1.02]' 
                                                        : ''
                                                ]"
                                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-control text-meta font-medium border transition cursor-pointer select-none {{ $itemFallbackClass }}">
                                                <template x-if="$store.substatusModal && $store.substatusModal.isSelected('{{ addslashes($itemValue) }}')">
                                                    <x-lucide-check class="w-3 h-3 stroke-[2.5]" />
                                                </template>
                                                <span>{{ $itemLabel }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="px-4 py-2.5 border-t border-stone-100 bg-stone-50/70 flex items-center justify-between">
                    <div>
                        <button 
                            type="button"
                            x-show="$store.substatusModal && ($store.substatusModal.substatus || ($store.substatusModal.flags && $store.substatusModal.flags.length > 0))"
                            @click.stop="$store.substatusModal.clearAll()"
                            class="text-meta text-red-600 hover:text-red-800 hover:bg-red-50 px-2 py-1 rounded-md transition flex items-center gap-1 font-semibold cursor-pointer">
                            <x-lucide-trash-2 class="w-3 h-3" />
                            <span>{{ __('Empty') }}</span>
                        </button>
                    </div>

                    <div class="flex items-center gap-2">
                        <button 
                            type="button" 
                            @click.stop="$store.substatusModal.close()" 
                            class="px-3.5 py-1.5 text-xs font-semibold text-white bg-ink hover:bg-black rounded-lg shadow-xs transition cursor-pointer flex items-center gap-1.5">
                            <x-lucide-check class="w-3.5 h-3.5 stroke-[2.5]" />
                            <span>{{ __('Save and close') }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>

<script>
    (function() {
        const registerInstallationModalStore = () => {
            if (!window.Alpine) return;
            if (Alpine.store('installationModal')) return;

            Alpine.store('installationModal', {
                isOpen: false,
                orderId: null,
                orderWo: '',
                orderCompany: '',
                types: [],
                search: '',
                allTypes: {{ \Illuminate\Support\Js::from($installationTypes->pluck('name')) }},
                typesMap: {{ \Illuminate\Support\Js::from($installationTypes->keyBy('name')->map(fn($t) => [
                    'bg' => $t->bg_color,
                    'text' => $t->text_color,
                    'border' => $t->border_color ?: $t->bg_color,
                ])) }},

                getStyle(t) {
                    if (!t) return '';
                    const direct = this.typesMap[t];
                    if (direct && direct.bg) return `background-color: ${direct.bg}; color: ${direct.text}; border-color: ${direct.border};`;
                    const lower = String(t).toLowerCase().trim();
                    for (const [k, v] of Object.entries(this.typesMap)) {
                        if (k.toLowerCase().trim() === lower && v.bg) return `background-color: ${v.bg}; color: ${v.text}; border-color: ${v.border};`;
                    }
                    return 'background-color: #f5f5f4; color: #44403c; border-color: #e7e5e4;';
                },

                isSelected(t) {
                    if (!t || !this.types) return false;
                    const upper = String(t).toUpperCase().trim();
                    return this.types.some(item => String(item).toUpperCase().trim() === upper);
                },

                toggleType(t) {
                    if (!this.orderId || !t) return;
                    const upper = String(t).toUpperCase().trim();
                    let current = [...(this.types || [])];
                    const idx = current.findIndex(item => String(item).toUpperCase().trim() === upper);
                    if (idx !== -1) {
                        current.splice(idx, 1);
                    } else {
                        current.push(upper);
                    }
                    this.types = current;

                    // Update overview table cell immediately in 0ms
                    window.dispatchEvent(new CustomEvent('order-installation-changed', {
                        detail: { orderId: this.orderId, types: current }
                    }));

                    // Silently persist on backend
                    const wire = window.__overviewWire || (window.Livewire ? Livewire.first() : null);
                    if (wire && typeof wire.toggleInstallationType === 'function') {
                        wire.toggleInstallationType(this.orderId, upper);
                    }
                },

                clearAll() {
                    if (!this.orderId) return;
                    this.types = [];

                    window.dispatchEvent(new CustomEvent('order-installation-changed', {
                        detail: { orderId: this.orderId, types: [] }
                    }));

                    const wire = window.__overviewWire || (window.Livewire ? Livewire.first() : null);
                    if (wire && typeof wire.clearInstallationTypes === 'function') {
                        wire.clearInstallationTypes(this.orderId);
                    }
                },

                getFirstMatch() {
                    if (!this.search || !this.search.trim()) return null;
                    const clean = (str) => str.toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
                    const q = clean(this.search);
                    const unselected = this.allTypes.filter(t => !this.isSelected(t));
                    const matchUnselected = unselected.find(t => clean(t).includes(q));
                    if (matchUnselected) return matchUnselected;
                    return this.allTypes.find(t => clean(t).includes(q)) || null;
                },

                addMatch() {
                    if (this.search && this.search.trim()) {
                        const match = this.getFirstMatch();
                        if (match) {
                            this.toggleType(match);
                            this.search = '';
                            setTimeout(() => {
                                const input = document.getElementById('installation-modal-tag-input');
                                if (input) input.focus();
                            }, 30);
                        }
                    }
                },

                onBackspace(e) {
                    if (!this.search && (!e.target.selectionStart || e.target.selectionStart === 0)) {
                        if (this.types && this.types.length > 0) {
                            const last = this.types[this.types.length - 1];
                            this.toggleType(last);
                        }
                    }
                },

                matchesSearch(t) {
                    if (!this.search || !this.search.trim()) return true;
                    if (!t) return false;
                    const clean = (str) => str.toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
                    return clean(t).includes(clean(this.search));
                },

                open(detail) {
                    if (!detail) return;
                    this.orderId = detail.orderId;
                    this.orderWo = detail.wo || '';
                    this.orderCompany = detail.company || '';
                    this.types = Array.isArray(detail.types) ? [...detail.types] : (detail.types ? [detail.types] : []);
                    this.search = '';
                    this.isOpen = true;
                    setTimeout(() => {
                        const input = document.getElementById('installation-modal-tag-input');
                        if (input) input.focus();
                    }, 60);
                },

                close() {
                    this.isOpen = false;
                    this.search = '';
                }
            });
        };

        const registerSubstatusModalStore = () => {
            if (!window.Alpine) return;
            if (Alpine.store('substatusModal')) return;

            Alpine.store('substatusModal', {
                isOpen: false,
                orderId: null,
                orderWo: '',
                orderCompany: '',
                substatus: null,
                substatusLabel: '—',
                substatusStyle: '',
                flags: [],
                search: '',
                flagsMap: {{ \Illuminate\Support\Js::from($globalFlagsData) }},
                processList: {{ \Illuminate\Support\Js::from($processFlatList) }},
                substatusesMap: {{ \Illuminate\Support\Js::from($substatusStyleMap) }},
                archivedNames: {{ \Illuminate\Support\Js::from(\App\Models\Substatus::getArchivedNames()) }},

                clean(str) {
                    return (str || '').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
                },

                matchesSearch(text) {
                    if (!this.search || !this.search.trim()) return true;
                    if (!text) return false;
                    return this.clean(text).includes(this.clean(this.search));
                },

                groupHasMatches(terms) {
                    if (!this.search || !this.search.trim()) return true;
                    const q = this.clean(this.search);
                    return terms.some(t => this.clean(t).includes(q));
                },

                isSelected(val) {
                    if (!val || !this.substatus) return false;
                    return String(val).toUpperCase().trim() === String(this.substatus).toUpperCase().trim();
                },

                isFlagSelected(flag) {
                    if (!flag || !this.flags) return false;
                    const upper = String(flag).toUpperCase().trim();
                    return this.flags.some(f => String(f).toUpperCase().trim() === upper);
                },

                getFlagStyle(flag) {
                    const f = this.flagsMap[flag] || this.flagsMap[String(flag).toUpperCase()];
                    if (f && f.bg) {
                        return `background-color: ${f.bg}; color: ${f.text}; border-color: ${f.border};`;
                    }
                    return 'background-color: var(--cc-camila-bg-light); color: var(--cc-camila-text-dark); border-color: var(--cc-camila-border);';
                },

                getFlagSolid(flag) {
                    const f = this.flagsMap[flag] || this.flagsMap[String(flag).toUpperCase()];
                    return (f && f.solid) ? f.solid : '#6B7280';
                },

                getFlagLabel(flag) {
                    const f = this.flagsMap[flag] || this.flagsMap[String(flag).toUpperCase()];
                    return (f && f.label) ? f.label : flag;
                },

                getFirstMatch() {
                    if (!this.search || !this.search.trim()) return null;
                    const q = this.clean(this.search);
                    // Check flags first
                    for (const [k, v] of Object.entries(this.flagsMap)) {
                        if (this.clean(k).includes(q) || this.clean(v.label).includes(q)) {
                            return { type: 'flag', value: k, label: v.label, solid: v.solid };
                        }
                    }
                    // Then check process substatuses
                    for (const item of this.processList) {
                        if (this.clean(item.value).includes(q) || this.clean(item.label).includes(q)) {
                            return { type: 'substatus', value: item.value, label: item.label, style: item.style };
                        }
                    }
                    return null;
                },

                addMatch() {
                    const match = this.getFirstMatch();
                    if (!match) return;
                    if (match.type === 'flag') {
                        this.toggleFlag(match.value);
                    } else if (match.type === 'substatus') {
                        this.setSubstatus(match.value, match.label, match.style);
                    }
                    this.search = '';
                    setTimeout(() => {
                        const input = document.getElementById('substatus-modal-search-input');
                        if (input) input.focus();
                    }, 30);
                },

                setSubstatus(status, label, style) {
                    if (!this.orderId) return;
                    const upperStatus = status ? String(status).toUpperCase().trim() : null;
                    const upperLabel = label ? String(label).toUpperCase().trim() : (upperStatus || '—');
                    const finalStyle = style || (upperStatus && this.substatusesMap[upperStatus] ? this.substatusesMap[upperStatus] : '');

                    // Toggle off if already selected
                    if (this.substatus && upperStatus && String(this.substatus).toUpperCase().trim() === upperStatus) {
                        this.clearSubstatus();
                        return;
                    }

                    this.substatus = upperStatus;
                    this.substatusLabel = upperLabel;
                    this.substatusStyle = finalStyle;

                    const isArch = (this.archivedNames || []).some(n => String(n).toUpperCase().trim() === upperStatus);

                    window.dispatchEvent(new CustomEvent('order-substatus-changed', {
                        detail: {
                            orderId: this.orderId,
                            substatus: upperStatus,
                            substatusLabel: upperLabel,
                            substatusStyle: finalStyle,
                            flags: this.flags,
                            isArchived: isArch
                        }
                    }));

                    const wire = window.__overviewWire || (window.Livewire ? Livewire.first() : null);
                    if (wire && typeof wire.updateSubstatus === 'function') {
                        wire.updateSubstatus(this.orderId, upperStatus);
                    }
                },

                toggleFlag(flag) {
                    if (!this.orderId || !flag) return;
                    const upper = String(flag).toUpperCase().trim();
                    let current = [...(this.flags || [])];
                    const idx = current.findIndex(f => String(f).toUpperCase().trim() === upper);
                    if (idx !== -1) {
                        current.splice(idx, 1);
                    } else {
                        current.push(upper);
                    }
                    this.flags = current;

                    window.dispatchEvent(new CustomEvent('order-substatus-changed', {
                        detail: {
                            orderId: this.orderId,
                            substatus: this.substatus,
                            substatusLabel: this.substatusLabel,
                            substatusStyle: this.substatusStyle,
                            flags: current
                        }
                    }));

                    const wire = window.__overviewWire || (window.Livewire ? Livewire.first() : null);
                    if (wire && typeof wire.toggleFlag === 'function') {
                        wire.toggleFlag(this.orderId, upper);
                    }
                },

                clearSubstatus() {
                    if (!this.orderId) return;
                    this.substatus = null;
                    this.substatusLabel = '—';
                    this.substatusStyle = '';

                    window.dispatchEvent(new CustomEvent('order-substatus-changed', {
                        detail: {
                            orderId: this.orderId,
                            substatus: null,
                            substatusLabel: '—',
                            substatusStyle: '',
                            flags: this.flags
                        }
                    }));

                    const wire = window.__overviewWire || (window.Livewire ? Livewire.first() : null);
                    if (wire && typeof wire.updateSubstatus === 'function') {
                        wire.updateSubstatus(this.orderId, null);
                    }
                },

                clearAll() {
                    if (!this.orderId) return;
                    const flagsToClear = [...(this.flags || [])];
                    this.substatus = null;
                    this.substatusLabel = '—';
                    this.substatusStyle = '';
                    this.flags = [];

                    window.dispatchEvent(new CustomEvent('order-substatus-changed', {
                        detail: {
                            orderId: this.orderId,
                            substatus: null,
                            substatusLabel: '—',
                            substatusStyle: '',
                            flags: []
                        }
                    }));

                    const wire = window.__overviewWire || (window.Livewire ? Livewire.first() : null);
                    if (wire) {
                        if (typeof wire.updateSubstatus === 'function') {
                            wire.updateSubstatus(this.orderId, null);
                        }
                        flagsToClear.forEach(f => {
                            if (typeof wire.toggleFlag === 'function') {
                                wire.toggleFlag(this.orderId, f);
                            }
                        });
                    }
                },

                open(detail) {
                    if (!detail) return;
                    this.orderId = detail.orderId;
                    this.orderWo = detail.wo || '';
                    this.orderCompany = detail.company || '';
                    this.substatus = detail.substatus || null;
                    this.substatusLabel = detail.substatusLabel || (detail.substatus || '—');
                    this.substatusStyle = detail.substatusStyle || (this.substatus && this.substatusesMap[this.substatus] ? this.substatusesMap[this.substatus] : '');
                    this.flags = Array.isArray(detail.flags) ? [...detail.flags] : (detail.flags ? [detail.flags] : []);
                    this.search = '';
                    this.isOpen = true;
                    setTimeout(() => {
                        const input = document.getElementById('substatus-modal-search-input');
                        if (input) input.focus();
                    }, 60);
                },

                close() {
                    this.isOpen = false;
                    this.search = '';
                }
            });
        };

        if (window.Alpine) {
            registerInstallationModalStore();
            registerSubstatusModalStore();
        } else {
            document.addEventListener('alpine:init', () => {
                registerInstallationModalStore();
                registerSubstatusModalStore();
            });
        }
    })();
</script>

<style>
    .menu-item-active {
        outline: 2px solid #0284c7 !important;
        outline-offset: -2px !important;
    }
</style>
