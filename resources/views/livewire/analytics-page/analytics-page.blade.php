@php
    $maxCycle = max(array_map(fn ($row) => $row['days'], $cycleTimes) ?: [0]) ?: 1;
    $maxThroughput = max(array_map(fn ($week) => $week['count'], $throughput) ?: [0]) ?: 1;
    $maxReason = max(array_map(fn ($row) => $row['count'], $closed['reasons']) ?: [0]) ?: 1;
@endphp
<div class="h-full flex flex-col gap-6 min-h-0 overflow-y-auto custom-vertical-scrollbar pr-1 pb-6">
    <x-ui.page-header id="tour-analytics-header" :title="__('Analytics')" :subtitle="__('Flow of the last :days days.', ['days' => $windowDays])" />

    {{-- Closed work KPIs (from the old Archived page) --}}
    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="ui-card p-4">
            <p class="text-meta text-ink-3">{{ __('Closed deliverables') }}</p>
            <p class="mt-1 text-title font-semibold tabular-nums text-ink">{{ number_format($closed['total']) }}</p>
        </div>
        <div class="ui-card p-4">
            <p class="text-meta text-ink-3">{{ __('Average days to close') }}</p>
            <p class="mt-1 text-title font-semibold tabular-nums text-ink">{{ $closed['average_days'] }}</p>
        </div>
        <div class="ui-card p-4">
            <p class="text-meta text-ink-3">{{ __('Top designer') }}</p>
            @if($closed['top_designer'])
                <p class="mt-1 truncate text-title font-semibold text-ink">{{ $closed['top_designer']['name'] }}</p>
                <p class="text-meta tabular-nums text-ink-3">{{ trans_choice('{1} :count closed|[2,*] :count closed', $closed['top_designer']['count']) }}</p>
            @else
                <p class="mt-1 text-title font-semibold text-ink-3">—</p>
            @endif
        </div>
        <div class="ui-card p-4">
            <p class="text-meta text-ink-3">{{ __('Client wait (average)') }}</p>
            <p class="mt-1 text-title font-semibold tabular-nums text-ink">{{ __(':days d', ['days' => $clientWait['average_days']]) }}</p>
            <p class="text-meta tabular-nums text-ink-3">{{ trans_choice('{1} :count waiting now|[0,*] :count waiting now', $clientWait['waiting_now']) }}@if($clientWait['longest_waiting_days'] > 0) · {{ __('longest :days d', ['days' => $clientWait['longest_waiting_days']]) }}@endif</p>
        </div>
    </section>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Cycle time per column --}}
        <section class="ui-card space-y-3 p-5">
            <div>
                <h2 class="ui-section-title">{{ __('Cycle time per column') }}</h2>
                <p class="mt-0.5 text-body text-ink-3">{{ __('Average days a deliverable stays in each column.') }}</p>
            </div>
            <ul class="space-y-2">
                @foreach($cycleTimes as $row)
                    <li class="grid grid-cols-[9rem_1fr_4rem] items-center gap-3 text-body" wire:key="cycle-{{ $row['status']->value }}">
                        <span class="truncate text-ink-2" title="{{ $row['status']->label() }}">{{ $row['status']->label() }}</span>
                        <span class="h-2 overflow-hidden rounded-full bg-sunken"><span class="block h-full rounded-full bg-stone-600" style="width: {{ $row['days'] > 0 ? max(2, round($row['days'] / $maxCycle * 100)) : 0 }}%"></span></span>
                        <span class="text-right tabular-nums text-ink" title="{{ trans_choice('{1} :count stay|[0,*] :count stays', $row['stays']) }}">{{ $row['stays'] > 0 ? __(':days d', ['days' => $row['days']]) : '—' }}</span>
                    </li>
                @endforeach
            </ul>
        </section>

        {{-- Weekly throughput --}}
        <section class="ui-card space-y-3 p-5">
            <div>
                <h2 class="ui-section-title">{{ __('Weekly throughput') }}</h2>
                <p class="mt-0.5 text-body text-ink-3">{{ __('Deliverables closed per week.') }}</p>
            </div>
            <div class="flex h-48 items-end justify-around gap-2 border-b border-line">
                @foreach($throughput as $week)
                    <div class="flex h-full flex-1 flex-col items-center justify-end" wire:key="week-{{ $week['week_start'] }}">
                        <span class="mb-1 text-meta font-medium tabular-nums text-ink">{{ $week['count'] }}</span>
                        <div class="w-full max-w-10 rounded-t-control bg-stone-600" style="height: {{ $week['count'] > 0 ? max(4, round($week['count'] / $maxThroughput * 100)) : 1 }}%"></div>
                    </div>
                @endforeach
            </div>
            <div class="flex justify-around gap-2 text-meta text-ink-3">
                @foreach($throughput as $week)
                    <span class="flex-1 text-center tabular-nums">{{ \Illuminate\Support\Carbon::parse($week['week_start'])->locale(app()->getLocale())->translatedFormat('j M') }}</span>
                @endforeach
            </div>
        </section>

        {{-- WIP vs limit --}}
        <section class="ui-card space-y-3 p-5">
            <div>
                <h2 class="ui-section-title">{{ __('WIP vs limit per designer') }}</h2>
                <p class="mt-0.5 text-body text-ink-3">{{ __('In design now, not counting blocked cards.') }}</p>
            </div>
            @forelse($wip as $row)
                @php $over = $row['wip'] > $row['limit']; @endphp
                <div class="grid grid-cols-[9rem_1fr_4rem] items-center gap-3 text-body" wire:key="wip-{{ $row['user']->id }}">
                    <x-ui.person :person="$row['user']" size="sm" show-name />
                    <span class="h-2 overflow-hidden rounded-full bg-sunken"><span class="block h-full rounded-full {{ $over ? 'bg-danger' : 'bg-stone-600' }}" style="width: {{ $row['limit'] > 0 ? min(100, round($row['wip'] / $row['limit'] * 100)) : 0 }}%"></span></span>
                    <span class="text-right tabular-nums {{ $over ? 'font-medium text-danger' : 'text-ink' }}">{{ $row['wip'] }} / {{ $row['limit'] }}</span>
                </div>
            @empty
                <x-ui.empty icon="users" :title="__('No designers yet')" />
            @endforelse
        </section>

        {{-- Close reasons --}}
        <section class="ui-card space-y-3 p-5">
            <div>
                <h2 class="ui-section-title">{{ __('Close reasons') }}</h2>
                <p class="mt-0.5 text-body text-ink-3">{{ __('Why closed deliverables ended.') }}</p>
            </div>
            @forelse($closed['reasons'] as $row)
                <div class="grid grid-cols-[12rem_1fr_3rem] items-center gap-3 text-body" wire:key="reason-{{ $row['reason']->value }}">
                    <span class="truncate text-ink-2" title="{{ $row['reason']->label() }}">{{ $row['reason']->label() }}</span>
                    <span class="h-2 overflow-hidden rounded-full bg-sunken"><span class="block h-full rounded-full bg-stone-600" style="width: {{ max(2, round($row['count'] / $maxReason * 100)) }}%"></span></span>
                    <span class="text-right tabular-nums text-ink">{{ $row['count'] }}</span>
                </div>
            @empty
                <x-ui.empty icon="archive" :title="__('Nothing closed yet')" />
            @endforelse
        </section>
    </div>
</div>
