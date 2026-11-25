@php
    $columnLabel = match ($column) {
        \App\Enums\CoreStatus::EURALIZ_ORDERS_RECEIVED,
        \App\Enums\CoreStatus::ADRIAN_ORDERS_RECEIVED,
        \App\Enums\CoreStatus::CESAR_ORDERS_RECEIVED => __(':name · received', ['name' => $column->shortLabel()]),
        default => \Illuminate\Support\Str::ucfirst(\Illuminate\Support\Str::lower($column->label())),
    };
@endphp
<div
    x-data="{ isTarget: false }"
    @dragover.prevent="isTarget = true"
    @dragleave.prevent="isTarget = false"
    @drop.prevent="
        isTarget = false;
        const orderId = event.dataTransfer.getData('text/plain');
        if (orderId) {
            $wire.$parent.moveOrder(orderId, '{{ $column->value }}');
        }
    "
    :class="isTarget ? 'border-line-strong bg-line/50' : 'border-transparent bg-sunken'"
    class="shrink-0 w-80 border rounded-card flex flex-col h-full overflow-hidden transition duration-150">

    <!-- Column header -->
    <div class="px-3 pt-3 pb-2 flex items-center justify-between sticky top-0 z-10 shrink-0">
        <h3 class="text-body font-semibold text-ink truncate">
            {{ $columnLabel }}
            <span class="font-normal text-ink-3 ml-1">{{ $totalItemCount }}</span>
        </h3>
        <button
            type="button"
            @click="$dispatch('open-create-order', { initialStatus: '{{ $column->value }}' })"
            class="p-1 rounded-control text-ink-3 hover:text-ink hover:bg-surface transition shrink-0"
            title="{{ __('Add new order to') }} {{ $column->label() }}"
            aria-label="{{ __('Add new order to') }} {{ $column->label() }}">
            <x-lucide-plus class="w-4 h-4" />
        </button>
    </div>

    <!-- Column cards -->
    <div class="px-2.5 pb-2.5 overflow-y-auto flex-1 space-y-2.5 custom-vertical-scrollbar min-h-0">
        @php
            $urgentColumnOrders = $columnOrders->filter(fn($o) => $o->isUrgente());
            $regularColumnOrders = $columnOrders->filter(fn($o) => !$o->isUrgente());
        @endphp

        @if($columnOrders->isEmpty() && $columnTasks->isEmpty())
            <div class="py-10 text-center border border-dashed border-line-strong rounded-card">
                <span class="text-meta text-ink-3 block">{{ __('Drag a card here') }}</span>
            </div>
        @else
            @foreach($urgentColumnOrders as $order)
                @include('livewire.kanban.card', ['order' => $order])
            @endforeach

            @foreach($columnTasks as $task)
                <div
                    wire:key="task-card-{{ $task->id }}"
                    x-data="{ isCompleting: false, isRemoving: false }"
                    :class="{ 'opacity-0 scale-90 -translate-y-3 pointer-events-none transition-all duration-300 ease-out': isCompleting || isRemoving }"
                    @if($task->order) @click="$dispatch('open-order-detail', { orderId: {{ $task->order->id }} })" @endif
                    class="bg-surface border border-line hover:border-line-strong rounded-card p-3 flex flex-col gap-2 transition duration-150 relative select-none cursor-pointer">
                    <div class="flex items-center justify-between gap-1.5 min-w-0">
                        <div class="flex flex-wrap items-center gap-1 min-w-0">
                            <x-ui.tag icon="check-square">{{ __('Linked task') }}</x-ui.tag>
                            @if($task->type)
                                <span class="text-meta text-ink-3">{{ $task->type->label() }}</span>
                            @endif
                        </div>
                        <span class="text-meta text-ink-2 shrink-0 truncate max-w-[110px]">{{ $task->assignee?->name ?? __('Unassigned') }}</span>
                    </div>

                    <div class="min-w-0">
                        <h4 class="font-medium text-body text-ink break-words">{{ $task->title }}</h4>
                        @if($task->order)
                            <p class="text-meta text-ink-3 truncate mt-0.5 flex items-center gap-1.5">
                                @if($task->order->wo_number)
                                    <x-wo-badge :number="$task->order->wo_number" />
                                @endif
                                <span class="truncate">{{ $task->order->company_name }}</span>
                                <x-orders.location :location="$task->order->location_text" />
                            </p>
                        @endif
                    </div>

                    <div class="flex items-center justify-between gap-1.5 min-w-0" @click.stop>
                        <button
                            type="button"
                            @click="isCompleting = true; setTimeout(() => $wire.$parent.toggleTaskComplete({{ $task->id }}), 300)"
                            class="px-2.5 h-7 rounded-control text-meta font-medium bg-surface hover:bg-sunken text-ink border border-line transition flex items-center gap-1">
                            <x-lucide-check class="w-3 h-3" />
                            <span>{{ __('Complete') }}</span>
                        </button>

                        <flux:dropdown position="bottom" align="end">
                            <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" aria-label="{{ __('More actions') }}" />
                            <flux:menu>
                                <flux:menu.item
                                    icon="trash"
                                    variant="danger"
                                    wire:click="$parent.deleteTask({{ $task->id }})"
                                    wire:confirm="{{ __('Delete this linked task?') }}">{{ __('Delete task') }}</flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </div>
                </div>
            @endforeach

            @foreach($regularColumnOrders as $order)
                @include('livewire.kanban.card', ['order' => $order])
            @endforeach
        @endif

        @if($hiddenCount > 0)
            <button
                type="button"
                wire:click="showMore"
                wire:loading.attr="disabled"
                wire:target="showMore"
                class="w-full h-8 rounded-control border border-line bg-surface hover:bg-sunken text-meta font-medium text-ink-2 transition cursor-pointer">
                {{ __('Show :count more', ['count' => min($hiddenCount, $pageSize)]) }}
                <span class="text-ink-3">({{ $hiddenCount }})</span>
            </button>
        @endif
    </div>
</div>
