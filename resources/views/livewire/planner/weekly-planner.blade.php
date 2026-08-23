<div 
    wire:poll.3s 
    x-data="{ 
        calendarOpen: false,
        scrollSpeedY: 0,
        scrollIntervalY: null,
        handleDragOverY(e) {
            const container = $el;
            const rect = container.getBoundingClientRect();
            const mouseY = e.clientY;
            const threshold = 100;
            const maxSpeed = 24;

            let speed = 0;
            if (mouseY - rect.top < threshold && mouseY - rect.top > 0) {
                const intensity = (threshold - (mouseY - rect.top)) / threshold;
                speed = -Math.max(6, Math.round(intensity * maxSpeed));
            } else if (rect.bottom - mouseY < threshold && rect.bottom - mouseY > 0) {
                const intensity = (threshold - (rect.bottom - mouseY)) / threshold;
                speed = Math.max(6, Math.round(intensity * maxSpeed));
            }

            this.scrollSpeedY = speed;

            if (speed !== 0 && !this.scrollIntervalY) {
                const step = () => {
                    if (this.scrollSpeedY !== 0) {
                        container.scrollTop += this.scrollSpeedY;
                        this.scrollIntervalY = requestAnimationFrame(step);
                    } else {
                        this.scrollIntervalY = null;
                    }
                };
                this.scrollIntervalY = requestAnimationFrame(step);
            } else if (speed === 0 && this.scrollIntervalY) {
                cancelAnimationFrame(this.scrollIntervalY);
                this.scrollIntervalY = null;
            }
        },
        stopAutoScrollY() {
            this.scrollSpeedY = 0;
            if (this.scrollIntervalY) {
                cancelAnimationFrame(this.scrollIntervalY);
                this.scrollIntervalY = null;
            }
        }
    }"
    @dragover="handleDragOverY($event)"
    @dragend="stopAutoScrollY()"
    @drop="stopAutoScrollY()"
    @dragleave.self="stopAutoScrollY()"
    class="h-full flex flex-col space-y-4 min-h-0 overflow-y-auto custom-vertical-scrollbar pr-1">
    
    <!-- Top Notion/Linear Rich Header & Week Navigator -->
    <div class="bg-white border border-[#e9e9e7] rounded-xl p-4 shadow-2xs shrink-0 relative z-20">
        
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3.5">
            
            <!-- Left Info & Selected Week Badge -->
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-9 h-9 rounded-lg bg-stone-900 text-white flex items-center justify-center shrink-0 shadow-2xs">
                    <x-lucide-calendar-days class="w-4.5 h-4.5 text-stone-100" />
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h1 class="text-base sm:text-lg font-bold text-zinc-900 tracking-tight">Planificador Semanal</h1>
                        @php
                            $startVal = Carbon\Carbon::parse($selectedWeekStart);
                            $endVal = $startVal->copy()->addDays(4);
                            $isThisWeek = $startVal->isCurrentWeek();
                        @endphp
                        @if($isThisWeek)
                            <span class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-[10px] font-bold border border-emerald-200 flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                Semana Actual
                            </span>
                        @endif
                    </div>
                    <p class="text-xs text-zinc-500 truncate mt-0.5">
                        Lunes {{ $startVal->format('d') }} de {{ $startVal->locale('es')->translatedFormat('F') }} al Viernes {{ $endVal->format('d') }} de {{ $endVal->locale('es')->translatedFormat('F, Y') }}
                    </p>
                </div>
            </div>

            <!-- Right Controls: Navigation + Mini Calendar Popover Toggle -->
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                
                <!-- Quick Navigation Pills with Active Week Dates -->
                <div class="flex items-center bg-[#f7f7f5] border border-[#e3e3e1] p-1 rounded-lg gap-1.5 h-8">
                    <button wire:click="previousWeek" class="p-1 rounded-md hover:bg-white hover:shadow-2xs text-zinc-700 transition cursor-pointer" title="Semana anterior">
                        <x-lucide-chevron-left class="w-4 h-4" />
                    </button>

                    <span class="px-2 font-bold text-xs text-zinc-900 font-mono tracking-tight select-none">
                        {{ $startVal->format('d M') }} - {{ $endVal->format('d M') }}
                    </span>

                    <button wire:click="nextWeek" class="p-1 rounded-md hover:bg-white hover:shadow-2xs text-zinc-700 transition cursor-pointer" title="Semana siguiente">
                        <x-lucide-chevron-right class="w-4 h-4" />
                    </button>
                </div>

                <!-- Dedicated 'Esta Semana' & 'Próxima Semana' Filter Buttons -->
                @php
                    $nextWeekMonday = now()->addWeek()->startOfWeek(Carbon\Carbon::MONDAY)->toDateString();
                    $isNextWeek = $startVal->toDateString() === $nextWeekMonday;
                @endphp
                <button 
                    wire:click="thisWeek" 
                    class="px-3 py-1 h-8 rounded-lg text-xs font-semibold shadow-2xs transition flex items-center gap-1.5 cursor-pointer {{ $isThisWeek ? 'bg-emerald-600 text-white shadow-xs' : 'bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200' }}"
                    title="Ir a la semana actual">
                    <x-lucide-calendar-days class="w-3.5 h-3.5" />
                    <span>Esta Semana</span>
                </button>

                <button 
                    wire:click="jumpWeeks(1)" 
                    class="px-3 py-1 h-8 rounded-lg text-xs font-semibold shadow-2xs transition flex items-center gap-1.5 cursor-pointer {{ $isNextWeek ? 'bg-indigo-600 text-white shadow-xs' : 'bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200' }}"
                    title="Ir a la próxima semana">
                    <x-lucide-arrow-right-circle class="w-3.5 h-3.5" />
                    <span>Próxima Semana</span>
                </button>

                <!-- Mini-Calendar Popover Button -->
                <div class="relative" @click.outside="calendarOpen = false">
                    <button 
                        @click="calendarOpen = !calendarOpen"
                        class="px-3 py-1.5 h-8 rounded-lg bg-stone-900 hover:bg-stone-800 text-white text-xs font-semibold shadow-2xs transition flex items-center gap-2 cursor-pointer">
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
    <div class="flex items-center gap-1 border-b border-[#e9e9e7] pb-2 pt-1 overflow-x-auto scrollbar-none text-xs shrink-0 relative z-10">
        <button wire:click="$set('selectedDesignerFilter', 'all')" class="px-3 py-1 rounded-md font-medium transition flex items-center gap-1.5 shrink-0 {{ $selectedDesignerFilter === 'all' ? 'bg-white text-zinc-900 border border-[#d0d0ce] shadow-2xs font-semibold' : 'text-zinc-500 hover:text-zinc-800 hover:bg-[#f2f2f0]' }}">
            <x-lucide-users class="w-3.5 h-3.5 text-zinc-500" />
            <span>Todos los Diseñadores</span>
        </button>

        @foreach($allDesigners as $des)
            <button wire:click="$set('selectedDesignerFilter', '{{ $des->id }}')" class="px-3 py-1 rounded-md font-medium transition flex items-center gap-1.5 shrink-0 {{ $selectedDesignerFilter == $des->id ? 'bg-white text-zinc-900 border border-stone-300 shadow-2xs font-semibold' : 'text-zinc-500 hover:text-zinc-800 hover:bg-[#f2f2f0]' }}">
                <span class="w-2 h-2 rounded-full {{ $des->dot_color_class }}"></span>
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

    <!-- Unscheduled Orders Pool (Horizontal Scroll Strip + Searchbar) -->
    @if($unscheduledOrders->count() > 0 || !empty($unscheduledSearch))
        <div 
            x-data="{ draggingOver: false }"
            @dragover.prevent="draggingOver = true"
            @dragenter.prevent="draggingOver = true"
            @dragleave="if (!$el.contains($event.relatedTarget)) draggingOver = false"
            @drop.prevent="
                draggingOver = false;
                let rawData = $event.dataTransfer.getData('text/plain');
                if (rawData && !rawData.startsWith('subtask:')) {
                    let id = rawData.replace('order:', '');
                    $wire.unscheduleOrder(id);
                }
            "
            :class="{ 'border-amber-500 bg-amber-50/70 ring-2 ring-amber-400': draggingOver }"
            class="bg-white border border-[#e9e9e7] rounded-xl p-4 space-y-3 shadow-2xs transition-all shrink-0">
            
            <!-- Header Bar: Title + Searchbar + Instructions -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-[#e9e9e7] pb-2.5 min-w-0">
                <div class="flex flex-wrap items-center gap-3 min-w-0 flex-1">
                    <h3 class="font-bold text-xs text-zinc-800 uppercase tracking-wider flex items-center gap-2 truncate shrink-0">
                        <x-lucide-clock class="w-4 h-4 text-indigo-600 shrink-0" />
                        <span>ÓRDENES PENDIENTES POR PROGRAMAR EN AGENDA ({{ $unscheduledOrders->count() }})</span>
                    </h3>

                    <!-- Search Input for Unscheduled Cards -->
                    <div class="relative flex-1 min-w-[220px] max-w-sm">
                        <x-lucide-search class="w-3.5 h-3.5 text-zinc-400 absolute left-2.5 top-1/2 -translate-y-1/2" />
                        <input 
                            type="text" 
                            wire:model.live.debounce.150ms="unscheduledSearch" 
                            placeholder="Buscar por empresa o trabajo..." 
                            class="bg-[#fbfbfa] focus:bg-white border border-[#e9e9e7] focus:border-stone-400 rounded-lg pl-8 pr-2.5 py-1 text-xs text-zinc-800 focus:outline-none w-full font-normal shadow-2xs transition" />
                        @if($unscheduledSearch)
                            <button 
                                wire:click="$set('unscheduledSearch', '')" 
                                type="button" 
                                class="absolute right-2 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-700">
                                <x-lucide-x class="w-3 h-3" />
                            </button>
                        @endif
                    </div>
                </div>

                <span class="text-[11px] text-zinc-400 shrink-0 whitespace-nowrap hidden lg:inline-block">
                    Arrastra al día deseado o selecciona en la lista
                </span>
            </div>

            <!-- Horizontal Scroll Clipped Cards Container -->
            <div class="flex gap-3 overflow-x-auto overflow-y-hidden custom-horizontal-scrollbar py-1 scrollbar-thin shrink-0 w-full min-h-[135px]">
                @forelse($unscheduledOrders as $unOrder)
                    <div 
                        draggable="true" 
                        @dragstart="e => e.dataTransfer.setData('text/plain', 'order:{{ $unOrder->id }}')"
                        class="shrink-0 w-72 rounded-xl p-3 space-y-2.5 transition cursor-grab active:cursor-grabbing shadow-2xs group flex flex-col justify-between select-none {{ $unOrder->isUrgente() ? ($unOrder->done_today ? 'bg-[#fafaf9] border border-stone-200 opacity-75 ring-0' : 'bg-gradient-to-br from-rose-50/90 via-white to-red-50/70 border-2 border-red-500/90 shadow-md ring-2 ring-red-300/40') : 'bg-[#fbfbfa] hover:bg-white border border-[#e9e9e7] hover:border-stone-300 hover:shadow-xs' }}">
                        
                        <div class="space-y-1.5">
                            <div class="flex items-start justify-between gap-1.5 min-w-0">
                                <div class="flex items-start gap-1.5 min-w-0 flex-1">
                                    <button 
                                        wire:click="toggleDoneToday({{ $unOrder->id }})" 
                                        type="button"
                                        class="w-4 h-4 mt-0.5 rounded-full border transition flex items-center justify-center shrink-0 cursor-pointer {{ $unOrder->done_today ? 'bg-emerald-500 border-emerald-500 text-white shadow-2xs' : 'border-stone-300 hover:border-emerald-500 bg-white text-transparent hover:text-emerald-500/40' }}"
                                        title="{{ $unOrder->done_today ? 'Completado (Clic para desmarcar)' : 'Marcar como completado' }}">
                                        <x-lucide-check class="w-2.5 h-2.5 stroke-[3]" />
                                    </button>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-1 mb-0.5">
                                            @foreach($unOrder->assigned_designers as $des)
                                                <span class="text-[9px] font-semibold px-1 py-0.2 rounded border shrink-0 {{ $des->badge_style }}">{{ $des->name }}</span>
                                            @endforeach
                                            @if($unOrder->scheduled_date)
                                                <span class="text-[9px] font-semibold px-1 py-0.2 rounded bg-indigo-50 text-indigo-700 border border-indigo-200 shrink-0">
                                                    Agendado {{ $unOrder->scheduled_date->format('d M') }}
                                                </span>
                                            @endif
                                        </div>
                                        <h4 class="font-normal text-[11px] text-zinc-500 truncate leading-snug {{ $unOrder->done_today ? 'line-through text-zinc-400' : '' }}" title="{{ $unOrder->company_name }}">{{ $unOrder->company_name }}</h4>
                                        <p class="font-bold text-xs text-zinc-900 truncate mt-0.5 {{ $unOrder->done_today ? 'line-through text-zinc-400' : '' }}" title="{{ $unOrder->task_name }}">{{ $unOrder->task_name }}</p>
                                    </div>
                                </div>
                                <x-lucide-grip-vertical class="w-3.5 h-3.5 text-zinc-300 group-hover:text-zinc-500 shrink-0" />
                            </div>

                            <div class="flex items-center justify-between text-[10px] text-zinc-500 pt-0.5">
                                <span>Vence: <strong class="font-mono text-zinc-800">{{ $unOrder->current_due_date ? $unOrder->current_due_date->format('d M') : 'N/A' }}</strong></span>
                                <button wire:click="$dispatch('open-order-detail', { orderId: {{ $unOrder->id }} })" class="text-stone-500 hover:text-stone-900 font-medium hover:underline">Detalle</button>
                            </div>
                        </div>

                        <!-- Schedule Day Dropdown -->
                        <select wire:change="scheduleOrder({{ $unOrder->id }}, $event.target.value)" class="bg-white border border-[#e9e9e7] rounded-lg px-2 py-1 text-[10px] text-zinc-700 focus:outline-none w-full truncate cursor-pointer font-medium hover:border-stone-300 transition">
                            <option value="">{{ $unOrder->scheduled_date ? 'Reasignar día (' . $unOrder->scheduled_date->format('d M') . ')...' : 'Programar para día...' }}</option>
                            @foreach($days as $day)
                                <option value="{{ $day['date_string'] }}" {{ $unOrder->scheduled_date && $unOrder->scheduled_date->toDateString() === $day['date_string'] ? 'selected' : '' }}>
                                    {{ $day['day_name'] }} ({{ ($day['is_next_week'] ?? false) ? $day['range_label'] : $day['date']->format('d M') }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                @empty
                    <div class="w-full p-6 text-center text-xs text-zinc-400 bg-[#fbfbfa] rounded-xl border border-[#e9e9e7] font-medium flex items-center justify-center gap-2">
                        <x-lucide-search-x class="w-4 h-4 text-zinc-400 shrink-0" />
                        <span>No se encontraron órdenes pendientes por programar {{ $unscheduledSearch ? 'para "' . $unscheduledSearch . '".' : 'en el workspace.' }}</span>
                    </div>
                @endforelse
            </div>
        </div>
    @endif

    <!-- Weekly Grid by Designer -->
    <div class="space-y-6">
        @foreach($designers as $designer)
            @php
                $designerSubtasks = $subtasks->filter(function ($st) use ($designer) {
                    if ($st->assignee_id) {
                        return (int) $st->assignee_id === (int) $designer->id;
                    }
                    if ($st->order) {
                        return (int) $st->order->designer_id === (int) $designer->id || $st->order->designers->contains('id', $designer->id);
                    }
                    return false;
                });
            @endphp
            <div class="bg-white border border-[#e9e9e7] rounded-xl p-4 space-y-3 shadow-2xs">
                
                <div class="flex items-center justify-between border-b border-[#e9e9e7] pb-2">
                    <div class="flex items-center gap-2.5">
                        <div class="w-6 h-6 rounded flex items-center justify-center font-bold text-xs shrink-0 border {{ $designer->badge_style }}">
                            {{ substr($designer->name, 0, 1) }}
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full {{ $designer->dot_color_class }}"></span>
                            <h3 class="font-semibold text-xs text-zinc-900">Diseñador/a: {{ $designer->name }}</h3>
                        </div>
                    </div>
                </div>

                <!-- 6 Columns Grid (Monday-Friday + Next Week) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
                    @foreach($days as $day)
                        @php
                            $isNextWeek = $day['is_next_week'] ?? false;
                            $isToday = !$isNextWeek && $day['date']->isToday();
                            
                            $dayOrders = $isNextWeek
                                ? $designer->orders->filter(fn($o) => $o->scheduled_date && $o->scheduled_date->gte(Carbon\Carbon::parse($day['date_string'])))
                                : $designer->orders->filter(fn($o) => $o->scheduled_date?->toDateString() === $day['date_string']);

                            $daySubtasks = $isNextWeek
                                ? $designerSubtasks->filter(fn($st) => $st->scheduled_date && $st->scheduled_date->gte(Carbon\Carbon::parse($day['date_string'])))
                                : $designerSubtasks->filter(fn($st) => $st->scheduled_date?->toDateString() === $day['date_string']);

                            $dayColorClass = match($day['day_name'] ?? '') {
                                'Lunes' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                                'Martes' => 'bg-sky-50 text-sky-700 border-sky-200',
                                'Miércoles' => 'bg-teal-50 text-teal-700 border-teal-200',
                                'Jueves' => 'bg-amber-50 text-amber-800 border-amber-200',
                                'Viernes' => 'bg-rose-50 text-rose-700 border-rose-200',
                                'Next Week' => 'bg-violet-50 text-violet-900 border-violet-300 border-dashed',
                                default => 'bg-stone-50 text-stone-700 border-stone-200',
                            };

                            if ($isToday) {
                                $dayColorClass = 'bg-amber-500 text-white font-black shadow-2xs ring-2 ring-amber-300';
                            }
                        @endphp
                        <div 
                            x-data="{ draggingOver: false }"
                            @dragover.prevent="draggingOver = true"
                            @dragenter.prevent="draggingOver = true"
                            @dragleave="if (!$el.contains($event.relatedTarget)) draggingOver = false"
                            @drop.prevent="
                                draggingOver = false;
                                let rawData = $event.dataTransfer.getData('text/plain');
                                if (rawData) {
                                    if (rawData.startsWith('subtask:')) {
                                        $wire.rescheduleSubtask(rawData.replace('subtask:', ''), '{{ $day['date_string'] }}');
                                    } else {
                                        let id = rawData.replace('order:', '');
                                        $wire.scheduleOrder(id, '{{ $day['date_string'] }}');
                                    }
                                }
                            "
                            :class="{ 
                                'border-indigo-500 bg-indigo-50/60 ring-2 ring-indigo-300': draggingOver, 
                                '{{ $isToday ? 'bg-amber-50/40 border-amber-300 ring-2 ring-amber-400/40 shadow-xs' : ($isNextWeek ? 'bg-[#f4f4f2] border-dashed border-stone-300' : 'bg-[#fbfbfa] border-[#e9e9e7]') }}': !draggingOver 
                            }"
                            class="border rounded-lg p-2 space-y-1.5 min-h-[120px] flex flex-col justify-between min-w-0 transition-all">
                            
                            <div class="min-w-0">
                                <!-- Day Header with HOY highlight -->
                                <div class="flex items-center justify-between border-b border-[#e9e9e7] pb-1 mb-1.5">
                                    <div class="flex items-center gap-1">
                                        <span class="px-1.5 py-0.2 rounded text-[9px] font-bold border uppercase tracking-wider {{ $dayColorClass }}">
                                            {{ $day['day_name'] }}
                                        </span>
                                        @if($isToday)
                                            <span class="px-1 py-0.2 rounded bg-amber-600 text-white text-[8px] font-bold animate-pulse">
                                                HOY
                                            </span>
                                        @endif
                                    </div>
                                    <span class="text-[9px] font-mono font-medium {{ $isToday ? 'text-amber-800 font-bold' : 'text-zinc-500' }}">
                                        {{ $isNextWeek ? $day['range_label'] : $day['date']->format('d M') }}
                                    </span>
                                </div>

                                @if($dayOrders->isEmpty() && $daySubtasks->isEmpty())
                                    <p class="text-[10px] text-zinc-400 text-center py-3 select-none">Sin trabajo agendado</p>
                                @else
                                    <div class="space-y-1.5">
                                        <!-- Main Order Cards Scheduled on this Day -->
                                        @foreach($dayOrders as $order)
                                            <div 
                                                draggable="true" 
                                                @dragstart="e => e.dataTransfer.setData('text/plain', 'order:{{ $order->id }}')"
                                                x-data="{ openSub: false, customTitle: '', targetDate: '{{ $day['date_string'] }}' }"
                                                class="rounded-md p-1.5 space-y-1 min-w-0 shadow-2xs cursor-grab active:cursor-grabbing hover:shadow-xs transition group relative {{ $order->isUrgente() ? ($order->done_today ? 'bg-[#fafaf9] border border-stone-200 opacity-75 ring-0' : 'bg-gradient-to-br from-rose-50/90 via-white to-red-50/70 border-2 border-red-500/90 shadow-md ring-2 ring-red-300/40') : ($order->isOverdue() && !$order->done_today ? 'bg-rose-50 border border-red-400' : ($order->isDueToday() && !$order->done_today ? 'bg-amber-50 border border-amber-300' : 'bg-white border border-[#e9e9e7] hover:border-stone-300')) }}"
                                                @if($order->isOverdue() && !$order->isUrgente() && !$order->done_today) style="border: 1px solid #ef4444 !important; background-color: #fef2f2 !important;" @elseif($order->isDueToday() && !$order->isUrgente() && !$order->done_today) style="border: 1px solid #f59e0b !important; background-color: #fffbeb !important;" @endif>
                                                
                                                <!-- Card Header: Company Name & Order Title -->
                                                <div class="flex items-start justify-between gap-1 min-w-0">
                                                    <div class="flex items-start gap-1.5 min-w-0 flex-1">
                                                        <button 
                                                            wire:click="toggleDoneToday({{ $order->id }})" 
                                                            type="button"
                                                            class="w-3.5 h-3.5 mt-0.5 rounded-full border transition flex items-center justify-center shrink-0 cursor-pointer {{ $order->done_today ? 'bg-emerald-500 border-emerald-500 text-white shadow-2xs' : 'border-stone-300 hover:border-emerald-500 bg-white text-transparent hover:text-emerald-500/40' }}"
                                                            title="{{ $order->done_today ? 'Completado (Clic para desmarcar)' : 'Marcar como completado' }}">
                                                            <x-lucide-check class="w-2.5 h-2.5 stroke-[3]" />
                                                        </button>
                                                        <div class="min-w-0 flex-1">
                                                            <h4 class="font-bold text-[11px] text-zinc-900 truncate leading-tight {{ $order->done_today ? 'line-through text-zinc-400' : '' }}" title="{{ $order->company_name }}">{{ $order->company_name }}</h4>
                                                            <p class="font-normal text-[10px] text-zinc-500 truncate leading-tight mt-0.2 {{ $order->done_today ? 'line-through text-zinc-400' : '' }}" title="{{ $order->task_name }}">{{ $order->task_name }}</p>
                                                        </div>
                                                    </div>
                                                    <div class="flex items-center gap-0.5 shrink-0">
                                                        @if($order->isOverdue())
                                                            <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse" title="SLA Vencido"></span>
                                                        @endif
                                                        <button wire:click="unscheduleOrder({{ $order->id }})" class="p-0.5 text-zinc-400 hover:text-red-600 transition" title="Desprogramar / Quitar de agenda">
                                                            <x-lucide-x-circle class="w-3 h-3" />
                                                        </button>
                                                        <button wire:click="$dispatch('open-order-detail', { orderId: {{ $order->id }} })" class="p-0.5 text-zinc-400 hover:text-zinc-700 transition" title="Ver detalle">
                                                            <x-lucide-panel-right class="w-3 h-3" />
                                                        </button>
                                                    </div>
                                                </div>

                                                <!-- Subtask Quick Add Launcher Button & Popover -->
                                                <div class="pt-0.5 flex items-center justify-between text-[9px]">
                                                    <button 
                                                        @click="openSub = !openSub"
                                                        type="button" 
                                                        class="text-violet-700 hover:text-violet-900 font-semibold flex items-center gap-0.5 bg-violet-50 hover:bg-violet-100 px-1 py-0.2 rounded border border-violet-200/80 transition">
                                                        <x-lucide-plus-circle class="w-2.5 h-2.5" />
                                                        <span>+ Subtarea</span>
                                                    </button>
                                                </div>

                                                <!-- Subtask Selection Popover Dropdown -->
                                                <div 
                                                    x-show="openSub" 
                                                    @click.outside="openSub = false"
                                                    x-transition:enter="transition ease-out duration-100"
                                                    x-transition:enter-start="opacity-0 scale-95"
                                                    x-transition:enter-end="opacity-100 scale-100"
                                                    class="absolute left-0 right-0 top-full mt-1 z-50 bg-white border border-[#e9e9e7] rounded-lg shadow-xl p-2 text-xs space-y-1.5">
                                                    
                                                    <div class="flex items-center justify-between min-w-0">
                                                        <span class="text-[10px] font-bold text-zinc-500 uppercase tracking-wider block truncate">Nueva subtarea:</span>
                                                    </div>

                                                    <!-- Select Target Date -->
                                                    <div>
                                                        <span class="text-[9px] text-zinc-400 block mb-0.5">Programar para día:</span>
                                                        <select x-model="targetDate" class="w-full bg-stone-50 border border-stone-200 rounded px-1 py-0.5 text-[10px] text-zinc-800 focus:outline-none">
                                                            @foreach($days as $d)
                                                                <option value="{{ $d['date_string'] }}">{{ $d['day_name'] }} ({{ ($d['is_next_week'] ?? false) ? $d['range_label'] : $d['date']->format('d M') }})</option>
                                                            @endforeach
                                                        </select>
                                                    </div>

                                                    <!-- Preset Subtask Chips -->
                                                    <div class="flex flex-wrap gap-1 pt-1 border-t border-stone-100">
                                                        @foreach($subtaskPresets as $preset)
                                                            @php
                                                                $safePlannerIcon = (preg_match('/^[a-z0-9\-]+$/i', $preset->emoji ?? '')) ? $preset->emoji : 'tag';
                                                            @endphp
                                                            <button 
                                                                type="button"
                                                                @click="$wire.scheduleSubtask({{ $order->id }}, '{{ addslashes($preset->title) }}', targetDate, {{ $designer->id }}); openSub = false"
                                                                class="px-1.5 py-0.5 rounded font-medium text-[10px] border transition shadow-2xs inline-flex items-center gap-1 {{ $preset->badgeStyle() }}">
                                                                <x-dynamic-component :component="'lucide-' . $safePlannerIcon" class="w-3 h-3" />
                                                                <span>{{ $preset->title }}</span>
                                                            </button>
                                                        @endforeach
                                                    </div>

                                                    <!-- Custom Subtask Input -->
                                                    <div class="pt-1 border-t border-stone-100">
                                                        <input 
                                                            type="text" 
                                                            x-model="customTitle"
                                                            @keyup.enter="if(customTitle.trim()) { $wire.scheduleSubtask({{ $order->id }}, customTitle, targetDate, {{ $designer->id }}); customTitle = ''; openSub = false; }"
                                                            placeholder="Escribe subtarea custom... (Enter)" 
                                                            class="w-full bg-stone-50 border border-stone-200 rounded px-2 py-1 text-[11px] text-zinc-800 focus:outline-none">
                                                    </div>
                                                </div>

                                            </div>
                                        @endforeach

                                        <!-- Independent Subtask Cards Scheduled on this Day -->
                                        @foreach($daySubtasks as $stask)
                                            <div 
                                                draggable="true" 
                                                @dragstart="e => e.dataTransfer.setData('text/plain', 'subtask:{{ $stask->id }}')"
                                                class="rounded-md p-1.5 space-y-1 min-w-0 shadow-2xs cursor-grab active:cursor-grabbing hover:shadow-xs transition group relative bg-violet-50/90 border border-violet-200/90 hover:border-violet-300">
                                                
                                                <div class="flex items-start justify-between gap-1 min-w-0">
                                                    <div class="flex items-start gap-1.5 min-w-0 flex-1">
                                                        <button 
                                                            wire:click="toggleSubtaskComplete({{ $stask->id }})" 
                                                            type="button"
                                                            class="w-3.5 h-3.5 mt-0.5 rounded-full border transition flex items-center justify-center shrink-0 cursor-pointer {{ $stask->isDone() ? 'bg-emerald-500 border-emerald-500 text-white shadow-2xs' : 'border-violet-300 hover:border-emerald-500 bg-white text-transparent hover:text-emerald-500/40' }}"
                                                            title="{{ $stask->isDone() ? 'Subtarea completada (Clic para desmarcar)' : 'Marcar subtarea como completada' }}">
                                                            <x-lucide-check class="w-2.5 h-2.5 stroke-[3]" />
                                                        </button>
                                                        <div class="min-w-0 flex-1">
                                                            <div class="flex items-center gap-1 mb-0.5">
                                                                <span class="px-1 py-0.2 rounded bg-violet-200/70 text-violet-800 text-[8px] font-bold uppercase tracking-wider flex items-center gap-0.5 shrink-0">
                                                                    <x-lucide-check-square class="w-2.5 h-2.5 text-violet-600" />
                                                                    Subtarea
                                                                </span>
                                                            </div>
                                                            <h5 class="font-bold text-[11px] text-zinc-900 truncate leading-tight {{ $stask->isDone() ? 'line-through text-zinc-400' : '' }}" title="{{ $stask->title }}">
                                                                {{ $stask->title }}
                                                            </h5>
                                                        </div>
                                                    </div>

                                                    <div class="flex items-center gap-0.5 shrink-0">
                                                        <button wire:click="deleteSubtask({{ $stask->id }})" class="p-0.5 text-zinc-400 hover:text-red-600 transition" title="Eliminar subtarea">
                                                            <x-lucide-x-circle class="w-3 h-3" />
                                                        </button>
                                                    </div>
                                                </div>

                                                <!-- Link to Original Parent Order Card -->
                                                @if($stask->order)
                                                    <div class="pt-1 border-t border-violet-100/80 flex items-start justify-between gap-1 text-[10px]">
                                                        <button 
                                                            wire:click="$dispatch('open-order-detail', { orderId: {{ $stask->order->id }} })" 
                                                            type="button"
                                                            class="text-left min-w-0 flex-1 hover:underline group/link" 
                                                            title="Ver orden original: {{ $stask->order->company_name }} - {{ $stask->order->task_name }}">
                                                            <div class="flex items-center gap-1 min-w-0">
                                                                <x-lucide-link class="w-2.5 h-2.5 text-indigo-500 shrink-0 group-hover/link:text-indigo-600" />
                                                                <span class="font-bold text-[10px] text-zinc-800 truncate leading-tight">{{ $stask->order->company_name }}</span>
                                                            </div>
                                                            @if($stask->order->task_name)
                                                                <p class="text-[9.5px] text-zinc-500 truncate leading-tight pl-3.5 mt-0.5">{{ $stask->order->task_name }}</p>
                                                            @endif
                                                        </button>
                                                        <button 
                                                            wire:click="$dispatch('open-order-detail', { orderId: {{ $stask->order->id }} })" 
                                                            class="p-0.5 text-zinc-400 hover:text-zinc-700 shrink-0 self-start mt-0.5" 
                                                            title="Abrir orden">
                                                            <x-lucide-panel-right class="w-2.5 h-2.5" />
                                                        </button>
                                                    </div>
                                                @endif

                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <span class="text-[9px] text-zinc-400 font-mono text-right block pt-1 border-t border-[#e9e9e7] shrink-0 select-none">
                                {{ $dayOrders->count() }} ord · {{ $daySubtasks->count() }} sub
                            </span>

                        </div>
                    @endforeach
                </div>

            </div>
        @endforeach
    </div>


</div>

