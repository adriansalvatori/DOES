<div>
    <!-- Notion Side Flyout Drawer (Light Mode Panel) -->
    @if($showModal && $order)
        <!-- Backdrop Overlay -->
        <div wire:click="closeModal" class="fixed inset-0 z-50 bg-black/30 backdrop-blur-xs transition-opacity"></div>

        <!-- Slide-over Right Panel (Fully Responsive Width) -->
        <div class="fixed inset-y-0 right-0 z-50 w-full sm:max-w-xl md:max-w-2xl lg:max-w-3xl bg-white border-l border-[#e9e9e7] shadow-2xl flex flex-col animate-in slide-in-from-right duration-200 overflow-x-hidden">
            
            <!-- Flyout Header (Notion Page Header) -->
            <div class="px-4 sm:px-6 py-4 border-b border-[#e9e9e7] bg-white sticky top-0 z-20 space-y-3">
                
                <!-- TOP UTILITY ACTION BAR (Fluid flex wrapping) -->
                <div class="flex flex-wrap items-center justify-between gap-2.5">
                    <!-- Left: Clean WO & Creation Timestamp Tag -->
                    <div class="flex items-center gap-2 shrink-0">
                        @if($order->wo_number)
                            <span class="px-2 py-0.5 rounded font-mono text-[11px] font-bold bg-zinc-900 text-white tracking-wide shadow-2xs">
                                {{ $order->wo_number }}
                            </span>
                        @else
                            <span class="px-2 py-0.5 rounded font-mono text-[11px] font-semibold bg-stone-100 text-zinc-500 border border-stone-200">
                                SIN WO
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

                        <button wire:click="closeModal" class="p-1.5 rounded-lg text-zinc-400 hover:text-zinc-700 hover:bg-stone-100 transition shrink-0" title="Cerrar panel">
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
                        <h2 class="text-lg sm:text-xl font-bold text-zinc-900 tracking-tight leading-snug break-words {{ $order->done_today ? 'line-through text-zinc-400' : '' }}">
                            {{ $order->company_name }}
                        </h2>

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
                                 }">
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
                                        <div 
                                            x-show="!$wire.editTrelloCardId || '{{ strtolower(addslashes($tc->trello_card_id . ' ' . $tc->wo_number . ' ' . $tc->company_name . ' ' . $tc->task_name . ' ' . $tc->trello_title)) }}'.includes(($wire.editTrelloCardId || '').toLowerCase())"
                                            @click="selectCard('{{ $tc->trello_card_id }}')" 
                                            class="p-2 hover:bg-blue-50/70 cursor-pointer flex items-center justify-between gap-2 transition">
                                            <div class="min-w-0">
                                                <span class="font-bold text-zinc-900 block truncate text-[11px]">
                                                    {{ $tc->wo_number ? 'WO '.$tc->wo_number.' - ' : '' }}{{ $tc->company_name ?: 'Sin empresa' }}
                                                </span>
                                                <span class="text-[10px] text-zinc-500 block truncate">{{ $tc->task_name ?: $tc->trello_title }}</span>
                                            </div>
                                            <span class="font-mono text-[10px] text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded border border-blue-200 shrink-0">
                                                {{ substr($tc->trello_card_id, 0, 8) }}...
                                            </span>
                                        </div>
                                    @empty
                                        <div class="p-3 text-center text-zinc-400 italic text-[11px]">No hay tarjetas registradas aún.</div>
                                    @endforelse
                                </div>
                            </div>

                            <!-- Responsible Person (Searchable Dropdown) -->
                            <div class="space-y-1 relative" 
                                 x-data="{ 
                                     open: false,
                                     selectResp(resp) {
                                         $wire.set('editResponsiblePerson', resp);
                                         this.open = false;
                                     }
                                 }">
                                <label class="font-medium text-zinc-700 block">Persona Responsable / Cliente:</label>
                                <div class="relative">
                                    <input 
                                        type="text" 
                                        wire:model.live="editResponsiblePerson" 
                                        @focus="open = true"
                                        @click.outside="open = false"
                                        autocomplete="off"
                                        placeholder="Ej: MARCELA o buscar..." 
                                        class="bg-white border border-[#e9e9e7] rounded-md px-3 py-1.5 text-xs text-zinc-900 focus:outline-none w-full pr-7">
                                    
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
                                    @forelse($existingResponsibles as $resp)
                                        <div 
                                            x-show="!$wire.editResponsiblePerson || '{{ strtolower(addslashes($resp)) }}'.includes(($wire.editResponsiblePerson || '').toLowerCase())"
                                            @click="selectResp('{{ addslashes($resp) }}')" 
                                            class="p-2 hover:bg-stone-100 cursor-pointer font-medium text-zinc-800 transition">
                                            {{ $resp }}
                                        </div>
                                    @empty
                                        <div class="p-2.5 text-zinc-400 italic text-[11px]">Escribe un nuevo nombre...</div>
                                    @endforelse
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
                                 }">
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
                                        <div 
                                            x-show="!$wire.editCompanyName || '{{ strtolower(addslashes($comp)) }}'.includes(($wire.editCompanyName || '').toLowerCase())"
                                            @click="selectComp('{{ addslashes($comp) }}')" 
                                            class="p-2 hover:bg-stone-100 cursor-pointer font-bold text-zinc-900 transition">
                                            {{ $comp }}
                                        </div>
                                    @empty
                                        <div class="p-2.5 text-zinc-400 italic text-[11px]">Escribe un nuevo nombre de empresa...</div>
                                    @endforelse
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
                                <input type="date" wire:model="editDueDate" class="bg-white border border-[#e9e9e7] rounded-md px-3 py-1.5 text-xs text-zinc-900 focus:outline-none w-full font-mono">
                            </div>

                            <!-- Core Status (Custom Searchable Combobox) -->
                            <div class="space-y-1 relative" 
                                 x-data="{ 
                                     open: false,
                                     selectStatus(val) {
                                         $wire.set('editCoreStatus', val);
                                         this.open = false;
                                     }
                                 }">
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
                                        <div 
                                            @click="selectStatus('{{ $st->value }}')" 
                                            class="p-2 hover:bg-stone-100 cursor-pointer font-medium text-zinc-800 transition flex items-center justify-between">
                                            <span>{{ $st->label() }}</span>
                                            @if($editCoreStatus === $st->value)
                                                <x-lucide-check class="w-3.5 h-3.5 text-emerald-600 stroke-[3]" />
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <!-- Substatus (Custom Searchable Combobox) -->
                            <div class="space-y-1 relative" 
                                 x-data="{ 
                                     open: false,
                                     selectSub(val) {
                                         $wire.set('editSubstatus', val);
                                         this.open = false;
                                     }
                                 }">
                                <label class="font-medium text-zinc-700 block">Subestatus Operativo:</label>
                                <div class="relative">
                                    <button 
                                        type="button" 
                                        @click="open = !open" 
                                        @click.outside="open = false"
                                        class="bg-white border border-[#e9e9e7] hover:border-stone-300 rounded-md px-3 py-1.5 text-xs text-zinc-900 w-full text-left flex items-center justify-between font-medium">
                                        <span>{{ $editSubstatus ? $editSubstatus : 'Sin Subestatus' }}</span>
                                        <x-lucide-chevron-down class="w-3.5 h-3.5 text-zinc-400" />
                                    </button>
                                </div>

                                <div 
                                    x-show="open" 
                                    x-transition:enter="transition ease-out duration-100"
                                    x-transition:enter-start="opacity-0 scale-95"
                                    x-transition:enter-end="opacity-100 scale-100"
                                    class="absolute left-0 right-0 top-full mt-1 z-50 bg-white border border-[#e9e9e7] rounded-lg shadow-xl max-h-48 overflow-y-auto divide-y divide-stone-100 text-xs">
                                    <div 
                                        @click="selectSub('')" 
                                        class="p-2 hover:bg-stone-100 cursor-pointer text-zinc-500 italic transition flex items-center justify-between">
                                        <span>Sin Subestatus</span>
                                        @if(!$editSubstatus)
                                            <x-lucide-check class="w-3.5 h-3.5 text-emerald-600 stroke-[3]" />
                                        @endif
                                    </div>
                                    @foreach($substatuses as $sub)
                                        <div 
                                            @click="selectSub('{{ $sub->value }}')" 
                                            class="p-2 hover:bg-stone-100 cursor-pointer font-medium text-zinc-800 transition flex items-center justify-between">
                                            <span>{{ $sub->value }}</span>
                                            @if($editSubstatus === $sub->value)
                                                <x-lucide-check class="w-3.5 h-3.5 text-emerald-600 stroke-[3]" />
                                            @endif
                                        </div>
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
                            <button wire:click="cancelEditing" class="px-3 py-1.5 rounded-md bg-stone-100 hover:bg-stone-200 text-zinc-700 text-xs font-medium transition">
                                Cancelar
                            </button>
                            
                            <button wire:click="saveOrder(false)" class="px-3.5 py-1.5 rounded-md bg-stone-800 hover:bg-stone-700 text-white font-medium text-xs transition">
                                Guardar Cambios
                            </button>

                            @if(!$order->in_workspace)
                                <button wire:click="saveOrder(true)" class="px-3.5 py-1.5 rounded-md bg-emerald-600 hover:bg-emerald-500 text-white font-medium text-xs shadow-2xs transition flex items-center gap-1">
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

                        <div class="min-w-0">
                            <span class="text-zinc-500 block text-[10px] uppercase font-semibold">ID Tarjeta Trello:</span>
                            @if($order->trello_card_id)
                                <a href="{{ $order->trello_url }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 mt-1 px-2 py-0.5 rounded text-[11px] font-mono font-semibold bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-100 transition max-w-full min-w-0" title="{{ $order->trello_card_id }}">
                                    <x-lucide-external-link class="w-3 h-3 text-blue-500 shrink-0" />
                                    <span class="truncate">{{ $order->trello_card_id }}</span>
                                </a>
                            @else
                                <button wire:click="startEditing" class="inline-flex items-center gap-1 mt-1 px-2 py-0.5 rounded text-[11px] font-medium bg-stone-100 text-zinc-500 hover:text-zinc-800 border border-stone-200 transition" title="Haz clic para vincular id de Trello">
                                    <x-lucide-plus class="w-3 h-3 shrink-0" />
                                    <span>Vincular</span>
                                </button>
                            @endif
                        </div>

                        <div class="min-w-0">
                            <span class="text-zinc-500 block text-[10px] uppercase font-semibold">Fecha Límite:</span>
                            <span class="font-mono font-semibold text-xs mt-1 flex items-center gap-1 truncate {{ $order->isOverdue() ? 'text-red-600' : 'text-zinc-800' }}">
                                <x-lucide-calendar class="w-3 h-3 shrink-0" />
                                <span class="truncate">{{ $order->current_due_date ? $order->current_due_date->format('d M, Y') : 'N/A' }}</span>
                            </span>
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

                    <!-- Related Tasks Section -->
                    <div class="space-y-2.5">
                        <div class="flex items-center justify-between border-b border-[#e9e9e7] pb-2">
                            <h4 class="font-semibold text-xs text-zinc-800 uppercase tracking-wider flex items-center gap-1.5">
                                <x-lucide-check-square class="w-3.5 h-3.5 text-zinc-600 shrink-0" /> Tareas Vinculadas
                            </h4>
                        </div>

                        <div class="space-y-1.5">
                            @forelse($order->relatedTasks as $task)
                                <div class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-lg p-2.5 flex items-center justify-between text-xs gap-2">
                                    <div class="min-w-0 flex-1">
                                        <span class="font-medium text-zinc-900 block text-[11px] truncate">{{ $task->title }}</span>
                                        <span class="text-zinc-400 text-[10px] font-mono block truncate">Trigger: {{ $task->trigger_type ?? 'Manual' }}</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 shrink-0">
                                        <button wire:click="toggleTaskStatus({{ $task->id }})" class="px-2 py-0.5 rounded text-[10px] font-medium transition {{ $task->isDone() ? 'bg-emerald-50 text-emerald-800 border border-emerald-200 hover:bg-emerald-100' : 'bg-amber-50 text-amber-800 border border-amber-200 hover:bg-amber-100' }}">
                                            {{ $task->isDone() ? 'Done ✓' : 'To Do' }}
                                        </button>
                                        <button wire:click="deleteTask({{ $task->id }})" wire:confirm="¿Eliminar esta tarea vinculada?" class="p-1 rounded text-zinc-400 hover:text-red-600 hover:bg-red-50 transition" title="Eliminar tarea">
                                            <x-lucide-trash-2 class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </div>
                            @empty
                                <p class="text-xs text-zinc-400">Sin tareas vinculadas activas.</p>
                            @endforelse
                        </div>

                        <!-- Add Task Input -->
                        <div class="flex gap-2 pt-1">
                            <input type="text" wire:model="newTaskTitle" wire:keydown.enter="addTask" placeholder="Añadir nueva tarea..." class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-md px-3 py-1.5 text-xs text-zinc-800 focus:outline-none flex-1 font-normal">
                            <button wire:click="addTask" class="px-3 py-1.5 rounded-md bg-stone-800 hover:bg-stone-700 text-white font-medium text-xs shrink-0">
                                + Añadir Tarea
                            </button>
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
                                                    {{ $event->created_at->format('d M, g:i A') }}
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
                                                <div class="mt-1 p-2 rounded-md bg-amber-50 border border-amber-200 text-[11px] text-amber-800 font-medium">
                                                    💬 <strong>Motivo:</strong> {{ $event->metadata['reason'] ?? $event->metadata['comment'] }}
                                                </div>
                                            @elseif($event->previous_value && $event->new_value && !str_contains($event->event_type, 'CREATED'))
                                                <p class="text-[11px] text-zinc-500 mt-0.5">
                                                    {{ \App\Enums\CoreStatus::tryFrom($event->previous_value)?->label() ?? $event->previous_value }} &rarr; 
                                                    <strong class="text-zinc-800">{{ \App\Enums\CoreStatus::tryFrom($event->new_value)?->label() ?? $event->new_value }}</strong>
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
                                                ⚠️
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
                                        <span>{{ $order->isOverdue() ? '⚠️ SLA Vencido' : '✅ En plazo límite' }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

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
        <div class="fixed inset-0 z-50 bg-black/40 backdrop-blur-xs flex items-center justify-center p-4">
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
        <div class="fixed inset-0 z-50 bg-black/40 backdrop-blur-xs flex items-center justify-center p-4">
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
</div>
