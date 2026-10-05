<div 
    x-data="{
        ordersState: {{ \Illuminate\Support\Js::from($ordersState ?? []) }},
        lastServerTimestamp: {{ now()->timestamp }},
        isSyncing: false,
        lastSyncTime: '',
        pollTimer: null,
        columns: [
            'proc_date',
            'due_date',
            'wo',
            'client',
            'name',
            'designer',
            'prod_note',
            'invoice',
            'email_date',
            'installation',
            'check',
            'deliv_note',
            'substatus'
        ],
        defaultColWidths: {
            proc_date: 6,
            due_date: 6,
            wo: 6,
            client: 12,
            name: 14,
            designer: 7.5,
            prod_note: 12,
            invoice: 7,
            email_date: 6,
            installation: 6,
            check: 3.5,
            deliv_note: 7,
            substatus: 7
        },
        colWidths: (() => {
            const defaults = {
                proc_date: 6,
                due_date: 6,
                wo: 6,
                client: 12,
                name: 14,
                designer: 7.5,
                prod_note: 12,
                invoice: 7,
                email_date: 6,
                installation: 6,
                check: 3.5,
                deliv_note: 7,
                substatus: 7
            };
            try {
                localStorage.removeItem('overview_col_widths');
                const savedStr = localStorage.getItem('overview_col_widths_pct');
                if (!savedStr) return Object.assign({}, defaults);
                const saved = JSON.parse(savedStr);
                let total = 0;
                for (let k in defaults) {
                    if (typeof saved[k] !== 'number' || saved[k] <= 0 || saved[k] > 50) {
                        return Object.assign({}, defaults);
                    }
                    total += saved[k];
                }
                if (Math.abs(total - 100) > 2) {
                    return Object.assign({}, defaults);
                }
                return Object.assign({}, defaults, saved);
            } catch (err) {
                return Object.assign({}, defaults);
            }
        })(),
        resizingDivider: null,
        init() {
            this.lastSyncTime = new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            this.startPolling();
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) {
                    this.syncState();
                }
            });
        },
        startPolling() {
            if (this.pollTimer) clearInterval(this.pollTimer);
            this.pollTimer = setInterval(() => {
                if (!document.hidden && !$wire.editingOrderId && !this.activeMenu) {
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
        getRowClass(orderId, defaultClass) {
            const st = this.ordersState[orderId];
            if (!st || !st.core_status) return defaultClass;
            if (st.core_status === 'ARCHIVED') return 'bg-cyan-100/90 hover:bg-cyan-200/90 text-cyan-950 font-semibold';
            if (st.core_status === 'EN PRODUCCIÓN') return 'bg-orange-200/90 hover:bg-orange-300/90 text-orange-950 font-semibold';
            return 'hover:bg-stone-50/80';
        },
        initResize(e, leftCol, rightCol = null) {
            const leftIdx = this.columns.indexOf(leftCol);
            if (leftIdx === -1) return;
            const rightKey = rightCol || this.columns[leftIdx + 1];
            if (!rightKey) return;

            const tableEl = this.$refs.ordersTable;
            const tableWidth = tableEl ? tableEl.getBoundingClientRect().width : (window.innerWidth - 300);
            if (!tableWidth || tableWidth <= 0) return;

            const startLeftWidth = Number(this.colWidths[leftCol] ?? this.defaultColWidths[leftCol] ?? 6);
            const startRightWidth = Number(this.colWidths[rightKey] ?? this.defaultColWidths[rightKey] ?? 6);
            const pairTotal = Math.round((startLeftWidth + startRightWidth) * 100) / 100;
            const startX = e.pageX;
            const minColWidth = 2.0;

            if (pairTotal <= minColWidth * 2) return;

            this.resizingDivider = leftCol;
            document.body.style.userSelect = 'none';
            document.body.style.cursor = 'col-resize';

            const onMove = (mv) => {
                if (mv.buttons === 0) {
                    onUp();
                    return;
                }
                const deltaPx = mv.pageX - startX;
                const deltaPct = (deltaPx / tableWidth) * 100;

                let newLeft = startLeftWidth + deltaPct;
                if (newLeft < minColWidth) {
                    newLeft = minColWidth;
                } else if (newLeft > pairTotal - minColWidth) {
                    newLeft = pairTotal - minColWidth;
                }

                newLeft = Math.round(newLeft * 100) / 100;
                let newRight = Math.round((pairTotal - newLeft) * 100) / 100;

                this.colWidths[leftCol] = newLeft;
                this.colWidths[rightKey] = newRight;
            };

            const onUp = () => {
                document.body.style.userSelect = '';
                document.body.style.cursor = '';
                this.resizingDivider = null;
                localStorage.setItem('overview_col_widths_pct', JSON.stringify(this.colWidths));
                window.removeEventListener('mousemove', onMove);
                window.removeEventListener('mouseup', onUp);
            };

            window.addEventListener('mousemove', onMove);
            window.addEventListener('mouseup', onUp);
        },
        resetDivider(leftCol, rightCol = null) {
            const leftIdx = this.columns.indexOf(leftCol);
            if (leftIdx === -1) return;
            const rightKey = rightCol || this.columns[leftIdx + 1];
            if (!rightKey) return;

            const defLeft = this.defaultColWidths[leftCol] ?? 6;
            const defRight = this.defaultColWidths[rightKey] ?? 6;
            const currentTotal = (this.colWidths[leftCol] || defLeft) + (this.colWidths[rightKey] || defRight);
            const defTotal = defLeft + defRight;
            const ratio = defLeft / defTotal;

            const newLeft = Math.round(currentTotal * ratio * 100) / 100;
            const newRight = Math.round((currentTotal - newLeft) * 100) / 100;

            this.colWidths[leftCol] = newLeft;
            this.colWidths[rightKey] = newRight;
            localStorage.setItem('overview_col_widths_pct', JSON.stringify(this.colWidths));
        },
        resetColWidths() {
            this.colWidths = Object.assign({}, this.defaultColWidths);
            localStorage.setItem('overview_col_widths_pct', JSON.stringify(this.colWidths));
        },
        activeMenu: null,
        targetOrderId: null,
        targetSubstatus: null,
        targetInstallationType: null,
        targetFlags: [],
        menuStyle: '',
        menuSearch: '',
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
        updateMenuPosition() {
            if (!this.activeMenu || !this.activeTriggerEl) return;
            if (!document.body.contains(this.activeTriggerEl)) return;
            const rect = this.activeTriggerEl.getBoundingClientRect();
            const spaceBelow = window.innerHeight - rect.bottom;

            let menuHeight = 240;
            let menuWidth = 220;
            if (this.activeMenu === 'substatus') { menuHeight = 460; menuWidth = 295; }
            if (this.activeMenu === 'designer') { menuHeight = 280; menuWidth = 230; }
            if (this.activeMenu === 'installation') { menuHeight = 340; menuWidth = 260; }
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
            const st = this.ordersState[orderId] || {};
            this.targetSubstatus = st.substatus !== undefined ? st.substatus : (extraData.substatus !== undefined ? extraData.substatus : null);
            this.targetInstallationType = st.installation_type !== undefined ? st.installation_type : (extraData.installationType !== undefined ? extraData.installationType : null);
            this.targetFlags = Array.isArray(st.flags) ? st.flags : (Array.isArray(extraData.flags) ? extraData.flags : []);

            this.updateMenuPosition();

            this.$nextTick(() => {
                const searchEl = this.$refs.popoverContainer ? this.$refs.popoverContainer.querySelector('[data-menu-search]') : null;
                if (searchEl) {
                    searchEl.focus({ preventScroll: true });
                }
            });
        },
        closeMenu() {
            this.activeMenu = null;
            this.targetOrderId = null;
            this.targetSubstatus = null;
            this.targetInstallationType = null;
            this.targetFlags = [];
            this.menuSearch = '';
            this.activeTriggerEl = null;
        },
        setDesigner(dId, dName, badgeStyle, inlineStyle) {
            const orderId = this.targetOrderId;
            this.closeMenu();
            if (orderId) {
                if (!this.ordersState[orderId]) this.ordersState[orderId] = {};
                this.ordersState[orderId].designer_id = dId;
                if (dName !== undefined) this.ordersState[orderId].designer_name = dName;
                if (badgeStyle !== undefined) this.ordersState[orderId].designer_badge_style = badgeStyle;
                if (inlineStyle !== undefined) this.ordersState[orderId].designer_badge_inline_style = inlineStyle;
                $wire.updateDesigner(orderId, dId);
            }
        },
        installationTypesMap: {{ \Illuminate\Support\Js::from($installationTypes->keyBy('name')->map(fn($t) => [
            'bg' => $t->bg_color,
            'text' => $t->text_color,
            'border' => $t->border_color ?: $t->bg_color,
        ])) }},
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
            return '';
        },
        getInstallationClass(type) {
            if (!type) return 'bg-transparent text-stone-400 hover:bg-stone-50';
            if (this.getInstallationStyle(type)) return 'border shadow-2xs font-bold';
            return 'bg-stone-100 text-stone-700 font-semibold';
        },
        getReviewCellClass(status) {
            if (status === 'CS') return 'bg-pink-100 text-pink-900 font-bold';
            if (status === 'CAMILA') return 'font-bold';
            return 'bg-transparent text-stone-700';
        },
        getReviewCellStyle(status) {
            if (status === 'CAMILA') return 'background-color: var(--cc-camila-bg-light); color: var(--cc-camila-text-dark);';
            return '';
        },
        getReviewIconColor(status) {
            if (status === 'CS') return 'text-pink-700 hover:bg-pink-200/80';
            if (status === 'CAMILA') return 'hover:opacity-80';
            return 'text-stone-400 hover:text-stone-800';
        },
        getReviewIconStyle(status) {
            if (status === 'CAMILA') return 'color: var(--cc-camila-solid);';
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
                this.ordersState[orderId] = Object.assign({}, this.ordersState[orderId] || {}, { installation_type: type });
                $wire.updateInstallationType(orderId, type);
            }
        },
        setSubstatus(status, label, style) {
            const orderId = this.targetOrderId;
            this.closeMenu();
            if (orderId) {
                if (!this.ordersState[orderId]) this.ordersState[orderId] = {};
                this.ordersState[orderId].substatus = status;
                if (label !== undefined) this.ordersState[orderId].substatus_label = label;
                if (style !== undefined) this.ordersState[orderId].substatus_style = style;
                $wire.updateSubstatus(orderId, status);
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
    @keydown.escape.window="closeMenu()"
    @scroll.passive="if (activeMenu) updateMenuPosition()"
    @scroll.window.passive="if (activeMenu) updateMenuPosition()"
    @resize.window.passive="if (activeMenu) updateMenuPosition()"
    class="h-full w-full max-w-full overflow-y-auto space-y-4 pb-32 px-1">

    <!-- Top Summary Metrics & Filtering Cards Bar (3 Cards) -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 w-full">
            <!-- 1. Órdenes Activas (Workspace) -->
            <div 
                wire:click="setTab('workspace')"
                class="p-3.5 rounded-xl border transition-all duration-150 flex flex-col justify-between cursor-pointer select-none group {{ $activeTab === 'workspace' ? 'bg-emerald-50/40 border-emerald-500 ring-2 ring-emerald-500/20 shadow-xs' : 'bg-white border-stone-200 hover:border-stone-300 hover:shadow-2xs' }}"
                title="{{ __('Clic para filtrar Órdenes Activas en Workspace') }}">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <span class="text-[11px] font-bold uppercase tracking-wider block transition-colors {{ $activeTab === 'workspace' ? 'text-emerald-800' : 'text-stone-500 group-hover:text-stone-700' }}">
                            {{ __('Órdenes Activas') }}
                        </span>
                        <div class="flex items-baseline gap-2 mt-0.5">
                            <span class="text-2xl font-extrabold text-stone-900 leading-tight">
                                {{ $totalWorkspaceCount }}
                            </span>
                            <span class="text-[11px] text-stone-400 font-medium lowercase">
                                {{ __('en workspace') }}
                            </span>
                        </div>
                    </div>
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center font-bold shrink-0 transition-colors {{ $activeTab === 'workspace' ? 'bg-emerald-500 text-white shadow-2xs' : 'bg-emerald-50 text-emerald-600 group-hover:bg-emerald-100' }}">
                        <x-lucide-activity class="w-5 h-5" />
                    </div>
                </div>
                <div class="mt-2.5 pt-2 border-t border-stone-100 flex items-center justify-between text-[11px] text-stone-500">
                    <span class="flex items-center gap-1 font-medium {{ $activeTab === 'workspace' ? 'text-emerald-700 font-semibold' : 'text-stone-500' }}">
                        <x-lucide-zap class="w-3.5 h-3.5 {{ $activeTab === 'workspace' ? 'text-emerald-600' : 'text-stone-400' }}" />
                        {{ __('Ver sólo Órdenes Activas') }}
                    </span>
                    @if(!empty($missingWoCount) && $missingWoCount > 0)
                        <span class="inline-flex items-center gap-1 px-1.5 py-0.2 rounded text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200" title="{{ __('Órdenes activas sin número WO') }}">
                            <x-lucide-alert-circle class="w-3 h-3 text-amber-500" />
                            {{ $missingWoCount }} {{ __('Sin WO') }}
                        </span>
                    @endif
                </div>
            </div>

            <!-- 2. En Producción -->
            <div 
                wire:click="setTab('production')"
                class="p-3.5 rounded-xl border transition-all duration-150 flex flex-col justify-between cursor-pointer select-none group {{ $activeTab === 'production' ? 'bg-pink-50/40 border-pink-500 ring-2 ring-pink-500/20 shadow-xs' : 'bg-white border-stone-200 hover:border-stone-300 hover:shadow-2xs' }}"
                title="{{ __('Clic para filtrar Órdenes En Producción') }}">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <span class="text-[11px] font-bold uppercase tracking-wider block transition-colors {{ $activeTab === 'production' ? 'text-pink-800' : 'text-stone-500 group-hover:text-stone-700' }}">
                            {{ __('En Producción') }}
                        </span>
                        <div class="flex items-baseline gap-2 mt-0.5">
                            <span class="text-2xl font-extrabold text-pink-600 leading-tight">
                                {{ $inProductionCount }}
                            </span>
                            @if(!empty($inWorkspaceProductionCount) && $inWorkspaceProductionCount > 0)
                                <span class="text-[11px] text-stone-400 font-medium">
                                    ({{ $inWorkspaceProductionCount }} {{ __('en workspace') }})
                                </span>
                            @endif
                        </div>
                    </div>
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center font-bold shrink-0 transition-colors {{ $activeTab === 'production' ? 'bg-pink-600 text-white shadow-2xs' : 'bg-pink-50 text-pink-600 group-hover:bg-pink-100' }}">
                        <x-lucide-layers class="w-5 h-5" />
                    </div>
                </div>
                <div class="mt-2.5 pt-2 border-t border-stone-100 flex items-center justify-between text-[11px] text-stone-500">
                    <span class="flex items-center gap-1 font-medium {{ $activeTab === 'production' ? 'text-pink-700 font-semibold' : 'text-stone-500' }}">
                        <x-lucide-filter class="w-3.5 h-3.5 {{ $activeTab === 'production' ? 'text-pink-600' : 'text-stone-400' }}" />
                        {{ __('Ver sólo Producción') }}
                    </span>
                    <span class="text-[10px] text-stone-400 font-medium">
                        {{ __('Fabricación activa') }}
                    </span>
                </div>
            </div>

            <!-- 3. Órdenes Archivadas (con sus subestatus) -->
            <div 
                wire:click="setTab('archived', 'all')"
                class="p-3.5 rounded-xl border transition-all duration-150 flex flex-col justify-between cursor-pointer select-none group {{ $activeTab === 'archived' ? 'bg-cyan-50/40 border-cyan-500 ring-2 ring-cyan-500/20 shadow-xs' : 'bg-white border-stone-200 hover:border-stone-300 hover:shadow-2xs' }}"
                title="{{ __('Clic para filtrar Órdenes Archivadas') }}">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <span class="text-[11px] font-bold uppercase tracking-wider block transition-colors {{ $activeTab === 'archived' ? 'text-cyan-800' : 'text-stone-500 group-hover:text-stone-700' }}">
                            {{ __('Órdenes Archivadas') }}
                        </span>
                        <div class="flex items-baseline gap-2 mt-0.5">
                            <span class="text-2xl font-extrabold text-cyan-800 leading-tight">
                                {{ $totalArchivedCount }}
                            </span>
                            <span class="text-[11px] text-stone-400 font-medium lowercase">
                                {{ __('Órdenes Finalizadas') }}
                            </span>
                        </div>
                    </div>
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center font-bold shrink-0 transition-colors {{ $activeTab === 'archived' ? 'bg-cyan-600 text-white shadow-2xs' : 'bg-cyan-50 text-cyan-600 group-hover:bg-cyan-100' }}">
                        <x-lucide-archive class="w-5 h-5" />
                    </div>
                </div>
                <!-- Subestatus Pills Bar -->
                <div class="mt-2.5 pt-2 border-t border-stone-100 flex items-center gap-1.5 flex-wrap">
                    @foreach($archivedSubstatusFilters as $filterKey => $filter)
                        @php
                            $isSelected = ($activeTab === 'archived' && $archivedSubstatus === $filterKey);
                            $pillStyle = '';
                            if ($filterKey === 'all') {
                                $pillClass = $isSelected 
                                    ? 'bg-cyan-700 text-white shadow-2xs border-cyan-800' 
                                    : 'bg-stone-100 text-stone-600 hover:bg-stone-200 border-stone-200/80';
                            } else {
                                if ($isSelected) {
                                    $solid = $filter['solid_bg'] ?? '#0E7490';
                                    $pillClass = 'shadow-2xs font-black text-white';
                                    $pillStyle = "background-color: {$solid}; border-color: {$solid}; color: #ffffff;";
                                } else {
                                    $bg = $filter['bg_color'] ?? '#F5F5F4';
                                    $txt = $filter['text_color'] ?? '#57534E';
                                    $bd = $filter['border_color'] ?? '#E7E5E4';
                                    $pillClass = 'hover:opacity-85 font-extrabold';
                                    $pillStyle = "background-color: {$bg}; color: {$txt}; border-color: {$bd};";
                                }
                            }
                        @endphp
                        <button 
                            type="button"
                            wire:click.stop="setArchivedSubstatus('{{ $filterKey }}')"
                            @if(!empty($pillStyle)) style="{{ $pillStyle }}" @endif
                            class="px-2 py-0.5 rounded text-[10.5px] transition cursor-pointer border {{ $pillClass }}"
                            title="{{ $filter['label'] }}">
                            {{ $filter['label'] }} ({{ $filter['count'] }})
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

    <!-- Toolbar Filters Bar -->
    <div class="bg-white rounded-xl border border-stone-200 p-3.5 shadow-2xs space-y-3 w-full">
        <div class="flex flex-wrap items-center justify-between gap-3 pb-2 border-b border-stone-100">
            <div class="flex items-center gap-3 flex-wrap">
                <div class="min-w-0">
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-zinc-900 tracking-tight">{{ __('Overview') }}</h1>
                </div>

                <!-- View Mode Tabs (TODAS | ÓRDENES ACTIVAS | EN PRODUCCIÓN | ARCHIVADAS | BACKLOG) -->
                <div class="inline-flex items-center gap-1 bg-stone-100 p-1 rounded-xl border border-stone-200 flex-wrap">
                    <button 
                        wire:click="setTab('all')"
                        class="uppercase px-3 py-1 rounded-lg text-xs font-bold transition cursor-pointer {{ $activeTab === 'all' ? 'bg-white text-stone-900 shadow-2xs' : 'text-stone-500 hover:text-stone-900' }}">
                        {{ __('Todas') }}
                    </button>
                    <button 
                        wire:click="setTab('workspace')"
                        class="uppercase px-3 py-1 rounded-lg text-xs font-bold transition cursor-pointer flex items-center gap-1.5 {{ $activeTab === 'workspace' ? 'bg-white text-emerald-800 shadow-2xs' : 'text-stone-500 hover:text-stone-900' }}">
                        <x-lucide-zap class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                        <span>{{ __('Órdenes Activas') }}</span>
                        <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-emerald-100 text-emerald-800 font-extrabold">{{ $totalWorkspaceCount }}</span>
                    </button>
                    <button 
                        wire:click="setTab('production')"
                        class="uppercase px-3 py-1 rounded-lg text-xs font-bold transition cursor-pointer flex items-center gap-1.5 {{ $activeTab === 'production' ? 'bg-white text-pink-800 shadow-2xs' : 'text-stone-500 hover:text-stone-900' }}">
                        <x-lucide-layers class="w-3.5 h-3.5 text-pink-600 shrink-0" />
                        <span>{{ __('En Producción') }}</span>
                        <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-pink-100 text-pink-800 font-extrabold">{{ $inProductionCount }}</span>
                    </button>
                    <button 
                        wire:click="setTab('archived', 'all')"
                        class="uppercase px-3 py-1 rounded-lg text-xs font-bold transition cursor-pointer flex items-center gap-1.5 {{ $activeTab === 'archived' ? 'bg-white text-cyan-800 shadow-2xs' : 'text-stone-500 hover:text-stone-900' }}">
                        <x-lucide-archive class="w-3.5 h-3.5 text-cyan-600 shrink-0" />
                        <span>{{ __('Archivadas') }}</span>
                        <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-cyan-100 text-cyan-800 font-extrabold">{{ $totalArchivedCount }}</span>
                    </button>
                    <button 
                        wire:click="setTab('backlog')"
                        class="uppercase px-3 py-1 rounded-lg text-xs font-bold transition cursor-pointer flex items-center gap-1.5 {{ $activeTab === 'backlog' ? 'bg-white text-amber-800 shadow-2xs' : 'text-stone-500 hover:text-stone-900' }}">
                        <x-lucide-inbox class="w-3.5 h-3.5 text-amber-600 shrink-0" />
                        <span>{{ __('Backlog') }}</span>
                        <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-amber-100 text-amber-800 font-extrabold">{{ $totalBacklogCount }}</span>
                    </button>
                </div>
            </div>

            <!-- Global Search & Reset Buttons -->
            <div class="flex items-center gap-2 flex-1 justify-end min-w-[240px]">
                <div class="relative flex-1">
                    <x-lucide-search class="w-4 h-4 text-stone-400 absolute left-2.5 top-2.5" />
                    <input 
                        type="text" 
                        wire:model.live.debounce.300ms="search"
                        placeholder="{{ __('Buscar en DOES (WO#, cliente, empresa, trabajo...)...') }}"
                        class="w-full pl-8 pr-3 py-1.5 bg-stone-50 border border-stone-200 rounded-lg text-xs focus:ring-2 focus:ring-stone-900 focus:bg-white transition"
                    >
                </div>

                @if($search || $filterWo || $filterClient || $filterDesigner || $filterReviewStatus || $filterInstallation || $filterDateRange)
                    <button 
                        wire:click="resetFilters" 
                        class="px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200 transition flex items-center gap-1.5 cursor-pointer shrink-0">
                        <x-lucide-rotate-ccw class="w-3.5 h-3.5" />
                        {{ __('Limpiar') }}
                    </button>
                @endif
            </div>
        </div>

        <!-- Filters Row -->
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-2">
            <!-- Filter WO -->
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-stone-400 mb-1">{{ __('Filtro WO') }}</label>
                <input 
                    type="text" 
                    wire:model.live.debounce.300ms="filterWo"
                    placeholder="ej. WO 1234 o Sin WO"
                    class="w-full px-2 py-1 bg-stone-50 border border-stone-200 rounded-md text-xs focus:ring-1 focus:ring-stone-900 focus:bg-white"
                >
            </div>

            <!-- Filter Client -->
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-stone-400 mb-1">{{ __('Cliente') }}</label>
                <select 
                    wire:model.live="filterClient" 
                    class="w-full px-2 py-1 bg-stone-50 border border-stone-200 rounded-md text-xs focus:ring-1 focus:ring-stone-900 focus:bg-white">
                    <option value="">{{ __('Todos los clientes') }}</option>
                    @foreach($clients as $c)
                        <option value="{{ is_object($c) ? $c->id : $c }}">{{ is_object($c) ? $c->name : $c }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Filter Designer -->
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-stone-400 mb-1">{{ __('Diseñador') }}</label>
                <select 
                    wire:model.live="filterDesigner" 
                    class="w-full px-2 py-1 bg-stone-50 border border-stone-200 rounded-md text-xs focus:ring-1 focus:ring-stone-900 focus:bg-white">
                    <option value="">{{ __('Todos') }}</option>
                    @foreach($filterDesigners as $d)
                        <option value="{{ is_object($d) ? $d->id : $d }}">{{ is_object($d) ? $d->name : $d }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Filter Review Status -->
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-stone-400 mb-1">{{ __('Revisión Estimado') }}</label>
                <select 
                    wire:model.live="filterReviewStatus" 
                    class="w-full px-2 py-1 bg-stone-50 border border-stone-200 rounded-md text-xs focus:ring-1 focus:ring-stone-900 focus:bg-white">
                    <option value="">{{ __('Todas') }}</option>
                    <option value="CS">{{ __('Revisado por CS') }}</option>
                    <option value="CAMILA">{{ __('Revisado por Camila') }}</option>
                    <option value="NONE">{{ __('Sin revisión') }}</option>
                </select>
            </div>

            <!-- Filter Installation -->
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-stone-400 mb-1">{{ __('Instalación') }}</label>
                <select 
                    wire:model.live="filterInstallation" 
                    class="w-full px-2 py-1 bg-stone-50 border border-stone-200 rounded-md text-xs focus:ring-1 focus:ring-stone-900 focus:bg-white">
                    <option value="">{{ __('Todas') }}</option>
                    @foreach($installationTypes as $instType)
                        <option value="{{ $instType->name }}">{{ $instType->name }}</option>
                    @endforeach
                    <option value="NONE">{{ __('Vacío (Sin información)') }}</option>
                </select>
            </div>
        </div>
    </div>

    @php
        $getDesignerBadgeStyle = function($designerName) {
            if (!$designerName) return 'bg-stone-100 text-stone-700 border-stone-200';
            return match($designerName) {
                'Euralíz' => 'bg-fuchsia-500 text-white font-semibold border-fuchsia-600 shadow-2xs',
                'César' => 'bg-cyan-500 text-white font-semibold border-cyan-600 shadow-2xs',
                'Adrián' => 'bg-emerald-500 text-white font-semibold border-emerald-600 shadow-2xs',
                default => 'bg-amber-400 text-amber-950 font-semibold border-amber-500 shadow-2xs',
            };
        };
    @endphp

    <!-- Single Unified Orders Data Grid (11 Columns Layout) -->
    <div 
        x-data="{ 
            headerHeight: 49,
            init() {
                const updateHeight = () => {
                    if (this.$refs.headerBar) {
                        this.headerHeight = this.$refs.headerBar.offsetHeight;
                    }
                };
                updateHeight();
                this.$nextTick(updateHeight);
                if (window.ResizeObserver && this.$refs.headerBar) {
                    new ResizeObserver(updateHeight).observe(this.$refs.headerBar);
                }
            }
        }"
        :style="'--table-header-h: ' + headerHeight + 'px;'"
        class="bg-white rounded-xl border border-stone-200 shadow-2xs w-full relative">
        <!-- Table Header Bar -->
        <div 
            x-ref="headerBar"
            class="sticky top-0 z-30 w-full px-4 py-3 bg-[#f7f7f5] border-b border-stone-200 flex flex-wrap items-center justify-between gap-2.5 rounded-t-xl">
            <div class="flex items-center gap-2.5 flex-wrap min-w-0">
                @if($activeTab === 'workspace')
                    <x-lucide-zap class="w-5 h-5 text-emerald-600 shrink-0" />
                @elseif($activeTab === 'production')
                    <x-lucide-layers class="w-5 h-5 text-pink-600 shrink-0" />
                @elseif($activeTab === 'backlog')
                    <x-lucide-inbox class="w-5 h-5 text-amber-600 shrink-0" />
                @elseif($activeTab === 'archived')
                    <x-lucide-archive class="w-5 h-5 text-cyan-600 shrink-0" />
                @else
                    <x-lucide-layout-grid class="w-5 h-5 text-stone-500 shrink-0" />
                @endif

                <h3 class="font-bold text-sm text-stone-900 tracking-tight shrink-0">
                    @if($activeTab === 'workspace')
                        {{ __('Todas las Órdenes Activas') }}
                    @elseif($activeTab === 'production')
                        {{ __('Órdenes en Producción') }}
                    @elseif($activeTab === 'backlog')
                        {{ __('Órdenes en Backlog') }}
                    @elseif($activeTab === 'archived')
                        {{ __('Órdenes Archivadas') }}
                        @if(!empty($archivedSubstatus) && $archivedSubstatus !== 'all')
                            <span class="text-cyan-700 font-semibold">• {{ ucfirst(str_replace('_', ' ', $archivedSubstatus)) }}</span>
                        @endif
                    @else
                        {{ __('Todas las Órdenes') }}
                    @endif
                </h3>

                @if(!empty($appliedFilters))
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="text-stone-300 font-light select-none">|</span>
                        @foreach($appliedFilters as $filter)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium bg-white text-stone-700 border border-stone-200 shadow-2xs">
                                <span class="text-stone-400 font-normal">{{ $filter['label'] }}:</span>
                                <span class="font-bold text-stone-900 max-w-[180px] truncate" title="{{ $filter['value'] }}">{{ $filter['value'] }}</span>
                                <button 
                                    type="button" 
                                    wire:click="clearFilter('{{ $filter['key'] }}')" 
                                    class="text-stone-400 hover:text-stone-700 hover:bg-stone-100 rounded p-0.5 transition cursor-pointer"
                                    title="{{ __('Quitar filtro') }}">
                                    <x-lucide-x class="w-2.5 h-2.5" />
                                </button>
                            </span>
                        @endforeach

                        @if(count($appliedFilters) > 1)
                            <button 
                                type="button" 
                                wire:click="resetFilters" 
                                class="text-[11px] font-semibold text-rose-600 hover:text-rose-700 hover:underline cursor-pointer ml-1 select-none">
                                {{ __('Limpiar todos') }}
                            </button>
                        @endif
                    </div>
                @endif

                <span class="hidden px-2.5 py-0.5 rounded-full text-xs font-extrabold bg-stone-200 text-stone-800">
                    {{ method_exists($orders, 'total') ? $orders->total() : count($orders) }}
                </span>
            </div>

            <div class="text-xs text-stone-500 font-medium hidden sm:flex items-center">
                <div class="inline-flex items-center gap-1.5 flex-wrap">
                    @if($activeTab !== 'all')
                        <button 
                            type="button"
                            wire:click="setTab('all')"
                            class="inline-flex items-center gap-1 font-semibold text-stone-600 hover:text-stone-900 hover:bg-stone-200/70 px-2 py-0.5 rounded-md transition cursor-pointer text-[11px]"
                            title="{{ __('Ver todas las órdenes') }}">
                            <x-lucide-layout-grid class="w-3 h-3 text-stone-500" />
                            <span>{{ __('Todas') }}</span>
                        </button>
                        <span class="text-stone-300">•</span>
                    @endif
                    <button 
                        type="button"
                        wire:click="setTab('workspace')"
                        class="inline-flex items-center gap-1 font-semibold transition cursor-pointer px-1.5 py-0.5 rounded-md {{ $activeTab === 'workspace' ? 'bg-emerald-100 text-emerald-900 ring-1 ring-emerald-500/30 shadow-2xs' : 'text-emerald-700 hover:text-emerald-900 hover:bg-emerald-50' }}"
                        title="{{ __('Ver Órdenes Activas') }}">
                        <x-lucide-zap class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                        <span>{{ $totalWorkspaceCount }} {{ __('Activas') }}</span>
                    </button>
                    <span class="text-stone-300">•</span>
                    <button 
                        type="button"
                        wire:click="setTab('production')"
                        class="inline-flex items-center gap-1 font-semibold transition cursor-pointer px-1.5 py-0.5 rounded-md {{ $activeTab === 'production' ? 'bg-pink-100 text-pink-900 ring-1 ring-pink-500/30 shadow-2xs' : 'text-pink-700 hover:text-pink-900 hover:bg-pink-50' }}"
                        title="{{ __('Ver Órdenes en Producción') }}">
                        <x-lucide-layers class="w-3.5 h-3.5 text-pink-600 shrink-0" />
                        <span>{{ $inProductionCount }} {{ __('Producción') }}</span>
                    </button>
                    <span class="text-stone-300">•</span>
                    <button 
                        type="button"
                        wire:click="setTab('backlog')"
                        class="inline-flex items-center gap-1 font-semibold transition cursor-pointer px-1.5 py-0.5 rounded-md {{ $activeTab === 'backlog' ? 'bg-amber-100 text-amber-900 ring-1 ring-amber-500/30 shadow-2xs' : 'text-amber-700 hover:text-amber-900 hover:bg-amber-50' }}"
                        title="{{ __('Ver Órdenes en Backlog') }}">
                        <x-lucide-inbox class="w-3.5 h-3.5 text-amber-600 shrink-0" />
                        <span>{{ $totalBacklogCount }} {{ __('Backlog') }}</span>
                    </button>
                    <span class="text-stone-300">•</span>
                    <button 
                        type="button"
                        wire:click="setTab('archived', 'all')"
                        class="inline-flex items-center gap-1 font-semibold transition cursor-pointer px-1.5 py-0.5 rounded-md {{ $activeTab === 'archived' ? 'bg-cyan-100 text-cyan-900 ring-1 ring-cyan-500/30 shadow-2xs' : 'text-cyan-700 hover:text-cyan-900 hover:bg-cyan-50' }}"
                        title="{{ __('Ver Órdenes Archivadas') }}">
                        <x-lucide-archive class="w-3.5 h-3.5 text-cyan-600 shrink-0" />
                        <span>{{ $totalArchivedCount }} {{ __('Archivadas') }}</span>
                    </button>
                </div>
            </div>
        </div>

        <div class="w-full">
            <table x-ref="ordersTable" class="w-full table-fixed text-left text-xs border-collapse">
                <thead class="sticky z-20 bg-stone-50 shadow-2xs" style="top: var(--table-header-h, 49px);">
                    <tr class="bg-stone-50 border-b border-stone-200 text-[10px] uppercase font-bold text-stone-500 tracking-wider">
                        <!-- 1. Fecha Procesado en Producción -->
                        <th 
                            :style="'width: ' + (colWidths['proc_date'] || 6) + '%; top: var(--table-header-h, 49px);'"
                            class="sticky z-20 bg-stone-50 border-b border-stone-200 shadow-2xs relative py-2.5 px-1 cursor-pointer hover:bg-stone-100 select-none group/col transition-colors"
                            wire:click="sortByColumn('production_processed_at')">
                            <div class="flex items-center justify-between gap-0.5 w-full pointer-events-none overflow-hidden">
                                <span class="truncate" title="Procesado en Producción">Proc. Prod.</span>
                                <x-lucide-arrow-up-down class="w-3 h-3 text-stone-400 shrink-0" />
                            </div>
                            <div 
                                @mousedown.stop.prevent="initResize($event, 'proc_date')"
                                @dblclick.stop.prevent="resetDivider('proc_date')"
                                @click.stop.prevent
                                :class="resizingDivider === 'proc_date' ? 'bg-emerald-500 opacity-100' : 'hover:bg-emerald-500/60 group-hover/col:bg-stone-300'"
                                class="absolute right-0 top-0 bottom-0 w-3 cursor-col-resize transition z-30"
                                title="Arrastrar para redimensionar">
                            </div>
                        </th>

                        <!-- 2. Order Due Date -->
                        <th 
                            :style="'width: ' + (colWidths['due_date'] || 6) + '%; top: var(--table-header-h, 49px);'"
                            class="sticky z-20 bg-stone-50 border-b border-stone-200 shadow-2xs relative py-2.5 px-1 cursor-pointer hover:bg-stone-100 select-none group/col transition-colors"
                            wire:click="sortByColumn('delivery_due_date')">
                            <div class="flex items-center justify-between gap-0.5 w-full pointer-events-none overflow-hidden">
                                <span class="truncate" title="Due Date (Fecha Límite de Entrega)">Due Date</span>
                                <x-lucide-arrow-up-down class="w-3 h-3 text-stone-400 shrink-0" />
                            </div>
                            <div 
                                @mousedown.stop.prevent="initResize($event, 'due_date')"
                                @dblclick.stop.prevent="resetDivider('due_date')"
                                @click.stop.prevent
                                :class="resizingDivider === 'due_date' ? 'bg-emerald-500 opacity-100' : 'hover:bg-emerald-500/60 group-hover/col:bg-stone-300'"
                                class="absolute right-0 top-0 bottom-0 w-3 cursor-col-resize transition z-30"
                                title="Arrastrar para redimensionar">
                            </div>
                        </th>

                        <!-- 3. WO # -->
                        <th 
                            :style="'width: ' + (colWidths['wo'] || 6) + '%; top: var(--table-header-h, 49px);'"
                            class="sticky z-20 bg-stone-50 border-b border-stone-200 shadow-2xs relative py-2.5 px-1 cursor-pointer hover:bg-stone-100 select-none group/col transition-colors"
                            wire:click="sortByColumn('wo_number')">
                            <div class="flex items-center justify-between gap-0.5 w-full pointer-events-none overflow-hidden">
                                <span class="truncate" title="WO #">WO #</span>
                                <x-lucide-arrow-up-down class="w-3 h-3 text-stone-400 shrink-0" />
                            </div>
                            <div 
                                @mousedown.stop.prevent="initResize($event, 'wo')"
                                @dblclick.stop.prevent="resetDivider('wo')"
                                @click.stop.prevent
                                :class="resizingDivider === 'wo' ? 'bg-emerald-500 opacity-100' : 'hover:bg-emerald-500/60 group-hover/col:bg-stone-300'"
                                class="absolute right-0 top-0 bottom-0 w-3 cursor-col-resize transition z-30"
                                title="Arrastrar para redimensionar">
                            </div>
                        </th>

                        <!-- 4. Client -->
                        <th 
                            :style="'width: ' + (colWidths['client'] || 12) + '%; top: var(--table-header-h, 49px);'"
                            class="sticky z-20 bg-stone-50 border-b border-stone-200 shadow-2xs relative py-2.5 px-1.5 cursor-pointer hover:bg-stone-100 select-none group/col transition-colors"
                            wire:click="sortByColumn('company_name')">
                            <div class="flex items-center justify-between gap-0.5 w-full pointer-events-none overflow-hidden">
                                <span class="truncate" title="Cliente">Cliente</span>
                                <x-lucide-arrow-up-down class="w-3 h-3 text-stone-400 shrink-0" />
                            </div>
                            <div 
                                @mousedown.stop.prevent="initResize($event, 'client')"
                                @dblclick.stop.prevent="resetDivider('client')"
                                @click.stop.prevent
                                :class="resizingDivider === 'client' ? 'bg-emerald-500 opacity-100' : 'hover:bg-emerald-500/60 group-hover/col:bg-stone-300'"
                                class="absolute right-0 top-0 bottom-0 w-3 cursor-col-resize transition z-30"
                                title="Arrastrar para redimensionar">
                            </div>
                        </th>

                        <!-- 5. Order Name -->
                        <th 
                            :style="'width: ' + (colWidths['name'] || 14) + '%; top: var(--table-header-h, 49px);'"
                            class="sticky z-20 bg-stone-50 border-b border-stone-200 shadow-2xs relative py-2.5 px-1.5 cursor-pointer hover:bg-stone-100 select-none group/col transition-colors"
                            wire:click="sortByColumn('task_name')">
                            <div class="flex items-center justify-between gap-0.5 w-full pointer-events-none overflow-hidden">
                                <span class="truncate" title="Nombre de Orden">Nombre de Orden</span>
                                <x-lucide-arrow-up-down class="w-3 h-3 text-stone-400 shrink-0" />
                            </div>
                            <div 
                                @mousedown.stop.prevent="initResize($event, 'name')"
                                @dblclick.stop.prevent="resetDivider('name')"
                                @click.stop.prevent
                                :class="resizingDivider === 'name' ? 'bg-emerald-500 opacity-100' : 'hover:bg-emerald-500/60 group-hover/col:bg-stone-300'"
                                class="absolute right-0 top-0 bottom-0 w-3 cursor-col-resize transition z-30"
                                title="Arrastrar para redimensionar">
                            </div>
                        </th>

                        <!-- 6. Designer -->
                        <th 
                            :style="'width: ' + (colWidths['designer'] || 7.5) + '%; top: var(--table-header-h, 49px);'"
                            class="sticky z-20 bg-stone-50 border-b border-stone-200 shadow-2xs relative py-2.5 px-1 select-none group/col">
                            <div class="flex items-center justify-between gap-0.5 w-full pointer-events-none overflow-hidden">
                                <span class="truncate" title="Diseñador">Diseñador</span>
                            </div>
                            <div 
                                @mousedown.stop.prevent="initResize($event, 'designer')"
                                @dblclick.stop.prevent="resetDivider('designer')"
                                @click.stop.prevent
                                :class="resizingDivider === 'designer' ? 'bg-emerald-500 opacity-100' : 'hover:bg-emerald-500/60 group-hover/col:bg-stone-300'"
                                class="absolute right-0 top-0 bottom-0 w-3 cursor-col-resize transition z-30"
                                title="Arrastrar para redimensionar">
                            </div>
                        </th>

                        <!-- 7. Nota Producción / Instalación -->
                        <th 
                            :style="'width: ' + (colWidths['prod_note'] || 12) + '%; top: var(--table-header-h, 49px);'"
                            class="sticky z-20 bg-stone-50 border-b border-stone-200 shadow-2xs relative py-2.5 px-1.5 select-none group/col">
                            <div class="flex items-center justify-between gap-0.5 w-full pointer-events-none overflow-hidden">
                                <span class="truncate" title="Nota Producción/Instalación">Nota Prod./Inst.</span>
                            </div>
                            <div 
                                @mousedown.stop.prevent="initResize($event, 'prod_note')"
                                @dblclick.stop.prevent="resetDivider('prod_note')"
                                @click.stop.prevent
                                :class="resizingDivider === 'prod_note' ? 'bg-emerald-500 opacity-100' : 'hover:bg-emerald-500/60 group-hover/col:bg-stone-300'"
                                class="absolute right-0 top-0 bottom-0 w-3 cursor-col-resize transition z-30"
                                title="Arrastrar para redimensionar">
                            </div>
                        </th>

                        <!-- 8. Estimado / Invoice -->
                        <th 
                            :style="'width: ' + (colWidths['invoice'] || 7) + '%; top: var(--table-header-h, 49px);'"
                            class="sticky z-20 bg-stone-50 border-b border-stone-200 shadow-2xs relative py-2.5 px-1 select-none group/col">
                            <div class="flex items-center justify-between gap-0.5 w-full pointer-events-none overflow-hidden">
                                <span class="truncate" title="Estimado / Invoice">Est. / Inv.</span>
                            </div>
                            <div 
                                @mousedown.stop.prevent="initResize($event, 'invoice')"
                                @dblclick.stop.prevent="resetDivider('invoice')"
                                @click.stop.prevent
                                :class="resizingDivider === 'invoice' ? 'bg-emerald-500 opacity-100' : 'hover:bg-emerald-500/60 group-hover/col:bg-stone-300'"
                                class="absolute right-0 top-0 bottom-0 w-3 cursor-col-resize transition z-30"
                                title="Arrastrar para redimensionar">
                            </div>
                        </th>

                        <!-- 8.5. Fecha Email -->
                        <th 
                            :style="'width: ' + (colWidths['email_date'] || 6) + '%; top: var(--table-header-h, 49px);'"
                            class="sticky z-20 bg-stone-50 border-b border-stone-200 shadow-2xs relative py-2.5 px-1 cursor-pointer hover:bg-stone-100 select-none group/col transition-colors"
                            wire:click="sortByColumn('email_date')">
                            <div class="flex items-center justify-between gap-0.5 w-full pointer-events-none overflow-hidden">
                                <span class="truncate" title="Fecha Email">Email</span>
                                <x-lucide-arrow-up-down class="w-3 h-3 text-stone-400 shrink-0" />
                            </div>
                            <div 
                                @mousedown.stop.prevent="initResize($event, 'email_date')"
                                @dblclick.stop.prevent="resetDivider('email_date')"
                                @click.stop.prevent
                                :class="resizingDivider === 'email_date' ? 'bg-emerald-500 opacity-100' : 'hover:bg-emerald-500/60 group-hover/col:bg-stone-300'"
                                class="absolute right-0 top-0 bottom-0 w-3 cursor-col-resize transition z-30"
                                title="Arrastrar para redimensionar">
                            </div>
                        </th>

                        <!-- 9. Instalación -->
                        <th 
                            :style="'width: ' + (colWidths['installation'] || 6) + '%; top: var(--table-header-h, 49px);'"
                            class="sticky z-20 bg-stone-50 border-b border-stone-200 shadow-2xs relative py-2.5 px-1 select-none group/col">
                            <div class="flex items-center justify-between gap-0.5 w-full pointer-events-none overflow-hidden">
                                <span class="truncate" title="Instalación">Instalación</span>
                            </div>
                            <div 
                                @mousedown.stop.prevent="initResize($event, 'installation')"
                                @dblclick.stop.prevent="resetDivider('installation')"
                                @click.stop.prevent
                                :class="resizingDivider === 'installation' ? 'bg-emerald-500 opacity-100' : 'hover:bg-emerald-500/60 group-hover/col:bg-stone-300'"
                                class="absolute right-0 top-0 bottom-0 w-3 cursor-col-resize transition z-30"
                                title="Arrastrar para redimensionar">
                            </div>
                        </th>

                        <!-- 10. CHECK MARK -->
                        <th 
                            :style="'width: ' + (colWidths['check'] || 3.5) + '%; top: var(--table-header-h, 49px);'"
                            class="sticky z-20 bg-stone-50 border-b border-stone-200 shadow-2xs relative py-2.5 px-0.5 text-center select-none group/col">
                            <div class="flex items-center justify-center gap-0.5 w-full pointer-events-none overflow-hidden">
                                <x-lucide-check class="w-3.5 h-3.5 text-stone-400" />
                            </div>
                            <div 
                                @mousedown.stop.prevent="initResize($event, 'check')"
                                @dblclick.stop.prevent="resetDivider('check')"
                                @click.stop.prevent
                                :class="resizingDivider === 'check' ? 'bg-emerald-500 opacity-100' : 'hover:bg-emerald-500/60 group-hover/col:bg-stone-300'"
                                class="absolute right-0 top-0 bottom-0 w-3 cursor-col-resize transition z-30"
                                title="Arrastrar para redimensionar">
                            </div>
                        </th>

                        <!-- 11. Nota de Entrega -->
                        <th 
                            :style="'width: ' + (colWidths['deliv_note'] || 7) + '%; top: var(--table-header-h, 49px);'"
                            class="sticky z-20 bg-stone-50 border-b border-stone-200 shadow-2xs relative py-2.5 px-1.5 select-none group/col">
                            <div class="flex items-center justify-between gap-0.5 w-full pointer-events-none overflow-hidden">
                                <span class="truncate" title="Nota de Entrega">Entrega</span>
                            </div>
                            <div 
                                @mousedown.stop.prevent="initResize($event, 'deliv_note')"
                                @dblclick.stop.prevent="resetDivider('deliv_note')"
                                @click.stop.prevent
                                :class="resizingDivider === 'deliv_note' ? 'bg-emerald-500 opacity-100' : 'hover:bg-emerald-500/60 group-hover/col:bg-stone-300'"
                                class="absolute right-0 top-0 bottom-0 w-3 cursor-col-resize transition z-30"
                                title="Arrastrar para redimensionar">
                            </div>
                        </th>

                        <!-- 12. Subestatus -->
                        <th 
                            :style="'width: ' + (colWidths['substatus'] || 7) + '%; top: var(--table-header-h, 49px);'"
                            class="sticky z-20 bg-stone-50 border-b border-stone-200 shadow-2xs relative py-2.5 px-1.5 cursor-pointer hover:bg-stone-100 select-none group/col transition-colors"
                            wire:click="sortByColumn('substatus')">
                            <div class="flex items-center justify-between gap-0.5 w-full pointer-events-none overflow-hidden">
                                <span class="truncate" title="Subestatus">Subestatus</span>
                                <x-lucide-arrow-up-down class="w-3 h-3 text-stone-400 shrink-0" />
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100 font-medium text-[11px]">
                    @forelse($orders as $order)
                        @php
                            $isProd = ($order->core_status === \App\Enums\CoreStatus::EN_PRODUCCION) 
                                || ($order->core_status?->value === 'EN PRODUCCIÓN') 
                                || ($order->core_status === 'EN PRODUCCIÓN');

                            $isArchived = ($order->core_status === \App\Enums\CoreStatus::ARCHIVED) 
                                || ($order->core_status?->value === 'ARCHIVED') 
                                || ($order->core_status === 'ARCHIVED')
                                || !empty($order->archived_at);

                            $subVal = $order->substatus?->value ?? (is_string($order->substatus) ? $order->substatus : null);

                            $rowStyle = match(true) {
                                $isArchived => 'bg-cyan-100/90 hover:bg-cyan-200/90 text-cyan-950 font-semibold',
                                $isProd => 'bg-orange-200/90 hover:bg-orange-300/90 text-orange-950 font-semibold',
                                default => 'hover:bg-stone-50/80',
                            };
                        @endphp
                        <tr 
                            data-order-id="{{ $order->id }}"
                            :class="getRowClass({{ $order->id }}, '{{ $rowStyle }}')"
                            class="transition-colors duration-75 group relative {{ $rowStyle }}">
                            <!-- 1. Fecha Procesado en Producción -->
                            @php
                                $procDateVal = $order->production_processed_at ? $order->production_processed_at->format('Y-m-d') : '';
                                $procDateDisplay = $order->production_processed_at ? $order->production_processed_at->format('d/m/Y') : '—';
                                $hasProcDate = !empty($procDateVal);
                            @endphp
                            <td class="py-1 px-1.5 truncate">
                                <div x-data="{ editing: false }" class="w-full flex items-center min-w-0">
                                    @if(!$hasProcDate)
                                        <button 
                                            type="button" 
                                            x-show="!editing"
                                            @click="editing = true; $nextTick(() => { if ($refs.procInput.showPicker) { try { $refs.procInput.showPicker(); } catch(e){} } $refs.procInput.focus(); })" 
                                            class="w-full text-left text-stone-400 hover:text-stone-700 text-[10px] font-mono px-1 py-0.5 rounded-sm transition-colors cursor-pointer"
                                            title="Sin fecha - Clic para asignar fecha de procesado">
                                            —
                                        </button>
                                    @endif
                                    <input 
                                        x-ref="procInput"
                                        @if(!$hasProcDate) x-show="editing" x-cloak @endif
                                        type="date"
                                        data-initial="{{ $procDateVal }}"
                                        value="{{ $procDateVal }}"
                                        @input.stop
                                        @blur="if (!$el.value) editing = false;"
                                        @change.stop="
                                            if ($el.value !== $el.dataset.initial) {
                                                $el.dataset.initial = $el.value;
                                                $wire.quickUpdateField({{ $order->id }}, 'production_processed_at', $el.value);
                                            }
                                            if (!$el.value) editing = false;
                                        "
                                        class="w-full bg-transparent hover:bg-stone-100/60 focus:bg-white text-[10px] text-stone-700 font-mono px-1 py-0.5 rounded-sm border-0 border-b border-transparent focus:border-stone-400 focus:outline-none focus:ring-0 transition-colors cursor-pointer"
                                        title="{{ $procDateDisplay }} (Procesado en Producción - Clic para editar)"
                                    >
                                </div>
                            </td>

                            <!-- 2. Order Due Date (Delivery Deadline) -->
                            @php
                                $dueDateVal = $order->delivery_due_date ? $order->delivery_due_date->format('Y-m-d') : '';
                                $dueDateDisplay = $order->delivery_due_date ? $order->delivery_due_date->format('d/m/Y') : '—';
                                $isDueDateFilled = !empty($dueDateVal);
                            @endphp
                            <td class="py-1 px-1.5 truncate">
                                <div x-data="{ editing: false }" class="w-full flex items-center min-w-0">
                                    @if(!$isDueDateFilled)
                                        <button 
                                            type="button" 
                                            x-show="!editing"
                                            @click="editing = true; $nextTick(() => { if ($refs.dueInput.showPicker) { try { $refs.dueInput.showPicker(); } catch(e){} } $refs.dueInput.focus(); })" 
                                            class="w-full text-left text-stone-400 hover:text-stone-700 text-[10px] font-mono px-1 py-0.5 rounded-sm transition-colors cursor-pointer"
                                            title="Sin Due Date - Clic para asignar fecha de entrega">
                                            —
                                        </button>
                                    @endif
                                    <input 
                                        x-ref="dueInput"
                                        @if(!$isDueDateFilled) x-show="editing" x-cloak @endif
                                        type="date"
                                        data-initial="{{ $dueDateVal }}"
                                        value="{{ $dueDateVal }}"
                                        @input.stop
                                        @blur="if (!$el.value) editing = false;"
                                        @change.stop="
                                            if ($el.value !== $el.dataset.initial) {
                                                $el.dataset.initial = $el.value;
                                                $wire.quickUpdateField({{ $order->id }}, 'delivery_due_date', $el.value);
                                            }
                                            if (!$el.value) editing = false;
                                        "
                                        class="w-full text-[10px] font-mono px-1 py-0.5 rounded-sm border-0 transition-colors cursor-pointer {{ $isDueDateFilled ? 'bg-red-600 text-white font-bold shadow-2xs focus:ring-1 focus:ring-red-700' : 'bg-transparent hover:bg-stone-100/60 focus:bg-white text-stone-700 border-b border-transparent focus:border-stone-400 focus:outline-none focus:ring-0' }}"
                                        style="{{ $isDueDateFilled ? 'color-scheme: dark;' : '' }}"
                                        title="{{ $dueDateDisplay }} (Due Date / Fecha Límite de Entrega - Clic para editar)"
                                    >
                                </div>
                            </td>

                            <!-- 3. WO # (Click opens modal if exists + Backlog pill if in_workspace is false) -->
                            <td class="py-1 px-1.5 truncate">
                                <div class="flex items-center gap-1 overflow-hidden truncate">
                                    @if(! $order->in_workspace)
                                        <button 
                                            wire:click="moveToWorkspace({{ $order->id }})"
                                            class="inline-flex items-center gap-0.5 px-1 py-0.2 rounded text-[9px] font-bold bg-amber-100 text-amber-900 border border-amber-300 hover:bg-amber-200 transition cursor-pointer shrink-0"
                                            title="En Backlog - Clic para mover al Workspace">
                                            <x-lucide-inbox class="w-3 h-3 text-amber-800" />
                                        </button>
                                    @endif

                                    @if($order->hasNoWo())
                                        <input 
                                            type="text" 
                                            value=""
                                            placeholder="+ WO"
                                            @input.stop
                                            @keydown.enter.stop.prevent="$el.blur()"
                                            @keydown.escape.stop.prevent="$el.value = ''; $el.blur()"
                                            @blur="
                                                const val = $el.value.trim();
                                                if (val) {
                                                    $wire.quickUpdateField({{ $order->id }}, 'wo_number', val);
                                                }
                                            "
                                            class="w-full bg-rose-50 hover:bg-rose-100/80 focus:bg-white text-rose-800 focus:text-stone-900 placeholder-rose-400 text-[10px] font-mono font-bold px-1.5 py-0.5 rounded-sm border-0 border-b border-rose-300 focus:border-stone-400 focus:outline-none focus:ring-0 transition-colors truncate"
                                            title="Sin WO - Clic para agregar número de WO"
                                        >
                                    @else
                                        <button 
                                            wire:click="$dispatch('open-order-detail', { orderId: {{ $order->id }} })"
                                            class="font-mono font-bold text-stone-900 hover:text-emerald-700 hover:underline truncate cursor-pointer text-left block"
                                            title="{{ $order->wo_number }} - Ver Detalle">
                                            {{ $order->wo_number }}
                                        </button>
                                    @endif
                                </div>
                            </td>

                            <!-- 4. Cliente (Click opens modal) -->
                            <td class="py-1 px-1.5 truncate">
                                <button 
                                    wire:click="$dispatch('open-order-detail', { orderId: {{ $order->id }} })"
                                    class="font-semibold text-stone-800 hover:text-emerald-700 hover:underline truncate cursor-pointer text-left w-full block uppercase"
                                    title="{{ $order->company_name ?: ($order->client?->name ?? '—') }}">
                                    {{ $order->company_name ?: ($order->client?->name ?? '—') }}
                                </button>
                            </td>

                            <!-- 5. Order Name (Click opens modal) -->
                            <td class="py-1 px-1.5 truncate">
                                <button 
                                    wire:click="$dispatch('open-order-detail', { orderId: {{ $order->id }} })"
                                    class="text-stone-700 font-medium hover:text-stone-900 hover:underline truncate cursor-pointer text-left w-full block uppercase"
                                    title="{{ $order->clean_task_name }}">
                                    {{ $order->clean_task_name }}
                                </button>
                            </td>

                            <!-- 6. Designer (Full Color Cell) -->
                            <td 
                                :class="ordersState[{{ $order->id }}]?.designer_badge_style || '{{ $order->getDesignerBadgeStyle() }}'"
                                :style="ordersState[{{ $order->id }}]?.designer_badge_inline_style || '{{ $order->getDesignerBadgeInlineStyle() }}'"
                                class="py-1 px-1.5 truncate transition text-[10px] font-semibold text-center {{ $order->getDesignerBadgeStyle() }}"
                                style="{{ $order->getDesignerBadgeInlineStyle() }}">
                                <button 
                                    type="button"
                                    data-popover-trigger="designer"
                                    @click.stop="openMenu('designer', {{ $order->id }}, $el)"
                                    :title="ordersState[{{ $order->id }}]?.designer_name || '{{ addslashes($order->designer_name) }}'"
                                    class="w-full text-center cursor-pointer border-none bg-transparent py-0.5 truncate block"
                                    title="{{ $order->designer_name }}">
                                    <span class="truncate block" x-text="ordersState[{{ $order->id }}]?.designer_name || '{{ addslashes($order->designer_name) }}'">{{ $order->designer_name }}</span>
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
                                    class="w-full bg-transparent hover:bg-stone-100/70 focus:bg-white text-[11px] text-stone-700 italic px-1.5 py-0.5 rounded-sm border-0 border-b border-transparent focus:border-stone-400 focus:outline-none focus:ring-0 transition-colors truncate placeholder-stone-400 focus:text-stone-900 focus:not-italic"
                                    title="{{ $order->production_note ?: 'Clic para editar nota de producción' }}"
                                >
                            </td>

                            <!-- 8. Estimado / Invoice (Clean grid cell styling, NO inner box borders) -->
                            @php
                                $isRevised = !empty($order->review_status);
                                $prefix = $isRevised ? 'INV' : 'EST';
                                $cleanNum = $order->estimate_invoice_number ? trim(preg_replace('/^(INV|EST)\s*#?\s*/i', '', $order->estimate_invoice_number)) : '';
                                $displayText = $cleanNum ? "{$prefix} {$cleanNum}" : $prefix;
                                
                                $cellBg = match($order->review_status) {
                                    'CS' => 'bg-pink-100 text-pink-900 font-bold',
                                    'CAMILA' => 'font-bold',
                                    default => 'bg-transparent text-stone-700',
                                };

                                $cellStyle = match($order->review_status) {
                                    'CAMILA' => 'background-color: var(--cc-camila-bg-light); color: var(--cc-camila-text-dark);',
                                    default => '',
                                };
                                
                                $checkIconColor = match($order->review_status) {
                                    'CS' => 'text-pink-700 hover:bg-pink-200/80',
                                    'CAMILA' => 'hover:opacity-80',
                                    default => 'text-stone-400 hover:text-stone-800',
                                };

                                $checkIconStyle = match($order->review_status) {
                                    'CAMILA' => 'color: var(--cc-camila-solid);',
                                    default => '',
                                };
                            @endphp
                            <td 
                                :class="getReviewCellClass(ordersState[{{ $order->id }}]?.review_status !== undefined ? ordersState[{{ $order->id }}].review_status : '{{ addslashes($order->review_status ?? '') }}')"
                                :style="getReviewCellStyle(ordersState[{{ $order->id }}]?.review_status !== undefined ? ordersState[{{ $order->id }}].review_status : '{{ addslashes($order->review_status ?? '') }}')"
                                class="py-1 px-1 truncate transition"
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
                                        @blur="
                                            const val = $el.value.trim();
                                            if (val !== $el.dataset.initial) {
                                                $el.dataset.initial = val;
                                                $wire.quickUpdateField({{ $order->id }}, 'estimate_invoice_number', val);
                                            }
                                            const curStatus = ordersState[{{ $order->id }}]?.review_status !== undefined ? ordersState[{{ $order->id }}].review_status : '{{ addslashes($order->review_status ?? '') }}';
                                            const curPrefix = (curStatus === 'CS' || curStatus === 'CAMILA') ? 'INV' : 'EST';
                                            $el.value = val ? (curPrefix + ' ' + val.replace(/^(INV|EST)\s*#?\s*/i, '')) : curPrefix;
                                        "
                                        class="flex-1 min-w-0 bg-transparent hover:bg-black/[0.04] focus:bg-white text-[10.5px] font-mono font-bold text-stone-900 px-1 py-0.5 rounded-sm border-0 border-b border-transparent focus:border-stone-400 focus:outline-none focus:ring-0 transition-colors truncate"
                                        title="{{ $displayText }} (Clic para editar)"
                                    >

                                    <button 
                                        type="button"
                                        data-popover-trigger="review"
                                        @click.stop="openMenu('review', {{ $order->id }}, $el)"
                                        :class="getReviewIconColor(ordersState[{{ $order->id }}]?.review_status !== undefined ? ordersState[{{ $order->id }}].review_status : '{{ addslashes($order->review_status ?? '') }}')"
                                        :style="getReviewIconStyle(ordersState[{{ $order->id }}]?.review_status !== undefined ? ordersState[{{ $order->id }}].review_status : '{{ addslashes($order->review_status ?? '') }}')"
                                        class="p-0.5 rounded cursor-pointer shrink-0 transition flex items-center justify-center border-none"
                                        title="Cambiar estado de revisión">
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
                                <div x-data="{ editing: false }" class="w-full flex items-center min-w-0">
                                    @if(!$hasEmailDate)
                                        <button 
                                            type="button" 
                                            x-show="!editing"
                                            @click="editing = true; $nextTick(() => { if ($refs.emailInput.showPicker) { try { $refs.emailInput.showPicker(); } catch(e){} } $refs.emailInput.focus(); })" 
                                            class="w-full text-left text-stone-400 hover:text-stone-700 text-[10px] font-mono px-1 py-0.5 rounded-sm transition-colors cursor-pointer"
                                            title="Sin fecha de email - Clic para asignar">
                                            —
                                        </button>
                                    @endif
                                    <input 
                                        x-ref="emailInput"
                                        @if(!$hasEmailDate) x-show="editing" x-cloak @endif
                                        type="date"
                                        data-initial="{{ $emailDateVal }}"
                                        value="{{ $emailDateVal }}"
                                        @input.stop
                                        @blur="if (!$el.value) editing = false;"
                                        @change.stop="
                                            if ($el.value !== $el.dataset.initial) {
                                                $el.dataset.initial = $el.value;
                                                $wire.quickUpdateField({{ $order->id }}, 'email_date', $el.value);
                                            }
                                            if (!$el.value) editing = false;
                                        "
                                        class="w-full bg-transparent hover:bg-stone-100/60 focus:bg-white text-[10px] text-stone-700 font-mono px-1 py-0.5 rounded-sm border-0 border-b border-transparent focus:border-stone-400 focus:outline-none focus:ring-0 transition-colors cursor-pointer"
                                        title="{{ $emailDateDisplay }} (Clic para editar)"
                                    >
                                </div>
                            </td>

                            <!-- 9. Instalación (Full Color Cell) -->
                            @php
                                $instTypeModel = !empty($order->installation_type) ? $installationTypes->firstWhere('name', $order->installation_type) : null;
                                $hasInstallation = !empty($order->installation_type);
                                $instStyle = '';
                                if ($instTypeModel && !empty($instTypeModel->bg_color)) {
                                    $instStyle = "background-color: {$instTypeModel->bg_color}; color: {$instTypeModel->text_color}; border-color: {$instTypeModel->border_color};";
                                }
                            @endphp
                            <td 
                                :style="getInstallationStyle(ordersState[{{ $order->id }}]?.installation_type !== undefined ? ordersState[{{ $order->id }}].installation_type : '{{ addslashes($order->installation_type ?? '') }}')"
                                @if(!empty($instStyle)) style="{{ $instStyle }}" @endif
                                :class="getInstallationClass(ordersState[{{ $order->id }}]?.installation_type !== undefined ? ordersState[{{ $order->id }}].installation_type : '{{ addslashes($order->installation_type ?? '') }}')"
                                class="py-1 px-1.5 truncate transition font-bold text-[10px]">
                                <button 
                                    type="button"
                                    data-popover-trigger="installation"
                                    @click.stop="openMenu('installation', {{ $order->id }}, $el, { installationType: ordersState[{{ $order->id }}]?.installation_type !== undefined ? ordersState[{{ $order->id }}].installation_type : {{ \Illuminate\Support\Js::from($order->installation_type ?? '') }} })"
                                    class="w-full text-left cursor-pointer flex items-center justify-between gap-1 py-0.5 border-none bg-transparent truncate"
                                    title="{{ __('Clic para cambiar instalación: :type', ['type' => $order->installation_type ?? __('Sin información')]) }}">
                                    <span class="truncate font-bold text-[10px] block" x-text="ordersState[{{ $order->id }}]?.installation_type !== undefined ? (ordersState[{{ $order->id }}].installation_type || '—') : '{{ $hasInstallation ? addslashes($order->installation_type) : '—' }}'">
                                        {{ $hasInstallation ? $order->installation_type : '—' }}
                                    </span>
                                    <x-lucide-chevron-down class="w-2.5 h-2.5 shrink-0 opacity-60" />
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
                                    class="w-full bg-transparent hover:bg-stone-100/70 focus:bg-white text-[11px] text-stone-700 italic px-1.5 py-0.5 rounded-sm border-0 border-b border-transparent focus:border-stone-400 focus:outline-none focus:ring-0 transition-colors truncate placeholder-stone-400 focus:text-stone-900 focus:not-italic"
                                    title="{{ $order->delivery_note ?: 'Clic para editar nota de entrega' }}"
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

                                $subFallbackClass = match($subVal) {
                                    'URGENTE', \App\Enums\Substatus::URGENTE->value => 'bg-red-600 text-white font-extrabold shadow-2xs',
                                    'BLOQUEADA', \App\Enums\Substatus::BLOQUEADA->value => 'bg-amber-500 text-amber-950 font-extrabold',
                                    'CUSTOMER SERVICE REQUIRED', \App\Enums\Substatus::CUSTOMER_SERVICE_REQUIRED->value => 'bg-amber-400 text-amber-950 font-extrabold',
                                    'OVERDUE', \App\Enums\Substatus::OVERDUE->value => 'bg-red-500 text-white font-extrabold',
                                    'ALMOST OVERDUE', \App\Enums\Substatus::ALMOST_OVERDUE->value => 'bg-amber-400 text-amber-950 font-bold',
                                    'CAMBIOS CAMILA', \App\Enums\Substatus::CAMBIOS_CAMILA->value => 'bg-purple-600 text-white font-extrabold',
                                    'CAMBIOS CLIENTE', \App\Enums\Substatus::CAMBIOS_CLIENTE->value => 'bg-sky-500 text-white font-extrabold',
                                    'WAITING FOR CLIENT', \App\Enums\Substatus::WAITING_FOR_CLIENT->value => 'bg-sky-400 text-sky-950 font-extrabold',
                                    'PAUSADO', \App\Enums\Substatus::PAUSADO->value => 'bg-stone-400 text-stone-950 font-bold',
                                    'FALTA APROBACIÓN DE ESTIMADO', \App\Enums\Substatus::FALTA_APROBACION_ESTIMADO->value => 'bg-orange-500 text-white font-extrabold',
                                    'TICKET', \App\Enums\Substatus::TICKET->value => 'bg-rose-500 text-white font-extrabold',
                                    'PONER EN ALTA', \App\Enums\Substatus::PONER_EN_ALTA->value, 'ENVIADO EN ALTA', \App\Enums\Substatus::ENVIADO_EN_ALTA->value => 'bg-pink-500 text-white font-extrabold',
                                    'AJUSTES DE PRODUCCIÓN', \App\Enums\Substatus::AJUSTES_PRODUCCION->value => 'bg-fuchsia-600 text-white font-extrabold',
                                    'ESPERANDO PERMISO', \App\Enums\Substatus::ESPERANDO_PERMISO->value => 'bg-yellow-500 text-yellow-950 font-bold',
                                    'NO RESPUESTA', \App\Enums\Substatus::NO_RESPUESTA->value => 'bg-stone-500 text-white font-semibold',
                                    'POTENTIAL CUSTOMER', \App\Enums\Substatus::POTENTIAL_CUSTOMER->value => 'bg-emerald-600 text-white font-extrabold',
                                    null, '' => 'bg-transparent text-stone-400 font-normal',
                                    default => 'bg-amber-400 text-amber-950 font-bold',
                                };
                            @endphp
                            <td 
                                :style="ordersState[{{ $order->id }}]?.substatus_style !== undefined ? ordersState[{{ $order->id }}].substatus_style : '{{ $subInlineStyle }}'"
                                @if(!empty($subInlineStyle)) style="{{ $subInlineStyle }}" @endif
                                class="py-1 px-1.5 truncate transition {{ empty($subInlineStyle) ? $subFallbackClass : '' }}">
                                <button 
                                    type="button"
                                    data-popover-trigger="substatus"
                                    @click.stop="openMenu('substatus', {{ $order->id }}, $el, { substatus: {{ \Illuminate\Support\Js::from($subVal ?? '') }}, flags: {{ \Illuminate\Support\Js::from($order->flags ?? []) }} })"
                                    class="w-full text-left cursor-pointer flex items-center justify-between gap-0.5 border-none bg-transparent py-0.5 truncate"
                                    title="Clic para cambiar subestatus / banderas: {{ $subLabel }}">
                                    <div class="flex items-center gap-0.5 overflow-hidden truncate">
                                        <span class="truncate font-bold text-[10px] block" x-text="ordersState[{{ $order->id }}]?.substatus_label || '{{ addslashes($subLabel) }}'">{{ $subLabel }}</span>
                                        @if($order->isOverdue())
                                            <span class="px-0.5 py-0.2 rounded text-[8px] font-extrabold bg-red-600 text-white uppercase shrink-0">!</span>
                                        @elseif($order->isDueToday())
                                            <span class="px-0.5 py-0.2 rounded text-[8px] font-bold bg-amber-500 text-amber-950 uppercase shrink-0">PV</span>
                                        @endif
                                        @if(!empty($order->flags))
                                            @foreach($order->flags as $fName)
                                                @php
                                                    $fEnum = \App\Enums\Substatus::tryFrom($fName);
                                                    $fLabel = $fEnum?->label() ?? $fName;
                                                    $fVars = match($fName) {
                                                        'URGENTE', \App\Enums\Substatus::URGENTE->value => [
                                                            'bg' => 'var(--cc-urgent-bg-light)',
                                                            'text' => 'var(--cc-urgent-text-dark)',
                                                            'border' => 'var(--cc-urgent-border)',
                                                        ],
                                                        'TICKET', \App\Enums\Substatus::TICKET->value => [
                                                            'bg' => 'var(--cc-camila-bg-light)',
                                                            'text' => 'var(--cc-camila-text-dark)',
                                                            'border' => 'var(--cc-camila-border)',
                                                        ],
                                                        'POTENTIAL CUSTOMER', \App\Enums\Substatus::POTENTIAL_CUSTOMER->value => [
                                                            'bg' => 'var(--cc-todo-today-bg-light)',
                                                            'text' => 'var(--cc-todo-today-text-dark)',
                                                            'border' => 'var(--cc-todo-today-border)',
                                                        ],
                                                        'EXTERNO', \App\Enums\Substatus::EXTERNO->value => [
                                                            'bg' => 'var(--cc-designer-external-bg-light)',
                                                            'text' => 'var(--cc-designer-external-text-dark)',
                                                            'border' => 'var(--cc-designer-external-border)',
                                                        ],
                                                        default => null,
                                                    };
                                                    $fBadgeStyle = $fVars 
                                                        ? "background-color: {$fVars['bg']}; color: {$fVars['text']}; border: 1px solid {$fVars['border']};"
                                                        : ($fEnum ? $fEnum->getInlineBadgeStyle() : 'background-color: var(--cc-camila-bg-light); color: var(--cc-camila-text-dark); border: 1px solid var(--cc-camila-border);');
                                                @endphp
                                                @if($fName !== 'OVERDUE' && $fName !== 'ALMOST OVERDUE')
                                                    <span class="px-1 py-0.2 rounded text-[8px] font-bold uppercase shrink-0" 
                                                          style="{{ $fBadgeStyle }}" 
                                                          title="Flag {{ $fLabel }}">{{ Str::limit($fLabel, 3, '') }}</span>
                                                @endif
                                            @endforeach
                                        @endif
                                    </div>
                                    <x-lucide-chevron-down class="w-2.5 h-2.5 opacity-60 shrink-0" />
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="13" class="py-8 text-center text-stone-400 font-medium">
                                {{ __('No se encontraron órdenes para la vista seleccionada.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Progressive Background Chunk Loader -->
        @if(!empty($hasMore))
            <div 
                wire:key="overview-chunk-loader-{{ $loadedCount }}"
                x-data
                x-init="$nextTick(() => $wire.loadNextChunk())"
                class="py-2.5 px-4 bg-emerald-50/70 border-t border-emerald-100 flex items-center justify-center gap-2 text-xs font-semibold text-emerald-800">
                <x-lucide-loader-2 class="w-4 h-4 animate-spin text-emerald-600 shrink-0" />
                <span>{{ __('Cargando órdenes adicionales en segundo plano...') }} ({{ count($orders) }} / {{ $totalFilteredCount }})</span>
            </div>
        @endif

        <!-- Pagination Links Bar or All Loaded Status -->
        @if(method_exists($orders, 'hasPages') && $orders->hasPages())
            <div class="px-4 py-3 border-t border-stone-200 bg-[#f7f7f5] flex flex-col sm:flex-row items-center justify-between gap-3 rounded-b-xl">
                <div class="text-xs text-stone-500 font-medium">
                    {{ __('Mostrando') }} <span class="font-bold text-stone-900">{{ $orders->firstItem() }}</span> {{ __('a') }} <span class="font-bold text-stone-900">{{ $orders->lastItem() }}</span> {{ __('de') }} <span class="font-bold text-stone-900">{{ $orders->total() }}</span> {{ __('órdenes') }}
                </div>
                <div>
                    {{ $orders->links() }}
                </div>
            </div>
        @else
            <div class="px-4 py-2.5 border-t border-stone-200 bg-[#f7f7f5] flex flex-col sm:flex-row items-center justify-between gap-3 rounded-b-xl">
                <div class="text-xs text-stone-600 font-medium flex items-center gap-2">
                    <span>{{ __('Mostrando') }} <strong class="text-stone-900 font-bold">{{ count($orders) }}</strong> {{ __('de') }} <strong class="text-stone-900 font-bold">{{ $totalFilteredCount }}</strong> {{ __('órdenes') }}</span>
                    <span class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-800 bg-emerald-100 px-2 py-0.5 rounded-full border border-emerald-300">
                        <x-lucide-zap class="w-3 h-3 text-emerald-600" /> {{ __('Sin paginación • File Cached') }}
                    </span>
                </div>
                <div class="text-[11px] text-stone-400">
                    @if(!empty($hasMore))
                        <span class="text-emerald-700 font-medium animate-pulse">{{ __('Cargando siguientes lotes en segundo plano...') }}</span>
                    @else
                        <span>{{ __('Todas las órdenes cargadas en la vista') }}</span>
                    @endif
                </div>
            </div>
        @endif
    </div>

    <!-- Single Centralized Unified Popover Container (No teleport leaks, max 1 active popover) -->
    <div 
        x-ref="popoverContainer"
        x-show="activeMenu !== null"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        @click.outside="if (!$event.target.closest('[data-popover-trigger]')) closeMenu()"
        @wheel.stop
        :style="menuStyle"
        class="bg-white shadow-2xl border border-stone-200 rounded-xl p-2 z-[99999] flex flex-col overflow-hidden text-stone-900 overscroll-contain"
        style="display: none;">

        <!-- 1. Designer Popover Content -->
        <template x-if="activeMenu === 'designer'">
            <div class="flex flex-col h-full min-h-0 space-y-1.5">
                <div class="px-2 py-0.5 text-[10px] font-bold text-stone-400 uppercase tracking-wider border-b border-stone-100 pb-1 flex items-center justify-between shrink-0">
                    <span>{{ __('Seleccionar Diseñador') }}</span>
                </div>

                <!-- Search input -->
                <div class="px-1 shrink-0">
                    <div class="relative flex items-center">
                        <x-lucide-search class="w-3.5 h-3.5 text-stone-400 absolute left-2 pointer-events-none" />
                        <input 
                            data-menu-search
                            x-model="menuSearch"
                            type="text" 
                            placeholder="{{ __('Buscar diseñador...') }}" 
                            class="w-full pl-7 pr-6 py-1 text-xs bg-stone-50 hover:bg-stone-100/80 focus:bg-white border border-stone-200 focus:border-stone-400 rounded-lg text-stone-800 placeholder-stone-400 outline-none transition"
                            @keydown.escape.stop="if (menuSearch) { menuSearch = ''; } else { closeMenu(); }"
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
                        x-show="matchesMenuSearch('sin asignar')"
                        @click="setDesigner(null, '{{ __('Sin Asignar') }}', 'bg-stone-50 border-dashed border-stone-300 text-stone-400 hover:border-stone-400', '')"
                        class="w-full text-left px-2 py-1.5 rounded-lg text-xs hover:bg-stone-100 text-stone-500 cursor-pointer">
                        -- {{ __('Sin Asignar') }} --
                    </button>
                    @foreach($designers as $d)
                        @php 
                            $desObj = is_object($d) ? $d : null; 
                            $dName = is_object($d) ? $d->name : $d; 
                            $dId = is_object($d) ? $d->id : $d;
                            $dStyle = $desObj ? $desObj->badge_style : '';
                            $dInline = $desObj ? $desObj->inline_badge_style : '';
                        @endphp
                        <button 
                            type="button"
                            x-show="matchesMenuSearch('{{ addslashes($dName) }}')"
                            @click="setDesigner({{ $dId }}, '{{ addslashes($dName) }}', '{{ addslashes($dStyle) }}', '{{ addslashes($dInline) }}')"
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
                <div class="px-2 py-0.5 text-[10px] font-bold text-stone-400 uppercase">Estado de Revisión</div>
                <button 
                    type="button"
                    @click="setReviewStatus('CS')"
                    class="w-full text-left px-2.5 py-1.5 rounded text-xs bg-pink-100 text-pink-900 font-semibold hover:bg-pink-200 transition cursor-pointer">
                    Revisado por CS (Rosado)
                </button>
                <button 
                    type="button"
                    @click="setReviewStatus('CAMILA')"
                    class="w-full text-left px-2.5 py-1.5 rounded text-xs font-semibold transition cursor-pointer"
                    style="background-color: var(--cc-camila-bg-light); color: var(--cc-camila-text-dark); border: 1px solid var(--cc-camila-border);">
                    Revisado por Camila
                </button>
                <button 
                    type="button"
                    @click="setReviewStatus(null)"
                    class="w-full text-left px-2.5 py-1.5 rounded text-xs bg-stone-50 text-stone-600 hover:bg-stone-100 border border-stone-200 transition cursor-pointer">
                    Sin revisión (Blanco / EST)
                </button>
            </div>
        </template>

        <!-- 3. Installation Popover Content -->
        <template x-if="activeMenu === 'installation'">
            <div class="flex flex-col h-full min-h-0 space-y-1.5">
                <div class="px-2 py-0.5 text-[10px] font-bold text-stone-400 uppercase tracking-wider border-b border-stone-100 pb-1 flex items-center justify-between shrink-0">
                    <span>{{ __('Tipo de Instalación') }}</span>
                    <a 
                        href="{{ route('settings.installation-types') }}" 
                        @click.stop="closeMenu(); if (window.Livewire) { Livewire.navigate('{{ route('settings.installation-types') }}'); } else { window.location.href = '{{ route('settings.installation-types') }}'; }" 
                        wire:navigate 
                        class="text-[9px] text-stone-400 hover:text-stone-700 hover:underline flex items-center gap-0.5 cursor-pointer pointer-events-auto z-10">
                        <x-lucide-settings class="w-2.5 h-2.5" />
                        <span>{{ __('Ajustes') }}</span>
                    </a>
                </div>

                <!-- Search Input -->
                <div class="px-1 shrink-0">
                    <div class="relative flex items-center">
                        <x-lucide-search class="w-3.5 h-3.5 text-stone-400 absolute left-2 pointer-events-none" />
                        <input 
                            data-menu-search
                            x-model="menuSearch"
                            type="text" 
                            placeholder="{{ __('Buscar instalación...') }}" 
                            class="w-full pl-7 pr-6 py-1 text-xs bg-stone-50 hover:bg-stone-100/80 focus:bg-white border border-stone-200 focus:border-stone-400 rounded-lg text-stone-800 placeholder-stone-400 outline-none transition"
                            @keydown.escape.stop="if (menuSearch) { menuSearch = ''; } else { closeMenu(); }"
                        >
                        <button 
                            x-show="menuSearch" 
                            @click="menuSearch = ''; $el.previousElementSibling.focus()" 
                            type="button" 
                            class="absolute right-2 text-stone-400 hover:text-stone-600 text-xs">✕</button>
                    </div>
                </div>

                <div @wheel.stop class="space-y-1 flex-1 min-h-0 overflow-y-auto overscroll-contain pr-0.5 custom-vertical-scrollbar">
                    @foreach($installationTypes as $instType)
                        <button 
                            type="button"
                            x-show="matchesMenuSearch('{{ addslashes($instType->name) }}')"
                            @click="setInstallationType('{{ $instType->name }}')"
                            class="w-full text-left px-2.5 py-1.5 rounded-lg text-xs font-bold border transition flex items-center justify-between cursor-pointer shadow-2xs hover:opacity-90"
                            style="background-color: {{ $instType->bg_color }}; color: {{ $instType->text_color }}; border-color: {{ $instType->border_color }};">
                            <span class="truncate">{{ $instType->name }}</span>
                            <template x-if="targetInstallationType === '{{ $instType->name }}'">
                                <x-lucide-check class="w-3.5 h-3.5 shrink-0" />
                            </template>
                        </button>
                    @endforeach
                </div>

                <div class="pt-1 border-t border-stone-100 shrink-0">
                    <button 
                        type="button"
                        x-show="matchesMenuSearch('vacio') || matchesMenuSearch('sin informacion')"
                        @click="setInstallationType(null)"
                        class="w-full text-left px-2.5 py-1.5 rounded-lg text-xs bg-stone-50 hover:bg-stone-100 text-stone-600 border border-stone-200 transition flex items-center justify-between cursor-pointer">
                        <span>{{ __('Vacío (Sin información)') }}</span>
                        <template x-if="!targetInstallationType">
                            <x-lucide-check class="w-3.5 h-3.5 shrink-0 text-stone-500" />
                        </template>
                    </button>
                </div>
            </div>
        </template>

        <!-- 4. Substatus & Global Flags Popover Content -->
        <template x-if="activeMenu === 'substatus'">
            <div class="flex flex-col h-full min-h-0 space-y-1.5">
                <!-- Search Input -->
                <div class="px-1 pt-0.5 shrink-0">
                    <div class="relative flex items-center">
                        <x-lucide-search class="w-3.5 h-3.5 text-stone-400 absolute left-2 pointer-events-none" />
                        <input 
                            data-menu-search
                            x-model="menuSearch"
                            type="text" 
                            placeholder="{{ __('Buscar subestatus o flag...') }}" 
                            class="w-full pl-7 pr-6 py-1 text-xs bg-stone-50 hover:bg-stone-100/80 focus:bg-white border border-stone-200 focus:border-stone-400 rounded-lg text-stone-800 placeholder-stone-400 outline-none transition"
                            @keydown.escape.stop="if (menuSearch) { menuSearch = ''; } else { closeMenu(); }"
                        >
                        <button 
                            x-show="menuSearch" 
                            @click="menuSearch = ''; $el.previousElementSibling.focus()" 
                            type="button" 
                            class="absolute right-2 text-stone-400 hover:text-stone-600 text-xs">✕</button>
                    </div>
                </div>

                <div @wheel.stop class="space-y-1.5 flex-1 min-h-0 overflow-y-auto overscroll-contain pr-0.5 custom-vertical-scrollbar">
                    <!-- Section 1: Global Flags (First) -->
                    <div 
                        x-show="!menuSearch"
                        class="px-2 py-0.5 text-[10px] font-extrabold text-stone-400 uppercase tracking-wider border-b border-stone-100 pb-1 flex items-center justify-between">
                        <span>{{ __('Banderas / Flags Globales') }}</span>
                        <span class="text-[9px] text-stone-400 font-normal lowercase">({{ __('coexistentes') }})</span>
                    </div>

                    @php
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
                    @endphp
                    @foreach($globalFlagsList as $flagItem)
                        @php
                            $flagValue = $flagItem instanceof \App\Models\Substatus ? $flagItem->name : ($flagItem instanceof \App\Enums\Substatus ? $flagItem->value : (string) $flagItem);
                            $flagModel = ($flagItem instanceof \App\Models\Substatus) ? $flagItem : (($substatuses->first() instanceof \App\Models\Substatus) ? $substatuses->firstWhere('name', $flagValue) : null);
                            $flagEnum = \App\Enums\Substatus::tryFrom($flagValue);
                            $flagLabel = $flagEnum?->label() ?? ($flagModel?->name ?? $flagValue);

                            // Use light pastel CSS color variables for global flags
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

                            $activeStyle = "background-color: {$flagBg}; color: {$flagText}; border-color: {$flagBorder}; font-weight: 700;";
                        @endphp

                        <button 
                            type="button"
                            x-show="matchesMenuSearch('{{ addslashes($flagLabel) }}') || matchesMenuSearch('{{ addslashes($flagValue) }}')"
                            @click="toggleGlobalFlag('{{ addslashes($flagValue) }}')"
                            :style="targetFlags.includes('{{ addslashes($flagValue) }}') ? '{{ $activeStyle }}' : 'border-color: {{ $flagBorder }};'"
                            :class="targetFlags.includes('{{ addslashes($flagValue) }}') 
                                ? 'shadow-2xs' 
                                : 'bg-white hover:bg-stone-50 text-stone-700 font-semibold'"
                            class="w-full text-left px-2.5 py-1.5 rounded-md text-xs transition flex items-center justify-between border cursor-pointer">
                            <div class="flex items-center gap-2 truncate">
                                <span class="w-2.5 h-2.5 rounded-full shrink-0 shadow-2xs" 
                                      style="background-color: {{ $flagSolid }};"></span>
                                <span class="truncate">{{ $flagLabel }}</span>
                            </div>
                            <div class="shrink-0 ml-1.5 flex items-center">
                                <template x-if="targetFlags.includes('{{ addslashes($flagValue) }}')">
                                    <x-lucide-check class="w-3.5 h-3.5 stroke-[3]" />
                                </template>
                                <template x-if="!targetFlags.includes('{{ addslashes($flagValue) }}')">
                                    <div class="w-3.5 h-3.5 rounded border border-stone-300"></div>
                                </template>
                            </div>
                        </button>
                    @endforeach

                    <!-- Section 2: Process Classification -->
                    <div 
                        x-show="!menuSearch"
                        class="px-2 pt-2 py-0.5 text-[10px] font-extrabold text-stone-400 uppercase tracking-wider border-t border-stone-100 mt-2">
                        {{ __('Clasificación de Proceso (1 Selección)') }}
                    </div>
                    
                    <button 
                        type="button"
                        x-show="matchesMenuSearch('sin subestatus') || matchesMenuSearch('vacio')"
                        @click="setSubstatus(null, '—', '')"
                        class="w-full text-left px-2.5 py-1 rounded-md text-xs bg-stone-50 text-stone-500 hover:bg-stone-100 border border-stone-200 transition font-medium flex items-center justify-between cursor-pointer">
                        <span>-- {{ __('Sin Subestatus') }} --</span>
                        <template x-if="!targetSubstatus">
                            <x-lucide-check class="w-3.5 h-3.5 text-stone-600 stroke-[3]" />
                        </template>
                    </button>

                    @foreach($groupedProcessSubstatuses as $groupKey => $group)
                        @php
                            $searchTerms = collect($group['items'])->flatMap(function ($subItem) {
                                $val = $subItem instanceof \App\Models\Substatus ? $subItem->name : $subItem->value;
                                $enum = \App\Enums\Substatus::tryFrom($val);
                                $lbl = $enum?->label() ?? $val;
                                return [$val, $lbl];
                            })->push($group['title'])->values();
                        @endphp
                        <div x-show="groupHasMatches({{ \Illuminate\Support\Js::from($searchTerms->all()) }})" class="pt-2 first:pt-1 space-y-1">
                            <div class="flex items-center gap-1.5 px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-wider text-stone-400 border-t border-stone-100 first:border-0 pt-1">
                                <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ $group['dot'] }}"></span>
                                <span class="truncate">{{ $group['title'] }}</span>
                            </div>

                            @foreach($group['items'] as $subItem)
                                @php
                                    $itemValue = $subItem instanceof \App\Models\Substatus ? $subItem->name : $subItem->value;
                                    $itemEnum = \App\Enums\Substatus::tryFrom($itemValue);
                                    if ($itemEnum && $itemEnum->isGlobal()) {
                                        continue;
                                    }
                                    if ($subItem instanceof \App\Models\Substatus && $subItem->is_global) {
                                        continue;
                                    }
                                    $itemLabel = $itemEnum?->label() ?? $itemValue;
                                    
                                    if ($subItem instanceof \App\Models\Substatus && $subItem->bg_color && $subItem->text_color) {
                                        $itemStyle = "background-color: {$subItem->bg_color}; color: {$subItem->text_color}; border-color: {$subItem->border_color};";
                                    } else {
                                        $itemStyle = $itemEnum?->customBadgeStyle() ?? '';
                                    }

                                    $itemFallbackClass = match($itemValue) {
                                        'BLOQUEADA' => 'bg-amber-500 text-amber-950 font-extrabold',
                                        'CUSTOMER SERVICE REQUIRED' => 'bg-amber-400 text-amber-950 font-extrabold',
                                        'CAMBIOS CAMILA' => 'bg-purple-600 text-white font-extrabold',
                                        'CAMBIOS CLIENTE' => 'bg-sky-500 text-white font-extrabold',
                                        'WAITING FOR CLIENT' => 'bg-sky-400 text-sky-950 font-extrabold',
                                        'PAUSADO' => 'bg-stone-400 text-stone-950 font-bold',
                                        'FALTA APROBACIÓN DE ESTIMADO' => 'bg-orange-500 text-white font-extrabold',
                                        'PONER EN ALTA', 'ENVIADO EN ALTA' => 'bg-pink-500 text-white font-extrabold',
                                        'AJUSTES DE PRODUCCIÓN' => 'bg-fuchsia-600 text-white font-extrabold',
                                        default => 'bg-stone-100 text-stone-800 border-stone-200 font-semibold',
                                    };
                                @endphp
                                
                                <button 
                                    type="button"
                                    x-show="matchesMenuSearch('{{ addslashes($itemLabel) }}') || matchesMenuSearch('{{ addslashes($itemValue) }}') || matchesMenuSearch('{{ addslashes($group['title']) }}')"
                                    @click="setSubstatus('{{ addslashes($itemValue) }}', '{{ addslashes($itemLabel) }}', '{{ addslashes($itemStyle) }}')"
                                    @if(!empty($itemStyle)) style="{{ $itemStyle }}" @endif
                                    class="w-full text-left px-2.5 py-1 rounded-md text-xs font-bold transition flex items-center justify-between border cursor-pointer {{ empty($itemStyle) ? $itemFallbackClass : '' }} hover:opacity-90">
                                    <span class="truncate">{{ $itemLabel }}</span>
                                    <template x-if="targetSubstatus === '{{ addslashes($itemValue) }}'">
                                        <x-lucide-check class="w-3.5 h-3.5 shrink-0 ml-1 stroke-[3]" />
                                    </template>
                                </button>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        </template>
    </div>
</div>
