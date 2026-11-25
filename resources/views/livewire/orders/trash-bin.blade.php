<div class="h-full flex flex-col gap-4 min-h-0 overflow-y-auto custom-vertical-scrollbar">
    <x-ui.page-header :title="__('Papelera')" :count="$trashedOrders->count()">
        <x-slot:actions>
            @if($trashedOrders->isNotEmpty())
                <button type="button" wire:click="emptyTrash"
                        wire:confirm="{{ __('¿Estás seguro de vaciar toda la papelera y eliminar todas las órdenes y sus tarjetas en Trello definitivamente? Esta acción es irreversible.') }}"
                        class="ui-btn ui-btn-danger" title="{{ __('Vaciar papelera / eliminar todo definitivamente') }}">
                    <x-lucide-trash-2 />{{ __('Vaciar papelera') }}
                </button>
            @endif
        </x-slot:actions>
        <x-slot:toolbar>
            <div class="relative w-72">
                <x-lucide-search class="pointer-events-none absolute left-2.5 top-2 h-4 w-4 text-ink-3" />
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('Buscar en la papelera...') }}" class="ui-input pl-8">
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
            <x-ui.empty icon="trash-2" :title="__('La papelera está vacía')" :description="__('Las órdenes eliminadas aparecerán aquí antes de ser borradas permanentemente.')" />
        @else
            <div class="overflow-x-auto">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>{{ __('WO') }}</th>
                            <th>{{ __('Empresa') }}</th>
                            <th>{{ __('Tarea') }}</th>
                            <th>{{ __('Lista') }}</th>
                            <th class="hidden 2xl:table-cell">{{ __('Responsable') }}</th>
                            <th>{{ __('Eliminada') }}</th>
                            <th class="sticky right-0 w-px bg-canvas"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($trashedOrders as $order)
                            <tr wire:key="trash-{{ $order->id }}">
                                <td class="whitespace-nowrap">@if($order->wo_number)<x-wo-badge :number="$order->wo_number" />@else<span class="text-ink-3">{{ __('Sin WO') }}</span>@endif</td>
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
                                        <button type="button" wire:click="restoreOrder({{ $order->id }})" class="ui-btn ui-btn-secondary ui-btn-sm" title="{{ __('Restaurar orden') }}">
                                            <x-lucide-undo-2 />{{ __('Restaurar') }}
                                        </button>
                                        <flux:dropdown position="bottom" align="end">
                                            <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" aria-label="{{ __('Acciones') }}" />
                                            <flux:menu>
                                                <flux:menu.item variant="danger" icon="trash" wire:click="forceDeleteOrder({{ $order->id }})"
                                                    wire:confirm="{{ __('¿Eliminar permanentemente de la app y de Trello? Esta acción no se puede deshacer.') }}">{{ __('Eliminar también de Trello') }}</flux:menu.item>
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
