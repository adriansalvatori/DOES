{{-- Resolve and Camila review modals, restyled to tokens: quiet header, ink primary button. --}}
@if($showResolveModal && $resolveOrder)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="resolve-modal-title" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-ink/40" wire:click="closeResolveModal"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-lg rounded-panel bg-surface shadow-panel border border-line overflow-hidden">
                <div class="px-5 py-4 border-b border-line flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h3 id="resolve-modal-title" class="text-section font-semibold text-ink">{{ __('Resolución de Bloqueo') }}</h3>
                        <div class="flex items-center gap-2 mt-1 min-w-0">
                            <x-wo-badge :number="$resolveOrder->wo_number" />
                            <span class="text-body text-ink-2 truncate">{{ $resolveOrder->company_name }} — {{ $resolveOrder->task_name }}</span>
                            <x-orders.location :location="$resolveOrder->location_text" />
                        </div>
                    </div>
                    <button wire:click="closeResolveModal" type="button" aria-label="{{ __('Cerrar') }}" class="p-1 rounded-control text-ink-3 hover:text-ink hover:bg-sunken cursor-pointer">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                </div>

                <div class="p-5 space-y-4">
                    <div class="rounded-card bg-sunken p-3 space-y-1.5">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-meta text-ink-3">{{ __('Motivo Actual del Bloqueo') }}</span>
                            <x-ui.tag tone="warn">{{ $resolveOrder->blocking_reason?->value ?? ($resolveOrder->substatus ? $resolveOrder->substatus->value : __('BLOQUEADA')) }}</x-ui.tag>
                        </div>
                        @if($resolveOrder->blocking_reason_other)
                            <p class="text-body text-ink">{{ $resolveOrder->blocking_reason_other }}</p>
                        @endif
                        <div class="text-meta text-ink-3 flex flex-wrap items-center gap-x-4 gap-y-1">
                            <span>{{ __('Diseñador') }}: <x-ui.person :person="$resolveOrder->primary_designer" size="sm" show-name class="inline-flex align-middle" /></span>
                            @if($resolveOrder->updated_at)
                                <span>{{ __('Bloqueado desde') }}: {{ $resolveOrder->updated_at->diffForHumans() }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label for="resolveComment" class="block text-body font-medium text-ink">
                            {{ __('Notas de resolución o seguimiento') }} <span class="text-ink-3 font-normal">({{ __('opcional para desbloquear, requerida para mantener') }})</span>
                        </label>
                        <textarea id="resolveComment" wire:model="resolveComment" rows="3"
                            placeholder="{{ __('Escribe cómo se resolvió el bloqueo o qué novedad hay del cliente...') }}"
                            class="w-full bg-surface border border-line rounded-control p-2.5 text-body text-ink focus:border-ink focus:outline-none"></textarea>
                        @error('resolveComment')
                            <span class="text-meta text-danger font-medium">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="flex items-center justify-between text-meta text-ink-3">
                        <button type="button" wire:click="$dispatch('open-order-detail', { orderId: {{ $resolveOrder->id }} }); closeResolveModal();" class="hover:text-ink underline cursor-pointer">
                            {{ __('Ver detalle completo de la orden') }}
                        </button>
                        @if($resolveOrder->trello_card_id)
                            <a href="https://trello.com/c/{{ $resolveOrder->trello_card_id }}" target="_blank" rel="noopener" class="hover:text-ink underline">{{ __('Ver en Trello') }}</a>
                        @endif
                    </div>
                </div>

                <div class="px-5 py-3.5 border-t border-line flex flex-col sm:flex-row items-center justify-between gap-2">
                    <flux:button type="button" variant="ghost" wire:click="closeResolveModal">{{ __('Cancelar') }}</flux:button>
                    <div class="flex items-center gap-2">
                        <flux:button type="button" variant="outline" wire:click="keepOrderBlocked">{{ __('Mantener Bloqueada') }}</flux:button>
                        <flux:button type="button" variant="primary" wire:click="unblockOrder">{{ __('Desbloquear Orden') }}</flux:button>
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
                        <h3 id="camila-modal-title" class="text-section font-semibold text-ink">{{ __('Revisión Camila') }}</h3>
                        <div class="flex items-center gap-2 mt-1 min-w-0">
                            <x-wo-badge :number="$camilaOrder->wo_number" />
                            <span class="text-body text-ink-2 truncate">{{ $camilaOrder->company_name }} — {{ $camilaOrder->task_name }}</span>
                            <x-orders.location :location="$camilaOrder->location_text" />
                        </div>
                    </div>
                    <button wire:click="closeCamilaModal" type="button" aria-label="{{ __('Cerrar') }}" class="p-1 rounded-control text-ink-3 hover:text-ink hover:bg-sunken cursor-pointer">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                </div>

                <div class="p-5 space-y-4">
                    <div class="rounded-card bg-sunken p-3 space-y-1.5">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-meta text-ink-3">{{ __('Tarea a Completar') }}</span>
                            <x-ui.tag tone="info">{{ $camilaOrder->core_status?->value ?? 'ENVIADO_A_CAMILA' }}</x-ui.tag>
                        </div>
                        <p class="text-body text-ink">{{ $camilaTask?->title ?? __('Revisión de diseño') }}</p>
                        <div class="text-meta text-ink-3 flex flex-wrap items-center gap-x-4 gap-y-1">
                            <span>{{ __('Diseñador') }}: <x-ui.person :person="$camilaOrder->primary_designer" size="sm" show-name class="inline-flex align-middle" /></span>
                            <span>{{ __('Revisión interna #') }}: {{ ($camilaOrder->internal_revision_count ?? 0) + 1 }}</span>
                        </div>
                    </div>

                    <p class="text-meta text-ink-3">
                        {{ __('Confirma la acción que deseas realizar con esta orden. Puedes solicitar ajustes al diseñador, pre-aprobar para enviar prueba al cliente, o solo registrar una nota en el timeline sin alterar el estado.') }}
                    </p>

                    <div class="space-y-1.5">
                        <label for="camilaComment" class="block text-body font-medium text-ink">
                            {{ __('Comentario o detalles') }} <span class="text-ink-3 font-normal">({{ __('requerido para "Solo Comentario", opcional para las demás') }})</span>
                        </label>
                        <textarea id="camilaComment" wire:model="camilaComment" rows="3"
                            placeholder="{{ __('Ej. Camila necesita conseguir más info / Cambiar tamaño de logo...') }}"
                            class="w-full bg-surface border border-line rounded-control p-2.5 text-body text-ink focus:border-ink focus:outline-none"></textarea>
                        @error('camilaComment')
                            <span class="text-meta text-danger font-medium">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="flex items-center justify-between text-meta text-ink-3">
                        <button type="button" wire:click="$dispatch('open-order-detail', { orderId: {{ $camilaOrder->id }} }); closeCamilaModal();" class="hover:text-ink underline cursor-pointer">
                            {{ __('Ver detalle completo de la orden') }}
                        </button>
                        @if($camilaOrder->trello_card_id)
                            <a href="https://trello.com/c/{{ $camilaOrder->trello_card_id }}" target="_blank" rel="noopener" class="hover:text-ink underline">{{ __('Ver en Trello') }}</a>
                        @endif
                    </div>
                </div>

                <div class="px-5 py-3.5 border-t border-line flex flex-col sm:flex-row items-center justify-between gap-2">
                    <flux:button type="button" variant="ghost" wire:click="closeCamilaModal">{{ __('Cancelar') }}</flux:button>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <flux:button type="button" variant="ghost" wire:click="camilaAddComment" title="{{ __('Mantiene la orden en Revisión Camila y agrega una nota al timeline') }}">{{ __('Solo Comentario') }}</flux:button>
                        <flux:button type="button" variant="outline" wire:click="camilaRequestChanges" title="{{ __('Mueve la orden a Para Hoy con subtarea de ajustes para el diseñador') }}">{{ __('Ajustes de Camila') }}</flux:button>
                        <flux:button type="button" variant="primary" wire:click="camilaPreApprove" title="{{ __('Crea subtarea de enviar proof al cliente pre-aprobada por Camila') }}">{{ __('Enviar al Cliente') }}</flux:button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
