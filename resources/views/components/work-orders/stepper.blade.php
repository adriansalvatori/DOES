{{--
    Mini status stepper: one segment per delivery status, a gap between design, production and fulfillment.
    Filled segments are behind the current status; the current one is dark.
    Usage: <x-work-orders.stepper :status="$deliverable->status" />
--}}
@props([
    'status',
])

@php
    $statuses = \App\Domain\Delivery\Enums\DeliverableStatus::cases();
    $current = $status?->position() ?? 1;
    $done = $status === \App\Domain\Delivery\Enums\DeliverableStatus::DONE;
    $lastArea = null;
@endphp

<span {{ $attributes->class('inline-flex items-center') }} role="img" aria-label="{{ $status?->label() }}" title="{{ $status?->label() }}">
    @foreach($statuses as $each)
        @if($lastArea !== null && $lastArea !== $each->area())
            <span class="w-1.5"></span>
        @endif
        @php
            $lastArea = $each->area();
        @endphp
        <i @class([
            'block h-1.5 w-2.5 mr-px rounded-[1px]',
            'bg-ink-3' => $done || $each->position() < $current,
            'bg-ink' => ! $done && $each->position() === $current,
            'bg-line-strong' => ! $done && $each->position() > $current,
        ])></i>
    @endforeach
</span>
