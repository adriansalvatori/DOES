{{--
    Status tag. Color carries health only:
    neutral (default) · danger (overdue, urgent) · warn (due today, blocked, missing info)
    info (waiting on Camila or the client) · ok (done, approved).
    Usage: <x-ui.tag tone="danger" icon="alert-circle">{{ __('Overdue') }}</x-ui.tag>
--}}
@props([
    'tone' => 'neutral',
    'icon' => null,
])

@php
    $toneClasses = match ($tone) {
        'danger' => 'bg-danger-soft text-danger',
        'warn' => 'bg-warn-soft text-warn',
        'info' => 'bg-info-soft text-info',
        'ok' => 'bg-ok-soft text-ok',
        default => 'bg-sunken text-ink-2',
    };
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-1 h-5.5 px-2 rounded-full text-meta font-medium whitespace-nowrap', $toneClasses]) }} data-tone="{{ $tone }}">
    @if($icon)
        @php
            // Accept "check" or "lucide-check"; an unknown icon is skipped rather than breaking the page.
            try {
                $iconSvg = svg('lucide-' . preg_replace('/^lucide-/', '', $icon), 'w-3.5 h-3.5 shrink-0', ['aria-hidden' => 'true'])->toHtml();
            } catch (\BladeUI\Icons\Exceptions\SvgNotFound) {
                $iconSvg = '';
            }
        @endphp
        {!! $iconSvg !!}
    @endif
    {{ $slot }}
</span>
