<div class="h-full flex flex-col gap-4 min-h-0 overflow-y-auto custom-vertical-scrollbar pb-6">
    <x-ui.page-header :title="__('Work orders')" :subtitle="__('One row per work order, with its approvals and receipts.')">
        <x-slot:actions>
            @if($this->canMerge && count($selected) >= 2)
                <button type="button" class="ui-btn ui-btn-secondary" wire:click="mergeSelected" data-merge-selected>
                    <x-lucide-merge class="w-4 h-4" aria-hidden="true" /> {{ __('Merge :count work orders', ['count' => count($selected)]) }}
                </button>
            @endif
        </x-slot:actions>
        <x-slot:toolbar>
            <div class="flex items-center gap-1 rounded-control bg-sunken p-0.5" role="tablist">
                @foreach(\App\Application\Delivery\Data\WorkOrderTab::cases() as $each)
                    <button type="button" role="tab" wire:click="setTab('{{ $each->value }}')" aria-selected="{{ $tab === $each->value ? 'true' : 'false' }}"
                        @class(['ui-btn ui-btn-sm', 'ui-btn-secondary' => $tab === $each->value, 'ui-btn-ghost' => $tab !== $each->value])>
                        {{ match ($each->value) { 'production' => __('Production'), 'fulfillment' => __('Fulfillment'), 'done' => __('Done'), default => __('Active') } }} <span class="tabular-nums text-ink-3">{{ $this->counts[$each->value] }}</span>
                    </button>
                @endforeach
            </div>
            <div class="relative w-64">
                <x-lucide-search class="pointer-events-none absolute left-2.5 top-2 h-4 w-4 text-ink-3" aria-hidden="true" />
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search WO or client') }}" aria-label="{{ __('Search WO or client') }}" class="ui-input pl-8">
            </div>
            <select wire:model.live="clientId" class="ui-select w-auto max-w-56" aria-label="{{ __('All companies') }}">
                <option value="">{{ __('All companies') }}</option>
                @foreach($this->clients as $client)
                    <option value="{{ $client['id'] }}">{{ \App\Support\TitleCase::from($client['name']) }}</option>
                @endforeach
            </select>
        </x-slot:toolbar>
    </x-ui.page-header>

    <div class="ui-card overflow-x-auto">
        @if($this->rows->isEmpty())
            <x-ui.empty icon="clipboard-list" :title="__('No work orders here')" :description="__('Try another tab or clear the search.')" />
        @else
            <table class="ui-table w-full">
                <thead>
                    <tr>
                        @if($this->canMerge)
                            <th class="w-8"><span class="sr-only">{{ __('Select for merge') }}</span></th>
                        @endif
                        <th class="text-left">{{ __('WO') }}</th>
                        <th class="text-left">{{ __('Client') }}</th>
                        <th class="text-right">{{ __('Phases') }}</th>
                        <th class="text-right">{{ __('Approved') }}</th>
                        <th class="text-right">{{ __('Received') }}</th>
                        <th class="text-left">{{ __('Promised') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($this->rows as $row)
                        @php
                            $workOrder = $row->workOrder;
                        @endphp
                        <tr wire:key="wo-{{ $workOrder->id }}" class="cursor-pointer hover:bg-sunken"
                            x-on:click="Livewire.navigate('{{ route('work-orders.show', $workOrder) }}')">
                            @if($this->canMerge)
                                <td x-on:click.stop>
                                    <input type="checkbox" wire:model.live="selected" value="{{ $workOrder->id }}" class="rounded border-line" aria-label="{{ __('Select for merge') }}">
                                </td>
                            @endif
                            <td><x-wo-badge :number="$workOrder->number" /></td>
                            <td>
                                <a href="{{ route('work-orders.show', $workOrder) }}" wire:navigate x-on:click.stop class="text-row font-medium text-ink hover:underline">{{ \App\Support\TitleCase::from($workOrder->displayName()) ?: __('No client') }}</a>
                                <x-orders.location :location="\App\Support\TitleCase::from($workOrder->clientLocation?->name)" class="ml-2" />
                            </td>
                            <td class="text-right tabular-nums">{{ $workOrder->phases_count > 0 ? $workOrder->phases_count : '—' }}</td>
                            <td class="text-right tabular-nums">{{ $row->approvedCount }} / {{ $row->deliverablesCount }}</td>
                            <td class="text-right tabular-nums">{{ $row->receivedCount }} / {{ $row->deliverablesCount }}</td>
                            <td><x-work-orders.promise :date="$workOrder->promised_date" :health="$row->health" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if($this->rows->hasPages())
        <div>{{ $this->rows->links() }}</div>
    @endif
</div>
