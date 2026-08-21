<div>
    <!-- Notion Side Flyout Drawer (Light Mode Panel) -->
    @if($showModal && $order)
        <!-- Backdrop Overlay -->
        <div wire:click="closeModal" class="fixed inset-0 z-50 bg-black/30 backdrop-blur-xs transition-opacity"></div>

        <!-- Slide-over Right Panel -->
        <div class="fixed inset-y-0 right-0 z-50 w-full max-w-2xl bg-white border-l border-[#e9e9e7] shadow-2xl flex flex-col animate-in slide-in-from-right duration-200">
            
            <!-- Flyout Header (Notion Page Header) -->
            <div class="px-6 py-4 border-b border-[#e9e9e7] bg-white sticky top-0 z-20 space-y-4">
                
                <!-- TOP UTILITY ACTION BAR (No badge collision!) -->
                <div class="flex items-center justify-between gap-3">
                    <!-- Left: Clean WO & Creation Timestamp Tag -->
                    <div class="flex items-center gap-2">
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

                    <!-- Right: Action Buttons -->
                    <div class="flex items-center gap-1.5 shrink-0">
                        @if($order->trello_url)
                            <a href="{{ $order->trello_url }}" target="_blank" rel="noopener noreferrer" 
                               class="px-2.5 py-1.5 rounded-lg bg-[#f7f7f5] hover:bg-stone-200 text-zinc-700 font-medium text-xs border border-[#e9e9e7] transition flex items-center gap-1.5" title="Abrir en Trello">
                                <x-lucide-external-link class="w-3.5 h-3.5 text-zinc-500" />
                                <span>Ver en Trello</span>
                            </a>
                        @endif

                        @if(!$isEditing)
                            <button wire:click="startEditing" class="px-2.5 py-1.5 rounded-lg bg-[#f7f7f5] hover:bg-stone-200 text-zinc-700 font-medium text-xs border border-[#e9e9e7] transition flex items-center gap-1.5" title="Editar campos">
                                <x-lucide-pencil class="w-3.5 h-3.5 text-zinc-500" />
                                <span>Editar</span>
                            </button>
                        @endif

                        @if(!$order->in_workspace)
                            <button wire:click="addToWorkspaceDirectly" class="px-3 py-1.5 rounded-lg bg-zinc-900 hover:bg-zinc-800 text-white font-medium text-xs transition flex items-center gap-1.5 shadow-2xs">
                                <x-lucide-plus-circle class="w-3.5 h-3.5 text-emerald-400" />
                                <span>Añadir a Workspace</span>
                            </button>
                        @else
                            <button wire:click="moveToBacklog" wire:confirm="¿Mover esta orden de regreso al Backlog?" class="px-2.5 py-1.5 rounded-lg bg-[#f7f7f5] hover:bg-stone-200 text-zinc-700 font-medium text-xs border border-[#e9e9e7] transition flex items-center gap-1.5">
                                <x-lucide-archive class="w-3.5 h-3.5 text-zinc-500" />
                                <span>A Backlog</span>
                            </button>
                        @endif

                        @if(!$order->approved || in_array($order->core_status, [\App\Enums\CoreStatus::ENVIADO_AL_CLIENTE, \App\Enums\CoreStatus::ENVIADO_A_CAMILA]))
                            <button wire:click="$set('showApprovalModal', true)" class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-medium text-xs transition flex items-center gap-1.5 shadow-2xs">
                                <x-lucide-check-circle-2 class="w-3.5 h-3.5" />
                                <span>Aprobar</span>
                            </button>
                        @endif

                        <div class="h-4 w-px bg-stone-200 mx-1"></div>

                        <button wire:click="closeModal" class="p-1.5 rounded-lg text-zinc-400 hover:text-zinc-700 hover:bg-stone-100 transition" title="Cerrar panel">
                            <x-lucide-x class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                <!-- MAIN TITLE & PROPERTY BADGES BLOCK -->
                <div class="space-y-2 min-w-0">
                    <h2 class="text-xl font-bold text-zinc-900 tracking-tight leading-snug break-words">
                        {{ $order->company_name }}
                    </h2>

                    @if($order->task_name)
                        <p class="text-xs text-zinc-500 font-normal leading-relaxed break-words">
                            {{ $order->task_name }}
                        </p>
                    @endif

                    <!-- Clean Property Badges Row (NO EMOJIS - All Lucide Icons!) -->
                    <div class="flex flex-wrap items-center gap-2 pt-1">
                        <!-- Status Badge -->
                        <span class="px-2.5 py-1 rounded-md text-[11px] font-semibold bg-stone-100 border border-stone-200 text-zinc-800 flex items-center gap-1.5">
                            <x-lucide-layers class="w-3.5 h-3.5 text-zinc-500 shrink-0" />
                            <span>{{ $order->core_status->label() }}</span>
                        </span>

                        <!-- Responsible Contact Badge (NO EMOJI!) -->
                        @if($order->responsible_person)
                            <span class="px-2.5 py-1 rounded-md text-[11px] font-bold bg-indigo-50 text-indigo-800 border border-indigo-200 flex items-center gap-1.5">
                                <x-lucide-user class="w-3.5 h-3.5 text-indigo-600 shrink-0" />
                                <span>{{ $order->responsible_person }}</span>
                            </span>
                        @endif

                        <!-- Backlog Status Badge (NO EMOJI!) -->
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

            </div>

            <!-- Flash Notification Banner -->
            @if (session()->has('message'))
                <div class="bg-emerald-50 border-b border-emerald-200 text-emerald-800 px-5 py-2 text-xs font-medium flex items-center gap-2 shrink-0">
                    <x-lucide-check-circle-2 class="w-4 h-4 text-emerald-600 shrink-0" />
                    <span>{{ session('message') }}</span>
                </div>
            @endif

            <!-- Flyout Body (Scrollable Notion Page Content) -->
            <div class="p-5 overflow-y-auto flex-1 space-y-5 scrollbar-thin">
                
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

                            <!-- Responsible Person -->
                            <div class="space-y-1">
                                <label class="font-medium text-zinc-700 block">Persona Responsable / Cliente:</label>
                                <input type="text" wire:model="editResponsiblePerson" placeholder="Ej: MARCELA" class="bg-white border border-[#e9e9e7] rounded-md px-3 py-1.5 text-xs text-zinc-900 focus:outline-none w-full">
                            </div>

                            <!-- Empresa -->
                            <div class="space-y-1">
                                <label class="font-medium text-zinc-700 block">Nombre de Empresa:</label>
                                <input type="text" wire:model="editCompanyName" class="bg-white border border-[#e9e9e7] rounded-md px-3 py-1.5 text-xs text-zinc-900 focus:outline-none w-full">
                            </div>

                            <!-- Tarea -->
                            <div class="space-y-1">
                                <label class="font-medium text-zinc-700 block">Tarea de Diseño / Trabajo:</label>
                                <input type="text" wire:model="editTaskName" class="bg-white border border-[#e9e9e7] rounded-md px-3 py-1.5 text-xs text-zinc-900 focus:outline-none w-full">
                            </div>

                            <!-- Diseñador -->
                            <div class="space-y-1">
                                <label class="font-medium text-zinc-700 block">Diseñador Asignado:</label>
                                <select wire:model="editDesignerId" class="bg-white border border-[#e9e9e7] rounded-md px-3 py-1.5 text-xs text-zinc-900 focus:outline-none w-full">
                                    <option value="">Sin Asignar</option>
                                    @foreach($designers as $designer)
                                        <option value="{{ $designer->id }}">{{ $designer->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Fecha Límite -->
                            <div class="space-y-1">
                                <label class="font-medium text-zinc-700 block">Fecha Límite (Due Date):</label>
                                <input type="date" wire:model="editDueDate" class="bg-white border border-[#e9e9e7] rounded-md px-3 py-1.5 text-xs text-zinc-900 focus:outline-none w-full font-mono">
                            </div>

                            <!-- Core Status -->
                            <div class="space-y-1">
                                <label class="font-medium text-zinc-700 block">Lista Trello / Estado Principal:</label>
                                <select wire:model="editCoreStatus" class="bg-white border border-[#e9e9e7] rounded-md px-3 py-1.5 text-xs text-zinc-900 focus:outline-none w-full">
                                    @foreach($coreStatuses as $st)
                                        <option value="{{ $st->value }}">{{ $st->label() }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Substatus -->
                            <div class="space-y-1">
                                <label class="font-medium text-zinc-700 block">Subestatus Operativo:</label>
                                <select wire:model="editSubstatus" class="bg-white border border-[#e9e9e7] rounded-md px-3 py-1.5 text-xs text-zinc-900 focus:outline-none w-full">
                                    <option value="">Sin Subestatus</option>
                                    @foreach($substatuses as $sub)
                                        <option value="{{ $sub->value }}">{{ $sub->value }}</option>
                                    @endforeach
                                </select>
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

                    <!-- Metadata Property Grid (Notion Property List) -->
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 bg-[#fbfbfa] p-3.5 rounded-xl border border-[#e9e9e7] text-xs">
                        <div>
                            <span class="text-zinc-500 block text-[10px] uppercase font-semibold">Diseñador:</span>
                            <span class="font-medium text-zinc-900 flex items-center gap-1 mt-0.5 truncate">
                                <x-lucide-user class="w-3 h-3 text-zinc-400 shrink-0" /> {{ $order->designer?->name ?? 'Sin Asignar' }}
                            </span>
                        </div>
                        <div>
                            <span class="text-zinc-500 block text-[10px] uppercase font-semibold">Creación Trello:</span>
                            <span class="font-mono text-zinc-800 text-[11px] font-medium mt-0.5 flex items-center gap-1 truncate">
                                <x-lucide-clock class="w-3 h-3 text-zinc-400 shrink-0" /> {{ $order->trello_created_at ? $order->trello_created_at->format('d M, Y (H:i)') : 'N/A' }}
                            </span>
                        </div>
                        <div>
                            <span class="text-zinc-500 block text-[10px] uppercase font-semibold">Fecha Límite:</span>
                            <span class="font-mono font-semibold text-xs mt-0.5 flex items-center gap-1 {{ $order->isOverdue() ? 'text-red-600' : 'text-zinc-800' }}">
                                <x-lucide-calendar class="w-3 h-3 shrink-0" /> {{ $order->current_due_date ? $order->current_due_date->format('d M, Y') : 'N/A' }}
                            </span>
                        </div>
                        <div>
                            <span class="text-zinc-500 block text-[10px] uppercase font-semibold">Revisiones Cliente:</span>
                            <span class="font-medium text-sky-800 text-xs mt-0.5 flex items-center gap-1">
                                <x-lucide-history class="w-3 h-3 text-sky-600 shrink-0" /> {{ $order->client_revision_count }}
                            </span>
                        </div>
                        <div>
                            <span class="text-zinc-500 block text-[10px] uppercase font-semibold">ID Trello:</span>
                            <span class="font-mono text-zinc-600 text-[11px] mt-0.5 block truncate">{{ $order->trello_card_id ?? 'Sin Sync' }}</span>
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

                    <!-- Timeline / Event Log -->
                    <div class="space-y-2.5">
                        <h4 class="font-semibold text-xs text-zinc-800 uppercase tracking-wider border-b border-[#e9e9e7] pb-1.5 flex items-center gap-1.5">
                            <x-lucide-activity class="w-3.5 h-3.5 text-zinc-600 shrink-0" /> Historial Completo & Auditoría
                        </h4>
                        
                        <div class="space-y-1.5 max-h-44 overflow-y-auto pr-1 scrollbar-thin">
                            @forelse($order->events as $event)
                                <div class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-lg p-2.5 text-xs flex items-center justify-between gap-2">
                                    <div class="min-w-0 flex-1">
                                        <span class="font-medium text-zinc-900 text-[11px] block truncate">{{ $event->event_type }}</span>
                                        @if(is_array($event->metadata) && (isset($event->metadata['reason']) || isset($event->metadata['comment'])))
                                            <span class="text-amber-800 font-medium text-[11px] block mt-0.5 whitespace-normal">
                                                Motivo: "{{ $event->metadata['reason'] ?? $event->metadata['comment'] }}"
                                            </span>
                                        @else
                                            <span class="text-zinc-500 text-[11px] truncate block">{{ $event->previous_value }} → {{ $event->new_value }}</span>
                                        @endif
                                    </div>
                                    <span class="text-[10px] text-zinc-400 font-mono shrink-0">{{ $event->created_at->format('d M, H:i') }}</span>
                                </div>
                            @empty
                                <p class="text-xs text-zinc-400">Sin eventos registrados aún.</p>
                            @endforelse
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
