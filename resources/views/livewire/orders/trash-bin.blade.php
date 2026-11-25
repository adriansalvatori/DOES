<div class="h-full flex flex-col gap-4 min-h-0 overflow-y-auto custom-vertical-scrollbar">
    <x-ui.page-header :title="__('Trash')" :count="$trashedOrders->count()">
        <x-slot:actions>
            @if($trashedOrders->isNotEmpty())
                <button type="button" wire:click="emptyTrash"
                        wire:confirm="{{ __('Empty the whole trash and permanently delete all orders and their Trello cards? This can\'t be undone.') }}"
                        class="ui-btn ui-btn-danger" title="{{ __('Empty trash / delete everything permanently') }}">
                    <x-lucide-trash-2 />{{ __('Empty trash') }}
                </button>
            @endif
        </x-slot:actions>
        <x-slot:toolbar>
            <div class="relative w-72">
                <x-lucide-search class="pointer-events-none absolute left-2.5 top-2 h-4 w-4 text-ink-3" />
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search in trash...') }}" class="ui-input pl-8">
            </div>
        </x-slot:toolbar>
    </x-ui.page-header>

    @if(session('message'))
        <div class="flex items-center gap-2 rounded-card border border-line bg-surface px-3 py-2 text-body text-ink">
            <x-lucide-check-circle class="h-4 w-4 shrink-0 text-ok" />{{ session('message') }}
        </div>
    @endif

    <div class="ui-card overflow-hidden">
        @if($trashedOrders->isEmpty())
            <x-ui.empty icon="trash-2" :title="__('Trash is empty')" :description="__('Deleted orders will appear here before being permanently removed.')" />
        @else
            <div class="overflow-x-auto">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>{{ __('WO') }}</th>
                            <th>{{ __('Company') }}</th>
                            <th>{{ __('Task') }}</th>
                            <th>{{ __('List') }}</th>
                            <th class="hidden 2xl:table-cell">{{ __('Account Rep') }}</th>
                            <th>{{ __('Deleted') }}</th>
                            <th class="sticky right-0 w-px bg-canvas"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($trashedOrders as $order)
                            <tr wire:key="trash-{{ $order->id }}">
                                <td class="whitespace-nowrap">@if($order->wo_number)<x-wo-badge :number="$order->wo_number" />@else<span class="text-ink-3">{{ __('No WO') }}</span>@endif</td>
                                <td class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="block max-w-[14rem] truncate font-medium" title="{{ $order->company_name }}">{{ $order->company_name }}</span>
                                        <x-orders.location :location="$order->location_text" class="shrink-0" />
                                    </div>
                                </td>
                                <td class="text-ink-2"><span class="block max-w-[16rem] truncate" title="{{ $order->task_name }}">{{ $order->task_name }}</span></td>
                                <td class="whitespace-nowrap text-ink-2">{{ $order->core_status?->label() ?? '—' }}</td>
                                <td class="hidden whitespace-nowrap text-ink-2 2xl:table-cell">{{ $order->responsible_person ?: '—' }}</td>
                                <td class="whitespace-nowrap text-ink-3">{{ $order->deleted_at->diffForHumans() }}</td>
                                <td class="sticky right-0 whitespace-nowrap bg-surface text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <button type="button" wire:click="restoreOrder({{ $order->id }})" class="ui-btn ui-btn-secondary ui-btn-sm" title="{{ __('Restore order') }}">
                                            <x-lucide-undo-2 />{{ __('Restore') }}
                                        </button>
                                        <flux:dropdown position="bottom" align="end">
                                            <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" aria-label="{{ __('Actions') }}" />
                                            <flux:menu>
                                                <flux:menu.item variant="danger" icon="trash" wire:click="forceDeleteOrder({{ $order->id }})"
                                                    wire:confirm="{{ __('Permanently delete from the app and Trello? This can\'t be undone.') }}">{{ __('Also delete from Trello') }}</flux:menu.item>
                                            </flux:menu>
                                        </flux:dropdown>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
