{{-- A plannable card. Blocked cards are greyed, never draggable. Opening it hands off to the deliverable panel. --}}
@php
    $blocked = $deliverable->openBlock !== null;
    $canDrag = ($draggable ?? false) && ! $blocked;
    $client = \App\Support\TitleCase::from($deliverable->company_name ?: $deliverable->workOrder?->client?->name);
    $promised = ($showPromised ?? false) ? $deliverable->workOrder?->promised_date : null;
@endphp
<div
    wire:key="card-{{ $deliverable->id }}-{{ $blocked ? 'b' : 'o' }}"
    class="group relative rounded-control border border-line bg-surface px-2 py-1.5 text-left {{ $blocked ? 'opacity-50' : 'hover:border-line-strong' }} {{ $canDrag ? 'cursor-grab active:cursor-grabbing' : '' }}"
    @if($canDrag)
        draggable="true"
        @dragstart="start($event, {{ $deliverable->id }})"
    @endif
    @click="$dispatch('open-deliverable', { deliverableId: {{ $deliverable->id }} })"
    role="button"
    tabindex="0"
    @keydown.enter="$dispatch('open-deliverable', { deliverableId: {{ $deliverable->id }} })"
>
    <div class="flex items-center gap-1.5 min-w-0">
        <x-wo-badge :number="$deliverable->workOrder?->number ?: $deliverable->wo_number" />
        <span class="truncate text-body font-medium text-ink min-w-0" title="{{ $client }}">{{ $client }}</span>
        @if(($deliverable->carry_over_count ?? 0) > 0)
            <span class="ui-chip shrink-0" title="{{ __('Carried over from an earlier day') }}">{{ __('Carry-over') }}</span>
        @endif
    </div>
    <x-orders.location :location="\App\Support\TitleCase::from($deliverable->location_name)" class="max-w-full" />
    <p class="truncate text-body text-ink-2" title="{{ $deliverable->task_name }}">
        @if($blocked)
            {{ $blockedLabel ?? __('Blocked · can\'t be planned') }}
        @else
            {{ $deliverable->task_name }}
        @endif
    </p>
    @if($promised)
        <p class="text-meta text-ink-3">{{ __('Promised') }} {{ $promised->translatedFormat('j M') }}</p>
    @endif
    @if($removable ?? false)
        <button
            type="button"
            @click.stop="$wire.unplanDeliverable({{ $deliverable->id }})"
            class="absolute right-1 top-1 hidden rounded p-0.5 text-ink-3 hover:bg-sunken hover:text-ink group-hover:block"
            aria-label="{{ __('Remove from the plan') }}"
            title="{{ __('Remove from the plan') }}"
        ><x-lucide-x class="w-3.5 h-3.5" /></button>
    @endif
</div>
