{{-- A day (or Next week) heading: the date, today marked, and open over total cards across the people shown. --}}
@php
    $isNext = $column === \App\Application\Delivery\Data\PlanWeek::NEXT_WEEK;
    $date = $isNext ? $plan->nextWeek : \Carbon\CarbonImmutable::parse($column);
    $isToday = ! $isNext && $column === $today;
    $isPast = ! $isNext && $column < $today;
    $stacked ??= false;
@endphp
<div @class(['sticky top-0 z-10 bg-canvas px-1 pb-1' => ! $stacked, 'px-1 pt-2' => $stacked])>
    <p @class(['text-meta font-semibold', 'text-ink' => $isToday || $isNext, 'text-ink-3' => $isPast, 'text-ink-2' => ! $isToday && ! $isPast && ! $isNext])>
        @if($isNext)
            {{ __('Next week') }}
        @else
            {{ $date->translatedFormat('D j') }}
        @endif
        @if($isToday)
            <span class="ml-1 rounded bg-ink px-1 py-px text-[10px] font-bold uppercase tracking-wide text-white">{{ __('Today') }}</span>
        @endif
    </p>
    <p class="text-[11px] tabular-nums text-ink-3" title="{{ __('Open / total') }}">{{ $totals['open'] }} / {{ $totals['total'] }}</p>
</div>
