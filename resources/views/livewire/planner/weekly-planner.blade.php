<div class="space-y-5" x-data="{ calendarOpen: false }">
    
    <!-- Top Notion/Linear Rich Header & Week Navigator -->
    <div class="bg-white border border-[#e9e9e7] rounded-xl p-4 shadow-2xs space-y-4">
        
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            
            <!-- Left Info & Selected Week Badge -->
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-stone-800 to-stone-950 flex items-center justify-center text-white shadow-xs shrink-0">
                    <x-lucide-calendar-days class="w-5 h-5" />
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <h2 class="text-sm font-bold text-zinc-900 tracking-tight">Planificador Semanal de Diseño</h2>
                        @php
                            $startVal = Carbon\Carbon::parse($selectedWeekStart);
                            $endVal = $startVal->copy()->addDays(4);
                            $isThisWeek = $startVal->isCurrentWeek();
                        @endphp
                        @if($isThisWeek)
                            <span class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-[10px] font-semibold border border-emerald-200 flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                Semana Actual
                            </span>
                        @endif
                    </div>
                    <p class="text-xs text-zinc-500 truncate mt-0.5">
                        Lunes {{ $startVal->format('d') }} de {{ $startVal->translatedFormat('F') }} al Viernes {{ $endVal->format('d') }} de {{ $endVal->translatedFormat('F, Y') }}
                    </p>
                </div>
            </div>

            <!-- Right Controls: Navigation + Mini Calendar Popover Toggle -->
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                
                <!-- Quick Navigation Pills -->
                <div class="flex items-center bg-[#f7f7f5] border border-[#e3e3e1] p-1 rounded-lg gap-1">
                    <button wire:click="previousWeek" class="p-1.5 rounded-md hover:bg-white hover:shadow-2xs text-zinc-700 transition" title="Semana anterior">
                        <x-lucide-chevron-left class="w-4 h-4" />
                    </button>

                    <button wire:click="thisWeek" class="px-2.5 py-1 rounded-md text-xs font-semibold {{ $isThisWeek ? 'bg-emerald-600 text-white shadow-2xs' : 'hover:bg-white text-zinc-700' }} transition">
                        Esta Semana
                    </button>

                    <button wire:click="nextWeek" class="p-1.5 rounded-md hover:bg-white hover:shadow-2xs text-zinc-700 transition" title="Semana siguiente">
                        <x-lucide-chevron-right class="w-4 h-4" />
                    </button>
                </div>

                <!-- Jump Weeks Quick Dropdown -->
                <div class="flex items-center gap-1 text-xs">
                    <button wire:click="jumpWeeks(1)" class="px-2.5 py-1.5 rounded-lg border border-indigo-200 bg-indigo-50/70 hover:bg-indigo-100 text-indigo-700 font-medium transition flex items-center gap-1">
                        <span>+1 Sem</span>
                    </button>
                    <button wire:click="jumpWeeks(2)" class="px-2.5 py-1.5 rounded-lg border border-violet-200 bg-violet-50/70 hover:bg-violet-100 text-violet-700 font-medium transition flex items-center gap-1">
                        <span>+2 Sem</span>
                    </button>
                </div>

                <!-- Mini-Calendar Popover Button -->
                <div class="relative" @click.outside="calendarOpen = false">
                    <button 
                        @click="calendarOpen = !calendarOpen"
                        class="px-3 py-1.5 rounded-lg bg-stone-900 hover:bg-stone-800 text-white text-xs font-semibold shadow-2xs transition flex items-center gap-2">
                        <x-lucide-calendar class="w-3.5 h-3.5 text-stone-200" />
                        <span>Abrir Calendario</span>
                        <x-lucide-chevron-down class="w-3.5 h-3.5 text-stone-400" />
                    </button>

                    <!-- Mini-Calendar Interactive Popover Modal -->
                    <div 
                        x-show="calendarOpen"
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        class="absolute right-0 top-full mt-2 w-80 z-50 bg-white border border-[#e9e9e7] rounded-xl shadow-2xl p-4 text-xs space-y-3">
                        
                        <!-- Month Header Navigator -->
                        <div class="flex items-center justify-between border-b border-[#f0f0ee] pb-2">
                            <button wire:click="previousMonth" class="p-1 rounded hover:bg-stone-100 text-zinc-600 transition">
                                <x-lucide-chevron-left class="w-4 h-4" />
                            </button>
                            <span class="font-bold text-zinc-900 text-xs capitalize">
                                {{ Carbon\Carbon::parse($viewMonth . '-01')->translatedFormat('F Y') }}
                            </span>
                            <button wire:click="nextMonth" class="p-1 rounded hover:bg-stone-100 text-zinc-600 transition">
                                <x-lucide-chevron-right class="w-4 h-4" />
                            </button>
                        </div>

                        <!-- Days of Week Header -->
                        <div class="grid grid-cols-7 text-center font-bold text-[10px] text-zinc-400 uppercase tracking-wider">
                            <span>Lun</span><span>Mar</span><span>Mié</span><span>Jue</span><span>Vie</span><span class="text-zinc-300">Sáb</span><span class="text-zinc-300">Dom</span>
                        </div>

                        <!-- Days Grid -->
                        <div class="grid grid-cols-7 gap-1 text-center font-medium">
                            @foreach($this->miniCalendarDays as $calDay)
                                <button 
                                    wire:click="selectWeekFromDate('{{ $calDay['date_string'] }}')"
                                    @click="calendarOpen = false"
                                    class="p-1.5 rounded-md text-xs transition relative group
                                        {{ $calDay['is_selected_week'] ? 'bg-indigo-600 text-white font-bold shadow-xs' : ($calDay['is_current_month'] ? 'text-zinc-800 hover:bg-stone-100' : 'text-zinc-300 hover:bg-stone-50') }}
                                        {{ $calDay['is_today'] && !$calDay['is_selected_week'] ? 'ring-1 ring-emerald-500 font-bold text-emerald-700' : '' }}">
                                    <span>{{ $calDay['day_number'] }}</span>
                                </button>
                            @endforeach
                        </div>

                        <!-- Quick Native Date Direct Picker -->
                        <div class="pt-2 border-t border-[#f0f0ee] flex items-center justify-between">
                            <span class="text-[11px] text-zinc-500">O elige fecha exacta:</span>
                            <input 
                                type="date" 
                                wire:model.live="selectedWeekStart" 
                                @change="calendarOpen = false"
                                class="bg-[#fbfbfa] border border-[#e9e9e7] rounded px-2 py-0.5 text-[11px] font-mono text-zinc-800">
                        </div>

                    </div>
                </div>

            </div>

        </div>

    </div>

    <!-- Notion Designer Filter Tabs Bar -->
    <div class="flex items-center gap-1 border-b border-[#e9e9e7] pb-2 overflow-x-auto scrollbar-none text-xs">
        <button wire:click="$set('selectedDesignerFilter', 'all')" class="px-3 py-1 rounded-md font-medium transition flex items-center gap-1.5 shrink-0 {{ $selectedDesignerFilter === 'all' ? 'bg-white text-zinc-900 border border-[#d0d0ce] shadow-2xs font-semibold' : 'text-zinc-500 hover:text-zinc-800 hover:bg-[#f2f2f0]' }}">
            <x-lucide-users class="w-3.5 h-3.5 text-zinc-500" />
            <span>Todos los Diseñadores</span>
        </button>

        @foreach($allDesigners as $des)
            <button wire:click="$set('selectedDesignerFilter', '{{ $des->id }}')" class="px-3 py-1 rounded-md font-medium transition flex items-center gap-1.5 shrink-0 {{ $selectedDesignerFilter == $des->id ? 'bg-white text-zinc-900 border border-stone-300 shadow-2xs font-semibold' : 'text-zinc-500 hover:text-zinc-800 hover:bg-[#f2f2f0]' }}">
                <x-lucide-user class="w-3.5 h-3.5 text-zinc-500" />
                <span>{{ $des->name }}</span>
            </button>
        @endforeach
    </div>

    <!-- Alert Flash Messages -->
    @if (session()->has('warning'))
        <div class="bg-amber-50 border border-amber-200 text-amber-800 p-3 rounded-lg text-xs font-medium flex items-center gap-2">
            <x-lucide-alert-triangle class="w-4 h-4 text-amber-600 shrink-0" />
            <span class="truncate">{{ session('warning') }}</span>
        </div>
    @endif
    @if (session()->has('message'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-3 rounded-lg text-xs font-medium flex items-center gap-2">
            <x-lucide-check-circle-2 class="w-4 h-4 text-emerald-600 shrink-0" />
            <span class="truncate">{{ session('message') }}</span>
        </div>
    @endif

    <!-- Unscheduled Orders Pool -->
    @if($unscheduledOrders->count() > 0)
        <div 
            x-data="{ draggingOver: false }"
            @dragover.prevent="draggingOver = true"
            @dragenter.prevent="draggingOver = true"
            @dragleave="if (!$el.contains($event.relatedTarget)) draggingOver = false"
            @drop.prevent="
                draggingOver = false;
                let id = $event.dataTransfer.getData('text/plain');
                if (id) {
                    $wire.unscheduleOrder(id);
                }
            "
            :class="{ 'border-amber-500 bg-amber-50/70 ring-2 ring-amber-400': draggingOver }"
            class="bg-white border border-[#e9e9e7] rounded-xl p-4 space-y-3 shadow-2xs transition-all">
            <div class="flex items-center justify-between border-b border-[#e9e9e7] pb-2 min-w-0">
                <h3 class="font-semibold text-xs text-zinc-700 uppercase tracking-wider flex items-center gap-2 truncate">
                    <x-lucide-clock class="w-4 h-4 text-zinc-500 shrink-0" /> Órdenes Pendientes por Programar en Agenda ({{ $unscheduledOrders->count() }})
                </h3>
                <span class="text-[11px] text-zinc-400 shrink-0 whitespace-nowrap">Arrastra al día deseado o selecciona en la lista</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5">
                @foreach($unscheduledOrders as $unOrder)
                    <div 
                        draggable="true" 
                        @dragstart="e => e.dataTransfer.setData('text/plain', '{{ $unOrder->id }}')"
                        class="bg-[#fbfbfa] hover:bg-stone-50 border border-[#e9e9e7] hover:border-stone-300 rounded-lg p-2.5 space-y-2 min-w-0 transition cursor-grab active:cursor-grabbing shadow-2xs group">
                        <div class="flex items-start justify-between gap-1 min-w-0">
                            <div class="min-w-0 flex-1">
                                <span class="text-[10px] text-zinc-500 font-medium uppercase truncate block">{{ $unOrder->designer?->name }}</span>
                                <h4 class="font-medium text-xs text-zinc-900 truncate" title="{{ $unOrder->company_name }}">{{ $unOrder->company_name }}</h4>
                            </div>
                            <x-lucide-grip-vertical class="w-3.5 h-3.5 text-zinc-300 group-hover:text-zinc-500 shrink-0" />
                        </div>

                        <div class="flex items-center justify-between text-[10px] text-zinc-500">
                            <span>Vence: <strong class="font-mono text-zinc-800">{{ $unOrder->current_due_date ? $unOrder->current_due_date->format('d M') : 'N/A' }}</strong></span>
                            <button wire:click="$dispatch('open-order-detail', { orderId: {{ $unOrder->id }} })" class="text-stone-500 hover:text-stone-900 font-medium">Detalle</button>
                        </div>

                        <!-- Schedule Day Dropdown -->
                        <select wire:change="scheduleOrder({{ $unOrder->id }}, $event.target.value)" class="bg-white border border-[#e9e9e7] rounded px-2 py-1 text-[10px] text-zinc-700 focus:outline-none w-full truncate cursor-pointer">
                            <option value="">Programar para día...</option>
                            @foreach($days as $day)
                                <option value="{{ $day['date_string'] }}">{{ $day['day_name'] }} ({{ ($day['is_next_week'] ?? false) ? $day['range_label'] : $day['date']->format('d M') }})</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Weekly Grid by Designer -->
    <div class="space-y-6">
        @foreach($designers as $designer)
            <div class="bg-white border border-[#e9e9e7] rounded-xl p-4 space-y-3 shadow-2xs">
                
                <div class="flex items-center justify-between border-b border-[#e9e9e7] pb-2">
                    <div class="flex items-center gap-2.5">
                        <div class="w-6 h-6 rounded bg-stone-100 border border-stone-200 flex items-center justify-center font-bold text-zinc-700 text-xs shrink-0">
                            {{ substr($designer->name, 0, 1) }}
                        </div>
                        <div>
                            <h3 class="font-semibold text-xs text-zinc-900">Diseñador/a: {{ $designer->name }}</h3>
                        </div>
                    </div>
                </div>

                <!-- 6 Columns Grid (Monday-Friday + Next Week) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
                    @foreach($days as $day)
                        @php
                            $isNextWeek = $day['is_next_week'] ?? false;
                            $dayOrders = $isNextWeek
                                ? $designer->orders->filter(fn($o) => $o->scheduled_date && $o->scheduled_date->gte(Carbon\Carbon::parse($day['date_string'])))
                                : $designer->orders->filter(fn($o) => $o->scheduled_date?->toDateString() === $day['date_string']);

                            $dayColorClass = match($day['day_name'] ?? '') {
                                'Lunes' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                                'Martes' => 'bg-sky-50 text-sky-700 border-sky-200',
                                'Miércoles' => 'bg-teal-50 text-teal-700 border-teal-200',
                                'Jueves' => 'bg-amber-50 text-amber-800 border-amber-200',
                                'Viernes' => 'bg-rose-50 text-rose-700 border-rose-200',
                                'Next Week' => 'bg-violet-50 text-violet-900 border-violet-300 border-dashed',
                                default => 'bg-stone-50 text-stone-700 border-stone-200',
                            };
                        @endphp
                        <div 
                            x-data="{ draggingOver: false }"
                            @dragover.prevent="draggingOver = true"
                            @dragenter.prevent="draggingOver = true"
                            @dragleave="if (!$el.contains($event.relatedTarget)) draggingOver = false"
                            @drop.prevent="
                                draggingOver = false;
                                let id = $event.dataTransfer.getData('text/plain');
                                if (id) {
                                    $wire.scheduleOrder(id, '{{ $day['date_string'] }}');
                                }
                            "
                            :class="{ 'border-indigo-500 bg-indigo-50/60 ring-2 ring-indigo-300': draggingOver, '{{ $isNextWeek ? 'bg-[#f4f4f2] border-dashed border-stone-300' : 'bg-[#fbfbfa] border-[#e9e9e7]' }}': !draggingOver }"
                            class="border rounded-lg p-2.5 space-y-2 min-h-[140px] flex flex-col justify-between min-w-0 transition-all">
                            
                            <div class="min-w-0">
                                <div class="flex items-center justify-between border-b border-[#e9e9e7] pb-1.5 mb-2">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold border uppercase tracking-wider {{ $dayColorClass }}">
                                        {{ $day['day_name'] }}
                                    </span>
                                    <span class="text-[10px] font-mono font-medium text-zinc-500">
                                        {{ $isNextWeek ? $day['range_label'] : $day['date']->format('d M') }}
                                    </span>
                                </div>

                                @if($dayOrders->isEmpty())
                                    <p class="text-[11px] text-zinc-400 text-center py-4 select-none">Sin trabajo agendado</p>
                                @else
                                    <div class="space-y-1.5">
                                        @foreach($dayOrders as $order)
                                            <div 
                                                draggable="true" 
                                                @dragstart="e => e.dataTransfer.setData('text/plain', '{{ $order->id }}')"
                                                class="bg-white border border-[#e9e9e7] hover:border-stone-300 rounded p-2 space-y-1 min-w-0 shadow-2xs cursor-grab active:cursor-grabbing hover:shadow-xs transition group">
                                                <div class="flex items-center justify-between gap-1 min-w-0">
                                                    <span class="font-medium text-xs text-zinc-900 truncate" title="{{ $order->company_name }}">{{ $order->company_name }}</span>
                                                    <div class="flex items-center gap-1 shrink-0">
                                                        @if($order->isOverdue())
                                                            <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse" title="SLA Vencido"></span>
                                                        @endif
                                                        <button wire:click="unscheduleOrder({{ $order->id }})" class="p-0.5 text-zinc-400 hover:text-red-600 transition" title="Desprogramar / Quitar de agenda">
                                                            <x-lucide-x-circle class="w-3 h-3" />
                                                        </button>
                                                        <button wire:click="$dispatch('open-order-detail', { orderId: {{ $order->id }} })" class="p-0.5 text-zinc-400 hover:text-zinc-700 transition" title="Ver detalle">
                                                            <x-lucide-panel-right class="w-3 h-3" />
                                                        </button>
                                                    </div>
                                                </div>
                                                <p class="text-[10px] text-zinc-500 truncate" title="{{ $order->task_name }}">{{ $order->task_name }}</p>

                                                @if($order->current_due_date && Carbon\Carbon::parse($day['date_string'])->isAfter($order->current_due_date))
                                                    <div class="mt-1 px-1.5 py-0.5 rounded bg-amber-50 text-amber-700 text-[9px] font-medium border border-amber-200 truncate">
                                                        ⚠️ Supera SLA
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <span class="text-[10px] text-zinc-400 font-mono text-right block pt-1.5 border-t border-[#e9e9e7] shrink-0 select-none">
                                {{ $dayOrders->count() }} órdenes
                            </span>

                        </div>
                    @endforeach
                </div>

            </div>
        @endforeach
    </div>

</div>

