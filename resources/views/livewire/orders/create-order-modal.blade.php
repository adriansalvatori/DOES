<div>
    @if($showModal)
        <div 
            id="create-order-modal-container"
            @click.self="confirmClose(() => $wire.closeModal())" 
            class="fixed inset-0 overflow-y-auto bg-stone-900/40 backdrop-blur-xs flex items-center justify-center p-4"
            style="z-index: 300;"
            @pointerdown="window.KudosModalStack?.bringToFront('create-order-modal')"
        >
            <div 
                x-data="{
                    initialForm: null,
                    init() {
                        this.snapshot();
                        this.$watch('$wire.showModal', (show) => {
                            if (!show) {
                                window.KudosDirtyGuard.unregister('create-order-modal');
                                window.KudosModalStack?.unregister('create-order-modal');
                            } else {
                                this.snapshot();
                                window.KudosDirtyGuard.register('create-order-modal', () => this.isDirty(), this.$el);
                                this.$nextTick(() => {
                                    const container = document.getElementById('create-order-modal-container') || this.$el;
                                    window.KudosModalStack?.register('create-order-modal', container, () => this.confirmClose(() => $wire.closeModal()));
                                });
                            }
                        });
                        window.KudosDirtyGuard.register('create-order-modal', () => this.isDirty(), this.$el);
                        this.$nextTick(() => {
                            const container = document.getElementById('create-order-modal-container') || this.$el;
                            window.KudosModalStack?.register('create-order-modal', container, () => this.confirmClose(() => $wire.closeModal()));
                        });
                        this.$cleanup(() => {
                            window.KudosDirtyGuard.unregister('create-order-modal');
                            window.KudosModalStack?.unregister('create-order-modal');
                        });
                    },
                    snapshot() {
                        this.initialForm = JSON.stringify({
                            company: ($wire.companyName || '').toString().trim(),
                            task: ($wire.taskName || '').toString().trim(),
                            wo: ($wire.woNumber || '').toString().trim(),
                            trelloId: ($wire.trelloCardId || '').toString().trim(),
                            resp: ($wire.responsiblePerson || '').toString().trim(),
                            location: ($wire.locationName || '').toString().trim(),
                            substatus: ($wire.substatus || '').toString().trim(),
                            flags: Array.from($wire.flags || []).map(String).sort(),
                            due: ($wire.dueDate || '').toString().trim(),
                            createOnTrello: Boolean($wire.createOnTrello),
                            designers: Array.from($wire.designerIds || []).map(String).sort()
                        });
                    },
                    isDirty() {
                        if (!$wire.showModal || !this.initialForm) return false;
                        const current = JSON.stringify({
                            company: ($wire.companyName || '').toString().trim(),
                            task: ($wire.taskName || '').toString().trim(),
                            wo: ($wire.woNumber || '').toString().trim(),
                            trelloId: ($wire.trelloCardId || '').toString().trim(),
                            resp: ($wire.responsiblePerson || '').toString().trim(),
                            location: ($wire.locationName || '').toString().trim(),
                            substatus: ($wire.substatus || '').toString().trim(),
                            flags: Array.from($wire.flags || []).map(String).sort(),
                            due: ($wire.dueDate || '').toString().trim(),
                            createOnTrello: Boolean($wire.createOnTrello),
                            designers: Array.from($wire.designerIds || []).map(String).sort()
                        });
                        return current !== this.initialForm;
                    },
                    confirmClose(action) {
                        if (window.KudosDirtyGuard && window.KudosDirtyGuard.isConfirmModalOpen) {
                            return;
                        }
                        if (this.isDirty()) {
                            window.KudosDirtyGuard.openConfirmModal({
                                title: '¿Guardar nueva orden?',
                                description: 'Has ingresado datos para crear una nueva orden.',
                                cancelText: 'Cancelar',
                                discardText: 'No guardar',
                                saveText: 'Guardar',
                                onCancel: () => {},
                                onDiscard: () => {
                                    window.KudosDirtyGuard.unregister('create-order-modal');
                                    window.KudosModalStack?.unregister('create-order-modal');
                                    action();
                                },
                                onSave: () => {
                                    window.KudosDirtyGuard.unregister('create-order-modal');
                                    window.KudosModalStack?.unregister('create-order-modal');
                                    $wire.save();
                                }
                            });
                        } else {
                            window.KudosDirtyGuard.unregister('create-order-modal');
                            window.KudosModalStack?.unregister('create-order-modal');
                            action();
                        }
                    }
                }"
                @keydown.window.escape="if (window.KudosModalStack ? window.KudosModalStack.isTop('create-order-modal') : true) confirmClose(() => $wire.closeModal())"
                class="bg-white border border-[#e9e9e7] rounded-xl shadow-2xl max-w-2xl w-full flex flex-col transition duration-200">
                
                <!-- Modal Header -->
                <div class="px-6 py-4 border-b border-[#e9e9e7] bg-[#fbfbfa] flex items-center justify-between">
                    <div class="flex items-center gap-2 min-w-0">
                        <div class="p-1.5 rounded-lg bg-stone-900 text-white shrink-0">
                            @if($isDuplicating)
                                <x-lucide-copy class="w-4 h-4 text-indigo-300" />
                            @else
                                <x-lucide-plus-circle class="w-4 h-4" />
                            @endif
                        </div>
                        <div>
                            @if($isDuplicating)
                                <h3 class="text-sm font-semibold text-zinc-900 tracking-tight">Duplicar Orden</h3>
                                <p class="text-[11px] text-zinc-500">Modifica los datos para crear la nueva copia de la orden.</p>
                            @else
                                <h3 class="text-sm font-semibold text-zinc-900 tracking-tight">Crear Nueva Orden</h3>
                                <p class="text-[11px] text-zinc-500">Añade una nueva orden directamente al flujo de trabajo activo.</p>
                            @endif
                        </div>
                    </div>
                    <button type="button" @click="confirmClose(() => $wire.closeModal())" class="p-1 text-zinc-400 hover:text-zinc-700 hover:bg-stone-100 rounded-md transition cursor-pointer">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                </div>

                <!-- Form Fields Container -->
                <form wire:submit.prevent="save" class="p-6 space-y-4 text-xs">
                    
                    <!-- Row 1: WO Number, Trello ID & Responsible Person -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                        <div>
                            <label class="block font-medium text-zinc-700 mb-1 flex items-center gap-1">
                                <x-lucide-hash class="w-3 h-3 text-zinc-400" />
                                <span>WO (Opcional)</span>
                            </label>
                            <div class="flex rounded-md shadow-2xs">
                                <span class="inline-flex items-center px-2 rounded-l-md border border-r-0 border-[#e9e9e7] bg-stone-100 text-zinc-600 font-mono font-bold text-xs select-none">
                                    WO
                                </span>
                                <input type="text" wire:model="woNumber" placeholder="16350" class="w-full bg-[#fbfbfa] border border-[#e9e9e7] rounded-r-md px-2.5 py-1.5 text-zinc-800 focus:border-stone-400 focus:outline-none font-mono font-semibold">
                            </div>
                            <button 
                                type="button" 
                                wire:click="generateWoNumber" 
                                wire:loading.attr="disabled"
                                class="mt-2 w-full inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-50/80 hover:bg-indigo-100 text-indigo-700 hover:text-indigo-900 font-semibold text-xs transition-all duration-150 cursor-pointer hover:shadow-xs active:scale-[0.98] disabled:opacity-50 select-none group"
                                title="Crear siguiente número de WO automáticamente">
                                <x-lucide-sparkles class="w-3.5 h-3.5 text-amber-500 shrink-0 transition-transform duration-200 group-hover:scale-125 group-hover:rotate-12" wire:loading.remove wire:target="generateWoNumber" />
                                <x-lucide-loader-2 class="w-3.5 h-3.5 animate-spin text-indigo-600 shrink-0" wire:loading wire:target="generateWoNumber" />
                                <span>Crear WO Automática</span>
                            </button>
                        </div>

                        <div class="relative" 
                             x-data="{ 
                                 open: false,
                                 selectCard(id) {
                                     $wire.set('trelloCardId', id);
                                     this.open = false;
                                 }
                             }"
                             x-dropdown-nav>
                            <label class="font-medium text-zinc-700 mb-1 flex items-center gap-1">
                                <x-lucide-external-link class="w-3 h-3 text-blue-600" />
                                <span>ID Tarjeta Trello</span>
                            </label>

                            <div class="relative">
                                <input 
                                    type="text" 
                                    wire:model.live="trelloCardId" 
                                    @focus="open = true"
                                    @click.outside="open = false"
                                    autocomplete="off"
                                    placeholder="Ej. AbCdEf12 o buscar..." 
                                    class="w-full bg-[#fbfbfa] border border-[#e9e9e7] rounded-md px-2.5 py-1.5 text-zinc-800 focus:border-stone-400 focus:outline-none font-mono pr-7">

                                <button 
                                    type="button" 
                                    @click="open = !open" 
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-600 p-0.5"
                                    title="Ver tarjetas de Trello">
                                    <x-lucide-chevron-down class="w-3.5 h-3.5" />
                                </button>
                            </div>

                            <!-- Custom Searchable Dropdown Popup -->
                            <div 
                                x-show="open" 
                                x-cloak
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                class="absolute left-0 right-0 top-full mt-1 z-50 bg-white border border-[#e9e9e7] rounded-lg shadow-2xl max-h-64 overflow-y-auto divide-y divide-stone-100 text-xs min-w-[260px]"
                                style="display: none;">
                                
                                <div class="px-2.5 py-1 bg-stone-50 border-b border-stone-100 font-bold text-[10px] uppercase text-zinc-400 sticky top-0 z-10">
                                    Tarjetas Disponibles ({{ count($availableTrelloCards) }})
                                </div>

                                @forelse($availableTrelloCards as $tc)
                                    <button 
                                        type="button"
                                        x-show="!$wire.trelloCardId || '{{ strtolower(addslashes($tc->trello_card_id . ' ' . $tc->wo_number . ' ' . $tc->company_name . ' ' . $tc->task_name . ' ' . $tc->trello_title)) }}'.includes(($wire.trelloCardId || '').toLowerCase())"
                                        @click="selectCard('{{ $tc->trello_card_id }}')" 
                                        class="w-full text-left p-2 hover:bg-blue-50/70 focus:bg-blue-50 focus:outline-none cursor-pointer flex items-center justify-between gap-2 transition">
                                        <div class="min-w-0">
                                            <span class="font-bold text-zinc-900 block truncate text-[11px]">
                                                {{ $tc->trello_title ?: ($tc->company_name ?: 'Tarjeta Trello') }}
                                            </span>
                                            @if($tc->task_name && $tc->task_name !== $tc->trello_title)
                                                <span class="text-[10px] text-zinc-500 block truncate">{{ $tc->task_name }}</span>
                                            @endif
                                        </div>
                                        <span class="font-mono text-[10px] text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded border border-blue-200 shrink-0">
                                            {{ substr($tc->trello_card_id, 0, 8) }}...
                                        </span>
                                    </button>
                                @empty
                                    <div class="p-3 text-center text-zinc-400 italic text-[11px]">No hay tarjetas disponibles.</div>
                                @endforelse
                            </div>

                            <!-- Option to auto-create Trello card if no ID is specified -->
                            <div class="mt-2 flex items-center gap-2">
                                <input 
                                    type="checkbox" 
                                    id="createOnTrelloCheckbox" 
                                    wire:model="createOnTrello"
                                    x-bind:disabled="!!$wire.trelloCardId"
                                    class="w-3.5 h-3.5 text-blue-600 rounded border-stone-300 focus:ring-blue-500 cursor-pointer disabled:opacity-50">
                                <label for="createOnTrelloCheckbox" class="text-[11px] text-zinc-600 cursor-pointer select-none font-medium flex items-center gap-1">
                                    <span>Crear nueva tarjeta en Trello al guardar</span>
                                </label>
                            </div>
                        </div>

                        <!-- Responsible Person (Searchable Dropdown Menu) -->
                        <div class="relative" 
                             x-data="{ 
                                 open: false,
                                 selectResp(resp) {
                                     $wire.set('responsiblePerson', resp);
                                     this.open = false;
                                 }
                             }"
                             x-dropdown-nav>
                            <label class="block font-medium text-zinc-700 mb-1 flex items-center gap-1">
                                <x-lucide-user-check class="w-3 h-3 text-zinc-400" />
                                <span>Responsable</span>
                            </label>
                            <div class="relative">
                                <input 
                                    type="text" 
                                    wire:model.live="responsiblePerson" 
                                    @focus="open = true"
                                    @click.outside="open = false"
                                    autocomplete="off"
                                    placeholder="Ej. AGUSTIN o buscar..." 
                                    class="w-full bg-[#fbfbfa] border border-[#e9e9e7] rounded-md px-2.5 py-1.5 text-zinc-800 focus:border-stone-400 focus:outline-none pr-7 font-semibold">
                                
                                <button 
                                    type="button" 
                                    @click="open = !open" 
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-600 p-0.5"
                                    title="Ver lista de responsables">
                                    <x-lucide-chevron-down class="w-3.5 h-3.5" />
                                </button>
                            </div>

                            <div 
                                x-show="open" 
                                x-cloak
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                class="absolute left-0 right-0 top-full mt-1 z-50 bg-white border border-[#e9e9e7] rounded-lg shadow-2xl max-h-60 overflow-y-auto divide-y divide-stone-100 text-xs"
                                style="display: none;">
                                
                                @if(!empty($clientContacts))
                                    <div class="px-2.5 py-1 bg-emerald-50/80 border-b border-emerald-100 font-bold text-[10px] uppercase text-emerald-800 flex items-center justify-between">
                                        <span>Contactos del cliente</span>
                                        <x-lucide-user class="w-3 h-3 text-emerald-600" />
                                    </div>
                                    @foreach($clientContacts as $cResp)
                                        <button 
                                            type="button"
                                            x-show="!$wire.responsiblePerson || '{{ strtolower(addslashes($cResp)) }}'.includes(($wire.responsiblePerson || '').toLowerCase())"
                                            @click="selectResp('{{ addslashes($cResp) }}')" 
                                            class="w-full text-left p-2 hover:bg-emerald-50 focus:bg-emerald-50 focus:outline-none cursor-pointer font-bold text-zinc-900 transition flex items-center justify-between">
                                            <span>{{ $cResp }}</span>
                                            <span class="text-[10px] text-emerald-600 font-medium">(Registrado)</span>
                                        </button>
                                    @endforeach
                                @endif

                                @php
                                    $otherResponsibles = array_diff($existingResponsibles->toArray(), $clientContacts ?? []);
                                @endphp

                                @if(!empty($otherResponsibles))
                                    @if(!empty($clientContacts))
                                        <div class="px-2.5 py-1 bg-stone-50 border-b border-stone-100 font-bold text-[10px] uppercase text-zinc-400">
                                            Otros responsables
                                        </div>
                                    @endif
                                    @foreach($otherResponsibles as $resp)
                                        <button 
                                            type="button"
                                            x-show="!$wire.responsiblePerson || '{{ strtolower(addslashes($resp)) }}'.includes(($wire.responsiblePerson || '').toLowerCase())"
                                            @click="selectResp('{{ addslashes($resp) }}')" 
                                            class="w-full text-left p-2 hover:bg-stone-100 focus:bg-stone-100 focus:outline-none cursor-pointer font-medium text-zinc-800 transition">
                                            {{ $resp }}
                                        </button>
                                    @endforeach
                                @endif

                                @if(empty($clientContacts) && empty($otherResponsibles))
                                    <div class="p-2.5 text-zinc-400 italic text-[11px]">Escribe un nuevo responsable...</div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Row 2: Company Name (Required Searchable Combobox) -->
                    <div class="relative" 
                         x-data="{ 
                             open: false,
                             selectComp(comp) {
                                 $wire.set('companyName', comp);
                                 this.open = false;
                             }
                         }"
                         x-dropdown-nav>
                        <label class="block font-medium text-zinc-700 mb-1 flex items-center gap-1">
                            <x-lucide-building-2 class="w-3 h-3 text-zinc-400" />
                            <span>Nombre Empresa <span class="text-red-500">*</span></span>
                        </label>
                        <div class="relative">
                            <input 
                                type="text" 
                                wire:model.live="companyName" 
                                @focus="open = true"
                                @click.outside="open = false"
                                autocomplete="off"
                                placeholder="Ej. RESTAURANTE EL TACO LOCO..." 
                                class="w-full bg-[#fbfbfa] border border-[#e9e9e7] rounded-md px-3 py-1.5 text-zinc-800 uppercase focus:border-stone-400 focus:outline-none font-semibold pr-7"
                                x-on:blur="$event.target.value = $event.target.value.toUpperCase()">
                            
                            <button 
                                type="button" 
                                @click="open = !open" 
                                class="absolute right-2 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-600 p-0.5">
                                <x-lucide-chevron-down class="w-3.5 h-3.5" />
                            </button>
                        </div>

                        <div 
                            x-show="open" 
                            x-cloak
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            class="absolute left-0 right-0 top-full mt-1 z-50 bg-white border border-[#e9e9e7] rounded-lg shadow-2xl max-h-60 overflow-y-auto divide-y divide-stone-100 text-xs"
                            style="display: none;">
                            @forelse($existingCompanies as $comp)
                                <button 
                                    type="button"
                                    x-show="!$wire.companyName || '{{ strtolower(addslashes($comp)) }}'.includes(($wire.companyName || '').toLowerCase())"
                                    @click="selectComp('{{ addslashes($comp) }}')" 
                                    class="w-full text-left p-2 hover:bg-stone-100 focus:bg-stone-100 focus:outline-none cursor-pointer font-bold text-zinc-900 transition">
                                    {{ $comp }}
                                </button>
                            @empty
                                <div class="p-2.5 text-zinc-400 italic text-[11px]">Escribe un nuevo nombre de empresa...</div>
                            @endforelse
                        </div>
                        @error('companyName') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                    </div>

                    <!-- Row 2.5: Location / Sede (Searchable Dropdown Menu) -->
                    <div class="relative" 
                         x-data="{ 
                             open: false,
                             selectLoc(loc) {
                                 $wire.set('locationName', loc);
                                 this.open = false;
                             }
                         }"
                         x-dropdown-nav>
                        <label class="block font-medium text-zinc-700 mb-1 flex items-center gap-1">
                            <x-lucide-map-pin class="w-3 h-3 text-rose-500" />
                            <span>Locación / Sede <span class="text-zinc-400 font-normal">(Opcional)</span></span>
                        </label>
                        <div class="relative">
                            <input 
                                type="text" 
                                wire:model.live="locationName" 
                                @focus="open = true"
                                @click.outside="open = false"
                                autocomplete="off"
                                placeholder="Ej. TALPA 8, SUCURSAL CENTRO..." 
                                class="w-full bg-[#fbfbfa] border border-[#e9e9e7] rounded-md px-3 py-1.5 text-zinc-800 uppercase focus:border-stone-400 focus:outline-none font-semibold text-emerald-700 pr-7">
                            
                            <button 
                                type="button" 
                                @click="open = !open" 
                                class="absolute right-2 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-600 p-0.5"
                                title="Ver locaciones disponibles">
                                <x-lucide-chevron-down class="w-3.5 h-3.5" />
                            </button>
                        </div>

                        <div 
                            x-show="open" 
                            x-cloak
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            class="absolute left-0 right-0 top-full mt-1 z-50 bg-white border border-[#e9e9e7] rounded-lg shadow-2xl max-h-48 overflow-y-auto divide-y divide-stone-100 text-xs"
                            style="display: none;">
                            
                            @if(!empty($clientLocations))
                                <div class="px-2.5 py-1 bg-emerald-50/80 border-b border-emerald-100 font-bold text-[10px] uppercase text-emerald-800 flex items-center justify-between">
                                    <span>Locaciones del cliente</span>
                                    <x-lucide-map-pin class="w-3 h-3 text-rose-500" />
                                </div>
                                @foreach($clientLocations as $cLoc)
                                    <button 
                                        type="button"
                                        x-show="!$wire.locationName || '{{ strtolower(addslashes($cLoc)) }}'.includes(($wire.locationName || '').toLowerCase())"
                                        @click="selectLoc('{{ addslashes($cLoc) }}')" 
                                        class="w-full text-left p-2 hover:bg-emerald-50 focus:bg-emerald-50 focus:outline-none cursor-pointer font-bold text-zinc-900 uppercase transition flex items-center justify-between">
                                        <span>{{ $cLoc }}</span>
                                        <span class="text-[10px] text-emerald-600 font-medium normal-case">(Registrada)</span>
                                    </button>
                                @endforeach
                            @endif

                            @php
                                $otherLocations = array_diff($existingLocations->toArray(), $clientLocations ?? []);
                            @endphp

                            @if(!empty($otherLocations))
                                @if(!empty($clientLocations))
                                    <div class="px-2.5 py-1 bg-stone-50 border-b border-stone-100 font-bold text-[10px] uppercase text-zinc-400">
                                        Otras locaciones
                                    </div>
                                @endif
                                @foreach($otherLocations as $loc)
                                    <button 
                                        type="button"
                                        x-show="!$wire.locationName || '{{ strtolower(addslashes($loc)) }}'.includes(($wire.locationName || '').toLowerCase())"
                                        @click="selectLoc('{{ addslashes($loc) }}')" 
                                        class="w-full text-left p-2 hover:bg-stone-100 focus:bg-stone-100 focus:outline-none cursor-pointer font-semibold text-zinc-800 uppercase transition">
                                        {{ $loc }}
                                    </button>
                                @endforeach
                            @endif

                            @if(empty($clientLocations) && empty($otherLocations))
                                <div class="p-2.5 text-zinc-400 italic text-[11px]">Escribe una nueva locación...</div>
                            @endif
                        </div>
                    </div>

                    <!-- Row 3: Task Description (Required) -->
                    <div>
                        <label class="block font-medium text-zinc-700 mb-1 flex items-center gap-1">
                            <x-lucide-briefcase class="w-3 h-3 text-zinc-400" />
                            <span>Tarea / Descripción Trabajo <span class="text-red-500">*</span></span>
                        </label>
                        <input type="text" wire:model="taskName" placeholder="Ej. MENÚ EXTERIOR ACRÍLICO & ROTULACIÓN" class="w-full bg-[#fbfbfa] border border-[#e9e9e7] rounded-md px-3 py-1.5 text-zinc-800 uppercase focus:border-stone-400 focus:outline-none" x-on:blur="$event.target.value = $event.target.value.toUpperCase()">
                        @error('taskName') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                    </div>

                    <!-- Row 4: Substatus & Designer -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 items-start">
                        <!-- Left Column: Condición / Subestado Pills -->
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="block font-medium text-zinc-700 flex items-center gap-1">
                                    <x-lucide-tag class="w-3 h-3 text-zinc-400" />
                                    <span>Condición / Subestado <span class="text-zinc-400 font-normal">(Opcional)</span></span>
                                </label>
                                @if($substatus || !empty($flags))
                                    <button 
                                        type="button" 
                                        wire:click="selectSubstatus('')" 
                                        class="text-[10px] text-zinc-400 hover:text-red-500 cursor-pointer font-medium transition"
                                        title="Quitar subestados y banderas">
                                        Limpiar
                                    </button>
                                @endif
                            </div>
                            <div class="flex flex-wrap items-center gap-1.5 pt-1">
                                @forelse($substatuses as $sub)
                                    @php
                                        $subVal = $sub instanceof \App\Models\Substatus ? $sub->name : ($sub->value ?? (string) $sub);
                                        $isGlobal = $sub instanceof \App\Models\Substatus ? (bool) $sub->is_global : ($sub instanceof \App\Enums\Substatus ? $sub->isGlobal() : false);
                                        $isSelected = $isGlobal ? in_array($subVal, $flags ?? [], true) : ($substatus === $subVal);

                                        $subModel = $sub instanceof \App\Models\Substatus ? $sub : \App\Models\Substatus::where('name', $subVal)->first();
                                        $bgColor = $subModel?->bg_color ?? '#f4f4f5';
                                        $textColor = $subModel?->text_color ?? '#27272a';
                                        $borderColor = $subModel?->border_color ?: $bgColor;

                                        $pillStyle = "background-color: {$bgColor}; color: {$textColor}; border-color: {$borderColor};";
                                    @endphp
                                    <button 
                                        type="button"
                                        wire:click="selectSubstatus('{{ addslashes($subVal) }}')"
                                        class="px-2.5 py-1 rounded-md text-[11px] border transition-all flex items-center gap-1 cursor-pointer select-none {{ $isSelected ? 'font-bold shadow-xs scale-[1.02] ring-2 ring-stone-900/20' : 'font-medium opacity-60 hover:opacity-95 hover:scale-[1.01]' }}"
                                        style="{{ $pillStyle }}"
                                        title="{{ $subVal }}"
                                    >
                                        <span>{{ $subVal }}</span>
                                        @if($isSelected)
                                            <x-lucide-check class="w-3 h-3 text-current stroke-[2.5]" />
                                        @endif
                                    </button>
                                @empty
                                    <span class="text-zinc-400 italic text-[11px]">No hay subestados disponibles para este estado</span>
                                @endforelse
                            </div>
                        </div>

                        <!-- Right Column: Diseñadores Asignados -->
                        <div>
                            <label class="block font-medium text-zinc-700 mb-1 flex items-center gap-1">
                                <x-lucide-user class="w-3 h-3 text-zinc-400" />
                                <span>Diseñadores Asignados</span>
                            </label>

                            @if($mostAvailableDesigner)
                                <button 
                                    type="button"
                                    wire:click="toggleDesigner({{ $mostAvailableDesigner->id }})"
                                    class="mb-1.5 w-full flex items-center gap-1.5 px-2.5 py-1 rounded bg-emerald-50/80 hover:bg-emerald-100/90 border border-emerald-200/60 text-[11px] text-emerald-900 transition cursor-pointer select-none group text-left"
                                    title="Clic para seleccionar a {{ $mostAvailableDesigner->name }}"
                                >
                                    <span class="relative flex h-2 w-2 shrink-0">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                                    </span>
                                    <span class="text-emerald-700/80 text-[10.5px]">Más disponible esta semana:</span>
                                    <span class="font-semibold text-emerald-950 flex items-center gap-1 truncate group-hover:underline">
                                        <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ $mostAvailableDesigner->dot_color_class }}" style="{{ $mostAvailableDesigner->dot_inline_style }}"></span>
                                        <span>{{ $mostAvailableDesigner->name }}</span>
                                    </span>
                                </button>
                            @endif

                            <div class="flex flex-wrap items-center gap-1.5 p-2 bg-[#fbfbfa] border border-[#e9e9e7] rounded-md min-h-[38px]">
                                @foreach($designers as $designer)
                                    @php $isAssigned = in_array((int)$designer->id, array_map('intval', $designerIds)); @endphp
                                    <button 
                                        type="button"
                                        wire:click="toggleDesigner({{ $designer->id }})"
                                        class="px-2 py-0.5 rounded text-[11px] font-semibold border transition flex items-center gap-1 cursor-pointer {{ $isAssigned ? $designer->badge_style : 'bg-white text-zinc-500 border-stone-200 hover:bg-stone-100' }}"
                                        style="{{ $isAssigned ? $designer->badge_inline_style : '' }}"
                                    >
                                        <span class="w-2 h-2 rounded-full {{ $designer->dot_color_class }}" style="{{ $designer->dot_inline_style }}"></span>
                                        <span>{{ $designer->name }}</span>
                                        @if($isAssigned)
                                            <x-lucide-check class="w-3 h-3 text-current stroke-[3]" />
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                            <span class="text-[10px] text-zinc-400 block mt-1">* Si seleccionas Diseñador Externo, se agregará Euralíz automáticamente.</span>
                        </div>
                    </div>

                    <!-- Row 5: Fecha Límite (SLA) Full Width -->
                    <div class="p-3 bg-stone-50/80 border border-[#e9e9e7] rounded-lg space-y-2.5">
                        <div class="flex items-center justify-between">
                            <label class="font-medium text-zinc-700 flex items-center gap-1.5">
                                <x-lucide-calendar class="w-3.5 h-3.5 text-indigo-600" />
                                <span class="font-semibold text-zinc-800">Fecha Límite (SLA)</span>
                            </label>
                            @if($dueDate)
                                <div class="flex items-center gap-2">
                                    <span class="text-[11px] text-zinc-500">
                                        Entrega: <strong class="text-zinc-800 font-semibold capitalize">{{ \Carbon\Carbon::parse($dueDate)->locale('es')->isoFormat('dddd, D [de] MMMM') }}</strong>
                                    </span>
                                    <button 
                                        type="button" 
                                        wire:click="$set('dueDate', '')" 
                                        class="text-[10px] text-zinc-400 hover:text-red-500 transition cursor-pointer font-medium"
                                        title="Borrar fecha">
                                        Quitar
                                    </button>
                                </div>
                            @else
                                <span class="text-[11px] text-zinc-400 italic">Sin fecha asignada</span>
                            @endif
                        </div>

                        <div class="grid grid-cols-3 gap-2.5 items-center">
                            @php
                                $todayDate = now()->toDateString();
                                $tomorrowDate = now()->addDay()->toDateString();
                                $isToday = ($dueDate === $todayDate);
                                $isTomorrow = ($dueDate === $tomorrowDate);
                            @endphp

                            <!-- Hoy -->
                            <button 
                                type="button" 
                                wire:click="setDueDatePreset('today')"
                                class="w-full py-1.5 px-3 rounded-md text-xs transition cursor-pointer border text-center select-none {{ $isToday ? 'bg-indigo-600 text-white border-indigo-600 shadow-2xs font-semibold' : 'bg-white text-zinc-700 border-[#e9e9e7] hover:bg-stone-100 hover:border-stone-300 font-medium' }}">
                                Hoy
                            </button>

                            <!-- Mañana -->
                            <button 
                                type="button" 
                                wire:click="setDueDatePreset('tomorrow')"
                                class="w-full py-1.5 px-3 rounded-md text-xs transition cursor-pointer border text-center select-none {{ $isTomorrow ? 'bg-indigo-600 text-white border-indigo-600 shadow-2xs font-semibold' : 'bg-white text-zinc-700 border-[#e9e9e7] hover:bg-stone-100 hover:border-stone-300 font-medium' }}">
                                Mañana
                            </button>

                            <!-- Exact Date Picker Input -->
                            <div class="relative w-full">
                                <input 
                                    type="date" 
                                    wire:model.live="dueDate" 
                                    class="w-full bg-white border border-[#e9e9e7] rounded-md px-3 py-1.5 text-zinc-800 focus:border-stone-400 focus:outline-none font-mono text-xs font-semibold shadow-2xs cursor-pointer">
                            </div>
                        </div>
                        @error('dueDate') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                    </div>

                    <!-- Footer Buttons -->
                    <div class="pt-4 border-t border-[#e9e9e7] flex items-center justify-end gap-2">
                        <button type="button" @click="confirmClose(() => $wire.closeModal())" class="px-3.5 py-1.5 rounded-lg border border-stone-200 text-zinc-600 hover:bg-stone-100 transition font-medium cursor-pointer">
                            Cancelar
                        </button>
                        <button 
                            type="submit" 
                            :disabled="!isDirty()"
                            :class="isDirty() ? 'bg-emerald-600 hover:bg-emerald-700 text-white cursor-pointer shadow-sm shadow-emerald-600/20' : 'bg-stone-200 text-stone-400 border border-stone-200 cursor-not-allowed'"
                            class="px-4 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1.5"
                        >
                            @if($isDuplicating)
                                <x-lucide-copy class="w-3.5 h-3.5" />
                                <span>Crear Copia</span>
                            @else
                                <x-lucide-plus class="w-3.5 h-3.5" />
                                <span>Crear Orden</span>
                            @endif
                        </button>
                    </div>

                </form>
            </div>
        </div>
    @endif

    <!-- ON HOLD REASON MODAL -->
    @if($showOnHoldModal)
        <div 
            class="fixed inset-0 z-[110] bg-black/40 backdrop-blur-xs flex items-center justify-center p-4" 
            @keydown.window.escape.prevent="$wire.closeOnHoldModal()"
            @keydown.window.enter.prevent="if($event.target.tagName !== 'TEXTAREA') $wire.confirmOnHold()"
            wire:keydown.escape="closeOnHoldModal">
            <div class="bg-white border border-[#e9e9e7] rounded-xl shadow-2xl max-w-md w-full p-5 space-y-4 animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-start justify-between border-b border-[#e9e9e7] pb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-amber-50 border border-amber-200 flex items-center justify-center text-amber-600 shrink-0">
                            <x-lucide-pause-circle class="w-4 h-4" />
                        </div>
                        <div>
                            <h3 class="font-bold text-sm text-zinc-900">{{ __('Motivo para Poner en On Hold') }}</h3>
                            <p class="text-xs text-zinc-500 uppercase">{{ $companyName ?: __('Nueva Orden') }} &mdash; {{ $taskName ?: '' }}</p>
                        </div>
                    </div>
                    <button wire:click="closeOnHoldModal" type="button" class="text-zinc-400 hover:text-zinc-600 transition">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                </div>

                <div class="space-y-1.5 text-xs">
                    <label class="font-medium text-zinc-700 block">{{ __('Motivo / Comentario:') }}</label>
                    <textarea wire:model="onHoldReason" rows="3" placeholder="Ej: Esperando confirmación de presupuesto por parte del cliente..." class="w-full bg-[#fbfbfa] border border-[#e9e9e7] rounded-lg p-2.5 text-xs text-zinc-900 focus:outline-none focus:border-stone-400"></textarea>
                    @error('onHoldReason')
                        <span class="text-red-600 text-[11px] block mt-0.5">{{ $message }}</span>
                    @enderror
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-2 border-t border-[#e9e9e7]">
                    <button wire:click="closeOnHoldModal" type="button" class="px-3 py-1.5 rounded-lg border border-stone-200 bg-stone-50 hover:bg-stone-100 text-xs font-medium text-zinc-700 transition">
                        {{ __('Cancelar') }}
                    </button>
                    <button wire:click="confirmOnHold" wire:loading.attr="disabled" type="button" class="px-3.5 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-500 text-white font-medium text-xs shadow-2xs transition flex items-center gap-1 cursor-pointer">
                        <x-lucide-check-circle-2 class="w-3.5 h-3.5" />
                        <span>{{ __('Poner en On Hold') }}</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
