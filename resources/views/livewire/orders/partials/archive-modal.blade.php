@if($showArchiveModal)
    @php
        $targetOrder = $targetOrder ?? ($order ?? ($pendingArchiveOrder ?? null));
        $archivedSubstatuses = $archivedSubstatuses ?? \App\Models\Substatus::getArchivedSubstatuses();
    @endphp
    <div 
        class="fixed inset-0 z-[360] bg-ink/40 flex items-center justify-center p-4" 
        @keydown.window.escape.prevent="$wire.closeArchiveModal()"
        @keydown.window.enter.prevent="$wire.confirmArchive()"
        wire:keydown.escape="closeArchiveModal">
        <div 
            class="bg-surface border border-line rounded-panel shadow-panel max-w-md w-full p-5 space-y-3"
            @click.outside="$wire.closeArchiveModal()">
            
            <!-- Modal Header -->
            <div class="flex items-center justify-between pb-2.5 border-b border-line">
                <div class="flex items-center gap-2 min-w-0">
                    <x-lucide-archive class="w-4 h-4 text-ink-2 shrink-0" />
                    <div class="min-w-0">
                        <h3 class="text-section font-semibold text-ink truncate">{{ __('Archivar Orden') }}</h3>
                        @if($targetOrder)
                            <p class="text-meta text-ink-3 truncate">
                                {{ $targetOrder->wo_number ? $targetOrder->wo_number . ' · ' : '' }}{{ $targetOrder->company_name ?? '' }}
                            </p>
                        @else
                            <p class="text-meta text-ink-3">{{ __('Selecciona el subestatus de cierre.') }}</p>
                        @endif
                    </div>
                </div>
                <button 
                    wire:click="closeArchiveModal" 
                    type="button" 
                    class="p-1 rounded-control text-ink-3 hover:text-ink hover:bg-sunken transition cursor-pointer"
                    title="{{ __('Cerrar') }}">
                    <x-lucide-x class="w-4 h-4" />
                </button>
            </div>

            <!-- Modal Body -->
            <div class="space-y-2 text-body">
                <div class="flex items-center justify-between">
                    <label class="font-medium text-ink-2 block text-meta">{{ __('Razón o subestatus de cierre:') }}</label>
                    <span class="text-meta text-ink-3">
                        {{ count($archivedSubstatuses) }} {{ __('disponibles') }}
                    </span>
                </div>

                <div class="space-y-0.5">
                    @forelse($archivedSubstatuses as $sub)
                        @php
                            $subName = $sub instanceof \App\Models\Substatus ? $sub->name : ($sub->value ?? (string) $sub);
                            $inlineStyle = $sub instanceof \App\Models\Substatus ? $sub->getInlineBadgeStyle() : ($sub->getInlineBadgeStyle() ?? '');
                            $isDefault = (bool) ($sub->is_default ?? false);
                            
                            // Contextual tooltip description
                            $desc = match(mb_strtoupper($subName)) {
                                'FINALIZADA !', 'FINALIZADA' => __('Trabajo completado y entregado al cliente.'),
                                'ORDEN LISTA - ENTREGADA' => __('Trabajo terminado y entregado con éxito.'),
                                'ORDEN LISTA' => __('Trabajo finalizado, listo para entrega o cerrado.'),
                                'CLIENTE NO RESPONSIVE', 'CLIENTE NO RESPONDIO' => __('Sin respuesta o no retiró la orden tras largo tiempo.'),
                                'CANCELADA' => __('Orden anulada o no realizada.'),
                                'CANCELADA POR CLIENTE' => __('Cancelada por decisión directa del cliente.'),
                                'CANCELADA POR CAMILA' => __('Cancelada internamente durante revisión / QA.'),
                                'DEBE' => __('Orden finalizada con saldo pendiente por cobrar.'),
                                'NO REALIZADA / TRANSFERIDA' => __('Orden transferida o no ejecutada.'),
                                default => null,
                            };
                        @endphp

                        <label 
                            class="flex items-center justify-between px-2.5 py-1.5 rounded-control cursor-pointer transition select-none text-body"
                            :class="$wire.archiveSubstatus === '{{ addslashes($subName) }}' 
                                ? 'bg-sunken' 
                                : 'hover:bg-sunken'"
                            @if($desc) title="{{ $desc }}" @endif>
                            <input 
                                type="radio" 
                                wire:model.live="archiveSubstatus" 
                                value="{{ $subName }}" 
                                class="sr-only">
                            
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="px-2 py-0.5 rounded text-meta font-medium border shrink-0" style="{{ $inlineStyle }}">
                                    {{ $subName }}
                                </span>

                                @if($isDefault)
                                    <span class="text-meta text-ink-3 bg-sunken px-1.5 rounded shrink-0">
                                        {{ __('Default') }}
                                    </span>
                                @endif
                            </div>
                        </label>
                    @empty
                        <div class="p-3 text-center text-body text-ink-3 bg-sunken rounded-control">
                            {{ __('No hay subestatus configurados para archivar.') }}
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="flex items-center justify-end gap-2 pt-2.5 border-t border-line">
                <button 
                    wire:click="closeArchiveModal" 
                    type="button" 
                    class="ui-btn ui-btn-secondary">
                    {{ __('Cancelar') }}
                </button>
                <button 
                    wire:click="confirmArchive" 
                    wire:loading.attr="disabled" 
                    type="button" 
                    class="ui-btn ui-btn-primary disabled:opacity-50">
                    <span wire:loading.remove wire:target="confirmArchive" class="flex items-center gap-1.5">
                        <x-lucide-archive class="w-3.5 h-3.5" />
                        <span>{{ __('Archivar Orden') }}</span>
                    </span>
                    <span wire:loading wire:target="confirmArchive" class="flex items-center gap-1.5">
                        <x-lucide-loader-2 class="w-3.5 h-3.5 animate-spin" />
                        <span>{{ __('Archivando...') }}</span>
                    </span>
                </button>
            </div>
        </div>
    </div>
@endif
