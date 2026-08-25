<div class="h-full flex flex-col space-y-4 min-h-0 overflow-y-auto custom-vertical-scrollbar pr-1">
    
    <!-- Flash Message Notification -->
    @if(session()->has('message'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold p-3 rounded-xl flex items-center justify-between shadow-2xs">
            <div class="flex items-center gap-2">
                <x-lucide-check-circle class="w-4 h-4 text-emerald-600 shrink-0" />
                <span>{{ session('message') }}</span>
            </div>
            <button @click="$el.parentElement.remove()" class="text-emerald-500 hover:text-emerald-800">
                <x-lucide-x class="w-3.5 h-3.5" />
            </button>
        </div>
    @endif

    <!-- Header Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-4 sm:p-5 rounded-2xl border border-[#e9e9e7] shadow-2xs shrink-0">
        <div class="space-y-1">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-xl bg-slate-100 border border-slate-200 text-slate-800 flex items-center justify-center font-bold">
                    <x-lucide-archive class="w-4 h-4 text-slate-700" />
                </div>
                <h2 class="text-lg font-bold text-zinc-900 tracking-tight">{{ __('Órdenes Archivadas & Rendimiento') }}</h2>
            </div>
            <p class="text-xs text-zinc-500 font-normal">
                {{ __('Análisis de cierre de órdenes, métricas por diseñador y registro histórico de entregas.') }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2 text-xs">
            <!-- Search Input -->
            <div class="relative">
                <x-lucide-search class="w-3.5 h-3.5 text-zinc-400 absolute left-2.5 top-1/2 -translate-y-1/2" />
                <input 
                    wire:model.live.debounce.250ms="search"
                    type="text"
                    placeholder="{{ __('Buscar en archivo...') }}"
                    class="pl-8 pr-3 py-1.5 bg-[#fbfbfa] border border-[#e9e9e7] rounded-xl text-xs text-zinc-800 placeholder-zinc-400 focus:outline-none focus:border-zinc-400 w-44 sm:w-56"
                >
            </div>

            <!-- Time Filter -->
            <select wire:model.live="timeFilter" class="px-2.5 py-1.5 bg-[#fbfbfa] border border-[#e9e9e7] rounded-xl text-xs text-zinc-700 focus:outline-none focus:border-zinc-400">
                <option value="all">{{ __('Todo el tiempo') }}</option>
                <option value="this_month">{{ __('Este mes') }}</option>
                <option value="this_week">{{ __('Esta semana') }}</option>
            </select>

            <!-- Designer Filter -->
            <select wire:model.live="designerFilter" class="px-2.5 py-1.5 bg-[#fbfbfa] border border-[#e9e9e7] rounded-xl text-xs text-zinc-700 focus:outline-none focus:border-zinc-400">
                <option value="all">{{ __('Todos los diseñadores') }}</option>
                @foreach($designers as $des)
                    <option value="{{ $des->id }}">{{ $des->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- Executive KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 shrink-0">
        
        <!-- KPI 1: Total Archived Orders -->
        <div class="bg-white border border-[#e9e9e7] rounded-2xl p-4 space-y-2 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-zinc-500 uppercase tracking-wider">{{ __('Total Archivadas') }}</span>
                <div class="w-7 h-7 rounded-lg bg-slate-100 border border-slate-200 text-slate-700 flex items-center justify-center">
                    <x-lucide-archive class="w-3.5 h-3.5" />
                </div>
            </div>
            <div>
                <span class="text-2xl sm:text-3xl font-bold font-mono text-zinc-900">{{ $totalArchivedCount }}</span>
                <span class="text-xs text-zinc-500 font-normal ml-1">{{ __('órdenes cerradas') }}</span>
            </div>
            <div class="pt-2 border-t border-[#e9e9e7] flex items-center justify-between text-[11px] text-zinc-500">
                <span>{{ __('Fuera del Workspace') }}</span>
                <span class="font-bold text-emerald-600 flex items-center gap-1">
                    <x-lucide-check-circle class="w-3 h-3" />
                    <span>{{ __('100% cerradas') }}</span>
                </span>
            </div>
        </div>

        <!-- KPI 2: En Producción (Waiting for Archive) -->
        <div class="bg-white border border-[#e9e9e7] rounded-2xl p-4 space-y-2 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-zinc-500 uppercase tracking-wider">{{ __('En Producción') }}</span>
                <div class="w-7 h-7 rounded-lg bg-pink-50 border border-pink-200 text-pink-700 flex items-center justify-center">
                    <x-lucide-box class="w-3.5 h-3.5" />
                </div>
            </div>
            <div>
                <span class="text-2xl sm:text-3xl font-bold font-mono text-pink-900">{{ $inProductionOrders->count() }}</span>
                <span class="text-xs text-zinc-500 font-normal ml-1">{{ __('listas para archivar') }}</span>
            </div>
            <div class="pt-2 border-t border-[#e9e9e7] flex items-center justify-between text-[11px] text-zinc-500">
                <span>{{ __('Columna derecha') }}</span>
                <span class="text-pink-600 font-medium">{{ __('Arrastrables a archivo') }}</span>
            </div>
        </div>

        <!-- KPI 3: Speed & Turnaround Time -->
        <div class="bg-white border border-[#e9e9e7] rounded-2xl p-4 space-y-2 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-zinc-500 uppercase tracking-wider">{{ __('Tiempo de Cierre Prom.') }}</span>
                <div class="w-7 h-7 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 flex items-center justify-center">
                    <x-lucide-clock class="w-3.5 h-3.5" />
                </div>
            </div>
            <div class="flex items-baseline gap-1.5">
                <span class="text-2xl sm:text-3xl font-bold font-mono text-emerald-900">{{ $globalAvgTurnaround }}</span>
                <span class="text-xs text-emerald-700 font-medium">{{ __('días (Inicio ➔ Cierre)') }}</span>
            </div>
            <div class="pt-2 border-t border-[#e9e9e7] flex items-center justify-between text-[11px] text-zinc-500">
                <span>{{ __('Desde Día de Inicio') }}</span>
                <span class="font-mono text-zinc-700 font-semibold">{{ __('Inicio vs Cierre') }}</span>
            </div>
        </div>

        <!-- KPI 4: Lead Designer Closures -->
        @php
            $topDesigner = collect($designerStats)->sortByDesc('count')->first();
        @endphp
        <div class="bg-white border border-[#e9e9e7] rounded-2xl p-4 space-y-2 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-zinc-500 uppercase tracking-wider">{{ __('Diseñador Líder') }}</span>
                <div class="w-7 h-7 rounded-lg bg-indigo-50 border border-indigo-200 text-indigo-700 flex items-center justify-center">
                    <x-lucide-award class="w-3.5 h-3.5" />
                </div>
            </div>
            <div>
                <span class="text-xl sm:text-2xl font-bold text-zinc-900 block truncate">{{ $topDesigner['designer']->name ?? __('Sin datos') }}</span>
                <span class="text-xs text-zinc-500 font-normal">
                    {{ $topDesigner['count'] ?? 0 }} {{ __('órdenes cerradas') }} ({{ $topDesigner['percentage'] ?? 0 }}%)
                </span>
            </div>
            <div class="pt-2 border-t border-[#e9e9e7] flex items-center justify-between text-[11px] text-zinc-500">
                <span>{{ __('Prom. turnaround:') }} <strong>{{ $topDesigner['avg_days'] ?? 0 }}d</strong></span>
                <span>{{ __('Rev:') }} <strong>{{ $topDesigner['total_revisions'] ?? 0 }}</strong></span>
            </div>
        </div>
    </div>

    <!-- Graphics Section: Pie Chart & Performance Analytics -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 shrink-0">
        
        <!-- Graphic 1: Dynamic Pie / Donut Chart (Distribution of Archived Orders per Designer) -->
        <div class="bg-white border border-[#e9e9e7] rounded-2xl p-5 space-y-4 shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between border-b border-stone-100 pb-3">
                <div>
                    <h3 class="text-sm font-bold text-zinc-900 flex items-center gap-2">
                        <x-lucide-pie-chart class="w-4 h-4 text-zinc-700" />
                        <span>{{ __('Distribución por Diseñador') }}</span>
                    </h3>
                    <p class="text-[11px] text-zinc-500">{{ __('Porcentaje de órdenes archivadas por diseñador') }}</p>
                </div>
                <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 font-mono text-[10px] font-bold border border-slate-200">
                    SVG Pie
                </span>
            </div>

            <!-- SVG Donut Chart Visual -->
            <div class="flex flex-col sm:flex-row items-center justify-around gap-4 py-2">
                @php
                    $colors = [
                        'Euralíz' => ['hex' => '#d946ef', 'bg' => 'bg-fuchsia-500', 'text' => 'text-fuchsia-600'],
                        'César' => ['hex' => '#06b6d4', 'bg' => 'bg-cyan-500', 'text' => 'text-cyan-600'],
                        'Adrián' => ['hex' => '#6366f1', 'bg' => 'bg-indigo-500', 'text' => 'text-indigo-600'],
                    ];
                    
                    // Build SVG Donut stroke-dasharray values
                    $cumulativePercentage = 0;
                    $strokeSegments = [];
                    $strokeWidth = 24;
                    $radius = 40;
                    $circumference = 2 * M_PI * $radius; // ~251.32

                    foreach ($designerStats as $ds) {
                        $name = $ds['designer']->name;
                        $pct = $ds['percentage'];
                        $dash = ($pct / 100) * $circumference;
                        $gap = $circumference - $dash;
                        $offset = $circumference - (($cumulativePercentage / 100) * $circumference);
                        
                        $hex = $colors[$name]['hex'] ?? '#64748b';
                        $strokeSegments[] = [
                            'name' => $name,
                            'hex' => $hex,
                            'dash' => "{$dash} {$gap}",
                            'offset' => $offset,
                            'count' => $ds['count'],
                            'pct' => $pct,
                        ];
                        $cumulativePercentage += $pct;
                    }
                @endphp

                <!-- SVG Donut Graphic -->
                <div class="relative w-36 h-36 shrink-0 flex items-center justify-center">
                    <svg class="w-full h-full transform -rotate-90" viewBox="0 0 100 100">
                        <!-- Background Circle -->
                        <circle cx="50" cy="50" r="{{ $radius }}" fill="transparent" stroke="#f1f5f9" stroke-width="{{ $strokeWidth }}" />
                        
                        @if($totalArchivedCount > 0)
                            @foreach($strokeSegments as $seg)
                                @if($seg['pct'] > 0)
                                    <circle 
                                        cx="50" 
                                        cy="50" 
                                        r="{{ $radius }}" 
                                        fill="transparent" 
                                        stroke="{{ $seg['hex'] }}" 
                                        stroke-width="{{ $strokeWidth }}" 
                                        stroke-dasharray="{{ $seg['dash'] }}" 
                                        stroke-dashoffset="{{ $seg['offset'] }}"
                                        class="transition-all duration-500 hover:opacity-80 cursor-pointer"
                                    />
                                @endif
                            @endforeach
                        @endif
                    </svg>
                    <!-- Center Badge -->
                    <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                        <span class="text-xl font-bold font-mono text-zinc-900">{{ $totalArchivedCount }}</span>
                        <span class="text-[9px] uppercase font-bold text-zinc-400 tracking-wider">{{ __('Cerradas') }}</span>
                    </div>
                </div>

                <!-- Donut Legend -->
                <div class="space-y-2.5 text-xs w-full sm:w-auto">
                    @foreach($designerStats as $ds)
                        @php
                            $c = $colors[$ds['designer']->name] ?? ['bg' => 'bg-slate-500', 'text' => 'text-slate-600'];
                        @endphp
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full {{ $c['bg'] }} shrink-0"></span>
                                <span class="font-medium text-zinc-800">{{ $ds['designer']->name }}</span>
                            </div>
                            <div class="font-mono text-[11px] font-semibold text-zinc-600">
                                <span>{{ $ds['count'] }}</span>
                                <span class="text-zinc-400 text-[10px]">({{ $ds['percentage'] }}%)</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Graphic 2: Performance & Turnaround Speed Bar Chart (Started Day ➔ Closed Day) -->
        <div class="bg-white border border-[#e9e9e7] rounded-2xl p-5 space-y-4 shadow-2xs lg:col-span-2 flex flex-col justify-between">
            <div class="flex items-center justify-between border-b border-stone-100 pb-3">
                <div>
                    <h3 class="text-sm font-bold text-zinc-900 flex items-center gap-2">
                        <x-lucide-bar-chart-2 class="w-4 h-4 text-zinc-700" />
                        <span>{{ __('Velocidad de Cierre & Turnaround (Días Inicio ➔ Cierre)') }}</span>
                    </h3>
                    <p class="text-[11px] text-zinc-500">{{ __('Comparativa de días promedio transcurridos desde Día de Inicio hasta Día de Cierre') }}</p>
                </div>
                <span class="px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-[10px] font-bold border border-emerald-200">
                    {{ __('Promedio global:') }} {{ $globalAvgTurnaround }} {{ __('días') }}
                </span>
            </div>

            <!-- Bar Chart Content -->
            <div class="space-y-3.5 py-1">
                @foreach($designerStats as $ds)
                    @php
                        $c = $colors[$ds['designer']->name] ?? ['bg' => 'bg-slate-500', 'text' => 'text-slate-600'];
                        $maxDays = max(1, collect($designerStats)->max('avg_days'));
                        $barPct = $maxDays > 0 ? min(100, round(($ds['avg_days'] / $maxDays) * 100)) : 0;
                    @endphp
                    <div class="space-y-1">
                        <div class="flex items-center justify-between text-xs font-medium">
                            <span class="text-zinc-800 font-semibold flex items-center gap-1.5">
                                <span class="w-2.5 h-2.5 rounded-full {{ $c['bg'] }}"></span>
                                <span>{{ $ds['designer']->name }}</span>
                            </span>
                            <div class="flex items-center gap-4 text-[11px] font-mono">
                                <span>{{ __('Órdenes:') }} <strong class="text-zinc-900">{{ $ds['count'] }}</strong></span>
                                <span>{{ __('Revisiones:') }} <strong class="text-zinc-900">{{ $ds['total_revisions'] }}</strong></span>
                                <span class="text-emerald-700 font-bold bg-emerald-50 px-2 py-0.5 rounded border border-emerald-100">
                                    {{ $ds['avg_days'] }} {{ __('días prom.') }}
                                </span>
                            </div>
                        </div>
                        <!-- Progress Bar Container -->
                        <div class="w-full bg-stone-100 rounded-full h-3 overflow-hidden border border-stone-200 flex items-center">
                            <div 
                                class="{{ $c['bg'] }} h-full rounded-full transition-all duration-500 relative" 
                                style="width: {{ max(6, $barPct) }}%"
                            ></div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="pt-3 border-t border-stone-100 flex items-center justify-between text-[11px] text-zinc-500">
                <span class="flex items-center gap-1">
                    <x-lucide-calendar class="w-3.5 h-3.5 text-zinc-400" />
                    <span>{{ __('Métrica calculada de (Archived At - Start Date)') }}</span>
                </span>
                <span class="font-medium text-zinc-600">{{ __('Ordenado por volumen de cierre') }}</span>
            </div>
        </div>
    </div>

    <!-- Main Content Area: Designer Archived Lists (Left 2 cols) & En Producción Sidebar (Right 1 col) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 min-h-0 flex-1">
        
        <!-- Left Section (2 Cols): Grouped Archived Orders by Designer -->
        <div class="lg:col-span-2 space-y-4">
            
            @if($archivedOrders->isEmpty())
                <div class="bg-white border border-[#e9e9e7] rounded-2xl p-12 text-center space-y-3 shadow-2xs">
                    <div class="w-16 h-16 rounded-2xl bg-slate-50 border border-slate-200 text-slate-400 mx-auto flex items-center justify-center">
                        <x-lucide-archive-restore class="w-8 h-8" />
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-sm font-bold text-zinc-800 uppercase tracking-tight">{{ __('No hay órdenes archivadas') }}</h3>
                        <p class="text-xs text-zinc-500">
                            {{ __('Arrastra órdenes desde la columna "En Producción" o desde el Kanban para archivarlas.') }}
                        </p>
                    </div>
                </div>
            @else
                @foreach($designerStats as $ds)
                    @if($ds['orders']->isNotEmpty())
                        @php
                            $c = $colors[$ds['designer']->name] ?? ['bg' => 'bg-slate-500', 'text' => 'text-slate-600'];
                        @endphp
                        <div class="bg-white border border-[#e9e9e7] rounded-2xl overflow-hidden shadow-2xs space-y-0">
                            
                            <!-- Designer Group Header -->
                            <div class="p-4 bg-[#f9f9f8] border-b border-[#e9e9e7] flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <span class="w-3 h-3 rounded-full {{ $c['bg'] }} ring-2 ring-stone-200"></span>
                                    <h3 class="font-bold text-sm text-zinc-900 tracking-tight">{{ $ds['designer']->name }}</h3>
                                    <span class="px-2 py-0.5 rounded-full bg-white text-[11px] font-mono text-zinc-700 font-semibold border border-stone-200">
                                        {{ $ds['count'] }} {{ __('órdenes') }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-3 text-xs font-mono text-zinc-500">
                                    <span>{{ __('Prom. cierre:') }} <strong class="text-zinc-800">{{ $ds['avg_days'] }}d</strong></span>
                                    <span>{{ __('Revisiones:') }} <strong class="text-zinc-800">{{ $ds['total_revisions'] }}</strong></span>
                                </div>
                            </div>

                            <!-- Designer Archived Cards Grid -->
                            <div class="p-4 grid grid-cols-1 sm:grid-cols-2 gap-3 bg-stone-50/30">
                                @foreach($ds['orders'] as $order)
                                    <div class="bg-white border border-[#e9e9e7] rounded-xl p-3.5 space-y-2.5 hover:shadow-md transition group">
                                        
                                        <!-- Top Row: WO & Company -->
                                        <div class="flex items-start justify-between gap-2">
                                            <div>
                                                <span class="text-[10px] font-mono font-bold text-zinc-500 bg-stone-100 px-1.5 py-0.5 rounded border border-stone-200 inline-block mb-1">
                                                    {{ $order->wo_number ?? 'WO-N/A' }}
                                                </span>
                                                <h4 class="text-xs font-bold text-zinc-900 group-hover:text-indigo-600 transition truncate max-w-[200px]">
                                                    {{ $order->company_name }}
                                                </h4>
                                                <p class="text-[11px] text-zinc-600 line-clamp-1">{{ $order->task_name }}</p>
                                            </div>

                                            <button 
                                                wire:click="restoreOrder({{ $order->id }})"
                                                wire:confirm="¿Desarchivar esta orden y devolverla a En Producción?"
                                                class="p-1.5 rounded-lg text-zinc-400 hover:text-emerald-700 hover:bg-emerald-50 border border-transparent hover:border-emerald-200 transition" 
                                                title="{{ __('Reabrir / Restaurar a En Producción') }}">
                                                <x-lucide-rotate-ccw class="w-3.5 h-3.5" />
                                            </button>
                                        </div>

                                        <!-- Dates Row: Started Day & Closed Day -->
                                        <div class="p-2 rounded-lg bg-[#fbfbfa] border border-[#eee] text-[11px] space-y-1 font-mono text-zinc-600">
                                            <div class="flex items-center justify-between">
                                                <span class="text-zinc-400 flex items-center gap-1">
                                                    <x-lucide-play-circle class="w-3 h-3 text-zinc-400" />
                                                    <span>{{ __('Inicio:') }}</span>
                                                </span>
                                                <strong class="text-zinc-800">
                                                    {{ $order->start_date ? \Illuminate\Support\Carbon::parse($order->start_date)->format('d M, Y') : $order->created_at->format('d M, Y') }}
                                                </strong>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <span class="text-zinc-400 flex items-center gap-1">
                                                    <x-lucide-check-circle class="w-3 h-3 text-slate-500" />
                                                    <span>{{ __('Cierre:') }}</span>
                                                </span>
                                                <strong class="text-slate-900">
                                                    {{ $order->archived_at ? $order->archived_at->format('d M, Y') : '—' }}
                                                </strong>
                                            </div>
                                        </div>

                                        <!-- Card Footer Stats -->
                                        <div class="flex items-center justify-between text-[10px] font-mono text-zinc-500 pt-1 border-t border-stone-100">
                                            <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 font-bold border border-slate-200 flex items-center gap-1">
                                                <x-lucide-timer class="w-3 h-3" />
                                                <span>{{ $order->days_to_close ?? 0 }} {{ __('días transcurridos') }}</span>
                                            </span>
                                            <span class="text-zinc-400">
                                                {{ __('Rev:') }} <strong class="text-zinc-700">{{ $order->client_revision_count }}</strong>
                                            </span>
                                        </div>

                                    </div>
                                @endforeach
                            </div>

                        </div>
                    @endif
                @endforeach
            @endif

        </div>

        <!-- Right Sidebar (1 Col): Orders Currently In Production ("En Producción") -->
        <div class="space-y-3">
            <div 
                x-data="{ isTarget: false }"
                @dragover.prevent="isTarget = true"
                @dragleave.prevent="isTarget = false"
                @drop.prevent="
                    isTarget = false;
                    const orderId = event.dataTransfer.getData('text/plain');
                    if (orderId) {
                        $wire.archiveOrder(orderId);
                    }
                "
                :class="{ 'border-zinc-600 ring-4 ring-zinc-300 bg-zinc-100': isTarget }"
                class="bg-white border border-[#e9e9e7] rounded-2xl p-4 space-y-3 shadow-2xs sticky top-0">
                
                <!-- Sidebar Header -->
                <div class="flex items-center justify-between border-b border-stone-100 pb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-lg bg-pink-50 border border-pink-200 text-pink-700 flex items-center justify-center font-bold">
                            <x-lucide-box class="w-4 h-4" />
                        </div>
                        <div>
                            <h3 class="font-bold text-xs text-zinc-900 uppercase tracking-wider">{{ __('En Producción') }}</h3>
                            <p class="text-[10px] text-zinc-500">{{ __('Listas para archivar y cerrar') }}</p>
                        </div>
                    </div>
                    <span class="px-2 py-0.5 rounded-full bg-pink-100 text-pink-800 text-[11px] font-mono font-bold">
                        {{ $inProductionOrders->count() }}
                    </span>
                </div>

                <!-- Archive Drag & Drop Zone inside Sidebar -->
                <div 
                    class="p-3 border-2 border-dashed border-stone-300 rounded-xl bg-stone-50 text-center space-y-1.5 transition">
                    <x-lucide-archive class="w-5 h-5 text-zinc-500 mx-auto" />
                    <span class="text-[11px] font-bold text-zinc-700 block uppercase tracking-tight">{{ __('Dropzone de Archivo') }}</span>
                    <p class="text-[10px] text-zinc-500 leading-tight">
                        {{ __('Arrastra una tarjeta desde abajo hacia este recuadro para archivarla de inmediato.') }}
                    </p>
                </div>

                <!-- In Production Cards List -->
                <div class="space-y-2 max-h-[calc(100vh-320px)] overflow-y-auto custom-vertical-scrollbar pr-1">
                    @if($inProductionOrders->isEmpty())
                        <div class="p-6 text-center text-xs text-zinc-400 border border-dashed border-stone-200 rounded-xl">
                            {{ __('No hay órdenes en producción actualmente.') }}
                        </div>
                    @else
                        @foreach($inProductionOrders as $order)
                            <div 
                                draggable="true"
                                @dragstart="event.dataTransfer.setData('text/plain', '{{ $order->id }}')"
                                class="bg-[#fcfcfb] border border-[#e9e9e7] hover:border-zinc-400 rounded-xl p-3 space-y-2 cursor-grab active:cursor-grabbing transition shadow-2xs group">
                                
                                <div class="flex items-start justify-between gap-1">
                                    <div>
                                        <span class="text-[9px] font-mono font-bold text-pink-700 bg-pink-50 px-1.5 py-0.5 rounded border border-pink-200">
                                            {{ $order->wo_number ?? 'WO-N/A' }}
                                        </span>
                                        <h5 class="text-xs font-bold text-zinc-900 truncate mt-1">{{ $order->company_name }}</h5>
                                        <p class="text-[11px] text-zinc-500 line-clamp-1">{{ $order->task_name }}</p>
                                    </div>
                                    
                                    <button 
                                        wire:click="archiveOrder({{ $order->id }})"
                                        wire:confirm="¿Archivar la orden {{ $order->company_name }}?"
                                        class="px-2 py-1 rounded bg-slate-900 hover:bg-slate-800 text-white text-[10px] font-medium transition flex items-center gap-1 shrink-0 shadow-2xs"
                                        title="{{ __('Archivar inmediatamente') }}">
                                        <x-lucide-archive class="w-3 h-3 text-slate-300" />
                                        <span>{{ __('Archivar') }}</span>
                                    </button>
                                </div>

                                <div class="flex items-center justify-between text-[10px] font-mono text-zinc-400 pt-1 border-t border-stone-100">
                                    <span>{{ $order->designer?->name ?? __('Sin asignar') }}</span>
                                    <span>{{ $order->start_date ? \Illuminate\Support\Carbon::parse($order->start_date)->format('d M') : $order->created_at->format('d M') }}</span>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>

            </div>
        </div>

    </div>

</div>
