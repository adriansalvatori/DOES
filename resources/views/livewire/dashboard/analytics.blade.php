<div class="h-full flex flex-col space-y-4 min-h-0 overflow-y-auto custom-vertical-scrollbar pr-1">
    
    <!-- Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-4 sm:p-5 rounded-2xl border border-[#e9e9e7] shadow-2xs shrink-0">
        <div class="space-y-1">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-xl bg-stone-100 border border-stone-200 text-zinc-700 flex items-center justify-center font-bold">
                    <x-lucide-bar-chart-3 class="w-4 h-4 text-stone-700" />
                </div>
                <h2 class="text-lg font-bold text-zinc-900 tracking-tight">{{ __('Analytics Dashboard') }}</h2>
            </div>
            <p class="text-xs text-zinc-500 font-normal">
                {{ __('Métricas operativas en tiempo real, análisis de equipo y rendimiento de entregas.') }}
            </p>
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

    <!-- Top 4 Executive KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        
        <!-- KPI 1: Carga Operativa Activa (Active Design Workload) -->
        <div class="bg-white border border-[#e9e9e7] rounded-2xl p-4 space-y-3 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-zinc-500 uppercase tracking-wider">{{ __('Carga Operativa Activa') }}</span>
                <div class="w-7 h-7 rounded-lg bg-blue-50 border border-blue-200 text-blue-700 flex items-center justify-center">
                    <x-lucide-layers class="w-3.5 h-3.5" />
                </div>
            </div>
            <div>
                <span class="text-2xl sm:text-3xl font-bold font-mono text-zinc-900">{{ $totalOrders }}</span>
                <span class="text-xs text-zinc-500 font-normal ml-1">{{ __('órdenes en diseño') }}</span>
            </div>
            <div class="pt-2 border-t border-[#e9e9e7] flex items-center justify-between text-[11px] text-zinc-600 font-medium">
                <span class="text-zinc-400">{{ __('Excluye Backlog y En Producción') }}</span>
                <span class="px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 font-bold border border-emerald-200 text-[10px]">{{ $inProductionCount }} {{ __('completadas') }}</span>
            </div>
        </div>

        <!-- KPI 2: Cumplimiento de SLA -->
        <div class="bg-white border border-[#e9e9e7] rounded-2xl p-4 space-y-3 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-zinc-500 uppercase tracking-wider">{{ __('Cumplimiento SLA') }}</span>
                <div class="w-7 h-7 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 flex items-center justify-center">
                    <x-lucide-shield-check class="w-3.5 h-3.5" />
                </div>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl sm:text-3xl font-bold font-mono text-emerald-900">{{ $slaComplianceRate }}%</span>
                <span class="text-xs text-emerald-700 font-semibold">{{ __('entregas a tiempo') }}</span>
            </div>
            <!-- Progress Bar -->
            <div class="w-full bg-stone-100 rounded-full h-2 overflow-hidden border border-stone-200">
                <div class="bg-emerald-500 h-full rounded-full transition-all duration-300" style="width: {{ $slaComplianceRate }}%"></div>
            </div>
        </div>

        <!-- KPI 3: Índice de Revisiones -->
        <div class="bg-white border border-[#e9e9e7] rounded-2xl p-4 space-y-3 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-zinc-500 uppercase tracking-wider">{{ __('Índice de Revisiones') }}</span>
                <div class="w-7 h-7 rounded-lg bg-sky-50 border border-sky-200 text-sky-700 flex items-center justify-center">
                    <x-lucide-history class="w-3.5 h-3.5" />
                </div>
            </div>
            <div>
                <span class="text-2xl sm:text-3xl font-bold font-mono text-zinc-900">{{ $totalClientRevisions }}</span>
                <span class="text-xs text-zinc-500 font-normal ml-1">{{ __('revisiones cliente') }}</span>
            </div>
            <div class="pt-2 border-t border-[#e9e9e7] flex items-center justify-between text-[11px] text-zinc-600 font-medium">
                <span>{{ __('Promedio/orden:') }} <strong>{{ $avgClientRevisions }}</strong></span>
                <span>{{ __('Internas:') }} <strong>{{ $totalInternalRevisions }}</strong></span>
            </div>
        </div>

        <!-- KPI 4: Atrasos & Riesgo Operativo -->
        <div class="bg-white border border-[#e9e9e7] rounded-2xl p-4 space-y-3 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-zinc-500 uppercase tracking-wider">{{ __('Órdenes en Atraso') }}</span>
                <div class="w-7 h-7 rounded-lg bg-rose-50 border border-rose-200 text-rose-700 flex items-center justify-center">
                    <x-lucide-alert-octagon class="w-3.5 h-3.5" />
                </div>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl sm:text-3xl font-bold font-mono {{ $overdueCount > 0 ? 'text-rose-600' : 'text-zinc-900' }}">{{ $overdueCount }}</span>
                <span class="text-xs font-semibold {{ $overdueCount > 0 ? 'text-rose-600' : 'text-zinc-400' }}">({{ $overdueRate }}% {{ __('del total') }})</span>
            </div>
            <div class="pt-2 border-t border-[#e9e9e7] flex items-center justify-between text-[11px] text-zinc-600 font-medium">
                <span>{{ __('Completadas Hoy:') }} <strong>{{ $doneTodayCount }}</strong></span>
                <span>{{ __('Aprobadas:') }} <strong>{{ $approvedCount }}</strong></span>
            </div>
        </div>

    </div>

    <!-- Related Tasks Summary Banner (Distinct from Orders) -->
    <div class="bg-stone-50 border border-stone-200 rounded-2xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs shadow-2xs">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-xl bg-white border border-stone-200 text-zinc-700 flex items-center justify-center font-bold shadow-2xs">
                <x-lucide-check-square class="w-4 h-4 text-zinc-600" />
            </div>
            <div>
                <h4 class="font-bold text-zinc-900 text-xs">{{ __('Tareas Vinculadas (Sub-tareas Operativas)') }}</h4>
                <p class="text-[11px] text-zinc-500">{{ __('Acciones de seguimiento y resolver asociadas. No forman parte del conteo de órdenes principales.') }}</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 shrink-0 text-[11px] font-medium">
            <span class="px-3 py-1 rounded-lg bg-white border border-stone-200 text-zinc-800 font-mono">
                {{ __('Total Sub-tareas:') }} <strong>{{ $totalRelatedTasks }}</strong>
            </span>
            <span class="px-3 py-1 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 font-mono">
                {{ __('Pendientes:') }} <strong>{{ $pendingRelatedTasks }}</strong>
            </span>
            <span class="px-3 py-1 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 font-mono">
                {{ __('Completadas:') }} <strong>{{ $completedRelatedTasks }}</strong>
            </span>
        </div>
    </div>

    <!-- Section 1: Core Status Distribution Chart & Grid -->
    <div class="bg-white border border-[#e9e9e7] rounded-2xl p-5 space-y-4 shadow-2xs">
        <div class="flex items-center justify-between border-b border-[#e9e9e7] pb-3">
            <div class="space-y-0.5">
                <h3 class="font-bold text-xs text-zinc-900 uppercase tracking-wider flex items-center gap-2">
                    <x-lucide-kanban class="w-4 h-4 text-zinc-600" />
                    Distribución por Estado Principal (Core Status)
                </h3>
                <p class="text-[11px] text-zinc-500">Flujo de tarjetas a lo largo de las etapas operativas del tablero.</p>
            </div>
        </div>

        <!-- Multi-Color Distribution Bar -->
        <div class="w-full h-3.5 rounded-full overflow-hidden bg-stone-100 border border-stone-200/80 flex shadow-inner">
            @foreach($coreStatusCounts as $item)
                @if($item['count'] > 0)
                    <div 
                        class="h-full transition-all duration-300 first:rounded-l-full last:rounded-r-full" 
                        style="width: {{ max($item['percentage'], 1.5) }}%; background-color: {{ $item['status']->hexColor() }};"
                        title="{{ $item['label'] }}: {{ $item['count'] }} tarjetas ({{ $item['percentage'] }}%)">
                    </div>
                @endif
            @endforeach
        </div>

        <!-- Core Status Cards (Single Responsive Horizontal Row) -->
        <div class="flex items-center gap-2.5 overflow-x-auto pb-1 pt-1 min-w-0 w-full">
            @foreach($coreStatusCounts as $item)
                <div class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-xl p-2.5 space-y-1 text-xs flex-1 min-w-[110px] shrink-0 xl:shrink hover:border-stone-300 transition shadow-2xs">
                    <div class="flex items-center gap-1.5 min-w-0">
                        <span class="w-2 h-2 rounded-full {{ $item['status']->dotClass() }} shrink-0"></span>
                        <span class="text-[10px] font-bold text-zinc-700 truncate" title="{{ $item['label'] }}">{{ $item['label'] }}</span>
                    </div>
                    <div class="flex items-baseline justify-between pt-0.5 min-w-0">
                        <span class="text-base font-bold font-mono text-zinc-900 leading-none">{{ $item['count'] }}</span>
                        <span class="text-[10px] font-mono text-zinc-400 shrink-0">{{ $item['percentage'] }}%</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Section 2 & 3: Designer Workload & Substatus Frequency -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        
        <!-- Designer Workload Breakdown Card (Donut Pie Chart View) -->
        <div class="bg-white border border-[#e9e9e7] rounded-2xl p-5 space-y-4 shadow-2xs">
            <div class="flex items-center justify-between border-b border-[#e9e9e7] pb-3">
                <div class="space-y-0.5">
                    <h3 class="font-bold text-xs text-zinc-900 uppercase tracking-wider flex items-center gap-2">
                        <x-lucide-pie-chart class="w-4 h-4 text-zinc-600" />
                        Carga de Trabajo por Diseñador
                    </h3>
                    <p class="text-[11px] text-zinc-500">Distribución porcentual de órdenes asignadas activas por integrante.</p>
                </div>
            </div>

            @php
                $accumulatedPct = 0;
                $gradientStops = [];
                $designerColors = [
                    'Euralíz' => '#d946ef',
                    'Adrián' => '#10b981',
                    'César' => '#06b6d4',
                    'Diseñador Externo' => '#eab308',
                    'Sin Asignar' => '#a1a1aa',
                ];

                foreach($designerStats as $st) {
                    $color = $designerColors[$st['designer']->name] ?? '#3b82f6';
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

            <div class="flex flex-col sm:flex-row items-center gap-6 pt-1">
                <!-- Donut Pie Chart Canvas -->
                <div class="relative w-44 h-44 shrink-0 flex items-center justify-center rounded-full shadow-sm border border-stone-200"
                     style="background: conic-gradient({{ $conicGradient }});">
                    <!-- Hollow Center Circle -->
                    <div class="w-28 h-28 bg-white rounded-full flex flex-col items-center justify-center shadow-xs border border-stone-100 text-center">
                        <span class="text-2xl font-bold font-mono text-zinc-900 leading-none">{{ $totalOrders }}</span>
                        <span class="text-[10px] text-zinc-500 font-semibold uppercase tracking-wider mt-1">Órdenes</span>
                        <span class="text-[9px] text-emerald-700 font-bold bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200 mt-0.5">Activas</span>
                    </div>
                </div>

                <!-- Interactive Designer Legend List -->
                <div class="space-y-2.5 flex-1 w-full">
                    @foreach($designerStats as $st)
                        @php
                            $hexColor = $designerColors[$st['designer']->name] ?? '#3b82f6';
                        @endphp
                        <div class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-xl p-2.5 flex items-center justify-between text-xs transition hover:bg-stone-50">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <span class="w-3.5 h-3.5 rounded-full shrink-0 ring-2 ring-stone-200" style="background-color: {{ $hexColor }}"></span>
                                <span class="font-bold text-zinc-900 text-xs truncate">{{ $st['designer']->name }}</span>
                            </div>
                            <div class="flex items-center gap-3 shrink-0 font-mono">
                                <span class="px-2 py-0.5 rounded text-[10px] font-semibold border {{ $st['designer']->badge_style }}">
                                    {{ $st['count'] }} órdenes
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
                                <span class="font-bold text-zinc-700 text-xs truncate">Sin Asignar</span>
                            </div>
                            <div class="flex items-center gap-3 shrink-0 font-mono">
                                <span class="px-2 py-0.5 rounded text-[10px] font-semibold border bg-stone-100 text-stone-700 border-stone-300">
                                    {{ $unassignedCount }} órdenes
                                </span>
                                <span class="font-bold text-zinc-800 w-12 text-right">{{ $unassignedPct }}%</span>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Substatus Frequency Breakdown Card -->
        <div class="bg-white border border-[#e9e9e7] rounded-2xl p-5 space-y-4 shadow-2xs">
            <div class="flex items-center justify-between border-b border-[#e9e9e7] pb-3">
                <div class="space-y-0.5">
                    <h3 class="font-bold text-xs text-zinc-900 uppercase tracking-wider flex items-center gap-2">
                        <x-lucide-tags class="w-4 h-4 text-zinc-600" />
                        Frecuencia de Subestatus Operativos
                    </h3>
                    <p class="text-[11px] text-zinc-500">Métricas de etiquetas activas (Para Hoy, Atrasadas, Resolver, etc.).</p>
                </div>
            </div>

            <div class="space-y-2.5 max-h-[320px] overflow-y-auto pr-1 scrollbar-thin">
                @foreach($substatusCounts as $subItem)
                    <div class="p-3 bg-[#fbfbfa] border border-[#e9e9e7] rounded-xl flex items-center justify-between gap-3 text-xs">
                        <div class="min-w-0 flex-1 space-y-1">
                            <div class="flex items-center justify-between">
                                <span class="px-2.5 py-0.5 rounded text-[11px] font-semibold border" style="{{ $subItem['style'] }}">
                                    {{ $subItem['name'] }}
                                </span>
                                <span class="font-mono text-xs font-bold text-zinc-800">{{ $subItem['count'] }} tarjetas</span>
                            </div>

                            <!-- Mini progress bar -->
                            <div class="w-full bg-stone-100 rounded-full h-1.5 overflow-hidden border border-stone-200">
                                <div class="bg-stone-700 h-full rounded-full" style="width: {{ max($subItem['percentage'], 2) }}%"></div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

    </div>

    <!-- Section 4: Top Accounts by Order Volume Table -->
    <div class="bg-white border border-[#e9e9e7] rounded-2xl p-5 space-y-4 shadow-2xs">
        <div class="flex items-center justify-between border-b border-[#e9e9e7] pb-3">
            <div class="space-y-0.5">
                <h3 class="font-bold text-xs text-zinc-900 uppercase tracking-wider flex items-center gap-2">
                    <x-lucide-building-2 class="w-4 h-4 text-zinc-600" />
                    Top Cuentas & Clientes con Mayor Carga Operativa
                </h3>
                <p class="text-[11px] text-zinc-500">Empresas con mayor volumen de órdenes procesadas en el tablero.</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="border-b border-[#e9e9e7] text-[10px] uppercase font-bold text-zinc-400 tracking-wider bg-[#fbfbfa]">
                        <th class="py-2.5 px-3">Empresa / Cliente</th>
                        <th class="py-2.5 px-3 font-mono text-right">Órdenes Totales</th>
                        <th class="py-2.5 px-3 font-mono text-right">Atrasadas</th>
                        <th class="py-2.5 px-3 text-right">Participación (%)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#e9e9e7]">
                    @forelse($topCompanies as $comp)
                        @php $compPct = $totalOrders > 0 ? round(($comp->total_orders / $totalOrders) * 100, 1) : 0; @endphp
                        <tr class="hover:bg-stone-50/80 transition">
                            <td class="py-3 px-3 font-bold text-zinc-900 truncate max-w-xs">
                                {{ $comp->company_name }}
                            </td>
                            <td class="py-3 px-3 font-mono font-bold text-zinc-800 text-right">
                                {{ $comp->total_orders }}
                            </td>
                            <td class="py-3 px-3 font-mono text-right">
                                @if($comp->overdue_count > 0)
                                    <span class="px-2 py-0.5 rounded bg-rose-50 text-rose-700 font-semibold border border-rose-200 text-[10px]">
                                        {{ $comp->overdue_count }} atrasadas
                                    </span>
                                @else
                                    <span class="text-emerald-700 font-semibold text-[11px]">0</span>
                                @endif
                            </td>
                            <td class="py-3 px-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <div class="w-20 bg-stone-100 rounded-full h-1.5 overflow-hidden border border-stone-200">
                                        <div class="bg-zinc-800 h-full rounded-full" style="width: {{ max($compPct, 3) }}%"></div>
                                    </div>
                                    <span class="font-mono text-xs text-zinc-700 font-medium w-9">{{ $compPct }}%</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-6 text-center text-zinc-400 italic">No hay registros de empresas aún.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
