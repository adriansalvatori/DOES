{{-- Resolve and Camila review modals, restyled to tokens: quiet header, ink primary button. --}}
@if($showResolveModal && $resolveOrder)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="resolve-modal-title" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-ink/40" wire:click="closeResolveModal"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-lg rounded-panel bg-surface shadow-panel border border-line overflow-hidden">
                <div class="px-5 py-4 border-b border-line flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h3 id="resolve-modal-title" class="text-section font-semibold text-ink">{{ __('Resolve block') }}</h3>
                        <div class="flex items-center gap-2 mt-1 min-w-0">
                            <x-wo-badge :number="$resolveOrder->wo_number" />
                            <span class="text-body text-ink-2 truncate">{{ $resolveOrder->company_name }} — {{ $resolveOrder->task_name }}</span>
                            <x-orders.location :location="$resolveOrder->location_text" />
                        </div>
                    </div>
                    <button wire:click="closeResolveModal" type="button" aria-label="{{ __('Close') }}" class="p-1 rounded-control text-ink-3 hover:text-ink hover:bg-sunken cursor-pointer">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                </div>

                <div class="p-5 space-y-4">
                    <div class="rounded-card bg-sunken p-3 space-y-1.5">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-meta text-ink-3">{{ __('Current block reason') }}</span>
                            <x-ui.tag tone="warn">{{ $resolveOrder->blocking_reason?->value ?? ($resolveOrder->substatus ? $resolveOrder->substatus->value : __('BLOCKED')) }}</x-ui.tag>
                        </div>
                        @if($resolveOrder->blocking_reason_other)
                            <p class="text-body text-ink">{{ $resolveOrder->blocking_reason_other }}</p>
                        @endif
                        <div class="text-meta text-ink-3 flex flex-wrap items-center gap-x-4 gap-y-1">
                            <span>{{ __('Designer') }}: <x-ui.person :person="$resolveOrder->primary_designer" size="sm" show-name class="inline-flex align-middle" /></span>
                            @if($resolveOrder->updated_at)
                                <span>{{ __('Blocked since') }}: {{ $resolveOrder->updated_at->diffForHumans() }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label for="resolveComment" class="block text-body font-medium text-ink">
                            {{ __('Resolution or follow-up notes') }} <span class="text-ink-3 font-normal">({{ __('optional to unblock, required to keep blocked') }})</span>
                        </label>
                        <textarea id="resolveComment" wire:model="resolveComment" rows="3"
                            placeholder="{{ __('Describe how the block was resolved or what\'s new from the client...') }}"
                            class="w-full bg-surface border border-line rounded-control p-2.5 text-body text-ink focus:border-ink focus:outline-none"></textarea>
                        @error('resolveComment')
                            <span class="text-meta text-danger font-medium">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="flex items-center justify-between text-meta text-ink-3">
                        <button type="button" wire:click="$dispatch('open-order-detail', { orderId: {{ $resolveOrder->id }} }); closeResolveModal();" class="hover:text-ink underline cursor-pointer">
                            {{ __('View full order details') }}
                        </button>
                        @if($resolveOrder->trello_card_id)
                            <a href="https://trello.com/c/{{ $resolveOrder->trello_card_id }}" target="_blank" rel="noopener" class="hover:text-ink underline">{{ __('View in Trello') }}</a>
                        @endif
                    </div>
                </div>

                <div class="px-5 py-3.5 border-t border-line flex flex-col sm:flex-row items-center justify-between gap-2">
                    <flux:button type="button" variant="ghost" wire:click="closeResolveModal">{{ __('Cancel') }}</flux:button>
                    <div class="flex items-center gap-2">
                        <flux:button type="button" variant="outline" wire:click="keepOrderBlocked">{{ __('Keep blocked') }}</flux:button>
                        <flux:button type="button" variant="primary" wire:click="unblockOrder">{{ __('Unblock Order') }}</flux:button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

@if($showCamilaModal && $camilaOrder)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="camila-modal-title" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-ink/40" wire:click="closeCamilaModal"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-lg rounded-panel bg-surface shadow-panel border border-line overflow-hidden">
                <div class="px-5 py-4 border-b border-line flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h3 id="camila-modal-title" class="text-section font-semibold text-ink">{{ __('Camila review') }}</h3>
                        <div class="flex items-center gap-2 mt-1 min-w-0">
                            <x-wo-badge :number="$camilaOrder->wo_number" />
                            <span class="text-body text-ink-2 truncate">{{ $camilaOrder->company_name }} — {{ $camilaOrder->task_name }}</span>
                            <x-orders.location :location="$camilaOrder->location_text" />
                        </div>
                    </div>
                    <button wire:click="closeCamilaModal" type="button" aria-label="{{ __('Close') }}" class="p-1 rounded-control text-ink-3 hover:text-ink hover:bg-sunken cursor-pointer">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                </div>

                <div class="p-5 space-y-4">
                    <div class="rounded-card bg-sunken p-3 space-y-1.5">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-meta text-ink-3">{{ __('Task to complete') }}</span>
                            <x-ui.tag tone="info">{{ $camilaOrder->core_status?->value ?? 'ENVIADO_A_CAMILA' }}</x-ui.tag>
                        </div>
                        <p class="text-body text-ink">{{ $camilaTask?->title ?? __('Design review') }}</p>
                        <div class="text-meta text-ink-3 flex flex-wrap items-center gap-x-4 gap-y-1">
                            <span>{{ __('Designer') }}: <x-ui.person :person="$camilaOrder->primary_designer" size="sm" show-name class="inline-flex align-middle" /></span>
                            <span>{{ __('Internal review #') }}: {{ ($camilaOrder->internal_revision_count ?? 0) + 1 }}</span>
                        </div>
                    </div>

                    <p class="text-meta text-ink-3">
                        {{ __('Choose what to do with this order. You can request changes from the designer, pre-approve it to send a proof to the client, or just log a note on the timeline without changing its status.') }}
                    </p>

                    <div class="space-y-1.5">
                        <label for="camilaComment" class="block text-body font-medium text-ink">
                            {{ __('Comment or details') }} <span class="text-ink-3 font-normal">({{ __('required for "Comment only", optional for the others') }})</span>
                        </label>
                        <textarea id="camilaComment" wire:model="camilaComment" rows="3"
                            placeholder="{{ __('E.g. Camila needs more info / Change logo size...') }}"
                            class="w-full bg-surface border border-line rounded-control p-2.5 text-body text-ink focus:border-ink focus:outline-none"></textarea>
                        @error('camilaComment')
                            <span class="text-meta text-danger font-medium">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="flex items-center justify-between text-meta text-ink-3">
                        <button type="button" wire:click="$dispatch('open-order-detail', { orderId: {{ $camilaOrder->id }} }); closeCamilaModal();" class="hover:text-ink underline cursor-pointer">
                            {{ __('View full order details') }}
                        </button>
                        @if($camilaOrder->trello_card_id)
                            <a href="https://trello.com/c/{{ $camilaOrder->trello_card_id }}" target="_blank" rel="noopener" class="hover:text-ink underline">{{ __('View in Trello') }}</a>
                        @endif
                    </div>
                </div>

                <div class="px-5 py-3.5 border-t border-line flex flex-col sm:flex-row items-center justify-between gap-2">
                    <flux:button type="button" variant="ghost" wire:click="closeCamilaModal">{{ __('Cancel') }}</flux:button>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <flux:button type="button" variant="ghost" wire:click="camilaAddComment" title="{{ __('Keeps the order in Camila review and adds a note to the timeline') }}">{{ __('Comment only') }}</flux:button>
                        <flux:button type="button" variant="outline" wire:click="camilaRequestChanges" title="{{ __('Moves the order to Today with an adjustments subtask for the designer') }}">{{ __('Camila\'s changes') }}</flux:button>
                        <flux:button type="button" variant="primary" wire:click="camilaPreApprove" title="{{ __('Creates a subtask to send the proof to the client, pre-approved by Camila') }}">{{ __('Send to client') }}</flux:button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
