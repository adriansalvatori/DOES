{{-- One day of one designer: its own drop target, so a drop only changes this element. --}}
@php($editable = $this->canEdit)
<div
    wire:key="cell-{{ $assignee->id }}-{{ $date }}"
    class="min-h-20 rounded-control border border-line bg-surface p-1 space-y-1"
    data-cell="{{ $assignee->id }}:{{ $date }}"
    @if($editable)
        @dragover.prevent="overCell = '{{ $assignee->id }}:{{ $date }}'"
        @dragleave="if (overCell === '{{ $assignee->id }}:{{ $date }}') { overCell = null }"
        @drop.prevent.stop="dropOnCell({{ $assignee->id }}, '{{ $date }}')"
        :class="overCell === '{{ $assignee->id }}:{{ $date }}' ? 'bg-sunken border-line-strong' : ''"
    @endif
>
    <p class="px-0.5 text-meta tabular-nums {{ $cell['used'] > $limit ? 'text-danger' : ($cell['used'] >= $limit && $limit > 0 ? 'text-warn' : 'text-ink-3') }}">{{ $cell['used'] }}/{{ $limit }}</p>
    @foreach($cell['cards'] as $deliverable)
        @include('livewire.plan-week.partials.card', ['deliverable' => $deliverable, 'draggable' => $editable, 'removable' => $editable, 'blockedLabel' => __('Blocked · not counted')])
    @endforeach
</div>
