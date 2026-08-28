@props([
    'number' => null,
    'variant' => 'dark', // 'dark', 'light', 'outline', 'amber'
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
            'class' => 'font-mono inline-flex items-center gap-1 cursor-pointer transition select-none group/wobadge ' . (
                $variant === 'dark' ? 'px-1.5 py-0.5 rounded text-[9px] sm:text-[10px] font-bold bg-stone-900 hover:bg-black text-white shrink-0 whitespace-nowrap shadow-2xs' :
                ($variant === 'light' ? 'px-1.5 py-0.2 rounded text-[10px] font-bold bg-stone-200 hover:bg-stone-300 text-zinc-700 shrink-0 whitespace-nowrap' :
                ($variant === 'outline' ? 'px-1.5 py-0.2 rounded text-[10px] font-bold bg-stone-100 hover:bg-stone-200 border border-stone-200 text-zinc-700 shrink-0 whitespace-nowrap' :
                ($variant === 'amber' ? 'font-mono bg-white hover:bg-amber-100 px-1.5 py-0.5 rounded border border-amber-300 text-amber-950 font-bold shadow-2xs' :
                'px-1.5 py-0.5 rounded text-[10px] font-bold bg-stone-800 text-white shrink-0')))
            )
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
