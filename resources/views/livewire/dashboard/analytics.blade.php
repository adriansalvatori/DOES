<div class="h-full flex flex-col space-y-5 min-h-0 overflow-y-auto custom-vertical-scrollbar pr-1 pb-6">
    
    <!-- Header & Action Bar -->
    <div id="tour-analytics-header" class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-4 sm:p-5 rounded-2xl border border-[#e9e9e7] shadow-2xs shrink-0">
        <div class="min-w-0">
            <h1 class="text-2xl sm:text-3xl font-extrabold text-zinc-900 tracking-tight">{{ __('Analytics Dashboard') }}</h1>
        </div>

        <div class="flex items-center gap-2 text-xs">
            <span class="px-3 py-1.5 rounded-xl bg-stone-100 border border-stone-200 text-zinc-700 font-mono text-[11px] font-medium flex items-center gap-1.5">
                <x-lucide-clock class="w-3.5 h-3.5 text-zinc-400" />
                <span>{{ __('Actualizado:') }} {{ now()->format('d M, Y - H:i') }}</span>
            </span>
            <a href="/" class="px-3 py-1.5 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white font-medium transition flex items-center gap-1.5 shadow-2xs">
                <x-lucide-layout-dashboard class="w-3.5 h-3.5" />
                <span>{{ __('Centro de Control') }}</span>
            </a>
        </div>
    </div>

    <!-- 1. Distribución por Estado Principal (On Top) -->
    <div class="bg-white border border-[#e9e9e7] rounded-2xl p-5 space-y-4 shadow-2xs">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-[#e9e9e7] pb-3">
            <div class="space-y-0.5">
                <h3 class="font-bold text-xs text-zinc-900 uppercase tracking-wider flex items-center gap-2">
                    <x-lucide-kanban class="w-4 h-4 text-zinc-600" />
                    <span>{{ __('Mapa de Órdenes en Curso') }}</span>
                </h3>
                <p class="text-[11px] text-zinc-500">
                    {{ __('Flujo y concentración de órdenes a lo largo de las etapas operativas activas.') }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-[11px] font-medium text-zinc-500 font-mono">
                    {{ $totalOrders }} {{ __('tarjetas activas') }}
                </span>
            </div>
        </div>

        <!-- Multi-Color Distribution Bar -->
        <div class="w-full h-3 rounded-full overflow-hidden bg-stone-100 border border-stone-200/80 flex shadow-inner">
            @if($totalOrders > 0)
                @foreach($coreStatusCounts as $item)
                    @if($item['count'] > 0)
                        <div 
                            class="h-full transition-all duration-300 first:rounded-l-full last:rounded-r-full" 
                            style="width: {{ max($item['percentage'], 1.5) }}%; background-color: {{ $item['status']->hexColor() }};"
                            title="{{ $item['label'] }}: {{ $item['count'] }} órdenes ({{ $item['percentage'] }}%)">
                        </div>
                    @endif
                @endforeach
            @else
                <div class="w-full h-full bg-stone-200"></div>
            @endif
        </div>

        <!-- Core Status Cards (Responsive Grid) -->
        <div class="grid grid-cols-2 sm:grid-cols-4 xl:grid-cols-8 gap-2.5 pt-1">
            @foreach($coreStatusCounts as $item)
                <div class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-xl p-2.5 space-y-1.5 text-xs hover:border-stone-300 transition shadow-2xs flex flex-col justify-between">
                    <div class="flex items-center gap-1.5 min-w-0">
                        <span class="w-2 h-2 rounded-full {{ $item['status']->dotClass() }} shrink-0"></span>
                        <span class="text-[11px] font-bold text-zinc-700 truncate" title="{{ $item['label'] }}">
                            {{ $item['label'] }}
                        </span>
                    </div>
                    <div class="flex items-baseline justify-between pt-1 border-t border-stone-200/50">
                        <span class="text-base sm:text-lg font-bold font-mono text-zinc-900 leading-none">
                            {{ $item['count'] }}
                        </span>
                        <span class="text-[10px] font-mono text-zinc-400 font-medium">
                            {{ $item['percentage'] }}%
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- 2. Abajo de eso: Carga de Trabajo por Diseñador (Izquierda) & Conteo / Cumplimiento SLA (Derecha) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-stretch">
        
        <!-- A la izquierda: Carga de trabajo por diseñador (existente) -->
        <div class="lg:col-span-7 bg-white border border-[#e9e9e7] rounded-2xl p-5 space-y-4 shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between border-b border-[#e9e9e7] pb-3">
                <div class="space-y-0.5">
                    <h3 class="font-bold text-xs text-zinc-900 uppercase tracking-wider flex items-center gap-2">
                        <x-lucide-pie-chart class="w-4 h-4 text-zinc-600" />
                        <span>{{ __('Carga de Trabajo por Diseñador') }}</span>
                    </h3>
                    <p class="text-[11px] text-zinc-500">
                        {{ __('Distribución porcentual de órdenes asignadas activas por integrante.') }}
                    </p>
                </div>
            </div>

            @php
                $accumulatedPct = 0;
                $gradientStops = [];
                $designerColors = [
                    'Euralíz' => '#F3A8FF',
                    'Adrián' => '#5FE9B5',
                    'César' => '#52EAFD',
                    'Diseñador Externo' => '#eab308',
                    'Sin Asignar' => '#a1a1aa',
                ];

                foreach($designerStats as $st) {
                    $color = $st['designer']->hex_color ?: ($designerColors[$st['designer']->name] ?? '#3b82f6');
                    $start = $accumulatedPct;
                    $end = $accumulatedPct + $st['workload_pct'];
                    $accumulatedPct = $end;
                    if ($st['count'] > 0) {
                        $gradientStops[] = "{$color} {$start}% {$end}%";
                    }
                }

                if ($unassignedCount > 0 && $totalOrders > 0) {
                    $unassignedPct = round(($unassignedCount / $totalOrders) * 100, 1);
                    $start = $accumulatedPct;
                    $end = min(100, $accumulatedPct + $unassignedPct);
                    $gradientStops[] = "#a1a1aa {$start}% {$end}%";
                }

                $conicGradient = !empty($gradientStops) ? implode(', ', $gradientStops) : '#e5e7eb 0% 100%';
            @endphp

            <div class="flex flex-col sm:flex-row items-center gap-6 py-2">
                <!-- Donut Pie Chart Canvas -->
                <div class="relative w-44 h-44 shrink-0 flex items-center justify-center rounded-full shadow-xs border border-stone-200"
                     style="background: conic-gradient({{ $conicGradient }});">
                    <!-- Hollow Center Circle -->
                    <div class="w-28 h-28 bg-white rounded-full flex flex-col items-center justify-center shadow-xs border border-stone-100 text-center">
                        <span class="text-2xl sm:text-3xl font-bold font-mono text-zinc-900 leading-none">{{ $totalOrders }}</span>
                        <span class="text-[10px] text-zinc-500 font-semibold uppercase tracking-wider mt-1">{{ __('Órdenes') }}</span>
                        <span class="text-[9px] text-emerald-700 font-bold bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200 mt-0.5">{{ __('Activas') }}</span>
                    </div>
                </div>

                <!-- Interactive Designer Legend List -->
                <div class="space-y-2.5 flex-1 w-full">
                    @foreach($designerStats as $st)
                        @php
                            $hexColor = $st['designer']->hex_color ?: ($designerColors[$st['designer']->name] ?? '#3b82f6');
                        @endphp
                        <div class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-xl p-2.5 flex items-center justify-between text-xs transition hover:bg-stone-50">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <span class="w-3.5 h-3.5 rounded-full shrink-0 ring-2 ring-stone-200" style="background-color: {{ $hexColor }}"></span>
                                <span class="font-bold text-zinc-900 text-xs truncate">{{ $st['designer']->name }}</span>
                                @if($st['overdue'] > 0)
                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-rose-50 text-rose-700 border border-rose-200" title="{{ __('Órdenes atrasadas') }}">
                                        {{ $st['overdue'] }} {{ __('atraso') }}
                                    </span>
                                @endif
                            </div>
                            <div class="flex items-center gap-3 shrink-0 font-mono">
                                <span class="px-2 py-0.5 rounded text-[10px] font-semibold border {{ $st['designer']->badge_style }}" style="{{ $st['designer']->badge_inline_style }}">
                                    {{ $st['count'] }} {{ __('órdenes') }}
                                </span>
                                <span class="font-bold text-zinc-800 w-12 text-right">{{ $st['workload_pct'] }}%</span>
                            </div>
                        </div>
                    @endforeach

                    @if($unassignedCount > 0)
                        @php
                            $unassignedPct = $totalOrders > 0 ? round(($unassignedCount / $totalOrders) * 100, 1) : 0;
                        @endphp
                        <div class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-xl p-2.5 flex items-center justify-between text-xs">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <span class="w-3.5 h-3.5 rounded-full shrink-0 ring-2 ring-stone-200 bg-stone-400"></span>
                                <span class="font-bold text-zinc-700 text-xs truncate">{{ __('Sin Asignar') }}</span>
                            </div>
                            <div class="flex items-center gap-3 shrink-0 font-mono">
                                <span class="px-2 py-0.5 rounded text-[10px] font-semibold border bg-stone-100 text-stone-700 border-stone-300">
                                    {{ $unassignedCount }} {{ __('órdenes') }}
                                </span>
                                <span class="font-bold text-zinc-800 w-12 text-right">{{ $unassignedPct }}%</span>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- A la derecha: Conteo órdenes activas + Porcentaje de cumplimiento del SLA -->
        <div class="lg:col-span-5 flex flex-col gap-5 justify-between">
            
            <!-- Conteo órdenes en diseño activas -->
            <div class="bg-white border border-[#e9e9e7] rounded-2xl p-5 shadow-2xs space-y-3.5 flex flex-col justify-between flex-1">
                <div class="flex items-center justify-between">
                    <div class="space-y-0.5">
                        <span class="text-[11px] font-bold text-zinc-500 uppercase tracking-wider block">
                            {{ __('Órdenes en Diseño Activas') }}
                        </span>
                        <p class="text-[11px] text-zinc-400 font-normal">
                            {{ __('Volumen actual en el tablero operativo') }}
                        </p>
                    </div>
                    <div class="w-9 h-9 rounded-xl bg-blue-50 border border-blue-200/80 text-blue-700 flex items-center justify-center shrink-0">
                        <x-lucide-layers class="w-4.5 h-4.5 text-blue-600" />
                    </div>
                </div>

                <div class="flex items-baseline gap-2.5 my-1">
                    <span class="text-3xl sm:text-4xl font-bold font-mono tracking-tight text-zinc-900 leading-none">
                        {{ $totalOrders }}
                    </span>
                    <span class="text-xs font-semibold text-zinc-500 uppercase tracking-wider">
                        {{ __('órdenes activas') }}
                    </span>
                </div>

                <div class="pt-3 border-t border-[#e9e9e7] flex flex-wrap items-center justify-between gap-2 text-[11px] text-zinc-600">
                    <span class="text-zinc-400 font-medium">{{ __('Excluye Backlog y En Producción') }}</span>
                    <span class="px-2 py-0.5 rounded-lg bg-emerald-50 text-emerald-700 font-bold border border-emerald-200 text-[10px]">
                        {{ $inProductionCount }} {{ __('completadas en taller') }}
                    </span>
                </div>
            </div>

            <!-- Porcentaje de cumplimiento del SLA -->
            <div class="bg-white border border-[#e9e9e7] rounded-2xl p-5 shadow-2xs space-y-3.5 flex flex-col justify-between flex-1">
                <div class="flex items-center justify-between">
                    <div class="space-y-0.5">
                        <span class="text-[11px] font-bold text-zinc-500 uppercase tracking-wider block">
                            {{ __('Cumplimiento del SLA') }}
                        </span>
                        <p class="text-[11px] text-zinc-400 font-normal">
                            {{ __('Puntualidad en los tiempos pactados de entrega') }}
                        </p>
                    </div>
                    <div class="w-9 h-9 rounded-xl bg-emerald-50 border border-emerald-200/80 text-emerald-700 flex items-center justify-center shrink-0">
                        <x-lucide-shield-check class="w-4.5 h-4.5 text-emerald-600" />
                    </div>
                </div>

                <div class="space-y-2">
                    <div class="flex items-baseline justify-between">
                        <div class="flex items-baseline gap-2">
                            <span class="text-3xl sm:text-4xl font-bold font-mono tracking-tight text-emerald-800 leading-none">
                                {{ $slaComplianceRate }}%
                            </span>
                            <span class="text-xs font-semibold text-emerald-700">
                                {{ __('a tiempo') }}
                            </span>
                        </div>
                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold {{ $overdueCount > 0 ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200' }}">
                            {{ $overdueCount > 0 ? $overdueCount . ' ' . __('atrasadas') : __('100% puntual') }}
                        </span>
                    </div>

                    <!-- SLA Progress Bar -->
                    <div class="w-full bg-stone-100 rounded-full h-2 overflow-hidden border border-stone-200/80">
                        <div class="bg-emerald-500 h-full rounded-full transition-all duration-300" style="width: {{ $slaComplianceRate }}%"></div>
                    </div>
                </div>

                <div class="pt-3 border-t border-[#e9e9e7] flex items-center justify-between text-[11px] text-zinc-600 font-medium">
                    <span class="flex items-center gap-1.5 text-emerald-700">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        <span>{{ max(0, $totalOrders - $overdueCount) }} {{ __('a tiempo') }}</span>
                    </span>
                    @if($overdueCount > 0)
                        <span class="flex items-center gap-1.5 text-rose-600 font-semibold">
                            <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                            <span>{{ $overdueCount }} {{ __('en atraso') }} ({{ $overdueRate }}%)</span>
                        </span>
                    @else
                        <span class="text-zinc-400 text-[10px]">
                            {{ __('Sin entregas vencidas') }}
                        </span>
                    @endif
                </div>
            </div>

        </div>

    </div>

</div>
