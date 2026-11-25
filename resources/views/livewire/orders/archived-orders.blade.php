@assets
<script>
    window.fillSubstatusOptions = (select) => {
        const template = document.getElementById('archived-substatus-options');
        if (!template || select.options.length > 1) return;
        const current = select.dataset.current;
        const currentOption = select.options[0].cloneNode(true);
        select.innerHTML = template.innerHTML;
        if (![...select.options].some(option => option.value === current)) {
            select.prepend(currentOption);
        }
        select.value = current;
    };
</script>
@endassets

<div class="h-full flex flex-col gap-4 min-h-0 overflow-y-auto custom-vertical-scrollbar">
    @if(session()->has('message'))
        <div x-data="{ show: true }" x-show="show" class="flex shrink-0 items-center justify-between gap-2 rounded-card border border-line bg-surface px-3 py-2 text-body text-ink">
            <span class="flex items-center gap-2"><x-lucide-check-circle class="h-4 w-4 shrink-0 text-ok" />{{ session('message') }}</span>
            <button type="button" @click="show = false" class="ui-btn ui-btn-ghost ui-btn-icon ui-btn-sm" aria-label="{{ __('Close') }}"><x-lucide-x /></button>
        </div>
    @endif

    <x-ui.page-header :title="__('Archived orders')" :count="$totalArchivedCount">
        <x-slot:toolbar>
            <div class="relative w-64">
                <x-lucide-search class="pointer-events-none absolute left-2.5 top-2 h-4 w-4 text-ink-3" />
                <input wire:model.live.debounce.250ms="search" type="text" placeholder="{{ __('Search in archive') }}" class="ui-input pl-8">
            </div>
            <select wire:model.live="substatusFilter" class="ui-select w-48" aria-label="{{ __('Closing') }}">
                <option value="all">{{ __('All outcomes') }}</option>
                <option value="finalizada">{{ __('Completed successfully') }}</option>
                <option value="cancelada">{{ __('Canceled') }}</option>
                <option value="no_responsive">{{ __('Unresponsive Client') }}</option>
            </select>
            <select wire:model.live="timeFilter" class="ui-select w-40" aria-label="{{ __('Period') }}">
                <option value="all">{{ __('All time') }}</option>
                <option value="this_month">{{ __('This month') }}</option>
                <option value="this_week">{{ __('This week') }}</option>
            </select>
            <select wire:model.live="designerFilter" class="ui-select w-48" aria-label="{{ __('Designer') }}">
                <option value="all">{{ __('All Designers') }}</option>
                @foreach($designers as $des)
                    <option value="{{ $des->id }}">{{ $des->name }}</option>
                @endforeach
            </select>
        </x-slot:toolbar>
    </x-ui.page-header>

    @php $emptyHint = __('Drag orders from the In Production column or from the Kanban board to archive them.'); @endphp
    @php $topDesigner = collect($designerStats)->sortByDesc('count')->first(); @endphp

    <!-- KPIs -->
    <div class="grid shrink-0 grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="ui-card p-4">
            <p class="text-meta text-ink-3">{{ __('Total Archived') }}</p>
            <p class="mt-1 text-title font-semibold tabular-nums text-ink">{{ $totalArchivedCount }}</p>
            <p class="mt-2 border-t border-line pt-2 text-meta text-ink-3">
                {{ __(':count successful', ['count' => $successfulCount]) }} · {{ __(':count canceled', ['count' => $canceledCount]) }} · {{ __(':count unresponsive', ['count' => $nonResponsiveCount]) }}
            </p>
        </div>
        <div class="ui-card p-4">
            <p class="text-meta text-ink-3">{{ __('In Production') }}</p>
            <p class="mt-1 text-title font-semibold tabular-nums text-ink">{{ $inProductionOrders->count() }}</p>
            <p class="mt-2 border-t border-line pt-2 text-meta text-ink-3">{{ __('ready to archive') }}</p>
        </div>
        <div class="ui-card p-4">
            <p class="text-meta text-ink-3">{{ __('Avg. time to close') }}</p>
            <p class="mt-1 text-title font-semibold tabular-nums text-ink">{{ $globalAvgTurnaround }} <span class="text-body font-normal text-ink-3">{{ __('days') }}</span></p>
            <p class="mt-2 border-t border-line pt-2 text-meta text-ink-3">{{ __('Start vs close') }}</p>
        </div>
        <div class="ui-card p-4">
            <p class="text-meta text-ink-3">{{ __('Top designer') }}</p>
            <p class="mt-1 truncate text-title font-semibold text-ink">{{ $topDesigner['designer']->name ?? __('No data') }}</p>
            <p class="mt-2 border-t border-line pt-2 text-meta text-ink-3">
                {{ trans_choice('{1} :count order closed|[0,*] :count orders closed', $topDesigner['count'] ?? 0) }} ({{ $topDesigner['percentage'] ?? 0 }}%) · {{ __(':days d average', ['days' => $topDesigner['avg_days'] ?? 0]) }}
            </p>
        </div>
    </div>

    <!-- Charts -->
    <div class="grid shrink-0 grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="ui-card p-4">
            <h3 class="ui-section-title">{{ __('Distribution by designer') }}</h3>
            <p class="text-meta text-ink-3">{{ __('Share of archived orders by designer') }}</p>
            <div class="mt-3 flex flex-col items-center justify-around gap-4 sm:flex-row">
                @php
                    $colors = [
                        'Euralíz' => ['hex' => '#F3A8FF', 'bg' => 'bg-fuchsia-500', 'text' => 'text-fuchsia-600'],
                        'César' => ['hex' => '#52EAFD', 'bg' => 'bg-cyan-500', 'text' => 'text-cyan-600'],
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
                        
                        $hex = $ds['designer']->hex_color ?: ($colors[$name]['hex'] ?? '#64748b');
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
                <div class="relative flex h-32 w-32 shrink-0 items-center justify-center">
                    <svg class="h-full w-full -rotate-90" viewBox="0 0 100 100" aria-hidden="true">
                        <circle cx="50" cy="50" r="{{ $radius }}" fill="transparent" stroke="#f3f3f0" stroke-width="{{ $strokeWidth }}" />
                        @if($totalArchivedCount > 0)
                            @foreach($strokeSegments as $seg)
                                @if($seg['pct'] > 0)
                                    <circle cx="50" cy="50" r="{{ $radius }}" fill="transparent" stroke="{{ $seg['hex'] }}" stroke-width="{{ $strokeWidth }}" stroke-dasharray="{{ $seg['dash'] }}" stroke-dashoffset="{{ $seg['offset'] }}" />
                                @endif
                            @endforeach
                        @endif
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                        <span class="text-title font-semibold tabular-nums text-ink">{{ $totalArchivedCount }}</span>
                        <span class="text-meta text-ink-3">{{ __('Closed') }}</span>
                    </div>
                </div>
                <div class="w-full space-y-2 text-body sm:w-auto">
                    @foreach($designerStats as $ds)
                        <div class="flex items-center justify-between gap-3">
                            <span class="flex items-center gap-2 text-ink">
                                <span class="h-2.5 w-2.5 shrink-0 rounded-full bg-ink-3" style="{{ $ds['designer']->dot_inline_style }}"></span>{{ $ds['designer']->name }}
                            </span>
                            <span class="tabular-nums text-ink-2">{{ $ds['count'] }} <span class="text-ink-3">({{ $ds['percentage'] }}%)</span></span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="ui-card p-4 lg:col-span-2">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="ui-section-title">{{ __('Closing speed') }}</h3>
                    <p class="text-meta text-ink-3">{{ __('Average days from start to close, by designer') }}</p>
                </div>
                <x-ui.tag tone="neutral">{{ __('Overall average:') }} {{ $globalAvgTurnaround }} {{ __('days') }}</x-ui.tag>
            </div>
            <div class="mt-4 space-y-3">
                @php $maxDays = max(1, collect($designerStats)->max('avg_days')); @endphp
                @foreach($designerStats as $ds)
                    @php $barPct = min(100, round(($ds['avg_days'] / $maxDays) * 100)); @endphp
                    <div class="space-y-1">
                        <div class="flex items-center justify-between text-body">
                            <span class="flex items-center gap-2 font-medium text-ink">
                                <span class="h-2.5 w-2.5 rounded-full bg-ink-3" style="{{ $ds['designer']->dot_inline_style }}"></span>{{ $ds['designer']->name }}
                            </span>
                            <span class="text-meta tabular-nums text-ink-3">
                                {{ trans_choice('{1} :count order|[0,*] :count orders', $ds['count']) }} · {{ __(':count revisions', ['count' => $ds['total_revisions']]) }} ·
                                <span class="font-medium text-ink">{{ $ds['avg_days'] }} {{ __('avg. days') }}</span>
                            </span>
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-sunken">
                            <div class="h-full rounded-full bg-ink-2" style="width: {{ max(4, $barPct) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="grid min-h-0 flex-1 grid-cols-1 gap-4 2xl:grid-cols-3">
        <!-- Archived orders by designer -->
        <div class="space-y-4 2xl:col-span-2">
            @if($totalArchivedCount === 0)
                <div class="ui-card">
                    <x-ui.empty icon="archive-restore" :title="__('No archived orders')" :description="$emptyHint" />
                </div>
            @else
                @foreach($designerStats as $ds)
                    @if($ds['count'] > 0)
                        <section class="ui-card overflow-hidden" wire:key="arch-group-{{ $ds['designer']->id }}">
                            <div class="flex items-center justify-between border-b border-line px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <x-ui.person :person="$ds['designer']" size="sm" show-name />
                                    <span class="rounded-full bg-sunken px-2 py-0.5 text-meta tabular-nums text-ink-2">{{ $ds['count'] }}</span>
                                </div>
                                <span class="text-meta text-ink-3">{{ __('Avg. close:') }} {{ $ds['avg_days'] }}d · {{ __('Revisions:') }} {{ $ds['total_revisions'] }}</span>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="ui-table">
                                    <thead>
                                        <tr>
                                            <th>{{ __('WO') }}</th>
                                            <th>{{ __('Company') }}</th>
                                            <th>{{ __('Closing') }}</th>
                                            <th>{{ __('Outcome') }}</th>
                                            <th class="is-num">{{ __('Days') }}</th>
                                            <th class="is-num">{{ __('Rev.') }}</th>
                                            <th class="w-px"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($ds['orders'] as $order)
                                            @php
                                                $subVal = $order->substatus?->value ?? (is_string($order->substatus) ? $order->substatus : 'FINALIZADA !');
                                            @endphp
                                            <tr wire:key="arch-{{ $order->id }}" class="cursor-pointer" wire:click="$dispatch('open-order-detail', { orderId: {{ $order->id }} })">
                                                <td class="whitespace-nowrap">@if($order->wo_number)<x-wo-badge :number="$order->wo_number" />@else<span class="text-ink-3">—</span>@endif</td>
                                                <td class="min-w-0">
                                                    <div class="flex items-center gap-2">
                                                        <span class="block max-w-[14rem] truncate font-medium" title="{{ $order->company_name }}">{{ $order->company_name }}</span>
                                                        <x-orders.location :location="$order->location_text" class="shrink-0" />
                                                    </div>
                                                    <span class="block max-w-xs truncate text-meta text-ink-3">{{ $order->task_name }}</span>
                                                </td>
                                                <td class="whitespace-nowrap text-ink-2">{{ $order->archived_at ? $order->archived_at->translatedFormat('d M Y') : '—' }}</td>
                                                <td x-on:click.stop="" class="whitespace-nowrap">
                                                    <select wire:change="updateSubstatus({{ $order->id }}, $event.target.value)"
                                                            x-data x-on:pointerenter="fillSubstatusOptions($el)" x-on:focus="fillSubstatusOptions($el)"
                                                            data-current="{{ $subVal }}" class="ui-select h-7 w-44 text-meta" aria-label="{{ __('Outcome') }}">
                                                        <option value="{{ $subVal }}" selected>{{ $archivedSubstatuses->firstWhere('name', $subVal)?->label() ?? $subVal }}</option>
                                                    </select>
                                                </td>
                                                <td class="is-num text-ink-2">{{ $order->days_to_close ?? 0 }}</td>
                                                <td class="is-num text-ink-2">{{ $order->client_revision_count }}</td>
                                                <td x-on:click.stop="" class="text-right">
                                                    <button type="button" wire:click="restoreOrder({{ $order->id }})" wire:confirm="{{ __('Unarchive this order and return it to In Production?') }}"
                                                            class="ui-btn ui-btn-ghost ui-btn-icon ui-btn-sm" title="{{ __('Reopen / restore to In Production') }}" aria-label="{{ __('Reopen / restore to In Production') }}">
                                                        <x-lucide-rotate-ccw />
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @if($ds['orders']->hasPages())
                                <div class="border-t border-line px-4 py-2">{{ $ds['orders']->links(data: ['scrollTo' => false]) }}</div>
                            @endif
                        </section>
                    @endif
                @endforeach
            @endif
        </div>

        <!-- In production sidebar -->
        <div>
            <div x-data="{ isTarget: false }"
                 @dragover.prevent="isTarget = true"
                 @dragleave.prevent="isTarget = false"
                 @drop.prevent="
                    isTarget = false;
                    const orderId = event.dataTransfer.getData('text/plain');
                    if (orderId) { $wire.archiveOrder(orderId); }
                 "
                 :class="{ 'border-ink': isTarget }"
                 class="ui-card space-y-3 p-4 2xl:sticky 2xl:top-0">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="ui-section-title">{{ __('In Production') }}</h3>
                        <p class="text-meta text-ink-3">{{ __('Ready to archive and close') }}</p>
                    </div>
                    <span class="rounded-full bg-sunken px-2 py-0.5 text-meta tabular-nums text-ink-2">{{ $inProductionOrders->count() }}</span>
                </div>

                <div class="rounded-control border border-dashed border-line-strong px-3 py-3 text-center">
                    <p class="text-body font-medium text-ink-2">{{ __('Archive drop zone') }}</p>
                    <p class="text-meta text-ink-3">{{ __('Drag a card from below into this box to archive it right away.') }}</p>
                </div>

                <div class="max-h-[calc(100vh-380px)] space-y-2 overflow-y-auto custom-vertical-scrollbar">
                    @forelse($inProductionOrders->take($productionLimit) as $order)
                        <div wire:key="prod-{{ $order->id }}" draggable="true" @dragstart="event.dataTransfer.setData('text/plain', '{{ $order->id }}')"
                             class="cursor-grab rounded-control border border-line bg-surface p-3 transition-colors hover:border-line-strong active:cursor-grabbing">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    @if($order->wo_number)<x-wo-badge :number="$order->wo_number" />@endif
                                    <p class="mt-1 truncate text-body font-medium text-ink">{{ $order->company_name }}</p>
                                    <p class="truncate text-meta text-ink-3">{{ $order->task_name }}</p>
                                </div>
                                <button type="button" wire:click="archiveOrder({{ $order->id }})" class="ui-btn ui-btn-secondary ui-btn-sm" title="{{ __('Archive order') }}">{{ __('Archive') }}</button>
                            </div>
                            <div class="mt-2 flex items-center justify-between border-t border-line pt-2 text-meta text-ink-3">
                                <span>{{ $order->designer?->name ?? __('Unassigned') }}</span>
                                <span>{{ ($order->start_date ? \Illuminate\Support\Carbon::parse($order->start_date) : $order->created_at)->translatedFormat('d M') }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="rounded-control border border-dashed border-line p-6 text-center text-body text-ink-3">{{ __('No orders in production right now.') }}</p>
                    @endforelse
                    @if($inProductionOrders->count() > $productionLimit)
                        <button type="button" wire:click="showMoreProduction" class="ui-btn ui-btn-secondary ui-btn-sm w-full">{{ __('Show more') }}</button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <template id="archived-substatus-options">
        @foreach($archivedSubstatuses as $archSub)
            <option value="{{ $archSub->name }}">{{ $archSub->label() }}</option>
        @endforeach
    </template>

    @include('livewire.orders.partials.archive-modal', [
        'targetOrder' => $pendingArchiveOrder ?? null,
        'archivedSubstatuses' => $archivedSubstatuses ?? null,
    ])
</div>
