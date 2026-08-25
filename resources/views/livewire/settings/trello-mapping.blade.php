<div 
    x-data="{
        initialMappings: null,
        init() {
            this.snapshot();
            Livewire.on('mappings-saved', () => this.snapshot());
            window.KudosDirtyGuard.register('trello-mapping', () => this.isDirty());
        },
        snapshot() {
            this.initialMappings = JSON.stringify($wire.mappings || {});
        },
        isDirty() {
            return JSON.stringify($wire.mappings || {}) !== this.initialMappings;
        }
    }"
    class="h-full flex flex-col space-y-4 min-h-0 overflow-y-auto custom-vertical-scrollbar pr-1">

    <div x-show="isDirty()" x-transition class="bg-amber-50 border border-amber-200 text-amber-900 p-3 rounded-xl text-xs font-semibold flex items-center justify-between shadow-2xs">
        <div class="flex items-center gap-2">
            <x-lucide-alert-triangle class="w-4 h-4 text-amber-600 shrink-0" />
            <span>{{ __('Tienes asignaciones de listas sin guardar. Recuerda hacer clic en "Guardar Mapeo".') }}</span>
        </div>
        <button wire:click="saveMappings" @click="snapshot()" class="px-3 py-1 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-xs font-bold transition shadow-xs cursor-pointer">
            {{ __('Guardar Mapeo Ahora') }}
        </button>
    </div>
    
    <!-- Top Notch Notion Header -->
    <div class="bg-white border border-[#e9e9e7] rounded-xl p-4 flex flex-col md:flex-row items-center justify-between gap-4 shadow-2xs shrink-0">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-purple-50 text-purple-700 border border-purple-200 flex items-center justify-center font-bold shadow-2xs">
                <x-lucide-sliders class="w-5 h-5 text-purple-600" />
            </div>
            <div>
                <h2 class="text-sm font-semibold text-zinc-900 tracking-tight">{{ __('Mapeo de Listas Trello') }}</h2>
                <p class="text-xs text-zinc-500">{{ __('Relaciona cada columna de estatus local con la lista correspondiente en tu tablero de Trello.') }}</p>
            </div>
        </div>

        <div class="flex items-center gap-2.5">
            <!-- Auto Detect Mappings Button -->
            <button 
                wire:click="autoDetectMappings" 
                class="px-3 py-1.5 rounded-md bg-stone-100 hover:bg-stone-200 text-stone-700 border border-stone-200 font-medium text-xs transition flex items-center gap-1.5 cursor-pointer"
                title="{{ __('Detectar coincidencias basadas en el nombre de la lista') }}">
                <x-lucide-sparkles class="w-3.5 h-3.5 text-stone-600" />
                <span>{{ __('Auto-detectar') }}</span>
            </button>

            <!-- Save Mappings Button -->
            <button 
                wire:click="saveMappings" 
                @click="snapshot()"
                :disabled="!isDirty()"
                :class="isDirty() ? 'bg-emerald-600 hover:bg-emerald-700 text-white cursor-pointer shadow-sm shadow-emerald-600/20' : 'bg-stone-200 text-stone-400 border border-stone-200 cursor-not-allowed'"
                class="px-3.5 py-1.5 rounded-md font-semibold text-xs transition flex items-center gap-2">
                <x-lucide-save class="w-3.5 h-3.5" />
                <span>{{ __('Guardar Mapeo') }}</span>
            </button>
        </div>
    </div>

    @if (session()->has('message'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-3 rounded-lg text-xs font-medium flex items-center gap-2">
            <x-lucide-check-circle-2 class="w-4 h-4 text-emerald-600 shrink-0" />
            <span class="truncate">{{ session('message') }}</span>
        </div>
    @endif

    <!-- Main Mappings Table Card -->
    <div class="bg-white border border-[#e9e9e7] rounded-xl p-4 space-y-4 shadow-2xs flex-1">
        <div class="flex items-center justify-between border-b border-[#e9e9e7] pb-3">
            <h3 class="font-semibold text-xs text-zinc-800 uppercase tracking-wider flex items-center gap-2">
                <x-lucide-arrow-right-left class="w-4 h-4 text-zinc-500" /> {{ __('Asignación de Columnas') }}
            </h3>
            
            <span class="text-[11px] text-zinc-500 font-mono">
                {{ __('Total Listas Trello Obtenidas:') }} <strong>{{ count($trelloLists) }}</strong>
            </span>
        </div>

        <div class="space-y-3">
            @foreach($statuses as $status)
                @php
                    $val = $status->value;
                    $selectedListId = $mappings[$val] ?? '';
                    $selectedList = collect($trelloLists)->firstWhere('id', $selectedListId);
                    $selectedListName = $selectedList['name'] ?? '';
                @endphp
                <div class="p-3.5 bg-[#fbfbfa] hover:bg-stone-50 rounded-xl border border-stone-200/90 flex flex-col md:flex-row md:items-center justify-between gap-4 transition">
                    
                    <!-- Left: Core Status Name & Color Badge -->
                    <div class="flex items-center gap-3 min-w-0 flex-1">
                        <div class="w-3.5 h-3.5 rounded-full bg-{{ $status->color() }}-500 shrink-0 shadow-2xs"></div>
                        <div class="min-w-0">
                            <span class="font-bold text-xs text-zinc-900 block truncate">{{ $status->label() }}</span>
                            <span class="font-mono text-[10px] text-zinc-400 block truncate">{{ __('Estatus Local:') }} {{ $val }}</span>
                        </div>
                    </div>

                    <!-- Middle: Mapping Arrow -->
                    <div class="hidden md:flex items-center gap-1.5 text-zinc-400 text-xs shrink-0">
                        <span class="text-[10px] font-mono text-zinc-400 uppercase">{{ __('Mapea a') }}</span>
                        <x-lucide-arrow-right class="w-4 h-4 text-zinc-400" />
                    </div>

                    <!-- Right: Searchable Dropdown List (Combobox) -->
                    <div 
                        x-data="{ 
                            open: false, 
                            search: '',
                            selectedId: '{{ $selectedListId }}',
                            selectedName: '{{ addslashes($selectedListName) }}',
                            selectList(id, name) {
                                this.selectedId = id;
                                this.selectedName = name;
                                $wire.setMapping('{{ $val }}', id);
                                this.open = false;
                                this.search = '';
                            }
                        }"
                        @click.outside="open = false"
                        x-dropdown-nav
                        class="relative w-full md:w-96 shrink-0">
                        
                        <!-- Search / Display Input Button -->
                        <div class="relative flex items-center">
                            <input 
                                type="text" 
                                x-model="search"
                                @focus="open = true"
                                @click="open = true"
                                autocomplete="off"
                                :placeholder="selectedName ? selectedName : @js(__('-- Seleccionar o Buscar Lista Trello --'))" 
                                class="w-full rounded-lg border border-[#e9e9e7] bg-white pl-3 pr-8 py-2 text-xs text-zinc-900 focus:outline-none focus:ring-2 focus:ring-zinc-900/10 font-medium shadow-2xs placeholder:text-zinc-900 placeholder:font-semibold">

                            <button 
                                type="button" 
                                @click="open = !open" 
                                class="absolute right-2 text-zinc-400 hover:text-zinc-600 p-1 cursor-pointer">
                                <x-lucide-chevron-down class="w-3.5 h-3.5 transition-transform duration-150" x-bind:class="open ? 'rotate-180' : ''" />
                            </button>
                        </div>

                        <!-- Searchable Dropdown Popup Menu -->
                        <div 
                            x-show="open" 
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            class="absolute left-0 right-0 top-full mt-1 z-50 bg-white border border-[#e9e9e7] rounded-xl shadow-2xl max-h-60 overflow-y-auto divide-y divide-stone-100 text-xs">
                            
                            <button 
                                type="button"
                                @click="selectList('', '')"
                                class="w-full text-left p-2.5 hover:bg-stone-100 focus:bg-stone-100 focus:outline-none cursor-pointer text-zinc-500 italic transition flex items-center justify-between">
                                <span>{{ __('-- Sin asignar (Detección por nombre) --') }}</span>
                                <template x-if="selectedId === ''">
                                    <x-lucide-check class="w-3.5 h-3.5 text-emerald-600" />
                                </template>
                            </button>

                            @foreach($trelloLists as $tList)
                                <button 
                                    type="button"
                                    x-show="!search || '{{ strtolower(addslashes($tList['name'])) }}'.includes(search.toLowerCase())"
                                    @click="selectList('{{ $tList['id'] }}', '{{ addslashes($tList['name']) }}')"
                                    class="w-full text-left p-2.5 hover:bg-purple-50 focus:bg-purple-50 focus:outline-none cursor-pointer flex items-center justify-between gap-2 transition"
                                    :class="selectedId === '{{ $tList['id'] }}' ? 'bg-purple-50/80 font-bold text-purple-900' : 'text-zinc-800'">
                                    <span class="truncate">{{ $tList['name'] }}</span>
                                    <template x-if="selectedId === '{{ $tList['id'] }}'">
                                        <x-lucide-check class="w-3.5 h-3.5 text-purple-600 shrink-0" />
                                    </template>
                                </button>
                            @endforeach
                        </div>
                    </div>

                </div>
            @endforeach
        </div>

        <div class="pt-3 border-t border-stone-100 flex justify-end">
            <button 
                wire:click="saveMappings" 
                @click="snapshot()"
                :disabled="!isDirty()"
                :class="isDirty() ? 'bg-emerald-600 hover:bg-emerald-700 text-white cursor-pointer shadow-sm shadow-emerald-600/20' : 'bg-stone-200 text-stone-400 border border-stone-200 cursor-not-allowed'"
                class="px-4 py-2 text-xs font-semibold rounded-xl transition flex items-center gap-1.5 font-bold">
                <x-lucide-save class="w-3.5 h-3.5" />
                <span>{{ __('Guardar Cambios de Mapeo') }}</span>
            </button>
        </div>
    </div>

</div>
