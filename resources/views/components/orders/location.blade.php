{{--
    Order location, always shown next to the client name. Most clients have several locations.
    The red pin is a fixed identifier, like the black WO tag; the text stays neutral.
    Usage: <x-orders.location :location="$order->location_text" />
--}}
@props([
    'location' => null,
])

@if(filled($location))
    <span {{ $attributes->class('inline-flex items-center gap-0.5 text-meta font-medium text-ink-2 whitespace-nowrap min-w-0') }} title="{{ __('Location') }}: {{ $location }}">
        <x-lucide-map-pin class="w-3.5 h-3.5 text-pin shrink-0" stroke-width="2.25" aria-hidden="true" />
        <span class="truncate">{{ $location }}</span>
    </span>
@endif
