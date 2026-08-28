<div>
    <!-- Notion Side Flyout Drawer (Light Mode Panel) -->
    @if($showModal && $order)
        <div 
            x-data="{
                initialForm: null,
                init() {
                    this.snapshot();
                    this.$watch('$wire.isEditing', (val) => {
                        if (val) this.snapshot();
                    });
                    this.$watch('$wire.showModal', (show) => {
                        if (!show) {
                            window.KudosDirtyGuard.unregister('order-detail-modal');
                        } else {
                            window.KudosDirtyGuard.register('order-detail-modal', () => this.isDirty(), this.$el);
                        }
                    });
                    window.KudosDirtyGuard.register('order-detail-modal', () => this.isDirty(), this.$el);
                    this.$cleanup(() => window.KudosDirtyGuard.unregister('order-detail-modal'));
                },
                snapshot() {
                    this.initialForm = JSON.stringify({
                        wo: ($wire.editWoNumber || '').toString().trim(),
                        trelloId: ($wire.editTrelloCardId || '').toString().trim(),
                        company: ($wire.editCompanyName || '').toString().trim(),
                        location: ($wire.editLocationName || '').toString().trim(),
                        resp: ($wire.editResponsiblePerson || '').toString().trim(),
                        task: ($wire.editTaskName || '').toString().trim(),
                        designers: Array.from($wire.editDesignerIds || []).map(String).sort(),
                        status: ($wire.editCoreStatus || '').toString().trim(),
                        substatus: ($wire.editSubstatus || '').toString().trim(),
                        due: ($wire.editDueDate || '').toString().trim(),
                        clientRev: Number($wire.editClientRevisionCount || 0),
                        internalRev: Number($wire.editInternalRevisionCount || 0)
                    });
                },
                isEditDirty() {
                    if (!$wire.isEditing || !this.initialForm) return false;
                    const current = JSON.stringify({
                        wo: ($wire.editWoNumber || '').toString().trim(),
                        trelloId: ($wire.editTrelloCardId || '').toString().trim(),
                        company: ($wire.editCompanyName || '').toString().trim(),
                        location: ($wire.editLocationName || '').toString().trim(),
                        resp: ($wire.editResponsiblePerson || '').toString().trim(),
                        task: ($wire.editTaskName || '').toString().trim(),
                        designers: Array.from($wire.editDesignerIds || []).map(String).sort(),
                        status: ($wire.editCoreStatus || '').toString().trim(),
                        substatus: ($wire.editSubstatus || '').toString().trim(),
                        due: ($wire.editDueDate || '').toString().trim(),
                        clientRev: Number($wire.editClientRevisionCount || 0),
                        internalRev: Number($wire.editInternalRevisionCount || 0)
                    });
                    return current !== this.initialForm;
                },
                isCommentDirty() {
                    if (!$wire.showModal) return false;
                    const c = ($wire.newTrelloComment || '').trim();
                    return c.length > 0;
                },
                isDirty() {
                    if (!$wire.showModal) return false;
                    return this.isEditDirty() || this.isCommentDirty();
                },
                confirmClose(action) {
                    if (window.KudosDirtyGuard && window.KudosDirtyGuard.isConfirmModalOpen) {
                        return;
                    }
                    let title = '¿Guardar cambios de la orden?';
                    let description = 'Tienes información editada en la orden sin guardar.';

                    if (this.isEditDirty() && this.isCommentDirty()) {
                        title = '¿Guardar cambios y publicar comentario?';
                        description = 'Tienes información editada en los campos de la orden y un borrador de comentario.';
                    } else if (this.isEditDirty()) {
                        title = '¿Guardar edición de la orden?';
                        description = 'Tienes cambios realizados en los campos de la orden sin guardar.';
                    } else if (this.isCommentDirty()) {
                        title = '¿Publicar borrador de comentario?';
                        description = 'Tienes un borrador de comentario escrito en la tarjeta.';
                    }

                    if (this.isDirty()) {
                        window.KudosDirtyGuard.openConfirmModal({
                            title: title,
                            description: description,
                            cancelText: 'Cancelar',
                            discardText: 'No guardar',
                            saveText: 'Guardar',
                            onCancel: () => {},
                            onDiscard: () => {
                                window.KudosDirtyGuard.unregister('order-detail-modal');
                                action();
                            },
                            onSave: () => {
                                window.KudosDirtyGuard.unregister('order-detail-modal');
                                if (this.isEditDirty()) {
                                    $wire.saveOrder(false);
                                }
                                if (this.isCommentDirty()) {
                                    $wire.postComment();
                                }
                                action();
                            }
                        });
                    } else {
                        window.KudosDirtyGuard.unregister('order-detail-modal');
                        action();
                    }
                }
            }"
            @keydown.window.escape="confirmClose(() => $wire.closeModal())"
            class="fixed inset-0 z-[100] flex"
        >
            <!-- Backdrop Overlay -->
            <div @click="confirmClose(() => $wire.closeModal())" class="fixed inset-0 z-[100] bg-black/30 backdrop-blur-xs transition-opacity"></div>

            <!-- Slide-over Right Panel (Fully Responsive Width) -->
            <div class="fixed inset-y-0 right-0 z-[100] w-full sm:max-w-xl md:max-w-2xl lg:max-w-3xl bg-white border-l border-[#e9e9e7] shadow-2xl flex flex-col animate-in slide-in-from-right duration-200 overflow-x-hidden">
            
            <!-- Flyout Header (Notion Page Header) -->
            <div class="px-4 sm:px-6 py-4 border-b border-[#e9e9e7] bg-white sticky top-0 z-20 space-y-3">
                
                <!-- TOP UTILITY ACTION BAR (Fluid flex wrapping) -->
                <div class="flex flex-wrap items-center justify-between gap-2.5">
                    <!-- Left: Clean WO & Creation Timestamp Tag -->
                    <div class="flex items-center gap-2 shrink-0">
                        @if($order->wo_number)
                            <x-wo-badge :number="$order->wo_number" variant="dark" class="px-2 py-0.5 text-[11px] tracking-wide" show-copy-icon />
                        @else
                            <span class="px-2 py-0.5 rounded font-mono text-[11px] font-semibold bg-stone-100 text-zinc-500 border border-stone-200">
                                SIN WO
                            </span>
                        @endif

                        @if($order->is_missing_from_trello)
                            <span class="px-2 py-0.5 rounded font-mono text-[11px] font-bold bg-stone-200 text-stone-700 border border-stone-300 flex items-center gap-1 shadow-2xs">
                                <x-lucide-alert-triangle class="w-3 h-3 text-stone-600" />
                                <span>FALTA EN TRELLO</span>
                            </span>
                        @endif

                        <span class="text-zinc-300">•</span>

                        <span class="text-xs font-mono text-zinc-500 flex items-center gap-1">
                            <x-lucide-clock class="w-3.5 h-3.5 text-zinc-400 shrink-0" />
                            <span>{{ $order->trello_created_at ? $order->trello_created_at->format('d M, Y (H:i)') : 'N/A' }}</span>
                        </span>
                    </div>

                    <!-- Right: Action Buttons (Fluid flex wrapping) -->
                    <div class="flex flex-wrap items-center gap-1.5 min-w-0 justify-end flex-1">
                        @if($order->trello_url)
                            <a href="{{ $order->trello_url }}" target="_blank" rel="noopener noreferrer" 
                               class="px-2 py-1 sm:px-2.5 sm:py-1.5 rounded-lg bg-[#f7f7f5] hover:bg-stone-200 text-zinc-700 font-medium text-xs border border-[#e9e9e7] transition flex items-center gap-1.5 shrink-0" title="Abrir en Trello">
                                <x-lucide-external-link class="w-3.5 h-3.5 text-zinc-500 shrink-0" />
                                <span class="hidden xs:inline">Ver en Trello</span>
                                <span class="xs:hidden">Trello</span>
                            </a>
                        @endif

                        @if(!$isEditing)
                            <button wire:click="startEditing" class="px-2 py-1 sm:px-2.5 sm:py-1.5 rounded-lg bg-[#f7f7f5] hover:bg-stone-200 text-zinc-700 font-medium text-xs border border-[#e9e9e7] transition flex items-center gap-1.5 shrink-0" title="Editar campos">
                                <x-lucide-pencil class="w-3.5 h-3.5 text-zinc-500 shrink-0" />
                                <span>Editar</span>
                            </button>
                            <button wire:click="$dispatch('open-duplicate-order', { orderId: {{ $order->id }} })" class="px-2 py-1 sm:px-2.5 sm:py-1.5 rounded-lg bg-[#f7f7f5] hover:bg-stone-200 text-zinc-700 font-medium text-xs border border-[#e9e9e7] transition flex items-center gap-1.5 shrink-0" title="Duplicar esta orden">
                                <x-lucide-copy class="w-3.5 h-3.5 text-zinc-500 shrink-0" />
                                <span class="hidden sm:inline">Duplicar</span>
                            </button>
                        @endif

                        @if(!$order->in_workspace)
                            <button wire:click="addToWorkspaceDirectly" class="px-2.5 py-1 sm:px-3 sm:py-1.5 rounded-lg bg-zinc-900 hover:bg-zinc-800 text-white font-medium text-xs transition flex items-center gap-1.5 shadow-2xs shrink-0">
                                <x-lucide-plus-circle class="w-3.5 h-3.5 text-emerald-400 shrink-0" />
                                <span>Añadir a Workspace</span>
                            </button>
                        @else
                            <button wire:click="moveToBacklog" wire:confirm="¿Mover esta orden de regreso al Backlog?" class="px-2 py-1 sm:px-2.5 sm:py-1.5 rounded-lg bg-[#f7f7f5] hover:bg-stone-200 text-zinc-700 font-medium text-xs border border-[#e9e9e7] transition flex items-center gap-1.5 shrink-0">
                                <x-lucide-archive class="w-3.5 h-3.5 text-zinc-500 shrink-0" />
                                <span>A Backlog</span>
                            </button>
                        @endif

                        @if($order->isBlocked())
                            <button wire:click="openUnblockModal" class="px-2.5 py-1 sm:px-3 sm:py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs transition flex items-center gap-1.5 shadow-2xs shrink-0 cursor-pointer">
                                <x-lucide-unlock class="w-3.5 h-3.5 shrink-0" />
                                <span>Desbloquear</span>
                            </button>
                        @endif

                        @if(!$order->approved || in_array($order->core_status, [\App\Enums\CoreStatus::ENVIADO_AL_CLIENTE, \App\Enums\CoreStatus::ENVIADO_A_CAMILA]))
                            <button wire:click="$set('showApprovalModal', true)" class="px-2.5 py-1 sm:px-3 sm:py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-medium text-xs transition flex items-center gap-1.5 shadow-2xs shrink-0">
                                <x-lucide-check-circle-2 class="w-3.5 h-3.5 shrink-0" />
                                <span>Aprobar</span>
                            </button>
                        @endif

                        <!-- Move to Trashcan Button -->
                        <button 
                            wire:click="deleteOrder" 
                            wire:confirm="¿Estás seguro de mover la orden '{{ $order->company_name }}' a la Papelera de Reciclaje?" 
                            class="px-2 py-1 sm:px-2.5 sm:py-1.5 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 font-medium text-xs border border-rose-200 transition flex items-center gap-1.5 shrink-0 cursor-pointer" 
                            title="Mover a la Papelera de Reciclaje">
                            <x-lucide-trash-2 class="w-3.5 h-3.5 text-rose-600 shrink-0" />
                            <span class="hidden sm:inline">Enviar a Papelera</span>
                            <span class="sm:hidden">Papelera</span>
                        </button>

                        <div class="h-4 w-px bg-stone-200 mx-0.5 hidden sm:block"></div>

                        <button @click="confirmClose(() => $wire.closeModal())" class="p-1.5 rounded-lg text-zinc-400 hover:text-zinc-700 hover:bg-stone-100 transition shrink-0 cursor-pointer" title="Cerrar panel">
                            <x-lucide-x class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                <!-- MAIN TITLE & PROPERTY BADGES BLOCK -->
                <div class="space-y-2 min-w-0 flex items-start gap-3">
                    <button 
                        wire:click="toggleDoneToday" 
                        type="button"
                        class="w-5 h-5 mt-1 rounded-full border transition flex items-center justify-center shrink-0 cursor-pointer {{ $order->done_today ? 'bg-emerald-500 border-emerald-500 text-white shadow-2xs' : 'border-stone-300 hover:border-emerald-500 bg-white text-transparent hover:text-emerald-500/40' }}"
                        title="{{ $order->done_today ? 'Completado (Clic para desmarcar)' : 'Marcar como completado' }}">
                        <x-lucide-check class="w-3 h-3 stroke-[3]" />
                    </button>
                    <div class="space-y-1 min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-2 flex-wrap">
                            <div class="flex items-center gap-2 flex-wrap min-w-0">
                                <h2 class="text-lg sm:text-xl font-bold text-zinc-900 tracking-tight leading-snug break-words {{ $order->done_today ? 'line-through text-zinc-400' : '' }}">
                                    {{ $order->company_name }}
                                </h2>

                                @if($order->location_name || $order->clientLocation)
                                    <span class="px-2 py-0.5 rounded-md text-xs font-semibold bg-[#f7f7f5] text-zinc-700 border border-[#e9e9e7] inline-flex items-center gap-1 shrink-0" title="Locación del Cliente">
                                        <x-lucide-map-pin class="w-3.5 h-3.5 text-rose-500 shrink-0" />
                                        <span>{{ $order->location_name ?: $order->clientLocation?->name }}</span>
                                    </span>
                                @endif
                            </div>

                            @if($order->isBlocked() || $order->core_status === \App\Enums\CoreStatus::ENTRANTE)
                                <button 
                                    wire:click="openUnblockModal" 
                                    type="button"
                                    class="px-3.5 py-1.5 rounded-xl bg-orange-500 hover:bg-orange-600 active:bg-orange-700 text-white font-bold text-xs shadow-md transition flex items-center gap-1.5 shrink-0 cursor-pointer opacity-100 filter-none"
                                    title="Desbloquear orden">
                                    <x-lucide-unlock class="w-4 h-4 text-white stroke-[2.5]" />
                                    <span>Desbloquear</span>
                                </button>
                            @endif
                        </div>

                        @if($order->task_name)
                            <p class="text-xs text-zinc-500 font-normal leading-relaxed break-words {{ $order->done_today ? 'line-through text-zinc-400' : '' }}">
                                {{ $order->task_name }}
                            </p>
                        @endif
                    </div>
                </div>

                <!-- Clean Property Badges Row -->
                <div class="flex flex-wrap items-center gap-1.5 pt-1">
                    <!-- Status Badge -->
                    <span class="px-2.5 py-1 rounded-md text-[11px] font-semibold bg-stone-100 border border-stone-200 text-zinc-800 flex items-center gap-1.5">
                        <x-lucide-layers class="w-3.5 h-3.5 text-zinc-500 shrink-0" />
                        <span>{{ $order->core_status->label() }}</span>
                    </span>

                    <!-- Responsible Contact Badge -->
                    @if($order->responsible_person)
                        <span class="px-2.5 py-1 rounded-md text-[11px] font-bold bg-indigo-50 text-indigo-800 border border-indigo-200 flex items-center gap-1.5">
                            <x-lucide-user class="w-3.5 h-3.5 text-indigo-600 shrink-0" />
                            <span>{{ $order->responsible_person }}</span>
                        </span>
                    @endif

                    <!-- Backlog Status Badge -->
                    @if(!$order->in_workspace)
                        <span class="px-2.5 py-1 rounded-md text-[11px] font-medium bg-amber-50 text-amber-800 border border-amber-200 flex items-center gap-1.5">
                            <x-lucide-box class="w-3.5 h-3.5 text-amber-600 shrink-0" />
                            <span>En Backlog</span>
                        </span>
                    @endif

                    <!-- Substatus Badge -->
                    @if($order->substatus)
                        <span class="px-2.5 py-1 rounded-md text-[11px] font-medium border flex items-center gap-1.5 {{ $order->substatus->badgeStyle() }}">
                            <x-lucide-alert-circle class="w-3.5 h-3.5 shrink-0" />
                            <span>{{ $order->substatus->value }}</span>
                        </span>
                    @endif
                </div>
            </div>

            <!-- Flash Notification Banner -->
            @if (session()->has('message'))
                <div class="bg-emerald-50 border-b border-emerald-200 text-emerald-800 px-5 py-2 text-xs font-medium flex items-center gap-2 shrink-0">
                    <x-lucide-check-circle-2 class="w-4 h-4 text-emerald-600 shrink-0" />
                    <span>{{ session('message') }}</span>
                </div>
            @endif

            <!-- Flyout Body (Scrollable Notion Page Content) -->
            <div class="p-4 sm:p-5 overflow-y-auto flex-1 space-y-5 scrollbar-thin">

                <!-- Pending WO Update Alert Banner -->
                @if($order->pending_wo_number)
                    <div class="bg-amber-50/90 border-2 border-amber-400 rounded-xl p-4 shadow-sm space-y-3 shrink-0 ring-2 ring-amber-200/50 animate-in fade-in zoom-in duration-150">
                        <div class="flex items-start gap-3">
                            <div class="p-2 rounded-xl bg-amber-500 text-white shrink-0 shadow-2xs">
                                <x-lucide-alert-triangle class="w-5 h-5 stroke-[2.5]" />
                            </div>
                            <div class="min-w-0 flex-1 space-y-1">
                                <div class="flex items-center justify-between gap-2 flex-wrap">
                                    <h4 class="font-bold text-xs text-amber-950 uppercase tracking-wider flex items-center gap-1.5">
                                        {{ __('Actualización de WO desde Trello disponible') }}
                                    </h4>
                                    <span class="px-2 py-0.5 rounded-full bg-amber-200/70 border border-amber-300 text-[10px] font-bold text-amber-900">
                                        {{ __('Requiere decisión') }}
                                    </span>
                                </div>
                                <p class="text-xs text-amber-900 leading-relaxed">
                                    Se detectó un nuevo número de WO en Trello: <x-wo-badge :number="$order->pending_wo_number" variant="amber" show-copy-icon /> <span class="text-[11px] text-amber-800">(Trello card title)</span>.
                                </p>
                                <p class="text-xs text-amber-900 leading-relaxed">
                                    El número registrado actualmente en DOES es: @if($order->wo_number)<x-wo-badge :number="$order->wo_number" variant="amber" show-copy-icon />@else<strong class="font-mono bg-white px-1.5 py-0.5 rounded border border-amber-300 text-amber-950 font-bold shadow-2xs">Sin WO / WO 0000</strong>@endif <span class="text-[11px] text-amber-800">(DOES)</span>.
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center justify-end gap-2 pt-2 border-t border-amber-200">
                            <button 
                                type="button" 
                                wire:click="dismissPendingWo" 
                                class="px-3 py-1.5 rounded-lg border border-amber-300 bg-white hover:bg-amber-100/70 text-amber-900 font-semibold text-xs transition cursor-pointer shadow-2xs flex items-center gap-1">
                                <x-lucide-x class="w-3.5 h-3.5 text-amber-700" />
                                <span>{{ __('Conservar') }} {{ $order->wo_number ?: 'actual' }} (DOES)</span>
                            </button>

                            <button 
                                type="button" 
                                wire:click="acceptPendingWo" 
                                class="px-3.5 py-1.5 rounded-lg bg-amber-900 hover:bg-amber-800 active:bg-amber-950 text-white font-bold text-xs shadow-2xs transition flex items-center gap-1.5 cursor-pointer">
                                <x-lucide-check class="w-4 h-4 text-emerald-400 stroke-[3]" />
                                <span>{{ __('Actualizar a') }} {{ $order->pending_wo_number }} (Trello)</span>
                            </button>
                        </div>
                    </div>
                @endif
                
                @if($isEditing)
                    <!-- EDIT FORM MODE -->
                    <div class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-xl p-4 space-y-4">
                        <div class="flex items-center justify-between border-b border-[#e9e9e7] pb-2">
                            <h4 class="font-semibold text-xs text-zinc-900 uppercase tracking-wider flex items-center gap-1.5">
                                <x-lucide-edit-3 class="w-4 h-4 text-zinc-700" /> Editar Información de la Orden
                            </h4>
                            <span class="text-[10px] text-zinc-400">Modifica fechas, diseñador, WO o campos disectados</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 text-xs">
                            <!-- WO Number -->
                            <div class="space-y-1">
                                <label class="font-medium text-zinc-700 block">Número de Orden:</label>
                                <div class="flex rounded-md shadow-2xs">
                                    <span class="inline-flex items-center px-2.5 rounded-l-md border border-r-0 border-[#e9e9e7] bg-stone-100 text-zinc-600 font-mono font-bold text-xs select-none">
                                        WO
                                    </span>
                                    <input type="text" wire:model="editWoNumber" placeholder="16253" class="bg-white border border-[#e9e9e7] rounded-r-md px-3 py-1.5 text-xs text-zinc-900 focus:outline-none w-full font-mono font-semibold">
                                </div>
                            </div>

                            <!-- Trello Card ID / Link (Custom Searchable Dropdown) -->
                            <div class="space-y-1 relative" 
                                 x-data="{ 
                                     open: false,
                                     selectCard(id) {
                                         $wire.set('editTrelloCardId', id);
                                         this.open = false;
                                     }
                                 }"
                                 x-dropdown-nav>
                                <label class="font-medium text-zinc-700 block flex items-center gap-1">
                                    <x-lucide-external-link class="w-3.5 h-3.5 text-blue-600" />
                                    <span>ID / Link Tarjeta Trello:</span>
                                </label>
                                
                                <div class="relative">
                                    <input 
                                        type="text" 
                                        wire:model.live="editTrelloCardId" 
                                        @focus="open = true"
                                        @click.outside="open = false"
                                        autocomplete="off"
                                        placeholder="Ej. AbCdEf12 o buscar por empresa/WO..." 
                                        class="bg-white border border-[#e9e9e7] rounded-md px-3 py-1.5 text-xs text-zinc-900 focus:outline-none w-full font-mono pr-7">
                                    
                                    <button 
                                        type="button" 
                                        @click="open = !open" 
                                        class="absolute right-2 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-600 p-0.5"
                                        title="Ver tarjetas de Trello disponibles">
                                        <x-lucide-chevron-down class="w-3.5 h-3.5" />
                                    </button>
                                </div>

                                <!-- Custom Searchable Dropdown Popup -->
                                <div 
                                    x-show="open" 
                                    x-transition:enter="transition ease-out duration-100"
                                    x-transition:enter-start="opacity-0 scale-95"
                                    x-transition:enter-end="opacity-100 scale-100"
                                    class="absolute left-0 right-0 top-full mt-1 z-50 bg-white border border-[#e9e9e7] rounded-lg shadow-xl max-h-48 overflow-y-auto divide-y divide-stone-100 text-xs">
                                    
                                    <div class="px-2.5 py-1 bg-stone-50 border-b border-stone-100 font-bold text-[10px] uppercase text-zinc-400">
                                        Tarjetas de Trello Disponibles ({{ count($availableTrelloCards) }})
                                    </div>

                                    @forelse($availableTrelloCards as $tc)
                                        <button 
                                            type="button"
                                            x-show="!$wire.editTrelloCardId || '{{ strtolower(addslashes($tc->trello_card_id . ' ' . $tc->wo_number . ' ' . $tc->company_name . ' ' . $tc->task_name . ' ' . $tc->trello_title)) }}'.includes(($wire.editTrelloCardId || '').toLowerCase())"
                                            @click="selectCard('{{ $tc->trello_card_id }}')" 
                                            class="w-full text-left p-2 hover:bg-blue-50/70 focus:bg-blue-50 focus:outline-none cursor-pointer flex items-center justify-between gap-2 transition">
                                            <div class="min-w-0">
                                                <span class="font-bold text-zinc-900 block truncate text-[11px]">
                                                    {{ $tc->trello_title ?: ($tc->company_name ?: 'Tarjeta Trello') }}
                                                </span>
                                                @if($tc->task_name && $tc->task_name !== $tc->trello_title)
                                                    <span class="text-[10px] text-zinc-500 block truncate">{{ $tc->task_name }}</span>
                                                @endif
                                            </div>
                                            <span class="font-mono text-[10px] text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded border border-blue-200 shrink-0">
                                                {{ substr($tc->trello_card_id, 0, 8) }}...
                                            </span>
                                        </button>
                                    @empty
                                        <div class="p-3 text-center text-zinc-400 italic text-[11px]">No hay tarjetas registradas aún.</div>
                                    @endforelse
                                </div>
                            </div>

                            <!-- Responsible Person (Searchable Dropdown Menu) -->
                            <div class="space-y-1 relative" 
                                 x-data="{ 
                                     open: false,
                                     selectResp(resp) {
                                         $wire.set('editResponsiblePerson', resp);
                                         this.open = false;
                                     }
                                 }"
                                 x-dropdown-nav>
                                <label class="font-medium text-zinc-700 block">Persona Responsable / Cliente:</label>
                                <div class="relative">
                                    <input 
                                        type="text" 
                                        wire:model.live="editResponsiblePerson" 
                                        @focus="open = true"
                                        @click.outside="open = false"
                                        autocomplete="off"
                                        placeholder="Ej: MARCELA o buscar..." 
                                        class="bg-white border border-[#e9e9e7] rounded-md px-3 py-1.5 text-xs text-zinc-900 focus:outline-none w-full pr-7 font-semibold">
                                    
                                    <button 
                                        type="button" 
                                        @click="open = !open" 
                                        class="absolute right-2 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-600 p-0.5"
                                        title="Ver lista de responsables">
                                        <x-lucide-chevron-down class="w-3.5 h-3.5" />
                                    </button>
                                </div>

                                <div 
                                    x-show="open" 
                                    x-transition:enter="transition ease-out duration-100"
                                    x-transition:enter-start="opacity-0 scale-95"
                                    x-transition:enter-end="opacity-100 scale-100"
                                    class="absolute left-0 right-0 top-full mt-1 z-50 bg-white border border-[#e9e9e7] rounded-lg shadow-xl max-h-48 overflow-y-auto divide-y divide-stone-100 text-xs">
                                    
                                    @if(!empty($clientContacts))
                                        <div class="px-2.5 py-1 bg-emerald-50/80 border-b border-emerald-100 font-bold text-[10px] uppercase text-emerald-800 flex items-center justify-between">
                                            <span>Contactos del cliente</span>
                                            <x-lucide-user class="w-3 h-3 text-emerald-600" />
                                        </div>
                                        @foreach($clientContacts as $cResp)
                                            <button 
                                                type="button"
                                                x-show="!$wire.editResponsiblePerson || '{{ strtolower(addslashes($cResp)) }}'.includes(($wire.editResponsiblePerson || '').toLowerCase())"
                                                @click="selectResp('{{ addslashes($cResp) }}')" 
                                                class="w-full text-left p-2 hover:bg-emerald-50 focus:bg-emerald-50 focus:outline-none cursor-pointer font-bold text-zinc-900 transition flex items-center justify-between">
                                                <span>{{ $cResp }}</span>
                                                <span class="text-[10px] text-emerald-600 font-medium">(Registrado)</span>
                                            </button>
                                        @endforeach
                                    @endif

                                    @php
                                        $otherResponsibles = array_diff($existingResponsibles->toArray(), $clientContacts ?? []);
                                    @endphp

                                    @if(!empty($otherResponsibles))
                                        @if(!empty($clientContacts))
                                            <div class="px-2.5 py-1 bg-stone-50 border-b border-stone-100 font-bold text-[10px] uppercase text-zinc-400">
                                                Otros responsables
                                            </div>
                                        @endif
                                        @foreach($otherResponsibles as $resp)
                                            <button 
                                                type="button"
                                                x-show="!$wire.editResponsiblePerson || '{{ strtolower(addslashes($resp)) }}'.includes(($wire.editResponsiblePerson || '').toLowerCase())"
                                                @click="selectResp('{{ addslashes($resp) }}')" 
                                                class="w-full text-left p-2 hover:bg-stone-100 focus:bg-stone-100 focus:outline-none cursor-pointer font-medium text-zinc-800 transition">
                                                {{ $resp }}
                                            </button>
                                        @endforeach
                                    @endif

                                    @if(empty($clientContacts) && empty($otherResponsibles))
                                        <div class="p-2.5 text-zinc-400 italic text-[11px]">Escribe un nuevo nombre...</div>
                                    @endif
                                </div>
                            </div>

                            <!-- Empresa (Searchable Dropdown) -->
                            <div class="space-y-1 relative" 
                                 x-data="{ 
                                     open: false,
                                     selectComp(comp) {
                                         $wire.set('editCompanyName', comp);
                                         this.open = false;
                                     }
                                 }"
                                 x-dropdown-nav>
                                <label class="font-medium text-zinc-700 block">Nombre de Empresa:</label>
                                <div class="relative">
                                    <input 
                                        type="text" 
                                        wire:model.live="editCompanyName" 
                                        @focus="open = true"
                                        @click.outside="open = false"
                                        autocomplete="off"
                                        placeholder="Ej: TAQUERIA LA CHULA..." 
                                        class="bg-white border border-[#e9e9e7] rounded-md px-3 py-1.5 text-xs text-zinc-900 focus:outline-none w-full pr-7 font-semibold">
                                    
                                    <button 
                                        type="button" 
                                        @click="open = !open" 
                                        class="absolute right-2 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-600 p-0.5">
                                        <x-lucide-chevron-down class="w-3.5 h-3.5" />
                                    </button>
                                </div>

                                <div 
                                    x-show="open" 
                                    x-transition:enter="transition ease-out duration-100"
                                    x-transition:enter-start="opacity-0 scale-95"
                                    x-transition:enter-end="opacity-100 scale-100"
                                    class="absolute left-0 right-0 top-full mt-1 z-50 bg-white border border-[#e9e9e7] rounded-lg shadow-xl max-h-40 overflow-y-auto divide-y divide-stone-100 text-xs">
                                    @forelse($existingCompanies as $comp)
                                        <button 
                                            type="button"
                                            x-show="!$wire.editCompanyName || '{{ strtolower(addslashes($comp)) }}'.includes(($wire.editCompanyName || '').toLowerCase())"
                                            @click="selectComp('{{ addslashes($comp) }}')" 
                                            class="w-full text-left p-2 hover:bg-stone-100 focus:bg-stone-100 focus:outline-none cursor-pointer font-bold text-zinc-900 transition">
                                            {{ $comp }}
                                        </button>
                                    @empty
                                        <div class="p-2.5 text-zinc-400 italic text-[11px]">Escribe un nuevo nombre de empresa...</div>
                                    @endforelse
                                </div>
                            </div>

                            <!-- Locación / Sede (Searchable Dropdown Menu) -->
                            <div class="space-y-1 relative" 
                                 x-data="{ 
                                     open: false,
                                     selectLoc(loc) {
                                         $wire.set('editLocationName', loc);
                                         this.open = false;
                                     }
                                 }"
                                 x-dropdown-nav>
                                <label class="font-medium text-zinc-700 block">Locación / Sede (Opcional):</label>
                                <div class="relative">
                                    <input 
                                        type="text" 
                                        wire:model.live="editLocationName" 
                                        @focus="open = true"
                                        @click.outside="open = false"
                                        autocomplete="off"
                                        placeholder="Ej: TALPA 8, SEDE NORTE..." 
                                        class="bg-white border border-[#e9e9e7] rounded-md px-3 py-1.5 text-xs text-zinc-900 focus:outline-none w-full pr-7 uppercase font-semibold text-emerald-700">
                                    
                                    <button 
                                        type="button" 
                                        @click="open = !open" 
                                        class="absolute right-2 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-600 p-0.5"
                                        title="Ver locaciones disponibles">
                                        <x-lucide-chevron-down class="w-3.5 h-3.5" />
                                    </button>
                                </div>

                                <div 
                                    x-show="open" 
                                    x-transition:enter="transition ease-out duration-100"
                                    x-transition:enter-start="opacity-0 scale-95"
                                    x-transition:enter-end="opacity-100 scale-100"
                                    class="absolute left-0 right-0 top-full mt-1 z-50 bg-white border border-[#e9e9e7] rounded-lg shadow-xl max-h-48 overflow-y-auto divide-y divide-stone-100 text-xs">
                                    
                                    @if(!empty($clientLocations))
                                        <div class="px-2.5 py-1 bg-emerald-50/80 border-b border-emerald-100 font-bold text-[10px] uppercase text-emerald-800 flex items-center justify-between">
                                            <span>Locaciones del cliente</span>
                                            <x-lucide-map-pin class="w-3 h-3 text-rose-500" />
                                        </div>
                                        @foreach($clientLocations as $cLoc)
                                            <button 
                                                type="button"
                                                x-show="!$wire.editLocationName || '{{ strtolower(addslashes($cLoc)) }}'.includes(($wire.editLocationName || '').toLowerCase())"
                                                @click="selectLoc('{{ addslashes($cLoc) }}')" 
                                                class="w-full text-left p-2 hover:bg-emerald-50 focus:bg-emerald-50 focus:outline-none cursor-pointer font-bold text-zinc-900 uppercase transition flex items-center justify-between">
                                                <span>{{ $cLoc }}</span>
                                                <span class="text-[10px] text-emerald-600 font-medium normal-case">(Registrada)</span>
                                            </button>
                                        @endforeach
                                    @endif

                                    @php
                                        $otherLocations = array_diff($existingLocations->toArray(), $clientLocations ?? []);
                                    @endphp

                                    @if(!empty($otherLocations))
                                        @if(!empty($clientLocations))
                                            <div class="px-2.5 py-1 bg-stone-50 border-b border-stone-100 font-bold text-[10px] uppercase text-zinc-400">
                                                Otras locaciones
                                            </div>
                                        @endif
                                        @foreach($otherLocations as $loc)
                                            <button 
                                                type="button"
                                                x-show="!$wire.editLocationName || '{{ strtolower(addslashes($loc)) }}'.includes(($wire.editLocationName || '').toLowerCase())"
                                                @click="selectLoc('{{ addslashes($loc) }}')" 
                                                class="w-full text-left p-2 hover:bg-stone-100 focus:bg-stone-100 focus:outline-none cursor-pointer font-semibold text-zinc-800 uppercase transition">
                                                {{ $loc }}
                                            </button>
                                        @endforeach
                                    @endif

                                    @if(empty($clientLocations) && empty($otherLocations))
                                        <div class="p-2.5 text-zinc-400 italic text-[11px]">Escribe una nueva locación...</div>
                                    @endif
                                </div>
                            </div>

                            <!-- Tarea -->
                            <div class="space-y-1">
                                <label class="font-medium text-zinc-700 block">Tarea de Diseño / Trabajo:</label>
                                <input type="text" wire:model="editTaskName" class="bg-white border border-[#e9e9e7] rounded-md px-3 py-1.5 text-xs text-zinc-900 focus:outline-none w-full">
                            </div>

                            <!-- Diseñadores -->
                            <div class="space-y-1.5 sm:col-span-2">
                                <label class="font-medium text-zinc-700 block">Diseñadores Asignados (Soporta múltiples):</label>
                                <div class="flex flex-wrap items-center gap-1.5 p-2 bg-stone-50 border border-[#e9e9e7] rounded-md min-h-[38px]">
                                    @foreach($designers as $des)
                                        @php $isAssigned = in_array((int)$des->id, array_map('intval', $editDesignerIds)); @endphp
                                        <button 
                                            type="button"
                                            wire:click="toggleDesigner({{ $des->id }})"
                                            class="px-2 py-0.5 rounded text-[11px] font-semibold border transition flex items-center gap-1 cursor-pointer {{ $isAssigned ? $des->badge_style : 'bg-white text-zinc-500 border-stone-200 hover:bg-stone-100' }}"
                                        >
                                            <span class="w-2 h-2 rounded-full {{ $des->dot_color_class }}"></span>
                                            <span>{{ $des->name }}</span>
                                            @if($isAssigned)
                                                <x-lucide-check class="w-3 h-3 text-current stroke-[3]" />
                                            @endif
                                        </button>
                                    @endforeach
                                </div>
                                <span class="text-[10px] text-zinc-400 block">* Si seleccionas Diseñador Externo, se agregará Euralíz automáticamente.</span>
                            </div>

                            <!-- Fecha Límite -->
                            <div class="space-y-1">
                                <label class="font-medium text-zinc-700 block">Fecha Límite (Due Date):</label>
                                <div class="flex items-center gap-1.5">
                                    <input type="date" wire:model="editDueDate" class="bg-white border border-[#e9e9e7] rounded-md px-3 py-1.5 text-xs text-zinc-900 focus:outline-none w-full font-mono">
                                    @if($editDueDate)
                                        <button 
                                            type="button" 
                                            wire:click="$set('editDueDate', '')" 
                                            class="px-2.5 py-1.5 rounded-md bg-stone-100 hover:bg-rose-50 text-stone-600 hover:text-rose-700 border border-stone-200 hover:border-rose-200 text-xs font-medium transition shrink-0 whitespace-nowrap cursor-pointer flex items-center gap-1.5" 
                                            title="Establecer fecha límite a Ninguna">
                                            <x-lucide-calendar-off class="w-3.5 h-3.5" />
                                            <span>Sin Fecha</span>
                                        </button>
                                    @endif
                                </div>
                            </div>

                            <!-- Core Status (Custom Searchable Combobox) -->
                            <div class="space-y-1 relative" 
                                 x-data="{ 
                                     open: false,
                                     selectStatus(val) {
                                         $wire.set('editCoreStatus', val);
                                         this.open = false;
                                     }
                                 }"
                                 x-dropdown-nav>
                                <label class="font-medium text-zinc-700 block">Lista Trello / Estado Principal:</label>
                                <div class="relative">
                                    <button 
                                        type="button" 
                                        @click="open = !open" 
                                        @click.outside="open = false"
                                        class="bg-white border border-[#e9e9e7] hover:border-stone-300 rounded-md px-3 py-1.5 text-xs text-zinc-900 w-full text-left flex items-center justify-between font-medium">
                                        <span>{{ \App\Enums\CoreStatus::tryFrom($editCoreStatus)?->label() ?? 'Seleccionar estado...' }}</span>
                                        <x-lucide-chevron-down class="w-3.5 h-3.5 text-zinc-400" />
                                    </button>
                                </div>

                                <div 
                                    x-show="open" 
                                    x-transition:enter="transition ease-out duration-100"
                                    x-transition:enter-start="opacity-0 scale-95"
                                    x-transition:enter-end="opacity-100 scale-100"
                                    class="absolute left-0 right-0 top-full mt-1 z-50 bg-white border border-[#e9e9e7] rounded-lg shadow-xl max-h-48 overflow-y-auto divide-y divide-stone-100 text-xs">
                                    @foreach($coreStatuses as $st)
                                        <button 
                                            type="button"
                                            @click="selectStatus('{{ $st->value }}')" 
                                            class="w-full text-left p-2 hover:bg-stone-100 focus:bg-stone-100 focus:outline-none cursor-pointer font-medium text-zinc-800 transition flex items-center justify-between">
                                            <span>{{ $st->label() }}</span>
                                            @if($editCoreStatus === $st->value)
                                                <x-lucide-check class="w-3.5 h-3.5 text-emerald-600 stroke-[3]" />
                                            @endif
                                        </button>
                                    @endforeach
                                </div>
                            </div>

                            <!-- Substatus (Custom Searchable Combobox with Color Badges) -->
                            <div class="space-y-1 relative" 
                                 x-data="{ 
                                     open: false,
                                     selectSub(val) {
                                         $wire.set('editSubstatus', val);
                                         this.open = false;
                                     }
                                 }"
                                 x-dropdown-nav>
                                <label class="font-medium text-zinc-700 block">Subestatus Operativo:</label>
                                <div class="relative">
                                    <button 
                                        type="button" 
                                        @click="open = !open" 
                                        @click.outside="open = false"
                                        class="bg-white border border-[#e9e9e7] hover:border-stone-300 rounded-md px-3 py-1.5 text-xs text-zinc-900 w-full text-left flex items-center justify-between font-medium">
                                        @if($editSubstatus)
                                            @php $subEnum = \App\Enums\Substatus::tryFrom($editSubstatus); @endphp
                                            <span class="px-2 py-0.5 rounded text-[11px] font-medium border {{ $subEnum ? $subEnum->badgeStyle() : 'bg-stone-100 text-stone-700 border-stone-200' }}">
                                                {{ $editSubstatus }}
                                            </span>
                                        @else
                                            <span class="text-zinc-500 italic">Sin Subestatus</span>
                                        @endif
                                        <x-lucide-chevron-down class="w-3.5 h-3.5 text-zinc-400" />
                                    </button>
                                </div>

                                <div 
                                    x-show="open" 
                                    x-transition:enter="transition ease-out duration-100"
                                    x-transition:enter-start="opacity-0 scale-95"
                                    x-transition:enter-end="opacity-100 scale-100"
                                    class="absolute left-0 right-0 top-full mt-1 z-50 bg-white border border-[#e9e9e7] rounded-lg shadow-xl max-h-56 overflow-y-auto divide-y divide-stone-100 text-xs">
                                    <button 
                                        type="button"
                                        @click="selectSub('')" 
                                        class="w-full text-left p-2.5 hover:bg-stone-100 focus:bg-stone-100 focus:outline-none cursor-pointer text-zinc-500 italic transition flex items-center justify-between">
                                        <span>Sin Subestatus</span>
                                        @if(!$editSubstatus)
                                            <x-lucide-check class="w-3.5 h-3.5 text-emerald-600 stroke-[3]" />
                                        @endif
                                    </button>
                                    @foreach($substatuses as $sub)
                                        <button 
                                            type="button"
                                            @click="selectSub('{{ $sub->value }}')" 
                                            class="w-full text-left p-2 hover:bg-stone-100 focus:bg-stone-100 focus:outline-none cursor-pointer transition flex items-center justify-between">
                                            <span class="px-2 py-0.5 rounded text-[11px] font-medium border {{ $sub->badgeStyle() }}">
                                                {{ $sub->value }}
                                            </span>
                                            @if($editSubstatus === $sub->value)
                                                <x-lucide-check class="w-3.5 h-3.5 text-emerald-600 stroke-[3]" />
                                            @endif
                                        </button>
                                    @endforeach
                                </div>
                            </div>

                            <!-- Revisiones Cliente -->
                            <div class="space-y-1">
                                <label class="font-medium text-zinc-700 block">Nº Revisiones Cliente:</label>
                                <input type="number" min="0" wire:model="editClientRevisionCount" class="bg-white border border-[#e9e9e7] rounded-md px-3 py-1.5 text-xs text-zinc-900 focus:outline-none w-full font-mono">
                            </div>

                            <!-- Revisiones Internas -->
                            <div class="space-y-1">
                                <label class="font-medium text-zinc-700 block">Nº Revisiones Internas:</label>
                                <input type="number" min="0" wire:model="editInternalRevisionCount" class="bg-white border border-[#e9e9e7] rounded-md px-3 py-1.5 text-xs text-zinc-900 focus:outline-none w-full font-mono">
                            </div>
                        </div>

                        <!-- Form Save Buttons -->
                        <div class="flex items-center justify-end gap-2 pt-3 border-t border-[#e9e9e7]">
                            <button type="button" @click.prevent="confirmClose(() => $wire.cancelEditing())" class="px-3 py-1.5 rounded-md bg-stone-100 hover:bg-stone-200 text-zinc-700 text-xs font-medium transition cursor-pointer">
                                Cancelar
                            </button>
                            
                            <button 
                                type="button"
                                wire:click="saveOrder(false)" 
                                :disabled="!isEditDirty()"
                                :class="isEditDirty() ? 'bg-emerald-600 hover:bg-emerald-700 text-white cursor-pointer shadow-sm shadow-emerald-600/20' : 'bg-stone-200 text-stone-400 border border-stone-200 cursor-not-allowed'"
                                class="px-3.5 py-1.5 rounded-md font-semibold text-xs transition flex items-center gap-1.5"
                            >
                                <x-lucide-check class="w-3.5 h-3.5" x-show="isEditDirty()" />
                                <span>Guardar Cambios</span>
                            </button>

                            @if(!$order->in_workspace)
                                <button 
                                    type="button"
                                    wire:click="saveOrder(true)" 
                                    :disabled="!isEditDirty()"
                                    :class="isEditDirty() ? 'bg-emerald-700 hover:bg-emerald-800 text-white cursor-pointer shadow-sm shadow-emerald-700/20' : 'bg-stone-200 text-stone-400 border border-stone-200 cursor-not-allowed'"
                                    class="px-3.5 py-1.5 rounded-md font-semibold text-xs shadow-2xs transition flex items-center gap-1.5"
                                >
                                    <x-lucide-arrow-right-circle class="w-3.5 h-3.5" />
                                    <span>Guardar & Añadir a Workspace</span>
                                </button>
                            @endif
                        </div>
                    </div>
                @else
                    <!-- READ MODE -->

                    <!-- Metadata Property Grid (Fluid Grid & Prevent Text Collisions) -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3 bg-[#fbfbfa] p-3.5 rounded-xl border border-[#e9e9e7] text-xs">
                        <div class="min-w-0">
                            <span class="text-zinc-500 block text-[10px] uppercase font-semibold">Diseñadores:</span>
                            <div class="flex flex-wrap items-center gap-1 mt-1">
                                @forelse($order->assigned_designers as $des)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] border truncate max-w-full {{ $des->badge_style }}">
                                        <span class="w-2 h-2 rounded-full {{ $des->dot_color_class }} shrink-0"></span>
                                        <span class="truncate">{{ $des->name }}</span>
                                    </span>
                                @empty
                                    <span class="text-zinc-400 text-xs">Sin Asignar</span>
                                @endforelse
                            </div>
                        </div>

                        <div class="min-w-0">
                            <span class="text-zinc-500 block text-[10px] uppercase font-semibold">Creación Trello:</span>
                            <span class="font-mono text-zinc-800 text-[11px] font-medium mt-1 flex items-center gap-1 truncate" title="{{ $order->trello_created_at ? $order->trello_created_at->format('d M, Y (H:i)') : 'N/A' }}">
                                <x-lucide-clock class="w-3 h-3 text-zinc-400 shrink-0" />
                                <span class="truncate">{{ $order->trello_created_at ? $order->trello_created_at->format('d M, Y') : 'N/A' }}</span>
                            </span>
                        </div>

                        <div class="min-w-0 {{ !$order->trello_card_id ? 'col-span-1 sm:col-span-2 lg:col-span-1' : '' }}">
                            <span class="text-zinc-500 block text-[10px] uppercase font-semibold">ID Tarjeta Trello:</span>
                            @if($order->trello_card_id)
                                <a href="{{ $order->trello_url }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 mt-1 px-2 py-0.5 rounded text-[11px] font-mono font-semibold bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-100 transition max-w-full min-w-0 whitespace-nowrap" title="{{ $order->trello_card_id }}">
                                    <x-lucide-external-link class="w-3 h-3 text-blue-500 shrink-0" />
                                    <span class="truncate">{{ $order->trello_card_id }}</span>
                                </a>
                            @else
                                <div class="flex items-center gap-1 mt-1 flex-wrap sm:flex-nowrap">
                                    <button 
                                        wire:click="createCardOnTrello" 
                                        wire:loading.attr="disabled"
                                        type="button"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-medium bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200 transition cursor-pointer whitespace-nowrap shrink-0 disabled:opacity-50" 
                                        title="Crear nueva tarjeta en Trello para esta orden">
                                        <x-lucide-plus-circle wire:loading.class="hidden" wire:target="createCardOnTrello" class="w-3 h-3 text-blue-600 shrink-0" />
                                        <x-lucide-loader-2 wire:loading wire:target="createCardOnTrello" class="w-3 h-3 text-blue-600 animate-spin shrink-0" />
                                        <span class="whitespace-nowrap">Crear en Trello</span>
                                    </button>
                                    <button 
                                        wire:click="startEditing" 
                                        type="button"
                                        class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[11px] font-medium bg-stone-100 text-zinc-600 hover:text-zinc-900 border border-stone-200 transition cursor-pointer whitespace-nowrap shrink-0" 
                                        title="Vincular ID existente de Trello">
                                        <x-lucide-link class="w-3 h-3 shrink-0" />
                                        <span class="whitespace-nowrap">Vincular</span>
                                    </button>
                                </div>
                            @endif
                        </div>

                        <div class="min-w-0">
                            <span class="text-zinc-500 block text-[10px] uppercase font-semibold">Fecha Límite:</span>
                            <div class="flex items-center gap-1.5 mt-1 min-w-0">
                                <span class="font-mono font-semibold text-xs flex items-center gap-1 truncate {{ $order->isOverdue() ? 'text-red-600' : 'text-zinc-800' }}">
                                    <x-lucide-calendar class="w-3 h-3 shrink-0" />
                                    <span class="truncate">{{ $order->current_due_date ? $order->current_due_date->format('d M, Y') : 'Sin Fecha' }}</span>
                                </span>
                                @if($order->current_due_date)
                                    <button 
                                        wire:click="clearDueDate" 
                                        wire:confirm="¿Estás seguro de establecer la fecha límite como Ninguna (Sin Fecha)?"
                                        type="button"
                                        class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-stone-100 hover:bg-rose-50 text-zinc-500 hover:text-rose-700 border border-stone-200 hover:border-rose-200 transition flex items-center gap-0.5 shrink-0 cursor-pointer"
                                        title="Establecer fecha límite a Ninguna">
                                        <x-lucide-calendar-off class="w-3 h-3" />
                                        <span>Sin Fecha</span>
                                    </button>
                                @endif
                            </div>
                        </div>

                        <div class="min-w-0">
                            <span class="text-zinc-500 block text-[10px] uppercase font-semibold">Revisiones Cliente:</span>
                            <span class="font-medium text-sky-800 text-xs mt-1 flex items-center gap-1">
                                <x-lucide-history class="w-3 h-3 text-sky-600 shrink-0" />
                                <span>{{ $order->client_revision_count }}</span>
                            </span>
                        </div>
                    </div>

                    <!-- Overdue Delay Resolution Prompt Banner -->
                    @if($order->isOverdue())
                        <div class="bg-red-50 border border-red-200 rounded-xl p-3.5 flex items-center justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <h4 class="font-semibold text-xs text-red-800 flex items-center gap-1.5">
                                    <x-lucide-alert-octagon class="w-4 h-4 text-red-600 shrink-0" /> Esta orden está en estado ATRASADO / OVERDUE
                                </h4>
                                <p class="text-[11px] text-zinc-600 mt-0.5">Requiere registrar la nueva fecha acordada con el cliente para resolver el atraso.</p>
                            </div>
                            <button wire:click="$set('showDelayModal', true)" class="px-3 py-1.5 rounded-md bg-red-600 hover:bg-red-500 text-white font-medium text-xs whitespace-nowrap shadow-2xs shrink-0">
                                Resolver Atraso
                            </button>
                        </div>
                    @endif

                    <!-- Related Subtasks Section -->
                    @php
                        $subtasks = $order->relatedTasks;
                        $totalSubtasks = $subtasks->count();
                        $doneSubtasks = $subtasks->filter(fn($t) => $t->isDone())->count();
                        $progressPercent = $totalSubtasks > 0 ? (int) round(($doneSubtasks / $totalSubtasks) * 100) : 0;
                    @endphp

                    <div class="space-y-3">
                        <!-- Subtasks Header & Progress Bar -->
                        <div class="space-y-1.5 border-b border-[#e9e9e7] pb-2.5">
                            <div class="flex items-center justify-between">
                                <h4 class="font-bold text-xs text-zinc-900 uppercase tracking-wider flex items-center gap-1.5">
                                    <x-lucide-check-square class="w-4 h-4 text-zinc-700 shrink-0" /> 
                                    <span>Subtareas</span>
                                    <span class="text-zinc-500 font-mono text-[11px] font-normal">({{ $doneSubtasks }}/{{ $totalSubtasks }})</span>
                                </h4>
                                @if($totalSubtasks > 0)
                                    <span class="text-[11px] font-mono font-semibold {{ $progressPercent === 100 ? 'text-emerald-600' : 'text-zinc-500' }}">
                                        {{ $progressPercent }}%
                                    </span>
                                @else
                                    <span class="text-[10px] text-zinc-400">Organiza y realiza el seguimiento de entregables</span>
                                @endif
                            </div>

                            @if($totalSubtasks > 0)
                                <div class="w-full bg-stone-100 rounded-full h-1.5 overflow-hidden">
                                    <div 
                                        class="h-full rounded-full transition-all duration-300 {{ $progressPercent === 100 ? 'bg-emerald-500' : 'bg-zinc-800' }}" 
                                        style="width: {{ $progressPercent }}%"
                                    ></div>
                                </div>
                            @endif
                        </div>

                        <!-- Subtasks List -->
                        <div class="space-y-1.5">
                            @forelse($subtasks as $task)
                                <div 
                                    x-data="{ 
                                        editing: false, 
                                        title: @js($task->title),
                                        saveTitle() {
                                            const trimmed = this.title.trim();
                                            if (trimmed && trimmed !== @js($task->title)) {
                                                $wire.updateTaskTitle({{ $task->id }}, trimmed);
                                            } else {
                                                this.title = @js($task->title);
                                            }
                                            this.editing = false;
                                        }
                                    }" 
                                    class="bg-[#fbfbfa] hover:bg-stone-50/90 border border-[#e9e9e7] hover:border-stone-300 rounded-xl p-2.5 flex items-center justify-between text-xs gap-3 transition shadow-2xs group"
                                >
                                    <!-- Read Mode View -->
                                    <div x-show="!editing" class="flex items-center justify-between gap-3 w-full min-w-0">
                                        <div class="flex items-center gap-2.5 min-w-0 flex-1">
                                            <!-- Status Checkbox Button -->
                                            <button 
                                                wire:click="toggleTaskStatus({{ $task->id }})" 
                                                type="button"
                                                class="w-4 h-4 rounded border transition flex items-center justify-center shrink-0 cursor-pointer {{ $task->isDone() ? 'bg-emerald-500 border-emerald-500 text-white shadow-2xs' : 'border-stone-300 hover:border-emerald-500 bg-white text-transparent hover:text-emerald-500/40' }}"
                                                title="{{ $task->isDone() ? 'Marcar como pendiente' : 'Marcar como completada' }}">
                                                <x-lucide-check class="w-3 h-3 stroke-[3]" />
                                            </button>

                                            <!-- Subtask Title & Tags -->
                                            <div class="min-w-0 flex-1 flex items-center gap-2 flex-wrap">
                                                <span 
                                                    @click="editing = true; $nextTick(() => $refs.editInput.focus())"
                                                    class="font-semibold text-zinc-900 text-xs break-words cursor-pointer hover:text-zinc-700 transition {{ $task->isDone() ? 'line-through text-zinc-400 font-normal' : '' }}" 
                                                    title="Haz clic para editar el nombre de la subtarea">
                                                    {{ $task->title }}
                                                </span>

                                                <!-- Work vs Admin Badge -->
                                                @if($task->is_work_task !== false)
                                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-blue-50 text-blue-700 border border-blue-200 shrink-0 flex items-center gap-1">
                                                        <x-lucide-wrench class="w-2.5 h-2.5 text-blue-600" />
                                                        <span>Trabajo</span>
                                                    </span>
                                                @else
                                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-amber-50 text-amber-700 border border-amber-200 shrink-0 flex items-center gap-1">
                                                        <x-lucide-clipboard-list class="w-2.5 h-2.5 text-amber-600" />
                                                        <span>Gestión</span>
                                                    </span>
                                                @endif

                                                <!-- Date Badge -->
                                                @if($task->scheduled_date)
                                                    <span class="text-[10px] text-zinc-500 font-medium inline-flex items-center gap-1 bg-stone-100 px-1.5 py-0.5 rounded border border-stone-200 shrink-0" title="Fecha programada">
                                                        <x-lucide-calendar class="w-2.5 h-2.5 text-zinc-400" />
                                                        <span>{{ $task->scheduled_date->format('d M') }}</span>
                                                    </span>
                                                @endif

                                                <!-- Assignee Badge -->
                                                @if($task->assignee)
                                                    <span class="text-[10px] text-zinc-500 font-medium inline-flex items-center gap-1 shrink-0" title="Asignado a">
                                                        <x-lucide-user class="w-2.5 h-2.5 text-zinc-400" />
                                                        <span>{{ $task->assignee->name }}</span>
                                                    </span>
                                                @endif
                                            </div>
                                        </div>

                                        <!-- Action Buttons -->
                                        <div class="flex items-center gap-1 shrink-0">
                                            <!-- Edit Name Button -->
                                            <button 
                                                type="button"
                                                @click="editing = true; $nextTick(() => $refs.editInput.focus())"
                                                class="p-1.5 rounded-lg bg-white hover:bg-stone-100 text-zinc-400 hover:text-zinc-700 border border-stone-200 transition cursor-pointer" 
                                                title="Editar nombre de subtarea">
                                                <x-lucide-pencil class="w-3.5 h-3.5" />
                                            </button>

                                            <!-- Delete Subtask Button -->
                                            <button 
                                                wire:click="deleteTask({{ $task->id }})" 
                                                wire:confirm="¿Estás seguro de eliminar la subtarea '{{ addslashes($task->title) }}'?" 
                                                class="p-1.5 rounded-lg bg-white hover:bg-rose-50 text-zinc-400 hover:text-rose-600 border border-stone-200 hover:border-rose-200 transition cursor-pointer" 
                                                title="Eliminar subtarea">
                                                <x-lucide-trash-2 class="w-3.5 h-3.5" />
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Edit Mode View -->
                                    <form 
                                        x-show="editing" 
                                        @submit.prevent="saveTitle()" 
                                        class="flex items-center gap-2 w-full"
                                        x-cloak
                                    >
                                        <input 
                                            x-ref="editInput"
                                            type="text" 
                                            x-model="title"
                                            @keydown.escape="editing = false; title = @js($task->title)"
                                            placeholder="Nombre de la subtarea..."
                                            class="flex-1 bg-white border border-stone-300 rounded-lg px-2.5 py-1 text-xs text-zinc-900 font-semibold focus:outline-none focus:ring-2 focus:ring-zinc-800"
                                        >
                                        <button 
                                            type="submit" 
                                            class="px-2.5 py-1 rounded-lg bg-zinc-900 hover:bg-zinc-800 text-white font-semibold text-xs shadow-2xs transition flex items-center gap-1 cursor-pointer">
                                            <x-lucide-check class="w-3.5 h-3.5" />
                                            <span>Guardar</span>
                                        </button>
                                        <button 
                                            type="button" 
                                            @click="editing = false; title = @js($task->title)" 
                                            class="p-1 rounded-lg text-zinc-400 hover:text-zinc-700 hover:bg-stone-200 transition cursor-pointer"
                                            title="Cancelar">
                                            <x-lucide-x class="w-3.5 h-3.5" />
                                        </button>
                                    </form>
                                </div>
                            @empty
                                <div class="p-4 text-center text-xs text-zinc-400 bg-[#fbfbfa] rounded-xl border border-[#e9e9e7]">
                                    Sin subtareas creadas aún.
                                </div>
                            @endforelse
                        </div>

                        <!-- Add Manual Subtask Form -->
                        <div class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-xl p-3 space-y-2.5 shadow-2xs">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-[11px] font-bold text-zinc-700 uppercase tracking-wider flex items-center gap-1">
                                    <x-lucide-plus-circle class="w-3.5 h-3.5 text-zinc-500" />
                                    <span>Añadir Nueva Subtarea</span>
                                </span>
                                <div class="flex items-center gap-1.5 text-xs">
                                    <button 
                                        type="button" 
                                        wire:click="$set('newTaskIsWork', true)" 
                                        class="px-2 py-1 rounded-md border text-[10px] font-semibold flex items-center gap-1 transition cursor-pointer {{ $newTaskIsWork ? 'bg-blue-50 text-blue-800 border-blue-300 font-bold shadow-2xs' : 'bg-white text-zinc-600 border-stone-200 hover:bg-stone-100' }}"
                                        title="Trabajo de diseño/producción">
                                        <x-lucide-wrench class="w-3 h-3 text-blue-600" />
                                        <span>Trabajo</span>
                                    </button>
                                    <button 
                                        type="button" 
                                        wire:click="$set('newTaskIsWork', false)" 
                                        class="px-2 py-1 rounded-md border text-[10px] font-semibold flex items-center gap-1 transition cursor-pointer {{ !$newTaskIsWork ? 'bg-amber-50 text-amber-800 border-amber-300 font-bold shadow-2xs' : 'bg-white text-zinc-600 border-stone-200 hover:bg-stone-100' }}"
                                        title="Gestión/Seguimiento administrativo">
                                        <x-lucide-clipboard-list class="w-3 h-3 text-amber-600" />
                                        <span>Gestión</span>
                                    </button>
                                </div>
                            </div>

                            <div class="flex flex-col sm:flex-row items-center gap-2">
                                <input 
                                    type="text" 
                                    wire:model="newTaskTitle" 
                                    wire:keydown.enter="addTask" 
                                    placeholder="Nombre de la subtarea (ej: Ajustes Camila, Confirmar medidas...)" 
                                    class="bg-white border border-[#e9e9e7] rounded-lg px-3 py-1.5 text-xs text-zinc-800 focus:outline-none focus:border-stone-400 flex-1 font-normal w-full">

                                <div class="flex items-center gap-1.5 w-full sm:w-auto shrink-0">
                                    <input 
                                        type="date" 
                                        wire:model="newTaskDate" 
                                        class="bg-white border border-[#e9e9e7] rounded-lg px-2 py-1.5 text-xs text-zinc-700 font-medium focus:outline-none focus:border-stone-400 shrink-0">
                                    <button wire:click="addTask" class="px-3.5 py-1.5 rounded-lg bg-zinc-900 hover:bg-zinc-800 text-white font-semibold text-xs shrink-0 shadow-2xs transition cursor-pointer flex items-center gap-1">
                                        <x-lucide-plus class="w-3.5 h-3.5" />
                                        <span>Añadir</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Timeline / Event Log (Color-Coded Vertical Timeline) -->
                    <div class="space-y-3 pt-2">
                        <div class="flex items-center justify-between border-b border-[#e9e9e7] pb-2">
                            <h4 class="font-bold text-xs text-zinc-900 uppercase tracking-wider flex items-center gap-2">
                                <x-lucide-git-commit-vertical class="w-4 h-4 text-indigo-600 shrink-0" /> 
                                LÍNEA DE TIEMPO / HISTORIAL
                            </h4>
                            <span class="text-[10px] text-zinc-400 font-medium">{{ $order->events->count() }} registro(s)</span>
                        </div>
                        
                        <div class="bg-[#fafaf9] border border-[#e9e9e7] rounded-xl p-4 space-y-4 shadow-2xs">
                            <div class="relative pl-6 space-y-5 max-h-64 overflow-y-auto pr-1 scrollbar-thin">
                                @forelse($order->events as $index => $event)
                                    <div class="relative group">
                                        <!-- Vertical Line Segment -->
                                        @if(!$loop->last || $order->current_due_date)
                                            <span class="absolute left-[-17px] top-3 bottom-[-24px] w-0.5 {{ $event->getLineColorClass() }}" aria-hidden="true"></span>
                                        @endif

                                        <!-- Node Dot -->
                                        <span class="absolute left-[-23px] top-1 w-3.5 h-3.5 rounded-full flex items-center justify-center {{ $event->getNodeColorClass() }}" aria-hidden="true"></span>

                                        <!-- Event Content -->
                                        <div class="min-w-0">
                                            <div class="flex items-center justify-between gap-2">
                                                <span class="text-[11px] font-semibold text-zinc-500 tracking-tight">
                                                    {{ $event->getDisplayDate() }}
                                                </span>
                                                @if($event->actor)
                                                    <span class="text-[9px] px-1.5 py-0.2 rounded bg-stone-200 text-zinc-600 font-medium">
                                                        {{ $event->actor }}
                                                    </span>
                                                @endif
                                            </div>

                                            <h5 class="text-xs font-semibold text-zinc-900 mt-0.5 truncate">
                                                {{ $event->getFormattedTitle() }}
                                            </h5>

                                            @if(is_array($event->metadata) && (isset($event->metadata['reason']) || isset($event->metadata['comment'])))
                                                <div class="mt-1 p-2 rounded-md bg-amber-50 border border-amber-200 text-[11px] text-amber-800 font-medium space-y-1">
                                                    <div class="flex items-start gap-1.5">
                                                        <x-lucide-message-square class="w-3.5 h-3.5 text-amber-600 shrink-0 mt-0.5" />
                                                        <div><strong>Motivo:</strong> {{ $event->metadata['reason'] ?? $event->metadata['comment'] }}</div>
                                                    </div>
                                                    @if(isset($event->metadata['blocked_duration']))
                                                        <div class="flex items-center gap-1.5 text-emerald-800 font-semibold pt-1 border-t border-amber-200/60">
                                                            <x-lucide-clock class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                                                            <span>{{ __('Tiempo bloqueada: :duration', ['duration' => $event->metadata['blocked_duration']]) }}</span>
                                                        </div>
                                                    @endif
                                                </div>
                                            @endif

                                            @if(is_array($event->metadata) && isset($event->metadata['trigger_type']))
                                                <div class="mt-1 p-2 rounded-md bg-purple-50 border border-purple-200 text-[11px] text-purple-900 font-medium space-y-0.5">
                                                    <div class="flex items-center justify-between gap-1.5 font-bold text-purple-800">
                                                        <span class="flex items-center gap-1">
                                                            <x-lucide-zap class="w-3.5 h-3.5 text-purple-600 shrink-0" />
                                                            <span>Origen: {{ $event->metadata['trigger_type'] }}</span>
                                                        </span>
                                                        @if(isset($event->metadata['priority']))
                                                            <span class="px-1.5 py-0.2 rounded text-[9px] uppercase font-extrabold {{ $event->metadata['priority'] === 'urgent' ? 'bg-rose-100 text-rose-700 border border-rose-300' : 'bg-purple-100 text-purple-700' }}">
                                                                {{ $event->metadata['priority'] }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                            @elseif($event->previous_value && $event->new_value && !str_contains($event->event_type, 'CREATED'))
                                                <p class="text-[11px] text-zinc-500 mt-0.5">
                                                    {{ $event->formatValueIfDate($event->previous_value) }} &rarr; 
                                                    <strong class="text-zinc-800">{{ $event->formatValueIfDate($event->new_value) }}</strong>
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <div class="relative">
                                        <span class="absolute left-[-23px] top-1 w-3.5 h-3.5 rounded-full bg-stone-300 ring-4 ring-stone-100"></span>
                                        <p class="text-xs text-zinc-500">Sin eventos en la línea de tiempo aún.</p>
                                    </div>
                                @endforelse

                                <!-- SLA / Current Due Date Node if set -->
                                @if($order->current_due_date)
                                    <div class="relative group pt-1">
                                        @if($order->isOverdue())
                                            <span class="absolute left-[-24px] top-0.5 w-4 h-4 rounded-full bg-red-500 text-white flex items-center justify-center ring-4 ring-red-100 font-bold text-[10px]" title="SLA Vencido">
                                                <x-lucide-alert-triangle class="w-2.5 h-2.5 text-white" />
                                            </span>
                                        @else
                                            <span class="absolute left-[-23px] top-1 w-3.5 h-3.5 rounded-full bg-emerald-500 ring-4 ring-emerald-100" aria-hidden="true"></span>
                                        @endif

                                        <div class="min-w-0">
                                            <span class="text-[11px] font-semibold text-zinc-500 tracking-tight">
                                                {{ $order->current_due_date->format('d M, g:i A') }}
                                            </span>
                                            <h5 class="text-xs font-bold {{ $order->isOverdue() ? 'text-red-600' : 'text-emerald-700' }} mt-0.5">
                                                Deadline de entrega {{ $order->isOverdue() ? '(Vencido)' : '(En plazo)' }}
                                            </h5>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <!-- Summary Duration Footer Box (matching mockup) -->
                            <div class="pt-3 border-t border-[#e9e9e7] flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs">
                                <div class="flex items-center gap-1.5 text-zinc-600">
                                    <x-lucide-clock class="w-3.5 h-3.5 text-zinc-500 shrink-0" />
                                    <span>Tiempo transcurrido:</span>
                                    <strong class="text-zinc-900 font-mono">{{ $order->created_at->diffForHumans(null, true) }}</strong>
                                </div>

                                @if($order->current_due_date)
                                    <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg border text-[11px] font-semibold {{ $order->isOverdue() ? 'bg-red-50 text-red-700 border-red-200' : 'bg-emerald-50 text-emerald-800 border-emerald-200' }}">
                                        @if($order->isOverdue())
                                            <x-lucide-alert-triangle class="w-3.5 h-3.5 text-red-600 shrink-0" />
                                            <span>SLA Vencido</span>
                                        @else
                                            <x-lucide-check-circle-2 class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                                            <span>En plazo límite</span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                <!-- TRELLO COMMENTS SECTION -->
                <div class="space-y-3 pt-2">
                    <div class="flex items-center justify-between">
                        <h4 class="font-bold text-xs text-zinc-900 uppercase tracking-wider flex items-center gap-2">
                            <span class="w-5 h-5 rounded bg-sky-600 text-white flex items-center justify-center font-bold text-[10px] shadow-2xs">T</span>
                            <span>Comentarios en Trello</span>
                            @if(!empty($trelloComments))
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-sky-100 text-sky-800 border border-sky-200">
                                    {{ count($trelloComments) }}
                                </span>
                            @endif
                        </h4>
                        @if($order->trello_card_id)
                            <button wire:click="loadTrelloComments" type="button" class="text-xs text-sky-700 hover:text-sky-900 font-medium flex items-center gap-1 transition cursor-pointer">
                                <x-lucide-refresh-cw wire:loading.class="animate-spin" wire:target="loadTrelloComments" class="w-3.5 h-3.5" />
                                <span>Actualizar</span>
                            </button>
                        @endif
                    </div>

                    @if(!$order->trello_card_id)
                        <div class="p-3.5 rounded-xl bg-blue-50/60 border border-blue-200/80 text-xs text-blue-900 flex items-center justify-between gap-3 shadow-2xs">
                            <div class="flex items-center gap-2 min-w-0">
                                <x-lucide-info class="w-4 h-4 text-blue-500 shrink-0" />
                                <span class="truncate">Esta orden no tiene tarjeta vinculada en Trello.</span>
                            </div>
                            <div class="flex items-center gap-1.5 shrink-0">
                                <button 
                                    wire:click="createCardOnTrello" 
                                    wire:loading.attr="disabled"
                                    type="button"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-2xs transition cursor-pointer disabled:opacity-50">
                                    <x-lucide-plus-circle wire:loading.class="hidden" wire:target="createCardOnTrello" class="w-3.5 h-3.5 shrink-0" />
                                    <x-lucide-loader-2 wire:loading wire:target="createCardOnTrello" class="w-3.5 h-3.5 animate-spin shrink-0" />
                                    <span>Crear Tarjeta en Trello</span>
                                </button>
                            </div>
                        </div>
                    @else
                        <div class="bg-[#fafaf9] border border-[#e9e9e7] rounded-xl p-4 space-y-4 shadow-2xs">
                            <!-- Add Comment Form with Live Inline Preview Inside Typing Box -->
                            <div class="space-y-2" x-data="{
                                init() {
                                    this.syncFromLivewire();
                                    this.$watch('$wire.newTrelloComment', (val) => {
                                        if (!val || val.trim() === '') {
                                            if (this.$refs.editor) this.$refs.editor.innerHTML = '';
                                        }
                                    });
                                },
                                syncFromLivewire() {
                                    if (!this.$refs.editor) return;
                                    if (! $wire.newTrelloComment) {
                                        this.$refs.editor.innerHTML = '';
                                    }
                                },
                                syncToLivewire() {
                                    const editor = this.$refs.editor;
                                    if (!editor) return;
                                    $wire.newTrelloComment = this.getMarkdownFromDOM(editor);
                                },
                                getMarkdownFromDOM(node) {
                                    let text = '';
                                    for (let child of node.childNodes) {
                                        if (child.nodeType === Node.TEXT_NODE) {
                                            text += child.nodeValue;
                                        } else if (child.nodeType === Node.ELEMENT_NODE) {
                                            const tag = child.tagName.toLowerCase();
                                            const childContent = this.getMarkdownFromDOM(child);

                                            if (tag === 'b' || tag === 'strong') {
                                                text += '**' + childContent + '**';
                                            } else if (tag === 'i' || tag === 'em') {
                                                text += '*' + childContent + '*';
                                            } else if (tag === 'code') {
                                                text += '`' + childContent + '`';
                                            } else if (tag === 'a') {
                                                const href = child.getAttribute('href') || childContent;
                                                text += '[' + childContent + '](' + href + ')';
                                            } else if (tag === 'li') {
                                                const parentTag = child.parentElement ? child.parentElement.tagName.toLowerCase() : '';
                                                if (parentTag === 'ol') {
                                                    const siblings = Array.from(child.parentElement.children).filter(c => c.tagName.toLowerCase() === 'li');
                                                    const idx = siblings.indexOf(child) + 1;
                                                    text += idx + '. ' + childContent.trim() + '\n';
                                                } else {
                                                    text += '- ' + childContent.trim() + '\n';
                                                }
                                            } else if (tag === 'ul' || tag === 'ol') {
                                                text += (text && !text.endsWith('\n') ? '\n' : '') + childContent;
                                            } else if (tag === 'div' || tag === 'p') {
                                                text += (text && !text.endsWith('\n') ? '\n' : '') + childContent;
                                            } else if (tag === 'br') {
                                                text += '\n';
                                            } else {
                                                text += childContent;
                                            }
                                        }
                                    }
                                    return text;
                                },
                                handleInput() {
                                    const sel = window.getSelection();
                                    if (sel && sel.rangeCount > 0) {
                                        const node = sel.anchorNode;
                                        if (node && node.nodeType === Node.TEXT_NODE) {
                                            const text = node.nodeValue || '';
                                            if (/^[\-\*]\s/.test(text) && !this.isInList(node)) {
                                                node.nodeValue = text.replace(/^[\-\*]\s/, '');
                                                document.execCommand('insertUnorderedList', false, null);
                                            } else if (/^\d+[\.\)]\s/.test(text) && !this.isInList(node)) {
                                                node.nodeValue = text.replace(/^\d+[\.\)]\s/, '');
                                                document.execCommand('insertOrderedList', false, null);
                                            }
                                        }
                                    }
                                    this.syncToLivewire();
                                },
                                isInList(node) {
                                    let curr = node;
                                    while (curr && curr !== this.$refs.editor) {
                                        if (curr.nodeName === 'UL' || curr.nodeName === 'OL' || curr.nodeName === 'LI') {
                                            return true;
                                        }
                                        curr = curr.parentNode;
                                    }
                                    return false;
                                },
                                handleKeydown(e) {
                                    const isCmdOrCtrl = e.metaKey || e.ctrlKey;

                                    // Cmd+Enter / Ctrl+Enter: Submit
                                    if (isCmdOrCtrl && e.key === 'Enter') {
                                        e.preventDefault();
                                        this.syncToLivewire();
                                        $wire.addTrelloComment();
                                        return;
                                    }

                                    // Cmd+B / Ctrl+B: Bold
                                    if (isCmdOrCtrl && (e.key === 'b' || e.key === 'B')) {
                                        e.preventDefault();
                                        document.execCommand('bold', false, null);
                                        this.syncToLivewire();
                                        return;
                                    }

                                    // Cmd+I / Ctrl+I: Italic
                                    if (isCmdOrCtrl && (e.key === 'i' || e.key === 'I')) {
                                        e.preventDefault();
                                        document.execCommand('italic', false, null);
                                        this.syncToLivewire();
                                        return;
                                    }

                                    // Cmd+K / Ctrl+K: Link
                                    if (isCmdOrCtrl && (e.key === 'k' || e.key === 'K')) {
                                        e.preventDefault();
                                        const url = prompt('Ingrese URL del enlace:', 'https://');
                                        if (url) {
                                            document.execCommand('createLink', false, url);
                                            this.syncToLivewire();
                                        }
                                        return;
                                    }
                                },
                                format(cmd, arg = null) {
                                    this.$refs.editor.focus();
                                    document.execCommand(cmd, false, arg);
                                    this.syncToLivewire();
                                }
                            }">
                                <style>
                                    .comment-editor-box ul {
                                        list-style-type: disc !important;
                                        padding-left: 1.25rem !important;
                                        margin-top: 0.25rem !important;
                                        margin-bottom: 0.25rem !important;
                                    }
                                    .comment-editor-box ol {
                                        list-style-type: decimal !important;
                                        padding-left: 1.25rem !important;
                                        margin-top: 0.25rem !important;
                                        margin-bottom: 0.25rem !important;
                                    }
                                    .comment-editor-box li {
                                        display: list-item !important;
                                        margin-top: 0.125rem !important;
                                        margin-bottom: 0.125rem !important;
                                    }
                                    .comment-editor-box a {
                                        color: #0284c7 !important;
                                        text-decoration: underline !important;
                                        font-weight: 500 !important;
                                    }
                                    .comment-editor-box a:hover {
                                        color: #0369a1 !important;
                                    }
                                </style>

                                <!-- Unified Typing Box (Live Inline Preview) -->
                                <div 
                                    x-ref="editor"
                                    contenteditable="true"
                                    @input="handleInput()"
                                    @keydown="handleKeydown($event)"
                                    data-placeholder="Escribe un comentario..."
                                    class="comment-editor-box w-full bg-white border border-[#e9e9e7] rounded-lg p-2.5 text-xs text-zinc-900 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition min-h-[84px] max-h-[220px] overflow-y-auto font-sans leading-relaxed outline-none prose prose-xs max-w-none empty:before:content-[attr(data-placeholder)] empty:before:text-zinc-400 empty:before:pointer-events-none"></div>

                                <div class="flex items-center justify-between pt-1">
                                    <!-- Quick Formatting Buttons -->
                                    <div class="flex items-center gap-1 text-[11px]">
                                        <button type="button" @click="format('bold')" class="px-2 py-0.5 rounded border border-stone-200 bg-white hover:bg-stone-100 font-bold text-zinc-700 transition" title="Negrita">B</button>
                                        <button type="button" @click="format('italic')" class="px-2 py-0.5 rounded border border-stone-200 bg-white hover:bg-stone-100 italic text-zinc-700 transition" title="Cursiva">I</button>
                                        <button type="button" @click="const url = prompt('URL del enlace:', 'https://'); if(url) format('createLink', url);" class="px-2 py-0.5 rounded border border-stone-200 bg-white hover:bg-stone-100 text-zinc-700 transition underline" title="Enlace">Link</button>
                                        <button type="button" @click="format('insertUnorderedList')" class="px-2 py-0.5 rounded border border-stone-200 bg-white hover:bg-stone-100 text-zinc-700 transition" title="Lista con viñetas">• Viñetas</button>
                                        <button type="button" @click="format('insertOrderedList')" class="px-2 py-0.5 rounded border border-stone-200 bg-white hover:bg-stone-100 text-zinc-700 transition" title="Lista numerada">1. Lista</button>
                                    </div>

                                    <button 
                                        wire:click="addTrelloComment" 
                                        wire:loading.attr="disabled"
                                        type="button" 
                                        class="px-3 py-1.5 rounded-lg bg-sky-600 hover:bg-sky-500 disabled:opacity-50 text-white font-medium text-xs transition flex items-center gap-1.5 shadow-2xs cursor-pointer shrink-0">
                                        <x-lucide-send wire:loading.remove wire:target="addTrelloComment" class="w-3.5 h-3.5" />
                                        <x-lucide-loader-2 wire:loading wire:target="addTrelloComment" class="w-3.5 h-3.5 animate-spin" />
                                        <span>Publicar en Trello</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Comments List -->
                            @if($isLoadingTrelloComments)
                                <div class="py-4 text-center text-xs text-zinc-500 flex items-center justify-center gap-2">
                                    <x-lucide-loader-2 class="w-4 h-4 animate-spin text-sky-600" />
                                    <span>Cargando comentarios desde Trello...</span>
                                </div>
                            @elseif($trelloCommentError)
                                <div class="p-3 rounded-lg bg-amber-50 border border-amber-200 text-xs text-amber-800 flex items-start gap-2">
                                    <x-lucide-alert-circle class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                                    <div>
                                        <strong>Nota de comentarios:</strong> {{ $trelloCommentError }}
                                    </div>
                                </div>
                            @elseif(empty($trelloComments))
                                <div class="py-3 text-center text-xs text-zinc-400">
                                    No hay comentarios registrados en la tarjeta de Trello aún.
                                </div>
                            @else
                                <div class="space-y-3 max-h-72 overflow-y-auto pr-1 scrollbar-thin divide-y divide-[#e9e9e7]">
                                    @foreach($trelloComments as $comment)
                                        <div class="pt-3 first:pt-0 space-y-1.5">
                                            <div class="flex items-center justify-between gap-2">
                                                <div class="flex items-center gap-2 min-w-0">
                                                    @if(!empty($comment['author_avatar']))
                                                        <img src="{{ $comment['author_avatar'] }}" alt="{{ $comment['author_name'] }}" class="w-5 h-5 rounded-full object-cover shrink-0">
                                                    @else
                                                        <div class="w-5 h-5 rounded-full bg-sky-100 text-sky-700 font-bold text-[9px] flex items-center justify-center shrink-0">
                                                            {{ strtoupper(substr($comment['author_name'] ?? 'T', 0, 1)) }}
                                                        </div>
                                                    @endif
                                                    <span class="text-xs font-semibold text-zinc-900 truncate">
                                                        {{ $comment['author_name'] }}
                                                    </span>
                                                    @if(!empty($comment['author_username']))
                                                        <span class="text-[10px] text-zinc-400 truncate">
                                                            @({{ $comment['author_username'] }})
                                                        </span>
                                                    @endif
                                                </div>
                                                @if(!empty($comment['date']))
                                                    <span class="text-[10px] text-zinc-400 shrink-0 font-mono">
                                                        {{ \Carbon\Carbon::parse($comment['date'])->diffForHumans() }}
                                                    </span>
                                                @endif
                                            </div>

                                            <div class="text-xs text-zinc-700 leading-relaxed pl-7 break-words prose prose-xs prose-stone max-w-none [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5 [&_li]:my-0.5">
                                                {!! \Illuminate\Support\Str::markdown($comment['text'], ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                    @endif
                </div>

            </div>


            <!-- Flyout Footer -->
            <div class="p-4 border-t border-[#e9e9e7] bg-[#f7f7f5] flex justify-end">
                <button wire:click="closeModal" class="px-4 py-1.5 rounded-md bg-stone-200 hover:bg-stone-300 text-zinc-800 text-xs font-medium transition">
                    Cerrar Panel
                </button>
            </div>

        </div>
    @endif

    <!-- APPROVAL ACTION MODAL -->
    @if($showApprovalModal)
        <div 
            class="fixed inset-0 z-[150] bg-black/40 backdrop-blur-xs flex items-center justify-center p-4"
            @keydown.window.escape.prevent="$wire.set('showApprovalModal', false)"
            @keydown.window.enter.prevent="$wire.submitApproval()">
            <div class="bg-white border border-[#e9e9e7] rounded-2xl w-full max-w-md p-5 space-y-4 shadow-2xl">
                <div>
                    <h3 class="text-base font-semibold text-zinc-900">Confirmación de Aprobación</h3>
                    <p class="text-xs text-zinc-500 mt-0.5">El cliente aprobó el diseño. Por favor confirma las siguientes condiciones:</p>
                </div>

                <div class="space-y-3 text-xs">
                    <!-- Medidas Confirmadas Checkbox -->
                    <label class="flex items-start gap-3 p-3.5 rounded-xl border border-[#e9e9e7] bg-[#fbfbfa] hover:bg-stone-50 transition cursor-pointer select-none">
                        <input type="checkbox" wire:model="measuresConfirmed" class="w-4 h-4 mt-0.5 rounded border-stone-300 text-stone-900 focus:ring-stone-400">
                        <div class="space-y-0.5 min-w-0 flex-1">
                            <span class="font-semibold text-zinc-900 text-xs block">Medidas 100% confirmadas</span>
                            @if(!$measuresConfirmed)
                                <span class="text-[11px] text-amber-700 font-medium block">Si no se marca, se enviará la orden a RESOLVER para confirmar medidas.</span>
                            @else
                                <span class="text-[11px] text-emerald-700 font-medium block">Medidas listas para producción.</span>
                            @endif
                        </div>
                    </label>

                    <!-- Estimado Aprobado Checkbox -->
                    <label class="flex items-start gap-3 p-3.5 rounded-xl border border-[#e9e9e7] bg-[#fbfbfa] hover:bg-stone-50 transition cursor-pointer select-none">
                        <input type="checkbox" wire:model="estimateApproved" class="w-4 h-4 mt-0.5 rounded border-stone-300 text-stone-900 focus:ring-stone-400">
                        <div class="space-y-0.5 min-w-0 flex-1">
                            <span class="font-semibold text-zinc-900 text-xs block">Estimado / Presupuesto aprobado</span>
                            @if(!$estimateApproved)
                                <span class="text-[11px] text-amber-700 font-medium block">Si no se marca, se registrará una advertencia de estimado pendiente.</span>
                            @else
                                <span class="text-[11px] text-emerald-700 font-medium block">Presupuesto verificado por cliente.</span>
                            @endif
                        </div>
                    </label>

                    <!-- Live Dynamic Outcome Preview Banner -->
                    <div class="p-3 rounded-xl border text-xs leading-relaxed transition-all {{ !$measuresConfirmed ? 'bg-amber-50 border-amber-200 text-amber-900' : (!$estimateApproved ? 'bg-orange-50 border-orange-200 text-orange-900' : 'bg-emerald-50 border-emerald-200 text-emerald-900') }}">
                        <div class="flex items-start gap-2">
                            @if(!$measuresConfirmed)
                                <x-lucide-alert-triangle class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                                <div>
                                    <strong class="font-bold block text-[11px] uppercase tracking-wider text-amber-800">Resultado: Resolver (Bloqueada)</strong>
                                    La orden se moverá a <span class="font-semibold">ENTRANTE</span> con subestado <span class="font-semibold">BLOQUEADA</span> y se creará una tarea de resolución de medidas de alta prioridad (SLA 24h).
                                </div>
                            @elseif(!$estimateApproved)
                                <x-lucide-info class="w-4 h-4 text-orange-600 shrink-0 mt-0.5" />
                                <div>
                                    <strong class="font-bold block text-[11px] uppercase tracking-wider text-orange-800">Resultado: Pendiente de Estimado</strong>
                                    La orden pasará al buzón del diseñador con subestado <span class="font-semibold">FALTA APROBACIÓN DE ESTIMADO</span> (SLA 24h).
                                </div>
                            @else
                                <x-lucide-check-circle-2 class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" />
                                <div>
                                    <strong class="font-bold block text-[11px] uppercase tracking-wider text-emerald-800">Resultado: Lista para Alta</strong>
                                    La orden pasará al buzón del diseñador con subestado <span class="font-semibold">PONER EN ALTA</span> (SLA 24h para preprensa de alta resolución).
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-2">
                    <button wire:click="$set('showApprovalModal', false)" class="px-3 py-1.5 rounded-md bg-stone-100 text-zinc-700 text-xs font-medium">
                        Cancelar
                    </button>
                    <button wire:click="submitApproval" class="px-3.5 py-1.5 rounded-md bg-emerald-600 hover:bg-emerald-500 text-white font-medium text-xs shadow-2xs">
                        Procesar Aprobación
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- DELAY RESOLUTION MODAL -->
    @if($showDelayModal)
        <div 
            class="fixed inset-0 z-[150] bg-black/40 backdrop-blur-xs flex items-center justify-center p-4"
            @keydown.window.escape.prevent="$wire.set('showDelayModal', false)"
            @keydown.window.enter.prevent="if($event.target.tagName !== 'TEXTAREA') $wire.submitDelayResolution()">
            <div class="bg-white border border-[#e9e9e7] rounded-2xl w-full max-w-md p-5 space-y-4 shadow-2xl">
                <div>
                    <h3 class="text-base font-semibold text-zinc-900">Resolver Atraso</h3>
                    <p class="text-xs text-zinc-500 mt-0.5">Ingresa la nueva fecha prometida al cliente para resolver el estado Overdue.</p>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="font-medium text-zinc-700 block mb-1">Nueva Fecha Prometida al Cliente:</label>
                        <input type="date" wire:model="clientPromisedDate" class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-md px-3 py-1.5 text-xs text-zinc-900 focus:outline-none w-full font-mono">
                    </div>

                    <div>
                        <label class="font-medium text-zinc-700 block mb-1">Motivo / Explicación del Retraso:</label>
                        <textarea wire:model="delayReason" rows="3" class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-md p-2.5 text-xs text-zinc-900 focus:outline-none w-full font-normal"></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-2">
                    <button wire:click="$set('showDelayModal', false)" class="px-3 py-1.5 rounded-md bg-stone-100 text-zinc-700 text-xs font-medium">
                        Cancelar
                    </button>
                    <button wire:click="submitDelayResolution" class="px-3.5 py-1.5 rounded-md bg-red-600 hover:bg-red-500 text-white font-medium text-xs shadow-2xs">
                        Guardar & Resolver Atraso
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- UNBLOCK MODAL -->
    @if($showUnblockModal)
        <div 
            class="fixed inset-0 z-[150] bg-black/40 backdrop-blur-xs flex items-center justify-center p-4" 
            @keydown.window.escape.prevent="$wire.closeUnblockModal()"
            @keydown.window.enter.prevent="if($event.target.tagName !== 'TEXTAREA') $wire.confirmUnblock()"
            wire:keydown.escape="closeUnblockModal">
            <div class="bg-white border border-[#e9e9e7] rounded-xl shadow-2xl max-w-lg w-full p-5 space-y-4 animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-start justify-between border-b border-[#e9e9e7] pb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-emerald-50 border border-emerald-200 flex items-center justify-center text-emerald-600 shrink-0">
                            <x-lucide-unlock class="w-4 h-4" />
                        </div>
                        <div>
                            <h3 class="font-bold text-sm text-zinc-900">{{ __('Desbloquear Orden') }}</h3>
                            <p class="text-xs text-zinc-500">{{ $order->company_name }} &mdash; {{ $order->task_name }}</p>
                        </div>
                    </div>
                    <button wire:click="closeUnblockModal" class="text-zinc-400 hover:text-zinc-600 transition">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                </div>

                <div class="space-y-3">
                    <label class="block text-xs font-medium text-zinc-700">
                        {{ __('¿Cómo se resolvió el bloqueo o qué acción se tomó?') }} <span class="text-rose-500">*</span>
                    </label>

                    <div class="flex flex-wrap gap-1.5">
                        <button type="button" wire:click="selectPresetReason('Medidas confirmadas y recibidas')" class="px-2 py-1 rounded bg-stone-100 hover:bg-emerald-50 hover:border-emerald-300 text-stone-700 hover:text-emerald-800 border border-stone-200 text-[11px] transition">
                            ✓ {{ __('Medidas confirmadas') }}
                        </button>
                        <button type="button" wire:click="selectPresetReason('Cliente aprobó información')" class="px-2 py-1 rounded bg-stone-100 hover:bg-emerald-50 hover:border-emerald-300 text-stone-700 hover:text-emerald-800 border border-stone-200 text-[11px] transition">
                            ✓ {{ __('Cliente aprobó información') }}
                        </button>
                        <button type="button" wire:click="selectPresetReason('Estimado aprobado')" class="px-2 py-1 rounded bg-stone-100 hover:bg-emerald-50 hover:border-emerald-300 text-stone-700 hover:text-emerald-800 border border-stone-200 text-[11px] transition">
                            ✓ {{ __('Estimado aprobado') }}
                        </button>
                        <button type="button" wire:click="selectPresetReason('Resuelto por Atención a Clientes')" class="px-2 py-1 rounded bg-stone-100 hover:bg-emerald-50 hover:border-emerald-300 text-stone-700 hover:text-emerald-800 border border-stone-200 text-[11px] transition">
                            ✓ {{ __('Resuelto por Atención a Clientes') }}
                        </button>
                    </div>

                    <textarea wire:model="unblockReason" rows="3" class="w-full rounded-lg border border-stone-300 p-2.5 text-xs text-zinc-900 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 placeholder-zinc-400" placeholder="{{ __('Describe el motivo o detalles de la resolución...') }}"></textarea>

                    @error('unblockReason')
                        <p class="text-xs text-rose-600 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-[#e9e9e7]">
                    <button wire:click="closeUnblockModal" type="button" class="px-3 py-1.5 rounded-lg border border-stone-200 bg-stone-50 hover:bg-stone-100 text-xs font-medium text-zinc-700 transition">
                        {{ __('Cancelar') }}
                    </button>
                    <button wire:click="confirmUnblock" wire:loading.attr="disabled" type="button" class="px-3.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-xs transition flex items-center gap-1.5">
                        <x-lucide-check-circle-2 class="w-3.5 h-3.5" />
                        <span>{{ __('Confirmar Desbloqueo') }}</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- BLOCK MODAL -->
    @if($showBlockModal)
        <div 
            class="fixed inset-0 z-[150] bg-black/40 backdrop-blur-xs flex items-center justify-center p-4" 
            @keydown.window.escape.prevent="$wire.closeBlockModal()"
            @keydown.window.enter.prevent="if($event.target.tagName !== 'TEXTAREA') $wire.confirmBlock()"
            wire:keydown.escape="closeBlockModal">
            <div class="bg-white border border-[#e9e9e7] rounded-xl shadow-2xl max-w-lg w-full p-5 space-y-4 animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-start justify-between border-b border-[#e9e9e7] pb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-rose-50 border border-rose-200 flex items-center justify-center text-rose-600 shrink-0">
                            <x-lucide-alert-octagon class="w-4 h-4" />
                        </div>
                        <div>
                            <h3 class="font-bold text-sm text-zinc-900">{{ __('Bloquear Orden') }}</h3>
                            <p class="text-xs text-zinc-500">{{ $order->company_name ?? '' }} &mdash; {{ $order->task_name ?? '' }}</p>
                        </div>
                    </div>
                    <button wire:click="closeBlockModal" class="text-zinc-400 hover:text-zinc-600 transition">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="font-medium text-zinc-700 block mb-1.5">{{ __('Motivo del Bloqueo:') }}</label>
                        <div class="grid grid-cols-2 gap-1.5">
                            @foreach([
                                'FALTAN MEDIDAS' => 'Faltan Medidas',
                                'FALTA LOGO' => 'Falta Logo / Arte',
                                'FALTA APROBACIÓN DE ESTIMADO' => 'Falta Aprobación Estimado',
                                'ESPERANDO CLIENTE' => 'Esperando Cliente',
                                'OTROS' => 'Otro Motivo'
                            ] as $value => $label)
                                <button type="button" 
                                        wire:click="$set('blockReason', '{{ $value }}')" 
                                        class="px-2.5 py-1.5 rounded-lg border text-[11px] font-medium text-left transition flex items-center justify-between {{ $blockReason === $value ? 'bg-rose-50 border-rose-300 text-rose-800 font-semibold shadow-2xs' : 'bg-stone-50 border-stone-200 text-zinc-700 hover:bg-stone-100' }}">
                                    <span>{{ $label }}</span>
                                    @if($blockReason === $value)
                                        <x-lucide-check class="w-3.5 h-3.5 text-rose-600 shrink-0" />
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </div>

                    @if($blockReason === 'OTROS')
                        <div class="space-y-1">
                            <label class="font-medium text-zinc-700 block">{{ __('Especificar Otro Motivo:') }}</label>
                            <input type="text" wire:model="blockReasonOther" placeholder="Ej: Esperando material especial de proveedor..." class="w-full bg-[#fbfbfa] border border-[#e9e9e7] rounded-lg px-2.5 py-1.5 text-xs text-zinc-900 focus:outline-none focus:border-stone-400">
                        </div>
                    @endif

                    <div class="space-y-1">
                        <label class="font-medium text-zinc-700 block">{{ __('Detalles o Comentarios Adicionales (Opcional):') }}</label>
                        <textarea wire:model="blockComment" rows="2" placeholder="Explica brevemente la situación..." class="w-full bg-[#fbfbfa] border border-[#e9e9e7] rounded-lg p-2.5 text-xs text-zinc-900 focus:outline-none focus:border-stone-400"></textarea>
                    </div>

                    <div class="pt-1 border-t border-stone-100">
                        <label class="flex items-center gap-2 cursor-pointer text-zinc-800 font-medium">
                            <input type="checkbox" wire:model="requireCustomerService" class="rounded border-stone-300 text-rose-600 focus:ring-rose-500 w-4 h-4">
                            <span>{{ __('Requiere atención / seguimiento del cliente o responsable') }}</span>
                        </label>
                        <p class="text-[11px] text-zinc-500 pl-6 mt-0.5">{{ __('Creará una tarea pendiente para dar seguimiento con el contacto o responsable del cliente.') }}</p>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-[#e9e9e7]">
                    <button wire:click="closeBlockModal" type="button" class="px-3 py-1.5 rounded-lg border border-stone-200 bg-stone-50 hover:bg-stone-100 text-xs font-medium text-zinc-700 transition">
                        {{ __('Cancelar') }}
                    </button>
                    <button wire:click="confirmBlock" wire:loading.attr="disabled" type="button" class="px-3.5 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold shadow-xs transition flex items-center gap-1.5">
                        <x-lucide-alert-octagon class="w-3.5 h-3.5" />
                        <span>{{ __('Bloquear Orden') }}</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
