{{-- Variables: $deliverable; optional $gate, $chip, $chipTone, $showAssignee. --}}
@php
    $gate ??= null;
    $chip ??= null;
    $chipTone ??= 'neutral';
    $showAssignee ??= false;
    $workOrder = $deliverable->workOrder;
    $company = \App\Support\TitleCase::from($workOrder?->displayName() ?: $deliverable->company_name);
    $location = \App\Support\TitleCase::from($workOrder?->clientLocation?->name);
@endphp

<li class="flex items-center gap-3 px-4 py-2.5" wire:key="row-{{ $deliverable->id }}">
    @if($gate)
        <input type="checkbox" wire:click="completeTask({{ $gate->id }})" @click.stop
            class="h-4 w-4 shrink-0 rounded border-line-strong cursor-pointer" aria-label="{{ __('Complete: :task', ['task' => $gate->title]) }}">
    @endif
    <div role="button" tabindex="0" x-on:click="$dispatch('open-deliverable', { deliverableId: {{ $deliverable->id }} })" x-on:keydown.enter="$dispatch('open-deliverable', { deliverableId: {{ $deliverable->id }} })" class="min-w-0 flex-1 cursor-pointer text-left">
        <span class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-0.5">
            @if($workOrder?->number)<x-wo-badge :number="$workOrder->number" />@endif
            <span class="truncate text-row font-medium text-ink">{{ $company }}</span>
            <x-orders.location :location="$location" />
            @if($chip)<x-ui.tag :tone="$chipTone">{{ $chip }}</x-ui.tag>@endif
        </span>
        <span class="block truncate text-body text-ink-2">{{ $deliverable->task_name }}</span>
    </div>
    @if($showAssignee && $deliverable->assignee)
        <x-ui.person :person="$deliverable->assignee" size="sm" show-name class="shrink-0" />
    @endif
    @if($gate?->advances_to)
        <span class="shrink-0 text-meta text-ink-3">{{ __('→ :status', ['status' => $gate->advances_to->label()]) }}</span>
    @endif
</li>
