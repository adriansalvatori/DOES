{{--
    Page header: every page starts with this. A 20px title, an optional count and one-line subtitle,
    an actions slot on the right and an optional toolbar row (search, filters, tabs) underneath.
    No card around it, no centered floating box.
    Usage:
    <x-ui.page-header :title="__('Clients')" :count="$total" :subtitle="__('…')">
        <x-slot:actions>…one primary button, others secondary…</x-slot:actions>
        <x-slot:toolbar>…search input, .ui-select filters, tabs…</x-slot:toolbar>
    </x-ui.page-header>
--}}
@props([
    'title',
    'subtitle' => null,
    'count' => null,
])

<header {{ $attributes->class('shrink-0') }}>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="min-w-0">
            <div class="flex items-center gap-2">
                <h1 class="text-title font-semibold tracking-tight text-ink text-balance">{{ $title }}</h1>
                @if(filled($count))
                    <span class="rounded-full bg-sunken px-2 py-0.5 text-meta font-medium tabular-nums text-ink-2">{{ is_numeric($count) ? number_format((float) $count) : $count }}</span>
                @endif
            </div>
            @if(filled($subtitle))
                <p class="text-body text-ink-3 mt-0.5">{{ $subtitle }}</p>
            @endif
        </div>
        @isset($actions)
            <div class="flex flex-wrap items-center gap-2 shrink-0">{{ $actions }}</div>
        @endisset
    </div>
    @isset($toolbar)
        <div class="mt-4 flex flex-wrap items-center gap-2">{{ $toolbar }}</div>
    @endisset
</header>
