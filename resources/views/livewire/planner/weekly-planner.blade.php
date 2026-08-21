<div class="space-y-5">
    
    <!-- Top Notion Header -->
    <div class="bg-white border border-[#e9e9e7] rounded-xl p-4 flex flex-col md:flex-row items-center justify-between gap-4 shadow-2xs">
        <div class="flex items-center gap-3 min-w-0">
            <x-lucide-calendar-days class="w-5 h-5 text-zinc-700 shrink-0" />
            <div class="min-w-0">
                <h2 class="text-sm font-semibold text-zinc-900 tracking-tight truncate">Planificador Semanal de Diseño</h2>
                <p class="text-xs text-zinc-500 truncate">Asignación diaria de órdenes para Euralíz, Adrián y César (Lunes a Viernes).</p>
            </div>
        </div>

        <div class="flex items-center gap-2.5 shrink-0">
            <span class="text-xs text-zinc-600 font-medium whitespace-nowrap">Semana Del:</span>
            <input type="date" wire:model.live="selectedWeekStart" class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-md px-2.5 py-1 text-xs text-zinc-800 focus:outline-none font-mono">
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
        <div class="bg-white border border-[#e9e9e7] rounded-xl p-4 space-y-3 shadow-2xs">
            <div class="flex items-center justify-between border-b border-[#e9e9e7] pb-2 min-w-0">
                <h3 class="font-semibold text-xs text-zinc-700 uppercase tracking-wider flex items-center gap-2 truncate">
                    <x-lucide-clock class="w-4 h-4 text-zinc-500 shrink-0" /> Órdenes Pendientes por Programar en Agenda ({{ $unscheduledOrders->count() }})
                </h3>
                <span class="text-[11px] text-zinc-400 shrink-0 whitespace-nowrap">Selecciona el día para agendar</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5">
                @foreach($unscheduledOrders as $unOrder)
                    <div class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-lg p-2.5 space-y-2 min-w-0">
                        <div class="min-w-0">
                            <span class="text-[10px] text-zinc-500 font-medium uppercase truncate block">{{ $unOrder->designer?->name }}</span>
                            <h4 class="font-medium text-xs text-zinc-900 truncate" title="{{ $unOrder->company_name }}">{{ $unOrder->company_name }}</h4>
                        </div>

                        <div class="flex items-center justify-between text-[10px] text-zinc-500">
                            <span>Vence: <strong class="font-mono text-zinc-800">{{ $unOrder->current_due_date ? $unOrder->current_due_date->format('d M') : 'N/A' }}</strong></span>
                        </div>

                        <!-- Schedule Day Dropdown -->
                        <select wire:change="scheduleOrder({{ $unOrder->id }}, $event.target.value)" class="bg-white border border-[#e9e9e7] rounded px-2 py-1 text-[10px] text-zinc-700 focus:outline-none w-full truncate">
                            <option value="">Programar para día...</option>
                            @foreach($days as $day)
                                <option value="{{ $day['date_string'] }}">{{ $day['day_name'] }} ({{ $day['date']->format('d M') }})</option>
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

                <!-- 5 Days Columns Grid -->
                <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                    @foreach($days as $day)
                        @php
                            $dayOrders = $designer->orders->filter(fn($o) => $o->scheduled_date?->toDateString() === $day['date_string']);
                        @endphp
                        <div class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-lg p-2.5 space-y-2 min-h-[140px] flex flex-col justify-between min-w-0">
                            
                            <div class="min-w-0">
                                <div class="flex items-center justify-between border-b border-[#e9e9e7] pb-1.5 mb-2">
                                    <span class="font-semibold text-xs text-zinc-700 uppercase tracking-wider">{{ $day['day_name'] }}</span>
                                    <span class="text-[10px] font-mono text-zinc-400">{{ $day['date']->format('d M') }}</span>
                                </div>

                                @if($dayOrders->isEmpty())
                                    <p class="text-[11px] text-zinc-400 text-center py-4">Sin trabajo agendado</p>
                                @else
                                    <div class="space-y-1.5">
                                        @foreach($dayOrders as $order)
                                            <div class="bg-white border border-[#e9e9e7] rounded p-2 space-y-1 min-w-0 shadow-2xs">
                                                <div class="flex items-center justify-between gap-1 min-w-0">
                                                    <span class="font-medium text-xs text-zinc-900 truncate" title="{{ $order->company_name }}">{{ $order->company_name }}</span>
                                                    @if($order->isOverdue())
                                                        <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse shrink-0" title="SLA Vencido"></span>
                                                    @endif
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

                            <span class="text-[10px] text-zinc-400 font-mono text-right block pt-1.5 border-t border-[#e9e9e7] shrink-0">
                                {{ $dayOrders->count() }} órdenes
                            </span>

                        </div>
                    @endforeach
                </div>

            </div>
        @endforeach
    </div>

</div>
