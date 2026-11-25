{{--
    A person (designer, reviewer, user) as initials on their identity color.
    The color is the only place a person's hue appears; it never tints cards or rows.
    Usage: <x-ui.person :person="$order->primary_designer" show-name />
--}}
@props([
    'person' => null,
    'name' => null,
    'color' => null,
    'size' => 'md',
    'showName' => false,
])

@php
    $displayName = $name ?? $person?->name;
    $hex = $color ?? $person?->hex_color;
    $words = preg_split('/\s+/u', trim((string) $displayName), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $initials = mb_strtoupper(implode('', array_map(fn (string $word): string => mb_substr($word, 0, 1), array_slice($words, 0, 2))));

    $textColor = '#1f1f1d';
    if (is_string($hex) && preg_match('/^#?([0-9a-f]{6})$/i', $hex, $match)) {
        [$red, $green, $blue] = array_map(fn (string $pair): float => hexdec($pair) / 255, str_split($match[1], 2));
        $toLinear = fn (float $channel): float => $channel <= 0.03928 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
        $luminance = 0.2126 * $toLinear($red) + 0.7152 * $toLinear($green) + 0.0722 * $toLinear($blue);
        $textColor = $luminance > 0.4 ? '#1f1f1d' : '#ffffff';
        $hex = '#' . $match[1];
    } else {
        $hex = null;
    }

    $sizeClasses = match ($size) {
        'sm' => 'w-5.5 h-5.5 text-[10px]',
        'lg' => 'w-8 h-8 text-meta',
        default => 'w-6.5 h-6.5 text-[11px]',
    };
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-2 min-w-0']) }}>
    @if(filled($displayName))
        <span
            class="{{ $sizeClasses }} rounded-full inline-grid place-items-center font-semibold leading-none shrink-0 select-none ring-1 ring-inset ring-black/5 {{ $hex ? '' : 'bg-stone-200 text-ink-2' }}"
            @if($hex) style="background-color: {{ $hex }}; color: {{ $textColor }};" @endif
            title="{{ $displayName }}"
            @if($showName) aria-hidden="true" @else role="img" aria-label="{{ $displayName }}" @endif
        >{{ $initials }}</span>
        @if($showName)
            <span class="text-body text-ink truncate">{{ $displayName }}</span>
        @endif
    @else
        <span class="{{ $sizeClasses }} rounded-full inline-grid place-items-center border border-dashed border-line-strong text-ink-3 shrink-0" title="{{ __('Unassigned') }}" @if(! $showName) role="img" aria-label="{{ __('Unassigned') }}" @endif>
            <x-lucide-user class="w-3 h-3" aria-hidden="true" />
        </span>
        @if($showName)
            <span class="text-body text-ink-3 truncate">{{ __('Unassigned') }}</span>
        @endif
    @endif
</span>
