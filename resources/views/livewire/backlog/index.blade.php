<div class="h-full flex flex-col gap-4 min-h-0">
    <x-ui.page-header :title="__('Backlog')" :count="$backlogTotalCount" :subtitle="trans_choice('{1} :count active in the workspace|[0,*] :count active in the workspace', $activeWorkspaceCount)">
        <x-slot:actions>
            {{-- Trello sync controls: removed with the Trello cutover. --}}
            <button type="button" wire:click="runTrelloSyncOnlyNew" wire:loading.attr="disabled" class="ui-btn ui-btn-secondary">
                <x-lucide-refresh-cw wire:loading wire:target="runTrelloSyncOnlyNew" class="animate-spin" />
                <span wire:loading.remove wire:target="runTrelloSyncOnlyNew">{{ __('Fetch new only') }}</span>
                <span wire:loading wire:target="runTrelloSyncOnlyNew">{{ __('Searching...') }}</span>
            </button>
            <button type="button" wire:click="runTrelloSync" wire:loading.attr="disabled" class="ui-btn ui-btn-secondary">
                <x-lucide-refresh-cw wire:loading wire:target="runTrelloSync" class="animate-spin" />
                <span wire:loading.remove wire:target="runTrelloSync">{{ __('Sync all') }}</span>
                <span wire:loading wire:target="runTrelloSync">{{ __('Syncing...') }}</span>
            </button>
        </x-slot:actions>
        <x-slot:toolbar>
            <div class="relative w-64">
                <x-lucide-search class="pointer-events-none absolute left-2.5 top-2 h-4 w-4 text-ink-3" />
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search in backlog...') }}" class="ui-input pl-8">
            </div>

            <x-lists.combo-filter model="companyFilter" :value="$companyFilter" :all-label="__('All companies')"
                :options="$existingCompanies->mapWithKeys(fn ($c) => [$c => $c])->all()" :search-placeholder="__('Search company...')" />

            <x-lists.combo-filter model="responsibleFilter" :value="$responsibleFilter" :all-label="__('All responsible people')"
                :options="$existingResponsibles->mapWithKeys(fn ($r) => [$r => $r])->all()" :search-placeholder="__('Search account rep...')" />

            <x-lists.combo-filter model="statusFilter" :value="$statusFilter" :all-label="__('All lists')"
                :options="collect(\App\Enums\CoreStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()" :search-placeholder="__('Search list...')" />

            <x-lists.combo-filter model="designerFilter" :value="$designerFilter" :all-label="__('All Designers')"
                :options="$designers->mapWithKeys(fn ($d) => [$d->id => $d->name])->all()" :search-placeholder="__('Search designer...')" />

            <select wire:model.live="sortBy" class="ui-select w-52" aria-label="{{ __('Sort by') }}">
                <option value="trello_created_at_desc">{{ __('Created (newest)') }}</option>
                <option value="trello_created_at_asc">{{ __('Created (oldest)') }}</option>
                <option value="due_date_asc">{{ __('Due date (soonest)') }}</option>
                <option value="company_asc">{{ __('Company (A-Z)') }}</option>
            </select>

            <div class="ml-auto flex items-center gap-2">
                <button type="button" wire:click="addAllFilteredToWorkspace" wire:confirm="{{ __('Add all filtered orders to active Workspace?') }}" class="ui-btn ui-btn-secondary">
                    {{ __('Add Filtered') }}
                </button>
                @if(count($selectedOrders) > 0)
                    <button type="button" wire:click="addSelectedToWorkspace" class="ui-btn ui-btn-primary">
                        {{ __('Add Selected') }} ({{ count($selectedOrders) }})
                    </button>
                @endif
            </div>
        </x-slot:toolbar>
    </x-ui.page-header>

    <!-- Loading overlay during Trello sync (removed with the Trello cutover) -->
    <div wire:loading.flex wire:target="runTrelloSync, runTrelloSyncOnlyNew" class="fixed inset-0 z-[500] items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-sm space-y-3 rounded-panel border border-line bg-surface p-6 text-center shadow-panel">
            <x-lucide-refresh-cw class="mx-auto h-6 w-6 animate-spin text-ink-2" />
            <h3 class="text-row font-semibold text-ink">
                <span wire:loading wire:target="runTrelloSyncOnlyNew">{{ __('Looking for new cards...') }}</span>
                <span wire:loading wire:target="runTrelloSync">{{ __('Syncing with Trello...') }}</span>
            </h3>
            <p class="text-body text-ink-3">
                <span wire:loading wire:target="runTrelloSyncOnlyNew">{{ __('Checking Trello and importing only cards that are not yet registered.') }}</span>
                <span wire:loading wire:target="runTrelloSync">{{ __('Connecting to Trello REST API, parsing lists, and updating cards in real time.') }}</span>
            </p>
        </div>
    </div>

    <!-- Sync summary report modal (removed with the Trello cutover) -->
    @if($syncReport['show'])
        <div
            x-data
            x-init="
                window.KudosModalStack?.register('sync-report-modal', $el, () => $wire.closeReportModal());
                $cleanup(() => window.KudosModalStack?.unregister('sync-report-modal'));
            "
            @pointerdown="window.KudosModalStack?.bringToFront('sync-report-modal')"
            class="fixed inset-0 flex items-center justify-center bg-black/40 p-4"
            style="z-index: 250;"
            @keydown.window.escape.prevent="if (window.KudosModalStack ? window.KudosModalStack.isTop('sync-report-modal') : !document.querySelector('[data-modal=\'order-detail\']')) $wire.closeReportModal()"
            @keydown.window.enter.prevent="if (window.KudosModalStack ? window.KudosModalStack.isTop('sync-report-modal') : !document.querySelector('[data-modal=\'order-detail\']')) $wire.closeReportModal()">
            <div class="w-full max-w-xl space-y-4 rounded-panel border border-line bg-surface p-6 shadow-panel">
                <div class="flex items-center justify-between border-b border-line pb-3">
                    <div>
                        <h3 class="text-row font-semibold text-ink">
                            @if(($syncReport['type'] ?? 'full') === 'new_only')
                                {{ __('New card search') }}
                            @else
                                {{ __('Trello Sync Summary') }}
                            @endif
                        </h3>
                        <p class="text-meta tabular-nums text-ink-3">{{ $syncReport['timestamp'] }}</p>
                    </div>
                    <button type="button" wire:click="closeReportModal" class="ui-btn ui-btn-ghost ui-btn-icon ui-btn-sm" aria-label="{{ __('Close') }}">
                        <x-lucide-x />
                    </button>
                </div>

                @if(($syncReport['type'] ?? 'full') === 'new_only')
                    <div class="grid grid-cols-3 gap-2">
                        @foreach([[__('Newly imported'), '+'.$syncReport['added'], __('orders')], [__('Already registered'), $syncReport['unchanged'], __('skipped')], [__('Total on board'), $syncReport['total'], __('cards')]] as [$lbl, $val, $unit])
                            <div class="ui-card p-3">
                                <span class="block truncate text-meta text-ink-3">{{ $lbl }}</span>
                                <div class="mt-1 flex items-baseline gap-1">
                                    <span class="text-title font-semibold tabular-nums text-ink">{{ $val }}</span>
                                    <span class="text-meta text-ink-3">{{ $unit }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if($syncReport['added'] === 0)
                        <div class="ui-card p-6 text-center">
                            <p class="text-row font-medium text-ink">{{ __('No new cards to import') }}</p>
                            <p class="mx-auto mt-1 max-w-sm text-body text-ink-3">{{ __('All :total active cards on the board are already in DOES. No existing order was changed.', ['total' => $syncReport['total']]) }}</p>
                        </div>
                    @else
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="ui-section-title">{{ __('New cards imported') }} ({{ count($syncReport['changes']) }})</span>
                                <x-ui.tag tone="neutral">{{ __('In backlog inbox') }}</x-ui.tag>
                            </div>
                            <div class="max-h-64 space-y-1.5 overflow-y-auto">
                                @foreach($syncReport['changes'] as $chg)
                                    <div @if(isset($chg['order_id'])) wire:click="$dispatch('open-order-detail', { orderId: {{ $chg['order_id'] }} })" @endif
                                         class="ui-card flex cursor-pointer items-center justify-between gap-3 p-3 transition-colors hover:bg-sunken/50"
                                         title="{{ __('Click to view detail of this order') }}">
                                        <div class="min-w-0">
                                            <p class="truncate text-body font-medium text-ink">{{ $chg['company'] }}</p>
                                            @if(!empty($chg['task']))<p class="truncate text-meta text-ink-3">{{ $chg['task'] }}</p>@endif
                                        </div>
                                        <x-ui.tag tone="neutral">{{ __('New') }}</x-ui.tag>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="flex items-center justify-between gap-3 border-t border-line pt-3">
                        <span class="text-meta text-ink-3">{{ __('Existing orders are protected (no changes or conflicts).') }}</span>
                        <button type="button" wire:click="closeReportModal" class="ui-btn ui-btn-primary">{{ __('Got it / Close') }}</button>
                    </div>
                @else
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-5">
                        @foreach([['created', __('Newly imported'), '+'.$syncReport['added']], ['pushed_to_trello', __('Sent to Trello'), $syncReport['pushed'] ?? 0], ['moved', __('Moved locally'), $syncReport['moved']], ['updated', __('Updated cards'), $syncReport['updated']], ['deleted', __('Missing from Trello'), $syncReport['deleted']]] as [$key, $lbl, $val])
                            <button type="button" wire:click="setFilter('{{ $key }}')"
                                    class="ui-card p-2.5 text-left transition-colors hover:bg-sunken/50 {{ $activeFilter === $key ? 'border-ink' : '' }}">
                                <span class="block truncate text-meta text-ink-3">{{ $lbl }}</span>
                                <div class="mt-0.5 flex items-baseline gap-1">
                                    <span class="text-section font-semibold tabular-nums text-ink">{{ $val }}</span>
                                    <span class="text-meta text-ink-3">{{ __('cards') }}</span>
                                </div>
                            </button>
                        @endforeach
                    </div>

                    <div class="flex items-center justify-between rounded-control bg-sunken px-3 py-2 text-body text-ink-2">
                        <span>{{ __('Total Processed on Board:') }}</span>
                        <span class="font-semibold tabular-nums text-ink">{{ $syncReport['total'] }} {{ __('cards') }}</span>
                    </div>

                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="ui-section-title">{{ __('List of Cards That Changed') }} ({{ count($syncReport['changes']) }})</span>
                            <button type="button" wire:click="setFilter('all')" class="ui-chip" aria-pressed="{{ $activeFilter === 'all' ? 'true' : 'false' }}">
                                {{ __('All') }} ({{ count($syncReport['changes']) }})
                            </button>
                        </div>

                        @php
                            $filteredChanges = collect($syncReport['changes'])->filter(function ($chg) use ($activeFilter) {
                                if ($activeFilter === 'all') return true;
                                return $chg['action'] === $activeFilter;
                            });
                        @endphp

                        @if($filteredChanges->isEmpty())
                            <div class="ui-card p-6 text-center text-body text-ink-3">{{ __('No cards registered in this change category.') }}</div>
                        @else
                            <div class="max-h-64 space-y-1.5 overflow-y-auto">
                                @foreach($filteredChanges as $chg)
                                    <div @if(isset($chg['order_id'])) wire:click="$dispatch('open-order-detail', { orderId: {{ $chg['order_id'] }} })" @endif
                                         class="ui-card flex cursor-pointer items-center justify-between gap-3 p-3 transition-colors hover:bg-sunken/50"
                                         title="{{ __('Click to view detail of this order') }}">
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-body font-medium text-ink">{{ $chg['company'] }}</p>
                                            @if($chg['task'])<p class="truncate text-meta text-ink-3">{{ $chg['task'] }}</p>@endif
                                            @if(!empty($chg['details']))
                                                <div class="mt-1 flex flex-wrap gap-1">
                                                    @foreach($chg['details'] as $detail)
                                                        <x-ui.tag tone="neutral">{{ $detail }}</x-ui.tag>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                        <div class="shrink-0 text-right">
                                            @if($chg['action'] === 'created')
                                                <x-ui.tag tone="neutral">{{ __('New Order') }}</x-ui.tag>
                                            @elseif($chg['action'] === 'pushed_to_trello')
                                                <x-ui.tag tone="neutral">{{ __('Sent to Trello:') }} {{ $chg['previous_status'] }} → {{ $chg['new_status'] }}</x-ui.tag>
                                            @elseif($chg['action'] === 'moved')
                                                <x-ui.tag tone="neutral">{{ $chg['previous_status'] }} → {{ $chg['new_status'] }}</x-ui.tag>
                                            @elseif($chg['action'] === 'updated')
                                                <x-ui.tag tone="neutral">{{ __('Updated') }}</x-ui.tag>
                                            @elseif($chg['action'] === 'deleted')
                                                <x-ui.tag tone="warn">{{ __('Missing in Trello') }}</x-ui.tag>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="flex justify-end border-t border-line pt-3">
                        <button type="button" wire:click="closeReportModal" class="ui-btn ui-btn-primary">{{ __('Got it / Close') }}</button>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <!-- Flash messages -->
    @if (session()->has('message'))
        <div class="flex shrink-0 items-center gap-2 rounded-card border border-line bg-surface px-3 py-2 text-body text-ink">
            <x-lucide-check-circle-2 class="h-4 w-4 shrink-0 text-ok" />
            <span class="truncate">{{ session('message') }}</span>
        </div>
    @endif
    @if (session()->has('warning'))
        <div class="flex shrink-0 items-center gap-2 rounded-card border border-line bg-warn-soft px-3 py-2 text-body text-warn">
            <x-lucide-alert-triangle class="h-4 w-4 shrink-0" />
            <span class="truncate">{{ session('warning') }}</span>
        </div>
    @endif

    <!-- New orders from Trello sync (removed with the Trello cutover) -->
    @if($newTrelloOrders->isNotEmpty())
        <div class="ui-card shrink-0 p-4">
            <h3 class="ui-section-title">{{ __('New orders from Trello') }} ({{ $newTrelloOrders->count() }})</h3>
            <p class="mt-0.5 text-body text-ink-3">{{ __('Orders just found by the Trello sync. Use "Add" to activate them in the workspace.') }}</p>

            <div class="mt-3 grid grid-cols-1 gap-2 md:grid-cols-2 lg:grid-cols-3">
                @foreach($newTrelloOrders as $nOrder)
                    <div wire:key="new-trello-{{ $nOrder->id }}" wire:click="$dispatch('open-order-detail', { orderId: {{ $nOrder->id }} })"
                         class="flex cursor-pointer items-center justify-between gap-2 rounded-control border border-line p-3 transition-colors hover:bg-sunken/50">
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5">
                                @if($nOrder->wo_number)<x-wo-badge :number="$nOrder->wo_number" />@endif
                                <span class="truncate text-body font-medium text-ink" title="{{ $nOrder->company_name }}">{{ $nOrder->company_name }}</span>
                            </div>
                            <p class="mt-0.5 truncate text-meta text-ink-3" title="{{ $nOrder->task_name }}">{{ $nOrder->task_name }}</p>
                        </div>
                        <button type="button" wire:click.stop="addToWorkspace({{ $nOrder->id }})" class="ui-btn ui-btn-secondary ui-btn-sm">{{ __('Add') }}</button>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Backlog table -->
    <div class="ui-card flex min-h-0 flex-1 flex-col overflow-hidden">
        <div class="min-h-0 flex-1 overflow-auto">
            <table class="ui-table">
                <thead>
                    <tr>
                        <th class="w-10"><input type="checkbox" wire:model.live="selectAll" aria-label="{{ __('Select all') }}" class="rounded border-line-strong text-ink focus:ring-ink/20"></th>
                        <th>{{ __('WO') }}</th>
                        <th>{{ __('Company') }}</th>
                        <th>{{ __('Task') }}</th>
                        <th class="hidden 2xl:table-cell">{{ __('Account Rep') }}</th>
                        <th>{{ __('Due date') }}</th>
                        <th>{{ __('List') }}</th>
                        <th>{{ __('Designer') }}</th>
                        <th class="hidden 2xl:table-cell">{{ __('Created') }}</th>
                        <th class="sticky right-0 w-px bg-canvas text-right">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        @php $isSelected = in_array((string) $order->id, $selectedOrders); @endphp
                        <tr wire:key="backlog-{{ $order->id }}" class="cursor-pointer" wire:click="$dispatch('open-order-detail', { orderId: {{ $order->id }} })">
                            <td x-on:click.stop="" class="w-10">
                                <input type="checkbox" wire:model.live="selectedOrders" value="{{ $order->id }}" aria-label="{{ __('Select order') }}" class="rounded border-line-strong text-ink focus:ring-ink/20">
                            </td>
                            <td class="whitespace-nowrap">
                                <div class="flex items-center gap-1.5">
                                    @if($order->wo_number)
                                        <x-wo-badge :number="$order->wo_number" />
                                    @else
                                        <span class="text-ink-3">—</span>
                                    @endif
                                    @if($order->pending_wo_number)
                                        <x-wo-badge :number="$order->pending_wo_number" prefix="Trello: " />
                                    @endif
                                </div>
                            </td>
                            <td class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="block max-w-[9rem] truncate font-medium {{ $order->done_today ? 'ui-done' : '' }}" title="{{ $order->company_name }}">{{ $order->company_name }}</span>
                                    <x-orders.location :location="$order->location_text" class="shrink-0" />
                                </div>
                            </td>
                            <td class="min-w-0 text-ink-2"><span class="block max-w-[11rem] truncate" title="{{ $order->task_name }}">{{ $order->task_name }}</span></td>
                            <td class="hidden whitespace-nowrap text-ink-2 2xl:table-cell">{{ $order->display_responsible_person }}</td>
                            <td class="whitespace-nowrap"><x-orders.due :date="$order->current_due_date" :done="false" /></td>
                            <td class="whitespace-nowrap text-ink-2">{{ $order->core_status->label() }}</td>
                            <td class="whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    @forelse($order->assigned_designers as $des)
                                        <span title="{{ $des->name }}"><x-ui.person :person="$des" size="sm" /></span>
                                    @empty
                                        <x-ui.person :person="null" size="sm" />
                                    @endforelse
                                </div>
                            </td>
                            <td class="hidden whitespace-nowrap text-ink-3 2xl:table-cell">{{ $order->trello_created_at ? $order->trello_created_at->translatedFormat('d M Y') : '—' }}</td>
                            <td x-on:click.stop="" class="sticky right-0 whitespace-nowrap bg-surface text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <button type="button" wire:click="addToWorkspace({{ $order->id }})" class="ui-btn ui-btn-secondary ui-btn-sm" title="{{ __('Add straight to the active workspace') }}">{{ __('Add') }}</button>
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" aria-label="{{ __('Actions') }}" />
                                        <flux:menu>
                                            <flux:menu.item icon="pencil-square" wire:click="$dispatch('open-order-detail', { orderId: {{ $order->id }}, startEdit: true })">{{ __('Edit and add') }}</flux:menu.item>
                                            @if($order->trello_url)
                                                <flux:menu.item icon="arrow-top-right-on-square" href="{{ $order->trello_url }}" target="_blank" rel="noopener noreferrer">{{ __('Open in Trello') }}</flux:menu.item>
                                            @endif
                                        </flux:menu>
                                    </flux:dropdown>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="p-0">
                                <x-ui.empty icon="inbox" :title="__('The backlog is empty')" :description="__('All cards are processed or in the workspace.')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex shrink-0 flex-col items-center justify-between gap-3 border-t border-line px-3 py-2 text-body text-ink-2 sm:flex-row">
            <div class="flex items-center gap-3">
                <span>{{ __('Showing :from – :to of :total', ['from' => $orders->firstItem() ?? 0, 'to' => $orders->lastItem() ?? 0, 'total' => $orders->total()]) }}</span>
                <select wire:model.live="perPage" class="ui-select w-32" aria-label="{{ __('Rows per page') }}">
                    <option value="25">{{ __(':count per page', ['count' => 25]) }}</option>
                    <option value="50">{{ __(':count per page', ['count' => 50]) }}</option>
                    <option value="100">{{ __(':count per page', ['count' => 100]) }}</option>
                </select>
            </div>

            @if($orders->hasPages())
                <div class="flex shrink-0 items-center gap-2">
                    <button type="button" wire:click="previousPage" @disabled($orders->onFirstPage()) class="ui-btn ui-btn-secondary ui-btn-sm"><x-lucide-chevron-left />{{ __('Previous') }}</button>
                    <span class="tabular-nums text-ink-3">{{ $orders->currentPage() }} / {{ $orders->lastPage() }}</span>
                    <button type="button" wire:click="nextPage" @disabled(! $orders->hasMorePages()) class="ui-btn ui-btn-secondary ui-btn-sm">{{ __('Next') }}<x-lucide-chevron-right /></button>
                </div>
            @endif
        </div>
    </div>
</div>
