<div>
    @if($showModal)
        <div @click.self="confirmClose(() => $wire.closeModal())" class="fixed inset-0 z-[100] overflow-y-auto bg-stone-900/40 backdrop-blur-xs flex items-center justify-center p-4">
            <div 
                x-data="{
                    initialForm: null,
                    init() {
                        this.snapshot();
                        this.$watch('$wire.showModal', (show) => {
                            if (!show) {
                                window.KudosDirtyGuard.unregister('create-order-modal');
                            } else {
                                this.snapshot();
                                window.KudosDirtyGuard.register('create-order-modal', () => this.isDirty(), this.$el);
                            }
                        });
                        window.KudosDirtyGuard.register('create-order-modal', () => this.isDirty(), this.$el);
                        this.$cleanup(() => window.KudosDirtyGuard.unregister('create-order-modal'));
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
                                    action();
                                },
                                onSave: () => {
                                    window.KudosDirtyGuard.unregister('create-order-modal');
                                    $wire.save();
                                }
                            });
                        } else {
                            action();
                        }
                    }
                }"
                @keydown.window.escape="confirmClose(() => $wire.closeModal())"
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
                            <label class="font-medium text-zinc-700 mb-1 flex items-center gap-1">
                                <x-lucide-hash class="w-3 h-3 text-zinc-400" />
                                <span>WO (Opcional)</span>
                            </label>
                            <div class="flex rounded-md shadow-2xs">
                                <span class="inline-flex items-center px-2 rounded-l-md border border-r-0 border-[#e9e9e7] bg-stone-100 text-zinc-600 font-mono font-bold text-xs select-none">
                                    WO
                                </span>
                                <input type="text" wire:model="woNumber" placeholder="16350" class="w-full bg-[#fbfbfa] border border-[#e9e9e7] rounded-r-md px-2.5 py-1.5 text-zinc-800 focus:border-stone-400 focus:outline-none font-mono font-semibold">
                            </div>
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
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                class="absolute left-0 right-0 top-full mt-1 z-50 bg-white border border-[#e9e9e7] rounded-lg shadow-2xl max-h-64 overflow-y-auto divide-y divide-stone-100 text-xs min-w-[260px]">
                                
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
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                class="absolute left-0 right-0 top-full mt-1 z-50 bg-white border border-[#e9e9e7] rounded-lg shadow-2xl max-h-60 overflow-y-auto divide-y divide-stone-100 text-xs">
                                
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
                                class="w-full bg-[#fbfbfa] border border-[#e9e9e7] rounded-md px-3 py-1.5 text-zinc-800 focus:border-stone-400 focus:outline-none font-semibold pr-7">
                            
                            <button 
                                type="button" 
                                @click="open = !open" 
                                class="absolute right-2 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-600 p-0.5">
                                <x-lucide-chevron-down class="w-3.5 h-3.5" />
                            </button>
                        </div>

                        <div 
                            x-show="open" 
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            class="absolute left-0 right-0 top-full mt-1 z-50 bg-white border border-[#e9e9e7] rounded-lg shadow-2xl max-h-60 overflow-y-auto divide-y divide-stone-100 text-xs">
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
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            class="absolute left-0 right-0 top-full mt-1 z-50 bg-white border border-[#e9e9e7] rounded-lg shadow-2xl max-h-48 overflow-y-auto divide-y divide-stone-100 text-xs">
                            
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
                        <input type="text" wire:model="taskName" placeholder="Ej. Menú Exterior Acrílico & Rotulación" class="w-full bg-[#fbfbfa] border border-[#e9e9e7] rounded-md px-3 py-1.5 text-zinc-800 focus:border-stone-400 focus:outline-none">
                        @error('taskName') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                    </div>

                    <!-- Row 4: Column / Status & Designer -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div class="relative" 
                             x-data="{ 
                                 open: false,
                                 selectStatus(val) {
                                     $wire.set('coreStatus', val);
                                     this.open = false;
                                 }
                             }"
                             x-dropdown-nav>
                            <label class="block font-medium text-zinc-700 mb-1 flex items-center gap-1">
                                <x-lucide-layers class="w-3 h-3 text-zinc-400" />
                                <span>Lista / Estado Kanban</span>
                            </label>
                            <div class="relative">
                                <button 
                                    type="button" 
                                    @click="open = !open" 
                                    @click.outside="open = false"
                                    class="w-full bg-[#fbfbfa] border border-[#e9e9e7] hover:border-stone-400 rounded-md px-3 py-1.5 text-zinc-800 focus:outline-none text-left flex items-center justify-between font-medium">
                                    <span>{{ \App\Enums\CoreStatus::tryFrom($coreStatus)?->label() ?? 'Seleccionar estado...' }}</span>
                                    <x-lucide-chevron-down class="w-3.5 h-3.5 text-zinc-400" />
                                </button>
                            </div>

                            <div 
                                x-show="open" 
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                class="absolute left-0 right-0 top-full mt-1 z-50 bg-white border border-[#e9e9e7] rounded-lg shadow-2xl max-h-60 overflow-y-auto divide-y divide-stone-100 text-xs">
                                @foreach($coreStatuses as $status)
                                    <button 
                                        type="button"
                                        @click="selectStatus('{{ $status->value }}')" 
                                        class="w-full text-left p-2 hover:bg-stone-100 focus:bg-stone-100 focus:outline-none cursor-pointer font-medium text-zinc-800 transition flex items-center justify-between">
                                        <span>{{ $status->label() }}</span>
                                        @if($coreStatus === $status->value)
                                            <x-lucide-check class="w-3.5 h-3.5 text-emerald-600 stroke-[3]" />
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <label class="block font-medium text-zinc-700 mb-1 flex items-center gap-1">
                                <x-lucide-user class="w-3 h-3 text-zinc-400" />
                                <span>Diseñadores Asignados</span>
                            </label>
                            <div class="flex flex-wrap items-center gap-1.5 p-2 bg-[#fbfbfa] border border-[#e9e9e7] rounded-md min-h-[38px]">
                                @foreach($designers as $designer)
                                    @php $isAssigned = in_array((int)$designer->id, array_map('intval', $designerIds)); @endphp
                                    <button 
                                        type="button"
                                        wire:click="toggleDesigner({{ $designer->id }})"
                                        class="px-2 py-0.5 rounded text-[11px] font-semibold border transition flex items-center gap-1 cursor-pointer {{ $isAssigned ? $designer->badge_style : 'bg-white text-zinc-500 border-stone-200 hover:bg-stone-100' }}"
                                    >
                                        <span class="w-2 h-2 rounded-full {{ $designer->dot_color_class }}"></span>
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

                    <!-- Row 5: Substatus & Due Date -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <!-- Substatus (Custom Searchable Combobox with Color Badges) -->
                        <div class="relative" 
                             x-data="{ 
                                 open: false,
                                 selectSub(val) {
                                     $wire.set('substatus', val);
                                     this.open = false;
                                 }
                             }"
                             x-dropdown-nav>
                            <label class="block font-medium text-zinc-700 mb-1 flex items-center gap-1">
                                <x-lucide-tag class="w-3 h-3 text-zinc-400" />
                                <span>Condición / Subestado (Opcional)</span>
                            </label>
                            <div class="relative">
                                <button 
                                    type="button" 
                                    @click="open = !open" 
                                    @click.outside="open = false"
                                    class="w-full bg-[#fbfbfa] border border-[#e9e9e7] hover:border-stone-400 rounded-md px-3 py-1.5 text-zinc-800 focus:outline-none text-left flex items-center justify-between font-medium">
                                    @if($substatus)
                                        @php $subEnum = \App\Enums\Substatus::tryFrom($substatus); @endphp
                                        <span class="px-2 py-0.5 rounded text-[11px] font-medium border {{ $subEnum ? $subEnum->badgeStyle() : 'bg-stone-100 text-stone-700 border-stone-200' }}">
                                            {{ $substatus }}
                                        </span>
                                    @else
                                        <span class="text-zinc-500 italic">Ninguno</span>
                                    @endif
                                    <x-lucide-chevron-down class="w-3.5 h-3.5 text-zinc-400" />
                                </button>
                            </div>

                            <div 
                                x-show="open" 
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                class="absolute left-0 right-0 top-full mt-1 z-50 bg-white border border-[#e9e9e7] rounded-lg shadow-2xl max-h-64 overflow-y-auto divide-y divide-stone-100 text-xs">
                                <button 
                                    type="button"
                                    @click="selectSub('')" 
                                    class="w-full text-left p-2.5 hover:bg-stone-100 focus:bg-stone-100 focus:outline-none cursor-pointer text-zinc-500 italic transition flex items-center justify-between">
                                    <span>Ninguno</span>
                                    @if(!$substatus)
                                        <x-lucide-check class="w-3.5 h-3.5 text-emerald-600 stroke-[3]" />
                                    @endif
                                </button>
                                @foreach($substatuses as $sub)
                                    <button 
                                        type="button"
                                        @click="selectSub('{{ $sub->value }}')" 
                                        class="w-full text-left p-2 hover:bg-stone-100 focus:bg-stone-100 focus:outline-none cursor-pointer transition flex items-center justify-between">
                                        <span class="px-2 py-0.5 rounded text-[11px] font-medium border {{ $sub->badgeStyle() }}">
                                            {{ $sub->value }}
                                        </span>
                                        @if($substatus === $sub->value)
                                            <x-lucide-check class="w-3.5 h-3.5 text-emerald-600 stroke-[3]" />
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <label class="block font-medium text-zinc-700 mb-1 flex items-center gap-1">
                                <x-lucide-calendar class="w-3 h-3 text-zinc-400" />
                                <span>Fecha Límite (SLA)</span>
                            </label>
                            <input type="date" wire:model="dueDate" class="w-full bg-[#fbfbfa] border border-[#e9e9e7] rounded-md px-3 py-1.5 text-zinc-800 focus:border-stone-400 focus:outline-none font-mono">
                        </div>
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
</div>
