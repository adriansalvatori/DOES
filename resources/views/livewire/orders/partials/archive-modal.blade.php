@if($showArchiveModal)
    @php
        $targetOrder = $targetOrder ?? ($order ?? ($pendingArchiveOrder ?? null));
        $archivedSubstatuses = $archivedSubstatuses ?? \App\Models\Substatus::getArchivedSubstatuses();
    @endphp
    <div 
        class="fixed inset-0 z-[360] bg-black/40 backdrop-blur-xs flex items-center justify-center p-4 animate-in fade-in duration-150" 
        @keydown.window.escape.prevent="$wire.closeArchiveModal()"
        @keydown.window.enter.prevent="$wire.confirmArchive()"
        wire:keydown.escape="closeArchiveModal">
        <div 
            class="bg-white border border-[#e9e9e7] rounded-2xl shadow-2xl max-w-md w-full p-5 space-y-4 animate-in fade-in zoom-in-95 duration-150"
            @click.outside="$wire.closeArchiveModal()">
            
            <!-- Modal Header -->
            <div class="flex items-start justify-between border-b border-[#e9e9e7] pb-3">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="w-8 h-8 rounded-lg bg-rose-50 border border-rose-200 flex items-center justify-center text-rose-600 shrink-0">
                        <x-lucide-archive class="w-4 h-4" />
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-base font-bold text-zinc-900 truncate">{{ __('Archivar Orden') }}</h3>
                        @if($targetOrder)
                            <p class="text-xs text-zinc-500 uppercase truncate font-mono">
                                {{ $targetOrder->wo_number ? $targetOrder->wo_number . ' · ' : '' }}{{ $targetOrder->company_name ?? '' }}
                            </p>
                        @else
                            <p class="text-xs text-zinc-500">{{ __('Selecciona el subestatus de cierre para esta orden.') }}</p>
                        @endif
                    </div>
                </div>
                <button 
                    wire:click="closeArchiveModal" 
                    type="button" 
                    class="p-1 rounded-md text-zinc-400 hover:text-zinc-700 hover:bg-stone-100 transition cursor-pointer"
                    title="{{ __('Cerrar') }}">
                    <x-lucide-x class="w-4 h-4" />
                </button>
            </div>

            <!-- Modal Body -->
            <div class="space-y-3 text-xs" x-data="{ search: '' }">
                <div class="flex items-center justify-between">
                    <label class="font-bold text-zinc-700 block">{{ __('Razón o subestatus de cierre:') }}</label>
                    <span class="text-[11px] text-zinc-400 font-medium">
                        {{ count($archivedSubstatuses) }} {{ __('disponibles') }}
                    </span>
                </div>

                @if(count($archivedSubstatuses) > 5)
                    <div class="relative">
                        <input 
                            type="text" 
                            x-model="search" 
                            placeholder="{{ __('Buscar subestatus de archivo...') }}" 
                            class="w-full bg-stone-50 border border-stone-200 rounded-lg px-2.5 py-1.5 pl-8 text-xs text-zinc-800 placeholder-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400 focus:bg-white transition"
                        >
                        <x-lucide-search class="w-3.5 h-3.5 text-zinc-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                        <button 
                            type="button" 
                            x-show="search" 
                            @click="search = ''" 
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-600">
                            <x-lucide-x class="w-3 h-3" />
                        </button>
                    </div>
                @endif

                <div class="space-y-2 max-h-72 overflow-y-auto pr-1">
                    @forelse($archivedSubstatuses as $sub)
                        @php
                            $subName = $sub instanceof \App\Models\Substatus ? $sub->name : ($sub->value ?? (string) $sub);
                            $subLabel = $sub instanceof \App\Models\Substatus ? $sub->label() : ($sub->label() ?? $subName);
                            $inlineStyle = $sub instanceof \App\Models\Substatus ? $sub->getInlineBadgeStyle() : ($sub->getInlineBadgeStyle() ?? '');
                            $isDefault = (bool) ($sub->is_default ?? false);
                            
                            // Contextual helper descriptions for known substatuses
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

                            // Appropriate icon
                            $icon = match(mb_strtoupper($subName)) {
                                'FINALIZADA !', 'FINALIZADA', 'ORDEN LISTA - ENTREGADA', 'ORDEN LISTA' => 'check-circle-2',
                                'CLIENTE NO RESPONSIVE', 'CLIENTE NO RESPONDIO' => 'user-x',
                                'CANCELADA', 'CANCELADA POR CLIENTE', 'CANCELADA POR CAMILA' => 'x-circle',
                                'DEBE' => 'alert-circle',
                                'NO REALIZADA / TRANSFERIDA' => 'arrow-right-left',
                                default => 'archive',
                            };
                        @endphp

                        <label 
                            x-show="!search || '{{ strtolower(addslashes($subName . ' ' . $subLabel . ' ' . ($desc ?? ''))) }}'.includes(search.toLowerCase().trim())"
                            class="flex items-center justify-between p-2.5 rounded-xl border cursor-pointer transition select-none"
                            :class="$wire.archiveSubstatus === '{{ addslashes($subName) }}' 
                                ? 'bg-rose-50/70 border-rose-300 ring-1 ring-rose-400 shadow-2xs' 
                                : 'bg-[#fbfbfa] border-stone-200 hover:border-stone-300 hover:bg-stone-50/80'">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <input 
                                    type="radio" 
                                    wire:model="archiveSubstatus" 
                                    value="{{ $subName }}" 
                                    class="text-rose-600 focus:ring-rose-500 shrink-0">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="font-bold text-zinc-900 flex items-center gap-1.5">
                                            @if($icon === 'check-circle-2')
                                                <x-lucide-check-circle-2 class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                                            @elseif($icon === 'user-x')
                                                <x-lucide-user-x class="w-3.5 h-3.5 text-amber-600 shrink-0" />
                                            @elseif($icon === 'x-circle')
                                                <x-lucide-x-circle class="w-3.5 h-3.5 text-red-600 shrink-0" />
                                            @elseif($icon === 'alert-circle')
                                                <x-lucide-alert-circle class="w-3.5 h-3.5 text-rose-600 shrink-0" />
                                            @elseif($icon === 'arrow-right-left')
                                                <x-lucide-arrow-right-left class="w-3.5 h-3.5 text-stone-600 shrink-0" />
                                            @else
                                                <x-lucide-archive class="w-3.5 h-3.5 text-zinc-500 shrink-0" />
                                            @endif
                                            <span>{{ $subLabel }}</span>
                                        </span>

                                        @if($inlineStyle)
                                            <span class="px-1.5 py-0.2 rounded text-[10px] font-bold border shrink-0" style="{{ $inlineStyle }}">
                                                {{ $subName }}
                                            </span>
                                        @endif

                                        @if($isDefault)
                                            <span class="px-1 py-0.2 rounded text-[9px] font-semibold bg-stone-200 text-zinc-600 uppercase tracking-tight">
                                                {{ __('Por Defecto') }}
                                            </span>
                                        @endif
                                    </div>

                                    @if($desc)
                                        <span class="text-[11px] text-zinc-500 block mt-0.5 leading-snug">
                                            {{ $desc }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </label>
                    @empty
                        <div class="p-4 text-center text-zinc-500 bg-stone-50 rounded-xl border border-stone-200">
                            {{ __('No hay subestatus configurados para archivar.') }}
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-[#e9e9e7]">
                <button 
                    wire:click="closeArchiveModal" 
                    type="button" 
                    class="px-3.5 py-1.5 rounded-lg border border-stone-200 bg-stone-50 hover:bg-stone-100 text-xs font-medium text-zinc-700 transition cursor-pointer">
                    {{ __('Cancelar') }}
                </button>
                <button 
                    wire:click="confirmArchive" 
                    wire:loading.attr="disabled" 
                    type="button" 
                    class="px-4 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs shadow-2xs transition flex items-center gap-1.5 cursor-pointer disabled:opacity-50">
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
