{{--
    One board card. Color means health only: danger/warn on the due chip, warn on blocked, info on hold.
    The "..." button opens the board's single shared menu (<x-board.card-menu>).
    Usage: <x-board.card :card="$deliverable" :colors="$designerColors" />
--}}
@props(['card', 'colors' => []])

@php
    $workOrder = $card->workOrder;
    $block = $card->openBlock;
    $hold = $card->openHold;
    $isDone = $card->status === \App\Domain\Delivery\Enums\DeliverableStatus::DONE;
    $company = \App\Support\TitleCase::from($card->company_name ?: $workOrder?->client?->name);
    $location = \App\Support\TitleCase::from($workOrder?->clientLocation?->name);
    $stuck = $block !== null || $hold !== null;
    $canProduce = ! $stuck && \Illuminate\Support\Facades\Gate::allows('moveInProduction', $card);
    $action = match (true) {
        ! $canProduce => null,
        $card->status === \App\Domain\Delivery\Enums\DeliverableStatus::READY_FOR_PRODUCTION => ['pullToProduction', __('Pull')],
        $card->status === \App\Domain\Delivery\Enums\DeliverableStatus::PRODUCTION_QUEUE => ['startProduction', __('Start')],
        $card->status === \App\Domain\Delivery\Enums\DeliverableStatus::IN_PRODUCTION => ['markProduced', __('Mark produced')],
        default => null,
    };
    $canReceive = ! $stuck && $card->status === \App\Domain\Delivery\Enums\DeliverableStatus::FULFILLMENT && \Illuminate\Support\Facades\Gate::allows('logReceipt', $card);
@endphp

<div
    {{ $attributes->class(['group bg-surface border border-line rounded-card p-3 flex flex-col gap-1.5 cursor-pointer select-none transition hover:border-line-strong hover:shadow-menu active:cursor-grabbing focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold']) }}
    tabindex="0"
    draggable="true"
    @dragstart="$event.dataTransfer.setData('text/plain', '{{ $card->id }}')"
    @click="$dispatch('open-deliverable', { deliverableId: {{ $card->id }} })"
    @keydown.enter.self="$dispatch('open-deliverable', { deliverableId: {{ $card->id }} })"
>
    <div class="flex items-center gap-1.5 min-w-0">
        <x-wo-badge :number="$workOrder?->number" />
        <span class="flex-1"></span>
        @if($card->assignee)
            <x-ui.person :person="$card->assignee" :color="$colors[$card->assignee_id] ?? null" size="sm" />
        @endif
        <button type="button"
                @click.stop="$dispatch('board-card-menu', { anchor: $el, id: {{ $card->id }}, status: '{{ $card->status->value }}', blocked: {{ $block ? 'true' : 'false' }}, onHold: {{ $hold ? 'true' : 'false' }}, workOrderId: {{ (int) $card->work_order_id }} })"
                class="p-1 -mr-1 rounded-control text-ink-3 hover:text-ink hover:bg-sunken transition shrink-0"
                aria-label="{{ __('More actions') }}" aria-haspopup="menu">
            <x-lucide-ellipsis class="w-4 h-4" aria-hidden="true" />
        </button>
    </div>

    <div class="flex items-center gap-1.5 min-w-0 flex-wrap">
        <span class="text-body font-medium text-ink truncate">{{ $company }}</span>
        <x-orders.location :location="$location" />
    </div>

    <p class="text-body text-ink-2 break-words">{{ $card->task_name }}</p>

    @php
        $hasChips = $card->flag_codes?->isNotEmpty() || $block || $hold || $card->due_target || $card->planned_for?->isToday();
    @endphp
    @if($hasChips)
        <div class="flex flex-wrap items-center gap-1">
            @foreach($card->flag_codes ?? [] as $flag)
                <x-ui.tag :tone="$flag === \App\Domain\Delivery\Enums\DeliverableFlag::URGENT ? 'danger' : 'neutral'">{{ $flag->label() }}</x-ui.tag>
            @endforeach
            @if($block)
                <x-ui.tag tone="warn" class="max-w-full truncate">{{ __('Blocked · :reason', ['reason' => $block->reason->label()]) }}</x-ui.tag>
            @endif
            @if($hold)
                <x-ui.tag tone="info" class="max-w-full truncate">{{ __('On hold · :reason', ['reason' => $hold->reason->label()]) }}</x-ui.tag>
            @endif
            @if($card->due_target)
                <x-orders.due :date="$card->due_target" :done="$isDone" />
            @endif
            @if($card->planned_for?->isToday() && ! $isDone)
                <x-ui.tag icon="calendar-check">{{ __('Planned today') }}</x-ui.tag>
            @endif
        </div>
    @endif

    @if($action)
        <button type="button" class="ui-btn ui-btn-secondary ui-btn-sm self-start" @click.stop="$wire.$parent.{{ $action[0] }}({{ $card->id }})">{{ $action[1] }}</button>
    @elseif($canReceive)
        <button type="button" class="ui-btn ui-btn-secondary ui-btn-sm self-start" @click.stop="$dispatch('board-receive', { scope: 'deliverable', id: {{ $card->id }}, count: 1 })">{{ __('Confirm received') }}</button>
    @endif
</div>
