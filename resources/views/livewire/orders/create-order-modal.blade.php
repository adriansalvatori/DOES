<div>
    @if($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-stone-900/40 backdrop-blur-xs flex items-center justify-center p-4">
            <div 
                x-data 
                @click.outside="$wire.closeModal()"
                class="bg-white border border-[#e9e9e7] rounded-xl shadow-xl max-w-lg w-full overflow-hidden flex flex-col transition duration-200">
                
                <!-- Modal Header -->
                <div class="px-5 py-4 border-b border-[#e9e9e7] bg-[#fbfbfa] flex items-center justify-between">
                    <div class="flex items-center gap-2 min-w-0">
                        <div class="p-1.5 rounded-lg bg-stone-900 text-white shrink-0">
                            <x-lucide-plus-circle class="w-4 h-4" />
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-zinc-900 tracking-tight">Crear Nueva Orden</h3>
                            <p class="text-[11px] text-zinc-500">Añade una nueva orden directamente al flujo de trabajo activo.</p>
                        </div>
                    </div>
                    <button wire:click="closeModal" class="p-1 text-zinc-400 hover:text-zinc-700 hover:bg-stone-100 rounded-md transition">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                </div>

                <!-- Form Fields Container -->
                <form wire:submit.prevent="save" class="p-5 space-y-4 text-xs">
                    
                    <!-- Row 1: WO Number & Responsible Person -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="font-medium text-zinc-700 mb-1 flex items-center gap-1">
                                <x-lucide-hash class="w-3 h-3 text-zinc-400" />
                                <span>Número de Orden (Opcional)</span>
                            </label>
                            <div class="flex rounded-md shadow-2xs">
                                <span class="inline-flex items-center px-2.5 rounded-l-md border border-r-0 border-[#e9e9e7] bg-stone-100 text-zinc-600 font-mono font-bold text-xs select-none">
                                    WO
                                </span>
                                <input type="text" wire:model="woNumber" placeholder="16350" class="w-full bg-[#fbfbfa] border border-[#e9e9e7] rounded-r-md px-3 py-1.5 text-zinc-800 focus:border-stone-400 focus:outline-none font-mono font-semibold">
                            </div>
                        </div>

                        <div>
                            <label class="block font-medium text-zinc-700 mb-1 flex items-center gap-1">
                                <x-lucide-user-check class="w-3 h-3 text-zinc-400" />
                                <span>Persona Contacto / Responsable</span>
                            </label>
                            <input type="text" wire:model="responsiblePerson" placeholder="Ej. AGUSTIN" class="w-full bg-[#fbfbfa] border border-[#e9e9e7] rounded-md px-3 py-1.5 text-zinc-800 focus:border-stone-400 focus:outline-none">
                        </div>
                    </div>

                    <!-- Row 2: Company Name (Required) -->
                    <div>
                        <label class="block font-medium text-zinc-700 mb-1 flex items-center gap-1">
                            <x-lucide-building-2 class="w-3 h-3 text-zinc-400" />
                            <span>Nombre Empresa <span class="text-red-500">*</span></span>
                        </label>
                        <input type="text" wire:model="companyName" placeholder="Ej. RESTAURANTE EL TACO LOCO" class="w-full bg-[#fbfbfa] border border-[#e9e9e7] rounded-md px-3 py-1.5 text-zinc-800 focus:border-stone-400 focus:outline-none font-semibold">
                        @error('companyName') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                    </div>

                    <!-- Row 3: Task Description (Required) -->
                    <div>
                        <label class="block font-medium text-zinc-700 mb-1 flex items-center gap-1">
                            <x-lucide-briefcase class="w-3 h-3 text-zinc-400" />
                            <span>Tarea / Descripción Trabajo <span class="text-red-500">*</span></span>
                        </label>
                        <input type="text" wire:model="taskName" placeholder="Ej. Menú Exterior Acrílico & Rotulación" class="w-full bg-[#fbfbfa] border border-[#e9e9e7] rounded-md px-3 py-1.5 text-zinc-800 focus:border-stone-400 focus:outline-none">
                        @error('taskName') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                    </div>

                    <!-- Row 4: Column / Status & Designer -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-medium text-zinc-700 mb-1 flex items-center gap-1">
                                <x-lucide-layers class="w-3 h-3 text-zinc-400" />
                                <span>Lista / Estado Kanban</span>
                            </label>
                            <select wire:model="coreStatus" class="w-full bg-[#fbfbfa] border border-[#e9e9e7] rounded-md px-3 py-1.5 text-zinc-800 focus:border-stone-400 focus:outline-none">
                                @foreach($coreStatuses as $status)
                                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block font-medium text-zinc-700 mb-1 flex items-center gap-1">
                                <x-lucide-user class="w-3 h-3 text-zinc-400" />
                                <span>Diseñador Asignado</span>
                            </label>
                            <select wire:model="designerId" class="w-full bg-[#fbfbfa] border border-[#e9e9e7] rounded-md px-3 py-1.5 text-zinc-800 focus:border-stone-400 focus:outline-none">
                                <option value="">Sin Asignar</option>
                                @foreach($designers as $designer)
                                    <option value="{{ $designer->id }}">{{ $designer->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Row 5: Substatus & Due Date -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-medium text-zinc-700 mb-1 flex items-center gap-1">
                                <x-lucide-tag class="w-3 h-3 text-zinc-400" />
                                <span>Condición / Subestado (Opcional)</span>
                            </label>
                            <select wire:model="substatus" class="w-full bg-[#fbfbfa] border border-[#e9e9e7] rounded-md px-3 py-1.5 text-zinc-800 focus:border-stone-400 focus:outline-none">
                                <option value="">Ninguno</option>
                                @foreach($substatuses as $sub)
                                    <option value="{{ $sub->value }}">{{ $sub->value }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block font-medium text-zinc-700 mb-1 flex items-center gap-1">
                                <x-lucide-calendar class="w-3 h-3 text-zinc-400" />
                                <span>Fecha Límite (SLA)</span>
                            </label>
                            <input type="date" wire:model="dueDate" class="w-full bg-[#fbfbfa] border border-[#e9e9e7] rounded-md px-3 py-1.5 text-zinc-800 focus:border-stone-400 focus:outline-none font-mono">
                        </div>
                    </div>

                    <!-- Footer Buttons -->
                    <div class="pt-4 border-t border-[#e9e9e7] flex items-center justify-end gap-2">
                        <button type="button" wire:click="closeModal" class="px-3.5 py-1.5 rounded-lg border border-stone-200 text-zinc-600 hover:bg-stone-100 transition font-medium">
                            Cancelar
                        </button>
                        <button type="submit" class="px-4 py-1.5 rounded-lg bg-stone-900 hover:bg-stone-800 text-white font-medium shadow-2xs transition flex items-center gap-1.5">
                            <x-lucide-plus class="w-3.5 h-3.5" />
                            <span>Crear Orden</span>
                        </button>
                    </div>

                </form>
            </div>
        </div>
    @endif
</div>
