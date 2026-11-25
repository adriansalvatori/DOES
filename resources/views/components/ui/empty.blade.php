{{--
    Empty state: an icon, one line, an optional hint and an optional single action in the slot.
    Usage: <x-ui.empty icon="inbox" :title="__('No hay órdenes atrasadas')" />
--}}
@props([
    'icon' => 'inbox',
    'title',
    'description' => null,
])

<div {{ $attributes->class('flex flex-col items-center justify-center text-center gap-1.5 py-10 px-4') }}>
    {{ svg('lucide-' . $icon, 'w-5 h-5 text-ink-3 mb-1', ['aria-hidden' => 'true']) }}
    <p class="text-body font-medium text-ink">{{ $title }}</p>
    @if(filled($description))
        <p class="text-meta text-ink-3 max-w-xs">{{ $description }}</p>
    @endif
    @if($slot->isNotEmpty())
        <div class="mt-2">{{ $slot }}</div>
    @endif
</div>
