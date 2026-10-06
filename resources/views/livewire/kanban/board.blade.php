<div wire:poll.visible.10s class="h-full flex flex-col space-y-3 min-h-0 overflow-hidden">
    
    @if($this->newOrdersCount > 0)
        <div class="bg-amber-50/90 border border-amber-200 rounded-xl px-3.5 py-2 flex items-center justify-between gap-3 text-xs shrink-0 shadow-2xs">
            <div class="flex items-center gap-2 text-amber-900 font-medium truncate">
                <span class="flex h-2 w-2 relative shrink-0">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
                </span>
                <span>{{ __('Hay') }} <strong>{{ $this->newOrdersCount }}</strong> {{ $this->newOrdersCount === 1 ? __('nueva orden') : __('nuevas órdenes') }} {{ __('de Trello sin revisar en el Backlog.') }}</span>
            </div>
            <a href="{{ route('backlog') }}" wire:navigate class="px-2.5 py-1 rounded-md bg-amber-100 hover:bg-amber-200 border border-amber-300 text-amber-900 text-[11px] font-bold transition shrink-0 flex items-center gap-1">
                <span>{{ __('Ver Nuevas Órdenes') }}</span>
                <x-lucide-arrow-right class="w-3 h-3 text-amber-800" />
            </a>
        </div>
    @endif
    
    <!-- Top Notion-Style Header Controls -->
    <div id="tour-kanban-header" class="bg-white border border-[#e9e9e7] rounded-xl p-3 space-y-2.5 shadow-2xs shrink-0">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 min-w-0">
            <div class="flex items-center gap-3 min-w-0 flex-wrap">
                <h1 id="tour-kanban-title" class="text-2xl sm:text-3xl font-extrabold text-zinc-900 tracking-tight">{{ __('Kanban Board') }}</h1>
            </div>

            <div class="flex items-center gap-2 shrink-0 self-end sm:self-auto">
                <button 
                    id="tour-kanban-new-btn"
                    @click="$dispatch('open-create-order')" 
                    class="px-3 py-1.5 h-8 rounded-lg bg-stone-900 hover:bg-stone-800 text-white text-xs font-semibold shadow-2xs transition flex items-center gap-1.5 shrink-0">
                    <x-lucide-plus class="w-3.5 h-3.5 text-white" />
                    <span>{{ __('Nueva Orden') }}</span>
                </button>

                <a href="{{ route('trash') }}" wire:navigate class="hidden px-2.5 py-1.5 h-8 rounded-lg bg-rose-50 hover:bg-rose-100 border border-rose-200 text-rose-700 hover:text-rose-900 text-xs font-semibold transition flex items-center gap-1.5 shrink-0" title="{{ __('Ver papelera') }}">
                    <x-lucide-trash-2 class="w-3.5 h-3.5" />
                    <span>{{ __('Papelera') }}</span>
                </a>
            </div>
        </div>

        <div class="pt-3 border-t border-[#f0f0ee] flex flex-wrap items-center gap-2 w-full">
            <!-- Search Input with Live Occurrences Dropdown -->
            <div class="relative flex-1 min-w-[200px] sm:min-w-[240px] max-w-sm" x-data="{ open: true }" x-dropdown-nav @click.outside="open = false">
                <x-lucide-search class="w-3.5 h-3.5 text-zinc-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none shrink-0 z-10" />
                <input type="text" 
                       id="tour-kanban-search"
                       wire:model.live.debounce.200ms="search" 
                       @focus="open = true" 
                       @input="open = true"
                       placeholder="{{ __('Buscar empresa o tarea...') }}" 
                       class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-lg pl-8 pr-3 py-1.5 h-8 text-xs text-zinc-800 focus:border-stone-400 focus:outline-none w-full">

                @if(strlen(trim($search)) >= 2)
                    <div x-show="open" 
                         x-cloak
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         class="absolute left-0 right-0 top-full mt-1.5 z-50 bg-white border border-[#e9e9e7] rounded-xl shadow-xl max-h-72 overflow-y-auto p-1.5 text-xs"
                         style="display: none;">
                        <div class="px-2 py-1 text-[10px] font-semibold uppercase tracking-wider text-zinc-400 border-b border-[#f0f0ee] mb-1 flex items-center justify-between">
                            <span>{{ __('Coincidencias') }} ({{ $this->searchResults->count() }})</span>
                            <span class="text-[9px] font-mono text-zinc-400">{{ __('Clic para abrir') }}</span>
                        </div>

                        @forelse($this->searchResults as $result)
                            <div role="button"
                                 tabindex="0"
                                 wire:click="selectSearchResult({{ $result->id }})" 
                                 @click="open = false"
                                 @keydown.enter="selectSearchResult({{ $result->id }}); open = false;"
                                 class="w-full text-left p-2 rounded-lg hover:bg-stone-100 focus:bg-stone-100 focus:outline-none transition flex flex-nowrap items-center justify-between gap-2 group cursor-pointer min-w-0">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5 min-w-0">
                                        @if($result->wo_number)
                                            <x-wo-badge :number="$result->wo_number" variant="light" />
                                        @endif
                                        <span class="font-semibold text-zinc-900 truncate group-hover:text-stone-900 text-xs shrink min-w-0 uppercase">
                                            {{ $result->company_name }}
                                        </span>
                                        @if($result->location_text)
                                            <span class="inline-flex items-center gap-0.5 text-[9px] font-semibold text-stone-600 bg-stone-100 px-1.5 py-0.2 rounded border border-stone-200/90 shrink-0" title="Locación: {{ $result->location_text }}">
                                                <x-lucide-map-pin class="w-2.5 h-2.5 text-rose-500 shrink-0" />
                                                <span class="truncate max-w-[120px]">{{ $result->location_text }}</span>
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-[11px] text-zinc-500 truncate mt-0.5 uppercase" title="{{ $result->task_name }}">{{ $result->task_name }}</p>
                                </div>

                                <div class="flex items-center gap-1.5 shrink-0 min-w-0">
                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-medium border bg-stone-50 text-zinc-600 border-stone-200 shrink-0 max-w-[130px] truncate" title="{{ $result->core_status->label() }}">
                                        {{ $result->core_status->label() }}
                                    </span>
                                    <x-lucide-chevron-right class="w-3.5 h-3.5 text-zinc-400 group-hover:text-zinc-700 transition shrink-0" />
                                </div>
                            </div>
                        @empty
                            <div class="px-3 py-4 text-center text-zinc-400 text-xs">
                                {{ __('No se encontraron coincidencias para') }} "{{ $search }}"
                            </div>
                        @endforelse
                    </div>
                @endif
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
                    <span class="truncate">{{ $companyFilter === 'all' ? __('Empresas (Todas)') : $companyFilter }}</span>
                    <x-lucide-chevron-down class="w-3 h-3 text-zinc-400 shrink-0" />
                </button>

                <div 
                    x-show="open" 
                    x-cloak
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    class="absolute left-0 top-full mt-1 z-50 bg-white border border-[#e9e9e7] rounded-lg shadow-xl w-52 max-h-56 overflow-y-auto divide-y divide-stone-100 text-xs"
                    style="display: none;">
                    <div class="p-1.5 sticky top-0 bg-white border-b border-stone-100">
                        <input 
                            type="text" 
                            x-model="search" 
                            placeholder="{{ __('Buscar empresa...') }}" 
                            class="w-full bg-stone-50 border border-stone-200 rounded px-2 py-1 text-[11px] text-zinc-800 focus:outline-none">
                    </div>
                    <button 
                        type="button"
                        @click="selectComp('all')" 
                        class="w-full text-left p-2 hover:bg-stone-100 focus:bg-stone-100 focus:outline-none cursor-pointer font-medium text-zinc-800 transition flex items-center justify-between">
                        <span>{{ __('Empresas (Todas)') }}</span>
                        @if($companyFilter === 'all')
                            <x-lucide-check class="w-3.5 h-3.5 text-emerald-600 stroke-[3]" />
                        @endif
                    </button>
                    @foreach($this->existingCompanies as $comp)
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
            <div class="hidden relative flex-1 min-w-[140px] sm:flex-none" 
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
                    <span class="truncate">{{ $responsibleFilter === 'all' ? __('Responsables (Todos)') : $responsibleFilter }}</span>
                    <x-lucide-chevron-down class="w-3 h-3 text-zinc-400 shrink-0" />
                </button>

                <div 
                    x-show="open" 
                    x-cloak
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    class="absolute left-0 top-full mt-1 z-50 bg-white border border-[#e9e9e7] rounded-lg shadow-xl w-48 max-h-56 overflow-y-auto divide-y divide-stone-100 text-xs"
                    style="display: none;">
                    <div class="p-1.5 sticky top-0 bg-white border-b border-stone-100">
                        <input 
                            type="text" 
                            x-model="search" 
                            placeholder="{{ __('Buscar responsable...') }}" 
                            class="w-full bg-stone-50 border border-stone-200 rounded px-2 py-1 text-[11px] text-zinc-800 focus:outline-none">
                    </div>
                    <button 
                        type="button"
                        @click="selectResp('all')" 
                        class="w-full text-left p-2 hover:bg-stone-100 focus:bg-stone-100 focus:outline-none cursor-pointer font-medium text-zinc-800 transition flex items-center justify-between">
                        <span>{{ __('Responsables (Todos)') }}</span>
                        @if($responsibleFilter === 'all')
                            <x-lucide-check class="w-3.5 h-3.5 text-emerald-600 stroke-[3]" />
                        @endif
                    </button>
                    @foreach($this->existingResponsibles as $resp)
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
                            {{ __('Diseñadores (Todos)') }}
                        @else
                            {{ $this->designers->firstWhere('id', $designerFilter)?->name ?? __('Diseñadores (Todos)') }}
                        @endif
                    </span>
                    <x-lucide-chevron-down class="w-3 h-3 text-zinc-400 shrink-0" />
                </button>

                <div 
                    x-show="open" 
                    x-cloak
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    class="absolute left-0 top-full mt-1 z-50 bg-white border border-[#e9e9e7] rounded-lg shadow-xl w-48 max-h-56 overflow-y-auto divide-y divide-stone-100 text-xs"
                    style="display: none;">
                    <div class="p-1.5 sticky top-0 bg-white border-b border-stone-100">
                        <input 
                            type="text" 
                            x-model="search" 
                            placeholder="{{ __('Buscar diseñador...') }}" 
                            class="w-full bg-stone-50 border border-stone-200 rounded px-2 py-1 text-[11px] text-zinc-800 focus:outline-none">
                    </div>
                    <button 
                        type="button"
                        @click="selectDesigner('all')" 
                        class="w-full text-left p-2 hover:bg-stone-100 focus:bg-stone-100 focus:outline-none cursor-pointer font-medium text-zinc-800 transition flex items-center justify-between">
                        <span>{{ __('Diseñadores (Todos)') }}</span>
                        @if($designerFilter === 'all')
                            <x-lucide-check class="w-3.5 h-3.5 text-emerald-600 stroke-[3]" />
                        @endif
                    </button>
                    @foreach($this->designers as $designer)
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

            <!-- Substatus Filter (Searchable) -->
            <div class="relative flex-1 min-w-[140px] sm:flex-none" 
                 x-data="{ 
                     open: false,
                     search: '',
                     selectSub(val) {
                         $wire.set('substatusFilter', val);
                         this.open = false;
                     }
                 }"
                 x-dropdown-nav>
                <button 
                    id="tour-kanban-substatus-filter"
                    type="button" 
                    @click="open = !open" 
                    @click.outside="open = false"
                    class="w-full bg-[#fbfbfa] hover:bg-white border border-[#e9e9e7] hover:border-stone-300 rounded-lg px-2.5 h-8 text-xs text-zinc-700 font-medium flex items-center justify-between gap-1 truncate transition shadow-2xs">
                    <span class="truncate">{{ $substatusFilter === 'all' ? __('Condición (Todas)') : $substatusFilter }}</span>
                    <x-lucide-chevron-down class="w-3 h-3 text-zinc-400 shrink-0" />
                </button>

                <div 
                    x-show="open" 
                    x-cloak
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    class="absolute left-0 top-full mt-1 z-50 bg-white border border-[#e9e9e7] rounded-lg shadow-xl w-48 max-h-56 overflow-y-auto divide-y divide-stone-100 text-xs"
                    style="display: none;">
                    <div class="p-1.5 sticky top-0 bg-white border-b border-stone-100">
                        <input 
                            type="text" 
                            x-model="search" 
                            placeholder="{{ __('Buscar condición...') }}" 
                            class="w-full bg-stone-50 border border-stone-200 rounded px-2 py-1 text-[11px] text-zinc-800 focus:outline-none">
                    </div>
                    <button 
                        type="button"
                        @click="selectSub('all')" 
                        class="w-full text-left p-2 hover:bg-stone-100 focus:bg-stone-100 focus:outline-none cursor-pointer font-medium text-zinc-800 transition flex items-center justify-between">
                        <span>{{ __('Condición (Todas)') }}</span>
                        @if($substatusFilter === 'all')
                            <x-lucide-check class="w-3.5 h-3.5 text-emerald-600 stroke-[3]" />
                        @endif
                    </button>
                    @foreach(\App\Enums\Substatus::cases() as $sub)
                        <button 
                            type="button"
                            x-show="!search || '{{ strtolower(addslashes($sub->value)) }}'.includes(search.toLowerCase())"
                            @click="selectSub('{{ $sub->value }}')" 
                            class="w-full text-left p-2 hover:bg-stone-100 focus:bg-stone-100 focus:outline-none cursor-pointer font-medium text-zinc-800 transition flex items-center justify-between">
                            <span class="px-2 py-0.5 rounded text-[10px] font-medium border {{ $sub->badgeStyle() }}" style="{{ $sub->getInlineBadgeStyle() }}">
                                {{ $sub->value }}
                            </span>
                            @if($substatusFilter === $sub->value)
                                <x-lucide-check class="w-3.5 h-3.5 text-emerald-600 stroke-[3] shrink-0" />
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- Notion Column Group Filter Tabs Bar -->
    <div id="tour-kanban-group-tabs" class="hidden flex items-center justify-between gap-1 border-b border-[#e9e9e7] pb-2 overflow-x-auto scrollbar-none text-xs shrink-0">
        <div class="flex items-center gap-1 shrink-0">
            <button wire:click="$set('columnGroup', 'all')" class="px-3 py-1 rounded-md font-medium transition flex items-center gap-1.5 shrink-0 {{ $columnGroup === 'all' ? 'bg-white text-zinc-900 border border-[#d0d0ce] shadow-2xs font-semibold' : 'text-zinc-500 hover:text-zinc-800 hover:bg-[#f2f2f0]' }}">
                <x-lucide-layers class="w-3.5 h-3.5 text-zinc-500" />
                <span>{{ __('Todas las Listas') }} (9)</span>
            </button>

            <button wire:click="$set('columnGroup', 'incoming')" class="px-3 py-1 rounded-md font-medium transition flex items-center gap-1.5 shrink-0 {{ $columnGroup === 'incoming' ? 'bg-white text-zinc-900 border border-stone-300 shadow-2xs font-semibold' : 'text-zinc-500 hover:text-zinc-800 hover:bg-[#f2f2f0]' }}">
                <x-lucide-inbox class="w-3.5 h-3.5 text-zinc-500" />
                <span>{{ __('Bloqueadas & Pendientes') }} (4)</span>
            </button>

            <button wire:click="$set('columnGroup', 'in_progress')" class="px-3 py-1 rounded-md font-medium transition flex items-center gap-1.5 shrink-0 {{ $columnGroup === 'in_progress' ? 'bg-white text-zinc-900 border border-stone-300 shadow-2xs font-semibold' : 'text-zinc-500 hover:text-zinc-800 hover:bg-[#f2f2f0]' }}">
                <x-lucide-play-circle class="w-3.5 h-3.5 text-zinc-500" />
                <span>{{ __('En Proceso') }} (3)</span>
            </button>

            <button wire:click="$set('columnGroup', 'final')" class="px-3 py-1 rounded-md font-medium transition flex items-center gap-1.5 shrink-0 {{ $columnGroup === 'final' ? 'bg-white text-zinc-900 border border-stone-300 shadow-2xs font-semibold' : 'text-zinc-500 hover:text-zinc-800 hover:bg-[#f2f2f0]' }}">
                <x-lucide-check-circle-2 class="w-3.5 h-3.5 text-zinc-500" />
                <span>{{ __('Producción & Hold') }} (2)</span>
            </button>
        </div>

        <button wire:click="toggleStandaloneTaskCards" class="hidden px-3 py-1 rounded-md font-medium transition flex items-center gap-1.5 shrink-0 text-xs {{ $showStandaloneTaskCards ? 'bg-violet-100 text-violet-900 border border-violet-300 font-semibold' : 'bg-white text-zinc-600 border border-stone-200 hover:bg-stone-50' }}" title="{{ __('Mostrar u ocultar tarjetas de tareas como elementos independientes en las columnas') }}">
            <x-lucide-list-todo class="w-3.5 h-3.5 {{ $showStandaloneTaskCards ? 'text-violet-700' : 'text-zinc-500' }}" />
            <span>{{ $showStandaloneTaskCards ? __('Ocultar Tarjetas de Tareas') : __('Mostrar Tarjetas de Tareas') }}</span>
        </button>
    </div>

    <!-- Alert Flash Message -->
    @if (session()->has('message'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-3 rounded-lg text-xs font-medium flex items-center gap-2 shrink-0">
            <x-lucide-check-circle-2 class="w-4 h-4 text-emerald-600 shrink-0" />
            <span class="truncate">{{ session('message') }}</span>
        </div>
    @endif

    <!-- Notion Light Kanban Columns Container (Full Width Drag & Drop Grid with Edge Auto-Scroll) -->
    <div 
        x-data="{
            scrollSpeed: 0,
            scrollInterval: null,
            handleDragOver(e) {
                const container = $el;
                const rect = container.getBoundingClientRect();
                const mouseX = e.clientX;
                const threshold = 100;
                const maxSpeed = 24;

                let speed = 0;
                if (mouseX - rect.left < threshold && mouseX - rect.left > 0) {
                    const intensity = (threshold - (mouseX - rect.left)) / threshold;
                    speed = -Math.max(6, Math.round(intensity * maxSpeed));
                } else if (rect.right - mouseX < threshold && rect.right - mouseX > 0) {
                    const intensity = (threshold - (rect.right - mouseX)) / threshold;
                    speed = Math.max(6, Math.round(intensity * maxSpeed));
                }

                this.scrollSpeed = speed;

                if (speed !== 0 && !this.scrollInterval) {
                    const step = () => {
                        if (this.scrollSpeed !== 0) {
                            container.scrollLeft += this.scrollSpeed;
                            this.scrollInterval = requestAnimationFrame(step);
                        } else {
                            this.scrollInterval = null;
                        }
                    };
                    this.scrollInterval = requestAnimationFrame(step);
                } else if (speed === 0 && this.scrollInterval) {
                    cancelAnimationFrame(this.scrollInterval);
                    this.scrollInterval = null;
                }
            },
            stopAutoScroll() {
                this.scrollSpeed = 0;
                if (this.scrollInterval) {
                    cancelAnimationFrame(this.scrollInterval);
                    this.scrollInterval = null;
                }
            }
        }"
        @dragover="handleDragOver($event)"
        @dragend="stopAutoScroll()"
        @drop="stopAutoScroll()"
        @dragleave.self="stopAutoScroll()"
        class="flex gap-3 overflow-x-auto pb-3 pt-1 custom-horizontal-scrollbar flex-1 min-h-0 w-full"
        id="tour-kanban-board">
        @foreach($columns as $column)
            @if($column === \App\Enums\CoreStatus::ARCHIVED)
                <!-- Archive Dropzone Column (No cards displayed) -->
                <div 
                    x-data="{ isTarget: false }"
                    @dragover.prevent="isTarget = true"
                    @dragleave.prevent="isTarget = false"
                    @drop.prevent="
                        isTarget = false;
                        const orderId = event.dataTransfer.getData('text/plain');
                        if (orderId) {
                            $wire.moveOrder(orderId, '{{ $column->value }}');
                        }
                    "
                    :class="{ 'border-zinc-500 ring-4 ring-zinc-300/80 bg-zinc-200/90 scale-[1.01] shadow-lg': isTarget, 'bg-[#f4f4f2] border-stone-300': !isTarget }"
                    class="shrink-0 w-80 border-2 border-dashed rounded-xl flex flex-col h-full overflow-hidden transition-all duration-200 shadow-2xs">
                    
                    <!-- Column Header -->
                    <div class="p-3 border-b border-[#e9e9e7] bg-[#e5e5e3] rounded-t-xl flex items-center justify-between sticky top-0 z-10 shrink-0">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="w-2.5 h-2.5 rounded-full shrink-0 bg-slate-700"></span>
                            <h3 class="font-bold text-xs text-zinc-900 uppercase tracking-wider truncate flex items-center gap-1.5">
                                <x-lucide-archive class="w-3.5 h-3.5 text-zinc-700 shrink-0" />
                                <span>{{ __('Enviar a Archivo') }}</span>
                            </h3>
                        </div>

                        <a href="/archived" wire:navigate title="{{ __('Ver Órdenes Archivadas') }}" class="flex items-center gap-1 px-2 py-0.5 rounded bg-white text-[11px] font-mono text-zinc-700 border border-stone-300 font-bold shrink-0 hover:bg-stone-100 transition">
                            <x-lucide-external-link class="w-3 h-3 text-zinc-500" />
                            <span>{{ $this->archivedCount }}</span>
                        </a>
                    </div>

                    <!-- Dropzone Body Container (No Cards Rendered!) -->
                    <div class="p-6 flex-1 flex flex-col items-center justify-center text-center space-y-4 min-h-0 bg-gradient-to-b from-stone-50/50 via-stone-100/40 to-stone-100">
                        <div class="w-14 h-14 rounded-2xl bg-white border border-stone-200 shadow-2xs flex items-center justify-center text-zinc-700 group-hover:scale-105 transition">
                            <x-lucide-archive-restore class="w-7 h-7 text-zinc-600" />
                        </div>
                        <div class="space-y-1.5 px-2">
                            <h4 class="text-xs font-bold text-zinc-800 uppercase tracking-tight">{{ __('Arrastra aquí para archivar') }}</h4>
                            <p class="text-[11px] text-zinc-500 leading-snug">
                                {{ __('Las órdenes soltadas en esta columna se marcarán como Archivadas y se ocultarán del Workspace activo.') }}
                            </p>
                        </div>
                        <a href="/archived" wire:navigate class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white font-medium text-xs shadow-2xs transition mt-2">
                            <x-lucide-bar-chart-2 class="w-3.5 h-3.5 text-zinc-300" />
                            <span>{{ __('Ver Rendimiento') }}</span>
                        </a>
                    </div>
                </div>
            @else
                @php
                    $columnOrders = $this->orders->filter(fn($o) => $o->core_status === $column);
                @endphp
                <div 
                    x-data="{ isTarget: false }"
                    @dragover.prevent="isTarget = true"
                    @dragleave.prevent="isTarget = false"
                    @drop.prevent="
                        isTarget = false;
                        const orderId = event.dataTransfer.getData('text/plain');
                        if (orderId) {
                            $wire.moveOrder(orderId, '{{ $column->value }}');
                        }
                    "
                    :class="{ 'border-stone-400 ring-2 ring-stone-300/60 bg-stone-100': isTarget }"
                    class="shrink-0 w-80 bg-[#f7f7f5] border border-[#e9e9e7] rounded-xl flex flex-col h-full overflow-hidden transition duration-150 shadow-2xs">
                
                <!-- Column Header -->
                <div class="p-3 border-b border-[#e9e9e7] bg-[#efefed] rounded-t-xl flex items-center justify-between sticky top-0 z-10 shrink-0">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="w-2.5 h-2.5 rounded-full shrink-0 shadow-2xs" style="{{ $column->dotStyle() }}"></span>
                        <h3 class="font-semibold text-xs text-zinc-800 uppercase tracking-wider truncate">{{ $column->label() }}</h3>
                    </div>
                    @php
                        $columnTasks = match ($column) {
                            \App\Enums\CoreStatus::TO_DO_TODAY => $this->relatedTasks->filter(fn($t) => $t->order !== null && !$t->isDone() && $t->type !== \App\Enums\RelatedTaskType::BLOCKED),
                            \App\Enums\CoreStatus::ENTRANTE => $this->relatedTasks->filter(fn($t) => $t->order !== null && !$t->isDone() && ($t->type === \App\Enums\RelatedTaskType::BLOCKED || $t->type === \App\Enums\RelatedTaskType::RESOLVER)),
                            default => collect(),
                        };
                        $totalItemCount = $columnOrders->count() + $columnTasks->count();
                    @endphp
                    <div class="flex items-center gap-1 shrink-0 ml-1">
                        <button 
                            @click="$dispatch('open-create-order', { initialStatus: '{{ $column->value }}' })" 
                            class="p-1 rounded text-zinc-500 hover:text-zinc-900 hover:bg-white transition" 
                            title="Añadir nueva orden a {{ $column->label() }}">
                            <x-lucide-plus class="w-3.5 h-3.5" />
                        </button>
                        <span class="px-2 py-0.5 rounded bg-white text-[11px] font-mono text-zinc-600 border border-stone-200 font-semibold shrink-0">
                            {{ $totalItemCount }}
                        </span>
                    </div>
                </div>

                <!-- Column Cards Container -->
                <div class="p-2.5 overflow-y-auto flex-1 space-y-2.5 custom-vertical-scrollbar min-h-0">
                    @php
                        $urgentColumnOrders = $columnOrders->filter(fn($o) => $o->isUrgente());
                        $regularColumnOrders = $columnOrders->filter(fn($o) => !$o->isUrgente());
                    @endphp

                    @if($columnOrders->isEmpty() && $columnTasks->isEmpty())
                        <div class="py-12 text-center border border-dashed border-stone-300 rounded-lg">
                            <x-lucide-move class="w-4 h-4 text-zinc-400 mx-auto mb-1 shrink-0" />
                            <span class="text-[11px] text-zinc-500 font-normal block">Arrastra una tarjeta aquí</span>
                        </div>
                    @else
                        <!-- 1. URGENTE ORDER CARDS (ALWAYS TOP OF EVERYTHING IN COLUMN!) -->
                        @foreach($urgentColumnOrders as $order)
                            @include('livewire.kanban.card', ['order' => $order, 'allColumns' => $allColumns])
                        @endforeach

                        <!-- 2. RELATED TASK CARDS IN KANBAN -->
                        @foreach($columnTasks as $task)
                            <div 
                                wire:key="task-card-{{ $task->id }}"
                                x-data="{ isCompleting: false, isRemoving: false }"
                                :class="{ 'opacity-0 scale-90 -translate-y-3 pointer-events-none transition-all duration-300 ease-out': isCompleting || isRemoving }"
                                class="bg-violet-50/60 border border-violet-200 hover:border-violet-300 rounded-lg p-3 space-y-2 shadow-2xs transition duration-200 group relative select-none">
                                <!-- Task Header: Badge & Assignee -->
                                <div class="flex items-start justify-between gap-1.5 min-w-0">
                                    <div class="flex flex-wrap gap-1 min-w-0">
                                        <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-violet-700 text-white shrink-0 flex items-center gap-1">
                                            <x-lucide-check-square class="w-3 h-3 text-white" />
                                            <span>{{ __('TAREA VINCULADA') }}</span>
                                        </span>
                                        @if($task->type)
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold shrink-0 {{ $task->type === \App\Enums\RelatedTaskType::SUBTASK ? 'bg-amber-100 text-amber-800 border border-amber-200' : 'bg-violet-100 text-violet-800 border border-violet-200' }}">
                                                {{ $task->type->label() }}
                                            </span>
                                        @endif
                                    </div>

                                    <div class="flex items-center gap-1 shrink-0">
                                        <span class="text-[10px] font-medium text-zinc-700 bg-white px-1.5 py-0.5 rounded border border-stone-200 shrink-0">
                                            {{ $task->assignee?->name ?? __('Sin Asignar') }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Task Title & Parent Order Link -->
                                <div class="min-w-0">
                                    <h4 class="font-semibold text-xs text-zinc-900 leading-snug break-words">
                                        {{ $task->title }}
                                    </h4>
                                    @if($task->order)
                                        <p class="text-[10px] text-violet-700 font-medium truncate mt-0.5 flex items-center gap-1 uppercase">
                                            <x-lucide-link class="w-3 h-3 text-violet-500 shrink-0" />
                                            @if($task->order && $task->order->wo_number)
                                                <x-wo-badge :number="$task->order->wo_number" variant="light" />
                                                <span>• {{ $task->order->company_name }}{{ $task->order->location_text ? ' (' . $task->order->location_text . ')' : '' }}</span>
                                            @else
                                                <span>{{ $task->order->wo_number ?? __('Orden') }} • {{ $task->order->company_name }}{{ $task->order->location_text ? ' (' . $task->order->location_text . ')' : '' }}</span>
                                            @endif
                                        </p>
                                    @endif
                                </div>

                                <!-- Task Controls: Quick Complete, Delete & View Order -->
                                <div class="pt-1.5 flex items-center justify-between gap-1.5 border-t border-violet-100 min-w-0">
                                    <div class="flex items-center gap-1">
                                        <button 
                                            @click="isCompleting = true; setTimeout(() => $wire.toggleTaskComplete({{ $task->id }}), 300)"
                                            class="px-2 py-0.5 rounded text-[10px] font-semibold bg-white hover:bg-emerald-50 text-emerald-800 border border-emerald-200 transition flex items-center gap-1 shadow-2xs">
                                            <x-lucide-check-circle-2 class="w-3 h-3 text-emerald-600" />
                                            <span>{{ __('Completar') }}</span>
                                        </button>
                                        <button 
                                            wire:click="deleteTask({{ $task->id }})"
                                            wire:confirm="{{ __('¿Eliminar esta tarea vinculada?') }}"
                                            @click.stop
                                            class="px-1.5 py-0.5 rounded bg-white hover:bg-rose-50 border border-rose-200 text-rose-600 hover:text-rose-700 transition flex items-center gap-1 text-[10px] shadow-2xs"
                                            title="{{ __('Eliminar tarea') }}">
                                            <x-lucide-trash-2 class="w-3 h-3" />
                                            <span>{{ __('Eliminar') }}</span>
                                        </button>
                                    </div>

                                    @if($task->order)
                                        <button wire:click="$dispatch('open-order-detail', { orderId: {{ $task->order->id }} })" class="px-2 py-0.5 rounded bg-white hover:bg-stone-100 border border-stone-200 text-[10px] font-medium text-zinc-700 transition flex items-center gap-1">
                                            <x-lucide-panel-right class="w-3 h-3 text-zinc-500" />
                                            <span>{{ __('Ver Orden') }}</span>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endforeach

                        <!-- 3. REGULAR ORDER CARDS IN KANBAN -->
                        @foreach($regularColumnOrders as $order)
                            @include('livewire.kanban.card', ['order' => $order, 'allColumns' => $allColumns])
                        @endforeach
                    @endif
                </div>

            </div>
            @endif
        @endforeach
    </div>

    <!-- Create Order Modal is registered in app layout -->

    <!-- On Hold Reason Modal -->
    @if($showOnHoldModal)
        <div class="fixed inset-0 z-[100] bg-black/40 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white border border-[#e9e9e7] rounded-2xl w-full max-w-md p-5 space-y-4 shadow-2xl">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-zinc-900 flex items-center gap-1.5">
                            <x-lucide-pause-circle class="w-5 h-5 text-amber-600 shrink-0" />
                            <span>Motivo para Poner en On Hold</span>
                        </h3>
                        <p class="text-xs text-zinc-500 mt-0.5">Ingresa la razón por la que esta orden entra en pausa.</p>
                    </div>
                    <button wire:click="cancelOnHold" class="p-1 rounded-md text-zinc-400 hover:text-zinc-700 hover:bg-stone-100 transition">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                </div>

                <div class="space-y-1.5 text-xs">
                    <label class="font-medium text-zinc-700 block">Motivo / Comentario:</label>
                    <textarea wire:model="onHoldReason" rows="3" placeholder="Ej: Esperando confirmación de presupuesto por parte del cliente..." class="w-full bg-[#fbfbfa] border border-[#e9e9e7] rounded-lg p-2.5 text-xs text-zinc-900 focus:outline-none focus:border-stone-400"></textarea>
                    @error('onHoldReason')
                        <span class="text-red-600 text-[11px] block mt-0.5">{{ $message }}</span>
                    @enderror
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-2">
                    <button wire:click="cancelOnHold" class="px-3 py-1.5 rounded-md bg-stone-100 text-zinc-700 text-xs font-medium hover:bg-stone-200 transition">
                        Cancelar
                    </button>
                    <button wire:click="confirmOnHold" class="px-3.5 py-1.5 rounded-md bg-amber-600 hover:bg-amber-500 text-white font-medium text-xs shadow-2xs transition flex items-center gap-1 cursor-pointer">
                        <x-lucide-check-circle-2 class="w-3.5 h-3.5" />
                        <span>Poner en On Hold</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Resume Order Modal -->
    @if($showResumeModal)
        <div class="fixed inset-0 z-[100] bg-black/40 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white border border-[#e9e9e7] rounded-2xl w-full max-w-md p-5 space-y-4 shadow-2xl animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-zinc-900 flex items-center gap-1.5">
                            <x-lucide-play-circle class="w-5 h-5 text-emerald-600 shrink-0" />
                            <span>Motivo para Reanudar Orden</span>
                        </h3>
                        <p class="text-xs text-zinc-500 mt-0.5">Ingresa el motivo por el cual la orden sale de On Hold y retoma trabajo.</p>
                    </div>
                    <button wire:click="cancelResume" class="p-1 rounded-md text-zinc-400 hover:text-zinc-700 hover:bg-stone-100 transition">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                </div>

                <div class="space-y-1.5 text-xs">
                    <label class="font-medium text-zinc-700 block">Motivo / Nota de Reanudación:</label>
                    <textarea wire:model="resumeReason" rows="3" placeholder="Ej: Cliente aprobó presupuesto / Se recibieron las medidas del cliente..." class="w-full bg-[#fbfbfa] border border-[#e9e9e7] rounded-lg p-2.5 text-xs text-zinc-900 focus:outline-none focus:border-stone-400"></textarea>
                    @error('resumeReason')
                        <span class="text-red-600 text-[11px] block mt-0.5">{{ $message }}</span>
                    @enderror
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-2">
                    <button wire:click="cancelResume" class="px-3 py-1.5 rounded-md bg-stone-100 text-zinc-700 text-xs font-medium hover:bg-stone-200 transition">
                        Cancelar
                    </button>
                    <button wire:click="confirmResume" class="px-3.5 py-1.5 rounded-md bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs shadow-2xs transition flex items-center gap-1 cursor-pointer">
                        <x-lucide-play class="w-3.5 h-3.5" />
                        <span>Reanudar Orden</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Archive Order Substatus Selection Modal -->
    @if($showArchiveModal)
        <div class="fixed inset-0 z-[100] bg-black/40 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white border border-[#e9e9e7] rounded-2xl w-full max-w-md p-5 space-y-4 shadow-2xl animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-start justify-between border-b border-[#e9e9e7] pb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-rose-50 border border-rose-200 flex items-center justify-center text-rose-600 shrink-0">
                            <x-lucide-archive class="w-4 h-4" />
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-zinc-900">{{ __('Archivar Orden') }}</h3>
                            <p class="text-xs text-zinc-500">{{ __('Selecciona el subestatus de cierre para esta orden.') }}</p>
                        </div>
                    </div>
                    <button wire:click="closeArchiveModal" class="p-1 rounded-md text-zinc-400 hover:text-zinc-700 hover:bg-stone-100 transition">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <label class="font-bold text-zinc-700 block">{{ __('Razón o subestatus de cierre:') }}</label>
                    
                    <div class="space-y-2">
                        <label class="flex items-center justify-between p-3 rounded-xl border border-stone-200 hover:border-emerald-300 hover:bg-emerald-50/40 cursor-pointer transition" :class="$wire.archiveSubstatus === 'FINALIZADA !' ? 'bg-emerald-50 border-emerald-300 ring-1 ring-emerald-400' : 'bg-[#fbfbfa]'">
                            <div class="flex items-center gap-2.5">
                                <input type="radio" wire:model="archiveSubstatus" value="FINALIZADA !" class="text-emerald-600 focus:ring-emerald-500">
                                <div>
                                    <span class="font-bold text-zinc-900 flex items-center gap-1.5">
                                        <x-lucide-check-circle-2 class="w-4 h-4 text-emerald-600 shrink-0" />
                                        <span>{{ __('Finalizada con Éxito') }}</span>
                                    </span>
                                    <span class="text-[11px] text-zinc-500 block mt-0.5">{{ __('Trabajo completado y entregado al cliente.') }}</span>
                                </div>
                            </div>
                        </label>

                        <label class="flex items-center justify-between p-3 rounded-xl border border-stone-200 hover:border-amber-300 hover:bg-amber-50/40 cursor-pointer transition" :class="$wire.archiveSubstatus === 'CLIENTE NO RESPONSIVE' ? 'bg-amber-50 border-amber-300 ring-1 ring-amber-400' : 'bg-[#fbfbfa]'">
                            <div class="flex items-center gap-2.5">
                                <input type="radio" wire:model="archiveSubstatus" value="CLIENTE NO RESPONSIVE" class="text-amber-600 focus:ring-amber-500">
                                <div>
                                    <span class="font-bold text-zinc-900 flex items-center gap-1.5">
                                        <x-lucide-user-x class="w-4 h-4 text-amber-600 shrink-0" />
                                        <span>{{ __('Cliente No Responsive') }}</span>
                                    </span>
                                    <span class="text-[11px] text-zinc-500 block mt-0.5">{{ __('Sin respuesta o no retiró la orden tras largo tiempo.') }}</span>
                                </div>
                            </div>
                        </label>

                        <label class="flex items-center justify-between p-3 rounded-xl border border-stone-200 hover:border-red-300 hover:bg-red-50/40 cursor-pointer transition" :class="$wire.archiveSubstatus === 'CANCELADA' ? 'bg-red-50 border-red-300 ring-1 ring-red-400' : 'bg-[#fbfbfa]'">
                            <div class="flex items-center gap-2.5">
                                <input type="radio" wire:model="archiveSubstatus" value="CANCELADA" class="text-red-600 focus:ring-red-500">
                                <div>
                                    <span class="font-bold text-zinc-900 flex items-center gap-1.5">
                                        <x-lucide-x-circle class="w-4 h-4 text-red-600 shrink-0" />
                                        <span>{{ __('Cancelada') }}</span>
                                    </span>
                                    <span class="text-[11px] text-zinc-500 block mt-0.5">{{ __('Orden anulada o no realizada.') }}</span>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-[#e9e9e7]">
                    <button wire:click="closeArchiveModal" class="px-3 py-1.5 rounded-md bg-stone-100 text-zinc-700 text-xs font-medium hover:bg-stone-200 transition">
                        {{ __('Cancelar') }}
                    </button>
                    <button wire:click="confirmArchive" class="px-3.5 py-1.5 rounded-md bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs shadow-2xs transition flex items-center gap-1.5 cursor-pointer">
                        <x-lucide-archive class="w-3.5 h-3.5" />
                        <span>{{ __('Archivar Orden') }}</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Block Order Modal -->
    @if($showBlockModal)
        <div class="fixed inset-0 z-[100] bg-black/40 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white border border-[#e9e9e7] rounded-2xl w-full max-w-md p-5 space-y-4 shadow-2xl">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-zinc-900 flex items-center gap-1.5">
                            <x-lucide-alert-octagon class="w-5 h-5 text-rose-600 shrink-0" />
                            <span>{{ __('Bloquear Orden') }}</span>
                        </h3>
                        <p class="text-xs text-zinc-500 mt-0.5">{{ __('Indica la razón por la que esta orden no puede avanzar.') }}</p>
                    </div>
                    <button wire:click="cancelBlock" class="p-1 rounded-md text-zinc-400 hover:text-zinc-700 hover:bg-stone-100 transition">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="font-medium text-zinc-700 block mb-1.5">{{ __('Motivo del Bloqueo:') }}</label>
                        <div class="grid grid-cols-2 gap-1.5">
                            @foreach([
                                'FALTAN MEDIDAS' => 'Faltan Medidas',
                                'FALTA LOGO' => 'Falta Logo / Arte',
                                'FALTA APROBACIÓN DE ESTIMADO' => 'Falta Aprobación Estimado',
                                'ESPERANDO CLIENTE' => 'Esperando Cliente',
                                'OTROS' => 'Otro Motivo'
                            ] as $value => $label)
                                <button type="button" 
                                        wire:click="$set('blockReason', '{{ $value }}')" 
                                        class="px-2.5 py-1.5 rounded-lg border text-[11px] font-medium text-left transition flex items-center justify-between {{ $blockReason === $value ? 'bg-rose-50 border-rose-300 text-rose-800 font-semibold shadow-2xs' : 'bg-stone-50 border-stone-200 text-zinc-700 hover:bg-stone-100' }}">
                                    <span>{{ $label }}</span>
                                    @if($blockReason === $value)
                                        <x-lucide-check class="w-3.5 h-3.5 text-rose-600 shrink-0" />
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </div>

                    @if($blockReason === 'OTROS')
                        <div class="space-y-1">
                            <label class="font-medium text-zinc-700 block">{{ __('Especificar Otro Motivo:') }}</label>
                            <input type="text" wire:model="blockReasonOther" placeholder="Ej: Esperando material especial de proveedor..." class="w-full bg-[#fbfbfa] border border-[#e9e9e7] rounded-lg px-2.5 py-1.5 text-xs text-zinc-900 focus:outline-none focus:border-stone-400">
                        </div>
                    @endif

                    <div class="space-y-1">
                        <label class="font-medium text-zinc-700 block">{{ __('Detalles o Comentarios Adicionales (Opcional):') }}</label>
                        <textarea wire:model="blockComment" rows="2" placeholder="Explica brevemente la situación..." class="w-full bg-[#fbfbfa] border border-[#e9e9e7] rounded-lg p-2.5 text-xs text-zinc-900 focus:outline-none focus:border-stone-400"></textarea>
                    </div>

                    <div class="pt-1 border-t border-stone-100">
                        <label class="flex items-center gap-2 cursor-pointer text-zinc-800 font-medium">
                            <input type="checkbox" wire:model="requireCustomerService" class="rounded border-stone-300 text-rose-600 focus:ring-rose-500 w-4 h-4">
                            <span>{{ __('Requiere atención / seguimiento del cliente o responsable') }}</span>
                        </label>
                        <p class="text-[11px] text-zinc-500 pl-6 mt-0.5">{{ __('Creará una tarea pendiente para dar seguimiento con el contacto o responsable del cliente.') }}</p>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-2">
                    <button wire:click="cancelBlock" class="px-3 py-1.5 rounded-md bg-stone-100 text-zinc-700 text-xs font-medium hover:bg-stone-200 transition">
                        {{ __('Cancelar') }}
                    </button>
                    <button wire:click="confirmBlock" class="px-3.5 py-1.5 rounded-md bg-rose-600 hover:bg-rose-500 text-white font-medium text-xs shadow-2xs transition flex items-center gap-1">
                        <x-lucide-alert-octagon class="w-3.5 h-3.5" />
                        <span>{{ __('Bloquear Orden') }}</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Unblock Order Modal -->
    @if($showUnblockModal)
        <div class="fixed inset-0 z-[100] bg-black/40 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white border border-[#e9e9e7] rounded-2xl w-full max-w-md p-5 space-y-4 shadow-2xl">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-zinc-900 flex items-center gap-1.5">
                            <x-lucide-check-circle-2 class="w-5 h-5 text-emerald-600 shrink-0" />
                            <span>{{ __('Desbloquear Orden') }}</span>
                        </h3>
                        <p class="text-xs text-zinc-500 mt-0.5">{{ __('La orden volverá a la lista del diseñador asignado.') }}</p>
                    </div>
                    <button wire:click="cancelUnblock" class="p-1 rounded-md text-zinc-400 hover:text-zinc-700 hover:bg-stone-100 transition">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <label class="font-medium text-zinc-700 block">{{ __('¿Cómo se resolvió el bloqueo?') }}</label>
                    
                    <div class="flex flex-wrap gap-1.5">
                        @foreach([
                            'Medidas confirmadas',
                            'Logo / Arte recibido',
                            'Estimado aprobado',
                            'Respuesta recibida del cliente'
                        ] as $preset)
                            <button type="button" 
                                    wire:click="$set('unblockReason', '{{ $preset }}')" 
                                    class="px-2.5 py-1.5 rounded-lg border text-[11px] font-medium transition {{ $unblockReason === $preset ? 'bg-emerald-50 border-emerald-300 text-emerald-800 font-semibold shadow-2xs' : 'bg-stone-50 border-stone-200 text-zinc-700 hover:bg-stone-100' }}">
                                {{ $preset }}
                            </button>
                        @endforeach
                    </div>

                    <div class="space-y-1 pt-1">
                        <input type="text" wire:model="unblockReason" placeholder="Escribe o selecciona un motivo..." class="w-full bg-[#fbfbfa] border border-[#e9e9e7] rounded-lg px-2.5 py-1.5 text-xs text-zinc-900 focus:outline-none focus:border-stone-400">
                        @error('unblockReason')
                            <span class="text-red-600 text-[11px] block mt-0.5">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-2">
                    <button wire:click="cancelUnblock" class="px-3 py-1.5 rounded-md bg-stone-100 text-zinc-700 text-xs font-medium hover:bg-stone-200 transition">
                        {{ __('Cancelar') }}
                    </button>
                    <button wire:click="confirmUnblock" class="px-3.5 py-1.5 rounded-md bg-emerald-600 hover:bg-emerald-500 text-white font-medium text-xs shadow-2xs transition flex items-center gap-1">
                        <x-lucide-check-circle-2 class="w-3.5 h-3.5" />
                        <span>{{ __('Desbloquear Orden') }}</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>
