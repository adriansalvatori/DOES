{{--
    Promised date with its health chip. Color carries health only: overdue is red, due soon is amber.
    Usage: <x-work-orders.promise :date="$workOrder->promised_date" :health="$row->health" />
--}}
@props([
    'date' => null,
    'health',
])

@if($date === null)
    <span class="text-ink-3">—</span>
@else
    @php
        $label = $date->translatedFormat('j M');
        $tone = match ($health->value) {
            'overdue' => 'danger',
            'due_soon' => 'warn',
            default => 'neutral',
        };
        $overdueDays = max(0, (int) $date->copy()->startOfDay()->diffInDays(now()->startOfDay()));
    @endphp
    <x-ui.tag :tone="$tone" class="tabular-nums">
        @if($health->value === 'overdue')
            {{ __('Overdue :days d', ['days' => $overdueDays]) }}
        @elseif($health->value === 'due_soon')
            {{ __('Due :date', ['date' => $label]) }}
        @else
            {{ $label }}
        @endif
    </x-ui.tag>
@endif
