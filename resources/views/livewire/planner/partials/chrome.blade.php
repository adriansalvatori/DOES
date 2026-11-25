    <!-- Marquee Selection Rectangle Overlay -->
    <div 
        x-show="isSelecting && (selectionBox.width > 6 || selectionBox.height > 6)" 
        x-cloak
        hidden
        :style="`left: ${selectionBox.left}px; top: ${selectionBox.top}px; width: ${selectionBox.width}px; height: ${selectionBox.height}px;`"
        class="fixed pointer-events-none border border-dashed border-ink bg-ink/10 z-[999] rounded-sm transition-none"
        style="display: none;">
    </div>

    
        <template id="planner-inline-creator-template">
        <div x-show="inlineActive" class="py-1 px-1 flex items-center justify-between gap-1.5 min-w-0 bg-stone-50/90 rounded border border-dashed border-stone-300 transition my-1">
            <div class="flex items-center gap-2 min-w-0 flex-1">
                <div class="w-3.5 h-3.5 rounded-full border border-stone-300 bg-white shrink-0 flex items-center justify-center">
                    <span class="w-1.5 h-1.5 rounded-full bg-ink"></span>
                </div>

                <div class="flex items-center gap-1.5 min-w-0 flex-1 text-meta leading-tight relative">
                    
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
                            class="w-full bg-transparent border-none p-0 focus:ring-0 focus:outline-none text-meta font-normal text-zinc-800 placeholder-stone-400" />

                        <!-- Floating Dropdown for Orders -->
                        <div 
                            x-ref="orderDropdownPanel"
                            x-show="dropdownOpen && getFilteredOrders().length > 0" 
                            x-cloak
                            class="absolute left-0 top-full mt-1 z-50 bg-white border border-stone-200 rounded-md max-h-44 overflow-y-auto divide-y divide-stone-100 text-meta w-72"
                            style="display: none;">
                            <template x-for="(ord, idx) in getFilteredOrders()" :key="ord.id">
                                <button 
                                    type="button" 
                                    @click="selectOrder(ord)" 
                                    @mouseenter="orderHighlightedIndex = idx"
                                    :class="{ 'bg-sunken text-ink font-semibold': orderHighlightedIndex === idx, 'hover:bg-stone-100': orderHighlightedIndex !== idx }"
                                    class="order-item-btn w-full text-left px-2.5 py-1.5 transition flex items-center justify-between gap-2 cursor-pointer">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5 min-w-0">
                                            <span class="font-semibold text-zinc-900 truncate" x-text="ord.company"></span>
                                            <template x-if="ord.location">
                                                <span class="text-meta font-semibold text-ink-2 bg-sunken px-1 py-0.2 rounded border border-line shrink-0 truncate max-w-[120px]" x-text="ord.location"></span>
                                            </template>
                                        </div>
                                        <span class="text-meta text-zinc-500 block truncate" x-text="ord.task"></span>
                                    </div>
                                </button>
                            </template>
                        </div>
                    </div>

                    <!-- Phase 2: Selected Order + Subtask Name Input on the SAME line -->
                     <div x-show="selectedOrderId" class="flex items-center gap-1 min-w-0 flex-1 relative" @click.outside="presetDropdownOpen = false; titleFocused = false;">
                         <span 
                             :class="titleFocused ? 'max-w-[40px] opacity-75' : 'max-w-[70px] opacity-100'"
                             class="font-semibold text-zinc-900  shrink-0 truncate transition-all duration-200" 
                             :title="selectedOrderCompany"
                             x-text="selectedOrderCompany"></span>
                         <span class="text-zinc-300 font-semibold shrink-0 transition-opacity duration-200" :class="titleFocused ? 'hidden' : 'inline'" x-show="selectedOrderTask">•</span>
                         <span 
                             :class="titleFocused ? 'hidden' : 'inline max-w-[65px]'"
                             class="text-zinc-500 font-medium shrink-0 truncate transition-all duration-200" 
                             :title="selectedOrderTask"
                             x-show="selectedOrderTask" 
                             x-text="selectedOrderTask"></span>
                         <span class="text-zinc-300 font-semibold shrink-0">•</span>

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
                                 class="w-full bg-sunken border border-line px-1.5 py-0.5 rounded text-meta font-semibold text-ink   focus:border-line focus:outline-none placeholder-ink-3 transition-all duration-200" />

                            <!-- Delicate Dropdown Menu for Subtask Presets -->
                            <div 
                                x-ref="presetDropdownPanel"
                                x-show="presetDropdownOpen && getFilteredPresets().length > 0"
                                x-cloak
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                                x-transition:leave="transition ease-in duration-75"
                                x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                                x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                                class="absolute left-0 top-full mt-1 z-50 bg-white/95 border border-stone-200 rounded-lg shadow-menu max-h-48 overflow-y-auto p-1 text-meta min-w-[210px] w-max max-w-xs space-y-0.5 divide-y divide-stone-100"
                                style="display: none;">
                                <div class="px-2 py-0.5 text-meta font-semibold text-stone-400  flex items-center justify-between select-none pb-0.5">
                                    <span>{{ __('Plantillas predeterminadas') }}</span>
                                    <span class="text-meta text-stone-300 font-mono">↑↓ Enter</span>
                                </div>
                                <div class="pt-0.5 space-y-0.5">
                                    <template x-for="(preset, idx) in getFilteredPresets()" :key="preset.id || idx">
                                        <button 
                                            type="button" 
                                            @click="selectPreset(preset)" 
                                            @mouseenter="presetHighlightedIndex = idx"
                                            :class="{ 'bg-sunken text-ink font-semibold': presetHighlightedIndex === idx, 'text-zinc-700 hover:bg-stone-50': presetHighlightedIndex !== idx }"
                                            class="preset-item-btn w-full text-left px-2 py-1 rounded-md transition flex items-center justify-between gap-2 cursor-pointer group">
                                            <div class="flex items-center gap-1.5 min-w-0">
                                                <span 
                                                    :class="preset.badge_style || 'bg-stone-100 text-stone-700 border-stone-200'"
                                                    class="px-1.5 py-0.5 rounded text-meta font-medium border shrink-0 inline-flex items-center gap-1">
                                                    <span x-text="preset.title"></span>
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-1 shrink-0">
                                                <span 
                                                    :class="preset.is_work_task ? 'bg-sunken text-ink-2 border-line' : 'bg-sunken text-ink-2 border-line'"
                                                    class="px-1 py-0.2 rounded text-meta font-semibold border">
                                                    <span x-text="preset.is_work_task ? 'Trabajo' : 'Gestión'"></span>
                                                </span>
                                                <span class="text-meta text-ink-2 font-medium opacity-0 group-hover:opacity-100 transition">
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
                            :class="isWorkTask ? 'bg-sunken hover:bg-sunken text-ink-2 border-line' : 'bg-sunken hover:bg-sunken text-ink-2 border-line'"
                            class="px-1.5 py-0.5 rounded text-meta font-semibold border transition cursor-pointer flex items-center gap-1 shrink-0 select-none"
                            :title="isWorkTask ? @js(__('Trabajo activo de diseño (Mueve orden a Working Today si es para hoy)')) : @js(__('Gestión / Coordinación (No mueve la orden principal a Working Today)'))">
                            <span x-show="isWorkTask" class="flex items-center gap-1">
                                <x-lucide-briefcase class="w-3 h-3 text-ink-2" />
                                <span>{{ __('Trabajo') }}</span>
                            </span>
                            <span x-show="!isWorkTask" class="flex items-center gap-1">
                                <x-lucide-clipboard-check class="w-3 h-3 text-ink-2" />
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
        </template>

    <!-- Modal Nueva Subtarea -->
    <div wire:ignore.self>
        <template x-if="subtaskModalOpen">
            <div 
                class="fixed inset-0 z-[100] overflow-y-auto bg-stone-900/40 flex items-center justify-center p-4"
                @keydown.window.escape="subtaskModalOpen = false"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0">
            
                <div 
                    @click.outside="subtaskModalOpen = false"
                    class="bg-white border border-line rounded-xl shadow-panel max-w-lg w-full flex flex-col transition duration-200 overflow-visible relative"
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100">
                    
                    <div class="px-5 py-4 border-b border-line bg-canvas flex items-center justify-between">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="p-2 rounded-lg bg-ink text-white shrink-0">
                                <x-lucide-check-square class="w-4 h-4" />
                            </div>
                            <div class="min-w-0">
                                <h3 class="font-semibold text-sm text-zinc-900 leading-snug">{{ __('Asignar Subtarea a Día') }}</h3>
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
                                    <span class="text-meta text-ink-2 font-semibold bg-sunken px-1.5 py-0.2 rounded border border-line flex items-center gap-1">
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
                                    class="w-full bg-canvas focus:bg-white border border-line focus:border-stone-400 rounded-lg pl-9 pr-14 py-2 text-xs text-zinc-800 focus:outline-none transition font-medium" />

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
                                x-cloak
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                class="absolute left-0 right-0 top-full mt-1 z-50 bg-white border border-line rounded-xl shadow-panel max-h-56 overflow-y-auto divide-y divide-stone-100 text-xs"
                                style="display: none;">
                                
                                <template x-for="(item, idx) in getFilteredOrders()" :key="item.id">
                                    <button 
                                        type="button"
                                        @click="subtaskOrderId = item.id; orderSearchQuery = item.text; if (item.designer_id) subtaskDesignerId = String(item.designer_id); orderDropdownOpen = false; modalOrderHighlightedIndex = -1;"
                                        @mouseenter="modalOrderHighlightedIndex = idx"
                                        :class="{ 'bg-sunken text-ink font-semibold': modalOrderHighlightedIndex === idx || (modalOrderHighlightedIndex === -1 && subtaskOrderId === item.id), 'hover:bg-stone-100': modalOrderHighlightedIndex !== idx }"
                                        class="modal-order-item-btn w-full text-left p-2.5 focus:outline-none cursor-pointer flex items-center justify-between gap-2 transition">
                                        <div class="min-w-0 flex-1">
                                             <div class="flex items-center gap-1.5 min-w-0">
                                                 <span class="font-semibold text-zinc-900 truncate text-xs" x-text="item.company"></span>
                                                 <template x-if="item.location">
                                                     <span class="text-meta font-semibold text-ink-2 bg-sunken px-1.5 py-0.2 rounded border border-line shrink-0 truncate max-w-[150px]" x-text="item.location"></span>
                                                 </template>
                                             </div>
                                             <span class="text-meta text-zinc-500 block truncate" x-text="item.task"></span>
                                        </div>
                                        <template x-if="subtaskOrderId === item.id">
                                            <x-lucide-check class="w-3.5 h-3.5 text-ink-2 shrink-0 stroke-[2.5]" />
                                        </template>
                                    </button>
                                </template>

                                <template x-if="getFilteredOrders().length === 0">
                                    <div class="p-3 text-center text-zinc-400 italic text-meta">
                                        {{ __('No se encontraron órdenes coincidentes.') }}
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="font-semibold text-zinc-700 block flex items-center justify-between">
                                <span>{{ __('Nombre de la Subtarea') }} <span class="text-danger">*</span></span>
                                <div class="flex items-center gap-1 bg-stone-100 p-0.5 rounded-lg text-meta font-semibold border border-stone-200">
                                    <button 
                                        type="button" 
                                        @click="subtaskIsWorkTask = true" 
                                        :class="subtaskIsWorkTask ? 'bg-ink text-white font-semibold' : 'text-zinc-600 hover:text-zinc-900'" 
                                        class="px-2 py-0.5 rounded-md transition cursor-pointer flex items-center gap-1">
                                        <x-lucide-briefcase class="w-3 h-3" />
                                        <span>{{ __('Trabajo') }}</span>
                                    </button>
                                    <button 
                                        type="button" 
                                        @click="subtaskIsWorkTask = false" 
                                        :class="!subtaskIsWorkTask ? 'bg-ink text-white font-semibold' : 'text-zinc-600 hover:text-zinc-900'" 
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
                                class="w-full bg-canvas focus:bg-white border border-line focus:border-stone-400 rounded-lg px-3 py-2 text-xs text-zinc-800 focus:outline-none transition" />
                        </div>

                        @if($subtaskPresets->count() > 0)
                            <div class="space-y-1.5 pt-1">
                                <span class="text-meta font-medium text-zinc-500 block">{{ __('O elige un nombre predefinido:') }}</span>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($subtaskPresets as $preset)
                                        @php
                                            $safePlannerIcon = (preg_match('/^[a-z0-9\-]+$/i', $preset->emoji ?? '')) ? $preset->emoji : 'tag';
                                        @endphp
                                        <button 
                                            type="button"
                                            @click="subtaskTitle = '{{ addslashes($preset->title) }}'; subtaskIsWorkTask = {{ $preset->is_work_task ? 'true' : 'false' }}"
                                            class="px-2 py-1 rounded-md font-medium text-meta border transition inline-flex items-center gap-1 cursor-pointer hover:opacity-80 {{ $preset->badgeStyle() }}">
                                            <x-dynamic-component :component="'lucide-' . $safePlannerIcon" class="w-3 h-3" />
                                            <span>{{ __($preset->title) }}</span>
                                            <span class="text-meta opacity-75">({{ $preset->is_work_task ? __('Trabajo') : __('Gestión') }})</span>
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
                                    class="w-full bg-canvas hover:bg-white border border-line hover:border-stone-400 rounded-lg px-2.5 py-1.5 text-xs text-zinc-800 focus:outline-none transition font-medium flex items-center justify-between cursor-pointer">
                                    <span class="flex items-center gap-1.5 truncate">
                                        <x-lucide-calendar class="w-3.5 h-3.5 text-ink-2 shrink-0" />
                                        <span x-text="subtaskDate ? subtaskDate : @js(__('Seleccionar fecha...'))"></span>
                                    </span>
                                    <x-lucide-chevron-down class="w-3.5 h-3.5 text-zinc-400 shrink-0 transition-transform duration-150" x-bind:class="calOpen ? 'rotate-180' : ''" />
                                </button>

                                <div 
                                    x-show="calOpen"
                                    x-cloak
                                    x-transition:enter="transition ease-out duration-100"
                                    x-transition:enter-start="opacity-0 scale-95"
                                    x-transition:enter-end="opacity-100 scale-100"
                                    class="absolute left-0 bottom-full mb-1.5 w-72 sm:w-80 z-50 bg-white border border-line rounded-xl shadow-panel p-3 text-xs space-y-2.5"
                                    style="display: none;">
                                    
                                    <div class="flex items-center justify-between border-b border-[#f0f0ee] pb-1.5">
                                        <button type="button" @click="modalCalShift(-1)" class="p-1 rounded hover:bg-stone-100 text-zinc-600 transition">
                                            <x-lucide-chevron-left class="w-3.5 h-3.5" />
                                        </button>
                                        <span class="font-semibold text-zinc-900 text-xs capitalize">
                                            <span x-text="modalCalLabel()"></span>
                                        </span>
                                        <button type="button" @click="modalCalShift(1)" class="p-1 rounded hover:bg-stone-100 text-zinc-600 transition">
                                            <x-lucide-chevron-right class="w-3.5 h-3.5" />
                                        </button>
                                    </div>

                                    <div class="flex items-center justify-between text-meta text-zinc-500 px-0.5">
                                        <span class="flex items-center gap-1 font-medium">
                                            <span class="w-2.5 h-2.5 rounded bg-sunken border border-line inline-block"></span>
                                            {{ __('Semana Actual') }}
                                        </span>
                                        <span class="flex items-center gap-1 font-medium">
                                            <span class="w-2.5 h-2.5 rounded bg-stone-900 inline-block"></span>
                                            {{ __('Seleccionado') }}
                                        </span>
                                    </div>

                                    <div class="grid grid-cols-7 text-center font-semibold text-meta text-zinc-400 ">
                                        <span>{{ __('Lun') }}</span><span>{{ __('Mar') }}</span><span>{{ __('Mié') }}</span><span>{{ __('Jue') }}</span><span>{{ __('Vie') }}</span><span class="text-zinc-300">{{ __('Sáb') }}</span><span class="text-zinc-300">{{ __('Dom') }}</span>
                                    </div>

                                    <div class="grid grid-cols-7 gap-1 text-center font-medium">
                                        <template x-for="d in modalCalDays()" :key="d.date">
                                            <button
                                                type="button"
                                                @click="subtaskDate = d.date; calOpen = false"
                                                :class="[subtaskDate === d.date ? 'bg-stone-900 text-white font-semibold' : '', d.currentWeek ? 'bg-sunken text-ink font-semibold border border-line hover:bg-sunken' : (d.currentMonth ? 'text-zinc-800 hover:bg-stone-100' : 'text-zinc-300 hover:bg-stone-50'), d.today ? 'border border-ink font-semibold' : '']"
                                                class="p-1.5 rounded-md text-xs transition relative group cursor-pointer">
                                                <span x-text="d.day"></span>
                                            </button>
                                        </template>
                                    </div>

                                    <div class="pt-1.5 border-t border-[#f0f0ee] flex items-center justify-between gap-1 text-meta">
                                        <span class="text-zinc-500 shrink-0">{{ __('O elegir fecha:') }}</span>
                                        <input 
                                            type="date" 
                                            x-model="subtaskDate"
                                            @change="calOpen = false"
                                            class="bg-canvas border border-line rounded px-2 py-0.5 text-meta font-mono text-zinc-800">
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-1">
                                <label class="font-semibold text-zinc-700 block">{{ __('Diseñador Asignado') }}</label>
                                <select 
                                    x-model="subtaskDesignerId" 
                                    class="w-full bg-canvas focus:bg-white border border-line focus:border-stone-400 rounded-lg px-2.5 py-1.5 text-xs text-zinc-800 focus:outline-none transition font-medium">
                                    <option value="">{{ __('Por defecto (de la Orden)') }}</option>
                                    @foreach($designers as $des)
                                        <option value="{{ $des->id }}">{{ $des->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                    </div>

                    <div class="px-5 py-3 border-t border-line bg-canvas flex items-center justify-end gap-2">
                        <button 
                            type="button" 
                            @click="subtaskModalOpen = false"
                            class="px-3 py-1.5 rounded-lg border border-[#d0d0ce] bg-white hover:bg-stone-50 text-zinc-700 font-semibold text-xs transition cursor-pointer">
                            {{ __('Cancelar') }}
                        </button>
                        <button 
                            type="button" 
                            @click="submitSubtaskModal()"
                            class="px-4 py-1.5 rounded-lg bg-ink hover:bg-black text-white font-semibold text-xs transition cursor-pointer flex items-center gap-1.5">
                            <x-lucide-check class="w-3.5 h-3.5" />
                            <span x-text="subtaskOrderId ? @js(__('Crear Subtarea')) : @js(__('Crear Nota'))"></span>
                        </button>
                    </div>

                </div>
            </div>
        </template>

        <!-- Modal Vincular Nota a Orden -->
        <template x-if="linkNoteModalOpen">
            <div 
                class="fixed inset-0 z-[105] overflow-y-auto bg-stone-900/50 flex items-center justify-center p-4"
                @keydown.window.escape="linkNoteModalOpen = false"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0">
            
                <div 
                    @click.outside="linkNoteModalOpen = false"
                    class="bg-white border border-line rounded-xl shadow-panel max-w-md w-full flex flex-col transition duration-200 overflow-visible relative"
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100">
                    
                    <div class="px-5 py-4 border-b border-line bg-canvas flex items-center justify-between">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="p-2 rounded-lg bg-sunken text-ink-2 shrink-0">
                                <x-lucide-link class="w-4 h-4" />
                            </div>
                            <div class="min-w-0">
                                <h3 class="font-semibold text-sm text-zinc-900 leading-snug">{{ __('Vincular Nota a Orden') }}</h3>
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
                        <div class="bg-sunken border border-line rounded-lg p-3 text-ink space-y-1">
                            <div class="font-semibold flex items-center gap-1.5 text-xs">
                                <x-lucide-info class="w-3.5 h-3.5 text-ink-2 shrink-0" />
                                <span>{{ __('Vinculación Obligatoria') }}</span>
                            </div>
                            <p class="text-meta leading-relaxed text-ink-2">
                                {{ __('Para marcar una nota como completada o asociarla al timeline de un cliente, debes vincularla a una orden. La información redundante del título (empresa/ubicación) se limpiará automáticamente.') }}
                            </p>
                            <div class="pt-1 font-semibold text-meta text-ink flex items-center gap-1">
                                <span>{{ __('Nota:') }}</span>
                                <span class="italic font-semibold text-zinc-900" x-text="linkNoteTitle"></span>
                            </div>
                        </div>

                        <div class="space-y-1 relative" @click.outside="linkNoteDropdownOpen = false">
                            <label class="font-semibold text-zinc-700 block flex items-center justify-between">
                                <span>{{ __('Seleccionar Orden del Workspace') }} <span class="text-danger">*</span></span>
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
                                    class="w-full bg-canvas focus:bg-white border border-line focus:border-stone-400 rounded-lg pl-9 pr-8 py-2 text-xs text-zinc-800 focus:outline-none transition font-medium" />

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
                                x-cloak
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                class="absolute left-0 right-0 top-full mt-1 z-50 bg-white border border-line rounded-xl shadow-panel max-h-56 overflow-y-auto divide-y divide-stone-100 text-xs"
                                style="display: none;">
                                
                                <template x-for="(item, idx) in getFilteredLinkNoteOrders()" :key="item.id">
                                    <button 
                                        type="button"
                                        @click="linkNoteOrderId = item.id; linkNoteSearchQuery = item.text; linkNoteDropdownOpen = false; submitLinkNoteModal();"
                                        @mouseenter="linkNoteHighlightedIndex = idx"
                                        :class="{ 'bg-sunken text-ink font-semibold': linkNoteHighlightedIndex === idx || (linkNoteHighlightedIndex === -1 && linkNoteOrderId === item.id), 'hover:bg-stone-100': linkNoteHighlightedIndex !== idx }"
                                        class="link-note-order-item-btn w-full text-left p-2.5 focus:outline-none cursor-pointer flex items-center justify-between gap-2 transition">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-1.5 min-w-0">
                                                <span class="font-semibold text-zinc-900 truncate text-xs" x-text="item.company"></span>
                                                <template x-if="item.location">
                                                    <span class="text-meta font-semibold text-ink-2 bg-sunken px-1.5 py-0.2 rounded border border-line shrink-0 truncate max-w-[150px]" x-text="item.location"></span>
                                                </template>
                                            </div>
                                            <span class="text-meta text-zinc-500 block truncate" x-text="item.task"></span>
                                        </div>
                                        <template x-if="linkNoteOrderId === item.id">
                                            <x-lucide-check class="w-3.5 h-3.5 text-ink-2 shrink-0 stroke-[2.5]" />
                                        </template>
                                    </button>
                                </template>

                                <template x-if="getFilteredLinkNoteOrders().length === 0">
                                    <div class="p-3 text-center text-zinc-400 italic text-meta">
                                        {{ __('No se encontraron órdenes coincidentes.') }}
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div class="px-5 py-3 border-t border-line bg-canvas flex items-center justify-end gap-2">
                        <button 
                            type="button" 
                            @click="linkNoteModalOpen = false"
                            class="px-3 py-1.5 rounded-lg border border-[#d0d0ce] bg-white hover:bg-stone-50 text-zinc-700 font-semibold text-xs transition cursor-pointer">
                            {{ __('Cancelar') }}
                        </button>
                        <button 
                            type="button" 
                            @click="submitLinkNoteModal()"
                            class="px-4 py-1.5 rounded-lg bg-ink hover:bg-black text-white font-semibold text-xs transition cursor-pointer flex items-center gap-1.5">
                            <x-lucide-link class="w-3.5 h-3.5" />
                            <span>{{ __('Vincular a Orden') }}</span>
                        </button>
                    </div>

                </div>
            </div>
        </template>
    </div>

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
        class="fixed bottom-5 left-5 z-50 flex items-center gap-3 px-3.5 py-2 rounded-card bg-surface text-stone-800 text-xs font-medium shadow-menu border border-stone-200/90 backdrop-blur-md">
        <div class="flex items-center gap-2 min-w-0">
            <span class="w-2 h-2 rounded-full bg-danger-soft0 shrink-0"></span>
            <span class="text-stone-700 text-meta font-semibold truncate" x-text="undoToastMessage"></span>
        </div>
        <button 
            wire:click="undoLastAction" 
            @click="hideUndoToast()"
            type="button" 
            class="px-2.5 py-1 rounded-lg bg-ink hover:bg-black text-white font-semibold text-meta flex items-center gap-1.5 shrink-0 transition cursor-pointer">
            <x-lucide-undo-2 class="w-3 h-3 text-stone-300" />
            <span>{{ __('Deshacer') }}</span>
            <kbd class="hidden sm:inline-block px-1.5 py-0.2 text-meta bg-white/20 text-white rounded font-mono">⌘Z</kbd>
        </button>
        <button @click="hideUndoToast()" type="button" class="p-1 text-stone-400 hover:text-stone-700 hover:bg-stone-100 rounded-md transition">
            <x-lucide-x class="w-3.5 h-3.5" />
        </button>
    </div>

    <!-- Floating Bottom-Center Bulk Actions Toolbar -->
    <div 
        x-show="selectedTaskIds.length > 0"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-6 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-6 scale-95"
        x-cloak
        style="display: none;"
        class="fixed bottom-5 left-1/2 -translate-x-1/2 z-50 flex items-center gap-2 px-3 py-2 rounded-panel bg-surface text-ink text-xs shadow-panel border border-line">
        
        <div class="flex items-center gap-2 pr-2.5 border-r border-line">
            <span class="w-2 h-2 rounded-full bg-ink"></span>
            <span class="font-semibold text-meta whitespace-nowrap text-ink">
                <span x-text="selectedTaskIds.length"></span> {{ __('seleccionadas') }}
            </span>
        </div>

        <!-- Quick Move Buttons -->
        <div class="flex items-center gap-1">
            <button 
                type="button" 
                @click="$wire.moveMultipleSubtasks(selectedTaskIds, '{{ now()->toDateString() }}'); clearSelection();"
                class="px-2.5 py-1 rounded-lg bg-sunken hover:bg-line text-ink-2 hover:text-ink transition cursor-pointer text-meta font-medium flex items-center gap-1"
                title="{{ __('Mover a Hoy') }}">
                <x-lucide-calendar class="w-3.5 h-3.5 text-ink-2" />
                <span>{{ __('Hoy') }}</span>
            </button>

            <button 
                type="button" 
                @click="$wire.moveMultipleSubtasks(selectedTaskIds, '{{ now()->addDay()->toDateString() }}'); clearSelection();"
                class="px-2.5 py-1 rounded-lg bg-sunken hover:bg-line text-ink-2 hover:text-ink transition cursor-pointer text-meta font-medium flex items-center gap-1"
                title="{{ __('Mover a Mañana') }}">
                <span>{{ __('Mañana') }}</span>
            </button>

            <button 
                type="button" 
                @click="$wire.moveMultipleSubtasks(selectedTaskIds, '{{ now()->addWeek()->startOfWeek()->toDateString() }}'); clearSelection();"
                class="px-2.5 py-1 rounded-lg bg-sunken hover:bg-line text-ink-2 hover:text-ink transition cursor-pointer text-meta font-medium flex items-center gap-1"
                title="{{ __('Mover a Próxima Semana') }}">
                <span>{{ __('Próx. Sem.') }}</span>
            </button>
        </div>

        <!-- Reassign Designer Dropdown -->
        <div class="relative" x-data="{ openDesDropdown: false }" @click.outside="openDesDropdown = false">
            <button 
                type="button" 
                @click="openDesDropdown = !openDesDropdown"
                class="px-2.5 py-1 rounded-lg bg-sunken hover:bg-line text-ink-2 hover:text-ink transition cursor-pointer text-meta font-medium flex items-center gap-1.5">
                <x-lucide-user-check class="w-3.5 h-3.5 text-ink-2" />
                <span>{{ __('Diseñador') }}</span>
                <x-lucide-chevron-up class="w-3 h-3 text-zinc-400 transition-transform" x-bind:class="openDesDropdown ? 'rotate-180' : ''" />
            </button>

            <div 
                x-show="openDesDropdown" 
                x-cloak
                style="display: none;"
                class="absolute bottom-full left-0 mb-2 w-48 bg-surface border border-line rounded-card shadow-menu overflow-hidden py-1 text-ink text-meta z-50">
                <div class="px-2.5 py-1 text-meta font-semibold  text-stone-400 border-b border-stone-100">
                    {{ __('Reasignar a Diseñador') }}
                </div>
                <div class="max-h-48 overflow-y-auto">
                    @foreach($allDesigners as $des)
                        <button 
                            type="button"
                            @click="$wire.moveMultipleSubtasks(selectedTaskIds, '', {{ $des->id }}); openDesDropdown = false; clearSelection();"
                            class="w-full text-left px-2.5 py-1.5 hover:bg-sunken hover:text-ink flex items-center gap-2 cursor-pointer transition">
                            <span class="w-2 h-2 rounded-full shrink-0 {{ $des->dot_color_class }}" style="{{ $des->dot_inline_style }}"></span>
                            <span class="truncate font-medium">{{ $des->name }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Bulk Complete -->
        <button 
            type="button" 
            @click="$wire.bulkToggleComplete(selectedTaskIds, true); clearSelection();"
            class="px-2.5 py-1 rounded-lg bg-ink hover:bg-black text-white transition cursor-pointer text-meta font-semibold flex items-center gap-1"
            title="{{ __('Marcar todas como completadas') }}">
            <x-lucide-check class="w-3.5 h-3.5" />
            <span>{{ __('Completar') }}</span>
        </button>

        <!-- Deselect All / Cancel -->
        <button 
            type="button" 
            @click="clearSelection()" 
            class="p-1 rounded-lg text-ink-3 hover:text-ink hover:bg-sunken transition cursor-pointer ml-1"
            title="{{ __('Deseleccionar todo (Esc)') }}">
            <x-lucide-x class="w-3.5 h-3.5" />
        </button>
    </div>
