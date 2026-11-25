@php
    $totalActionCount = $blockedOrders->count() + $resolverTasks->count();
    $hasActionRequired = $totalActionCount > 0;
@endphp

<div class="h-full flex flex-col gap-4 min-h-0 overflow-y-auto custom-vertical-scrollbar">
    <x-ui.page-header id="tour-resolver-header" :title="__('Action Required')" :subtitle="__('Orders and tasks that need a decision from you.')">
        <x-slot:actions>
            <div id="tour-resolver-cases-badge">
                @if($hasActionRequired)
                    <x-ui.tag tone="warn">{{ trans_choice('{1} :count open case|[2,*] :count open cases', $totalActionCount) }}</x-ui.tag>
                @else
                    <x-ui.tag tone="ok" icon="lucide-check">{{ __('All caught up') }}</x-ui.tag>
                @endif
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    @if(session()->has('message'))
        <div class="flex shrink-0 items-center gap-2 rounded-card border border-line bg-surface px-3 py-2 text-body text-ink">
            <x-lucide-check-circle class="h-4 w-4 shrink-0 text-ok" />{{ session('message') }}
        </div>
    @endif

    @if(!$hasActionRequired)
        <div class="ui-card">
            <x-ui.empty icon="check-circle-2" :title="__('Nothing here to be done')" :description="__('No orders or tasks need attention right now. Everything is running as planned.')" />
        </div>
    @else
        @if($blockedOrders->isNotEmpty())
            <section>
                <h2 class="ui-section-title mb-2">{{ __('Orders that cannot progress normally') }}</h2>
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    @foreach($blockedOrders as $order)
                        <div wire:key="blocked-{{ $order->id }}" wire:click="$dispatch('open-order-detail', { orderId: {{ $order->id }} })"
                             class="ui-card cursor-pointer space-y-2.5 p-4 transition-colors hover:bg-sunken/40">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        @if($order->wo_number)<x-wo-badge :number="$order->wo_number" />@endif
                                        <x-ui.tag tone="warn">{{ $order->blocking_reason?->label() ?? ($order->substatus ? $order->substatus->label() : __('Blocked')) }}</x-ui.tag>
                                    </div>
                                    <div class="mt-1.5 flex min-w-0 items-center gap-2">
                                        <h3 class="truncate text-row font-medium text-ink" title="{{ $order->company_name }}">{{ $order->company_name }}</h3>
                                        <x-orders.location :location="$order->location_text" class="shrink-0 max-w-[45%]" />
                                    </div>
                                    <p class="truncate text-body text-ink-2" title="{{ $order->task_name }}">{{ $order->task_name }}</p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <x-ui.person :person="$order->designer" size="sm" show-name />
                                    <div class="mt-1"><x-orders.due :date="$order->current_due_date" :done="false" /></div>
                                </div>
                            </div>

                            @if($order->blocking_reason_other)
                                <p class="rounded-control bg-sunken px-2.5 py-1.5 text-body text-ink-2">{{ __('Reason:') }} {{ $order->blocking_reason_other }}</p>
                            @endif

                            @if($order->done_today)
                                <div x-on:click.stop="" class="space-y-1.5 border-t border-line pt-2.5">
                                    <p class="text-meta font-medium text-ink-2">{{ __('Today\'s work is done — confirm where it goes:') }}</p>
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <button type="button" wire:click="sendToCamila({{ $order->id }})" class="ui-btn ui-btn-secondary ui-btn-sm">{{ __('Sent to Camila') }}</button>
                                        <button type="button" wire:click="sendToClient({{ $order->id }})" class="ui-btn ui-btn-secondary ui-btn-sm">{{ __('Sent to Client') }}</button>
                                        @if($order->isApproved() || $order->substatus === \App\Enums\Substatus::PONER_EN_ALTA)
                                            <button type="button" wire:click="sendToProduction({{ $order->id }})" class="ui-btn ui-btn-secondary ui-btn-sm">{{ __('Sent to production') }}</button>
                                        @endif
                                        <button type="button" wire:click="keepOnPendingWork({{ $order->id }})" class="ui-btn ui-btn-ghost ui-btn-sm">{{ __('Keep in pending work') }}</button>
                                    </div>
                                </div>
                            @endif

                            <div class="flex items-center justify-between border-t border-line pt-2.5">
                                <span class="text-meta text-ink-3">{{ __('Status:') }} <span class="text-ink-2">{{ $order->core_status->label() }}</span></span>
                                <button type="button" x-on:click.stop="" wire:click="openUnblockModal({{ $order->id }})" class="ui-btn ui-btn-secondary ui-btn-sm">
                                    <x-lucide-unlock />{{ __('Unblock') }}
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @if($resolverTasks->isNotEmpty())
            <section>
                <h2 class="ui-section-title mb-2">{{ __('Tasks waiting to be resolved') }}</h2>
                <div class="ui-card divide-y divide-line">
                    @foreach($resolverTasks as $task)
                        <div wire:key="rtask-{{ $task->id }}" @if($task->order) wire:click="$dispatch('open-order-detail', { orderId: {{ $task->order->id }} })" @endif
                             class="flex items-center justify-between gap-3 px-4 py-3 {{ $task->order ? 'cursor-pointer hover:bg-sunken/40' : '' }}">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    @if($task->order)
                                        <span class="truncate text-body font-medium text-ink">{{ $task->order->company_name }}</span>
                                        <x-orders.location :location="$task->order->location_text" class="shrink-0 max-w-[40%]" />
                                    @endif
                                    <x-ui.tag tone="neutral">{{ $task->type?->label() ?? $task->type }}</x-ui.tag>
                                </div>
                                <p class="mt-0.5 text-body text-ink-2">{{ $task->display_title }}</p>
                            </div>
                            <x-ui.person :person="$task->assignee" size="sm" />
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    @endif

    @if($showUnblockModal && $unblockingOrder)
        <div class="fixed inset-0 z-[150] flex items-center justify-center bg-black/40 p-4"
             @keydown.window.escape.prevent="$wire.closeUnblockModal()"
             @keydown.window.enter.prevent="if($event.target.tagName !== 'TEXTAREA') $wire.confirmUnblock()"
             wire:keydown.escape="closeUnblockModal">
            <div class="w-full max-w-lg space-y-4 rounded-panel border border-line bg-surface p-5 shadow-panel">
                <div class="flex items-start justify-between border-b border-line pb-3">
                    <div class="min-w-0">
                        <h3 class="ui-section-title">{{ __('Unblock Order') }}</h3>
                        <p class="truncate text-body text-ink-3">
                            {{ $unblockingOrder->company_name }}@if($unblockingOrder->location_text) · {{ $unblockingOrder->location_text }}@endif — {{ $unblockingOrder->task_name }}
                        </p>
                    </div>
                    <button type="button" wire:click="closeUnblockModal" class="ui-btn ui-btn-ghost ui-btn-icon ui-btn-sm" aria-label="{{ __('Close') }}"><x-lucide-x /></button>
                </div>

                <div class="space-y-3">
                    <label class="ui-label">{{ __('How was the block resolved or what action was taken?') }}</label>

                    <div class="flex flex-wrap gap-1.5">
                        @foreach([['Medidas confirmadas y recibidas', __('Measurements confirmed')], ['Cliente aprobó información', __('Client approved information')], ['Estimado aprobado', __('Estimate approved')], ['Resuelto por Atención a Clientes', __('Resolved by Customer Service')]] as [$reason, $label])
                            <button type="button" wire:click="selectPresetReason('{{ $reason }}')" class="ui-chip">{{ $label }}</button>
                        @endforeach
                    </div>

                    <textarea wire:model="unblockReason" rows="3" class="ui-input" placeholder="{{ __('Describe the reason or resolution details...') }}"></textarea>

                    @error('unblockReason')
                        <p class="text-meta font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-line pt-3">
                    <button wire:click="closeUnblockModal" type="button" class="ui-btn ui-btn-secondary">{{ __('Cancel') }}</button>
                    <button wire:click="confirmUnblock" wire:loading.attr="disabled" type="button" class="ui-btn ui-btn-primary">{{ __('Confirm Unblock') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
