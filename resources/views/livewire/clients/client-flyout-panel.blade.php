<div>
    @if($isOpen)
        {{-- Backdrop --}}
        <div 
            class="fixed inset-0 bg-stone-900/40 backdrop-blur-2xs z-40 transition-opacity"
            wire:click="close"
        ></div>

        {{-- Slide-over Flyout Panel --}}
        <div class="fixed inset-y-0 right-0 max-w-xl w-full bg-white shadow-2xl z-50 flex flex-col border-l border-[#e9e9e7] transform transition-transform duration-200">
            
            {{-- Panel Header --}}
            <div class="p-4 border-b border-[#e9e9e7] bg-[#f7f7f5] flex items-center justify-between shrink-0">
                <div class="flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-lg bg-stone-900 text-white flex items-center justify-center shrink-0 shadow-2xs">
                        <x-lucide-building-2 class="w-4 h-4 text-stone-100" />
                    </div>
                    <div>
                        <h2 class="text-xs font-bold text-zinc-900 uppercase tracking-tight">
                            {{ $clientId ? ($name ?: __('Detalle del Cliente')) : __('Nuevo Cliente') }}
                        </h2>
                        <p class="text-[11px] text-zinc-500">
                            {{ $clientId ? __('Gestión de información, locaciones, contactos y recursos.') : __('Complete la información para registrar el nuevo cliente.') }}
                        </p>
                    </div>
                </div>
                <button 
                    type="button" 
                    wire:click="close"
                    class="p-1 rounded hover:bg-[#efefed] text-zinc-400 hover:text-zinc-700 transition cursor-pointer"
                >
                    <x-lucide-x class="w-4 h-4" />
                </button>
            </div>

            {{-- Merge Suggestions Banner --}}
            @if(!empty($mergeSuggestions))
                <div class="m-4 p-3 bg-amber-50 border border-amber-200 rounded-lg space-y-2 text-xs shrink-0">
                    <div class="flex items-center gap-1.5 text-amber-800 font-bold">
                        <x-lucide-alert-triangle class="w-4 h-4 text-amber-600 shrink-0" />
                        <span>{{ __('Sugerencia de Fusión Detectada') }}</span>
                    </div>
                    <p class="text-[11px] text-amber-700">
                        {{ __('Se encontraron registros o clientes con nombres similares que pueden unificarse:') }}
                    </p>
                    <div class="space-y-1.5">
                        @foreach($mergeSuggestions as $suggestion)
                            <div class="flex items-center justify-between p-2 rounded-md bg-white border border-amber-200 text-xs">
                                <div>
                                    <span class="font-bold text-zinc-900 uppercase">{{ $suggestion['name'] }}</span>
                                    <span class="text-zinc-500 text-[10px] ml-1">({{ $suggestion['orders_count'] }} {{ __('órdenes') }})</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <button 
                                        type="button" 
                                        wire:click="mergeClient({{ $suggestion['id'] }})"
                                        class="px-2 py-0.5 rounded bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-[10px] transition cursor-pointer"
                                    >
                                        {{ __('Fusionar aquí') }}
                                    </button>
                                    <button 
                                        type="button" 
                                        wire:click="dismissMerge({{ $suggestion['id'] }})"
                                        class="px-2 py-0.5 rounded bg-stone-100 hover:bg-stone-200 border border-stone-200 text-zinc-700 text-[10px] transition cursor-pointer"
                                    >
                                        {{ __('Descartar') }}
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Tabs Header --}}
            <div class="flex border-b border-[#e9e9e7] bg-[#fafaf9] px-4 text-xs font-medium text-zinc-600 gap-1 overflow-x-auto shrink-0">
                <button 
                    type="button" 
                    wire:click="$set('activeTab', 'general')"
                    class="py-2.5 px-3 border-b-2 transition cursor-pointer flex items-center gap-1.5 {{ $activeTab === 'general' ? 'border-emerald-600 text-zinc-900 font-semibold' : 'border-transparent text-zinc-500 hover:text-zinc-800' }}"
                >
                    <x-lucide-user class="w-3.5 h-3.5 text-zinc-500" />
                    <span>Client Info</span>
                </button>
                <button 
                    type="button" 
                    wire:click="$set('activeTab', 'links')"
                    class="py-2.5 px-3 border-b-2 transition cursor-pointer flex items-center gap-1.5 {{ $activeTab === 'links' ? 'border-emerald-600 text-zinc-900 font-semibold' : 'border-transparent text-zinc-500 hover:text-zinc-800' }}"
                >
                    <x-lucide-link class="w-3.5 h-3.5 text-zinc-500" />
                    <span>{{ __('Enlaces & Recursos') }}</span>
                </button>
                @if($currentClient)
                    <button 
                        type="button" 
                        wire:click="$set('activeTab', 'projects')"
                        class="py-2.5 px-3 border-b-2 transition cursor-pointer flex items-center gap-1.5 {{ $activeTab === 'projects' ? 'border-emerald-600 text-zinc-900 font-semibold' : 'border-transparent text-zinc-500 hover:text-zinc-800' }}"
                    >
                        <x-lucide-folder class="w-3.5 h-3.5 text-zinc-500" />
                        <span>{{ __('Proyectos') }} ({{ $currentClient->activeOrders->count() + $currentClient->archivedOrders->count() }})</span>
                    </button>
                @endif
            </div>

            {{-- Panel Body --}}
            <div class="flex-1 overflow-y-auto p-6 space-y-7 custom-vertical-scrollbar">
                {{-- TAB 1: Client Info (General, Locaciones & Contactos) --}}
                @if($activeTab === 'general')
                    <div class="space-y-8">
                        {{-- General Info Section --}}
                        <div class="space-y-4">
                            <div>
                                <label class="block text-[10px] font-bold uppercase tracking-wider text-zinc-500 mb-1.5">
                                    {{ __('Nombre del Cliente') }} <span class="text-red-500">*</span> ({{ __('MAYÚSCULAS') }})
                                </label>
                                <input 
                                    type="text" 
                                    wire:model="name"
                                    placeholder="EJ. FUERZA LATINA"
                                    class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-md px-3 py-1.5 text-xs text-zinc-800 uppercase focus:outline-none focus:border-stone-400 w-full font-bold"
                                />
                                @error('name') <span class="text-[11px] text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-[10px] font-bold uppercase tracking-wider text-zinc-500 mb-1.5">
                                    {{ __('Notas Generales u Observaciones') }}
                                </label>
                                <textarea 
                                    wire:model="notes" 
                                    rows="2" 
                                    placeholder="{{ __('Notas internas sobre el cliente...') }}"
                                    class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-md px-3 py-1.5 text-xs text-zinc-800 focus:outline-none focus:border-stone-400 w-full"
                                ></textarea>
                            </div>
                        </div>

                        {{-- Section: Locaciones & Direcciones (Generous Spacing & Divider) --}}
                        <div class="border-t border-[#e9e9e7] pt-7 mt-7 space-y-5">
                            <div class="flex items-center justify-between pb-1">
                                <div>
                                    <h3 class="text-xs font-bold text-zinc-800 uppercase tracking-wider flex items-center gap-1.5">
                                        <x-lucide-map-pin class="w-3.5 h-3.5 text-emerald-600" />
                                        <span>{{ __('Locaciones & Direcciones') }}</span>
                                    </h3>
                                    <p class="text-[11px] text-zinc-500 mt-0.5">{{ __('Dirección física de cada sede del cliente.') }}</p>
                                </div>
                                <button 
                                    type="button" 
                                    wire:click="addLocation"
                                    class="text-xs text-emerald-700 font-semibold hover:underline cursor-pointer"
                                >
                                    + {{ __('Agregar Locación') }}
                                </button>
                            </div>

                            <div class="space-y-5 divide-y divide-[#e9e9e7]">
                                @foreach($locations as $index => $location)
                                    <div class="{{ $index > 0 ? 'pt-5' : '' }} space-y-3">
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            <div>
                                                <label class="text-[10px] font-semibold text-zinc-500 uppercase">
                                                    {{ __('Nombre Locación') }} <span class="text-red-500">*</span>
                                                </label>
                                                <input 
                                                    type="text" 
                                                    wire:model="locations.{{ $index }}.name" 
                                                    placeholder="EJ. EL SOL / SEDE NORTE"
                                                    class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-md px-2.5 py-1 text-xs text-zinc-800 uppercase w-full focus:outline-none font-semibold text-emerald-700 mt-1"
                                                />
                                            </div>
                                            <div>
                                                <label class="text-[10px] font-semibold text-zinc-500 uppercase">
                                                    {{ __('Dirección Física (Visible)') }} <span class="text-red-500">*</span>
                                                </label>
                                                <input 
                                                    type="text" 
                                                    wire:model="locations.{{ $index }}.address" 
                                                    placeholder="Calle 10 # 25-30, Local 102"
                                                    class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-md px-2.5 py-1 text-xs text-zinc-800 w-full focus:outline-none mt-1"
                                                />
                                            </div>
                                            <div>
                                                <label class="text-[10px] font-semibold text-zinc-500 uppercase">
                                                    {{ __('Gestor Locación') }} <span class="text-zinc-400 font-normal">({{ __('Opcional') }})</span>
                                                </label>
                                                <input 
                                                    type="text" 
                                                    wire:model="locations.{{ $index }}.manager_name" 
                                                    placeholder="Nombre del gestor"
                                                    class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-md px-2.5 py-1 text-xs text-zinc-800 w-full focus:outline-none mt-1"
                                                />
                                            </div>
                                            <div>
                                                <label class="text-[10px] font-semibold text-zinc-500 uppercase">
                                                    {{ __('Teléfono Gestor') }} <span class="text-zinc-400 font-normal">({{ __('Opcional') }})</span>
                                                </label>
                                                <input 
                                                    type="text" 
                                                    wire:model="locations.{{ $index }}.manager_phone" 
                                                    placeholder="WhatsApp / Teléfono gestor"
                                                    class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-md px-2.5 py-1 text-xs text-zinc-800 w-full focus:outline-none font-mono mt-1"
                                                />
                                            </div>
                                        </div>
                                        <div>
                                            <label class="text-[10px] font-semibold text-zinc-500 uppercase">{{ __('Notas / Especificaciones Técnicas') }}</label>
                                            <textarea 
                                                wire:model="locations.{{ $index }}.notes" 
                                                rows="1" 
                                                placeholder="{{ __('Medidas, horarios de entrega, observaciones...') }}"
                                                class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-md px-2.5 py-1 text-xs text-zinc-800 w-full focus:outline-none mt-1"
                                            ></textarea>
                                        </div>
                                        <div class="flex justify-end pt-1">
                                            <button 
                                                type="button" 
                                                wire:click="removeLocation({{ $index }})"
                                                class="text-[11px] text-rose-600 hover:underline cursor-pointer"
                                            >
                                                {{ __('Eliminar Locación') }}
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Section: Contactos Múltiples (Generous Spacing & Divider) --}}
                        <div class="border-t border-[#e9e9e7] pt-7 mt-7 space-y-5">
                            <div class="flex items-center justify-between pb-1">
                                <div>
                                    <h3 class="text-xs font-bold text-zinc-800 uppercase tracking-wider flex items-center gap-1.5">
                                        <x-lucide-users class="w-3.5 h-3.5 text-zinc-600" />
                                        <span>{{ __('Contactos por Departamento') }}</span>
                                    </h3>
                                    <p class="text-[11px] text-zinc-500 mt-0.5">{{ __('Responsables de compras, diseño, facturación, etc.') }}</p>
                                </div>
                                <button 
                                    type="button" 
                                    wire:click="addContact"
                                    class="text-xs text-emerald-700 font-semibold hover:underline cursor-pointer"
                                >
                                    + {{ __('Agregar Contacto') }}
                                </button>
                            </div>

                            <div class="space-y-5 divide-y divide-[#e9e9e7]">
                                @foreach($contacts as $index => $contact)
                                    <div class="{{ $index > 0 ? 'pt-5' : '' }} space-y-3">
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            <div>
                                                <label class="text-[10px] font-semibold text-zinc-500 uppercase">{{ __('Nombre Contacto') }}</label>
                                                <input 
                                                    type="text" 
                                                    wire:model="contacts.{{ $index }}.name" 
                                                    placeholder="Nombre y Apellido"
                                                    class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-md px-2.5 py-1 text-xs text-zinc-800 w-full focus:outline-none mt-1"
                                                />
                                            </div>
                                            <div>
                                                <label class="text-[10px] font-semibold text-zinc-500 uppercase">{{ __('Departamento / Rol') }}</label>
                                                <input 
                                                    type="text" 
                                                    wire:model="contacts.{{ $index }}.department" 
                                                    placeholder="Ej. Compras, Diseño, Gerencia"
                                                    class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-md px-2.5 py-1 text-xs text-zinc-800 w-full focus:outline-none mt-1"
                                                />
                                            </div>
                                            <div>
                                                <label class="text-[10px] font-semibold text-zinc-500 uppercase">{{ __('Teléfono') }}</label>
                                                <input 
                                                    type="text" 
                                                    wire:model="contacts.{{ $index }}.phone" 
                                                    placeholder="+57 300 0000000"
                                                    class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-md px-2.5 py-1 text-xs text-zinc-800 w-full focus:outline-none font-mono mt-1"
                                                />
                                            </div>
                                            <div>
                                                <label class="text-[10px] font-semibold text-zinc-500 uppercase">{{ __('Correo Electrónico') }}</label>
                                                <input 
                                                    type="email" 
                                                    wire:model="contacts.{{ $index }}.email" 
                                                    placeholder="correo@cliente.com"
                                                    class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-md px-2.5 py-1 text-xs text-zinc-800 w-full focus:outline-none mt-1"
                                                />
                                            </div>
                                        </div>
                                        <div class="flex items-center justify-between pt-1">
                                            <label class="inline-flex items-center gap-1.5 text-xs text-zinc-600 cursor-pointer">
                                                <input 
                                                    type="checkbox" 
                                                    wire:model="contacts.{{ $index }}.is_primary"
                                                    class="rounded border-stone-300 text-emerald-600 focus:ring-emerald-500"
                                                />
                                                <span>{{ __('Contacto Principal') }}</span>
                                            </label>
                                            <button 
                                                type="button" 
                                                wire:click="removeContact({{ $index }})"
                                                class="text-[11px] text-rose-600 hover:underline cursor-pointer"
                                            >
                                                {{ __('Eliminar Contacto') }}
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                {{-- TAB 2: Enlaces & Recursos (Drive, Brandbook, Assets) --}}
                @if($activeTab === 'links')
                    <div class="space-y-5">
                        <div class="flex items-center justify-between pb-1">
                            <div>
                                <h3 class="text-xs font-bold text-zinc-800 uppercase tracking-wider">
                                    {{ __('Enlaces y Recursos Agrupados') }}
                                </h3>
                                <p class="text-[11px] text-zinc-500 mt-0.5">{{ __('Google Drive (Brandbook, Assets) o recursos por departamento.') }}</p>
                            </div>
                            <button 
                                type="button" 
                                wire:click="addLink"
                                class="text-xs text-emerald-700 font-semibold hover:underline cursor-pointer"
                            >
                                + {{ __('Agregar Enlace') }}
                            </button>
                        </div>

                        <div class="space-y-5 divide-y divide-[#e9e9e7]">
                            @foreach($links as $index => $link)
                                <div class="{{ $index > 0 ? 'pt-5' : '' }} space-y-2.5">
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                                        <div>
                                            <label class="text-[10px] font-semibold text-zinc-500 uppercase">{{ __('Etiqueta') }}</label>
                                            <input 
                                                type="text" 
                                                wire:model="links.{{ $index }}.label" 
                                                placeholder="Ej. Brandbook, Assets"
                                                class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-md px-2.5 py-1 text-xs text-zinc-800 w-full focus:outline-none font-semibold mt-1"
                                            />
                                        </div>
                                        <div>
                                            <label class="text-[10px] font-semibold text-zinc-500 uppercase">{{ __('Grupo / Depto') }}</label>
                                            <input 
                                                type="text" 
                                                wire:model="links.{{ $index }}.department" 
                                                placeholder="Ej. Diseño, Producción"
                                                class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-md px-2.5 py-1 text-xs text-zinc-800 w-full focus:outline-none mt-1"
                                            />
                                        </div>
                                        <div>
                                            <label class="text-[10px] font-semibold text-zinc-500 uppercase">{{ __('URL Enlace (Drive/Web)') }}</label>
                                            <input 
                                                type="url" 
                                                wire:model="links.{{ $index }}.url" 
                                                placeholder="https://drive.google.com/..."
                                                class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-md px-2.5 py-1 text-xs text-zinc-800 w-full focus:outline-none font-mono mt-1"
                                            />
                                        </div>
                                    </div>
                                    <div class="flex justify-end pt-1">
                                        <button 
                                            type="button" 
                                            wire:click="removeLink({{ $index }})"
                                            class="text-[11px] text-rose-600 hover:underline cursor-pointer"
                                        >
                                            {{ __('Eliminar Enlace') }}
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- TAB 3: Proyectos --}}
                @if($activeTab === 'projects' && $currentClient)
                    <div class="space-y-4">
                        <h3 class="text-xs font-bold text-zinc-800 uppercase tracking-wider">
                            {{ __('Historial de Proyectos') }} ({{ $currentClient->orders->count() }})
                        </h3>

                        <div class="space-y-2.5 divide-y divide-[#e9e9e7]">
                            @forelse($currentClient->orders as $index => $order)
                                <div 
                                    wire:click="$dispatch('open-order-detail', { orderId: {{ $order->id }} })"
                                    class="{{ $index > 0 ? 'pt-3' : '' }} p-2.5 rounded-lg hover:bg-[#fcfcfb] border border-transparent hover:border-[#e9e9e7] flex items-center justify-between text-xs cursor-pointer transition group"
                                >
                                    <div class="min-w-0 pr-2">
                                        <div class="font-semibold text-zinc-900 flex items-center gap-2 truncate">
                                            @if($order->wo_number)
                                                <span class="px-1.5 py-0.5 rounded font-mono text-[10px] font-bold bg-zinc-900 text-white tracking-wide shrink-0 shadow-2xs">
                                                    {{ $order->wo_number }}
                                                </span>
                                            @endif
                                            @if($order->location_name || $order->clientLocation)
                                                <span class="inline-flex items-center gap-1 font-semibold text-zinc-700 bg-[#f7f7f5] px-1.5 py-0.5 rounded border border-[#e9e9e7] shrink-0 text-[10px]" title="Locación del Cliente">
                                                    <x-lucide-map-pin class="w-3 h-3 text-zinc-400 shrink-0" />
                                                    <span>{{ $order->location_name ?: $order->clientLocation?->name }}</span>
                                                </span>
                                            @endif
                                            <span class="group-hover:text-emerald-700 transition truncate">{{ $order->task_name }}</span>
                                        </div>
                                        @if($order->designer)
                                            <div class="text-zinc-500 text-[10px] mt-1 flex items-center gap-2 flex-wrap">
                                                <span class="px-1.5 py-0.2 rounded text-[9px] border font-medium {{ $order->getDesignerBadgeStyle() }}">
                                                    {{ $order->designer->name }}
                                                </span>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-1.5 shrink-0">
                                        @if($order->substatus)
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-medium border shrink-0 whitespace-nowrap {{ $order->substatus->badgeStyle() }}">
                                                {{ $order->substatus->label() }}
                                            </span>
                                        @endif
                                        @if($order->core_status)
                                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold border shrink-0 {{ $order->core_status->badgeStyle() }}">
                                                {{ $order->core_status->label() }}
                                            </span>
                                        @endif
                                        <x-lucide-panel-right class="w-3.5 h-3.5 text-zinc-400 group-hover:text-zinc-700 transition shrink-0" />
                                    </div>
                                </div>
                            @empty
                                <p class="text-xs text-zinc-400 italic">{{ __('No hay proyectos registrados para este cliente.') }}</p>
                            @endforelse
                        </div>
                    </div>
                @endif
            </div>

            {{-- Panel Footer --}}
            <div class="p-3.5 border-t border-[#e9e9e7] bg-[#f7f7f5] flex items-center justify-end gap-2 shrink-0">
                <button 
                    type="button" 
                    wire:click="close"
                    class="px-3 py-1.5 bg-white hover:bg-stone-50 border border-[#e9e9e7] text-zinc-700 text-xs font-medium rounded-md cursor-pointer transition"
                >
                    {{ __('Cancelar') }}
                </button>
                <button 
                    type="button" 
                    wire:click="save"
                    class="px-3.5 py-1.5 bg-zinc-900 hover:bg-black text-white text-xs font-medium rounded-md shadow-2xs cursor-pointer transition"
                >
                    {{ __('Guardar Cliente') }}
                </button>
            </div>
        </div>
    @endif
</div>
