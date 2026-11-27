{{--
    Priority marker: chevrons up for high work, down for low. Neutral ink, because on cards color means health only.
    Medium, the default, shows nothing unless show-label is set.
    Usage: <x-board.priority :priority="$deliverable->priority" /> or with show-label in a details list.
--}}
@props(['priority' => null, 'showLabel' => false])

@php
    $priority = $priority instanceof \App\Domain\Delivery\Enums\Priority
        ? $priority
        : (\App\Domain\Delivery\Enums\Priority::tryFrom((string) $priority) ?? \App\Domain\Delivery\Enums\Priority::MEDIUM);
@endphp

@if($showLabel || $priority !== \App\Domain\Delivery\Enums\Priority::MEDIUM)
    <span {{ $attributes->class(['inline-flex items-center gap-1 text-meta shrink-0', 'text-ink font-semibold' => $priority->rank() > 3, 'text-ink-3' => $priority->rank() <= 3]) }} title="{{ __('Priority') }}: {{ $priority->label() }}">
        <x-dynamic-component :component="'lucide-'.$priority->icon()" class="w-4 h-4" stroke-width="2.25" aria-hidden="true" />
        @if($showLabel)
            <span>{{ $priority->label() }}</span>
        @else
            <span class="sr-only">{{ __('Priority') }}: {{ $priority->label() }}</span>
        @endif
    </span>
@endif
