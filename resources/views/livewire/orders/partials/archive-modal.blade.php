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
                        <h3 class="text-section font-semibold text-ink truncate">{{ __('Archive Order') }}</h3>
                        @if($targetOrder)
                            <p class="text-meta text-ink-3 truncate">
                                {{ $targetOrder->wo_number ? $targetOrder->wo_number . ' · ' : '' }}{{ $targetOrder->company_name ?? '' }}
                            </p>
                        @else
                            <p class="text-meta text-ink-3">{{ __('Choose a closing status.') }}</p>
                        @endif
                    </div>
                </div>
                <button 
                    wire:click="closeArchiveModal" 
                    type="button" 
                    class="p-1 rounded-control text-ink-3 hover:text-ink hover:bg-sunken transition cursor-pointer"
                    title="{{ __('Close') }}">
                    <x-lucide-x class="w-4 h-4" />
                </button>
            </div>

            <!-- Modal Body -->
            <div class="space-y-2 text-body">
                <div class="flex items-center justify-between">
                    <label class="font-medium text-ink-2 block text-meta">{{ __('Closing reason:') }}</label>
                    <span class="text-meta text-ink-3">
                        {{ count($archivedSubstatuses) }} {{ __('available') }}
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
                                'FINALIZADA !', 'FINALIZADA' => __('Work completed and delivered to client.'),
                                'ORDEN LISTA - ENTREGADA' => __('Work finished and delivered.'),
                                'ORDEN LISTA' => __('Work finished, ready for delivery or closed.'),
                                'CLIENTE NO RESPONSIVE', 'CLIENTE NO RESPONDIO' => __('No response or failed to pick up order after an extended period.'),
                                'CANCELADA' => __('Order voided or cancelled.'),
                                'CANCELADA POR CLIENTE' => __('Cancelled by the client.'),
                                'CANCELADA POR CAMILA' => __('Cancelled internally during review.'),
                                'DEBE' => __('Order finished with an outstanding balance.'),
                                'NO REALIZADA / TRANSFERIDA' => __('Order transferred or not carried out.'),
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
                            {{ __('No closing statuses are set up.') }}
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
                    {{ __('Cancel') }}
                </button>
                <button 
                    wire:click="confirmArchive" 
                    wire:loading.attr="disabled" 
                    type="button" 
                    class="ui-btn ui-btn-primary disabled:opacity-50">
                    <span wire:loading.remove wire:target="confirmArchive" class="flex items-center gap-1.5">
                        <x-lucide-archive class="w-3.5 h-3.5" />
                        <span>{{ __('Archive Order') }}</span>
                    </span>
                    <span wire:loading wire:target="confirmArchive" class="flex items-center gap-1.5">
                        <x-lucide-loader-2 class="w-3.5 h-3.5 animate-spin" />
                        <span>{{ __('Archiving...') }}</span>
                    </span>
                </button>
            </div>
        </div>
    </div>
@endif
