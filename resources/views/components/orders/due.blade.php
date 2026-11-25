{{--
    Due date as a short relative label, toned by health:
    "Atrasada 2 d" (danger) · "Hoy" (warn) · "Mañana" · "vie 22" (this week) · "22 nov".
    A completed order shows its date without a tone.
    Usage: <x-orders.due :date="$order->due_date" :done="$order->done_today" />
--}}
@props([
    'date' => null,
    'done' => false,
])

@php
    $due = $date ? \Illuminate\Support\Carbon::parse($date)->startOfDay() : null;
    $label = null;
    $tone = 'neutral';

    if ($due) {
        $today = \Illuminate\Support\Carbon::today();
        $daysUntil = (int) $today->diffInDays($due, false);
        $localized = $due->copy()->locale(app()->getLocale());

        if ($done) {
            $label = str_replace('.', '', $localized->translatedFormat('j M'));
        } elseif ($daysUntil < 0) {
            $label = __('Atrasada :count d', ['count' => abs($daysUntil)]);
            $tone = 'danger';
        } elseif ($daysUntil === 0) {
            $label = __('Hoy');
            $tone = 'warn';
        } elseif ($daysUntil === 1) {
            $label = __('Mañana');
        } elseif ($daysUntil < 7) {
            $label = str_replace('.', '', $localized->translatedFormat('D j'));
        } else {
            $label = str_replace('.', '', $localized->translatedFormat('j M'));
        }
    }
@endphp

@if($label)
    <x-ui.tag :tone="$tone" {{ $attributes->merge(['title' => __('Fecha Límite') . ': ' . $due->format('d/m/Y')]) }}>{{ $label }}</x-ui.tag>
@endif
