@php
    $presetDataList = $subtaskPresets->map(function($p) {
        return [
            'id' => $p->id,
            'title' => $p->title,
            'badge_style' => $p->badgeStyle(),
            'is_work_task' => (bool) $p->is_work_task,
        ];
    })->values()->toArray();
@endphp
<div 
    wire:poll.3s 
    @open-subtask-modal.window="openCreateSubtaskModal($event.detail.orderId || '', $event.detail.dateStr || '', $event.detail.designerId || '')"
    @open-link-note-modal.window="openLinkNoteModal($event.detail.taskId || null, $event.detail.noteTitle || '')"
    x-data="{ 
        calendarOpen: false,
        subtaskModalOpen: false,
        subtaskOrderId: '',
        subtaskDate: '',
        subtaskDesignerId: '',
        subtaskTitle: '',
        subtaskIsWorkTask: true,
        orderDropdownOpen: false,
        orderSearchQuery: '',
        workspaceOrdersList: @js($workspaceOrdersList),
        subtaskPresetsList: @js($presetDataList),
        modalOrderHighlightedIndex: -1,
        linkNoteModalOpen: false,
        linkNoteTaskId: null,
        linkNoteTitle: '',
        linkNoteOrderId: '',
        linkNoteSearchQuery: '',
        linkNoteDropdownOpen: false,
        linkNoteHighlightedIndex: -1,
        openLinkNoteModal(taskId, noteTitle = '') {
            this.linkNoteTaskId = taskId;
            this.linkNoteTitle = noteTitle;
            this.linkNoteOrderId = '';
            this.linkNoteSearchQuery = '';
            this.linkNoteDropdownOpen = true;
            this.linkNoteHighlightedIndex = -1;
            this.linkNoteModalOpen = true;
            this.$nextTick(() => {
                if (this.$refs.linkNoteSearchInput) {
                    this.$refs.linkNoteSearchInput.focus();
                }
            });
        },
        getFilteredLinkNoteOrders() {
            if (!this.linkNoteSearchQuery) {
                return this.workspaceOrdersList;
            }
            const q = this.linkNoteSearchQuery.toLowerCase().trim();
            return this.workspaceOrdersList.filter(o => 
                (o.text && o.text.toLowerCase().includes(q)) || 
                (o.company && o.company.toLowerCase().includes(q)) || 
                (o.location && o.location.toLowerCase().includes(q)) ||
                (o.task && o.task.toLowerCase().includes(q)) ||
                (o.wo_number && o.wo_number.toLowerCase().includes(q)) ||
                (o.trello_card_title && o.trello_card_title.toLowerCase().includes(q))
            );
        },
        navigateLinkNoteOrder(step) {
            const list = this.getFilteredLinkNoteOrders();
            if (list.length === 0) return;
            if (this.linkNoteHighlightedIndex === -1) {
                this.linkNoteHighlightedIndex = step > 0 ? 0 : list.length - 1;
            } else {
                this.linkNoteHighlightedIndex = (this.linkNoteHighlightedIndex + step + list.length) % list.length;
            }
            this.$nextTick(() => {
                const container = this.$refs.linkNoteDropdownPanel;
                if (!container) return;
                const items = container.querySelectorAll('.link-note-order-item-btn');
                if (items[this.linkNoteHighlightedIndex]) {
                    items[this.linkNoteHighlightedIndex].scrollIntoView({ block: 'nearest' });
                }
            });
        },
        selectHighlightedLinkNoteOrder() {
            const list = this.getFilteredLinkNoteOrders();
            if (this.linkNoteHighlightedIndex >= 0 && list[this.linkNoteHighlightedIndex]) {
                const item = list[this.linkNoteHighlightedIndex];
                this.linkNoteOrderId = item.id;
                this.linkNoteSearchQuery = item.text;
                this.linkNoteDropdownOpen = false;
                this.linkNoteHighlightedIndex = -1;
                this.submitLinkNoteModal();
            }
        },
        submitLinkNoteModal() {
            if (!this.linkNoteOrderId) {
                alert('{{ __('Debes seleccionar una orden del workspace para vincular la nota.') }}');
                return;
            }
            $wire.linkSubtaskToOrder(this.linkNoteTaskId, this.linkNoteOrderId);
            this.linkNoteModalOpen = false;
        },
        getFilteredOrders() {
            if (!this.orderSearchQuery) {
                return this.workspaceOrdersList;
            }
            const q = this.orderSearchQuery.toLowerCase().trim();
            return this.workspaceOrdersList.filter(o => 
                (o.text && o.text.toLowerCase().includes(q)) || 
                (o.company && o.company.toLowerCase().includes(q)) || 
                (o.location && o.location.toLowerCase().includes(q)) ||
                (o.task && o.task.toLowerCase().includes(q)) ||
                (o.wo_number && o.wo_number.toLowerCase().includes(q)) ||
                (o.trello_card_title && o.trello_card_title.toLowerCase().includes(q))
            );
        },
        navigateModalOrder(step) {
            const list = this.getFilteredOrders();
            if (list.length === 0) return;
            if (this.modalOrderHighlightedIndex === -1) {
                this.modalOrderHighlightedIndex = step > 0 ? 0 : list.length - 1;
            } else {
                this.modalOrderHighlightedIndex = (this.modalOrderHighlightedIndex + step + list.length) % list.length;
            }
            this.$nextTick(() => {
                const container = this.$refs.modalOrderDropdownPanel;
                if (!container) return;
                const items = container.querySelectorAll('.modal-order-item-btn');
                if (items[this.modalOrderHighlightedIndex]) {
                    items[this.modalOrderHighlightedIndex].scrollIntoView({ block: 'nearest' });
                }
            });
        },
        selectHighlightedModalOrder() {
            const list = this.getFilteredOrders();
            if (this.modalOrderHighlightedIndex >= 0 && list[this.modalOrderHighlightedIndex]) {
                const item = list[this.modalOrderHighlightedIndex];
                this.subtaskOrderId = item.id;
                this.orderSearchQuery = item.text;
                if (item.designer_id) this.subtaskDesignerId = String(item.designer_id);
                this.orderDropdownOpen = false;
                this.modalOrderHighlightedIndex = -1;
            }
        },
        openCreateSubtaskModal(orderId = '', dateStr = '', designerId = '') {
            this.subtaskOrderId = orderId ? String(orderId) : '';
            this.subtaskDate = dateStr || '';
            this.subtaskTitle = '';
            this.subtaskIsWorkTask = true;
            this.orderDropdownOpen = false;
            
            let match = this.workspaceOrdersList.find(o => String(o.id) === String(orderId));
            this.orderSearchQuery = match ? match.text : '';
            this.subtaskDesignerId = (match && match.designer_id) ? String(match.designer_id) : (designerId ? String(designerId) : '');

            this.subtaskModalOpen = true;
            this.$nextTick(() => {
                if (!this.subtaskOrderId && this.$refs.orderSearchInput) {
                    this.$refs.orderSearchInput.focus();
                } else if (this.$refs.subtaskTitleInput) {
                    this.$refs.subtaskTitleInput.focus();
                }
            });
        },
        submitSubtaskModal() {
            if (!this.subtaskTitle.trim()) {
                alert('{{ __('Por favor ingresa el nombre de la subtarea o nota.') }}');
                return;
            }
            $wire.scheduleSubtask(this.subtaskOrderId || null, this.subtaskTitle.trim(), this.subtaskDate, this.subtaskDesignerId, this.subtaskIsWorkTask);
            this.subtaskModalOpen = false;
        },
        undoToastOpen: false,
        undoToastMessage: '',
        undoToastTimer: null,
        triggerUndoToast(msg) {
            this.undoToastMessage = msg || '{{ __('Subtarea eliminada') }}';
            this.undoToastOpen = true;
            if (this.undoToastTimer) clearTimeout(this.undoToastTimer);
            this.undoToastTimer = setTimeout(() => {
                this.undoToastOpen = false;
            }, 5000);
        },
        hideUndoToast() {
            this.undoToastOpen = false;
            if (this.undoToastTimer) clearTimeout(this.undoToastTimer);
        },
        initDragAutoScroll() {
            let scrollInterval = null;
            let currentX = 0;
            let currentY = 0;
            let isDragging = false;

            const EDGE_THRESHOLD = 90;
            const MAX_SPEED = 24;

            const handleDragOver = (e) => {
                if (e.clientX === 0 && e.clientY === 0) return;
                currentX = e.clientX;
                currentY = e.clientY;
                isDragging = true;

                if (!scrollInterval) {
                    scrollInterval = requestAnimationFrame(scrollLoop);
                }
            };

            const handleDragEnd = () => {
                isDragging = false;
                if (scrollInterval) {
                    cancelAnimationFrame(scrollInterval);
                    scrollInterval = null;
                }
            };

            const scrollLoop = () => {
                if (!isDragging) {
                    scrollInterval = null;
                    return;
                }

                const vw = window.innerWidth;
                const vh = window.innerHeight;

                // 1. Window vertical scrolling
                if (currentY < EDGE_THRESHOLD && window.scrollY > 0) {
                    const intensity = (EDGE_THRESHOLD - Math.max(0, currentY)) / EDGE_THRESHOLD;
                    window.scrollBy(0, -Math.ceil(intensity * MAX_SPEED));
                } else if (currentY > vh - EDGE_THRESHOLD) {
                    const intensity = (Math.min(vh, currentY) - (vh - EDGE_THRESHOLD)) / EDGE_THRESHOLD;
                    window.scrollBy(0, Math.ceil(intensity * MAX_SPEED));
                }

                // 2. Window horizontal scrolling
                if (currentX < EDGE_THRESHOLD && window.scrollX > 0) {
                    const intensity = (EDGE_THRESHOLD - Math.max(0, currentX)) / EDGE_THRESHOLD;
                    window.scrollBy(-Math.ceil(intensity * MAX_SPEED), 0);
                } else if (currentX > vw - EDGE_THRESHOLD) {
                    const intensity = (Math.min(vw, currentX) - (vw - EDGE_THRESHOLD)) / EDGE_THRESHOLD;
                    window.scrollBy(Math.ceil(intensity * MAX_SPEED), 0);
                }

                // 3. Overflow scroll containers
                const containers = document.querySelectorAll('.overflow-x-auto, .overflow-y-auto, .custom-vertical-scrollbar, .custom-horizontal-scrollbar');
                containers.forEach(container => {
                    const rect = container.getBoundingClientRect();
                    const isWithinY = currentY >= rect.top - 30 && currentY <= rect.bottom + 30;
                    const isWithinX = currentX >= rect.left - 30 && currentX <= rect.right + 30;

                    if (isWithinY && isWithinX) {
                        // Container horizontal scroll
                        if (container.scrollWidth > container.clientWidth) {
                            if (currentX > rect.right - EDGE_THRESHOLD) {
                                const intensity = Math.min(1, (currentX - (rect.right - EDGE_THRESHOLD)) / EDGE_THRESHOLD);
                                container.scrollLeft += Math.ceil(intensity * MAX_SPEED);
                            } else if (currentX < rect.left + EDGE_THRESHOLD) {
                                const intensity = Math.min(1, ((rect.left + EDGE_THRESHOLD) - currentX) / EDGE_THRESHOLD);
                                container.scrollLeft -= Math.ceil(intensity * MAX_SPEED);
                            }
                        }

                        // Container vertical scroll
                        if (container.scrollHeight > container.clientHeight) {
                            if (currentY > rect.bottom - EDGE_THRESHOLD) {
                                const intensity = Math.min(1, (currentY - (rect.bottom - EDGE_THRESHOLD)) / EDGE_THRESHOLD);
                                container.scrollTop += Math.ceil(intensity * MAX_SPEED);
                            } else if (currentY < rect.top + EDGE_THRESHOLD) {
                                const intensity = Math.min(1, ((rect.top + EDGE_THRESHOLD) - currentY) / EDGE_THRESHOLD);
                                container.scrollTop -= Math.ceil(intensity * MAX_SPEED);
                            }
                        }
                    }
                });

                scrollInterval = requestAnimationFrame(scrollLoop);
            };

            window.addEventListener('dragover', handleDragOver, { passive: true });
            window.addEventListener('dragend', handleDragEnd, { passive: true });
            window.addEventListener('drop', handleDragEnd, { passive: true });
        }
    }" 
    x-init="initDragAutoScroll()"
    @subtask-deleted.window="triggerUndoToast($event.detail.message)"
    @subtask-restored.window="hideUndoToast()"
    @keydown.window="(e) => {
        if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'z' && !e.shiftKey) {
            const active = document.activeElement;
            const isInput = active && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA' || active.tagName === 'SELECT' || active.isContentEditable);
            const hasModal = document.querySelector('[role=\'dialog\']');
            if (!isInput && !hasModal && $wire.recentlyDeletedSubtaskIds.length > 0) {
                e.preventDefault();
                $wire.undoDeleteSubtask();
            }
        }
    }"
    class="h-full flex flex-col space-y-4 text-xs font-sans bg-[#fbfbfa] min-h-0 overflow-y-auto custom-vertical-scrollbar pr-1 pb-4 text-zinc-800">

    <!-- Top Sticky / Dynamic Toolbar Header -->
    <div class="sticky top-0 z-20 bg-[#fbfbfa] pt-0.5 pb-2">
        <div id="tour-planner-header" class="bg-white border border-[#e9e9e7] rounded-xl p-3.5 md:p-4 shadow-2xs space-y-3">
            
            <!-- LINE 1: Title -->
            <div class="flex items-center justify-between min-w-0 gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="p-2.5 rounded-xl bg-stone-900 text-white shrink-0 shadow-2xs">
                        <x-lucide-calendar-days class="w-5 h-5" />
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h1 class="font-black text-base md:text-lg text-zinc-900 tracking-tight leading-none">
                                {{ __('Agenda Semanal') }}
                            </h1>
                            @php
                                $startVal = Carbon\Carbon::parse($selectedWeekStart);
                                $endVal = $startVal->copy()->addDays(4);
                                $currentMonday = now()->startOfWeek(Carbon\Carbon::MONDAY)->toDateString();
                                $isThisWeek = $startVal->toDateString() === $currentMonday;
                            @endphp
                            @if($isThisWeek)
                                <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-bold text-[10px] uppercase tracking-wider border border-emerald-300">
                                    {{ __('Semana Actual') }}
                                </span>
                            @endif
                        </div>
                        <p class="text-xs text-zinc-500 font-medium truncate mt-0.5">
                            Lunes {{ $startVal->format('d') }} de {{ $startVal->locale('es')->translatedFormat('F') }} al Viernes {{ $endVal->format('d') }} de {{ $endVal->locale('es')->translatedFormat('F, Y') }}
                        </p>
                    </div>
                </div>

                @if(isset($slaBreachedList) && $slaBreachedList->isNotEmpty())
                    <button 
                        wire:click="openAllSlaWarningsModal"
                        type="button" 
                        class="px-3 py-1.5 rounded-lg bg-red-600 hover:bg-red-700 text-white font-bold text-xs flex items-center gap-1.5 transition shadow-xs border border-red-700 cursor-pointer shrink-0"
                        title="{{ __('Ver tareas que superan SLA') }}">
                        <x-lucide-alert-triangle class="w-4 h-4 text-white animate-bounce" />
                        <span>{{ $slaBreachedList->count() }} {{ __('Alertas SLA') }}</span>
                    </button>
                @endif
            </div>

            <!-- LINE 2: Designer Filter (Left) ___________________ Week jump, This Week, Next Week, Open Calendar (Right) -->
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 pt-2.5 border-t border-[#e9e9e7]">
                
                <!-- Left: Designer Filter Tabs -->
                <div class="flex items-center gap-1 overflow-x-auto scrollbar-none text-xs shrink-0 relative z-10">
                    <button wire:click="$set('selectedDesignerFilter', 'all')" class="px-3 py-1 rounded-md font-medium transition flex items-center gap-1.5 shrink-0 {{ $selectedDesignerFilter === 'all' ? 'bg-white text-zinc-900 border border-[#d0d0ce] shadow-2xs font-semibold' : 'text-zinc-500 hover:text-zinc-800' }}">
                        <x-lucide-users class="w-3.5 h-3.5 text-zinc-500" />
                        <span>{{ __('Todos los Diseñadores') }}</span>
                    </button>

                    @foreach($allDesigners as $des)
                        <button 
                            wire:click="$set('selectedDesignerFilter', '{{ $des->id }}')" 
                            class="px-2.5 py-1 rounded-md font-medium transition flex items-center gap-1.5 shrink-0 {{ $selectedDesignerFilter == $des->id ? 'bg-white text-zinc-900 border border-[#d0d0ce] shadow-2xs font-semibold' : 'text-zinc-500 hover:text-zinc-800' }}">
                            <span class="w-2 h-2 rounded-full {{ $des->dot_color_class }}"></span>
                            <span>{{ $des->name }}</span>
                        </button>
                    @endforeach
                </div>

                <!-- Right: Week jump, This Week, Next Week, Open Calendar -->
                <div class="flex flex-wrap items-center gap-2 shrink-0">
                    <!-- Week jump navigator -->
                    <div class="flex items-center bg-[#f7f7f5] border border-[#e3e3e1] p-1 rounded-lg gap-1.5 h-8">
                        <button wire:click="previousWeek" class="p-1 rounded-md hover:bg-white hover:shadow-2xs text-zinc-700 transition cursor-pointer" title="Semana anterior">
                            <x-lucide-chevron-left class="w-4 h-4" />
                        </button>

                        <span class="px-2 font-bold text-xs text-zinc-900 font-mono tracking-tight select-none">
                            {{ $startVal->format('d M') }} - {{ $endVal->format('d M') }}
                        </span>

                        <button wire:click="nextWeek" class="p-1 rounded-md hover:bg-white hover:shadow-2xs text-zinc-700 transition cursor-pointer" title="Semana siguiente">
                            <x-lucide-chevron-right class="w-4 h-4" />
                        </button>
                    </div>

                    <!-- This Week -->
                    @php
                        $nextWeekMonday = now()->addWeek()->startOfWeek(Carbon\Carbon::MONDAY)->toDateString();
                        $isNextWeek = $startVal->toDateString() === $nextWeekMonday;
                    @endphp
                    <button 
                        wire:click="thisWeek" 
                        class="px-3 py-1 h-8 rounded-lg text-xs font-semibold shadow-2xs transition flex items-center gap-1.5 cursor-pointer {{ $isThisWeek ? 'bg-emerald-600 text-white shadow-xs' : 'bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200' }}"
                        title="Ir a la semana actual">
                        <x-lucide-calendar-days class="w-3.5 h-3.5" />
                        <span>{{ __('Esta Semana') }}</span>
                    </button>

                    <!-- Next Week -->
                    <button 
                        wire:click="jumpWeeks(1)" 
                        class="px-3 py-1 h-8 rounded-lg text-xs font-semibold shadow-2xs transition flex items-center gap-1.5 cursor-pointer {{ $isNextWeek ? 'bg-indigo-600 text-white shadow-xs' : 'bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200' }}"
                        title="Ir a la próxima semana">
                        <x-lucide-arrow-right-circle class="w-3.5 h-3.5" />
                        <span>{{ __('Próxima Semana') }}</span>
                    </button>

                    <!-- Open Calendar Popover Button -->
                    <div class="relative" @click.outside="calendarOpen = false">
                        <button 
                            @click="calendarOpen = !calendarOpen"
                            class="px-3 py-1.5 h-8 rounded-lg bg-stone-900 hover:bg-stone-800 text-white text-xs font-semibold shadow-2xs transition flex items-center gap-2 cursor-pointer">
                            <x-lucide-calendar class="w-3.5 h-3.5 text-stone-200" />
                            <span>{{ __('Abrir Calendario') }}</span>
                            <x-lucide-chevron-down class="w-3.5 h-3.5 text-stone-400" />
                        </button>

                        <!-- Mini-Calendar Interactive Popover Modal -->
                        <div 
                            x-show="calendarOpen"
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            class="absolute right-0 top-full mt-2 w-80 z-50 bg-white border border-[#e9e9e7] rounded-xl shadow-2xl p-4 text-xs space-y-3">
                            
                            <!-- Month Header Navigator -->
                            <div class="flex items-center justify-between border-b border-[#f0f0ee] pb-2">
                                <button wire:click="previousMonth" class="p-1 rounded hover:bg-stone-100 text-zinc-600 transition">
                                    <x-lucide-chevron-left class="w-4 h-4" />
                                </button>
                                <span class="font-bold text-zinc-900 text-xs capitalize">
                                    {{ Carbon\Carbon::parse($viewMonth . '-01')->translatedFormat('F Y') }}
                                </span>
                                <button wire:click="nextMonth" class="p-1 rounded hover:bg-stone-100 text-zinc-600 transition">
                                    <x-lucide-chevron-right class="w-4 h-4" />
                                </button>
                            </div>

                            <!-- Days of Week Header -->
                            <div class="grid grid-cols-7 text-center font-bold text-[10px] text-zinc-400 uppercase tracking-wider">
                                <span>Lun</span><span>Mar</span><span>Mié</span><span>Jue</span><span>Vie</span><span class="text-zinc-300">Sáb</span><span class="text-zinc-300">Dom</span>
                            </div>

                            <!-- Days Grid -->
                            <div class="grid grid-cols-7 gap-1 text-center font-medium">
                                @foreach($this->miniCalendarDays as $calDay)
                                    <button 
                                        wire:click="selectWeekFromDate('{{ $calDay['date_string'] }}')"
                                        @click="calendarOpen = false"
                                        class="p-1.5 rounded-md text-xs transition relative group
                                            {{ $calDay['is_selected_week'] ? 'bg-indigo-600 text-white font-bold shadow-xs' : ($calDay['is_current_month'] ? 'text-zinc-800 hover:bg-stone-100' : 'text-zinc-300 hover:bg-stone-50') }}
                                            {{ $calDay['is_today'] && !$calDay['is_selected_week'] ? 'ring-1 ring-emerald-500 font-bold text-emerald-700' : '' }}">
                                        <span>{{ $calDay['day_number'] }}</span>
                                    </button>
                                @endforeach
                            </div>

                            <!-- Quick Native Date Direct Picker -->
                            <div class="pt-2 border-t border-[#f0f0ee] flex items-center justify-between">
                                <span class="text-[11px] text-zinc-500">{{ __('O elige fecha exacta:') }}</span>
                                <input 
                                    type="date" 
                                    wire:model.live="selectedWeekStart" 
                                    @change="calendarOpen = false"
                                    class="bg-[#fbfbfa] border border-[#e9e9e7] rounded px-2 py-0.5 text-[11px] font-mono text-zinc-800">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- LINE 3: Por Dia / Diseñador + Workspace Searchbar (Left) ____________________ Searchbar Backlog (Right) -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 pt-2.5 border-t border-[#e9e9e7]">
                
                <!-- Left: Por Dia / Diseñador View Mode Switcher + Tareas de Sistema Toggle + Workspace Searchbar -->
                <div class="flex flex-col sm:flex-row items-center gap-2.5 w-full md:w-auto">
                    <!-- View Mode Switcher -->
                    <div class="flex items-center bg-[#f7f7f5] border border-[#e3e3e1] p-0.5 rounded-lg gap-1 h-8 text-xs font-medium shrink-0">
                        <button 
                            type="button" 
                            wire:click="changeViewMode('by_day')"
                            class="px-2.5 py-1 h-7 rounded-md transition cursor-pointer flex items-center gap-1.5 {{ $viewMode === 'by_day' ? 'bg-white text-zinc-900 font-bold shadow-2xs' : 'text-zinc-500 hover:text-zinc-800' }}">
                            <x-lucide-calendar-days class="w-3.5 h-3.5" />
                            <span>{{ __('Por Días') }}</span>
                        </button>
                        <button 
                            type="button" 
                            wire:click="changeViewMode('by_designer')"
                            class="px-2.5 py-1 h-7 rounded-md transition cursor-pointer flex items-center gap-1.5 {{ $viewMode === 'by_designer' ? 'bg-white text-zinc-900 font-bold shadow-2xs' : 'text-zinc-500 hover:text-zinc-800' }}">
                            <x-lucide-users class="w-3.5 h-3.5" />
                            <span>{{ __('Por Diseñador') }}</span>
                        </button>
                    </div>

                    <!-- System Tasks Toggle Button (Violet Accent) -->
                    <button 
                        type="button" 
                        wire:click="toggleShowSystemTasks"
                        class="px-2.5 py-1 h-8 rounded-lg border text-xs font-semibold shadow-2xs transition flex items-center gap-1.5 cursor-pointer shrink-0 {{ $showSystemTasks ? 'bg-violet-600 hover:bg-violet-700 text-white border-violet-700 shadow-xs' : 'bg-violet-50 hover:bg-violet-100 text-violet-700 border-violet-200' }}"
                        title="{{ $showSystemTasks ? __('Ocultar tareas de sistema') : __('Mostrar tareas de sistema') }}">
                        <x-lucide-cpu class="w-3.5 h-3.5 {{ $showSystemTasks ? 'text-violet-100' : 'text-violet-600' }}" />
                        <span>{{ __('Tareas de Sistema') }}</span>
                    </button>

                    <!-- Workspace Orders Search Bar (Next to View Switcher) -->
                    <div class="relative shrink-0 w-full sm:w-56" x-data="{ open: true }" @click.outside="open = false">
                        <div class="relative w-full">
                            <x-lucide-search class="w-3.5 h-3.5 text-zinc-400 absolute left-2.5 top-1/2 -translate-y-1/2" />
                            <input 
                                type="text" 
                                wire:model.live.debounce.150ms="unscheduledSearch"
                                @focus="open = true"
                                placeholder="{{ __('Buscar en Workspace...') }}" 
                                class="bg-[#fbfbfa] focus:bg-white border border-[#e9e9e7] focus:border-stone-400 rounded-lg pl-7 pr-7 py-1 text-xs text-zinc-800 focus:outline-none w-full font-normal shadow-2xs transition h-8 placeholder-zinc-400" />
                            @if($unscheduledSearch)
                                <button 
                                    wire:click="$set('unscheduledSearch', '')" 
                                    type="button" 
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-700"
                                    title="{{ __('Limpiar búsqueda') }}">
                                    <x-lucide-x class="w-3.5 h-3.5" />
                                </button>
                            @endif
                        </div>

                        <!-- Workspace Search Results Popover -->
                        @if(!empty($unscheduledSearch))
                            <div 
                                x-show="open"
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                class="absolute left-0 top-full mt-1.5 w-80 sm:w-96 z-50 bg-white border border-[#e9e9e7] rounded-xl shadow-2xl overflow-hidden divide-y divide-stone-100 text-xs">
                                
                                <div class="px-3 py-2 bg-stone-50 border-b border-stone-100 font-bold text-[10px] uppercase text-zinc-500 flex items-center justify-between">
                                    <span class="flex items-center gap-1.5">
                                        <x-lucide-search class="w-3.5 h-3.5 text-zinc-500" />
                                        {{ __('Órdenes en Workspace') }} ({{ $workspaceSearchResults->count() }})
                                    </span>
                                    <span class="text-[9px] font-normal text-zinc-400">{{ __('Workspace Activo') }}</span>
                                </div>

                                <div class="max-h-72 overflow-y-auto custom-vertical-scrollbar divide-y divide-stone-100">
                                    @forelse($workspaceSearchResults as $wOrder)
                                        <div 
                                            wire:click="$dispatch('open-order-detail', { orderId: {{ $wOrder->id }} })"
                                            @click="open = false"
                                            class="p-2.5 hover:bg-stone-100 cursor-pointer flex items-center justify-between gap-2 transition group">
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center gap-1 mb-0.5">
                                                    <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-stone-100 text-zinc-700 border border-stone-200 shrink-0">
                                                        Workspace
                                                    </span>
                                                    @if($wOrder->wo_number)
                                                        <span class="font-mono text-[9px] text-zinc-400">#{{ $wOrder->wo_number }}</span>
                                                    @endif
                                                    @if($wOrder->designer)
                                                        <span class="text-[9px] text-zinc-500 font-medium truncate">• {{ $wOrder->designer->name }}</span>
                                                    @endif
                                                </div>
                                                <h4 class="font-bold text-xs text-zinc-900 truncate group-hover:text-stone-900 transition">{{ $wOrder->company_name }}</h4>
                                                <p class="text-[11px] text-zinc-500 truncate mt-0.2">{{ $wOrder->task_name }}</p>
                                            </div>
                                            <div class="flex items-center gap-1 shrink-0">
                                                <x-lucide-panel-right class="w-4 h-4 text-zinc-400 group-hover:text-stone-700 transition" />
                                            </div>
                                        </div>
                                    @empty
                                        <div class="p-4 text-center text-zinc-400 italic text-[11px]">
                                            {{ __('No se encontraron órdenes activas para ":query".', ['query' => $unscheduledSearch]) }}
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Right: Backlog Search Bar -->
                <div class="relative shrink-0 w-full sm:w-56" x-data="{ open: true }" @click.outside="open = false">
                    <div class="relative w-full">
                        <x-lucide-archive class="w-3.5 h-3.5 text-zinc-400 absolute left-2.5 top-1/2 -translate-y-1/2" />
                        <input 
                            type="text" 
                            wire:model.live.debounce.150ms="backlogSearch"
                            @focus="open = true"
                            placeholder="{{ __('Buscar en Backlog...') }}" 
                            class="bg-[#fbfbfa] focus:bg-white border border-[#e9e9e7] focus:border-stone-400 rounded-lg pl-7 pr-7 py-1 text-xs text-zinc-800 focus:outline-none w-full font-normal shadow-2xs transition h-8 placeholder-zinc-400" />
                        @if($backlogSearch)
                            <button 
                                wire:click="$set('backlogSearch', '')" 
                                type="button" 
                                class="absolute right-2 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-700"
                                title="{{ __('Limpiar búsqueda') }}">
                                <x-lucide-x class="w-3.5 h-3.5" />
                            </button>
                        @endif
                    </div>

                    <!-- Backlog Search Results Popover -->
                    @if(!empty($backlogSearch))
                        <div 
                            x-show="open"
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            class="absolute right-0 top-full mt-1.5 w-80 sm:w-96 z-50 bg-white border border-[#e9e9e7] rounded-xl shadow-2xl overflow-hidden divide-y divide-stone-100 text-xs">
                            
                            <div class="px-3 py-2 bg-stone-50 border-b border-stone-100 font-bold text-[10px] uppercase text-zinc-500 flex items-center justify-between">
                                <span class="flex items-center gap-1.5">
                                    <x-lucide-archive class="w-3.5 h-3.5 text-zinc-500" />
                                    {{ __('Órdenes en Backlog') }} ({{ $backlogOrders->count() }})
                                </span>
                                <span class="text-[9px] font-normal text-zinc-400">{{ __('Sin agendar') }}</span>
                            </div>

                            <div class="max-h-72 overflow-y-auto custom-vertical-scrollbar divide-y divide-stone-100">
                                @forelse($backlogOrders as $bOrder)
                                    <div 
                                        wire:click="$dispatch('open-order-detail', { orderId: {{ $bOrder->id }} })"
                                        @click="open = false"
                                        class="p-2.5 hover:bg-stone-100 cursor-pointer flex items-center justify-between gap-2 transition group">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-1 mb-0.5">
                                                <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-amber-50 text-amber-800 border border-amber-200 shrink-0">
                                                    Backlog
                                                </span>
                                                @if($bOrder->wo_number)
                                                    <span class="font-mono text-[9px] text-zinc-400">#{{ $bOrder->wo_number }}</span>
                                                @endif
                                                @if($bOrder->designer)
                                                    <span class="text-[9px] text-zinc-500 font-medium truncate">• {{ $bOrder->designer->name }}</span>
                                                @endif
                                            </div>
                                            <h4 class="font-bold text-xs text-zinc-900 truncate group-hover:text-stone-900 transition">{{ $bOrder->company_name }}</h4>
                                            <p class="text-[11px] text-zinc-500 truncate mt-0.2">{{ $bOrder->task_name }}</p>
                                        </div>
                                        <div class="flex items-center gap-1 shrink-0">
                                            <x-lucide-panel-right class="w-4 h-4 text-zinc-400 group-hover:text-stone-700 transition" />
                                        </div>
                                    </div>
                                @empty
                                    <div class="p-4 text-center text-zinc-400 italic text-[11px]">
                                        {{ __('No se encontraron órdenes en backlog para ":query".', ['query' => $backlogSearch]) }}
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>

    <!-- MAIN GRID CONTAINER: TOGGLED BY VIEW MODE -->
    @if($viewMode === 'by_day')
        <!-- Weekly Grid by Day -->
        <div class="space-y-6 flex-1 min-h-0 max-w-7xl mx-auto w-full">
            @foreach($designers as $designer)
                @php
                    $designerSubtasks = $subtasks->filter(function ($st) use ($designer) {
                        if ($st->assignee_id) {
                            return (int) $st->assignee_id === (int) $designer->id;
                        }
                        if ($st->order) {
                            return (int) $st->order->designer_id === (int) $designer->id || $st->order->designers->contains('id', $designer->id);
                        }
                        return false;
                    });
                @endphp
                <div class="space-y-2">
                    
                    <div class="flex items-center justify-between pb-1">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full shrink-0 {{ $designer->dot_color_class }}"></span>
                            <h3 class="font-semibold text-base text-zinc-900 tracking-tight">Diseñador/a: {{ $designer->name }}</h3>
                        </div>
                    </div>

                    <!-- 6 Columns Grid (Monday-Friday + Next Week) with horizontal overflow safety -->
                    <div class="overflow-x-auto custom-horizontal-scrollbar pb-1">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 min-w-[700px] lg:min-w-full">
                            @foreach($days as $day)
                                @php
                                    $isNextWeek = $day['is_next_week'] ?? false;
                                    $isToday = !$isNextWeek && $day['date']->isToday();
                                    
                                    $dayOrders = $isNextWeek
                                        ? $designer->orders->filter(fn($o) => $o->scheduled_date && $o->scheduled_date->gte(Carbon\Carbon::parse($day['date_string'])))
                                        : $designer->orders->filter(fn($o) => $o->scheduled_date?->toDateString() === $day['date_string']);

                                    $daySubtasks = $isNextWeek
                                        ? $designerSubtasks->filter(fn($st) => $st->scheduled_date && $st->scheduled_date->gte(Carbon\Carbon::parse($day['date_string'])))
                                        : $designerSubtasks->filter(fn($st) => $st->scheduled_date?->toDateString() === $day['date_string']);

                                    if (! $showSystemTasks) {
                                        $daySubtasks = $daySubtasks->filter(fn($st) => $st->isWorkTask());
                                    }

                                    $dayColorClass = match($day['day_name'] ?? '') {
                                        'Lunes' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                                        'Martes' => 'bg-sky-50 text-sky-700 border-sky-200',
                                        'Miércoles' => 'bg-teal-50 text-teal-700 border-teal-200',
                                        'Jueves' => 'bg-amber-50 text-amber-800 border-amber-200',
                                        'Viernes' => 'bg-rose-50 text-rose-700 border-rose-200',
                                        'Next Week' => 'bg-violet-50 text-violet-900 border-violet-300 border-dashed',
                                        default => 'bg-stone-50 text-stone-700 border-stone-200',
                                    };
                                @endphp
                                <div 
                                    x-data="{ draggingOver: false }"
                                    @dragover.prevent="draggingOver = true"
                                    @dragenter.prevent="draggingOver = true"
                                    @dragleave="if (!$el.contains($event.relatedTarget)) draggingOver = false"
                                    @drop.prevent="
                                        draggingOver = false;
                                        let rawData = $event.dataTransfer.getData('text/plain');
                                        if (rawData) {
                                            if (rawData.startsWith('subtask:')) {
                                                $wire.rescheduleSubtask(rawData.replace('subtask:', ''), '{{ $day['date_string'] }}');
                                            } else {
                                                let id = rawData.replace('order:', '');
                                                $wire.scheduleOrder(id, '{{ $day['date_string'] }}');
                                            }
                                        }
                                    "
                                    :class="{ 'border-stone-400 ring-2 ring-stone-300/60 bg-stone-100': draggingOver }"
                                    class="bg-[#f7f7f5] border border-[#e9e9e7] rounded-xl flex flex-col min-h-[140px] overflow-hidden transition duration-150 shadow-2xs">
                                    
                                    <!-- Kanban Column Header -->
                                    <div class="p-2.5 bg-[#efefed] border-b border-[#e9e9e7] rounded-t-xl flex items-center justify-between sticky top-0 z-10 shrink-0">
                                        <div class="flex items-center gap-1.5 min-w-0">
                                            <span class="px-1.5 py-0.2 rounded text-[9px] font-bold border uppercase tracking-wider {{ $dayColorClass }}">
                                                {{ $day['day_name'] }}
                                            </span>
                                            @if($isToday)
                                                <span class="px-1 py-0.2 rounded bg-amber-500 text-white text-[8px] font-bold uppercase tracking-wider">
                                                    HOY
                                                </span>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-1.5 shrink-0 ml-1">
                                            <span class="text-[9px] font-mono font-medium {{ $isToday ? 'text-amber-800 font-bold' : 'text-zinc-400' }}">
                                                {{ $isNextWeek ? $day['range_label'] : $day['date']->format('d M') }}
                                            </span>
                                            <span class="px-1.5 py-0.5 rounded bg-white text-[9.5px] font-mono text-zinc-600 border border-stone-200 font-semibold shrink-0">
                                                {{ $dayOrders->count() + $daySubtasks->count() }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Kanban Cards Body -->
                                    <div class="p-2 overflow-y-auto flex-1 space-y-2 min-h-0">
                                        @if($dayOrders->isEmpty() && $daySubtasks->isEmpty())
                                            <div class="py-8 text-center border border-dashed border-stone-300/70 rounded-lg my-1">
                                                <span class="text-[10px] text-zinc-400 font-normal block">Sin trabajo agendado</span>
                                            </div>
                                        @else
                                            <div class="space-y-2">
                                                <!-- Main Order Kanban Cards -->
                                                @foreach($dayOrders as $order)
                                                    <div 
                                                        draggable="true" 
                                                        @dragstart="e => e.dataTransfer.setData('text/plain', 'order:{{ $order->id }}')"
                                                        x-data="{ openSub: false, customTitle: '', targetDate: '{{ $day['date_string'] }}' }"
                                                        class="bg-white border border-[#e9e9e7] hover:border-stone-400 rounded-xl p-2.5 space-y-1.5 shadow-2xs transition group relative select-none cursor-grab active:cursor-grabbing {{ $order->isUrgente() ? ($order->done_today ? 'bg-[#fafaf9] opacity-75' : 'bg-gradient-to-br from-rose-50/90 via-white to-red-50/70 border-2 border-red-500/90 ring-1 ring-red-300/40') : ($order->isOverdue() && !$order->done_today ? 'bg-rose-50/50 border-rose-200' : '') }}">
                                                        
                                                        <div class="flex items-start justify-between gap-1.5 min-w-0">
                                                            <div class="flex items-center gap-2 min-w-0 flex-1">
                                                                <button 
                                                                    wire:click="toggleDoneToday({{ $order->id }})" 
                                                                    type="button"
                                                                    class="w-3.5 h-3.5 rounded-full border transition flex items-center justify-center shrink-0 cursor-pointer mt-0.5 {{ $order->done_today ? 'bg-emerald-500 border-emerald-500 text-white shadow-2xs' : 'border-stone-300 hover:border-emerald-500 bg-white text-transparent hover:text-emerald-500/40' }}">
                                                                    <x-lucide-check class="w-2.5 h-2.5 stroke-[3]" />
                                                                </button>
                                                                <div class="min-w-0 flex-1">
                                                                    <h4 class="font-bold text-[11px] text-zinc-900 truncate leading-tight {{ $order->done_today ? 'line-through text-zinc-400' : '' }}">{{ $order->company_name }}</h4>
                                                                    <p class="font-normal text-[10px] text-zinc-500 truncate leading-tight mt-0.5 {{ $order->done_today ? 'line-through text-zinc-400' : '' }}">{{ $order->task_name }}</p>
                                                                </div>
                                                            </div>

                                                            <div class="flex items-center gap-1 shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                                                                <button wire:click="unscheduleOrder({{ $order->id }})" class="p-0.5 text-zinc-400 hover:text-red-600 transition" title="Desprogramar">
                                                                    <x-lucide-x-circle class="w-3 h-3" />
                                                                </button>
                                                                <button wire:click="$dispatch('open-order-detail', { orderId: {{ $order->id }} })" class="p-0.5 text-zinc-400 hover:text-zinc-700 transition" title="Ver detalle">
                                                                    <x-lucide-panel-right class="w-3 h-3" />
                                                                </button>
                                                            </div>
                                                        </div>

                                                        @if($order->current_due_date)
                                                            @php
                                                                $orderOverSla = ! $order->isSlaExempt() && $order->scheduled_date && $order->scheduled_date->gt($order->current_due_date);
                                                                $orderOverdue = $order->isOverdue();
                                                            @endphp
                                                            <div class="flex items-center justify-between pt-1 border-t border-stone-100 text-[9px]">
                                                                @if($orderOverSla || $orderOverdue)
                                                                    <span class="font-bold text-red-600 inline-flex items-center gap-1" title="SLA: {{ $order->current_due_date->format('d M, Y') }}">
                                                                        <x-lucide-alert-triangle class="w-2.5 h-2.5 text-red-600 shrink-0" />
                                                                        <span>SLA Excedido</span>
                                                                    </span>
                                                                @else
                                                                    <span class="text-zinc-400 font-mono">
                                                                        SLA: {{ $order->current_due_date->format('d M') }}
                                                                    </span>
                                                                @endif

                                                                <button 
                                                                    @click="openSub = !openSub"
                                                                    type="button" 
                                                                    class="text-violet-700 hover:text-violet-900 font-semibold opacity-0 group-hover:opacity-100 transition-opacity flex items-center gap-0.5 px-1 py-0.2 rounded hover:bg-violet-50">
                                                                    <x-lucide-plus-circle class="w-2.5 h-2.5" />
                                                                    <span>+ Subtarea</span>
                                                                </button>
                                                            </div>
                                                        @else
                                                            <div class="pt-1 border-t border-stone-100 flex items-center justify-end text-[9px]">
                                                                <button 
                                                                    @click="openSub = !openSub"
                                                                    type="button" 
                                                                    class="text-violet-700 hover:text-violet-900 font-semibold opacity-0 group-hover:opacity-100 transition-opacity flex items-center gap-0.5 px-1 py-0.2 rounded hover:bg-violet-50">
                                                                    <x-lucide-plus-circle class="w-2.5 h-2.5" />
                                                                    <span>+ Subtarea</span>
                                                                </button>
                                                            </div>
                                                        @endif

                                                        <div 
                                                            x-show="openSub" 
                                                            @click.outside="openSub = false"
                                                            class="absolute left-0 right-0 top-full mt-1 z-50 bg-white border border-[#e9e9e7] rounded-lg shadow-xl p-2 text-xs space-y-1.5">
                                                            <div>
                                                                <span class="text-[9px] text-zinc-400 block mb-0.5">Programar para día:</span>
                                                                <select x-model="targetDate" class="w-full bg-stone-50 border border-stone-200 rounded px-1 py-0.5 text-[10px] text-zinc-800 focus:outline-none">
                                                                    @foreach($days as $d)
                                                                        <option value="{{ $d['date_string'] }}">{{ $d['day_name'] }} ({{ ($d['is_next_week'] ?? false) ? $d['range_label'] : $d['date']->format('d M') }})</option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                            <div class="flex flex-wrap gap-1 pt-1 border-t border-stone-100">
                                                                @foreach($subtaskPresets as $preset)
                                                                    @php
                                                                        $safePlannerIcon = (preg_match('/^[a-z0-9\-]+$/i', $preset->emoji ?? '')) ? $preset->emoji : 'tag';
                                                                    @endphp
                                                                    <button 
                                                                        type="button"
                                                                        @click="$wire.scheduleSubtask({{ $order->id }}, '{{ addslashes($preset->title) }}', targetDate, {{ $designer->id }}); openSub = false"
                                                                        class="px-1.5 py-0.5 rounded font-medium text-[10px] border transition shadow-2xs inline-flex items-center gap-1 {{ $preset->badgeStyle() }}">
                                                                        <x-dynamic-component :component="'lucide-' . $safePlannerIcon" class="w-3 h-3" />
                                                                        <span>{{ $preset->title }}</span>
                                                                    </button>
                                                                @endforeach
                                                            </div>
                                                            <div class="pt-1 border-t border-stone-100">
                                                                <input 
                                                                    type="text" 
                                                                    x-model="customTitle"
                                                                    @keyup.enter="if(customTitle.trim()) { $wire.scheduleSubtask({{ $order->id }}, customTitle, targetDate, {{ $designer->id }}); customTitle = ''; openSub = false; }"
                                                                    placeholder="Escribe subtarea custom... (Enter)" 
                                                                    class="w-full bg-stone-50 border border-stone-200 rounded px-2 py-1 text-[11px] text-zinc-800 focus:outline-none">
                                                            </div>
                                                        </div>

                                                    </div>
                                                @endforeach

                                                <!-- Day Subtask Kanban Cards -->
                                                @foreach($daySubtasks as $stask)
                                                    <div 
                                                        draggable="true" 
                                                        @dragstart="e => e.dataTransfer.setData('text/plain', 'subtask:{{ $stask->id }}')"
                                                        class="bg-white/80 border border-stone-200/80 hover:border-stone-300 rounded-lg p-2 space-y-1 shadow-2xs transition group relative select-none cursor-grab active:cursor-grabbing">
                                                        
                                                        <div class="flex items-center justify-between gap-1 min-w-0">
                                                            <div class="flex items-center gap-2 min-w-0 flex-1">
                                                                <button 
                                                                    wire:click="toggleSubtaskComplete({{ $stask->id }})" 
                                                                    type="button"
                                                                    class="w-3.5 h-3.5 rounded-full border transition flex items-center justify-center shrink-0 cursor-pointer {{ $stask->isDone() ? 'bg-emerald-500 border-emerald-500 text-white shadow-2xs' : 'border-stone-300 hover:border-emerald-500 bg-white text-transparent hover:text-emerald-500/40' }}">
                                                                    <x-lucide-check class="w-2.5 h-2.5 stroke-[3]" />
                                                                                                                     <div class="min-w-0 flex-1" @click="if({{ $stask->isNote() ? 'true' : 'false' }}) { $dispatch('open-link-note-modal', { taskId: {{ $stask->id }}, noteTitle: '{{ addslashes($stask->title) }}' }) }">
                                                                    <h5 class="font-bold text-[11px] truncate leading-tight {{ $stask->isDone() ? 'line-through text-zinc-400' : ($stask->isSystemTask() ? 'text-violet-700 font-semibold' : 'text-zinc-900') }} {{ $stask->isNote() ? 'cursor-pointer hover:text-amber-700' : '' }}">
                                                                        {{ $stask->title }}
                                                                    </h5>
                                                                </div>
                                                            </div>

                                                            <div class="flex items-center gap-0.5 shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                                                                <button wire:click="deleteSubtask({{ $stask->id }})" class="p-0.5 text-zinc-400 hover:text-red-600 transition" title="Eliminar subtarea">
                                                                    <x-lucide-x-circle class="w-3 h-3" />
                                                                </button>
                                                            </div>
                                                        </div>

                                                        @if($stask->order)
                                                            <div class="pt-0.5 flex items-center justify-between gap-1 text-[10px]">
                                                                <button 
                                                                    wire:click="$dispatch('open-order-detail', { orderId: {{ $stask->order->id }} })" 
                                                                    type="button"
                                                                    class="text-left min-w-0 flex-1 hover:underline group/link">
                                                                    <div class="flex items-center gap-1 min-w-0">
                                                                        <x-lucide-link class="w-2.5 h-2.5 text-indigo-500 shrink-0 group-hover/link:text-indigo-600" />
                                                                        <span class="font-bold text-[10px] truncate leading-tight {{ $stask->isSystemTask() ? 'text-violet-700' : 'text-zinc-800' }}">{{ $stask->order->company_name }}</span>
                                                                        @if($stask->order->location_text)
                                                                            <span class="inline-flex items-center gap-0.5 text-[9.5px] font-bold text-zinc-900 shrink-0 truncate max-w-[100px]">
                                                                                <x-lucide-map-pin class="w-2.5 h-2.5 text-red-500 shrink-0 stroke-[2.5]" />
                                                                                <span class="uppercase truncate">{{ $stask->order->location_text }}</span>
                                                                            </span>
                                                                        @endif
                                                                        @if($stask->order->task_name)
                                                                            <span class="text-[9.5px] truncate leading-tight {{ $stask->isSystemTask() ? 'text-violet-600 font-medium' : 'text-zinc-500' }}">• {{ $stask->order->task_name }}</span>
                                                                        @endif
                                                                    </div>
                                                                </button>
                                                                <button 
                                                                    wire:click="$dispatch('open-order-detail', { orderId: {{ $stask->order->id }} })" 
                                                                    class="p-0.5 text-zinc-400 hover:text-zinc-700 shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                                                                    <x-lucide-panel-right class="w-2.5 h-2.5" />
                                                                </button>
                                                            </div>
                                                            @if($stask->order->current_due_date)
                                                                @php
                                                                    $staskOverSla = ! $stask->isFollowUp() && ! $stask->order->isSlaExempt() && $stask->scheduled_date && $stask->scheduled_date->gt($stask->order->current_due_date);
                                                                    $staskOverdue = $stask->order->isOverdue();
                                                                @endphp
                                                                @if($staskOverSla || $staskOverdue)
                                                                    <div class="pt-0.5 text-[9px] font-bold text-red-600 flex items-center gap-1" title="SLA: {{ $stask->order->current_due_date->format('d M, Y') }}">
                                                                        <x-lucide-alert-triangle class="w-2.5 h-2.5 text-red-600 shrink-0" />
                                                                        <span>SLA Excedido</span>
                                                                    </div>
                                                                @endif
                                                            @endif
                                                        @else
                                                            <div class="pt-0.5 flex items-center justify-between gap-1 text-[10px]">
                                                                <button 
                                                                    @click="$dispatch('open-link-note-modal', { taskId: {{ $stask->id }}, noteTitle: '{{ addslashes($stask->title) }}' })" 
                                                                    type="button"
                                                                    class="text-left min-w-0 flex-1 group/link flex items-center gap-1 cursor-pointer">
                                                                    <span class="inline-flex items-center gap-1 text-[9.5px] font-bold text-amber-800 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-200/80">
                                                                        <x-lucide-sticky-note class="w-3 h-3 text-amber-600 shrink-0" />
                                                                        <span>{{ __('Nota (Sin Orden)') }}</span>
                                                                        <span class="text-[9px] text-amber-600 font-normal underline hover:text-amber-900">({{ __('Vincular orden') }})</span>
                                                                    </span>
                                                                </button>
                                                            </div>
                                                        @endif

                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>

                                </div>
                            @endforeach
                        </div>
                    </div>

                </div>
            @endforeach
        </div>
    @else
        <!-- Weekly Grid by Designer (Columnas por Diseñador subdivididas por Días) -->
        @php
            $designerGridList = $designers->reject(fn($d) => str_contains(mb_strtolower($d->name), 'externo'));
            $colCount = max(1, $designerGridList->count());
            $gridColsClass = match($colCount) {
                1 => 'grid-cols-1 max-w-md',
                2 => 'grid-cols-1 md:grid-cols-2 max-w-4xl',
                3 => 'grid-cols-1 md:grid-cols-3 max-w-6xl',
                4 => 'grid-cols-1 md:grid-cols-2 lg:grid-cols-4 max-w-7xl',
                default => 'grid-cols-1 md:grid-cols-3 lg:grid-cols-5 max-w-7xl',
            };
        @endphp
        <div class="overflow-x-auto custom-horizontal-scrollbar pb-2 w-full min-h-0 flex-1 flex justify-center">
            <div class="grid {{ $gridColsClass }} gap-6 items-start divide-x divide-stone-200/60 w-full mx-auto">
                @foreach($designerGridList as $designer)
                    @php
                        $designerSubtasks = $subtasks->filter(function ($st) use ($designer) {
                            if ($st->assignee_id) {
                                return (int) $st->assignee_id === (int) $designer->id;
                            }
                            if ($st->order) {
                                return (int) $st->order->designer_id === (int) $designer->id || $st->order->designers->contains('id', $designer->id);
                            }
                            return false;
                        });
                        $designerTotalCount = $designerSubtasks->count();
                        $visibleDesignerSubtasks = $showSystemTasks ? $designerSubtasks : $designerSubtasks->filter(fn($st) => $st->isWorkTask());
                        $designerVisibleCount = $visibleDesignerSubtasks->count();
                    @endphp
                    <div class="flex flex-col min-w-0 pr-4 space-y-3">
                        
                        <!-- Designer Header -->
                        <div class="flex items-center justify-between border-b border-stone-200/60 pb-2">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="w-2.5 h-2.5 rounded-full shrink-0 {{ $designer->dot_color_class }}"></span>
                                <h3 class="font-semibold text-base text-zinc-900 truncate tracking-tight" title="{{ $designer->name }}">{{ $designer->name }}</h3>
                            </div>
                            <div class="flex items-center gap-1.5 shrink-0">
                                <span class="px-2 py-0.5 rounded-full bg-stone-100 text-zinc-600 font-mono text-[10px] font-bold" title="{{ __('Subtareas semanales') }}">
                                    @if(!$showSystemTasks && $designerVisibleCount !== $designerTotalCount)
                                        {{ $designerVisibleCount }} ({{ $designerTotalCount }})
                                    @else
                                        {{ $designerVisibleCount }}
                                    @endif
                                </span>
                                <button 
                                    type="button"
                                    @click.stop="$dispatch('open-subtask-modal', { designerId: '{{ $designer->id }}' })" 
                                    class="p-1 rounded hover:bg-stone-100 text-zinc-500 hover:text-zinc-900 transition cursor-pointer"
                                    title="{{ __('Agregar subtarea a') }} {{ $designer->name }}">
                                    <x-lucide-plus class="w-4 h-4" />
                                </button>
                            </div>
                        </div>

                        <!-- Day Subdivisions Inside Designer Column -->
                        <div class="space-y-3">
                            @foreach($days as $day)
                                @php
                                    $isNextWeek = $day['is_next_week'] ?? false;
                                    $isToday = !$isNextWeek && $day['date']->isToday();
                                    $isFirstDayOfWeek = !$isNextWeek && $loop->first;

                                    $daySubtasks = $isNextWeek
                                        ? $designerSubtasks->filter(fn($st) => $st->scheduled_date && $st->scheduled_date->gte(Carbon\Carbon::parse($day['date_string'])))
                                        : $designerSubtasks->filter(function ($st) use ($day, $isFirstDayOfWeek) {
                                            if ($st->scheduled_date?->toDateString() === $day['date_string']) {
                                                return true;
                                            }
                                            if ($isFirstDayOfWeek && $st->scheduled_date && $st->scheduled_date->lt(Carbon\Carbon::parse($day['date_string'])) && $st->status !== 'done') {
                                                return true;
                                            }
                                            return false;
                                        });

                                    if (! $showSystemTasks) {
                                        $daySubtasks = $daySubtasks->filter(fn($st) => $st->isWorkTask());
                                    }

                                    $dayColorClass = match($day['day_name'] ?? '') {
                                        'Lunes', 'Monday' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                                        'Martes', 'Tuesday' => 'bg-sky-50 text-sky-700 border-sky-200',
                                        'Miércoles', 'Wednesday' => 'bg-teal-50 text-teal-700 border-teal-200',
                                        'Jueves', 'Thursday' => 'bg-amber-50 text-amber-800 border-amber-200',
                                        'Viernes', 'Friday' => 'bg-rose-50 text-rose-700 border-rose-200',
                                        'Próxima Semana', 'Next Week' => 'bg-violet-50 text-violet-900 border-violet-300 border-dashed',
                                        default => 'bg-stone-50 text-stone-700 border-stone-200',
                                    };
                                @endphp

                                <div 
                                    x-data="{ 
                                        draggingOver: false,
                                        inlineActive: false,
                                        orderSearch: '',
                                        selectedOrderId: '',
                                        selectedOrderCompany: '',
                                        selectedOrderTask: '',
                                        selectedOrderLocation: '',
                                        subtaskTitle: '',
                                        isWorkTask: true,
                                        dropdownOpen: false,
                                        presetDropdownOpen: false,
                                        presetHighlightedIndex: -1,
                                        orderHighlightedIndex: -1,
                                        titleFocused: false,
                                        getFilteredOrders() {
                                            if (!this.orderSearch) return this.workspaceOrdersList || [];
                                            const q = this.orderSearch.toLowerCase().trim();
                                            return (this.workspaceOrdersList || []).filter(o => 
                                                (o.text && o.text.toLowerCase().includes(q)) || 
                                                (o.company && o.company.toLowerCase().includes(q)) || 
                                                (o.location && o.location.toLowerCase().includes(q)) ||
                                                (o.task && o.task.toLowerCase().includes(q)) ||
                                                (o.wo_number && o.wo_number.toLowerCase().includes(q)) ||
                                                (o.trello_card_title && o.trello_card_title.toLowerCase().includes(q))
                                            );
                                        },
                                        navigateOrder(step) {
                                            const list = this.getFilteredOrders();
                                            if (list.length === 0) return;
                                            if (this.orderHighlightedIndex === -1) {
                                                this.orderHighlightedIndex = step > 0 ? 0 : list.length - 1;
                                            } else {
                                                this.orderHighlightedIndex = (this.orderHighlightedIndex + step + list.length) % list.length;
                                            }
                                            this.$nextTick(() => {
                                                const container = this.$refs.orderDropdownPanel;
                                                if (!container) return;
                                                const items = container.querySelectorAll('.order-item-btn');
                                                if (items[this.orderHighlightedIndex]) {
                                                    items[this.orderHighlightedIndex].scrollIntoView({ block: 'nearest' });
                                                }
                                            });
                                        },
                                        selectHighlightedOrder() {
                                            const list = this.getFilteredOrders();
                                            if (this.orderHighlightedIndex >= 0 && list[this.orderHighlightedIndex]) {
                                                this.selectOrder(list[this.orderHighlightedIndex]);
                                            }
                                        },
                                        handleOrderEnter() {
                                            const list = this.getFilteredOrders();
                                            if (this.dropdownOpen && this.orderHighlightedIndex >= 0 && list[this.orderHighlightedIndex]) {
                                                this.selectHighlightedOrder();
                                            } else if (this.orderSearch.trim()) {
                                                $wire.scheduleSubtask(null, this.orderSearch.trim(), '{{ $day['date_string'] }}', '{{ $designer->id }}', this.isWorkTask);
                                                this.resetInline();
                                            }
                                        },
                                        getFilteredPresets() {
                                            const list = this.subtaskPresetsList || [];
                                            if (!this.subtaskTitle) return list;
                                            const q = this.subtaskTitle.toLowerCase().trim();
                                            return list.filter(p => p.title.toLowerCase().includes(q));
                                        },
                                        selectPreset(preset) {
                                            this.subtaskTitle = preset.title;
                                            if (preset.is_work_task !== undefined && preset.is_work_task !== null) {
                                                this.isWorkTask = Boolean(preset.is_work_task);
                                            }
                                            this.presetDropdownOpen = false;
                                            this.presetHighlightedIndex = -1;
                                            this.$nextTick(() => {
                                                if (this.$refs.titleInput) this.$refs.titleInput.focus();
                                            });
                                        },
                                        navigatePreset(step) {
                                            const list = this.getFilteredPresets();
                                            if (list.length === 0) return;
                                            if (this.presetHighlightedIndex === -1) {
                                                this.presetHighlightedIndex = step > 0 ? 0 : list.length - 1;
                                            } else {
                                                this.presetHighlightedIndex = (this.presetHighlightedIndex + step + list.length) % list.length;
                                            }
                                            this.$nextTick(() => {
                                                const container = this.$refs.presetDropdownPanel;
                                                if (!container) return;
                                                const items = container.querySelectorAll('.preset-item-btn');
                                                if (items[this.presetHighlightedIndex]) {
                                                    items[this.presetHighlightedIndex].scrollIntoView({ block: 'nearest' });
                                                }
                                            });
                                        },
                                        openInline() {
                                            this.inlineActive = true;
                                            this.isWorkTask = true;
                                            this.orderHighlightedIndex = -1;
                                            this.$nextTick(() => {
                                                if (this.$refs.orderSearchInput) this.$refs.orderSearchInput.focus();
                                            });
                                        },
                                        selectOrder(order) {
                                            this.selectedOrderId = String(order.id);
                                            this.selectedOrderCompany = order.company;
                                            this.selectedOrderTask = order.task || '';
                                            this.selectedOrderLocation = order.location || '';
                                            this.orderSearch = order.text;
                                            this.dropdownOpen = false;
                                            this.orderHighlightedIndex = -1;
                                            this.presetDropdownOpen = true;
                                            this.presetHighlightedIndex = -1;
                                            this.$nextTick(() => {
                                                if (this.$refs.titleInput) this.$refs.titleInput.focus();
                                            });
                                        },
                                        submitSubtask() {
                                            if (!this.selectedOrderId) {
                                                if (this.orderSearch.trim()) {
                                                    $wire.scheduleSubtask(null, this.orderSearch.trim(), '{{ $day['date_string'] }}', '{{ $designer->id }}', this.isWorkTask);
                                                    this.resetInline();
                                                    return;
                                                }
                                                alert('{{ __('Por favor ingresa el nombre de la subtarea o nota.') }}');
                                                return;
                                            }
                                            if (!this.subtaskTitle.trim()) {
                                                alert('{{ __('Por favor ingresa el nombre de la subtarea.') }}');
                                                return;
                                            }
                                            $wire.scheduleSubtask(this.selectedOrderId, this.subtaskTitle.trim(), '{{ $day['date_string'] }}', '{{ $designer->id }}', this.isWorkTask);
                                            this.resetInline();
                                        },
                                        resetInline() {
                                            this.orderSearch = '';
                                            this.selectedOrderId = '';
                                            this.selectedOrderCompany = '';
                                            this.selectedOrderTask = '';
                                            this.selectedOrderLocation = '';
                                            this.subtaskTitle = '';
                                            this.isWorkTask = true;
                                            this.dropdownOpen = false;
                                            this.orderHighlightedIndex = -1;
                                            this.presetDropdownOpen = false;
                                            this.presetHighlightedIndex = -1;
                                            this.$nextTick(() => {
                                                if (this.$refs.orderSearchInput) this.$refs.orderSearchInput.focus();
                                            });
                                        },
                                        cancelInline() {
                                            this.inlineActive = false;
                                            this.orderSearch = '';
                                            this.selectedOrderId = '';
                                            this.selectedOrderCompany = '';
                                            this.selectedOrderTask = '';
                                            this.selectedOrderLocation = '';
                                            this.subtaskTitle = '';
                                            this.isWorkTask = true;
                                            this.dropdownOpen = false;
                                            this.orderHighlightedIndex = -1;
                                            this.presetDropdownOpen = false;
                                            this.presetHighlightedIndex = -1;
                                        }
                                    }"
                                    @dragover.prevent="draggingOver = true"
                                    @dragenter.prevent="draggingOver = true"
                                    @dragleave="if (!$el.contains($event.relatedTarget)) draggingOver = false"
                                    @drop.prevent="
                                        draggingOver = false;
                                        let rawData = $event.dataTransfer.getData('text/plain');
                                        if (rawData) {
                                            if (rawData.startsWith('subtask:')) {
                                                $wire.rescheduleSubtask(rawData.replace('subtask:', ''), '{{ $day['date_string'] }}');
                                            } else {
                                                let id = rawData.replace('order:', '');
                                                $wire.scheduleOrder(id, '{{ $day['date_string'] }}');
                                            }
                                        }
                                    "
                                    :class="{ 
                                        'bg-indigo-50/70 rounded-md': draggingOver, 
                                        '{{ $isToday ? 'bg-amber-50/30 rounded-md px-1.5' : '' }}': !draggingOver 
                                    }"
                                    class="pt-3 pb-2 border-t border-stone-200/40 first:border-t-0 first:pt-0 space-y-1.5 min-h-[50px] flex flex-col justify-between min-w-0 transition-colors">
                                    
                                    <div class="min-w-0 space-y-1">
                                        <!-- Day Subdivision Header -->
                                        <div class="flex items-center justify-between pb-0.5">
                                            <div class="flex items-center gap-1.5 min-w-0">
                                                <span class="px-1.5 py-0.2 rounded text-[9px] font-bold border uppercase tracking-wider {{ $dayColorClass }}">
                                                    {{ $day['day_name'] }}
                                                </span>
                                                @if($isToday)
                                                    <span class="px-1.5 py-0.2 rounded bg-amber-500 text-white text-[8px] font-bold uppercase tracking-wider">
                                                        HOY
                                                    </span>
                                                @endif
                                            </div>
                                            <span class="text-[9px] font-mono text-zinc-400">
                                                {{ $isNextWeek ? $day['range_label'] : $day['date']->format('d M') }}
                                            </span>
                                        </div>

                                        <!-- Subtasks List for this Day & Designer -->
                                        @if($daySubtasks->isNotEmpty())
                                            <div class="space-y-0.5">
                                                @foreach($daySubtasks as $stask)
                                                    @php
                                                        $staskDone = $stask->isDone();
                                                    @endphp
                                                    <div 
                                                        draggable="true" 
                                                        @dragstart="e => e.dataTransfer.setData('text/plain', 'subtask:{{ $stask->id }}')"
                                                        @click.stop="if({{ $stask->order ? 'true' : 'false' }}) { $dispatch('open-order-detail', { orderId: {{ $stask->order?->id ?? 0 }} }) } else { $dispatch('open-link-note-modal', { taskId: {{ $stask->id }}, noteTitle: '{{ addslashes($stask->title) }}' }) }"
                                                        class="py-1.5 px-2 flex items-start justify-between gap-2 min-w-0 cursor-pointer active:cursor-grabbing hover:bg-stone-100/80 rounded-md transition group {{ $staskDone ? 'opacity-60' : '' }}">
                                                        
                                                        <div class="flex items-start gap-2 min-w-0 flex-1 overflow-visible">
                                                            <button 
                                                                @click.stop="$wire.toggleSubtaskComplete({{ $stask->id }})" 
                                                                type="button"
                                                                class="w-3.5 h-3.5 mt-0.5 rounded-full border transition flex items-center justify-center shrink-0 cursor-pointer {{ $staskDone ? 'bg-emerald-500 border-emerald-500 text-white shadow-2xs' : 'border-stone-300 hover:border-emerald-500 bg-white text-transparent hover:text-emerald-500/40' }}"
                                                                title="{{ $staskDone ? 'Subtarea completada' : 'Marcar subtarea como completada' }}">
                                                                <x-lucide-check class="w-2.5 h-2.5 stroke-[3]" />
                                                            </button>

                                                            <div class="min-w-0 flex-1 flex flex-col gap-0.5 overflow-visible">
                                                                @if($stask->order)
                                                                    <!-- Row 1: Order Details (Company, Location, Task Name) -->
                                                                    <div class="flex items-center gap-1 text-[10.5px] leading-tight min-w-0">
                                                                        <!-- Company Name with Instant Tooltip -->
                                                                        <div class="relative group/tip min-w-0 shrink">
                                                                            <span class="uppercase tracking-tight block truncate {{ $staskDone ? 'line-through text-zinc-400' : ($stask->isSystemTask() ? 'text-violet-700 font-bold' : 'text-zinc-900 font-bold') }}">
                                                                                {{ $stask->order->company_name }}
                                                                            </span>
                                                                            <div class="absolute bottom-full left-0 mb-1 hidden group-hover/tip:flex items-center px-1.5 py-0.5 text-[9.5px] font-medium text-white bg-zinc-900 rounded shadow-md whitespace-nowrap z-50 pointer-events-none">
                                                                                {{ $stask->order->company_name }}
                                                                            </div>
                                                                        </div>

                                                                        @if($stask->order->location_text)
                                                                            <!-- Location Name with Instant Tooltip -->
                                                                            <div class="relative group/tip min-w-0 shrink flex items-center gap-0.5">
                                                                                <x-lucide-map-pin class="w-2.5 h-2.5 text-red-500 shrink-0 stroke-[2.5]" />
                                                                                <span class="uppercase tracking-tight block truncate {{ $staskDone ? 'line-through text-zinc-400' : 'text-zinc-900 font-bold' }} text-[10px]">
                                                                                    {{ $stask->order->location_text }}
                                                                                </span>
                                                                                <div class="absolute bottom-full left-0 mb-1 hidden group-hover/tip:flex items-center px-1.5 py-0.5 text-[9.5px] font-medium text-white bg-zinc-900 rounded shadow-md whitespace-nowrap z-50 pointer-events-none">
                                                                                    {{ $stask->order->location_text }}
                                                                                </div>
                                                                            </div>
                                                                        @endif

                                                                        @if($stask->order->task_name)
                                                                            <!-- Order Name with Instant Tooltip -->
                                                                            <div class="relative group/tip min-w-0 flex-1">
                                                                                <span class="block truncate {{ $staskDone ? 'line-through text-zinc-400' : ($stask->isSystemTask() ? 'text-violet-600 font-medium' : 'text-zinc-500 font-medium') }} text-[10px]">
                                                                                    • {{ $stask->order->task_name }}
                                                                                </span>
                                                                                <div class="absolute bottom-full left-0 mb-1 hidden group-hover/tip:flex items-center px-1.5 py-0.5 text-[9.5px] font-medium text-white bg-zinc-900 rounded shadow-md whitespace-nowrap z-50 pointer-events-none">
                                                                                    {{ $stask->order->task_name }}
                                                                                </div>
                                                                            </div>
                                                                        @endif
                                                                    </div>

                                                                    <!-- Row 2: Subtask Badge/Title & SLA Warning -->
                                                                    <div class="flex items-center gap-1 text-[10px] leading-tight min-w-0 pt-0.5">
                                                                        <!-- Subtask Title Badge -->
                                                                        @php
                                                                            $presetMatch = $subtaskPresets->firstWhere('title', $stask->title);
                                                                        @endphp
                                                                        @if($presetMatch)
                                                                            <span class="px-1.5 py-0.2 rounded text-[9.5px] font-medium border shrink-0 inline-flex items-center gap-1 max-w-full truncate {{ $presetMatch->badgeStyle() }} {{ $staskDone ? 'opacity-50 line-through' : '' }}">
                                                                                <span class="truncate">{{ $stask->title }}</span>
                                                                            </span>
                                                                        @else
                                                                            <span class="font-medium px-1.5 py-0.2 rounded text-[9.5px] shrink-0 max-w-full truncate {{ $staskDone ? 'line-through text-zinc-400 bg-stone-100 border-stone-200' : ($stask->isSystemTask() ? 'text-violet-800 bg-violet-50 border border-violet-200' : ($stask->isNote() ? 'text-amber-900 bg-amber-50 border border-amber-200/60' : 'text-amber-900 bg-amber-50 border border-amber-200/60')) }}">
                                                                                <span class="truncate">{{ $stask->title }}</span>
                                                                            </span>
                                                                        @endif

                                                                        <!-- SLA Badge with Instant Tooltip -->
                                                                        @if($stask->order->current_due_date)
                                                                            @php
                                                                                $staskOverSla = ! $stask->isFollowUp() && ! $stask->order->isSlaExempt() && $stask->scheduled_date && $stask->scheduled_date->gt($stask->order->current_due_date);
                                                                                $staskOverdue = $stask->order->isOverdue();
                                                                            @endphp
                                                                            @if($staskOverSla || $staskOverdue)
                                                                                <div class="relative group/tip shrink-0">
                                                                                    <span class="text-[9px] font-bold text-red-600 shrink-0 inline-flex items-center gap-0.5 {{ $staskDone ? 'opacity-50' : '' }}">
                                                                                        <x-lucide-alert-triangle class="w-2.5 h-2.5 text-red-600 shrink-0" />
                                                                                        <span>SLA</span>
                                                                                    </span>
                                                                                    <div class="absolute bottom-full right-0 mb-1 hidden group-hover/tip:flex items-center px-1.5 py-0.5 text-[9.5px] font-medium text-white bg-zinc-900 rounded shadow-md whitespace-nowrap z-50 pointer-events-none">
                                                                                        SLA: {{ $stask->order->current_due_date->format('d M, Y') }}
                                                                                    </div>
                                                                                </div>
                                                                            @endif
                                                                        @endif
                                                                    </div>
                                                                @else
                                                                    <!-- Standalone subtask (without order) -->
                                                                    <div class="flex items-center gap-1 min-w-0">
                                                                        <span class="font-bold text-[10.5px] truncate leading-tight {{ $staskDone ? 'line-through text-zinc-400' : ($stask->isSystemTask() ? 'text-violet-700' : 'text-zinc-900') }}">
                                                                            {{ $stask->title }}
                                                                        </span>
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        </div>

                                                        <button 
                                                            @click.stop 
                                                            wire:click="deleteSubtask({{ $stask->id }})" 
                                                            type="button"
                                                            class="p-0.5 mt-0.5 text-zinc-400 hover:text-red-600 transition shrink-0 opacity-0 group-hover:opacity-100" 
                                                            title="Eliminar subtarea">
                                                            <x-lucide-trash-2 class="w-3.5 h-3.5" />
                                                        </button>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif

                                        <!-- Placeholder when empty & not active -->
                                        @if($daySubtasks->isEmpty())
                                            <div x-show="!inlineActive" @click="openInline()" class="py-1 px-1 text-left cursor-pointer hover:bg-stone-100/60 rounded transition flex items-center gap-2">
                                                <div class="w-3.5 h-3.5 rounded-full border border-dashed border-stone-300 shrink-0"></div>
                                                <p class="text-[9.5px] text-zinc-400 select-none">+ {{ __('Escribir subtarea') }}</p>
                                            </div>
                                        @endif

                                        <!-- Quick Add Link when subtasks exist & not active -->
                                        @if($daySubtasks->isNotEmpty())
                                            <div x-show="!inlineActive" class="pt-0.5 px-1">
                                                <button type="button" @click="openInline()" class="text-[9.5px] text-zinc-400 hover:text-amber-800 font-medium transition cursor-pointer select-none">
                                                    + {{ __('Escribir subtarea...') }}
                                                </button>
                                            </div>
                                        @endif

                                        <!-- Super Simple Single-Line Inline Creator Row -->
                                        <div x-show="inlineActive" class="py-1 px-1 flex items-center justify-between gap-1.5 min-w-0 bg-stone-50/90 rounded border border-dashed border-stone-300 transition my-1">
                                            <div class="flex items-center gap-2 min-w-0 flex-1">
                                                <div class="w-3.5 h-3.5 rounded-full border border-stone-300 bg-white shrink-0 flex items-center justify-center">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                                                </div>

                                                <div class="flex items-center gap-1.5 min-w-0 flex-1 text-[11px] leading-tight relative">
                                                    
                                                    <!-- Phase 1: Order Search (if no order selected yet) -->
                                                    <div x-show="!selectedOrderId" class="relative w-full" @click.outside="dropdownOpen = false">
                                                        <input 
                                                            x-ref="orderSearchInput"
                                                            type="text"
                                                            x-model="orderSearch"
                                                            @focus="dropdownOpen = true; orderHighlightedIndex = -1;"
                                                            @input="dropdownOpen = true; orderHighlightedIndex = -1;"
                                                            @keydown.arrow-down.prevent="if(!dropdownOpen) dropdownOpen = true; else navigateOrder(1);"
                                                            @keydown.arrow-up.prevent="if(!dropdownOpen) dropdownOpen = true; else navigateOrder(-1);"
                                                            @keydown.enter.prevent="handleOrderEnter()"
                                                            @keydown.escape.stop="if(dropdownOpen) { dropdownOpen = false; orderHighlightedIndex = -1; } else { cancelInline(); }"
                                                            placeholder="{{ __('+ Buscar empresa o escribir nota...') }}"
                                                            class="w-full bg-transparent border-none p-0 focus:ring-0 focus:outline-none text-[11px] font-normal text-zinc-800 placeholder-stone-400" />

                                                        <!-- Floating Dropdown for Orders -->
                                                        <div 
                                                            x-ref="orderDropdownPanel"
                                                            x-show="dropdownOpen && getFilteredOrders().length > 0" 
                                                            class="absolute left-0 top-full mt-1 z-50 bg-white border border-stone-200 rounded-md shadow-md max-h-44 overflow-y-auto divide-y divide-stone-100 text-[11px] w-72">
                                                            <template x-for="(ord, idx) in getFilteredOrders()" :key="ord.id">
                                                                <button 
                                                                    type="button" 
                                                                    @click="selectOrder(ord)" 
                                                                    @mouseenter="orderHighlightedIndex = idx"
                                                                    :class="{ 'bg-amber-50 text-amber-950 font-semibold ring-1 ring-amber-200': orderHighlightedIndex === idx, 'hover:bg-stone-100': orderHighlightedIndex !== idx }"
                                                                    class="order-item-btn w-full text-left px-2.5 py-1.5 transition flex items-center justify-between gap-2 cursor-pointer">
                                                                    <div class="min-w-0 flex-1">
                                                                        <div class="flex items-center gap-1.5 min-w-0">
                                                                            <span class="font-bold text-zinc-900 truncate" x-text="ord.company"></span>
                                                                            <template x-if="ord.location">
                                                                                <span class="text-[9.5px] font-semibold text-emerald-700 bg-emerald-50 px-1 py-0.2 rounded border border-emerald-200 shrink-0 truncate max-w-[120px]" x-text="ord.location"></span>
                                                                            </template>
                                                                        </div>
                                                                        <span class="text-[10px] text-zinc-500 block truncate" x-text="ord.task"></span>
                                                                    </div>
                                                                </button>
                                                            </template>
                                                        </div>
                                                    </div>

                                                    <!-- Phase 2: Selected Order + Subtask Name Input on the SAME line -->
                                                     <div x-show="selectedOrderId" class="flex items-center gap-1 min-w-0 flex-1 relative" @click.outside="presetDropdownOpen = false; titleFocused = false;">
                                                         <span 
                                                             :class="titleFocused ? 'max-w-[40px] opacity-75' : 'max-w-[70px] opacity-100'"
                                                             class="font-bold text-zinc-900 uppercase tracking-tight shrink-0 truncate transition-all duration-200" 
                                                             :title="selectedOrderCompany"
                                                             x-text="selectedOrderCompany"></span>
                                                         <span class="text-zinc-300 font-bold shrink-0 transition-opacity duration-200" :class="titleFocused ? 'hidden' : 'inline'" x-show="selectedOrderTask">•</span>
                                                         <span 
                                                             :class="titleFocused ? 'hidden' : 'inline max-w-[65px]'"
                                                             class="text-zinc-500 font-medium shrink-0 truncate transition-all duration-200" 
                                                             :title="selectedOrderTask"
                                                             x-show="selectedOrderTask" 
                                                             x-text="selectedOrderTask"></span>
                                                         <span class="text-zinc-300 font-bold shrink-0">•</span>

                                                         <!-- Subtask Title Input with Delicate Presets Dropdown -->
                                                         <div class="relative flex-1 min-w-0 transition-all duration-200">
                                                             <input 
                                                                 x-ref="titleInput"
                                                                 type="text"
                                                                 x-model="subtaskTitle"
                                                                 @focus="presetDropdownOpen = true; titleFocused = true;"
                                                                 @input="presetDropdownOpen = true; presetHighlightedIndex = -1;"
                                                                 @keydown.arrow-down.prevent="if(!presetDropdownOpen) presetDropdownOpen = true; else navigatePreset(1);"
                                                                 @keydown.arrow-up.prevent="if(!presetDropdownOpen) presetDropdownOpen = true; else navigatePreset(-1);"
                                                                 @keydown.enter.prevent="if (presetDropdownOpen && presetHighlightedIndex >= 0 && getFilteredPresets()[presetHighlightedIndex]) { selectPreset(getFilteredPresets()[presetHighlightedIndex]); } else { submitSubtask(); }"
                                                                 @keydown.backspace="if(!subtaskTitle) { selectedOrderId = ''; selectedOrderCompany = ''; selectedOrderTask = ''; presetDropdownOpen = false; titleFocused = false; }"
                                                                 @keydown.escape.stop="if(presetDropdownOpen) { presetDropdownOpen = false; presetHighlightedIndex = -1; } else { cancelInline(); }"
                                                                 placeholder="{{ __('Nombre subtarea (Enter para guardar)...') }}"
                                                                 class="w-full bg-amber-50/90 border border-amber-200/80 px-1.5 py-0.5 rounded text-[10.5px] font-semibold text-amber-950 focus:ring-1 focus:ring-amber-400 focus:border-amber-300 focus:outline-none placeholder-amber-700/50 transition-all duration-200" />

                                                            <!-- Delicate Dropdown Menu for Subtask Presets -->
                                                            <div 
                                                                x-ref="presetDropdownPanel"
                                                                x-show="presetDropdownOpen && getFilteredPresets().length > 0"
                                                                x-transition:enter="transition ease-out duration-100"
                                                                x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                                                                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                                                                x-transition:leave="transition ease-in duration-75"
                                                                x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                                                                x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                                                                class="absolute left-0 top-full mt-1 z-50 bg-white/95 backdrop-blur-xs border border-stone-200 rounded-lg shadow-xl max-h-48 overflow-y-auto p-1 text-[11px] min-w-[210px] w-max max-w-xs space-y-0.5 divide-y divide-stone-100"
                                                                style="display: none;">
                                                                <div class="px-2 py-0.5 text-[9px] font-bold text-stone-400 uppercase tracking-wider flex items-center justify-between select-none pb-0.5">
                                                                    <span>{{ __('Plantillas predeterminadas') }}</span>
                                                                    <span class="text-[8.5px] text-stone-300 font-mono">↑↓ Enter</span>
                                                                </div>
                                                                <div class="pt-0.5 space-y-0.5">
                                                                    <template x-for="(preset, idx) in getFilteredPresets()" :key="preset.id || idx">
                                                                        <button 
                                                                            type="button" 
                                                                            @click="selectPreset(preset)" 
                                                                            @mouseenter="presetHighlightedIndex = idx"
                                                                            :class="{ 'bg-amber-50 text-amber-950 font-semibold ring-1 ring-amber-200': presetHighlightedIndex === idx, 'text-zinc-700 hover:bg-stone-50': presetHighlightedIndex !== idx }"
                                                                            class="preset-item-btn w-full text-left px-2 py-1 rounded-md transition flex items-center justify-between gap-2 cursor-pointer group">
                                                                            <div class="flex items-center gap-1.5 min-w-0">
                                                                                <span 
                                                                                    :class="preset.badge_style || 'bg-stone-100 text-stone-700 border-stone-200'"
                                                                                    class="px-1.5 py-0.5 rounded text-[10px] font-medium border shrink-0 inline-flex items-center gap-1 shadow-2xs">
                                                                                    <span x-text="preset.title"></span>
                                                                                </span>
                                                                            </div>
                                                                            <div class="flex items-center gap-1 shrink-0">
                                                                                <span 
                                                                                    :class="preset.is_work_task ? 'bg-blue-50 text-blue-700 border-blue-200' : 'bg-amber-50 text-amber-800 border-amber-200'"
                                                                                    class="px-1 py-0.2 rounded text-[8.5px] font-bold border">
                                                                                    <span x-text="preset.is_work_task ? 'Trabajo' : 'Gestión'"></span>
                                                                                </span>
                                                                                <span class="text-[9px] text-amber-700 font-medium opacity-0 group-hover:opacity-100 transition">
                                                                                    ↵
                                                                                </span>
                                                                            </div>
                                                                        </button>
                                                                    </template>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Working / Managing Compact Toggle Pill -->
                                                        <button 
                                                            type="button" 
                                                            @click="isWorkTask = !isWorkTask"
                                                            :class="isWorkTask ? 'bg-blue-50 hover:bg-blue-100 text-blue-700 border-blue-200/90' : 'bg-amber-50 hover:bg-amber-100 text-amber-800 border-amber-200/90'"
                                                            class="px-1.5 py-0.5 rounded text-[9.5px] font-bold border transition cursor-pointer flex items-center gap-1 shrink-0 shadow-2xs select-none"
                                                            :title="isWorkTask ? '{{ __('Trabajo activo de diseño (Mueve orden a Working Today si es para hoy)') }}' : '{{ __('Gestión / Coordinación (No mueve la orden principal a Working Today)') }}'">
                                                            <span x-show="isWorkTask" class="flex items-center gap-1">
                                                                <x-lucide-briefcase class="w-3 h-3 text-blue-600" />
                                                                <span>{{ __('Trabajo') }}</span>
                                                            </span>
                                                            <span x-show="!isWorkTask" class="flex items-center gap-1">
                                                                <x-lucide-clipboard-check class="w-3 h-3 text-amber-600" />
                                                                <span>{{ __('Gestión') }}</span>
                                                            </span>
                                                        </button>

                                                    </div>

                                                </div>
                                            </div>

                                            <div class="flex items-center gap-1 shrink-0">
                                                <button type="button" @click="cancelInline()" class="p-0.5 text-stone-400 hover:text-stone-700" title="{{ __('Cancelar') }}">
                                                    <x-lucide-x class="w-3.5 h-3.5" />
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Modal Nueva Subtarea -->
    <div wire:ignore.self>
        <template x-if="subtaskModalOpen">
            <div 
                class="fixed inset-0 z-[100] overflow-y-auto bg-stone-900/40 backdrop-blur-xs flex items-center justify-center p-4"
                @keydown.window.escape="subtaskModalOpen = false"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0">
            
                <div 
                    @click.outside="subtaskModalOpen = false"
                    class="bg-white border border-[#e9e9e7] rounded-xl shadow-2xl max-w-lg w-full flex flex-col transition duration-200 overflow-visible relative"
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100">
                    
                    <div class="px-5 py-4 border-b border-[#e9e9e7] bg-[#fbfbfa] flex items-center justify-between">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="p-2 rounded-lg bg-violet-600 text-white shrink-0 shadow-2xs">
                                <x-lucide-check-square class="w-4 h-4" />
                            </div>
                            <div class="min-w-0">
                                <h3 class="font-bold text-sm text-zinc-900 leading-snug">{{ __('Asignar Subtarea a Día') }}</h3>
                                <p class="text-xs text-zinc-500 truncate">
                                    {{ __('Programar para el día y diseñador seleccionado') }}
                                </p>
                            </div>
                        </div>
                        <button 
                            type="button" 
                            @click="subtaskModalOpen = false"
                            class="p-1 rounded-lg text-zinc-400 hover:text-zinc-700 hover:bg-stone-100 transition cursor-pointer">
                            <x-lucide-x class="w-4 h-4" />
                        </button>
                    </div>

                    <div class="p-5 space-y-4 text-xs">
                        <div class="space-y-1 relative" @click.outside="orderDropdownOpen = false">
                            <label class="font-semibold text-zinc-700 block flex items-center justify-between">
                                <span>{{ __('Orden / Trabajo Principal') }} <span class="text-zinc-400 font-normal">({{ __('Opcional - dejar en blanco para Nota') }})</span></span>
                                <template x-if="subtaskOrderId">
                                    <span class="text-[10px] text-emerald-600 font-bold bg-emerald-50 px-1.5 py-0.2 rounded border border-emerald-200 flex items-center gap-1">
                                        <x-lucide-check class="w-3 h-3" />
                                        {{ __('Orden seleccionada') }}
                                    </span>
                                </template>
                            </label>

                            <div class="relative">
                                <x-lucide-search class="w-3.5 h-3.5 text-zinc-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                                
                                <input 
                                    type="text" 
                                    x-ref="orderSearchInput"
                                    x-model="orderSearchQuery"
                                    @focus="orderDropdownOpen = true; modalOrderHighlightedIndex = -1;"
                                    @input="orderDropdownOpen = true; modalOrderHighlightedIndex = -1; if (!orderSearchQuery) subtaskOrderId = '';"
                                    @keydown.arrow-down.prevent="if(!orderDropdownOpen) orderDropdownOpen = true; else navigateModalOrder(1);"
                                    @keydown.arrow-up.prevent="if(!orderDropdownOpen) orderDropdownOpen = true; else navigateModalOrder(-1);"
                                    @keydown.enter.prevent="if (orderDropdownOpen && modalOrderHighlightedIndex >= 0) { selectHighlightedModalOrder(); }"
                                    @keydown.escape.stop="if(orderDropdownOpen) { orderDropdownOpen = false; modalOrderHighlightedIndex = -1; }"
                                    placeholder="{{ __('Buscar por empresa o trabajo...') }}" 
                                    class="w-full bg-[#fbfbfa] focus:bg-white border border-[#e9e9e7] focus:border-stone-400 rounded-lg pl-9 pr-14 py-2 text-xs text-zinc-800 focus:outline-none transition shadow-2xs font-medium" />

                                <div class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center gap-1">
                                    <template x-if="orderSearchQuery">
                                        <button 
                                            type="button" 
                                            @click="orderSearchQuery = ''; subtaskOrderId = ''; orderDropdownOpen = true; $refs.orderSearchInput.focus()" 
                                            class="p-1 text-zinc-400 hover:text-zinc-700 rounded-md transition cursor-pointer"
                                            title="{{ __('Limpiar búsqueda') }}">
                                            <x-lucide-x class="w-3.5 h-3.5" />
                                        </button>
                                    </template>
                                    <button 
                                        type="button" 
                                        @click="orderDropdownOpen = !orderDropdownOpen" 
                                        class="p-1 text-zinc-400 hover:text-zinc-700 rounded-md transition cursor-pointer"
                                        title="{{ __('Mostrar opciones') }}">
                                        <x-lucide-chevron-down class="w-3.5 h-3.5 transition-transform duration-150" x-bind:class="orderDropdownOpen ? 'rotate-180' : ''" />
                                    </button>
                                </div>
                            </div>

                            <div 
                                x-ref="modalOrderDropdownPanel"
                                x-show="orderDropdownOpen"
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                class="absolute left-0 right-0 top-full mt-1 z-50 bg-white border border-[#e9e9e7] rounded-xl shadow-2xl max-h-56 overflow-y-auto divide-y divide-stone-100 text-xs">
                                
                                <template x-for="(item, idx) in getFilteredOrders()" :key="item.id">
                                    <button 
                                        type="button"
                                        @click="subtaskOrderId = item.id; orderSearchQuery = item.text; if (item.designer_id) subtaskDesignerId = String(item.designer_id); orderDropdownOpen = false; modalOrderHighlightedIndex = -1;"
                                        @mouseenter="modalOrderHighlightedIndex = idx"
                                        :class="{ 'bg-violet-50 text-violet-900 font-semibold ring-1 ring-violet-200': modalOrderHighlightedIndex === idx || (modalOrderHighlightedIndex === -1 && subtaskOrderId === item.id), 'hover:bg-stone-100': modalOrderHighlightedIndex !== idx }"
                                        class="modal-order-item-btn w-full text-left p-2.5 focus:outline-none cursor-pointer flex items-center justify-between gap-2 transition">
                                        <div class="min-w-0 flex-1">
                                             <div class="flex items-center gap-1.5 min-w-0">
                                                 <span class="font-bold text-zinc-900 truncate text-xs" x-text="item.company"></span>
                                                 <template x-if="item.location">
                                                     <span class="text-[10px] font-semibold text-emerald-700 bg-emerald-50 px-1.5 py-0.2 rounded border border-emerald-200 shrink-0 truncate max-w-[150px]" x-text="item.location"></span>
                                                 </template>
                                             </div>
                                             <span class="text-[11px] text-zinc-500 block truncate" x-text="item.task"></span>
                                        </div>
                                        <template x-if="subtaskOrderId === item.id">
                                            <x-lucide-check class="w-3.5 h-3.5 text-violet-600 shrink-0 stroke-[2.5]" />
                                        </template>
                                    </button>
                                </template>

                                <template x-if="getFilteredOrders().length === 0">
                                    <div class="p-3 text-center text-zinc-400 italic text-[11px]">
                                        {{ __('No se encontraron órdenes coincidentes.') }}
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="font-semibold text-zinc-700 block flex items-center justify-between">
                                <span>{{ __('Nombre de la Subtarea') }} <span class="text-red-500">*</span></span>
                                <div class="flex items-center gap-1 bg-stone-100 p-0.5 rounded-lg text-[10px] font-semibold border border-stone-200">
                                    <button 
                                        type="button" 
                                        @click="subtaskIsWorkTask = true" 
                                        :class="subtaskIsWorkTask ? 'bg-blue-600 text-white shadow-2xs font-bold' : 'text-zinc-600 hover:text-zinc-900'" 
                                        class="px-2 py-0.5 rounded-md transition cursor-pointer flex items-center gap-1">
                                        <x-lucide-briefcase class="w-3 h-3" />
                                        <span>{{ __('Trabajo') }}</span>
                                    </button>
                                    <button 
                                        type="button" 
                                        @click="subtaskIsWorkTask = false" 
                                        :class="!subtaskIsWorkTask ? 'bg-amber-600 text-white shadow-2xs font-bold' : 'text-zinc-600 hover:text-zinc-900'" 
                                        class="px-2 py-0.5 rounded-md transition cursor-pointer flex items-center gap-1">
                                        <x-lucide-clipboard-check class="w-3 h-3" />
                                        <span>{{ __('Gestión') }}</span>
                                    </button>
                                </div>
                            </label>
                            <input 
                                type="text" 
                                x-ref="subtaskTitleInput"
                                x-model="subtaskTitle"
                                @keyup.enter="submitSubtaskModal()"
                                placeholder="{{ __('Ej: Revisión, Ajustes de Cliente, Impresión, Montaje...') }}"
                                class="w-full bg-[#fbfbfa] focus:bg-white border border-[#e9e9e7] focus:border-stone-400 rounded-lg px-3 py-2 text-xs text-zinc-800 focus:outline-none transition shadow-2xs" />
                        </div>

                        @if($subtaskPresets->count() > 0)
                            <div class="space-y-1.5 pt-1">
                                <span class="text-[11px] font-medium text-zinc-500 block">{{ __('O elige un nombre predefinido:') }}</span>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($subtaskPresets as $preset)
                                        @php
                                            $safePlannerIcon = (preg_match('/^[a-z0-9\-]+$/i', $preset->emoji ?? '')) ? $preset->emoji : 'tag';
                                        @endphp
                                        <button 
                                            type="button"
                                            @click="subtaskTitle = '{{ addslashes($preset->title) }}'; subtaskIsWorkTask = {{ $preset->is_work_task ? 'true' : 'false' }}"
                                            class="px-2 py-1 rounded-md font-medium text-[11px] border transition shadow-2xs inline-flex items-center gap-1 cursor-pointer hover:opacity-80 {{ $preset->badgeStyle() }}">
                                            <x-dynamic-component :component="'lucide-' . $safePlannerIcon" class="w-3 h-3" />
                                            <span>{{ __($preset->title) }}</span>
                                            <span class="text-[9px] opacity-75">({{ $preset->is_work_task ? __('Trabajo') : __('Gestión') }})</span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-[#f0f0ee]">
                            <div class="space-y-1 relative" x-data="{ calOpen: false }" @click.outside="calOpen = false">
                                <label class="font-semibold text-zinc-700 block">{{ __('Día Asignado') }}</label>
                                
                                <button 
                                    type="button" 
                                    @click="calOpen = !calOpen"
                                    class="w-full bg-[#fbfbfa] hover:bg-white border border-[#e9e9e7] hover:border-stone-400 rounded-lg px-2.5 py-1.5 text-xs text-zinc-800 focus:outline-none transition shadow-2xs font-medium flex items-center justify-between cursor-pointer">
                                    <span class="flex items-center gap-1.5 truncate">
                                        <x-lucide-calendar class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                                        <span x-text="subtaskDate ? subtaskDate : '{{ __('Seleccionar fecha...') }}'"></span>
                                    </span>
                                    <x-lucide-chevron-down class="w-3.5 h-3.5 text-zinc-400 shrink-0 transition-transform duration-150" x-bind:class="calOpen ? 'rotate-180' : ''" />
                                </button>

                                <div 
                                    x-show="calOpen"
                                    x-transition:enter="transition ease-out duration-100"
                                    x-transition:enter-start="opacity-0 scale-95"
                                    x-transition:enter-end="opacity-100 scale-100"
                                    class="absolute left-0 bottom-full mb-1.5 w-72 sm:w-80 z-50 bg-white border border-[#e9e9e7] rounded-xl shadow-2xl p-3 text-xs space-y-2.5">
                                    
                                    <div class="flex items-center justify-between border-b border-[#f0f0ee] pb-1.5">
                                        <button type="button" wire:click="previousMonth" class="p-1 rounded hover:bg-stone-100 text-zinc-600 transition">
                                            <x-lucide-chevron-left class="w-3.5 h-3.5" />
                                        </button>
                                        <span class="font-bold text-zinc-900 text-xs capitalize">
                                            {{ Carbon\Carbon::parse($viewMonth . '-01')->translatedFormat('F Y') }}
                                        </span>
                                        <button type="button" wire:click="nextMonth" class="p-1 rounded hover:bg-stone-100 text-zinc-600 transition">
                                            <x-lucide-chevron-right class="w-3.5 h-3.5" />
                                        </button>
                                    </div>

                                    <div class="flex items-center justify-between text-[10px] text-zinc-500 px-0.5">
                                        <span class="flex items-center gap-1 font-medium">
                                            <span class="w-2.5 h-2.5 rounded bg-emerald-100 border border-emerald-300 inline-block"></span>
                                            {{ __('Semana Actual') }}
                                        </span>
                                        <span class="flex items-center gap-1 font-medium">
                                            <span class="w-2.5 h-2.5 rounded bg-stone-900 inline-block"></span>
                                            {{ __('Seleccionado') }}
                                        </span>
                                    </div>

                                    <div class="grid grid-cols-7 text-center font-bold text-[10px] text-zinc-400 uppercase tracking-wider">
                                        <span>{{ __('Lun') }}</span><span>{{ __('Mar') }}</span><span>{{ __('Mié') }}</span><span>{{ __('Jue') }}</span><span>{{ __('Vie') }}</span><span class="text-zinc-300">{{ __('Sáb') }}</span><span class="text-zinc-300">{{ __('Dom') }}</span>
                                    </div>

                                    <div class="grid grid-cols-7 gap-1 text-center font-medium">
                                        @foreach($this->miniCalendarDays as $calDay)
                                            @php
                                                $isCurrWeek = $calDay['is_current_week'] ?? false;
                                            @endphp
                                            <button 
                                                type="button"
                                                @click="subtaskDate = '{{ $calDay['date_string'] }}'; calOpen = false"
                                                :class="{ 'bg-stone-900 text-white font-bold ring-2 ring-emerald-500 shadow-xs': subtaskDate === '{{ $calDay['date_string'] }}' }"
                                                class="p-1.5 rounded-md text-xs transition relative group cursor-pointer
                                                    {{ $isCurrWeek ? 'bg-emerald-100/90 text-emerald-950 font-semibold border border-emerald-300/80 hover:bg-emerald-200' : ($calDay['is_current_month'] ? 'text-zinc-800 hover:bg-stone-100' : 'text-zinc-300 hover:bg-stone-50') }}
                                                    {{ $calDay['is_today'] ? 'ring-1 ring-emerald-500 font-bold' : '' }}">
                                                <span>{{ $calDay['day_number'] }}</span>
                                            </button>
                                        @endforeach
                                    </div>

                                    <div class="pt-1.5 border-t border-[#f0f0ee] flex items-center justify-between gap-1 text-[11px]">
                                        <span class="text-zinc-500 shrink-0">{{ __('O elegir fecha:') }}</span>
                                        <input 
                                            type="date" 
                                            x-model="subtaskDate"
                                            @change="calOpen = false"
                                            class="bg-[#fbfbfa] border border-[#e9e9e7] rounded px-2 py-0.5 text-[11px] font-mono text-zinc-800">
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-1">
                                <label class="font-semibold text-zinc-700 block">{{ __('Diseñador Asignado') }}</label>
                                <select 
                                    x-model="subtaskDesignerId" 
                                    class="w-full bg-[#fbfbfa] focus:bg-white border border-[#e9e9e7] focus:border-stone-400 rounded-lg px-2.5 py-1.5 text-xs text-zinc-800 focus:outline-none transition shadow-2xs font-medium">
                                    <option value="">{{ __('Por defecto (de la Orden)') }}</option>
                                    @foreach($designers as $des)
                                        <option value="{{ $des->id }}">{{ $des->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                    </div>

                    <div class="px-5 py-3 border-t border-[#e9e9e7] bg-[#fbfbfa] flex items-center justify-end gap-2">
                        <button 
                            type="button" 
                            @click="subtaskModalOpen = false"
                            class="px-3 py-1.5 rounded-lg border border-[#d0d0ce] bg-white hover:bg-stone-50 text-zinc-700 font-semibold text-xs transition cursor-pointer shadow-2xs">
                            {{ __('Cancelar') }}
                        </button>
                        <button 
                            type="button" 
                            @click="submitSubtaskModal()"
                            class="px-4 py-1.5 rounded-lg bg-violet-600 hover:bg-violet-700 text-white font-semibold text-xs transition cursor-pointer shadow-2xs flex items-center gap-1.5">
                            <x-lucide-check class="w-3.5 h-3.5" />
                            <span x-text="subtaskOrderId ? '{{ __('Crear Subtarea') }}' : '{{ __('Crear Nota') }}'"></span>
                        </button>
                    </div>

                </div>
            </div>
        </template>

        <!-- Modal Vincular Nota a Orden -->
        <template x-if="linkNoteModalOpen">
            <div 
                class="fixed inset-0 z-[105] overflow-y-auto bg-stone-900/50 backdrop-blur-xs flex items-center justify-center p-4"
                @keydown.window.escape="linkNoteModalOpen = false"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0">
            
                <div 
                    @click.outside="linkNoteModalOpen = false"
                    class="bg-white border border-[#e9e9e7] rounded-xl shadow-2xl max-w-md w-full flex flex-col transition duration-200 overflow-visible relative"
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100">
                    
                    <div class="px-5 py-4 border-b border-[#e9e9e7] bg-[#fbfbfa] flex items-center justify-between">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="p-2 rounded-lg bg-amber-500 text-white shrink-0 shadow-2xs">
                                <x-lucide-link class="w-4 h-4" />
                            </div>
                            <div class="min-w-0">
                                <h3 class="font-bold text-sm text-zinc-900 leading-snug">{{ __('Vincular Nota a Orden') }}</h3>
                                <p class="text-xs text-zinc-500 truncate">
                                    {{ __('Asociar nota a un trabajo del workspace') }}
                                </p>
                            </div>
                        </div>
                        <button 
                            type="button" 
                            @click="linkNoteModalOpen = false"
                            class="p-1 rounded-lg text-zinc-400 hover:text-zinc-700 hover:bg-stone-100 transition cursor-pointer">
                            <x-lucide-x class="w-4 h-4" />
                        </button>
                    </div>

                    <div class="p-5 space-y-4 text-xs">
                        <div class="bg-amber-50 border border-amber-200/80 rounded-lg p-3 text-amber-900 space-y-1">
                            <div class="font-bold flex items-center gap-1.5 text-xs">
                                <x-lucide-info class="w-3.5 h-3.5 text-amber-600 shrink-0" />
                                <span>{{ __('Vinculación Obligatoria') }}</span>
                            </div>
                            <p class="text-[11px] leading-relaxed text-amber-800">
                                {{ __('Para marcar una nota como completada o asociarla al timeline de un cliente, debes vincularla a una orden. La información redundante del título (empresa/ubicación) se limpiará automáticamente.') }}
                            </p>
                            <div class="pt-1 font-semibold text-[11px] text-amber-950 flex items-center gap-1">
                                <span>{{ __('Nota:') }}</span>
                                <span class="italic font-bold text-zinc-900" x-text="linkNoteTitle"></span>
                            </div>
                        </div>

                        <div class="space-y-1 relative" @click.outside="linkNoteDropdownOpen = false">
                            <label class="font-semibold text-zinc-700 block flex items-center justify-between">
                                <span>{{ __('Seleccionar Orden del Workspace') }} <span class="text-red-500">*</span></span>
                            </label>

                            <div class="relative">
                                <x-lucide-search class="w-3.5 h-3.5 text-zinc-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                                
                                <input 
                                    type="text" 
                                    x-ref="linkNoteSearchInput"
                                    x-model="linkNoteSearchQuery"
                                    @focus="linkNoteDropdownOpen = true; linkNoteHighlightedIndex = -1;"
                                    @input="linkNoteDropdownOpen = true; linkNoteHighlightedIndex = -1; if (!linkNoteSearchQuery) linkNoteOrderId = '';"
                                    @keydown.arrow-down.prevent="if(!linkNoteDropdownOpen) linkNoteDropdownOpen = true; else navigateLinkNoteOrder(1);"
                                    @keydown.arrow-up.prevent="if(!linkNoteDropdownOpen) linkNoteDropdownOpen = true; else navigateLinkNoteOrder(-1);"
                                    @keydown.enter.prevent="if (linkNoteDropdownOpen && linkNoteHighlightedIndex >= 0) { selectHighlightedLinkNoteOrder(); } else if (linkNoteOrderId) { submitLinkNoteModal(); }"
                                    @keydown.escape.stop="if(linkNoteDropdownOpen) { linkNoteDropdownOpen = false; linkNoteHighlightedIndex = -1; } else { linkNoteModalOpen = false; }"
                                    placeholder="{{ __('Buscar por empresa, ubicación o trabajo...') }}" 
                                    class="w-full bg-[#fbfbfa] focus:bg-white border border-[#e9e9e7] focus:border-stone-400 rounded-lg pl-9 pr-8 py-2 text-xs text-zinc-800 focus:outline-none transition shadow-2xs font-medium" />

                                <div class="absolute right-2 top-1/2 -translate-y-1/2">
                                    <button 
                                        type="button" 
                                        @click="linkNoteDropdownOpen = !linkNoteDropdownOpen" 
                                        class="p-1 text-zinc-400 hover:text-zinc-700 rounded-md transition cursor-pointer">
                                        <x-lucide-chevron-down class="w-3.5 h-3.5 transition-transform duration-150" x-bind:class="linkNoteDropdownOpen ? 'rotate-180' : ''" />
                                    </button>
                                </div>
                            </div>

                            <div 
                                x-ref="linkNoteDropdownPanel"
                                x-show="linkNoteDropdownOpen"
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                class="absolute left-0 right-0 top-full mt-1 z-50 bg-white border border-[#e9e9e7] rounded-xl shadow-2xl max-h-56 overflow-y-auto divide-y divide-stone-100 text-xs">
                                
                                <template x-for="(item, idx) in getFilteredLinkNoteOrders()" :key="item.id">
                                    <button 
                                        type="button"
                                        @click="linkNoteOrderId = item.id; linkNoteSearchQuery = item.text; linkNoteDropdownOpen = false; submitLinkNoteModal();"
                                        @mouseenter="linkNoteHighlightedIndex = idx"
                                        :class="{ 'bg-amber-50 text-amber-900 font-semibold ring-1 ring-amber-200': linkNoteHighlightedIndex === idx || (linkNoteHighlightedIndex === -1 && linkNoteOrderId === item.id), 'hover:bg-stone-100': linkNoteHighlightedIndex !== idx }"
                                        class="link-note-order-item-btn w-full text-left p-2.5 focus:outline-none cursor-pointer flex items-center justify-between gap-2 transition">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-1.5 min-w-0">
                                                <span class="font-bold text-zinc-900 truncate text-xs" x-text="item.company"></span>
                                                <template x-if="item.location">
                                                    <span class="text-[10px] font-semibold text-emerald-700 bg-emerald-50 px-1.5 py-0.2 rounded border border-emerald-200 shrink-0 truncate max-w-[150px]" x-text="item.location"></span>
                                                </template>
                                            </div>
                                            <span class="text-[11px] text-zinc-500 block truncate" x-text="item.task"></span>
                                        </div>
                                        <template x-if="linkNoteOrderId === item.id">
                                            <x-lucide-check class="w-3.5 h-3.5 text-amber-600 shrink-0 stroke-[2.5]" />
                                        </template>
                                    </button>
                                </template>

                                <template x-if="getFilteredLinkNoteOrders().length === 0">
                                    <div class="p-3 text-center text-zinc-400 italic text-[11px]">
                                        {{ __('No se encontraron órdenes coincidentes.') }}
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div class="px-5 py-3 border-t border-[#e9e9e7] bg-[#fbfbfa] flex items-center justify-end gap-2">
                        <button 
                            type="button" 
                            @click="linkNoteModalOpen = false"
                            class="px-3 py-1.5 rounded-lg border border-[#d0d0ce] bg-white hover:bg-stone-50 text-zinc-700 font-semibold text-xs transition cursor-pointer shadow-2xs">
                            {{ __('Cancelar') }}
                        </button>
                        <button 
                            type="button" 
                            @click="submitLinkNoteModal()"
                            class="px-4 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white font-semibold text-xs transition cursor-pointer shadow-2xs flex items-center gap-1.5">
                            <x-lucide-link class="w-3.5 h-3.5" />
                            <span>{{ __('Vincular a Orden') }}</span>
                        </button>
                    </div>

                </div>
            </div>
        </template>
    </div>

    <!-- Modal Alerta SLA -->
    @if($showSlaWarningModal)
        <div 
            class="fixed inset-0 z-[100] overflow-y-auto bg-stone-900/50 backdrop-blur-xs flex items-center justify-center p-4"
            @keydown.window.escape.prevent="$wire.closeSlaWarningModal()"
            @keydown.window.enter.prevent="$wire.closeSlaWarningModal()"
            wire:keydown.escape="closeSlaWarningModal">
            
            <div 
                class="bg-white border border-rose-200 rounded-xl shadow-2xl max-w-lg w-full flex flex-col transition duration-200 overflow-hidden relative">
                
                <div class="px-5 py-4 border-b border-rose-100 bg-rose-50/90 flex items-center justify-between">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="p-2 rounded-lg bg-rose-600 text-white shrink-0 shadow-2xs">
                            <x-lucide-alert-triangle class="w-5 h-5 stroke-[2.5]" />
                        </div>
                        <div class="min-w-0">
                            <h3 class="font-bold text-sm text-rose-950 leading-snug">{{ __('Alertas de SLA / Exceso de Plazo') }}</h3>
                            <p class="text-xs text-rose-700 truncate">
                                {{ __('Tareas o subtareas programadas fuera del plazo límite oficial') }}
                            </p>
                        </div>
                    </div>
                    <button 
                        type="button" 
                        wire:click="closeSlaWarningModal"
                        class="p-1 rounded-lg text-rose-400 hover:text-rose-700 hover:bg-rose-100/60 transition cursor-pointer">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                </div>

                <div class="p-5 space-y-3.5 text-xs text-zinc-700 max-h-96 overflow-y-auto custom-vertical-scrollbar">
                    @if(!empty($slaWarningDetails))
                        <div class="p-3 bg-amber-50/90 border border-amber-200 rounded-lg space-y-1">
                            <span class="font-bold text-zinc-900 block text-xs">{{ $slaWarningDetails['company_name'] ?? '' }}</span>
                            <span class="text-[11px] text-zinc-600 block">{{ $slaWarningDetails['task_name'] ?? '' }}</span>
                        </div>

                        <p class="leading-relaxed">
                            {{ __('La subtarea fue programada para el') }} <strong class="text-zinc-900 font-bold">{{ $slaWarningDetails['scheduled_date'] ?? '' }}</strong>, {{ __('lo cual supera la fecha límite del SLA') }} (<strong class="text-rose-600 font-bold">{{ $slaWarningDetails['current_due_date'] ?? '' }}</strong>) {{ __('por') }} <strong class="text-rose-600 font-bold">{{ $slaWarningDetails['days_overdue'] ?? 0 }} {{ __('días') }}</strong>.
                        </p>
                    @elseif(isset($slaBreachedList) && $slaBreachedList->isNotEmpty())
                        <div class="space-y-2">
                            <p class="font-medium text-zinc-600">
                                {{ __('Las siguientes :count tareas en la agenda semanal superan su fecha límite de SLA:', ['count' => $slaBreachedList->count()]) }}
                            </p>
                            <div class="divide-y divide-stone-100 border border-stone-200 rounded-lg overflow-hidden">
                                @foreach($slaBreachedList as $item)
                                    <div class="p-3 bg-rose-50/40 hover:bg-rose-50/80 transition flex items-start justify-between gap-3">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-1.5 mb-0.5">
                                                <span class="px-1.5 py-0.2 rounded text-[9px] font-bold uppercase {{ $item['type'] === 'subtask' ? ((isset($item['is_work_task']) && !$item['is_work_task']) ? 'bg-violet-600 text-white border border-violet-700' : 'bg-violet-100 text-violet-800 border border-violet-200') : 'bg-stone-200 text-zinc-800 border border-stone-300' }}">
                                                    {{ $item['type'] === 'subtask' ? ((isset($item['is_work_task']) && !$item['is_work_task']) ? __('Sistema') : __('Subtarea')) : __('Orden') }}
                                                </span>
                                                <h4 class="font-bold text-xs text-zinc-900 truncate">{{ $item['company_name'] }}</h4>
                                            </div>
                                            <p class="text-[11px] text-zinc-600 truncate">{{ $item['task_name'] }}</p>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <span class="text-rose-600 font-bold text-xs block">
                                                +{{ $item['days_overdue'] }}d {{ __('Atraso') }}
                                            </span>
                                            <span class="text-[10px] text-zinc-400 block mt-0.5">
                                                SLA: {{ $item['current_due_date'] }}
                                            </span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="p-4 text-center text-zinc-400 italic">
                            {{ __('No hay alertas de SLA activas en esta semana.') }}
                        </div>
                    @endif

                    <div class="p-2.5 bg-stone-50 border border-stone-200 rounded-lg text-[11px] text-zinc-500 flex items-start gap-2">
                        <x-lucide-info class="w-3.5 h-3.5 text-zinc-400 shrink-0 mt-0.5" />
                        <span>{{ __('La fecha límite oficial del trabajo (SLA) se mantiene bloqueada e intacta para fines de control de atrasos.') }}</span>
                    </div>
                </div>

                <div class="px-5 py-3 border-t border-[#e9e9e7] bg-[#fbfbfa] flex items-center justify-end">
                    <button 
                        type="button" 
                        wire:click="closeSlaWarningModal"
                        class="px-4 py-1.5 rounded-lg bg-stone-900 hover:bg-stone-800 text-white font-semibold text-xs transition cursor-pointer shadow-2xs flex items-center gap-1.5">
                        <x-lucide-check class="w-3.5 h-3.5" />
                        <span>{{ __('Entendido') }}</span>
                    </button>
                </div>

            </div>
        </div>
    @endif

    <!-- Floating Bottom-Left Subtask Deletion Undo Toast -->
    <div 
        x-show="undoToastOpen" 
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-4"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-4"
        style="display: none;"
        class="fixed bottom-5 left-5 z-50 flex items-center gap-3 px-3.5 py-2 rounded-xl bg-white/95 text-stone-800 text-xs font-medium shadow-xl shadow-stone-900/10 border border-stone-200/90 backdrop-blur-md">
        <div class="flex items-center gap-2 min-w-0">
            <span class="w-2 h-2 rounded-full bg-rose-500 shrink-0"></span>
            <span class="text-stone-700 text-[11.5px] font-semibold truncate" x-text="undoToastMessage"></span>
        </div>
        <button 
            wire:click="undoDeleteSubtask" 
            @click="hideUndoToast()"
            type="button" 
            class="px-2.5 py-1 rounded-lg bg-stone-900 hover:bg-stone-800 text-white font-bold text-[10.5px] flex items-center gap-1.5 shrink-0 transition cursor-pointer shadow-2xs">
            <x-lucide-undo-2 class="w-3 h-3 text-stone-300" />
            <span>{{ __('Deshacer') }}</span>
            <kbd class="hidden sm:inline-block px-1.5 py-0.2 text-[8.5px] bg-stone-700 text-stone-200 rounded font-mono">⌘Z</kbd>
        </button>
        <button @click="hideUndoToast()" type="button" class="p-1 text-stone-400 hover:text-stone-700 hover:bg-stone-100 rounded-md transition">
            <x-lucide-x class="w-3.5 h-3.5" />
        </button>
    </div>

</div>
