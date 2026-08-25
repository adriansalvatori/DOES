<div 
    x-data="{ 
        open: false, 
        selectedIndex: -1,
        resultsCount: 0,
        focusInput() {
            this.open = true;
            $nextTick(() => {
                this.$refs.searchInput.focus();
                this.$refs.searchInput.select();
            });
        },
        closeSearch() {
            this.open = false;
            this.selectedIndex = -1;
            $wire.clearSearch();
        }
    }"
    @keydown.window.cmd.k.prevent="focusInput()"
    @keydown.window.ctrl.k.prevent="focusInput()"
    @click.outside="open = false"
    x-dropdown-nav
    class="relative flex-1 max-w-lg mx-2 sm:mx-4"
>
    <!-- Search Bar Input Field & Create Task Button in Header -->
    <div class="flex items-center gap-2">
        <div class="relative flex-1 flex items-center">
            <x-lucide-search class="w-3.5 h-3.5 text-zinc-400 absolute left-2.5 pointer-events-none shrink-0" />
            
            <input 
                x-ref="searchInput"
                type="text" 
                wire:model.live.debounce.200ms="search" 
                @focus="open = true"
                @keydown.escape="open = false"
                @keydown.arrow-down.prevent="if (open && resultsCount > 0) { selectedIndex = (selectedIndex + 1) % resultsCount; }"
                @keydown.arrow-up.prevent="if (open && resultsCount > 0) { selectedIndex = (selectedIndex - 1 + resultsCount) % resultsCount; }"
                @keydown.enter.prevent="
                    if (open && selectedIndex >= 0 && selectedIndex < resultsCount) {
                        let btn = $refs.resultsContainer?.children[selectedIndex]?.querySelector('button');
                        if (btn) btn.click();
                    }
                "
                placeholder="{{ __('Buscar órdenes activas (WO#, empresa, trabajo...)...') }}" 
                class="w-full bg-[#f4f4f2] hover:bg-[#eaeaea] focus:bg-white border border-[#e2e2df] focus:border-stone-400 rounded-lg pl-8 pr-12 py-1 h-7.5 text-xs text-zinc-800 placeholder-zinc-400 transition focus:outline-none focus:ring-1 focus:ring-stone-300 shadow-2xs"
            />

            <div class="absolute right-2 flex items-center gap-1">
                @if(trim($search) !== '')
                    <button 
                        type="button" 
                        wire:click="clearSearch" 
                        @click.stop="$refs.searchInput.focus()"
                        class="text-zinc-400 hover:text-zinc-700 p-0.5 rounded transition cursor-pointer"
                        title="{{ __('Limpiar búsqueda') }}">
                        <x-lucide-x class="w-3 h-3" />
                    </button>
                @else
                    <kbd class="hidden sm:inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono font-medium text-zinc-400 bg-white border border-stone-200 shadow-2xs pointer-events-none">
                        ⌘K
                    </kbd>
                @endif
            </div>
        </div>

        <!-- Create Task Button -->
        <button 
            type="button" 
            @click="$dispatch('open-create-order')"
            class="h-7.5 px-2.5 rounded-lg bg-stone-900 hover:bg-stone-800 text-white font-medium text-xs transition shrink-0 flex items-center gap-1.5 shadow-2xs cursor-pointer"
            title="{{ __('Nueva Tarea') }}">
            <x-lucide-plus class="w-3.5 h-3.5 text-stone-300" />
            <span class="hidden sm:inline">{{ __('Nueva Tarea') }}</span>
        </button>
    </div>

    <!-- Live Search Dropdown Popup / Spotlight Container -->
    <div 
        x-show="open && $wire.search.trim().length >= 1" 
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-98 -translate-y-1"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
        x-transition:leave-end="opacity-0 scale-98 -translate-y-1"
        x-init="resultsCount = {{ count($results) }}"
        x-effect="resultsCount = {{ count($results) }}"
        class="absolute left-0 top-full mt-2 bg-white border border-stone-200 rounded-xl shadow-2xl z-[100] overflow-hidden text-xs max-h-[440px] flex flex-col w-full sm:w-[560px]"
        style="display: none;"
    >
        <!-- Header status bar inside search dropdown -->
        <div class="px-3 py-1.5 bg-stone-50 border-b border-stone-200/80 flex items-center justify-between text-[11px] text-zinc-500 font-medium shrink-0">
            <span class="flex items-center gap-1.5">
                <x-lucide-layers class="w-3.5 h-3.5 text-zinc-400" />
                {{ __('Órdenes Activas') }}
            </span>
            <span class="text-[10px] text-zinc-400 font-mono">
                {{ count($results) }} {{ count($results) === 1 ? __('coincidencia') : __('coincidencias') }}
            </span>
        </div>

        <!-- Compact Results List -->
        <div x-ref="resultsContainer" class="overflow-y-auto divide-y divide-stone-100 flex-1 p-1">
            @forelse($results as $index => $order)
                <div 
                    :class="{ 'bg-stone-100/90': selectedIndex === {{ $index }} }"
                    @mouseenter="selectedIndex = {{ $index }}"
                    class="rounded-md transition"
                >
                    <button 
                        type="button"
                        wire:click="selectOrder({{ $order->id }})"
                        @click="closeSearch()"
                        class="w-full text-left px-2.5 py-1.5 rounded-md hover:bg-stone-100/80 transition flex items-center justify-between gap-2.5 group cursor-pointer"
                    >
                        <!-- Left Info Column -->
                        <div class="min-w-0 flex-1 space-y-0.5">
                            <div class="flex items-center gap-2 truncate">
                                @if($order->wo_number)
                                    <span class="px-1.5 py-0.2 rounded text-[10px] font-mono font-bold bg-stone-100 border border-stone-200 text-zinc-700 shrink-0">
                                        #{{ $order->wo_number }}
                                    </span>
                                @endif

                                <span class="font-bold text-zinc-900 truncate group-hover:text-stone-900 text-xs">
                                    {{ $order->company_name }}
                                </span>

                                <span class="text-zinc-300 text-[10px] shrink-0">•</span>

                                <span class="text-zinc-600 text-[11px] truncate font-medium">
                                    {{ $order->task_name ?: ($order->trello_title ?: __('Sin título')) }}
                                </span>
                            </div>

                            <!-- Sub-line details (Designer & Responsible) -->
                            <div class="flex items-center gap-2 text-[10px] text-zinc-400">
                                @php
                                    $assigned = $order->assigned_designers;
                                @endphp
                                @if($assigned->isNotEmpty())
                                    <span class="truncate flex items-center gap-1 text-zinc-500 font-medium">
                                        <x-lucide-palette class="w-3 h-3 text-zinc-400 shrink-0" />
                                        {{ $assigned->pluck('name')->join(', ') }}
                                    </span>
                                @endif

                                @if($order->responsible_person)
                                    <span class="truncate flex items-center gap-1">
                                        <x-lucide-user class="w-3 h-3 text-zinc-400 shrink-0" />
                                        {{ $order->responsible_person }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <!-- Right Badges Column -->
                        <div class="shrink-0 flex items-center gap-1.5">
                            @if($order->substatus === \App\Enums\Substatus::OVERDUE || $order->isOverdue())
                                <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-red-100 text-red-700 border border-red-200 shrink-0">
                                    {{ __('ATRASADA') }}
                                </span>
                            @endif

                            @if($order->substatus && $order->substatus !== \App\Enums\Substatus::OVERDUE)
                                <span class="text-[10px] text-zinc-500 font-medium hidden sm:inline truncate max-w-[110px]">
                                    {{ $order->substatus->label() }}
                                </span>
                            @endif

                            @if($order->core_status)
                                <span class="px-2 py-0.5 rounded text-[9.5px] font-semibold bg-stone-100 text-zinc-700 border border-stone-200 uppercase tracking-tight shrink-0">
                                    {{ $order->core_status->label() }}
                                </span>
                            @endif
                        </div>
                    </button>
                </div>
            @empty
                <div class="p-5 text-center text-zinc-500 space-y-1">
                    <x-lucide-search-x class="w-5 h-5 text-zinc-300 mx-auto" />
                    <p class="font-medium text-zinc-700 text-xs">{{ __('No se encontraron órdenes activas') }}</p>
                    <p class="text-[11px] text-zinc-400">{{ __('Intenta buscar con el número de WO, empresa, trabajo o diseñador.') }}</p>
                </div>
            @endforelse
        </div>

        <!-- Footer keyboard shortcut hints -->
        <div class="px-3 py-1.5 bg-stone-50 border-t border-stone-200/80 flex items-center justify-between text-[10px] text-zinc-400 shrink-0">
            <div class="flex items-center gap-3">
                <span class="flex items-center gap-1">
                    <kbd class="px-1 py-0.2 rounded bg-white border border-stone-200 font-mono text-[9px]">↑</kbd>
                    <kbd class="px-1 py-0.2 rounded bg-white border border-stone-200 font-mono text-[9px]">↓</kbd>
                    {{ __('Navegar') }}
                </span>
                <span class="flex items-center gap-1">
                    <kbd class="px-1 py-0.2 rounded bg-white border border-stone-200 font-mono text-[9px]">↵</kbd>
                    {{ __('Abrir orden') }}
                </span>
            </div>
            <span class="flex items-center gap-1">
                <kbd class="px-1 py-0.2 rounded bg-white border border-stone-200 font-mono text-[9px]">ESC</kbd>
                {{ __('Cerrar') }}
            </span>
        </div>
    </div>
</div>
