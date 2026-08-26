<div wire:poll.3s class="h-full flex flex-col space-y-3 min-h-0 overflow-hidden">
    
    @if(isset($newOrdersCount) && $newOrdersCount > 0)
        <div class="bg-amber-50/90 border border-amber-200 rounded-xl px-3.5 py-2 flex items-center justify-between gap-3 text-xs shrink-0 shadow-2xs">
            <div class="flex items-center gap-2 text-amber-900 font-medium truncate">
                <span class="flex h-2 w-2 relative shrink-0">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
                </span>
                <span>{{ __('Hay') }} <strong>{{ $newOrdersCount }}</strong> {{ $newOrdersCount === 1 ? __('nueva orden') : __('nuevas órdenes') }} {{ __('de Trello sin revisar en el Backlog.') }}</span>
            </div>
            <a href="{{ route('backlog') }}" class="px-2.5 py-1 rounded-md bg-amber-100 hover:bg-amber-200 border border-amber-300 text-amber-900 text-[11px] font-bold transition shrink-0 flex items-center gap-1">
                <span>{{ __('Ver Nuevas Órdenes') }}</span>
                <x-lucide-arrow-right class="w-3 h-3 text-amber-800" />
            </a>
        </div>
    @endif
    
    <!-- Top Notion-Style Header Controls -->
    <div id="tour-kanban-header" class="bg-white border border-[#e9e9e7] rounded-xl p-3 space-y-2.5 shadow-2xs shrink-0">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 min-w-0">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-9 h-9 rounded-lg bg-stone-900 text-white flex items-center justify-center shrink-0 shadow-2xs">
                    <x-lucide-kanban class="w-4.5 h-4.5 text-stone-100" />
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h1 class="text-base sm:text-lg font-bold text-zinc-900 tracking-tight">{{ __('Kanban Board') }}</h1>
                        <span class="px-2 py-0.5 rounded-full bg-stone-100 border border-stone-200 text-[10px] font-bold text-zinc-600">9 {{ __('Listas') }}</span>
                    </div>
                    <p class="text-xs text-zinc-500 truncate mt-0.5">{{ __('Arrastra y suelta tarjetas entre listas para actualizar su estado en tiempo real.') }}</p>
                </div>
            </div>

            <div class="flex items-center gap-2 shrink-0 self-end sm:self-auto">
                <button 
                    id="tour-kanban-new-btn"
                    @click="$dispatch('open-create-order')" 
                    class="px-3 py-1.5 h-8 rounded-lg bg-stone-900 hover:bg-stone-800 text-white text-xs font-semibold shadow-2xs transition flex items-center gap-1.5 shrink-0">
                    <x-lucide-plus class="w-3.5 h-3.5 text-white" />
                    <span>{{ __('Nueva Orden') }}</span>
                </button>

                <a href="{{ route('trash') }}" class="px-2.5 py-1.5 h-8 rounded-lg bg-rose-50 hover:bg-rose-100 border border-rose-200 text-rose-700 hover:text-rose-900 text-xs font-semibold transition flex items-center gap-1.5 shrink-0" title="{{ __('Ver papelera') }}">
                    <x-lucide-trash-2 class="w-3.5 h-3.5" />
                    <span>{{ __('Papelera') }}</span>
                </a>
            </div>
        </div>

        <div class="pt-3 border-t border-[#f0f0ee] flex flex-wrap items-center gap-2 w-full">
            <!-- Search Input with Live Occurrences Dropdown -->
            <div class="relative flex-1 min-w-[200px] sm:min-w-[240px] max-w-sm" x-data="{ open: true }" x-dropdown-nav @click.outside="open = false">
                <x-lucide-search class="w-3.5 h-3.5 text-zinc-400 absolute left-3 top-2.5 shrink-0 z-10" />
                <input type="text" 
                       id="tour-kanban-search"
                       wire:model.live.debounce.200ms="search" 
                       @focus="open = true" 
                       @input="open = true"
                       placeholder="{{ __('Buscar empresa o tarea...') }}" 
                       class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-lg pl-8 pr-3 py-1.5 h-8 text-xs text-zinc-800 focus:border-stone-400 focus:outline-none w-full">

                @if(strlen(trim($search)) >= 2)
                    <div x-show="open" 
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         class="absolute left-0 right-0 top-full mt-1.5 z-50 bg-white border border-[#e9e9e7] rounded-xl shadow-xl max-h-72 overflow-y-auto p-1.5 text-xs">
                        <div class="px-2 py-1 text-[10px] font-semibold uppercase tracking-wider text-zinc-400 border-b border-[#f0f0ee] mb-1 flex items-center justify-between">
                            <span>{{ __('Coincidencias') }} ({{ $this->searchResults->count() }})</span>
                            <span class="text-[9px] font-mono text-zinc-400">{{ __('Clic para abrir') }}</span>
                        </div>

                        @forelse($this->searchResults as $result)
                            <button wire:click="selectSearchResult({{ $result->id }})" 
                                    @click="open = false"
                                    class="w-full text-left p-2 rounded-lg hover:bg-stone-100 focus:bg-stone-100 focus:outline-none transition flex items-center justify-between gap-2 group cursor-pointer">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5">
                                        @if($result->wo_number)
                                            <span class="font-mono text-[10px] font-bold px-1.5 py-0.2 rounded bg-stone-200 text-zinc-700 shrink-0">
                                                {{ $result->wo_number }}
                                            </span>
                                        @endif
                                        <span class="font-semibold text-zinc-900 truncate group-hover:text-stone-900 text-xs">
                                            {{ $result->company_name }}
                                        </span>
                                    </div>
                                    <p class="text-[11px] text-zinc-500 truncate mt-0.5" title="{{ $result->task_name }}">{{ $result->task_name }}</p>
                                </div>

                                <div class="flex items-center gap-1.5 shrink-0">
                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-medium border bg-stone-50 text-zinc-600 border-stone-200 shrink-0">
                                        {{ $result->core_status->label() }}
                                    </span>
                                    <x-lucide-chevron-right class="w-3.5 h-3.5 text-zinc-400 group-hover:text-zinc-700 transition shrink-0" />
                                </div>
                            </button>
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
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    class="absolute left-0 top-full mt-1 z-50 bg-white border border-[#e9e9e7] rounded-lg shadow-xl w-52 max-h-56 overflow-y-auto divide-y divide-stone-100 text-xs">
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
                    <span class="truncate">{{ $responsibleFilter === 'all' ? __('Responsables (Todos)') : $responsibleFilter }}</span>
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
                            {{ $designers->firstWhere('id', $designerFilter)?->name ?? __('Diseñadores (Todos)') }}
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
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    class="absolute left-0 top-full mt-1 z-50 bg-white border border-[#e9e9e7] rounded-lg shadow-xl w-48 max-h-56 overflow-y-auto divide-y divide-stone-100 text-xs">
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
                            <span class="px-2 py-0.5 rounded text-[10px] font-medium border {{ $sub->badgeStyle() }}">
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
    <div id="tour-kanban-group-tabs" class="flex items-center gap-1 border-b border-[#e9e9e7] pb-2 overflow-x-auto scrollbar-none text-xs shrink-0">
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
                        @php
                            $archivedTotalCount = \App\Models\Order::archived()->count();
                        @endphp
                        <a href="/archived" title="{{ __('Ver Órdenes Archivadas') }}" class="flex items-center gap-1 px-2 py-0.5 rounded bg-white text-[11px] font-mono text-zinc-700 border border-stone-300 font-bold shrink-0 hover:bg-stone-100 transition">
                            <x-lucide-external-link class="w-3 h-3 text-zinc-500" />
                            <span>{{ $archivedTotalCount }}</span>
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
                        <a href="/archived" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white font-medium text-xs shadow-2xs transition mt-2">
                            <x-lucide-bar-chart-2 class="w-3.5 h-3.5 text-zinc-300" />
                            <span>{{ __('Ver Rendimiento') }}</span>
                        </a>
                    </div>
                </div>
            @else
                @php
                    $columnOrders = $orders->filter(fn($o) => $o->core_status === $column);
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
                        <span class="w-2 h-2 rounded-full shrink-0 bg-stone-600"></span>
                        <h3 class="font-semibold text-xs text-zinc-800 uppercase tracking-wider truncate">{{ $column->label() }}</h3>
                    </div>
                    @php
                        $columnTasks = match ($column) {
                            \App\Enums\CoreStatus::TO_DO_TODAY => $relatedTasks->filter(fn($t) => $t->order !== null && !$t->isDone() && $t->type !== \App\Enums\RelatedTaskType::BLOCKED),
                            \App\Enums\CoreStatus::ENTRANTE => $relatedTasks->filter(fn($t) => $t->order !== null && !$t->isDone() && ($t->type === \App\Enums\RelatedTaskType::BLOCKED || $t->type === \App\Enums\RelatedTaskType::RESOLVER)),
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
                            <div 
                                wire:key="order-card-{{ $order->id }}"
                                @click="$dispatch('open-order-detail', { orderId: {{ $order->id }} })"
                                draggable="true"
                                @dragstart="event.dataTransfer.setData('text/plain', '{{ $order->id }}')"
                                class="rounded-xl p-3 space-y-2 transition cursor-pointer active:cursor-grabbing group relative select-none hover:shadow-lg {{ $order->done_today ? 'bg-[#fafaf9] border border-stone-200/90 shadow-2xs opacity-75 ring-0' : 'bg-gradient-to-br from-rose-50/90 via-white to-red-50/70 border-2 border-red-500/90 shadow-md ring-2 ring-red-300/40' }}">
                                
                                <!-- Card Header: Badges & Designer -->
                                <div class="flex items-start justify-between gap-1.5 min-w-0">
                                    <div class="flex flex-wrap gap-1 min-w-0">
                                        @if($order->is_missing_from_trello)
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-stone-200 text-stone-700 border border-stone-300 shrink-0 whitespace-nowrap flex items-center gap-0.5">
                                                <x-lucide-alert-triangle class="w-2.5 h-2.5 text-stone-600" />
                                                <span>FALTA EN TRELLO</span>
                                            </span>
                                        @endif
                                        @if($order->wo_number)
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-mono font-bold bg-stone-900 text-white shrink-0 whitespace-nowrap">
                                                {{ $order->wo_number }}
                                            </span>
                                        @endif

                                        <!-- URGENTE Badge (Muted when done_today) -->
                                        @if($order->done_today)
                                            <span class="px-2 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider bg-stone-200 text-stone-600 border border-stone-300 flex items-center gap-1 shrink-0 opacity-80" title="Urgente (Completado para hoy)">
                                                <x-lucide-check class="w-2.5 h-2.5 text-stone-500" />
                                                <span>URGENTE</span>
                                            </span>
                                        @else
                                            <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-red-600 text-white shadow-2xs shadow-red-500/30 flex items-center gap-1.5 shrink-0">
                                                <span class="w-1.5 h-1.5 rounded-full bg-white animate-ping"></span>
                                                <span>URGENTE</span>
                                            </span>
                                        @endif

                                        @if($order->responsible_person)
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-indigo-50 text-indigo-800 border border-indigo-200 shrink-0 whitespace-nowrap flex items-center gap-1">
                                                <x-lucide-user class="w-2.5 h-2.5 text-indigo-600 shrink-0" />
                                                <span>{{ $order->responsible_person }}</span>
                                            </span>
                                        @endif
                                        @if($order->substatus && $order->substatus->value !== 'URGENTE')
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-medium border shrink-0 whitespace-nowrap {{ $order->substatus->badgeStyle() }}">
                                                {{ $order->substatus->value }}
                                            </span>
                                        @endif
                                        @if($order->customer_service_required)
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-pink-50 text-pink-700 border border-pink-200 shrink-0 whitespace-nowrap">
                                                ATENCIÓN CLIENTE
                                            </span>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-1 shrink-0 ml-1">
                                        @if($order->trello_url)
                                            <a href="{{ $order->trello_url }}" @click.stop target="_blank" rel="noopener noreferrer" class="p-1 rounded text-blue-600 hover:text-blue-800 hover:bg-blue-50 transition shrink-0" title="Abrir en Trello.com">
                                                <x-lucide-external-link class="w-3.5 h-3.5" />
                                            </a>
                                        @endif
                                        <div class="flex flex-wrap items-center gap-1 shrink-0 justify-end">
                                            @forelse($order->assigned_designers as $des)
                                                <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded border shrink-0 whitespace-nowrap {{ $des->badge_style }}">
                                                    {{ $des->name }}
                                                </span>
                                            @empty
                                                <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded border border-amber-300 bg-amber-100 text-amber-800 shrink-0 whitespace-nowrap">
                                                    Sin Asignar
                                                </span>
                                            @endforelse
                                        </div>
                                    </div>
                                </div>

                                <!-- Card Title & Company -->
                                <div class="min-w-0 flex items-start gap-2">
                                    <button 
                                        wire:click="toggleDoneToday({{ $order->id }})" 
                                        @click.stop
                                        type="button"
                                        class="w-4 h-4 mt-0.5 rounded-full border transition flex items-center justify-center shrink-0 cursor-pointer {{ $order->done_today ? 'bg-emerald-500 border-emerald-500 text-white shadow-2xs' : 'border-stone-300 hover:border-emerald-500 bg-white text-transparent hover:text-emerald-500/40' }}"
                                        title="{{ $order->done_today ? 'Completado (Clic para desmarcar)' : 'Marcar como completado' }}">
                                        <x-lucide-check class="w-2.5 h-2.5 stroke-[3]" />
                                    </button>
                                    <div class="min-w-0 flex-1">
                                        <h4 class="font-normal text-[11px] text-zinc-600 truncate leading-snug {{ $order->done_today ? 'line-through text-zinc-400' : '' }}" title="{{ $order->company_name }}">{{ $order->company_name }}</h4>
                                        <p class="font-bold text-xs text-zinc-900 group-hover:text-stone-800 transition truncate mt-0.5 {{ $order->done_today ? 'line-through text-zinc-400' : '' }}" title="{{ $order->task_name }}">{{ $order->task_name }}</p>
                                    </div>
                                </div>

                                <!-- Metadata & Due Date -->
                                <div class="flex items-center justify-between text-[10px] pt-1.5 border-t gap-1 {{ $order->done_today ? 'border-stone-200 text-zinc-500' : 'border-red-200/60' }}">
                                    <div class="flex items-center gap-1 min-w-0">
                                        <x-lucide-calendar class="w-3.5 h-3.5 {{ $order->done_today ? 'text-zinc-400' : 'text-red-600' }} shrink-0" />
                                        <span class="font-mono font-medium truncate {{ $order->done_today ? 'text-zinc-500' : 'text-red-700 font-bold' }}">
                                            {{ $order->current_due_date ? $order->current_due_date->format('d M') : 'N/A' }}
                                        </span>
                                    </div>

                                    @if($order->relatedTasks->where('status', 'todo')->count() > 0)
                                        <span class="px-1.5 py-0.5 rounded bg-violet-100 text-violet-800 font-semibold border border-violet-200 flex items-center gap-1 shrink-0 whitespace-nowrap" title="Tareas vinculadas activas">
                                            <x-lucide-check-square class="w-3 h-3 text-violet-600 shrink-0" />
                                            <span>{{ $order->relatedTasks->where('status', 'todo')->count() }} Tareas</span>
                                        </span>
                                    @endif
                                </div>

                                <!-- Quick Move Select & Modal Trigger -->
                                <div class="pt-1.5 flex items-center justify-between gap-1.5 border-t min-w-0 {{ $order->done_today ? 'border-stone-200' : 'border-red-200/60' }}">
                                    <select wire:change="moveOrder({{ $order->id }}, $event.target.value)" @click.stop class="rounded px-1.5 py-0.5 text-[10px] focus:outline-none w-full min-w-0 truncate font-medium {{ $order->done_today ? 'bg-stone-50 border-stone-200 text-zinc-600' : 'bg-white border-red-200 hover:border-red-300 text-zinc-800' }}">
                                        <option value="">Mover a...</option>
                                        @foreach($allColumns as $colOption)
                                            @if($colOption !== $order->core_status)
                                                <option value="{{ $colOption->value }}">{{ $colOption->label() }}</option>
                                            @endif
                                        @endforeach
                                    </select>

                                    <div class="shrink-0 flex items-center gap-1">
                                        <button wire:click="$dispatch('open-duplicate-order', { orderId: {{ $order->id }} })" @click.stop class="px-1.5 py-0.5 rounded border text-[10px] font-medium transition flex items-center gap-1 {{ $order->done_today ? 'bg-stone-100 hover:bg-stone-200 border-stone-200 text-zinc-600' : 'bg-white hover:bg-rose-100 border-red-200 text-zinc-700 hover:text-zinc-900' }}" title="Duplicar Orden">
                                            <x-lucide-copy class="w-3 h-3 text-zinc-500" />
                                            <span>Duplicar</span>
                                        </button>
                                        <button wire:click="$dispatch('open-order-detail', { orderId: {{ $order->id }} })" @click.stop class="p-1 rounded border text-[10px] font-medium transition flex items-center gap-1 {{ $order->done_today ? 'bg-stone-100 hover:bg-stone-200 border-stone-200 text-zinc-600' : 'bg-white hover:bg-rose-100 border-red-200 text-zinc-700 hover:text-zinc-900' }}" title="Ver detalle de la orden">
                                            <x-lucide-panel-right class="w-3.5 h-3.5 text-zinc-600" />
                                        </button>
                                        <button
                                            wire:click="trashOrder({{ $order->id }})"
                                            @click.stop
                                            wire:confirm="¿Mover esta orden a la papelera?"
                                            class="px-1.5 py-0.5 rounded border text-[10px] font-medium transition flex items-center gap-1 {{ $order->done_today ? 'bg-stone-100 hover:bg-red-50 border-stone-200 text-zinc-500 hover:text-red-600' : 'bg-white hover:bg-red-100 border-red-200 text-red-600 hover:text-red-800' }}"
                                            title="Mover a la papelera"
                                        >
                                            <x-lucide-trash-2 class="w-3 h-3" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach

                        <!-- 2. RELATED TASK CARDS IN KANBAN -->
                        @foreach($columnTasks as $task)
                            <div 
                                wire:key="task-card-{{ $task->id }}"
                                x-data="{ isCompleting: false }"
                                :class="{ 'opacity-0 scale-90 -translate-y-3 pointer-events-none transition-all duration-300 ease-out': isCompleting }"
                                class="bg-violet-50/60 border border-violet-200 hover:border-violet-300 rounded-lg p-3 space-y-2 shadow-2xs transition duration-200 group relative select-none">
                                <!-- Task Header: Badge & Assignee -->
                                <div class="flex items-start justify-between gap-1.5 min-w-0">
                                    <div class="flex flex-wrap gap-1 min-w-0">
                                        <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-violet-700 text-white shrink-0 flex items-center gap-1">
                                            <x-lucide-check-square class="w-3 h-3 text-white" />
                                            <span>TAREA VINCULADA</span>
                                        </span>
                                        @if($task->type)
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-violet-100 text-violet-800 border border-violet-200 shrink-0">
                                                {{ $task->type->label() }}
                                            </span>
                                        @endif
                                    </div>

                                    <div class="flex items-center gap-1 shrink-0">
                                        <span class="text-[10px] font-medium text-zinc-700 bg-white px-1.5 py-0.5 rounded border border-stone-200 shrink-0">
                                            {{ $task->assignee?->name ?? 'Sin Asignar' }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Task Title & Parent Order Link -->
                                <div class="min-w-0">
                                    <h4 class="font-semibold text-xs text-zinc-900 leading-snug break-words">
                                        {{ $task->title }}
                                    </h4>
                                    @if($task->order)
                                        <p class="text-[10px] text-violet-700 font-medium truncate mt-0.5 flex items-center gap-1">
                                            <x-lucide-link class="w-3 h-3 text-violet-500 shrink-0" />
                                            <span>{{ $task->order->wo_number ?? 'Orden' }} • {{ $task->order->company_name }}</span>
                                        </p>
                                    @endif
                                </div>

                                <!-- Task Controls: Quick Complete & View Order -->
                                <div class="pt-1.5 flex items-center justify-between gap-1.5 border-t border-violet-100 min-w-0">
                                    <button 
                                        @click="isCompleting = true; setTimeout(() => $wire.toggleTaskComplete({{ $task->id }}), 300)"
                                        class="px-2 py-0.5 rounded text-[10px] font-semibold bg-white hover:bg-emerald-50 text-emerald-800 border border-emerald-200 transition flex items-center gap-1 shadow-2xs">
                                        <x-lucide-check-circle-2 class="w-3 h-3 text-emerald-600" />
                                        <span>Completar</span>
                                    </button>

                                    @if($task->order)
                                        <button wire:click="$dispatch('open-order-detail', { orderId: {{ $task->order->id }} })" class="px-2 py-0.5 rounded bg-white hover:bg-stone-100 border border-stone-200 text-[10px] font-medium text-zinc-700 transition flex items-center gap-1">
                                            <x-lucide-panel-right class="w-3 h-3 text-zinc-500" />
                                    @endif
                                </div>
                            </div>
                        @endforeach

                        <!-- 3. REGULAR ORDER CARDS IN KANBAN -->
                        @foreach($regularColumnOrders as $order)
                            <div 
                                wire:key="order-card-{{ $order->id }}"
                                @click="$dispatch('open-order-detail', { orderId: {{ $order->id }} })"
                                draggable="true"
                                @dragstart="event.dataTransfer.setData('text/plain', '{{ $order->id }}')"
                                class="rounded-lg p-3 space-y-2 shadow-2xs transition cursor-pointer active:cursor-grabbing group relative select-none hover:shadow-md {{ $order->getCardBgClass() }}"
                                @if($order->is_missing_from_trello) style="border: 1.5px dashed #a8a29e !important; background-color: #f5f5f4 !important; opacity: 0.75 !important;" @elseif($order->isOverdue()) style="border: 1px solid #ef4444 !important; background-color: #fef2f2 !important;" @elseif($order->isDueToday()) style="border: 1px solid #f59e0b !important; background-color: #fffbeb !important;" @elseif($order->isApproved() || $order->isInProduction()) style="border: 1px solid #f472b6 !important; background-color: #fdf2f8 !important;" @endif>
                                
                                <!-- Card Header: Badges & Designer -->
                                <div class="flex items-start justify-between gap-1.5 min-w-0">
                                    <div class="flex flex-wrap gap-1 min-w-0">
                                        @if($order->is_missing_from_trello)
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-stone-200 text-stone-700 border border-stone-300 shrink-0 whitespace-nowrap flex items-center gap-0.5">
                                                <x-lucide-alert-triangle class="w-2.5 h-2.5 text-stone-600" />
                                                <span>FALTA EN TRELLO</span>
                                            </span>
                                        @endif
                                        @if($order->wo_number)
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-mono font-bold bg-stone-900 text-white shrink-0 whitespace-nowrap">
                                                {{ $order->wo_number }}
                                            </span>
                                        @endif
                                        @if($order->approved && $order->substatus !== \App\Enums\Substatus::PONER_EN_ALTA)
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-pink-100 text-pink-800 border border-pink-300 shrink-0 whitespace-nowrap flex items-center gap-0.5">
                                                <x-lucide-check-circle-2 class="w-2.5 h-2.5 text-pink-600" />
                                                <span>{{ __('APROBADA') }}</span>
                                            </span>
                                        @endif
                                        @if($order->responsible_person)
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-indigo-50 text-indigo-800 border border-indigo-200 shrink-0 whitespace-nowrap flex items-center gap-1">
                                                <x-lucide-user class="w-2.5 h-2.5 text-indigo-600 shrink-0" />
                                                <span>{{ $order->responsible_person }}</span>
                                            </span>
                                        @endif
                                        @if($order->substatus)
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-medium border shrink-0 whitespace-nowrap {{ $order->substatus->badgeStyle() }}">
                                                {{ $order->substatus->value }}
                                            </span>
                                        @endif
                                        @if($order->customer_service_required)
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-pink-50 text-pink-700 border border-pink-200 shrink-0 whitespace-nowrap">
                                                {{ __('ATENCIÓN CLIENTE') }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-1 shrink-0 ml-1">
                                        @if($order->trello_url)
                                            <a href="{{ $order->trello_url }}" @click.stop target="_blank" rel="noopener noreferrer" class="p-1 rounded text-blue-600 hover:text-blue-800 hover:bg-blue-50 transition shrink-0" title="Abrir en Trello.com">
                                                <x-lucide-external-link class="w-3.5 h-3.5" />
                                            </a>
                                        @endif
                                        <div class="flex flex-wrap items-center gap-1 shrink-0 justify-end">
                                            @forelse($order->assigned_designers as $des)
                                                <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded border shrink-0 whitespace-nowrap {{ $des->badge_style }}">
                                                    {{ $des->name }}
                                                </span>
                                            @empty
                                                <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded border border-amber-300 bg-amber-100 text-amber-800 shrink-0 whitespace-nowrap">
                                                    Sin Asignar
                                                </span>
                                            @endforelse
                                        </div>
                                    </div>
                                </div>

                                <!-- Card Title & Company -->
                                <div class="min-w-0 flex items-start gap-2">
                                    <button 
                                        wire:click="toggleDoneToday({{ $order->id }})" 
                                        @click.stop
                                        type="button"
                                        class="w-4 h-4 mt-0.5 rounded-full border transition flex items-center justify-center shrink-0 cursor-pointer {{ $order->done_today ? 'bg-emerald-500 border-emerald-500 text-white shadow-2xs' : 'border-stone-300 hover:border-emerald-500 bg-white text-transparent hover:text-emerald-500/40' }}"
                                        title="{{ $order->done_today ? 'Completado (Clic para desmarcar)' : 'Marcar como completado' }}">
                                        <x-lucide-check class="w-2.5 h-2.5 stroke-[3]" />
                                    </button>
                                    <div class="min-w-0 flex-1">
                                        <h4 class="font-normal text-[11px] text-zinc-500 truncate leading-snug {{ $order->done_today ? 'line-through text-zinc-400' : '' }}" title="{{ $order->company_name }}">{{ $order->company_name }}</h4>
                                        <p class="font-bold text-xs text-zinc-900 group-hover:text-stone-800 transition truncate mt-0.5 {{ $order->done_today ? 'line-through text-zinc-400' : '' }}" title="{{ $order->task_name }}">{{ $order->task_name }}</p>
                                    </div>
                                </div>

                                <!-- Metadata & Due Date -->
                                <div class="flex items-center justify-between text-[10px] text-zinc-500 pt-1.5 border-t border-[#f0f0ee] gap-1">
                                    <div class="flex items-center gap-1 min-w-0">
                                        @if($order->core_status === \App\Enums\CoreStatus::ENVIADO_AL_CLIENTE)
                                            @php
                                                $sentDate = $order->last_meaningful_update ?? $order->updated_at ?? now();
                                                $daysElapsed = $sentDate ? $sentDate->diffInWeekdays(now()) : 0;
                                                $daysRemaining = max(0, 9 - $daysElapsed);
                                            @endphp
                                            <x-lucide-send class="w-3 h-3 text-sky-500 shrink-0" />
                                            <span class="font-mono font-medium truncate text-sky-800" title="Enviado el {{ $sentDate->format('d M, Y') }} ({{ $daysElapsed }}d transcurridos)">
                                                Enviado hace {{ $daysElapsed }}d <span class="text-sky-600 font-normal">({{ $daysRemaining }}d a Hold)</span>
                                            </span>
                                        @else
                                            <x-lucide-calendar class="w-3 h-3 text-zinc-400 shrink-0" />
                                            <span class="font-mono font-medium truncate {{ $order->isOverdue() ? 'text-red-600 font-bold' : ($order->isDueToday() ? 'text-amber-800 font-bold' : 'text-zinc-700') }}">
                                                {{ $order->current_due_date ? ($order->current_due_date->isToday() ? 'Hoy (' . $order->current_due_date->format('d M') . ')' : $order->current_due_date->format('d M')) : 'N/A' }}
                                            </span>
                                        @endif
                                    </div>

                                    @if($order->relatedTasks->where('status', 'todo')->count() > 0)
                                        <span class="px-1.5 py-0.5 rounded bg-violet-100 text-violet-800 font-semibold border border-violet-200 flex items-center gap-1 shrink-0 whitespace-nowrap" title="Tareas vinculadas activas">
                                            <x-lucide-check-square class="w-3 h-3 text-violet-600 shrink-0" />
                                            <span>{{ $order->relatedTasks->where('status', 'todo')->count() }} Tareas</span>
                                        </span>
                                    @endif
                                </div>

                                <!-- Quick Move Select & Modal Trigger -->
                                <div class="pt-1.5 flex items-center justify-between gap-1.5 border-t border-[#f0f0ee] min-w-0">
                                    <select wire:change="moveOrder({{ $order->id }}, $event.target.value)" @click.stop class="bg-[#fbfbfa] border border-[#e9e9e7] rounded px-1.5 py-0.5 text-[10px] text-zinc-700 focus:outline-none w-full min-w-0 truncate">
                                        <option value="">Mover a...</option>
                                        @foreach($allColumns as $colOption)
                                            @if($colOption !== $order->core_status)
                                                <option value="{{ $colOption->value }}">{{ $colOption->label() }}</option>
                                            @endif
                                        @endforeach
                                    </select>

                                    <div class="shrink-0 flex items-center gap-1">
                                        <button wire:click="$dispatch('open-duplicate-order', { orderId: {{ $order->id }} })" @click.stop class="px-1.5 py-0.5 rounded bg-stone-100 hover:bg-stone-200 border border-stone-200 text-[10px] font-medium text-zinc-700 hover:text-zinc-900 transition flex items-center gap-1" title="Duplicar Orden">
                                            <x-lucide-copy class="w-3 h-3 text-zinc-500" />
                                            <span>Duplicar</span>
                                        </button>
                                        <button wire:click="$dispatch('open-order-detail', { orderId: {{ $order->id }} })" @click.stop class="p-1 rounded bg-stone-100 hover:bg-stone-200 border border-stone-200 text-[10px] font-medium text-zinc-700 hover:text-zinc-900 transition flex items-center gap-1" title="Ver detalle de la orden">
                                            <x-lucide-panel-right class="w-3.5 h-3.5 text-zinc-600" />
                                        </button>
                                        <button
                                            wire:click="trashOrder({{ $order->id }})"
                                            @click.stop
                                            wire:confirm="¿Mover esta orden a la papelera?"
                                            class="px-1.5 py-0.5 rounded bg-red-50 hover:bg-red-100 border border-red-200 text-[10px] font-medium text-red-600 hover:text-red-800 transition flex items-center gap-1"
                                            title="Mover a la papelera"
                                        >
                                            <x-lucide-trash-2 class="w-3 h-3" />
                                        </button>
                                    </div>
                                </div>
                            </div>
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
                    <button wire:click="confirmOnHold" class="px-3.5 py-1.5 rounded-md bg-amber-600 hover:bg-amber-500 text-white font-medium text-xs shadow-2xs transition flex items-center gap-1">
                        <x-lucide-check-circle-2 class="w-3.5 h-3.5" />
                        <span>Poner en On Hold</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>
