<div class="h-full flex flex-col gap-4 min-h-0" x-data="{ installationTypes: @js($this->installationTypes) }">
    <x-ui.page-header :title="__('Overview')" :count="$this->rows->total()">
        <x-slot:toolbar>
            <div class="flex flex-wrap items-center gap-1 rounded-control bg-sunken p-0.5" role="tablist">
                @foreach([
                    'all' => __('All'),
                    'active' => __('Active'),
                    'production' => __('Production'),
                    'done' => __('Done'),
                    'backlog' => __('Backlog'),
                    'urgent' => __('Urgent'),
                    'with_due_date' => __('With due date'),
                ] as $key => $label)
                    <button type="button" role="tab" wire:click="setTab('{{ $key }}')" aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                        @class(['ui-btn ui-btn-sm', 'ui-btn-secondary' => $tab === $key, 'ui-btn-ghost' => $tab !== $key])>{{ $label }}</button>
                @endforeach
            </div>
        </x-slot:toolbar>
    </x-ui.page-header>

    <div class="flex flex-wrap items-center gap-2">
        <div class="relative w-64">
            <x-lucide-search class="pointer-events-none absolute left-2.5 top-2 h-4 w-4 text-ink-3" />
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search all...') }}" class="ui-input pl-8">
        </div>
        <input type="text" wire:model.live.debounce.300ms="workOrderNumber" placeholder="{{ __('WO filter (e.g. 1234)') }}" class="ui-input w-44">
        <x-lists.combo-filter model="clientId" :value="$clientId ?? 'all'" :all-label="__('All clients')" :options="$this->clientOptions" :search-placeholder="__('Search company...')" />
        <x-lists.combo-filter model="assigneeId" :value="$assigneeId ?? 'all'" :all-label="__('All Designers')" :options="$this->designerOptions" :search-placeholder="__('Search designer...')" />
        <select wire:model.live="installation" class="ui-select w-44" aria-label="{{ __('Installation') }}">
            <option value="">{{ __('Any installation') }}</option>
            <option value="NONE">{{ __('Empty (no info)') }}</option>
            @foreach($this->installationTypes as $type)
                <option value="{{ $type }}">{{ $type }}</option>
            @endforeach
        </select>
        @if(filled($search) || filled($workOrderNumber) || $clientId || $assigneeId || filled($installation))
            <button type="button" wire:click="resetFilters" class="ui-btn ui-btn-ghost ui-btn-sm">{{ __('Clear') }}</button>
        @endif
    </div>

    <div class="ui-card min-h-0 flex-1 overflow-auto custom-vertical-scrollbar">
        <table class="w-full border-collapse text-left">
            <thead class="sticky top-0 z-20 bg-stone-50 shadow-2xs">
                <tr class="text-meta font-medium text-ink-3">
                    <th class="w-10 px-2 py-2"><span class="sr-only">{{ __('Show deliverables') }}</span></th>
                    <th class="px-2 py-2" title="{{ __('Processed in production') }}">{{ __('Processed') }}</th>
                    <th class="px-2 py-2" title="{{ __('Delivery due date') }}">{{ __('Due date') }}</th>
                    <th class="px-2 py-2">{{ __('WO #') }}</th>
                    <th class="px-2 py-2">{{ __('Client') }}</th>
                    <th class="px-2 py-2">{{ __('Order name') }}</th>
                    <th class="px-2 py-2">{{ __('Designer') }}</th>
                    <th class="px-2 py-2" title="{{ __('Production / installation note') }}">{{ __('Prod./inst. note') }}</th>
                    <th class="px-2 py-2" title="{{ __('Estimate / Invoice') }}">{{ __('Est. / Inv.') }}</th>
                    <th class="px-2 py-2" title="{{ __('Email date') }}">{{ __('Email') }}</th>
                    <th class="px-2 py-2">{{ __('Installation') }}</th>
                    <th class="px-2 py-2" title="{{ __('Delivery note') }}">{{ __('Delivery') }}</th>
                    <th class="px-2 py-2">{{ __('Status') }}</th>
                    <th class="px-2 py-2 text-center">{{ __('Checked') }}</th>
                </tr>
            </thead>
            @forelse($this->rows as $row)
                <livewire:overview.page.overview-row-line :work-order-id="$row->workOrder->id" :tab="$tab" :row="$row" :key="'wo-'.$row->workOrder->id" />
            @empty
                <tbody><tr><td colspan="14"><x-ui.empty icon="inbox" :title="__('No orders found for this view.')" /></td></tr></tbody>
            @endforelse
        </table>
    </div>

    <div class="shrink-0">{{ $this->rows->links() }}</div>
</div>
