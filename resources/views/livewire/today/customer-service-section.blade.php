@php
    $groups = $this->groups;
    $blocks = collect($groups)->sum(fn ($group) => collect($group->items)->where('type', 'block')->count());
    $holds = collect($groups)->sum(fn ($group) => collect($group->items)->where('type', 'hold')->count());
@endphp

<div class="space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-heading font-semibold text-ink">{{ __('Blocks and holds') }}</h2>
            <p class="text-body text-ink-3"><b class="tabular-nums">{{ $blocks }}</b> {{ __('blocked') }} · <b class="tabular-nums">{{ $holds }}</b> {{ __('on hold') }}</p>
        </div>
        <a href="{{ route('v2.resolver') }}" wire:navigate class="ui-btn ui-btn-secondary">{{ __('Open the Resolver') }}</a>
    </div>

    @forelse($groups as $group)
        <a href="{{ route('v2.resolver') }}" wire:navigate wire:key="cs-{{ $group->workOrder->id }}" class="ui-card flex flex-wrap items-center gap-2 px-4 py-2.5 hover:bg-sunken/40">
            <x-ui.tag :tone="$group->oldestAgeInDays >= 7 ? 'danger' : ($group->oldestAgeInDays >= 3 ? 'warn' : 'neutral')" class="tabular-nums">{{ __(':days d', ['days' => $group->oldestAgeInDays]) }}</x-ui.tag>
            <x-wo-badge :number="$group->workOrder->number" />
            <span class="truncate text-row font-medium text-ink">{{ \App\Support\TitleCase::from($group->workOrder->client?->name ?? $group->items[0]->deliverable->company_name) }}</span>
            <x-orders.location :location="\App\Support\TitleCase::from($group->workOrder->clientLocation?->name)" />
            <span class="flex-1"></span>
            <span class="text-meta text-ink-3">{{ trans_choice('{1} :count item|[2,*] :count items', count($group->items)) }}</span>
        </a>
    @empty
        <div class="ui-card"><x-ui.empty icon="check-circle-2" :title="__('Nothing blocked')" :description="__('Everything is moving.')" /></div>
    @endforelse
</div>
