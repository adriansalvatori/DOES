@php
    // Stages share one neutral ramp (dark to light). Only "blocked" borrows a health tone.
    $stageRamp = ['#1c1917', '#44403c', '#57534e', '#78716c', '#8f8a85', '#a8a29e', '#bdb8b2', '#d6d3d1'];
    $stageColor = function ($status, int $i) use ($stageRamp) {
        return $status === \App\Enums\CoreStatus::ENTRANTE ? 'var(--color-warn)' : $stageRamp[$i % count($stageRamp)];
    };
    // Availability bars: stage in neutral tints, designer identity as the dot under each bar.
    $tintCamila = '#1c1917';
    $tintWorking = '#78716c';
    $tintQueue = '#d6d3d1';
@endphp

<div class="h-full flex flex-col gap-6 min-h-0 overflow-y-auto custom-vertical-scrollbar pr-1 pb-6">

    <x-ui.page-header id="tour-analytics-header" :title="__('Analytics')" :subtitle="trans_choice('{1} :count orden activa|[2,*] :count órdenes activas', $totalOrders, ['count' => $totalOrders])" />

    {{-- 1. Order map --}}
    <section class="ui-card p-5 space-y-4">
        <h2 class="ui-section-title">{{ __('Mapa de Órdenes en Curso') }}</h2>

        <div class="w-full h-2 rounded-full overflow-hidden bg-sunken flex gap-px">
            @if($totalOrders > 0)
                @foreach($coreStatusCounts as $i => $item)
                    @if($item['count'] > 0)
                        <div class="h-full" style="width: {{ max($item['percentage'], 1.5) }}%; background-color: {{ $stageColor($item['status'], $i) }};"
                            title="{{ $item['label'] }}: {{ trans_choice('{1} :count orden|[2,*] :count órdenes', $item['count'], ['count' => $item['count']]) }} ({{ $item['percentage'] }}%)"></div>
                    @endif
                @endforeach
            @endif
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            @foreach($coreStatusCounts as $i => $item)
                <div class="rounded-card border border-line p-3">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="w-2 h-2 rounded-full shrink-0" style="background-color: {{ $stageColor($item['status'], $i) }}"></span>
                        <span class="text-body text-ink-2 truncate" title="{{ $item['label'] }}">{{ $item['label'] }}</span>
                    </div>
                    <div class="mt-1.5 flex items-baseline justify-between">
                        <span class="text-title font-semibold tabular-nums text-ink">{{ $item['count'] }}</span>
                        <span class="text-meta tabular-nums text-ink-3">{{ $item['percentage'] }}%</span>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- 2. Availability by designer --}}
    <section class="ui-card p-5 space-y-4">
        <h2 class="ui-section-title">{{ __('Disponibilidad Actual por Diseñador') }}</h2>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-stretch">
            <div class="lg:col-span-4 rounded-card border border-line bg-canvas p-4 flex flex-col gap-3">
                <div class="h-64 flex items-end justify-around gap-3 border-b border-line">
                    @foreach($designerAvailabilityStats as $st)
                        @php
                            $totalActive = $st['total_active'];
                            $barHeightPct = $maxAvailableOrders > 0 ? round(($totalActive / $maxAvailableOrders) * 100, 1) : 0;
                            $incPct = $totalActive > 0 ? ($st['incoming_count'] / $totalActive) * 100 : 0;
                            $workPct = $totalActive > 0 ? ($st['working_today_count'] / $totalActive) * 100 : 0;
                            $camPct = $totalActive > 0 ? ($st['sent_to_camila_count'] / $totalActive) * 100 : 0;
                        @endphp
                        <div class="flex flex-col items-center justify-end h-full flex-1 max-w-[56px]">
                            <span class="text-meta font-medium tabular-nums text-ink mb-1">{{ $totalActive }}</span>
                            <div class="w-full rounded-t-control overflow-hidden flex flex-col" style="height: {{ $totalActive > 0 ? max($barHeightPct, 6) : 1 }}%; {{ $totalActive > 0 ? '' : 'background-color:#d6d3d1;' }}">
                                @if($st['sent_to_camila_count'] > 0)
                                    <div style="height: {{ $camPct }}%; background-color: {{ $tintCamila }};" title="{{ \App\Enums\CoreStatus::ENVIADO_A_CAMILA->label() }}: {{ $st['sent_to_camila_count'] }}"></div>
                                @endif
                                @if($st['working_today_count'] > 0)
                                    <div style="height: {{ $workPct }}%; background-color: {{ $tintWorking }};" title="{{ \App\Enums\CoreStatus::TO_DO_TODAY->label() }}: {{ $st['working_today_count'] }}"></div>
                                @endif
                                @if($st['incoming_count'] > 0)
                                    <div style="height: {{ $incPct }}%; background-color: {{ $tintQueue }};" title="{{ $st['queue_status']->label() }}: {{ $st['incoming_count'] }}"></div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="flex justify-around gap-3">
                    @foreach($designerAvailabilityStats as $st)
                        <div class="flex items-center justify-center gap-1.5 flex-1 max-w-[56px] min-w-0">
                            <span class="w-2 h-2 rounded-full shrink-0" style="background-color: {{ $st['designer']->hex_color ?: '#a8a29e' }}"></span>
                            <span class="text-meta text-ink-2 truncate" title="{{ $st['designer']->name }}">{{ Str::before($st['designer']->name, ' ') }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="flex flex-wrap justify-center gap-x-4 gap-y-1 text-meta text-ink-2">
                    @foreach([[$tintCamila, \App\Enums\CoreStatus::ENVIADO_A_CAMILA], [$tintWorking, \App\Enums\CoreStatus::TO_DO_TODAY], [$tintQueue, \App\Enums\CoreStatus::ENTRANTE]] as [$tint, $case])
                        <span class="inline-flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-xs" style="background-color: {{ $tint }}"></span>
                            {{ $case === \App\Enums\CoreStatus::ENTRANTE ? __('En cola') : $case->shortLabel() }}
                        </span>
                    @endforeach
                </div>
            </div>

            <div class="lg:col-span-8 grid grid-cols-1 md:grid-cols-3 gap-4 items-stretch">
                @foreach($designerAvailabilityStats as $st)
                    @php
                        $des = $st['designer'];
                        $isLowestCount = ($st['total_active'] === $minAvailableOrders);
                    @endphp
                    <div class="rounded-card border border-line bg-surface flex flex-col min-w-0">
                        <div class="p-3 border-b border-line space-y-2">
                            <div class="flex items-center justify-between gap-2">
                                <x-ui.person :person="$des" size="sm" show-name />
                                <span class="text-body font-medium tabular-nums text-ink shrink-0">{{ trans_choice('{1} :count activa|[2,*] :count activas', $st['total_active'], ['count' => $st['total_active']]) }}</span>
                            </div>
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-meta text-ink-3">
                                <span>{{ $st['queue_status']->shortLabel() }} <span class="tabular-nums text-ink-2">{{ $st['incoming_count'] }}</span></span>
                                <span>{{ \App\Enums\CoreStatus::TO_DO_TODAY->shortLabel() }} <span class="tabular-nums text-ink-2">{{ $st['working_today_count'] }}</span></span>
                                <span>{{ \App\Enums\CoreStatus::ENVIADO_A_CAMILA->shortLabel() }} <span class="tabular-nums text-ink-2">{{ $st['sent_to_camila_count'] }}</span></span>
                            </div>
                            @if($isLowestCount)
                                <x-ui.tag tone="ok" title="{{ __('Diseñador con menor número de órdenes activas') }}">{{ __('Menor Carga') }}</x-ui.tag>
                            @endif
                        </div>

                        <div class="flex-1 overflow-y-auto max-h-60 custom-vertical-scrollbar divide-y divide-line">
                            @forelse($st['orders'] as $order)
                                <div wire:click="$dispatch('open-order-detail', { orderId: {{ $order->id }} })" role="button" tabindex="0"
                                    class="px-3 py-2 hover:bg-sunken cursor-pointer min-w-0">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <x-wo-badge :number="$order->wo_number" />
                                        <span class="text-body font-medium text-ink truncate">{{ $order->company_name }}</span>
                                    </div>
                                    <p class="text-meta text-ink-3 truncate mt-0.5" title="{{ $order->clean_task_name }}">{{ $order->clean_task_name }}</p>
                                </div>
                            @empty
                                <p class="p-4 text-center text-body text-ink-3">{{ __('Sin órdenes en proceso') }}</p>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- 3. Workload and SLA --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-stretch">
        <section class="lg:col-span-7 ui-card p-5 space-y-4">
            <div>
                <h2 class="ui-section-title">{{ __('Carga de Trabajo por Diseñador') }}</h2>
                <p class="text-body text-ink-3 mt-0.5">{{ __('Distribución porcentual de órdenes asignadas activas por integrante.') }}</p>
            </div>

            @php
                $accumulatedPct = 0;
                $gradientStops = [];
                foreach($designerStats as $st) {
                    $color = $st['designer']->hex_color ?: '#a8a29e';
                    $start = $accumulatedPct;
                    $end = $accumulatedPct + $st['workload_pct'];
                    $accumulatedPct = $end;
                    if ($st['count'] > 0) {
                        $gradientStops[] = "{$color} {$start}% {$end}%";
                    }
                }
                $unassignedPct = $totalOrders > 0 ? round(($unassignedCount / $totalOrders) * 100, 1) : 0;
                if ($unassignedCount > 0 && $totalOrders > 0) {
                    $gradientStops[] = "#d6d3d1 {$accumulatedPct}% ".min(100, $accumulatedPct + $unassignedPct).'%';
                }
                $conicGradient = ! empty($gradientStops) ? implode(', ', $gradientStops) : '#ebeae6 0% 100%';
            @endphp

            <div class="flex flex-col sm:flex-row items-center gap-6">
                <div class="relative w-40 h-40 shrink-0 rounded-full grid place-items-center" style="background: conic-gradient({{ $conicGradient }});">
                    <div class="w-28 h-28 bg-surface rounded-full flex flex-col items-center justify-center text-center">
                        <span class="text-title font-semibold tabular-nums text-ink leading-none">{{ $totalOrders }}</span>
                        <span class="text-meta text-ink-3 mt-1">{{ __('Órdenes') }}</span>
                    </div>
                </div>

                <div class="flex-1 w-full divide-y divide-line">
                    @foreach($designerStats as $st)
                        <div class="flex items-center justify-between gap-3 py-2.5">
                            <div class="flex items-center gap-2 min-w-0">
                                <x-ui.person :person="$st['designer']" size="sm" show-name />
                                @if($st['overdue'] > 0)
                                    <x-ui.tag tone="danger" title="{{ __('Órdenes Atrasadas') }}">{{ $st['overdue'] }} {{ trans_choice('{1} atrasada|[2,*] atrasadas', $st['overdue']) }}</x-ui.tag>
                                @endif
                            </div>
                            <div class="flex items-center gap-4 shrink-0 text-body tabular-nums">
                                <span class="text-ink-2">{{ $st['count'] }} {{ trans_choice('{1} orden|[2,*] órdenes', $st['count']) }}</span>
                                <span class="font-medium text-ink w-12 text-right">{{ $st['workload_pct'] }}%</span>
                            </div>
                        </div>
                    @endforeach
                    @if($unassignedCount > 0)
                        <div class="flex items-center justify-between gap-3 py-2.5">
                            <x-ui.person :person="null" />
                            <div class="flex items-center gap-4 shrink-0 text-body tabular-nums">
                                <span class="text-ink-2">{{ $unassignedCount }} {{ trans_choice('{1} orden|[2,*] órdenes', $unassignedCount) }}</span>
                                <span class="font-medium text-ink w-12 text-right">{{ $unassignedPct }}%</span>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        <div class="lg:col-span-5 flex flex-col gap-6">
            <section class="ui-card p-5 flex-1 flex flex-col gap-3">
                <div>
                    <h2 class="ui-section-title">{{ __('Órdenes Activas') }}</h2>
                    <p class="text-body text-ink-3 mt-0.5">{{ __('Volumen actual en el tablero operativo') }}</p>
                </div>
                <span class="text-[32px] leading-10 font-semibold tabular-nums text-ink">{{ $totalOrders }}</span>
                <p class="mt-auto pt-3 border-t border-line text-body text-ink-3 flex flex-wrap justify-between gap-2">
                    <span>{{ __('Excluye Backlog y En Producción') }}</span>
                    <span class="text-ink-2">{{ $inProductionCount }} {{ __('completadas en producción') }}</span>
                </p>
            </section>

            <section class="ui-card p-5 flex-1 flex flex-col gap-3">
                <div>
                    <h2 class="ui-section-title">{{ __('Cumplimiento del SLA') }}</h2>
                    <p class="text-body text-ink-3 mt-0.5">{{ __('Porcentaje de puntualidad en los tiempos pactados/esperados de entrega') }}</p>
                </div>
                <div class="flex items-baseline gap-2">
                    <span class="text-[32px] leading-10 font-semibold tabular-nums text-ink">{{ $slaComplianceRate }}%</span>
                    <span class="text-body text-ink-3">{{ __('órdenes a tiempo') }}</span>
                </div>
                <div class="w-full bg-sunken rounded-full h-1.5 overflow-hidden">
                    <div class="bg-ink h-full rounded-full" style="width: {{ $slaComplianceRate }}%"></div>
                </div>
                <p class="mt-auto pt-3 border-t border-line text-body flex flex-wrap justify-between gap-2">
                    <span class="text-ink-2 tabular-nums">{{ max(0, $totalOrders - $overdueCount) }} {{ __('órdenes a tiempo') }}</span>
                    @if($overdueCount > 0)
                        <span class="text-danger font-medium tabular-nums">{{ $overdueCount }} {{ __('Órdenes Atrasadas') }} ({{ $overdueRate }}%)</span>
                    @else
                        <span class="text-ink-3">{{ __('Sin entregas vencidas') }}</span>
                    @endif
                </p>
            </section>
        </div>
    </div>
</div>
