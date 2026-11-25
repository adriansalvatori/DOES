@php
    $status = $this->statusEnum;
    $column = $this->column;
    $groupedByWorkOrder = $status === \App\Domain\Delivery\Enums\DeliverableStatus::FULFILLMENT;
@endphp
<div
    x-data="{ isTarget: false }"
    @dragover.prevent="isTarget = true"
    @dragleave.prevent="isTarget = false"
    @drop.prevent="isTarget = false; const id = $event.dataTransfer.getData('text/plain'); if (id) { $wire.$parent.move(Number(id), @js($status->value)); }"
    :class="isTarget ? 'border-line-strong bg-line/50' : 'border-transparent bg-sunken'"
    class="shrink-0 w-72 border rounded-card flex flex-col h-full overflow-hidden transition duration-150"
    data-board-column="{{ $status->value }}">

    <div class="px-3 pt-3 pb-2 flex items-center justify-between shrink-0">
        <h3 class="text-body font-semibold text-ink truncate">
            {{ $status->label() }}
            <span class="font-normal text-ink-3 ml-1 tabular-nums">{{ $column->total }}</span>
        </h3>
    </div>

    <div class="px-2.5 pb-2.5 overflow-y-auto flex-1 space-y-2.5 custom-vertical-scrollbar min-h-0">
        @if($column->cards->isEmpty())
            <div class="py-8 text-center border border-dashed border-line-strong rounded-card">
                <span class="text-meta text-ink-3 block">{{ __('Drag a card here') }}</span>
            </div>
        @elseif($groupedByWorkOrder)
            @foreach($this->workOrderGroups as $workOrderId => $group)
                @php($workOrder = $group->first()->workOrder)
                <section class="space-y-2" wire:key="wo-{{ $workOrderId }}">
                    <div class="flex items-center gap-1.5 min-w-0 px-0.5">
                        <x-wo-badge :number="$workOrder?->number" />
                        <span class="text-meta font-medium text-ink truncate">{{ \App\Support\TitleCase::from($workOrder?->client?->name ?? $group->first()->company_name) }}</span>
                        <button type="button" class="ui-btn ui-btn-secondary ui-btn-sm ml-auto"
                                @click="$dispatch('board-receive', { scope: 'work_order', id: {{ (int) $workOrderId }}, count: {{ $group->count() }} })">
                            {{ __('Confirm all received') }}
                        </button>
                    </div>
                    @foreach($group as $card)
                        <x-board.card :card="$card" :colors="$designerColors" wire:key="card-{{ $card->id }}" />
                    @endforeach
                </section>
            @endforeach
        @else
            @foreach($this->lanes as $lane)
                @if($groupBy === 'designer' && $status->area() === \App\Domain\Delivery\Enums\Area::DESIGN)
                    <div class="flex items-center gap-1.5 px-0.5 pt-1 text-meta font-medium text-ink-2 min-w-0">
                        <x-ui.person :person="$lane['assignee']" :color="$designerColors[$lane['assignee']?->id] ?? null" size="sm" />
                        <span class="truncate">{{ $lane['assignee']?->name ?? __('Unassigned') }}</span>
                        <span class="text-ink-3 tabular-nums">{{ $lane['cards']->count() }}</span>
                        @if($lane['wip'])
                            <span class="ml-auto tabular-nums {{ $lane['wip']['used'] >= $lane['wip']['limit'] ? 'text-danger' : 'text-ink-3' }}" title="{{ __('Work in progress') }}">{{ $lane['wip']['used'] }}/{{ $lane['wip']['limit'] }}</span>
                        @endif
                    </div>
                @endif
                @foreach($lane['cards'] as $card)
                    <x-board.card :card="$card" :colors="$designerColors" wire:key="card-{{ $card->id }}" />
                @endforeach
            @endforeach
        @endif

        @if($column->hiddenCount() > 0)
            <button type="button" wire:click="showMore" wire:loading.attr="disabled" wire:target="showMore"
                    class="w-full h-8 rounded-control border border-line bg-surface hover:bg-sunken text-meta font-medium text-ink-2 transition cursor-pointer">
                {{ __('Show :count more', ['count' => min($column->hiddenCount(), \App\Livewire\Board\Column::PAGE_SIZE)]) }}
                <span class="text-ink-3 tabular-nums">({{ $column->hiddenCount() }})</span>
            </button>
        @endif
    </div>
</div>
