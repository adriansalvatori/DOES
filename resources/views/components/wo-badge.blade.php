{{--
    The WO tag: the order's fixed identifier. Always black with bold white text, wherever a work order appears.
    `variant` is accepted for backwards compatibility but every variant renders the same black tag.
--}}
@props([
    'number' => null,
    'variant' => 'dark',
    'prefix' => '',
    'showCopyIcon' => false,
])

@php
    $rawNumber = trim((string)($number ?? ''));
    // Clean numeric part for title/tooltip
    $cleanNumber = preg_replace('/^(wo|#)[\s#\-:]*/i', '', $rawNumber);
@endphp

@if($rawNumber !== '')
    <button
        type="button"
        x-data="{ copied: false }"
        @click.stop.prevent="
            window.copyWoToClipboard('{{ addslashes($rawNumber) }}', $event);
            copied = true;
            setTimeout(() => copied = false, 2000);
        "
        title="{{ __('Clic para copiar WO ') . ($cleanNumber ?: $rawNumber) }}"
        {{ $attributes->merge([
            'class' => 'font-sans tabular-nums inline-flex items-center gap-1 cursor-pointer transition select-none group/wobadge px-1.5 py-0.5 rounded text-xs leading-4 font-bold bg-wo hover:bg-black text-white shrink-0 whitespace-nowrap',
        ]) }}
    >
        <span>{{ $prefix }}{{ $rawNumber }}</span>
        @if($showCopyIcon)
            <template x-if="!copied">
                <x-lucide-copy class="w-2.5 h-2.5 text-current opacity-60 group-hover/wobadge:opacity-100 transition shrink-0 ml-0.5" />
            </template>
            <template x-if="copied">
                <x-lucide-check class="w-2.5 h-2.5 text-emerald-400 shrink-0 ml-0.5" />
            </template>
        @endif
    </button>
@endif
