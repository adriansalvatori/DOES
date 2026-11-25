{{--
    Order stage without hue: a five-step phase meter in ink and gray, plus the status label.
    Usage: <x-orders.stage :status="$order->core_status" />
--}}
@props([
    'status' => null,
    'showLabel' => true,
])

@php
    $coreStatus = $status instanceof \App\Enums\CoreStatus
        ? $status
        : \App\Enums\CoreStatus::tryFrom(mb_strtoupper(trim((string) $status)));
    $phase = $coreStatus?->phase() ?? 0;
    $isArchived = $coreStatus === \App\Enums\CoreStatus::ARCHIVED;
@endphp

@if($coreStatus)
    <span {{ $attributes->class('inline-flex items-center gap-2 min-w-0') }} title="{{ $coreStatus->phaseLabel() }} · {{ $coreStatus->label() }}">
        <span class="inline-flex items-center gap-0.5 shrink-0" role="img" aria-label="{{ __('Fase :current de 5', ['current' => $phase]) }}">
            @for($step = 1; $step <= 5; $step++)
                <span class="w-2.5 h-1.5 rounded-xs {{ $step <= $phase ? ($isArchived ? 'bg-ink-3' : 'bg-ink') : 'bg-line-strong' }}"></span>
            @endfor
        </span>
        @if($showLabel)
            <span class="text-body text-ink truncate">{{ $coreStatus->label() }}</span>
        @endif
    </span>
@endif
