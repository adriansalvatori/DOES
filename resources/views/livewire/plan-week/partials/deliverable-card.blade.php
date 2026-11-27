{{--
    A deliverable card: in the backlog (drag it onto a day to plan it; its tasks without a day follow), or planned on
    a day with no open task yet (dashed, with "Add task"). Blocked work is greyed and never draggable.
--}}
@php
    $blocked = $deliverable->openBlock !== null;
    $canDrag = ($draggable ?? false) && ! $blocked;
    $workOrder = $deliverable->workOrder;
    $client = \App\Support\TitleCase::from($workOrder?->displayName() ?: $deliverable->company_name);
    $location = \App\Support\TitleCase::from($workOrder?->clientLocation?->name);
    $health = \App\Application\Delivery\Data\PromiseHealth::for($workOrder?->promised_date, true, now());
    $label = trim(($workOrder?->number ?? '').' · '.$client.' · '.$deliverable->task_name, ' ·');
    $haystack = mb_strtolower(implode(' ', [$workOrder?->number, $deliverable->wo_number, $client, $deliverable->task_name]));
    $dim = ! $inBacklog && $term !== '' && ! str_contains($haystack, $term);
    $unscheduled = (int) ($deliverable->unscheduled_tasks_count ?? 0);
@endphp
<div wire:key="deliverable-{{ $inBacklog ? 'backlog' : 'planned' }}-{{ $deliverable->id }}-{{ $blocked ? 'b' : 'o' }}" data-deliverable-id="{{ $deliverable->id }}"
     @if($canDrag) draggable="true" x-on:dragstart="startDeliverable($event, {{ $deliverable->id }})" @endif
     x-on:click="$dispatch('open-deliverable', { deliverableId: {{ $deliverable->id }} })"
     x-on:keydown.enter="$dispatch('open-deliverable', { deliverableId: {{ $deliverable->id }} })"
     role="button" tabindex="0"
     @class([
         'group/card relative rounded-control border px-1.5 py-1 text-left',
         'border-line bg-surface hover:border-line-strong' => $inBacklog && ! $blocked,
         'border-dashed border-line-strong bg-surface' => ! $inBacklog,
         'opacity-50' => $blocked,
         'opacity-35' => $dim,
         'cursor-grab active:cursor-grabbing' => $canDrag,
         'cursor-pointer' => ! $canDrag,
     ])>
    <div class="flex min-w-0 items-center gap-1">
        <x-wo-badge :number="$workOrder?->number ?: $deliverable->wo_number" class="px-1! py-0! text-[10px]!" />
        <span class="truncate text-[11px] font-medium text-ink" title="{{ $client }}">{{ $client }}</span>
        @if($workOrder?->promised_date)
            <x-ui.tag class="ml-auto shrink-0" :tone="match ($health) { \App\Application\Delivery\Data\PromiseHealth::OVERDUE => 'danger', \App\Application\Delivery\Data\PromiseHealth::DUE_SOON => 'warn', default => 'neutral' }" title="{{ __('Promised date') }}">{{ $workOrder->promised_date->translatedFormat('j M') }}</x-ui.tag>
        @endif
    </div>
    @if(filled($location))
        <x-orders.location :location="$location" class="max-w-full truncate text-[11px]!" />
    @endif
    <div class="flex min-w-0 items-center gap-1">
        <p class="min-w-0 flex-1 truncate text-[11px] text-ink-2" title="{{ $deliverable->task_name }}">{{ $blocked ? __('Blocked · can\'t be planned') : $deliverable->task_name }}</p>
        <x-board.priority :priority="$deliverable->priority" class="[&_svg]:h-3.5 [&_svg]:w-3.5" />
        @if($deliverable->hasFlag(\App\Domain\Delivery\Enums\DeliverableFlag::URGENT))
            <x-ui.tag tone="danger">{{ __('Urgent') }}</x-ui.tag>
        @endif
    </div>
    @if($inBacklog && $unscheduled > 0)
        <p class="text-[10px] text-ink-3">{{ trans_choice('{1} 1 task to place|[2,*] :count tasks to place', $unscheduled, ['count' => $unscheduled]) }}</p>
    @endif
    @if(! $inBacklog && $this->canEditRow($person))
        <button type="button" data-no-box class="mt-1 flex items-center gap-1 rounded px-1 py-0.5 text-[11px] font-medium text-ink-2 hover:bg-sunken hover:text-ink"
                x-on:click.stop="openQuick('{{ $cellKey }}', {{ $person->id }}, '{{ $column }}', {{ $deliverable->id }}, @js($label))">
            <x-lucide-plus class="h-3 w-3" aria-hidden="true" /> {{ __('Add the first task') }}
        </button>
    @endif
    @if(! $inBacklog && $this->canEdit)
        <button type="button" class="absolute right-0.5 top-0.5 hidden rounded p-0.5 text-ink-3 hover:bg-sunken hover:text-ink group-hover/card:block"
                x-on:click.stop="$wire.unplanDeliverable({{ $deliverable->id }})" aria-label="{{ __('Remove from the plan') }}" title="{{ __('Remove from the plan') }}">
            <x-lucide-x class="h-3 w-3" />
        </button>
    @endif
</div>
