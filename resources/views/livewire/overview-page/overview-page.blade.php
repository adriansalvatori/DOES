@php
    $frozen = fn (string $column): string => \App\Livewire\Overview\OverviewPage::frozen($column);
    $rows = $this->rows;
    $counts = $this->tabCounts;
@endphp
<div class="h-full flex flex-col gap-3 min-h-0"
     x-data="{
        installationTypes: @js($this->installationTypes),
        designers: @js(collect($this->designerOptions)->map(fn ($name, $id) => ['id' => $id, 'name' => $name])->values()),
        expandAll: $wire.entangle('expandAll'),
        timer: null,
        init() {
            this.timer = setInterval(() => this.check(), 30000);
        },
        destroy() { clearInterval(this.timer); },
        check() {
            if (document.visibilityState !== 'visible') { return; }
            if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)) { return; }
            $wire.checkForChanges();
        },
        toggleExpandAll() {
            this.expandAll = ! this.expandAll;
            this.$dispatch('overview-expand');
        },
     }"
     x-on:focus.window.debounce.500ms="check()"
     x-on:keydown.window.meta.f.prevent="$refs.search.focus(); $refs.search.select()"
     x-on:keydown.window.ctrl.f.prevent="$refs.search.focus(); $refs.search.select()">
    <x-ui.page-header :title="__('Overview')" :count="$rows->total()">
        <x-slot:toolbar>
            <div class="flex flex-wrap items-center gap-1 rounded-control bg-sunken p-0.5" role="tablist">
                @foreach([
                    'all' => __('All'),
                    'active' => __('Active'),
                    'production' => __('Production'),
                    'done' => __('Done'),
                    'backlog' => __('Backlog'),
                ] as $key => $label)
                    <button type="button" role="tab" wire:click="setTab('{{ $key }}')" aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                        @class(['ui-btn ui-btn-sm gap-1.5', 'ui-btn-secondary' => $tab === $key, 'ui-btn-ghost' => $tab !== $key])>
                        {{ $label }}
                        <span class="tabular-nums text-ink-3">{{ number_format($counts[$key] ?? 0) }}</span>
                    </button>
                @endforeach
            </div>
        </x-slot:toolbar>
    </x-ui.page-header>

    <div class="flex flex-wrap items-center gap-2">
        <div class="relative w-64">
            <x-lucide-search class="pointer-events-none absolute left-2.5 top-2 h-4 w-4 text-ink-3" />
            <input type="text" x-ref="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search all...') }}" class="ui-input pl-8 pr-12">
            <span class="ui-kbd pointer-events-none absolute right-1.5 top-1.5 hidden lg:inline-flex">⌘F</span>
        </div>
        <input type="text" wire:model.live.debounce.300ms="workOrderNumber" placeholder="{{ __('WO filter (e.g. 1234)') }}" class="ui-input w-40">
        <x-lists.combo-filter model="clientId" :value="$clientId ?? 'all'" :all-label="__('All clients')" :options="$this->clientOptions" :search-placeholder="__('Search company...')" />
        <x-lists.combo-filter model="assigneeId" :value="$assigneeId ?? 'all'" :all-label="__('All Designers')" :options="$this->designerOptions" :search-placeholder="__('Search designer...')" />
        <select wire:model.live="installation" class="ui-select w-40" aria-label="{{ __('Installation') }}">
            <option value="">{{ __('Any installation') }}</option>
            <option value="NONE">{{ __('Empty (no info)') }}</option>
            @foreach($this->installationTypes as $type)
                <option value="{{ $type }}">{{ $type }}</option>
            @endforeach
        </select>
        <select wire:model.live="review" class="ui-select w-40" aria-label="{{ __('Estimate review') }}">
            <option value="">{{ __('Any review') }}</option>
            <option value="NONE">{{ __('Not reviewed') }}</option>
            <option value="CS">{{ __('Reviewed by :name', ['name' => 'CS']) }}</option>
            <option value="CAMILA">{{ __('Reviewed by :name', ['name' => 'Camila']) }}</option>
        </select>
        <button type="button" class="ui-chip" wire:click="$toggle('urgent')" aria-pressed="{{ $urgent ? 'true' : 'false' }}">{{ __('Urgent') }}</button>
        <button type="button" class="ui-chip" wire:click="$toggle('withDueDate')" aria-pressed="{{ $withDueDate ? 'true' : 'false' }}">{{ __('With due date') }}</button>
        <button type="button" class="ui-btn ui-btn-ghost ui-btn-sm ml-auto" x-on:click="toggleExpandAll()">
            <x-lucide-chevrons-up-down class="h-4 w-4" aria-hidden="true" />
            <span x-text="expandAll ? @js(__('Collapse all')) : @js(__('Expand all'))"></span>
        </button>
    </div>

    @if($this->activeFilters !== [])
        <div class="-mt-1 flex flex-wrap items-center gap-1.5">
            @foreach($this->activeFilters as $filter => $label)
                <button type="button" wire:click="clearFilter('{{ $filter }}')" class="inline-flex h-6 items-center gap-1 rounded-full bg-sunken pl-2.5 pr-1.5 text-meta text-ink hover:bg-line" title="{{ __('Remove filter') }}">
                    {{ $label }}
                    <x-lucide-x class="h-3 w-3 text-ink-3" aria-hidden="true" />
                </button>
            @endforeach
            <button type="button" wire:click="resetFilters" class="ui-btn ui-btn-ghost ui-btn-sm">{{ __('Clear') }}</button>
        </div>
    @endif

    <div class="ui-card min-h-0 flex-1 overflow-auto custom-vertical-scrollbar" data-overview-grid>
        <table class="ui-grid">
            <thead>
                <tr>
                    <th class="is-frozen" style="{{ $frozen('toggle') }}"><span class="sr-only">{{ __('Show deliverables') }}</span></th>
                    <th class="is-frozen" style="{{ $frozen('processed') }}" title="{{ __('Processed in production') }}">{{ __('Processed') }}</th>
                    <th class="is-frozen" style="{{ $frozen('due') }}" title="{{ __('Delivery due date') }}">{{ __('Due date') }}</th>
                    <th class="is-frozen" style="{{ $frozen('number') }}">{{ __('WO #') }}</th>
                    <th class="is-frozen" style="{{ $frozen('client') }}">{{ __('Client') }}</th>
                    <th class="is-frozen is-frozen-last" style="{{ $frozen('name') }}">{{ __('Order name') }}</th>
                    <th>{{ __('Designer') }}</th>
                    <th title="{{ __('Production / installation note') }}">{{ __('Prod./inst. note') }}</th>
                    <th title="{{ __('Estimate / Invoice') }}">{{ __('Est. / Inv.') }}</th>
                    <th title="{{ __('Email date') }}">{{ __('Email') }}</th>
                    <th>{{ __('Installation') }}</th>
                    <th class="text-center" title="{{ __('Checked') }}"><x-lucide-check class="mx-auto h-3.5 w-3.5" aria-hidden="true" /><span class="sr-only">{{ __('Checked') }}</span></th>
                    <th title="{{ __('Delivery note') }}">{{ __('Delivery') }}</th>
                    <th>{{ __('Status') }}</th>
                </tr>
            </thead>
            @forelse($rows as $row)
                <livewire:overview.page.overview-row-line :work-order-id="$row->workOrder->id" :tab="$tab" :row="$row" :key="'wo-'.$row->workOrder->id" />
            @empty
                <tbody><tr><td colspan="14" class="h-auto!"><x-ui.empty icon="inbox" :title="__('No orders found for this view.')" /></td></tr></tbody>
            @endforelse
        </table>

        @if($rows->total() > 0)
            <div class="sticky left-0 flex items-center gap-3 px-3 py-3 text-meta text-ink-3">
                <span>{{ __('Showing :shown of :total', ['shown' => number_format(count($rows->items())), 'total' => number_format($rows->total())]) }}</span>
                @if(count($rows->items()) < $rows->total())
                    <span wire:loading.remove wire:target="loadMore" x-intersect.margin.400px="$wire.loadMore()">{{ __('Scroll for more') }}</span>
                    <span wire:loading wire:target="loadMore">{{ __('Loading...') }}</span>
                @endif
            </div>
        @endif
    </div>
</div>
