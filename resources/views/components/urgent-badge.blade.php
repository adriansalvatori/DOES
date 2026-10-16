@props([
    'done' => false,
    'blocked' => false,
    'compact' => false,
])

@php
    $isDoneOrBlocked = $done || $blocked;
@endphp

@if($isDoneOrBlocked)
    <span {{ $attributes->merge(['class' => ($compact ? 'px-1.5 py-0.2 text-[8.5px]' : 'px-2 py-0.5 text-[9px]') . ' rounded-full font-bold uppercase tracking-wider bg-stone-200 text-stone-600 border border-stone-300 inline-flex items-center gap-1 shrink-0 opacity-80 select-none']) }} title="{{ __('Urgente') }}">
        @if($done)
            <x-lucide-check class="w-2.5 h-2.5 text-stone-500" />
        @endif
        <span>{{ __('URGENTE') }}</span>
    </span>
@else
    <span {{ $attributes->merge(['class' => ($compact ? 'px-1.5 py-0.2 text-[8.5px]' : 'px-2 py-0.5 text-[9px]') . ' rounded-full font-black uppercase tracking-wider bg-red-600 text-white shadow-2xs shadow-red-500/30 inline-flex items-center gap-1.5 shrink-0 select-none']) }} title="{{ __('Orden Urgente') }}">
        <span class="w-1.5 h-1.5 rounded-full bg-white animate-ping"></span>
        <span>{{ __('URGENTE') }}</span>
    </span>
@endif
