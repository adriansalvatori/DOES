<div class="h-full flex flex-col space-y-3 min-h-0">
    
    <!-- Unified Top Notion-Style Header Controls -->
    <div class="bg-white border border-[#e9e9e7] rounded-xl p-4 space-y-3.5 shadow-2xs shrink-0">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 min-w-0">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-9 h-9 rounded-lg bg-stone-900 text-white flex items-center justify-center shrink-0 shadow-2xs">
                    <x-lucide-box class="w-4.5 h-4.5 text-stone-100" />
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h1 class="text-base sm:text-lg font-bold text-zinc-900 tracking-tight">Backlog de Órdenes</h1>
                        <span class="px-2 py-0.5 rounded-full bg-stone-100 border border-stone-200 text-[10px] font-bold text-zinc-600">{{ $backlogTotalCount }} Tarjetas</span>
                    </div>
                    <p class="text-xs text-zinc-500 truncate mt-0.5">
                        Tarjetas fuera del Workspace activo. Selecciónalas y añádelas cuando requieras procesarlas.
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-3 shrink-0 self-end sm:self-auto">
                <div class="px-3 py-1.5 h-8 rounded-lg bg-stone-50 border border-stone-200 text-xs text-zinc-700 flex items-center gap-2">
                    <x-lucide-layers class="w-3.5 h-3.5 text-zinc-500" />
                    <span>Activas en Workspace: <strong class="text-zinc-900">{{ $activeWorkspaceCount }}</strong></span>
                </div>

                <button 
                    wire:click="runTrelloSync" 
                    wire:loading.attr="disabled"
                    class="px-3.5 py-1.5 h-8 rounded-lg bg-stone-900 hover:bg-stone-800 active:bg-black disabled:opacity-50 text-white font-medium text-xs shadow-2xs transition flex items-center gap-2 cursor-pointer shrink-0">
                    <x-lucide-refresh-cw wire:loading.class="animate-spin" wire:target="runTrelloSync" class="w-3.5 h-3.5 text-emerald-400 shrink-0" />
                    <span wire:loading.remove wire:target="runTrelloSync">{{ __('Sincronizar Trello') }}</span>
                    <span wire:loading wire:target="runTrelloSync">{{ __('Sincronizando...') }}</span>
                </button>
            </div>
        </div>

        <div class="pt-3 border-t border-[#f0f0ee] flex flex-wrap items-center justify-between gap-2 w-full">
            <div class="flex flex-wrap items-center gap-2 flex-1 min-w-0">
                <div class="relative flex-1 min-w-[200px] sm:min-w-[240px] max-w-sm">
                    <x-lucide-search class="w-3.5 h-3.5 text-zinc-400 absolute left-3 top-2.5 shrink-0" />
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Buscar en backlog..." class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-lg pl-8 pr-3 py-1.5 h-8 text-xs text-zinc-800 focus:border-stone-400 focus:outline-none w-full">
                </div>

                <!-- Company Filter (Searchable) -->
                <div class="relative flex-1 min-w-[140px] sm:flex-none" 
                     x-data="{ 
                         open: false,
                         search: '',
                         selectComp(val) {
                             $wire.set('companyFilter', val);
                             this.open = false;
                         }
                     }"
                     x-dropdown-nav>
                    <button 
                        type="button" 
                        @click="open = !open" 
                        @click.outside="open = false"
                        class="w-full bg-[#fbfbfa] hover:bg-white border border-[#e9e9e7] hover:border-stone-300 rounded-lg px-2.5 h-8 text-xs text-zinc-700 font-medium flex items-center justify-between gap-1 truncate transition shadow-2xs">
                        <span class="truncate">{{ $companyFilter === 'all' ? 'Empresas (Todas)' : $companyFilter }}</span>
                        <x-lucide-chevron-down class="w-3 h-3 text-zinc-400 shrink-0" />
                    </button>

                    <div 
                        x-show="open" 
                        x-transition:enter="transition ease-out duration-100"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        class="absolute left-0 top-full mt-1 z-50 bg-white border border-[#e9e9e7] rounded-lg shadow-xl w-52 max-h-56 overflow-y-auto divide-y divide-stone-100 text-xs">
                        <div class="p-1.5 sticky top-0 bg-white border-b border-stone-100">
                            <input 
                                type="text" 
                                x-model="search" 
                                placeholder="Buscar empresa..." 
                                class="w-full bg-stone-50 border border-stone-200 rounded px-2 py-1 text-[11px] text-zinc-800 focus:outline-none">
                        </div>
                        <button 
                            type="button"
                            @click="selectComp('all')" 
                            class="w-full text-left p-2 hover:bg-stone-100 focus:bg-stone-100 focus:outline-none cursor-pointer font-medium text-zinc-800 transition flex items-center justify-between">
                            <span>Empresas (Todas)</span>
                            @if($companyFilter === 'all')
                                <x-lucide-check class="w-3.5 h-3.5 text-emerald-600 stroke-[3]" />
                            @endif
                        </button>
                        @foreach($existingCompanies as $comp)
                            <button 
                                type="button"
                                x-show="!search || '{{ strtolower(addslashes($comp)) }}'.includes(search.toLowerCase())"
                                @click="selectComp('{{ addslashes($comp) }}')" 
                                class="w-full text-left p-2 hover:bg-stone-100 focus:bg-stone-100 focus:outline-none cursor-pointer font-medium text-zinc-800 transition flex items-center justify-between">
                                <span class="truncate">{{ $comp }}</span>
                                @if($companyFilter === $comp)
                                    <x-lucide-check class="w-3.5 h-3.5 text-emerald-600 stroke-[3] shrink-0" />
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>

                <!-- Responsible Filter (Searchable) -->
                <div class="relative flex-1 min-w-[140px] sm:flex-none" 
                     x-data="{ 
                         open: false,
                         search: '',
                         selectResp(val) {
                             $wire.set('responsibleFilter', val);
                             this.open = false;
                         }
                     }"
                     x-dropdown-nav>
                    <button 
                        type="button" 
                        @click="open = !open" 
                        @click.outside="open = false"
                        class="w-full bg-[#fbfbfa] hover:bg-white border border-[#e9e9e7] hover:border-stone-300 rounded-lg px-2.5 h-8 text-xs text-zinc-700 font-medium flex items-center justify-between gap-1 truncate transition shadow-2xs">
                        <span class="truncate">{{ $responsibleFilter === 'all' ? 'Responsables (Todos)' : $responsibleFilter }}</span>
                        <x-lucide-chevron-down class="w-3 h-3 text-zinc-400 shrink-0" />
                    </button>

                    <div 
                        x-show="open" 
                        x-transition:enter="transition ease-out duration-100"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        class="absolute left-0 top-full mt-1 z-50 bg-white border border-[#e9e9e7] rounded-lg shadow-xl w-48 max-h-56 overflow-y-auto divide-y divide-stone-100 text-xs">
                        <div class="p-1.5 sticky top-0 bg-white border-b border-stone-100">
                            <input 
                                type="text" 
                                x-model="search" 
                                placeholder="Buscar responsable..." 
                                class="w-full bg-stone-50 border border-stone-200 rounded px-2 py-1 text-[11px] text-zinc-800 focus:outline-none">
                        </div>
                        <button 
                            type="button"
                            @click="selectResp('all')" 
                            class="w-full text-left p-2 hover:bg-stone-100 focus:bg-stone-100 focus:outline-none cursor-pointer font-medium text-zinc-800 transition flex items-center justify-between">
                            <span>Responsables (Todos)</span>
                            @if($responsibleFilter === 'all')
                                <x-lucide-check class="w-3.5 h-3.5 text-emerald-600 stroke-[3]" />
                            @endif
                        </button>
                        @foreach($existingResponsibles as $resp)
                            <button 
                                type="button"
                                x-show="!search || '{{ strtolower(addslashes($resp)) }}'.includes(search.toLowerCase())"
                                @click="selectResp('{{ addslashes($resp) }}')" 
                                class="w-full text-left p-2 hover:bg-stone-100 focus:bg-stone-100 focus:outline-none cursor-pointer font-medium text-zinc-800 transition flex items-center justify-between">
                                <span class="truncate">{{ $resp }}</span>
                                @if($responsibleFilter === $resp)
                                    <x-lucide-check class="w-3.5 h-3.5 text-emerald-600 stroke-[3] shrink-0" />
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>

                <!-- Status Filter (Searchable) -->
                <div class="relative flex-1 min-w-[140px] sm:flex-none" 
                     x-data="{ 
                         open: false,
                         search: '',
                         selectStatus(val) {
                             $wire.set('statusFilter', val);
                             this.open = false;
                         }
                     }"
                     x-dropdown-nav>
                    <button 
                        type="button" 
                        @click="open = !open" 
                        @click.outside="open = false"
                        class="w-full bg-[#fbfbfa] hover:bg-white border border-[#e9e9e7] hover:border-stone-300 rounded-lg px-2.5 h-8 text-xs text-zinc-700 font-medium flex items-center justify-between gap-1 truncate transition shadow-2xs">
                        <span class="truncate">
                            @if($statusFilter === 'all')
                                Listas (Todas)
                            @else
                                {{ \App\Enums\CoreStatus::tryFrom($statusFilter)?->label() ?? 'Listas (Todas)' }}
                            @endif
                        </span>
                        <x-lucide-chevron-down class="w-3 h-3 text-zinc-400 shrink-0" />
                    </button>

                    <div 
                        x-show="open" 
                        x-transition:enter="transition ease-out duration-100"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        class="absolute left-0 top-full mt-1 z-50 bg-white border border-[#e9e9e7] rounded-lg shadow-xl w-48 max-h-56 overflow-y-auto divide-y divide-stone-100 text-xs">
                        <div class="p-1.5 sticky top-0 bg-white border-b border-stone-100">
                            <input 
                                type="text" 
                                x-model="search" 
                                placeholder="Buscar lista..." 
                                class="w-full bg-stone-50 border border-stone-200 rounded px-2 py-1 text-[11px] text-zinc-800 focus:outline-none">
                        </div>
                        <button 
                            type="button"
                            @click="selectStatus('all')" 
                            class="w-full text-left p-2 hover:bg-stone-100 focus:bg-stone-100 focus:outline-none cursor-pointer font-medium text-zinc-800 transition flex items-center justify-between">
                            <span>Listas (Todas)</span>
                            @if($statusFilter === 'all')
                                <x-lucide-check class="w-3.5 h-3.5 text-emerald-600 stroke-[3]" />
                            @endif
                        </button>
                        @foreach(\App\Enums\CoreStatus::cases() as $st)
                            <button 
                                type="button"
                                x-show="!search || '{{ strtolower(addslashes($st->label())) }}'.includes(search.toLowerCase())"
                                @click="selectStatus('{{ $st->value }}')" 
                                class="w-full text-left p-2 hover:bg-stone-100 focus:bg-stone-100 focus:outline-none cursor-pointer font-medium text-zinc-800 transition flex items-center justify-between">
                                <span class="truncate">{{ $st->label() }}</span>
                                @if($statusFilter === $st->value)
                                    <x-lucide-check class="w-3.5 h-3.5 text-emerald-600 stroke-[3] shrink-0" />
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>

                <!-- Designer Filter (Searchable) -->
                <div class="relative flex-1 min-w-[140px] sm:flex-none" 
                     x-data="{ 
                         open: false,
                         search: '',
                         selectDesigner(val) {
                             $wire.set('designerFilter', val);
                             this.open = false;
                         }
                     }"
                     x-dropdown-nav>
                    <button 
                        type="button" 
                        @click="open = !open" 
                        @click.outside="open = false"
                        class="w-full bg-[#fbfbfa] hover:bg-white border border-[#e9e9e7] hover:border-stone-300 rounded-lg px-2.5 h-8 text-xs text-zinc-700 font-medium flex items-center justify-between gap-1 truncate transition shadow-2xs">
                        <span class="truncate">
                            @if($designerFilter === 'all')
                                Diseñadores (Todos)
                            @else
                                {{ $designers->firstWhere('id', $designerFilter)?->name ?? 'Diseñadores (Todos)' }}
                            @endif
                        </span>
                        <x-lucide-chevron-down class="w-3 h-3 text-zinc-400 shrink-0" />
                    </button>

                    <div 
                        x-show="open" 
                        x-transition:enter="transition ease-out duration-100"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        class="absolute left-0 top-full mt-1 z-50 bg-white border border-[#e9e9e7] rounded-lg shadow-xl w-48 max-h-56 overflow-y-auto divide-y divide-stone-100 text-xs">
                        <div class="p-1.5 sticky top-0 bg-white border-b border-stone-100">
                            <input 
                                type="text" 
                                x-model="search" 
                                placeholder="Buscar diseñador..." 
                                class="w-full bg-stone-50 border border-stone-200 rounded px-2 py-1 text-[11px] text-zinc-800 focus:outline-none">
                        </div>
                        <button 
                            type="button"
                            @click="selectDesigner('all')" 
                            class="w-full text-left p-2 hover:bg-stone-100 focus:bg-stone-100 focus:outline-none cursor-pointer font-medium text-zinc-800 transition flex items-center justify-between">
                            <span>Diseñadores (Todos)</span>
                            @if($designerFilter === 'all')
                                <x-lucide-check class="w-3.5 h-3.5 text-emerald-600 stroke-[3]" />
                            @endif
                        </button>
                        @foreach($designers as $designer)
                            <button 
                                type="button"
                                x-show="!search || '{{ strtolower(addslashes($designer->name)) }}'.includes(search.toLowerCase())"
                                @click="selectDesigner('{{ $designer->id }}')" 
                                class="w-full text-left p-2 hover:bg-stone-100 focus:bg-stone-100 focus:outline-none cursor-pointer font-medium text-zinc-800 transition flex items-center justify-between">
                                <span class="truncate">{{ $designer->name }}</span>
                                @if((string)$designerFilter === (string)$designer->id)
                                    <x-lucide-check class="w-3.5 h-3.5 text-emerald-600 stroke-[3] shrink-0" />
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>

                <select wire:model.live="sortBy" class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-lg px-2.5 h-8 text-xs text-zinc-700 font-medium focus:border-stone-400 focus:outline-none flex-1 min-w-[170px] sm:flex-none">
                    <option value="trello_created_at_desc">Creación (Más Recientes)</option>
                    <option value="trello_created_at_asc">Creación (Más Antiguas)</option>
                    <option value="due_date_asc">Fecha Límite Próxima</option>
                    <option value="company_asc">Empresa (A-Z)</option>
                </select>
            </div>

            <div class="flex items-center gap-2 shrink-0 ml-auto">
                @if(count($selectedOrders) > 0)
                    <button wire:click="addSelectedToWorkspace" class="px-3.5 py-1.5 h-8 rounded-lg bg-stone-900 hover:bg-stone-800 text-white font-medium text-xs shadow-2xs transition flex items-center gap-1.5">
                        <x-lucide-plus-circle class="w-3.5 h-3.5 text-emerald-400" />
                        <span>Añadir Selección ({{ count($selectedOrders) }})</span>
                    </button>
                @endif

                <button wire:click="addAllFilteredToWorkspace" wire:confirm="¿Añadir todas las órdenes filtradas al Workspace activo?" class="px-3 py-1.5 h-8 rounded-lg bg-stone-100 hover:bg-stone-200 text-zinc-800 border border-stone-200 font-medium text-xs transition flex items-center gap-1.5">
                    <x-lucide-arrow-right-circle class="w-3.5 h-3.5 text-zinc-600" />
                    <span>Añadir Filtradas</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Animated Fullscreen Loading Overlay during Trello Sync -->
    <div wire:loading.flex wire:target="runTrelloSync" class="fixed inset-0 z-[100] bg-black/40 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl border border-stone-200 shadow-2xl p-6 max-w-sm w-full text-center space-y-4 animate-in fade-in zoom-in duration-150">
            <div class="relative w-16 h-16 mx-auto flex items-center justify-center">
                <div class="absolute inset-0 rounded-full border-4 border-stone-100 animate-ping opacity-75"></div>
                <div class="w-14 h-14 rounded-full bg-stone-900 text-white flex items-center justify-center shadow-lg">
                    <x-lucide-refresh-cw class="w-7 h-7 animate-spin text-emerald-400" />
                </div>
            </div>
            <div>
                <h3 class="font-bold text-sm text-zinc-900 tracking-tight">{{ __('Sincronizando con Trello...') }}</h3>
                <p class="text-xs text-zinc-500 mt-1 leading-relaxed">{{ __('Conectando a la API REST de Trello, analizando listas y actualizando tarjetas en tiempo real.') }}</p>
            </div>
        </div>
    </div>

    <!-- Interactive Sync Summary Report Modal -->
    @if($syncReport['show'])
        <div 
            class="fixed inset-0 z-[100] bg-black/40 backdrop-blur-xs flex items-center justify-center p-4"
            @keydown.window.escape.prevent="$wire.closeReportModal()"
            @keydown.window.enter.prevent="$wire.closeReportModal()">
            <div class="bg-white rounded-2xl border border-stone-200 shadow-2xl max-w-xl w-full p-6 space-y-5 animate-in fade-in zoom-in duration-150">
                <div class="flex items-center justify-between border-b border-stone-100 pb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center justify-center font-bold shadow-2xs">
                            <x-lucide-check-circle-2 class="w-5 h-5 text-emerald-600" />
                        </div>
                        <div>
                            <h3 class="font-bold text-sm text-zinc-900 tracking-tight">{{ __('Resumen de Sincronización Trello') }}</h3>
                            <p class="text-[11px] text-zinc-400 font-mono">{{ $syncReport['timestamp'] }}</p>
                        </div>
                    </div>
                    <button wire:click="closeReportModal" class="text-zinc-400 hover:text-zinc-700 cursor-pointer">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                </div>

                <!-- Metrics Grid Report -->
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
                    <button 
                        wire:click="setFilter('created')"
                        class="p-2.5 rounded-xl border text-left transition cursor-pointer {{ $activeFilter === 'created' ? 'bg-emerald-100/70 border-emerald-400 ring-2 ring-emerald-400/30' : 'bg-emerald-50/70 border-emerald-200 hover:bg-emerald-100/50' }}">
                        <span class="text-[9px] uppercase font-bold text-emerald-800 tracking-wider block truncate">{{ __('Nuevas Importadas') }}</span>
                        <div class="flex items-baseline gap-1 mt-0.5">
                            <span class="text-lg font-bold text-emerald-900 font-mono">+{{ $syncReport['added'] }}</span>
                            <span class="text-[10px] text-emerald-700">{{ __('tarjetas') }}</span>
                        </div>
                    </button>

                    <button 
                        wire:click="setFilter('pushed_to_trello')"
                        class="p-2.5 rounded-xl border text-left transition cursor-pointer {{ $activeFilter === 'pushed_to_trello' ? 'bg-purple-100/70 border-purple-400 ring-2 ring-purple-400/30' : 'bg-purple-50/70 border-purple-200 hover:bg-purple-100/50' }}">
                        <span class="text-[9px] uppercase font-bold text-purple-800 tracking-wider block truncate">{{ __('Enviadas a Trello') }}</span>
                        <div class="flex items-baseline gap-1 mt-0.5">
                            <span class="text-lg font-bold text-purple-900 font-mono">{{ $syncReport['pushed'] ?? 0 }}</span>
                            <span class="text-[10px] text-purple-700">{{ __('tarjetas') }}</span>
                        </div>
                    </button>

                    <button 
                        wire:click="setFilter('moved')"
                        class="p-2.5 rounded-xl border text-left transition cursor-pointer {{ $activeFilter === 'moved' ? 'bg-sky-100/70 border-sky-400 ring-2 ring-sky-400/30' : 'bg-sky-50/70 border-sky-200 hover:bg-sky-100/50' }}">
                        <span class="text-[9px] uppercase font-bold text-sky-800 tracking-wider block truncate">{{ __('Movidas Local') }}</span>
                        <div class="flex items-baseline gap-1 mt-0.5">
                            <span class="text-lg font-bold text-sky-900 font-mono">{{ $syncReport['moved'] }}</span>
                            <span class="text-[10px] text-sky-700">{{ __('tarjetas') }}</span>
                        </div>
                    </button>

                    <button 
                        wire:click="setFilter('updated')"
                        class="p-2.5 rounded-xl border text-left transition cursor-pointer {{ $activeFilter === 'updated' ? 'bg-amber-100/70 border-amber-400 ring-2 ring-amber-400/30' : 'bg-amber-50/70 border-amber-200 hover:bg-amber-100/50' }}">
                        <span class="text-[9px] uppercase font-bold text-amber-800 tracking-wider block truncate">{{ __('Actualizadas') }}</span>
                        <div class="flex items-baseline gap-1 mt-0.5">
                            <span class="text-lg font-bold text-amber-900 font-mono">{{ $syncReport['updated'] }}</span>
                            <span class="text-[10px] text-amber-700">{{ __('tarjetas') }}</span>
                        </div>
                    </button>

                    <button 
                        wire:click="setFilter('deleted')"
                        class="p-2.5 rounded-xl border text-left transition cursor-pointer {{ $activeFilter === 'deleted' ? 'bg-rose-100/70 border-rose-400 ring-2 ring-rose-400/30' : 'bg-rose-50/70 border-rose-200 hover:bg-rose-100/50' }}">
                        <span class="text-[9px] uppercase font-bold text-rose-800 tracking-wider block truncate">{{ __('Faltantes Trello') }}</span>
                        <div class="flex items-baseline gap-1 mt-0.5">
                            <span class="text-lg font-bold text-rose-900 font-mono">{{ $syncReport['deleted'] }}</span>
                            <span class="text-[10px] text-rose-700">{{ __('tarjetas') }}</span>
                        </div>
                    </button>
                </div>

                <div class="bg-stone-50 border border-stone-200 rounded-xl p-3 flex items-center justify-between text-xs font-medium text-zinc-700">
                    <span class="flex items-center gap-1.5">
                        <x-lucide-layers class="w-4 h-4 text-zinc-500" /> {{ __('Total Procesadas en Tablero:') }}
                    </span>
                    <span class="font-bold font-mono text-zinc-900">{{ $syncReport['total'] }} {{ __('tarjetas') }}</span>
                </div>

                <!-- Complete Scrollable List of Changed Cards -->
                <div class="space-y-2.5">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-zinc-800 uppercase tracking-wider flex items-center gap-1.5">
                            <x-lucide-list-checks class="w-4 h-4 text-stone-600" /> 
                            {{ __('Lista de Tarjetas Que Cambiaron') }} ({{ count($syncReport['changes']) }})
                        </span>

                        <!-- Filter Category Tabs -->
                        <div class="flex items-center gap-1 text-[11px]">
                            <button 
                                wire:click="setFilter('all')" 
                                class="px-2 py-0.5 rounded-md font-medium transition cursor-pointer {{ $activeFilter === 'all' ? 'bg-zinc-900 text-white font-semibold' : 'text-zinc-600 hover:bg-stone-100' }}">
                                {{ __('Todas') }} ({{ count($syncReport['changes']) }})
                            </button>
                        </div>
                    </div>

                    @php
                        $filteredChanges = collect($syncReport['changes'])->filter(function ($chg) use ($activeFilter) {
                            if ($activeFilter === 'all') return true;
                            return $chg['action'] === $activeFilter;
                        });
                    @endphp

                    @if($filteredChanges->isEmpty())
                        <div class="p-6 text-center text-zinc-400 bg-stone-50 rounded-xl border border-stone-200 text-xs">
                            <p>{{ __('No hay tarjetas registradas en esta categoría de cambios.') }}</p>
                        </div>
                    @else
                        <div class="space-y-2 max-h-64 overflow-y-auto pr-1 scrollbar-thin text-xs">
                            @foreach($filteredChanges as $chg)
                                <div 
                                    @if(isset($chg['order_id']))
                                        wire:click="$dispatch('open-order-detail', { orderId: {{ $chg['order_id'] }} })"
                                    @endif
                                    class="p-3 bg-[#fbfbfa] hover:bg-stone-100/90 rounded-xl border border-stone-200 flex items-center justify-between gap-3 transition cursor-pointer group shadow-2xs"
                                    title="{{ __('Haz clic para ver el detalle de esta orden') }}">
                                    <div class="min-w-0 flex-1 space-y-1">
                                        <div class="space-y-0.5">
                                            <span class="font-bold text-zinc-900 group-hover:text-stone-900 flex items-center gap-1.5 truncate text-xs">
                                                <span>{{ $chg['company'] }}</span>
                                                <x-lucide-external-link class="w-3.5 h-3.5 text-zinc-400 group-hover:text-zinc-700 opacity-0 group-hover:opacity-100 transition shrink-0" />
                                            </span>
                                            @if($chg['task'])
                                                <span class="text-[11px] text-zinc-500 block truncate font-normal">{{ $chg['task'] }}</span>
                                            @endif
                                        </div>

                                        @if(!empty($chg['details']))
                                            <div class="flex flex-wrap gap-1 pt-0.5">
                                                @foreach($chg['details'] as $detail)
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-medium bg-amber-50 text-amber-900 border border-amber-200/80 shadow-2xs">
                                                        <x-lucide-sparkles class="w-3 h-3 text-amber-600 shrink-0" />
                                                        <span>{{ $detail }}</span>
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>

                                    <div class="shrink-0 text-right">
                                        @if($chg['action'] === 'created')
                                            <span class="px-2.5 py-1 rounded-md text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                                + {{ __('Nueva Orden') }}
                                            </span>
                                        @elseif($chg['action'] === 'pushed_to_trello')
                                            <span class="px-2.5 py-1 rounded-md text-[10px] font-bold bg-purple-100 text-purple-800 border border-purple-200 flex items-center gap-1">
                                                <x-lucide-arrow-up-right class="w-3 h-3 text-purple-600 inline shrink-0" />
                                                <span>{{ __('Enviado a Trello:') }} {{ $chg['previous_status'] }} ➔ {{ $chg['new_status'] }}</span>
                                            </span>
                                        @elseif($chg['action'] === 'moved')
                                            <span class="px-2.5 py-1 rounded-md text-[10px] font-bold bg-sky-100 text-sky-800 border border-sky-200 flex items-center gap-1">
                                                <span>{{ $chg['previous_status'] }}</span>
                                                <x-lucide-arrow-right class="w-3 h-3 text-sky-600 inline shrink-0" />
                                                <span>{{ $chg['new_status'] }}</span>
                                            </span>
                                        @elseif($chg['action'] === 'updated')
                                            <span class="px-2.5 py-1 rounded-md text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                                {{ __('Actualizada') }}
                                            </span>
                                        @elseif($chg['action'] === 'deleted')
                                            <span class="px-2.5 py-1 rounded-md text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-200">
                                                {{ __('Falta en Trello') }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="pt-2 border-t border-stone-100 flex justify-end">
                    <button 
                        wire:click="closeReportModal" 
                        class="px-4 py-2 bg-stone-900 hover:bg-stone-800 text-white font-semibold text-xs rounded-xl shadow-2xs transition cursor-pointer">
                        {{ __('Entendido / Cerrar') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Flash & Warning Messages -->
    @if (session()->has('message'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-3 rounded-lg text-xs font-medium flex items-center gap-2 shrink-0">
            <x-lucide-check-circle-2 class="w-4 h-4 text-emerald-600 shrink-0" />
            <span class="truncate">{{ session('message') }}</span>
        </div>
    @endif
    @if (session()->has('warning'))
        <div class="bg-amber-50 border border-amber-200 text-amber-800 p-3 rounded-lg text-xs font-medium flex items-center gap-2 shrink-0">
            <x-lucide-alert-triangle class="w-4 h-4 text-amber-600 shrink-0" />
            <span class="truncate">{{ session('warning') }}</span>
        </div>
    @endif

    <!-- Highlighted Section: New Orders from Trello Sync -->
    @if($newTrelloOrders->isNotEmpty())
        <div class="bg-gradient-to-r from-sky-50 via-cyan-50/60 to-white border-2 border-sky-400 rounded-xl p-4 space-y-3 shadow-sm ring-2 ring-sky-200/50 shrink-0">
            <div class="flex items-center justify-between border-b border-sky-200 pb-2">
                <div class="flex items-center gap-2">
                    <div class="p-1.5 rounded-lg bg-sky-500 text-white shrink-0 shadow-2xs">
                        <x-lucide-sparkles class="w-4 h-4 animate-pulse" />
                    </div>
                    <div>
                        <h3 class="font-bold text-xs text-sky-950 uppercase tracking-wider flex items-center gap-2">
                            Nuevas Órdenes de Trello ({{ $newTrelloOrders->count() }})
                        </h3>
                        <p class="text-[11px] text-sky-800 mt-0.5">Órdenes recién encontradas en la sincronización de Trello. Haz clic en "Añadir a Workspace" para activarlas.</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2.5">
                @foreach($newTrelloOrders as $nOrder)
                    <div class="bg-white border border-sky-300 hover:border-sky-500 rounded-lg p-3 space-y-2 shadow-2xs transition group">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5 mb-1">
                                    <span class="px-1.5 py-0.2 rounded bg-sky-100 text-sky-800 text-[9px] font-bold uppercase tracking-wider border border-sky-300">
                                        NUEVA DE TRELLO
                                    </span>
                                    @if($nOrder->wo_number)
                                        <x-wo-badge :number="$nOrder->wo_number" variant="dark" />
                                    @endif
                                </div>
                                <h4 class="font-normal text-[11px] text-zinc-500 truncate leading-snug" title="{{ $nOrder->company_name }}">{{ $nOrder->company_name }}</h4>
                                <p class="font-bold text-xs text-zinc-900 truncate mt-0.5" title="{{ $nOrder->task_name }}">{{ $nOrder->task_name }}</p>
                            </div>
                            
                            <button wire:click="addToWorkspace({{ $nOrder->id }})" class="px-2.5 py-1 rounded bg-sky-600 hover:bg-sky-700 text-white text-[10px] font-medium transition shrink-0 flex items-center gap-1 shadow-2xs">
                                <x-lucide-plus class="w-3 h-3" />
                                <span>Añadir</span>
                            </button>
                        </div>

                        <div class="flex items-center justify-between text-[10px] pt-1.5 border-t border-sky-100 text-zinc-500">
                            <span>Resp: <strong class="text-zinc-700">{{ $nOrder->responsible_person ?: 'N/A' }}</strong></span>
                            <button wire:click="$dispatch('open-order-detail', { orderId: {{ $nOrder->id }} })" class="text-sky-700 hover:underline font-medium">
                                Ver Detalle →
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Backlog Notion Database Table Card Container -->
    <div class="flex-1 min-h-0 bg-white border border-[#e9e9e7] rounded-xl shadow-2xs flex flex-col overflow-hidden">
        <!-- Internal Scrollable Table Area -->
        <div class="flex-1 min-h-0 overflow-y-auto overflow-x-auto custom-vertical-scrollbar">
            <table class="w-full text-left text-xs text-zinc-700">
                <thead class="bg-[#f7f7f5] text-zinc-600 font-semibold border-b border-[#e9e9e7] uppercase text-[10px] tracking-wider sticky top-0 z-20 shadow-2xs">
                    <tr>
                        <th class="sticky left-0 z-30 bg-[#f7f7f5] p-3 w-10 text-center border-r border-[#e9e9e7]">
                            <input type="checkbox" wire:model.live="selectAll" class="rounded border-stone-300 text-stone-800 focus:ring-stone-400">
                        </th>
                        <th class="sticky left-10 z-30 bg-[#f7f7f5] p-3 whitespace-nowrap border-r border-[#e9e9e7] shadow-[2px_0_4px_-2px_rgba(0,0,0,0.05)]">WO #</th>
                        <th class="p-3">Empresa</th>
                        <th class="p-3">Responsable</th>
                        <th class="p-3">Tarea / Trabajo</th>
                        <th class="p-3">Creación Trello</th>
                        <th class="p-3">Fecha Límite</th>
                        <th class="p-3">Lista / Estado</th>
                        <th class="p-3">Diseñador</th>
                        <th class="sticky right-0 z-30 bg-[#f7f7f5] p-3 text-right whitespace-nowrap border-l border-[#e9e9e7] shadow-[-2px_0_4px_-2px_rgba(0,0,0,0.05)]">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#e9e9e7]">
                    @forelse($orders as $order)
                        @php $isSelected = in_array((string)$order->id, $selectedOrders); @endphp
                        <tr class="group transition {{ $order->isUrgente() ? 'bg-red-500 text-white font-bold shadow-md' : ($order->isOverdue() ? 'bg-rose-50 border border-red-400' : ($order->isDueToday() ? 'bg-amber-50 border border-amber-300' : ($isSelected ? 'bg-stone-50' : 'hover:bg-[#fcfcfb]'))) }}"
                            @if($order->isOverdue() && !$order->isUrgente()) style="border: 1px solid #ef4444 !important; background-color: #fef2f2 !important;" @elseif($order->isDueToday() && !$order->isUrgente()) style="border: 1px solid #f59e0b !important; background-color: #fffbeb !important;" @endif>
                            <td class="sticky left-0 z-10 p-3 text-center border-r border-[#e9e9e7] transition-colors {{ $isSelected ? 'bg-stone-50' : 'bg-white group-hover:bg-[#fcfcfb]' }}">
                                <input type="checkbox" wire:model.live="selectedOrders" value="{{ $order->id }}" class="rounded border-stone-300 text-stone-800 focus:ring-stone-400">
                            </td>
                            <td class="sticky left-10 z-10 p-3 whitespace-nowrap font-mono text-xs font-bold text-zinc-900 border-r border-[#e9e9e7] shadow-[2px_0_4px_-2px_rgba(0,0,0,0.05)] transition-colors {{ $isSelected ? 'bg-stone-50' : 'bg-white group-hover:bg-[#fcfcfb]' }}">
                                <div class="flex items-center gap-1.5">
                                    @if($order->wo_number)
                                        <x-wo-badge :number="$order->wo_number" variant="dark" />
                                    @else
                                        <span class="text-zinc-400 text-[10px]">—</span>
                                    @endif
                                    @if($order->pending_wo_number)
                                        <x-wo-badge :number="$order->pending_wo_number" variant="amber" prefix="Trello: " />
                                    @endif
                                    @if($order->trello_url)
                                        <a href="{{ $order->trello_url }}" target="_blank" rel="noopener noreferrer" class="p-0.5 rounded text-blue-600 hover:text-blue-800 hover:bg-blue-50 transition" title="Abrir en Trello.com">
                                            <x-lucide-external-link class="w-3.5 h-3.5" />
                                        </a>
                                    @endif
                                </div>
                            </td>
                            <td class="p-3 font-semibold text-zinc-900 min-w-0">
                                <span class="truncate block max-w-xs {{ $order->done_today ? 'line-through text-zinc-400' : '' }}" title="{{ $order->company_name }}">{{ $order->company_name }}</span>
                            </td>
                            <td class="p-3 whitespace-nowrap">
                                @if($order->responsible_person)
                                    <span class="px-2 py-0.5 rounded bg-indigo-50 text-indigo-800 border border-indigo-200 text-[10px] font-bold">
                                        {{ $order->responsible_person }}
                                    </span>
                                @else
                                    <span class="text-zinc-400 text-[10px]">—</span>
                                @endif
                            </td>
                            <td class="p-3 text-zinc-600 min-w-0">
                                <span class="truncate block max-w-sm" title="{{ $order->task_name }}">{{ $order->task_name }}</span>
                            </td>
                            <td class="p-3 font-mono text-[11px] text-zinc-600 whitespace-nowrap">
                                {{ $order->trello_created_at ? $order->trello_created_at->format('d M, Y (H:i)') : 'N/A' }}
                            </td>
                            <td class="p-3 font-mono text-zinc-500 whitespace-nowrap">
                                {{ $order->current_due_date ? $order->current_due_date->format('d M, Y') : 'N/A' }}
                            </td>
                            <td class="p-3 whitespace-nowrap">
                                <span class="px-2 py-0.5 rounded text-[10px] font-medium bg-stone-100 border border-stone-200 text-zinc-700">
                                    {{ $order->core_status->label() }}
                                </span>
                            </td>
                            <td class="p-3 whitespace-nowrap">
                                <div class="flex flex-wrap items-center gap-1">
                                    @forelse($order->assigned_designers as $des)
                                        <span class="px-2 py-0.5 rounded text-[10px] border font-semibold {{ $des->badge_style }}">
                                            {{ $des->name }}
                                        </span>
                                    @empty
                                        <span class="px-2 py-0.5 rounded text-[10px] border border-amber-300 bg-amber-100 text-amber-800 font-semibold">
                                            Sin Asignar
                                        </span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="sticky right-0 z-10 p-3 text-right whitespace-nowrap border-l border-[#e9e9e7] shadow-[-2px_0_4px_-2px_rgba(0,0,0,0.05)] transition-colors {{ $isSelected ? 'bg-stone-50' : 'bg-white group-hover:bg-[#fcfcfb]' }}">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button wire:click="$dispatch('open-order-detail', { orderId: {{ $order->id }}, startEdit: true })" class="px-2 py-1 rounded bg-stone-100 hover:bg-stone-200 border border-stone-200 text-[11px] font-medium text-zinc-800 transition flex items-center gap-1" title="Editar campos y añadir a workspace">
                                        <x-lucide-pencil-line class="w-3 h-3 text-zinc-600" />
                                        <span>Editar & Añadir</span>
                                    </button>
                                    
                                    <button wire:click="addToWorkspace({{ $order->id }})" class="px-2 py-1 rounded bg-stone-900 hover:bg-stone-800 text-white text-[11px] font-medium transition flex items-center gap-1" title="Añadir directamente a workspace activo">
                                        <x-lucide-plus-circle class="w-3 h-3 text-emerald-400" />
                                        <span>Añadir Directo</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="p-12 text-center text-zinc-400 italic">
                                <x-lucide-check-circle-2 class="w-6 h-6 text-emerald-600 mx-auto mb-2" />
                                <span>No hay tarjetas pendientes en el Backlog. ¡Todas están procesadas o en Workspace!</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Integrated Bottom Pagination Footer -->
        <div class="shrink-0 bg-white border-t border-[#e9e9e7] p-3 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-zinc-600 font-medium">
            <div class="flex items-center gap-3">
                <span>
                    Mostrando <strong class="text-zinc-900 font-semibold">{{ $orders->firstItem() ?? 0 }}</strong> – <strong class="text-zinc-900 font-semibold">{{ $orders->lastItem() ?? 0 }}</strong> de <strong class="text-zinc-900 font-semibold">{{ $orders->total() }}</strong> tarjetas
                </span>
                <div class="h-3 w-px bg-zinc-300"></div>
                <div class="flex items-center gap-1.5">
                    <span class="text-zinc-400 text-[11px]">Ver:</span>
                    <select wire:model.live="perPage" class="bg-[#fbfbfa] border border-[#e9e9e7] rounded px-2 py-0.5 text-xs text-zinc-800 font-medium focus:outline-none">
                        <option value="25">25 / pág</option>
                        <option value="50">50 / pág</option>
                        <option value="100">100 / pág</option>
                    </select>
                </div>
            </div>

            @if($orders->hasPages())
                <div class="flex items-center gap-1.5 shrink-0">
                    <!-- Previous Button -->
                    @if($orders->onFirstPage())
                        <span class="px-2.5 py-1 rounded-md bg-stone-100 text-zinc-400 border border-stone-200 text-xs font-medium cursor-not-allowed flex items-center gap-1">
                            <x-lucide-chevron-left class="w-3.5 h-3.5" />
                            <span>Anterior</span>
                        </span>
                    @else
                        <button wire:click="previousPage" class="px-2.5 py-1 rounded-md bg-white hover:bg-stone-100 text-zinc-800 border border-stone-200 text-xs font-medium transition shadow-2xs flex items-center gap-1">
                            <x-lucide-chevron-left class="w-3.5 h-3.5 text-zinc-600" />
                            <span>Anterior</span>
                        </button>
                    @endif

                    <!-- Page Indicator -->
                    <span class="px-3 py-1 rounded-md bg-stone-100 border border-stone-200 text-xs font-semibold font-mono text-zinc-800">
                        {{ $orders->currentPage() }} / {{ $orders->lastPage() }}
                    </span>

                    <!-- Next Button -->
                    @if($orders->hasMorePages())
                        <button wire:click="nextPage" class="px-2.5 py-1 rounded-md bg-white hover:bg-stone-100 text-zinc-800 border border-stone-200 text-xs font-medium transition shadow-2xs flex items-center gap-1">
                            <span>Siguiente</span>
                            <x-lucide-chevron-right class="w-3.5 h-3.5 text-zinc-600" />
                        </button>
                    @else
                        <span class="px-2.5 py-1 rounded-md bg-stone-100 text-zinc-400 border border-stone-200 text-xs font-medium cursor-not-allowed flex items-center gap-1">
                            <span>Siguiente</span>
                            <x-lucide-chevron-right class="w-3.5 h-3.5" />
                        </span>
                    @endif
                </div>
            @endif
        </div>
    </div>

</div>
