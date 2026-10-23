@props([
    'title',
])

<div {{ $attributes->merge(['class' => 'flex items-center justify-between gap-2 flex-wrap min-h-[32px] select-none']) }}>
    <div class="flex items-center gap-2">
        <h4 class="font-bold text-sm text-zinc-900 uppercase tracking-wider flex items-center gap-2">
            @if(isset($icon))
                {{ $icon }}
            @endif
            <span>{{ $title }}</span>
        </h4>
        @if(isset($badge))
            {{ $badge }}
        @endif
    </div>

    @if(isset($actions))
        <div class="flex items-center gap-2 shrink-0">
            {{ $actions }}
        </div>
    @endif
</div>
