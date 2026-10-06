<div class="flex-1 w-full min-h-0 flex flex-col space-y-6 overflow-y-auto custom-vertical-scrollbar pr-2 max-w-6xl mx-auto pb-28">
    <!-- Header Card -->
    <div class="bg-white border border-[#e9e9e7] rounded-2xl p-5 shadow-2xs flex flex-wrap items-center justify-between gap-4 shrink-0">
        <div class="min-w-0">
            <h1 class="text-2xl sm:text-3xl font-extrabold text-zinc-900 tracking-tight">{{ __('Configuración de Estados y Subestatus') }}</h1>
        </div>

        <div class="flex items-center gap-3">
            <div class="relative w-64">
                <x-lucide-search class="w-4 h-4 text-zinc-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                <input 
                    wire:model.live.debounce.250ms="search" 
                    type="text" 
                    placeholder="{{ __('Buscar subestatus...') }}" 
                    class="w-full pl-9 pr-3 py-1.5 bg-stone-50 border border-stone-200 rounded-lg text-xs focus:bg-white focus:outline-none focus:ring-2 focus:ring-stone-400 transition" />
            </div>

            <button 
                wire:click="openCreateModal" 
                class="px-4 py-2 bg-stone-900 hover:bg-stone-800 text-white text-xs font-semibold rounded-xl shadow-2xs transition flex items-center gap-1.5 cursor-pointer">
                <x-lucide-plus class="w-4 h-4" />
                <span>{{ __('Nuevo Subestatus') }}</span>
            </button>
        </div>
    </div>

    <!-- Flash Notifications -->
    @if(session()->has('message'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl text-xs font-medium flex items-center justify-between">
            <span>{{ session('message') }}</span>
        </div>
    @endif

    @if(session()->has('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl text-xs font-medium flex items-center justify-between">
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- SECCIÓN 1: SUBESTATUS POR CORE STATUS (ORGANIZACIÓN POR PROCESO) -->
    <div class="space-y-4">
        <div class="flex items-center justify-between border-b border-stone-200 pb-2.5">
            <div>
                <h2 class="font-bold text-sm text-zinc-900 flex items-center gap-2">
                    <x-lucide-workflow class="w-4 h-4 text-indigo-600" />
                    <span>{{ __('Subestatus por Core Status (Proceso)') }}</span>
                </h2>
                <p class="text-xs text-zinc-500 mt-0.5">{{ __('Subestatus específicos que corresponden al flujo de cada fase operativa.') }}</p>
            </div>
            <span class="text-xs font-semibold text-zinc-400 bg-stone-100 px-2.5 py-1 rounded-lg">
                {{ count($coreStatuses) }} {{ __('Core Statuses') }}
            </span>
        </div>

        <div class="grid grid-cols-1 gap-4">
            @foreach($coreStatuses as $coreCase)
                @php
                    $assignedSubstatuses = $substatusesByCoreStatus[$coreCase->value] ?? collect();
                @endphp
                <div class="bg-white border border-[#e9e9e7] rounded-2xl shadow-2xs overflow-hidden">
                    <!-- Core Status Section Header -->
                    <div class="px-5 py-3.5 bg-stone-50/70 border-b border-[#e9e9e7] flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="w-2.5 h-2.5 rounded-full shrink-0 {{ $coreCase->dotClass() }}" style="{{ $coreCase->dotStyle() }}"></span>
                            @if(\App\Enums\CoreStatus::isPendingDesign($coreCase))
                                <span class="px-2.5 py-0.5 rounded text-xs font-bold border bg-fuchsia-50 text-fuchsia-700 border-fuchsia-200">
                                    {{ __('Colas de Diseño (Órdenes Recibidas)') }}
                                </span>
                                <span class="text-[11px] text-zinc-400 font-mono hidden sm:inline">({{ __('Aplica automáticamente a todos los diseñadores') }})</span>
                            @else
                                <span class="px-2.5 py-0.5 rounded text-xs font-bold border {{ $coreCase->badgeStyle() }}" style="{{ $coreCase->badgeInlineStyle() }}">
                                    {{ $coreCase->label() }}
                                </span>
                                <span class="text-[11px] text-zinc-400 font-mono hidden sm:inline">({{ $coreCase->value }})</span>
                            @endif
                            <span class="text-xs text-zinc-500 font-medium ml-2">
                                {{ $assignedSubstatuses->count() }} {{ __($assignedSubstatuses->count() === 1 ? 'subestatus' : 'subestatus') }}
                            </span>
                        </div>

                        <button 
                            wire:click="openCreateModalForCoreStatus('{{ $coreCase->value }}')" 
                            class="px-2.5 py-1 rounded-lg bg-white hover:bg-stone-100 text-zinc-700 text-xs font-semibold border border-stone-200 shadow-2xs transition flex items-center gap-1.5 cursor-pointer">
                            <x-lucide-plus class="w-3.5 h-3.5 text-stone-600" />
                            <span>{{ __('Agregar Subestatus') }}</span>
                        </button>
                    </div>

                    <!-- Substatuses List under this Core Status -->
                    @if($assignedSubstatuses->isEmpty())
                        <div class="px-5 py-4 text-xs text-zinc-400 italic">
                            {{ __('Sin subestatus específicos asignados a este Core Status.') }}
                        </div>
                    @else
                        <div class="divide-y divide-stone-100" 
                             data-reorder-group="core-{{ $coreCase->value }}"
                             x-data="{ draggingId: null, dragOverId: null }">
                            @foreach($assignedSubstatuses as $sub)
                                <div 
                                    wire:key="sub-{{ $sub->id }}"
                                    data-id="{{ $sub->id }}"
                                    draggable="true"
                                    @dragstart="
                                        $event.dataTransfer.setData('text/plain', '{{ $sub->id }}');
                                        $event.dataTransfer.effectAllowed = 'move';
                                        draggingId = {{ $sub->id }};
                                    "
                                    @dragend="
                                        draggingId = null;
                                        dragOverId = null;
                                    "
                                    @dragover.prevent="
                                        $event.dataTransfer.dropEffect = 'move';
                                        dragOverId = {{ $sub->id }};
                                    "
                                    @dragleave="
                                        if (dragOverId === {{ $sub->id }}) dragOverId = null;
                                    "
                                    @drop.prevent="
                                        let srcId = parseInt($event.dataTransfer.getData('text/plain'), 10);
                                        let targetId = {{ $sub->id }};
                                        if (srcId && srcId !== targetId) {
                                            let container = $el.closest('[data-reorder-group]');
                                            let rows = Array.from(container.querySelectorAll('[data-id]'));
                                            let ids = rows.map(r => parseInt(r.getAttribute('data-id'), 10));
                                            let fromIndex = ids.indexOf(srcId);
                                            let toIndex = ids.indexOf(targetId);
                                            if (fromIndex !== -1 && toIndex !== -1) {
                                                ids.splice(fromIndex, 1);
                                                ids.splice(toIndex, 0, srcId);
                                                $wire.reorderSubstatuses(ids);
                                            }
                                        }
                                        draggingId = null;
                                        dragOverId = null;
                                    "
                                    :class="{
                                        'opacity-40 bg-stone-100': draggingId === {{ $sub->id }},
                                        'border-t-2 border-stone-800 bg-stone-50': dragOverId === {{ $sub->id }} && draggingId !== {{ $sub->id }}
                                    }"
                                    class="px-5 py-3 flex items-center justify-between hover:bg-stone-50/60 transition gap-4 group">
                                    
                                    <!-- Substatus Drag Handle, Chevrons & Badge Preview -->
                                    <div class="flex items-center gap-3 min-w-0 flex-1">
                                        <!-- Reorder Controls: Drag Handle & Up/Down Chevrons -->
                                        <div class="flex items-center gap-1 shrink-0 text-stone-400">
                                            <span class="cursor-grab active:cursor-grabbing p-1 rounded hover:bg-stone-100 hover:text-stone-700 transition" title="{{ __('Arrastrar para organizar orden') }}">
                                                <x-lucide-grip-vertical class="w-4 h-4" />
                                            </span>
                                            <div class="flex flex-col -space-y-1">
                                                <button 
                                                    type="button"
                                                    wire:click="moveUp({{ $sub->id }})"
                                                    @disabled($loop->first)
                                                    class="p-0.5 rounded hover:bg-stone-200 hover:text-stone-700 disabled:opacity-20 disabled:hover:bg-transparent cursor-pointer disabled:cursor-not-allowed transition"
                                                    title="{{ __('Mover hacia arriba') }}">
                                                    <x-lucide-chevron-up class="w-3.5 h-3.5" />
                                                </button>
                                                <button 
                                                    type="button"
                                                    wire:click="moveDown({{ $sub->id }})"
                                                    @disabled($loop->last)
                                                    class="p-0.5 rounded hover:bg-stone-200 hover:text-stone-700 disabled:opacity-20 disabled:hover:bg-transparent cursor-pointer disabled:cursor-not-allowed transition"
                                                    title="{{ __('Mover hacia abajo') }}">
                                                    <x-lucide-chevron-down class="w-3.5 h-3.5" />
                                                </button>
                                            </div>
                                        </div>

                                        <span 
                                            class="px-3 py-1 rounded-md text-xs font-bold border shrink-0 shadow-2xs"
                                            style="background-color: {{ $sub->bg_color }}; color: {{ $sub->text_color }}; border-color: {{ $sub->border_color }};">
                                            {{ __($sub->name) }}
                                        </span>

                                        @if($sub->is_default)
                                            <span class="text-[10px] bg-emerald-50 text-emerald-700 border border-emerald-200 px-2 py-0.5 rounded font-bold flex items-center gap-1 shrink-0">
                                                <x-lucide-check-circle-2 class="w-3 h-3 text-emerald-600" />
                                                <span>{{ __('Por Defecto') }}</span>
                                            </span>
                                        @endif

                                        @if($sub->is_system)
                                            <span class="text-[10px] bg-stone-100 text-stone-600 px-2 py-0.5 rounded border border-stone-200 font-medium shrink-0">
                                                {{ __('Sistema') }}
                                            </span>
                                        @endif
                                    </div>

                                    <!-- Style Type Indicator -->
                                    <div class="hidden sm:flex items-center gap-2 shrink-0">
                                        <span class="text-[10px] px-2 py-0.5 rounded border font-medium {{ $sub->style_type === 'solid' ? 'bg-stone-800 text-white border-stone-900' : 'bg-stone-100 text-zinc-600 border-stone-200' }}">
                                            {{ $sub->style_type === 'solid' ? __('Color Sólido') : __('Fondo Claro') }}
                                        </span>
                                    </div>

                                    <!-- Actions -->
                                    <div class="flex items-center gap-2 shrink-0">
                                        <button 
                                            wire:click="openEditModal({{ $sub->id }})" 
                                            class="px-2.5 py-1 rounded-lg bg-stone-100 hover:bg-stone-200 text-zinc-700 text-xs font-medium border border-stone-200 transition flex items-center gap-1 cursor-pointer">
                                            <x-lucide-pencil class="w-3.5 h-3.5" />
                                            <span>{{ __('Editar') }}</span>
                                        </button>

                                        @if(!$sub->is_system)
                                            <button 
                                                wire:click="delete({{ $sub->id }})" 
                                                wire:confirm="{{ __('¿Estás seguro de eliminar el subestatus ":name"?', ['name' => $sub->name]) }}" 
                                                class="p-1 rounded-lg bg-white hover:bg-red-50 text-red-600 border border-stone-200 hover:border-red-200 transition cursor-pointer" 
                                                title="{{ __('Eliminar Subestatus') }}">
                                                <x-lucide-trash-2 class="w-4 h-4" />
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <!-- SECCIÓN 2: SUBESTATUS TRANSVERSALES Y ALERTAS GLOBALES -->
    <div class="space-y-4 pt-4">
        <div class="flex items-center justify-between border-b border-stone-200 pb-2.5">
            <div>
                <h2 class="font-bold text-sm text-zinc-900 flex items-center gap-2">
                    <x-lucide-layers class="w-4 h-4 text-purple-600" />
                    <span>{{ __('Subestatus Transversales y Alertas Globales') }}</span>
                </h2>
                <p class="text-xs text-zinc-500 mt-0.5">{{ __('Subestatus aplicables a cualquier fase de la orden (ej. Ticket, Cliente Potencial, Urgente) y alertas automáticas por tiempo SLA.') }}</p>
            </div>
            <span class="text-xs font-semibold text-purple-700 bg-purple-50 px-2.5 py-1 rounded-lg border border-purple-200">
                {{ $globalSubstatuses->count() }} {{ __('Subestatus Globales') }}
            </span>
        </div>

        <div class="bg-white border border-[#e9e9e7] rounded-2xl shadow-2xs overflow-hidden">
            @if($globalSubstatuses->isEmpty())
                <div class="p-8 text-center text-zinc-400 text-xs">
                    {{ __('No hay subestatus transversales globales registrados.') }}
                </div>
            @else
                <div class="divide-y divide-stone-100" 
                     data-reorder-group="global"
                     x-data="{ draggingId: null, dragOverId: null }">
                    @foreach($globalSubstatuses as $sub)
                        <div 
                            wire:key="sub-{{ $sub->id }}"
                            data-id="{{ $sub->id }}"
                            draggable="true"
                            @dragstart="
                                $event.dataTransfer.setData('text/plain', '{{ $sub->id }}');
                                $event.dataTransfer.effectAllowed = 'move';
                                draggingId = {{ $sub->id }};
                            "
                            @dragend="
                                draggingId = null;
                                dragOverId = null;
                            "
                            @dragover.prevent="
                                $event.dataTransfer.dropEffect = 'move';
                                dragOverId = {{ $sub->id }};
                            "
                            @dragleave="
                                if (dragOverId === {{ $sub->id }}) dragOverId = null;
                            "
                            @drop.prevent="
                                let srcId = parseInt($event.dataTransfer.getData('text/plain'), 10);
                                let targetId = {{ $sub->id }};
                                if (srcId && srcId !== targetId) {
                                    let container = $el.closest('[data-reorder-group]');
                                    let rows = Array.from(container.querySelectorAll('[data-id]'));
                                    let ids = rows.map(r => parseInt(r.getAttribute('data-id'), 10));
                                    let fromIndex = ids.indexOf(srcId);
                                    let toIndex = ids.indexOf(targetId);
                                    if (fromIndex !== -1 && toIndex !== -1) {
                                        ids.splice(fromIndex, 1);
                                        ids.splice(toIndex, 0, srcId);
                                        $wire.reorderSubstatuses(ids);
                                    }
                                }
                                draggingId = null;
                                dragOverId = null;
                            "
                            :class="{
                                'opacity-40 bg-stone-100': draggingId === {{ $sub->id }},
                                'border-t-2 border-stone-800 bg-stone-50': dragOverId === {{ $sub->id }} && draggingId !== {{ $sub->id }}
                            }"
                            class="px-5 py-3.5 flex items-center justify-between hover:bg-stone-50/60 transition gap-4 group">
                            
                            <!-- Substatus Drag Handle, Chevrons & Badge Preview -->
                            <div class="flex items-center gap-3 min-w-0 flex-1">
                                <!-- Reorder Controls: Drag Handle & Up/Down Chevrons -->
                                <div class="flex items-center gap-1 shrink-0 text-stone-400">
                                    <span class="cursor-grab active:cursor-grabbing p-1 rounded hover:bg-stone-100 hover:text-stone-700 transition" title="{{ __('Arrastrar para organizar orden') }}">
                                        <x-lucide-grip-vertical class="w-4 h-4" />
                                    </span>
                                    <div class="flex flex-col -space-y-1">
                                        <button 
                                            type="button"
                                            wire:click="moveUp({{ $sub->id }})"
                                            @disabled($loop->first)
                                            class="p-0.5 rounded hover:bg-stone-200 hover:text-stone-700 disabled:opacity-20 disabled:hover:bg-transparent cursor-pointer disabled:cursor-not-allowed transition"
                                            title="{{ __('Mover hacia arriba') }}">
                                            <x-lucide-chevron-up class="w-3.5 h-3.5" />
                                        </button>
                                        <button 
                                            type="button"
                                            wire:click="moveDown({{ $sub->id }})"
                                            @disabled($loop->last)
                                            class="p-0.5 rounded hover:bg-stone-200 hover:text-stone-700 disabled:opacity-20 disabled:hover:bg-transparent cursor-pointer disabled:cursor-not-allowed transition"
                                            title="{{ __('Mover hacia abajo') }}">
                                            <x-lucide-chevron-down class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </div>

                                <span 
                                    class="px-3 py-1 rounded-md text-xs font-bold border shrink-0 shadow-2xs"
                                    style="background-color: {{ $sub->bg_color }}; color: {{ $sub->text_color }}; border-color: {{ $sub->border_color }};">
                                    {{ __($sub->name) }}
                                </span>

                                <span class="text-[10px] bg-purple-50 text-purple-700 border border-purple-200 px-2 py-0.5 rounded font-bold flex items-center gap-1 shrink-0">
                                    <x-lucide-globe class="w-3 h-3 text-purple-600" />
                                    <span>{{ __('Transversal Global') }}</span>
                                </span>

                                @if($sub->is_system)
                                    <span class="text-[10px] bg-stone-100 text-stone-600 px-2 py-0.5 rounded border border-stone-200 font-medium shrink-0">
                                        {{ __('Sistema') }}
                                    </span>
                                @endif
                            </div>

                            <!-- Style Type Indicator -->
                            <div class="hidden sm:flex items-center gap-2 shrink-0">
                                <span class="text-[10px] px-2 py-0.5 rounded border font-medium {{ $sub->style_type === 'solid' ? 'bg-stone-800 text-white border-stone-900' : 'bg-stone-100 text-zinc-600 border-stone-200' }}">
                                    {{ $sub->style_type === 'solid' ? __('Color Sólido') : __('Fondo Claro') }}
                                </span>
                            </div>

                            <!-- Actions -->
                            <div class="flex items-center gap-2 shrink-0">
                                <button 
                                    wire:click="openEditModal({{ $sub->id }})" 
                                    class="px-2.5 py-1 rounded-lg bg-stone-100 hover:bg-stone-200 text-zinc-700 text-xs font-medium border border-stone-200 transition flex items-center gap-1 cursor-pointer">
                                    <x-lucide-pencil class="w-3.5 h-3.5" />
                                    <span>{{ __('Editar') }}</span>
                                </button>

                                @if(!$sub->is_system)
                                    <button 
                                        wire:click="delete({{ $sub->id }})" 
                                        wire:confirm="{{ __('¿Estás seguro de eliminar el subestatus ":name"?', ['name' => $sub->name]) }}" 
                                        class="p-1 rounded-lg bg-white hover:bg-red-50 text-red-600 border border-stone-200 hover:border-red-200 transition cursor-pointer" 
                                        title="{{ __('Eliminar Subestatus') }}">
                                        <x-lucide-trash-2 class="w-4 h-4" />
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <!-- Create / Edit Substatus Modal -->
    @if($showModal)
        <div @click.self="confirmClose(() => $wire.closeModal())" class="fixed inset-0 z-[100] bg-black/40 backdrop-blur-xs flex items-center justify-center p-4">
            <div 
                x-data="{
                    initialName: null,
                    initialColor: null,
                    initialStyleType: null,
                    init() {
                        this.initialName = $wire.name || '';
                        this.initialColor = $wire.main_color || '';
                        this.initialStyleType = $wire.style_type || '';
                        window.KudosDirtyGuard.register('substatus-modal', () => this.isDirty());
                        this.$cleanup(() => window.KudosDirtyGuard.unregister('substatus-modal'));
                    },
                    isDirty() {
                        if (!$wire.showModal) return false;
                        return ($wire.name || '') !== this.initialName || 
                               ($wire.main_color || '') !== this.initialColor ||
                               ($wire.style_type || '') !== this.initialStyleType;
                    },
                    confirmClose(action) {
                        if (window.KudosDirtyGuard && window.KudosDirtyGuard.isConfirmModalOpen) {
                            return;
                        }
                        if (this.isDirty()) {
                            window.KudosDirtyGuard.openConfirmModal({
                                title: @js(__('¿Guardar cambios en subestatus?')),
                                description: @js(__('Has modificado la configuración de este subestatus.')),
                                cancelText: @js(__('Cancelar')),
                                discardText: @js(__('No guardar')),
                                saveText: @js(__('Guardar')),
                                onCancel: () => {},
                                onDiscard: () => {
                                    window.KudosDirtyGuard.unregister('substatus-modal');
                                    action();
                                },
                                onSave: () => {
                                    window.KudosDirtyGuard.unregister('substatus-modal');
                                    $wire.save();
                                }
                            });
                        } else {
                            action();
                        }
                    }
                }"
                @keydown.window.escape="confirmClose(() => $wire.closeModal())"
                class="bg-white rounded-2xl border border-stone-200 shadow-xl max-w-md w-full p-6 space-y-5 animate-in fade-in zoom-in duration-150">
                <div class="flex items-center justify-between border-b border-stone-100 pb-3">
                    <h3 class="font-bold text-sm text-zinc-900 flex items-center gap-2">
                        <x-lucide-tag class="w-4 h-4 text-stone-700" />
                        <span>{{ $editingId ? __('Editar Subestatus') : __('Nuevo Subestatus') }}</span>
                    </h3>
                    <button type="button" @click="confirmClose(() => $wire.closeModal())" class="text-zinc-400 hover:text-zinc-700 cursor-pointer">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                </div>

                <!-- Form Fields -->
                <form wire:submit.prevent="save" class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-zinc-700 mb-1">{{ __('Nombre del Subestatus') }}</label>
                        <input 
                            wire:model="name" 
                            type="text" 
                            placeholder="{{ __('Ej: EN REVISIÓN CLIENTE') }}" 
                            class="w-full px-3 py-2 border border-stone-200 rounded-lg text-xs focus:ring-2 focus:ring-stone-400 focus:outline-none uppercase font-semibold" />
                        @error('name') <span class="text-[11px] text-red-600 mt-0.5 block">{{ $message }}</span> @enderror
                    </div>

                    <!-- Core Status / Global Assignment -->
                    <div class="grid grid-cols-1 gap-3 p-3 bg-stone-50 border border-stone-200 rounded-xl">
                        <div>
                            <label class="block text-xs font-medium text-zinc-700 mb-1 flex items-center justify-between">
                                <span>{{ __('Core Status Asociado') }}</span>
                                @if($is_global)
                                    <span class="text-[10px] text-purple-700 font-bold bg-purple-50 px-2 py-0.5 rounded border border-purple-200">{{ __('Modo Transversal Activo') }}</span>
                                @endif
                            </label>
                            <select 
                                wire:model.live="core_status" 
                                :disabled="$wire.is_global"
                                class="w-full px-3 py-2 border border-stone-200 rounded-lg text-xs focus:ring-2 focus:ring-stone-400 focus:outline-none bg-white font-medium disabled:opacity-50 disabled:bg-stone-100">
                                <option value="">{{ __('-- Seleccionar Core Status --') }}</option>
                                @foreach($dropdownStatuses as $statusCase)
                                    <option value="{{ $statusCase->value }}">
                                        @if(\App\Enums\CoreStatus::isPendingDesign($statusCase))
                                            {{ __('Colas de Diseño (Diseñadores: Euralíz, Adrián, César)') }}
                                        @else
                                            {{ $statusCase->label() }} ({{ $statusCase->value }})
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            <span class="text-[10px] text-zinc-400 mt-0.5 block">{{ __('Selecciona el Core Status al cual pertenece este subestatus.') }}</span>
                        </div>

                        <div class="flex items-center justify-between pt-1 border-t border-stone-200/60">
                            <label class="flex items-center gap-2 text-xs font-medium text-zinc-700 cursor-pointer">
                                <input type="checkbox" wire:model.live="is_global" class="rounded border-stone-300 text-stone-900 focus:ring-stone-400">
                                <span class="font-semibold text-purple-900">{{ __('Es Subestatus Transversal / Global') }}</span>
                            </label>

                            @if(!$is_global && !empty($core_status))
                                <label class="flex items-center gap-2 text-xs font-medium text-zinc-700 cursor-pointer">
                                    <input type="checkbox" wire:model="is_default" class="rounded border-stone-300 text-stone-900 focus:ring-stone-400">
                                    <span class="font-semibold text-emerald-900">{{ __('Por Defecto') }}</span>
                                </label>
                            @endif
                        </div>
                    </div>

                    <!-- Style Type Selector: Light vs Solid -->
                    <div class="space-y-1.5">
                        <label class="block text-xs font-medium text-zinc-700">{{ __('Estilo de Fondo') }}</label>
                        <div class="grid grid-cols-2 gap-2">
                            <button 
                                type="button" 
                                wire:click="setStyleType('light')" 
                                class="p-2.5 rounded-xl border text-center text-xs font-semibold transition cursor-pointer flex items-center justify-center gap-2 {{ $style_type === 'light' ? 'bg-stone-900 text-white border-stone-900 shadow-2xs' : 'bg-stone-50 border-stone-200 text-zinc-700 hover:bg-stone-100' }}">
                                <x-lucide-sun class="w-3.5 h-3.5" />
                                <span>{{ __('Fondo Claro') }}</span>
                            </button>
                            <button 
                                type="button" 
                                wire:click="setStyleType('solid')" 
                                class="p-2.5 rounded-xl border text-center text-xs font-semibold transition cursor-pointer flex items-center justify-center gap-2 {{ $style_type === 'solid' ? 'bg-stone-900 text-white border-stone-900 shadow-2xs' : 'bg-stone-50 border-stone-200 text-zinc-700 hover:bg-stone-100' }}">
                                <x-lucide-paint-bucket class="w-3.5 h-3.5" />
                                <span>{{ __('Color Sólido') }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Single Main Color Selection & Presets -->
                    <div class="space-y-2">
                        <label class="block text-xs font-medium text-zinc-700">{{ __('Color Principal del Subestatus') }}</label>
                        
                        <div class="flex items-center gap-3">
                            <input 
                                wire:model.live="main_color" 
                                type="color" 
                                class="w-10 h-10 rounded-xl border border-stone-300 p-0.5 cursor-pointer shadow-2xs shrink-0" />
                            <input 
                                wire:model.live="main_color" 
                                type="text" 
                                class="w-32 text-xs font-mono px-3 py-2 border border-stone-200 rounded-lg uppercase" />
                        </div>

                        <!-- Curated Palette Swatches -->
                        <div class="pt-2">
                            <span class="text-[11px] font-medium text-zinc-600 block mb-1.5">{{ __('Paleta del Proyecto:') }}</span>
                            <div class="flex flex-wrap items-center gap-2">
                                @php
                                    $presets = [
                                        '#EF4444' => __('Rojo'),
                                        '#F43F5E' => __('Rosa'),
                                        '#F97316' => __('Naranja'),
                                        '#F59E0B' => __('Ámbar'),
                                        '#10B981' => __('Verde'),
                                        '#14B8A6' => __('Teal'),
                                        '#0EA5E9' => __('Sky'),
                                        '#3B82F6' => __('Azul'),
                                        '#6366F1' => __('Índigo'),
                                        '#A855F7' => __('Púrpura'),
                                        '#EC4899' => __('Fucsia'),
                                        '#78716C' => __('Piedra'),
                                    ];
                                @endphp
                                @foreach($presets as $hex => $label)
                                    <button 
                                        type="button"
                                        wire:click="selectPresetColor('{{ $hex }}')" 
                                        class="w-6 h-6 rounded-full border border-black/10 transition shadow-2xs hover:scale-110 cursor-pointer {{ strtoupper($main_color) === $hex ? 'ring-2 ring-offset-2 ring-stone-900 scale-110' : '' }}" 
                                        style="background-color: {{ $hex }};" 
                                        title="{{ $label }}">
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- Live Badge Preview -->
                    <div class="p-4 bg-stone-50 border border-stone-200 rounded-xl space-y-1.5">
                        <span class="text-[10px] text-zinc-400 uppercase font-semibold block">{{ __('Vista Previa del Badge') }}</span>
                        <div class="pt-0.5">
                            <span 
                                class="px-3.5 py-1.5 rounded-lg text-xs font-bold border shadow-2xs inline-block"
                                style="background-color: {{ $bg_color }}; color: {{ $text_color }}; border-color: {{ $border_color }};">
                                {{ $name ? strtoupper($name) : __('EJEMPLO SUBESTATUS') }}
                            </span>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-stone-100">
                        <button 
                            type="button" 
                            @click="confirmClose(() => $wire.closeModal())" 
                            class="px-4 py-2 border border-stone-200 text-zinc-700 hover:bg-stone-50 text-xs font-medium rounded-xl transition cursor-pointer">
                            {{ __('Cancelar') }}
                        </button>
                        <button 
                            type="submit" 
                            :disabled="!isDirty()"
                            :class="isDirty() ? 'bg-emerald-600 hover:bg-emerald-700 text-white cursor-pointer shadow-sm shadow-emerald-600/20' : 'bg-stone-200 text-stone-400 border border-stone-200 cursor-not-allowed'"
                            class="px-4 py-2 text-xs font-semibold rounded-xl transition flex items-center gap-1.5"
                        >
                            <x-lucide-check class="w-3.5 h-3.5" x-show="isDirty()" />
                            <span>{{ __('Guardar Subestatus') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
