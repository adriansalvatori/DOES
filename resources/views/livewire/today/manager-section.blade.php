@php
    $data = $this->data;
    $counts = $data['counts'];
@endphp

<div class="space-y-4">
    <div>
        <h2 class="text-heading font-semibold text-ink">{{ __('Needs attention') }}</h2>
        <div class="mt-1 flex flex-wrap items-center gap-x-5 gap-y-1 text-body text-ink-2">
            <span @class(['text-danger' => $counts['overdue'] > 0])><b class="tabular-nums">{{ $counts['overdue'] }}</b> {{ __('overdue') }}</span>
            <span><b class="tabular-nums text-ink">{{ $data['dueToday']->count() }}</b> {{ __('due today') }}</span>
            <span @class(['text-danger' => $counts['urgent'] > 0])><b class="tabular-nums">{{ $counts['urgent'] }}</b> {{ __('urgent') }}</span>
            <span><b class="tabular-nums text-ink">{{ $counts['unassigned'] }}</b> {{ __('unassigned') }}</span>
            <a href="{{ route('resolver') }}" wire:navigate class="hover:underline"><b class="tabular-nums text-ink">{{ $counts['blocked'] + $counts['onHold'] }}</b> {{ __('blocked or on hold') }}</a>
        </div>
    </div>

    @foreach([
        ['key' => 'overdue', 'title' => __('Overdue'), 'tone' => 'danger'],
        ['key' => 'dueToday', 'title' => __('Due today'), 'tone' => 'warn'],
        ['key' => 'urgent', 'title' => __('Urgent'), 'tone' => 'danger'],
        ['key' => 'unassigned', 'title' => __('Unassigned'), 'tone' => 'neutral'],
    ] as $list)
        @if($data[$list['key']]->isNotEmpty())
            <section wire:key="manager-{{ $list['key'] }}">
                <h3 class="ui-section-title mb-2">{{ $list['title'] }}</h3>
                <ul class="ui-card divide-y divide-line">
                    @foreach($data[$list['key']] as $deliverable)
                        @include('livewire.today.partials.row', [
                            'deliverable' => $deliverable,
                            'showAssignee' => true,
                            'chip' => $deliverable->due_target ? $deliverable->due_target->translatedFormat('j M') : null,
                            'chipTone' => $list['tone'],
                        ])
                    @endforeach
                </ul>
            </section>
        @endif
    @endforeach

    <section>
        <h3 class="ui-section-title mb-2">{{ __('WIP per designer') }}</h3>
        <ul class="ui-card divide-y divide-line">
            @foreach($this->wip as $row)
                <li class="flex items-center justify-between gap-3 px-4 py-2.5" wire:key="wip-{{ $row['designer']->id }}">
                    <x-ui.person :person="$row['designer']" size="sm" show-name />
                    <span @class(['text-body tabular-nums', 'text-warn font-medium' => $row['limit'] > 0 && $row['used'] >= $row['limit'], 'text-ink-2' => ! ($row['limit'] > 0 && $row['used'] >= $row['limit'])])>{{ $row['used'] }} / {{ $row['limit'] }}</span>
                </li>
            @endforeach
        </ul>
    </section>
</div>
