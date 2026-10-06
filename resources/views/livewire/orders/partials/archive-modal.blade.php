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
            class="bg-white border border-[#e9e9e7] rounded-xl shadow-2xl max-w-md w-full p-4 space-y-3 animate-in fade-in zoom-in-95 duration-150"
            @click.outside="$wire.closeArchiveModal()">
            
            <!-- Modal Header -->
            <div class="flex items-center justify-between pb-2.5 border-b border-[#e9e9e7]">
                <div class="flex items-center gap-2 min-w-0">
                    <div class="w-7 h-7 rounded-lg bg-rose-50 border border-rose-200/80 flex items-center justify-center text-rose-600 shrink-0">
                        <x-lucide-archive class="w-3.5 h-3.5" />
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-sm font-bold text-zinc-900 truncate leading-tight">{{ __('Archivar Orden') }}</h3>
                        @if($targetOrder)
                            <p class="text-[11px] text-zinc-500 uppercase truncate font-mono leading-tight">
                                {{ $targetOrder->wo_number ? $targetOrder->wo_number . ' · ' : '' }}{{ $targetOrder->company_name ?? '' }}
                            </p>
                        @else
                            <p class="text-[11px] text-zinc-500 leading-tight">{{ __('Selecciona el subestatus de cierre.') }}</p>
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
            <div class="space-y-2.5 text-xs" x-data="{ search: '' }">
                <div class="flex items-center justify-between">
                    <label class="font-semibold text-zinc-700 block text-xs">{{ __('Razón o subestatus de cierre:') }}</label>
                    <span class="text-[11px] text-zinc-400 font-medium">
                        {{ count($archivedSubstatuses) }} {{ __('disponibles') }}
                    </span>
                </div>

                @if(count($archivedSubstatuses) > 4)
                    <div class="relative">
                        <input 
                            type="text" 
                            x-model="search" 
                            placeholder="{{ __('Buscar subestatus de archivo...') }}" 
                            class="w-full bg-stone-50/80 border border-stone-200 rounded-lg px-2.5 py-1.5 pl-7.5 pr-7 text-xs text-zinc-800 placeholder-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400 focus:bg-white transition"
                        >
                        <x-lucide-search class="w-3.5 h-3.5 text-zinc-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                        <button 
                            type="button" 
                            x-show="search" 
                            @click="search = ''" 
                            class="absolute right-2 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-600 p-0.5">
                            <x-lucide-x class="w-3 h-3" />
                        </button>
                    </div>
                @endif

                <div class="space-y-1 max-h-72 overflow-y-auto pr-0.5 scrollbar-thin">
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
                            x-show="!search || '{{ strtolower(addslashes($subName . ' ' . ($desc ?? ''))) }}'.includes(search.toLowerCase().trim())"
                            class="flex items-center justify-between px-2.5 py-1.5 rounded-lg border cursor-pointer transition select-none text-xs group"
                            :class="$wire.archiveSubstatus === '{{ addslashes($subName) }}' 
                                ? 'bg-rose-50/70 border-rose-300 ring-1 ring-rose-400/40 shadow-2xs' 
                                : 'bg-white border-stone-200/90 hover:bg-stone-50 hover:border-stone-300'"
                            @if($desc) title="{{ $desc }}" @endif>
                            <div class="flex items-center gap-2 min-w-0">
                                <input 
                                    type="radio" 
                                    wire:model.live="archiveSubstatus" 
                                    value="{{ $subName }}" 
                                    class="text-rose-600 focus:ring-rose-500 w-3.5 h-3.5 shrink-0 cursor-pointer">
                                
                                <span class="px-2 py-0.5 rounded text-[11px] font-semibold border shrink-0 tracking-tight" style="{{ $inlineStyle }}">
                                    {{ $subName }}
                                </span>

                                @if($isDefault)
                                    <span class="text-[9px] font-semibold text-zinc-400 bg-stone-100 px-1.5 py-0.2 rounded border border-stone-200 shrink-0 uppercase tracking-tight">
                                        {{ __('Default') }}
                                    </span>
                                @endif
                            </div>

                            <div class="flex items-center gap-1.5 shrink-0 ml-2">
                                <span x-show="$wire.archiveSubstatus === '{{ addslashes($subName) }}'" class="transition">
                                    <x-lucide-check class="w-3.5 h-3.5 text-rose-600 stroke-[2.5]" />
                                </span>
                            </div>
                        </label>
                    @empty
                        <div class="p-3 text-center text-xs text-zinc-500 bg-stone-50 rounded-lg border border-stone-200">
                            {{ __('No hay subestatus configurados para archivar.') }}
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="flex items-center justify-end gap-2 pt-2.5 border-t border-[#e9e9e7]">
                <button 
                    wire:click="closeArchiveModal" 
                    type="button" 
                    class="px-3 py-1.5 rounded-lg border border-stone-200 bg-stone-50 hover:bg-stone-100 text-xs font-medium text-zinc-700 transition cursor-pointer">
                    {{ __('Cancelar') }}
                </button>
                <button 
                    wire:click="confirmArchive" 
                    wire:loading.attr="disabled" 
                    type="button" 
                    class="px-3.5 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-500 text-white font-semibold text-xs shadow-2xs transition flex items-center gap-1.5 cursor-pointer disabled:opacity-50">
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
