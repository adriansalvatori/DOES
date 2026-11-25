@php
    $data = $this->data;
    $gates = $this->nextGates;
    $wipFull = $data['wip']['limit'] > 0 && $data['wip']['used'] >= $data['wip']['limit'];
    $overdueDays = fn ($deliverable): int => max(1, (int) $deliverable->due_target->startOfDay()->diffInDays(now()->startOfDay()));
@endphp

<div class="space-y-4" wire:key="designer-{{ $designerId }}">
    <div class="flex flex-wrap items-center gap-x-5 gap-y-1 text-body text-ink-2">
        <span><b class="tabular-nums text-ink">{{ $data['plannedToday']->count() }}</b> {{ __('planned') }}</span>
        <span><b class="tabular-nums text-ink">{{ $data['carryOvers']->count() }}</b> {{ __('carried over') }}</span>
        <span @class(['text-danger' => $data['overdue']->isNotEmpty()])><b class="tabular-nums">{{ $data['overdue']->count() }}</b> {{ __('overdue') }}</span>
        <span><b class="tabular-nums text-ink">{{ $data['alerts']->count() }}</b> {{ __('follow-ups') }}</span>
        <span @class(['text-warn' => $wipFull])>{{ __('WIP') }} <b class="tabular-nums">{{ $data['wip']['used'] }} / {{ $data['wip']['limit'] }}</b></span>
    </div>

    @if($undoTaskId)
        <div wire:key="undo-{{ $undoTaskId }}" x-data x-init="setTimeout(() => $wire.dismissUndo(), 8000)" role="status"
            class="flex items-center justify-between gap-3 rounded-control border border-line bg-surface px-3 py-2 text-body text-ink">
            <span class="flex items-center gap-2"><x-lucide-check-circle-2 class="h-4 w-4 text-ok" />{{ $undoMessage }}</span>
            <button type="button" wire:click="undoTask" class="ui-btn ui-btn-secondary ui-btn-sm">{{ __('Undo') }}</button>
        </div>
    @endif

    @if($data['plannedToday']->isEmpty() && $data['carryOvers']->isEmpty() && $data['overdue']->isEmpty() && $data['alerts']->isEmpty())
        <div class="ui-card"><x-ui.empty icon="check-circle-2" :title="__('Nothing planned for today')" :description="__('Plan your week or pull work from the Board.')" /></div>
    @endif

    @foreach([
        ['key' => 'plannedToday', 'title' => __('Planned for today'), 'chip' => null, 'tone' => 'neutral'],
        ['key' => 'carryOvers', 'title' => __('Carried over'), 'chip' => __('Carried over'), 'tone' => 'warn'],
        ['key' => 'overdue', 'title' => __('Overdue'), 'chip' => null, 'tone' => 'danger'],
    ] as $list)
        @if($data[$list['key']]->isNotEmpty())
            <section>
                <h2 class="ui-section-title mb-2">{{ $list['title'] }}</h2>
                <ul class="ui-card divide-y divide-line">
                    @foreach($data[$list['key']] as $deliverable)
                        @include('livewire.today.partials.row', [
                            'deliverable' => $deliverable,
                            'gate' => $gates->get($deliverable->id),
                            'chip' => match ($list['key']) { 'overdue' => __('Overdue :count d', ['count' => $overdueDays($deliverable)]), 'carryOvers' => __('Carried over'), default => null },
                            'chipTone' => $list['tone'],
                        ])
                    @endforeach
                </ul>
            </section>
        @endif
    @endforeach

    @if($data['alerts']->isNotEmpty())
        <section>
            <h2 class="ui-section-title mb-2">{{ __('Follow-ups') }}</h2>
            <ul class="ui-card divide-y divide-line">
                @foreach($data['alerts'] as $alert)
                    @php
                        $deliverable = $alert->deliverable;
                        $silentDays = match ($alert->kind) {
                            \App\Domain\Delivery\Enums\AlertKind::CLIENT_FOLLOW_UP_1 => 3,
                            \App\Domain\Delivery\Enums\AlertKind::CLIENT_FOLLOW_UP_2 => 6,
                            \App\Domain\Delivery\Enums\AlertKind::CLIENT_FOLLOW_UP_3 => 9,
                            default => null,
                        };
                    @endphp
                    <li class="flex flex-wrap items-center gap-3 px-4 py-2.5" wire:key="alert-{{ $alert->id }}">
                        <div role="button" tabindex="0" x-on:click="$dispatch('open-deliverable', { deliverableId: {{ $alert->deliverable_id }} })" x-on:keydown.enter="$dispatch('open-deliverable', { deliverableId: {{ $alert->deliverable_id }} })" class="min-w-0 flex-1 cursor-pointer text-left">
                            <span class="flex min-w-0 flex-wrap items-center gap-x-2">
                                <x-ui.tag tone="warn">{{ $silentDays ? __('Client silent :days days', ['days' => $silentDays]) : $alert->kind->label() }}</x-ui.tag>
                                @if($deliverable?->workOrder?->number)<x-wo-badge :number="$deliverable->workOrder->number" />@endif
                                <span class="truncate text-row font-medium text-ink">{{ \App\Support\TitleCase::from($deliverable?->workOrder?->displayName() ?: $deliverable?->company_name) }}</span>
                            </span>
                            <span class="block truncate text-body text-ink-2">{{ $deliverable?->task_name }}</span>
                        </div>
                        <button type="button" wire:click="logFollowUp({{ $alert->id }})" class="ui-btn ui-btn-secondary ui-btn-sm">{{ __('Log follow-up') }}</button>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
