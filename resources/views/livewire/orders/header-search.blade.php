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
                        let btns = $refs.resultsContainer?.querySelectorAll('.search-result-btn');
                        if (btns && btns[selectedIndex]) btns[selectedIndex].click();
                    }
                "
                placeholder="{{ __('Buscar en DOES (WO#, cliente, empresa, trabajo...)...') }}" 
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
        x-init="resultsCount = {{ count($results) + count($clientResults) }}"
        x-effect="resultsCount = {{ count($results) + count($clientResults) }}"
        class="absolute left-0 top-full mt-2 bg-white border border-stone-200 rounded-xl shadow-2xl z-[100] overflow-hidden text-xs max-h-[460px] flex flex-col w-full sm:w-[560px]"
        style="display: none;"
    >
        <!-- Header status bar inside search dropdown -->
        <div class="px-3 py-1.5 bg-stone-50 border-b border-stone-200/80 flex items-center justify-between text-[11px] text-zinc-500 font-medium shrink-0">
            <span class="flex items-center gap-1.5">
                <x-lucide-search class="w-3.5 h-3.5 text-zinc-400" />
                {{ __('Resultados de búsqueda') }}
            </span>
            <span class="text-[10px] text-zinc-400 font-mono">
                {{ count($results) + count($clientResults) }} {{ (count($results) + count($clientResults)) === 1 ? __('coincidencia') : __('coincidencias') }}
            </span>
        </div>

        <!-- Compact Results List -->
        <div x-ref="resultsContainer" class="overflow-y-auto flex-1 p-1 space-y-1.5">
            @php $itemIndex = 0; @endphp

            {{-- Client Results Section --}}
            @if(count($clientResults) > 0)
                <div class="space-y-1">
                    <div class="px-2.5 py-1 bg-stone-100/70 text-[10px] font-bold text-zinc-500 uppercase tracking-wider flex items-center gap-1.5 shrink-0 rounded">
                        <x-lucide-building-2 class="w-3.5 h-3.5 text-zinc-400" />
                        <span>{{ __('Base de Datos Clientes') }}</span>
                        <span class="text-[9px] text-zinc-400 font-mono">({{ count($clientResults) }})</span>
                    </div>

                    @foreach($clientResults as $client)
                        @php $idx = $itemIndex++; @endphp
                        <div 
                            :class="{ 'bg-stone-100/90': selectedIndex === {{ $idx }} }"
                            @mouseenter="selectedIndex = {{ $idx }}"
                            class="rounded-md transition"
                        >
                            <button 
                                type="button"
                                wire:click="selectClient({{ $client->id }})"
                                @click="closeSearch()"
                                class="search-result-btn w-full text-left px-2.5 py-1.5 rounded-md hover:bg-stone-100/80 transition flex items-center justify-between gap-2.5 group cursor-pointer"
                            >
                                <!-- Left Info Column -->
                                <div class="grid grid-cols-[auto_1fr] items-center gap-x-2 gap-y-0.5 min-w-0 flex-1">
                                    <!-- Col 1, Row 1: CLIENTE Badge -->
                                    <div class="flex items-center">
                                        <span class="px-1.5 py-0.2 rounded text-[10px] font-mono font-bold bg-amber-50 text-amber-700 border border-amber-200/80 shrink-0">
                                            CLIENTE
                                        </span>
                                    </div>

                                    <!-- Col 2, Row 1: Client Name + Active Orders count -->
                                    <div class="flex items-center gap-2 truncate min-w-0">
                                        <span class="font-bold text-zinc-900 truncate group-hover:text-stone-900 text-xs">
                                            {{ $client->name }}
                                        </span>

                                        @if($client->active_orders_count > 0)
                                            <span class="text-zinc-300 text-[10px] shrink-0">•</span>
                                            <span class="truncate flex items-center gap-1 text-[10px] text-emerald-700 font-medium shrink-0">
                                                <x-lucide-layers class="w-3 h-3 text-emerald-600 shrink-0" />
                                                <span>{{ $client->active_orders_count }} {{ $client->active_orders_count === 1 ? __('orden activa') : __('órdenes activas') }}</span>
                                            </span>
                                        @endif
                                    </div>

                                    <!-- Col 1, Row 2: Building Icon -->
                                    <div class="flex items-center min-w-0">
                                        <span class="truncate flex items-center gap-1 text-[10px] text-zinc-400 font-medium">
                                            <x-lucide-building-2 class="w-3 h-3 text-zinc-400 shrink-0" />
                                        </span>
                                    </div>

                                    <!-- Col 2, Row 2: Contact / Website / Main Location Address -->
                                    @php
                                        $mainLoc = $client->locations->first(function ($loc) {
                                            $addr = trim($loc->address ?? '');
                                            return !empty($addr) && $addr !== 'Por definir' && !str_starts_with($addr, 'Por definir');
                                        }) ?? $client->locations->first();

                                        $mainAddress = $mainLoc?->address;
                                        if ($mainAddress === 'Por definir' || str_starts_with($mainAddress ?? '', 'Por definir')) {
                                            $mainAddress = null;
                                        }
                                    @endphp
                                    <div class="flex items-center gap-2 text-[10px] text-zinc-500 truncate min-w-0">
                                        @if($client->primaryContact)
                                            <span class="truncate flex items-center gap-1">
                                                <x-lucide-user class="w-3 h-3 text-zinc-400 shrink-0" />
                                                <span class="truncate">{{ $client->primaryContact->name }}</span>
                                            </span>
                                        @endif

                                        @if($client->website)
                                            @if($client->primaryContact)
                                                <span class="text-zinc-300 text-[10px] shrink-0">•</span>
                                            @endif
                                            <span class="truncate flex items-center gap-1">
                                                <x-lucide-globe class="w-3 h-3 text-zinc-400 shrink-0" />
                                                <span class="truncate">{{ str_replace(['https://', 'http://', 'www.'], '', $client->website) }}</span>
                                            </span>
                                        @endif

                                        @if($mainAddress)
                                            @if($client->primaryContact || $client->website)
                                                <span class="text-zinc-300 text-[10px] shrink-0">•</span>
                                            @endif
                                            <span 
                                                x-data="{ 
                                                    copied: false,
                                                    copyAddress(e) {
                                                        e.stopPropagation();
                                                        e.preventDefault();
                                                        const text = {{ json_encode($mainAddress) }};
                                                        if (navigator.clipboard && window.isSecureContext) {
                                                            navigator.clipboard.writeText(text).then(() => {
                                                                this.copied = true;
                                                                setTimeout(() => this.copied = false, 2000);
                                                            });
                                                        } else {
                                                            const ta = document.createElement('textarea');
                                                            ta.value = text;
                                                            ta.style.position = 'fixed';
                                                            ta.style.opacity = '0';
                                                            document.body.appendChild(ta);
                                                            ta.select();
                                                            document.execCommand('copy');
                                                            document.body.removeChild(ta);
                                                            this.copied = true;
                                                            setTimeout(() => this.copied = false, 2000);
                                                        }
                                                    }
                                                }"
                                                @click="copyAddress($event)"
                                                title="{{ __('Clic para copiar dirección') }}"
                                                class="inline-flex items-center gap-1 text-[10px] text-zinc-700 hover:text-zinc-900 bg-stone-100 hover:bg-stone-200 border border-stone-200/90 px-1.5 py-0.2 rounded cursor-pointer transition shrink-0 group/addr"
                                            >
                                                <template x-if="!copied">
                                                    <span class="flex items-center gap-1 truncate max-w-[220px]">
                                                        <x-lucide-map-pin class="w-3 h-3 text-rose-500 shrink-0" />
                                                        <span class="truncate font-medium">{{ $mainAddress }}</span>
                                                        <x-lucide-copy class="w-2.5 h-2.5 text-zinc-400 group-hover/addr:text-zinc-600 shrink-0 ml-0.5" />
                                                    </span>
                                                </template>
                                                <template x-if="copied">
                                                    <span class="flex items-center gap-1 text-emerald-700 font-semibold">
                                                        <x-lucide-check class="w-3 h-3 text-emerald-600 shrink-0" />
                                                        <span>{{ __('¡Copiado!') }}</span>
                                                    </span>
                                                </template>
                                            </span>
                                        @endif

                                        @if(!$client->primaryContact && !$client->website && !$mainAddress)
                                            <span class="text-zinc-400 text-[11px] truncate italic">{{ __('Ficha de cliente') }}</span>
                                        @endif
                                    </div>
                                </div>

                                <!-- Right Action Badge -->
                                <div class="shrink-0 flex items-center gap-1">
                                    <span class="px-2 py-0.5 rounded text-[9.5px] font-semibold bg-stone-100 text-zinc-700 border border-stone-200 uppercase tracking-tight shrink-0 flex items-center gap-1 group-hover:bg-stone-200">
                                        {{ __('Ver Ficha') }}
                                        <x-lucide-chevron-right class="w-3 h-3 text-zinc-400" />
                                    </span>
                                </div>
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Active Orders Results Section --}}
            @if(count($results) > 0)
                <div class="space-y-1 pt-1">
                    @if(count($clientResults) > 0)
                        <div class="px-2.5 py-1 bg-stone-100/70 text-[10px] font-bold text-zinc-500 uppercase tracking-wider flex items-center gap-1.5 shrink-0 rounded">
                            <x-lucide-layers class="w-3.5 h-3.5 text-zinc-400" />
                            <span>{{ __('Órdenes Activas') }}</span>
                            <span class="text-[9px] text-zinc-400 font-mono">({{ count($results) }})</span>
                        </div>
                    @endif

                    @foreach($results as $order)
                        @php $idx = $itemIndex++; @endphp
                        <div 
                            :class="{ 'bg-stone-100/90': selectedIndex === {{ $idx }} }"
                            @mouseenter="selectedIndex = {{ $idx }}"
                            class="rounded-md transition"
                        >
                            <button 
                                type="button"
                                wire:click="selectOrder({{ $order->id }})"
                                @click="closeSearch()"
                                class="search-result-btn w-full text-left px-2.5 py-1.5 rounded-md hover:bg-stone-100/80 transition flex items-center justify-between gap-2.5 group cursor-pointer"
                            >
                                <!-- Left Info Column -->
                                <div class="grid grid-cols-[auto_1fr] items-center gap-x-2 gap-y-0.5 min-w-0 flex-1">
                                    <!-- Col 1, Row 1: WO Badge -->
                                    <div class="flex items-center">
                                        @if($order->wo_number)
                                            <x-wo-badge :number="$order->wo_number" variant="outline" prefix="#" />
                                        @endif
                                    </div>

                                    <!-- Col 2, Row 1: Company Name + Responsible -->
                                    <div class="flex items-center gap-2 truncate min-w-0">
                                        <span class="font-bold text-zinc-900 truncate group-hover:text-stone-900 text-xs">
                                            {{ $order->company_name }}
                                        </span>

                                        @if($order->responsible_person)
                                            <span class="text-zinc-300 text-[10px] shrink-0">•</span>
                                            <span class="truncate flex items-center gap-1 text-[10px] text-zinc-500 font-medium min-w-0">
                                                <x-lucide-user class="w-3 h-3 text-zinc-400 shrink-0" />
                                                <span class="truncate">{{ $order->responsible_person }}</span>
                                            </span>
                                        @endif
                                    </div>

                                    <!-- Col 1, Row 2: Designer -->
                                    <div class="flex items-center min-w-0">
                                        @php
                                            $assigned = $order->assigned_designers;
                                        @endphp
                                        @if($assigned->isNotEmpty())
                                            <span class="truncate flex items-center gap-1 text-[10px] text-zinc-500 font-medium max-w-[110px]">
                                                <x-lucide-palette class="w-3 h-3 text-zinc-400 shrink-0" />
                                                <span class="truncate">{{ $assigned->pluck('name')->join(', ') }}</span>
                                            </span>
                                        @endif
                                    </div>

                                    <!-- Col 2, Row 2: Order name -->
                                    <div class="flex items-center min-w-0">
                                        <span class="text-zinc-600 text-[11px] truncate font-medium">
                                            {{ $order->task_name ?: ($order->trello_title ?: __('Sin título')) }}
                                        </span>
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
                    @endforeach
                </div>
            @endif

            @if(count($results) === 0 && count($clientResults) === 0)
                <div class="p-5 text-center text-zinc-500 space-y-1">
                    <x-lucide-search-x class="w-5 h-5 text-zinc-300 mx-auto" />
                    <p class="font-medium text-zinc-700 text-xs">{{ __('No se encontraron resultados') }}</p>
                    <p class="text-[11px] text-zinc-400">{{ __('Intenta buscar con el nombre del cliente, WO#, trabajo o diseñador.') }}</p>
                </div>
            @endif
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
