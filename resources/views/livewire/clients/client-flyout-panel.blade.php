<div 
    x-data="{
        initialSnapshot: null,
        init() {
            this.snapshot();
            this.$watch('$wire.isOpen', (open) => {
                if (open) {
                    this.snapshot();
                } else {
                    window.KudosDirtyGuard.unregister('client-flyout-panel');
                }
            });
            window.KudosDirtyGuard.register('client-flyout-panel', () => this.isDirty());
            this.$cleanup(() => window.KudosDirtyGuard.unregister('client-flyout-panel'));
        },
        snapshot() {
            this.initialSnapshot = JSON.stringify({
                name: ($wire.name || '').trim(),
                website: ($wire.website || '').trim(),
                notes: ($wire.notes || '').trim(),
                locations: ($wire.locations || []).map(l => ({
                    name: (l.name || '').trim(),
                    manager_name: (l.manager_name || '').trim(),
                    address: (l.address || '').trim(),
                    phone: (l.phone || '').trim(),
                    email: (l.email || '').trim(),
                    notes: (l.notes || '').trim()
                })),
                contacts: ($wire.contacts || []).map(c => ({
                    name: (c.name || '').trim(),
                    department: (c.department || '').trim(),
                    phone: (c.phone || '').trim(),
                    email: (c.email || '').trim(),
                    is_primary: Boolean(c.is_primary)
                })),
                links: ($wire.links || []).map(l => ({
                    label: (l.label || '').trim(),
                    department: (l.department || '').trim(),
                    url: (l.url || '').trim()
                }))
            });
        },
        isDirty() {
            if (!$wire.isOpen || !this.initialSnapshot) return false;
            const current = JSON.stringify({
                name: ($wire.name || '').trim(),
                website: ($wire.website || '').trim(),
                notes: ($wire.notes || '').trim(),
                locations: ($wire.locations || []).map(l => ({
                    name: (l.name || '').trim(),
                    manager_name: (l.manager_name || '').trim(),
                    address: (l.address || '').trim(),
                    phone: (l.phone || '').trim(),
                    email: (l.email || '').trim(),
                    notes: (l.notes || '').trim()
                })),
                contacts: ($wire.contacts || []).map(c => ({
                    name: (c.name || '').trim(),
                    department: (c.department || '').trim(),
                    phone: (c.phone || '').trim(),
                    email: (c.email || '').trim(),
                    is_primary: Boolean(c.is_primary)
                })),
                links: ($wire.links || []).map(l => ({
                    label: (l.label || '').trim(),
                    department: (l.department || '').trim(),
                    url: (l.url || '').trim()
                }))
            });
            return current !== this.initialSnapshot;
        },
        confirmClose(action) {
            if (window.KudosDirtyGuard && window.KudosDirtyGuard.isConfirmModalOpen) {
                return;
            }
            if (this.isDirty()) {
                window.KudosDirtyGuard.openConfirmModal({
                    title: '¿Guardar cambios del cliente?',
                    description: 'Tienes modificaciones en la información del cliente que no han sido guardadas.',
                    cancelText: 'Cancelar',
                    discardText: 'No guardar',
                    saveText: 'Guardar',
                    onCancel: () => {},
                    onDiscard: () => {
                        window.KudosDirtyGuard.unregister('client-flyout-panel');
                        action();
                    },
                    onSave: () => {
                        window.KudosDirtyGuard.unregister('client-flyout-panel');
                        $wire.save();
                    }
                });
            } else {
                action();
            }
        }
    }"
    @keydown.window.escape="if ($wire.isOpen && !window.KudosDirtyGuard.isConfirmModalOpen) confirmClose(() => $wire.close())"
>
    @if($isOpen)
        {{-- Backdrop --}}
        <div 
            class="fixed inset-0 bg-stone-900/30 backdrop-blur-2xs z-40 transition-opacity"
            @click="confirmClose(() => $wire.close())"
        ></div>

        {{-- Slide-over Flyout Panel (Proportioned max-w-2xl ~ 672px width) --}}
        <div class="fixed inset-y-0 right-0 w-full max-w-2xl bg-white shadow-2xl z-50 flex flex-col border-l border-zinc-200 transform transition-transform duration-200">
            
            {{-- Panel Header --}}
            <div class="px-6 py-4 border-b border-zinc-100 bg-white flex items-center justify-between shrink-0">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-9 h-9 rounded-lg bg-zinc-900 text-white flex items-center justify-center shrink-0 shadow-2xs">
                        <x-lucide-building-2 class="w-4 h-4 text-white" />
                    </div>
                    <div class="min-w-0">
                        <h2 class="text-sm font-bold text-zinc-900 uppercase tracking-tight">
                            {{ $clientId ? ($name ?: __('Detalle del Cliente')) : __('Nuevo Cliente') }}
                        </h2>
                        @php
                            $primaryContact = $currentClient?->primaryContact ?: $currentClient?->contacts->first();
                        @endphp
                        @if($currentClient)
                            <div class="flex items-center gap-2.5 text-xs text-zinc-600 mt-0.5 flex-wrap">
                                @if($primaryContact && $primaryContact->name)
                                    <span class="font-semibold text-zinc-800 flex items-center gap-1">
                                        <x-lucide-user class="w-3 h-3 text-emerald-600 shrink-0" />
                                        <span>{{ $primaryContact->name }}</span>
                                    </span>
                                @endif
                                @if($primaryContact && $primaryContact->phone)
                                    <a href="tel:{{ $primaryContact->phone }}" class="flex items-center gap-1 text-zinc-500 hover:text-emerald-700 font-mono text-[11px] transition">
                                        <x-lucide-phone class="w-3 h-3 text-zinc-400 shrink-0" />
                                        <span>{{ $primaryContact->phone }}</span>
                                    </a>
                                @endif
                                @if($primaryContact && $primaryContact->email)
                                    <a href="mailto:{{ $primaryContact->email }}" class="flex items-center gap-1 text-zinc-500 hover:text-emerald-700 text-[11px] transition truncate">
                                        <x-lucide-mail class="w-3 h-3 text-zinc-400 shrink-0" />
                                        <span>{{ $primaryContact->email }}</span>
                                    </a>
                                @endif
                                @if($website)
                                    <a href="{{ Str::startsWith($website, ['http://', 'https://']) ? $website : 'https://'.$website }}" target="_blank" class="flex items-center gap-1 text-emerald-600 hover:underline text-[11px] transition">
                                        <x-lucide-globe class="w-3 h-3 shrink-0" />
                                        <span>{{ preg_replace('/^https?:\/\//i', '', $website) }}</span>
                                    </a>
                                @endif
                            </div>
                        @else
                            <p class="text-xs text-zinc-500">
                                {{ __('Complete la información para registrar el nuevo cliente.') }}
                            </p>
                        @endif
                    </div>
                </div>
                <button 
                    type="button" 
                    @click="confirmClose(() => $wire.close())"
                    class="p-1.5 rounded-lg hover:bg-zinc-100 text-zinc-400 hover:text-zinc-700 transition cursor-pointer"
                >
                    <x-lucide-x class="w-4 h-4" />
                </button>
            </div>

            {{-- Merge Suggestions Banner --}}
            @if(!empty($mergeSuggestions))
                <div class="mx-6 mt-4 p-3.5 bg-amber-50/80 border border-amber-200/70 rounded-lg space-y-2 text-xs shrink-0">
                    <div class="flex items-center gap-2 text-amber-900 font-semibold text-xs">
                        <x-lucide-alert-triangle class="w-4 h-4 text-amber-600 shrink-0" />
                        <span>{{ __('Sugerencia de Fusión Detectada') }}</span>
                    </div>
                    <p class="text-[11px] text-amber-700/90">
                        {{ __('Se encontraron registros o clientes con nombres similares que pueden unificarse:') }}
                    </p>
                    <div class="space-y-1.5 pt-1">
                        @foreach($mergeSuggestions as $suggestion)
                            <div class="flex items-center justify-between p-2 rounded-md bg-white border border-amber-200/60 text-xs">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-bold text-zinc-900 uppercase text-xs">{{ $suggestion['name'] }}</span>
                                    <span class="text-zinc-500 text-[11px]">({{ $suggestion['orders_count'] }} {{ __('órdenes') }})</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button 
                                        type="button" 
                                        wire:click="mergeClient({{ $suggestion['id'] }})"
                                        class="px-2.5 py-1 rounded bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-xs transition cursor-pointer"
                                    >
                                        {{ __('Fusionar aquí') }}
                                    </button>
                                    <button 
                                        type="button" 
                                        wire:click="dismissMerge({{ $suggestion['id'] }})"
                                        class="px-2.5 py-1 rounded bg-zinc-100 hover:bg-zinc-200 text-zinc-600 text-xs transition cursor-pointer"
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
            <div class="flex border-b border-zinc-200/80 bg-white px-6 gap-2 text-xs font-medium shrink-0">
                <button 
                    type="button" 
                    wire:click="$set('activeTab', 'general')"
                    class="py-3 px-3 border-b-2 font-medium transition cursor-pointer flex items-center gap-2 text-xs {{ $activeTab === 'general' ? 'border-emerald-600 text-zinc-900 font-semibold' : 'border-transparent text-zinc-500 hover:text-zinc-800' }}"
                >
                    <x-lucide-user class="w-3.5 h-3.5 {{ $activeTab === 'general' ? 'text-emerald-600' : 'text-zinc-400' }}" />
                    <span>Client Info</span>
                </button>
                <button 
                    type="button" 
                    wire:click="$set('activeTab', 'links')"
                    class="py-3 px-3 border-b-2 font-medium transition cursor-pointer flex items-center gap-2 text-xs {{ $activeTab === 'links' ? 'border-emerald-600 text-zinc-900 font-semibold' : 'border-transparent text-zinc-500 hover:text-zinc-800' }}"
                >
                    <x-lucide-link class="w-3.5 h-3.5 {{ $activeTab === 'links' ? 'text-emerald-600' : 'text-zinc-400' }}" />
                    <span>{{ __('Enlaces & Recursos') }}</span>
                </button>
                @if($currentClient)
                    <button 
                        type="button" 
                        wire:click="$set('activeTab', 'projects')"
                        class="py-3 px-3 border-b-2 font-medium transition cursor-pointer flex items-center gap-2 text-xs {{ $activeTab === 'projects' ? 'border-emerald-600 text-zinc-900 font-semibold' : 'border-transparent text-zinc-500 hover:text-zinc-800' }}"
                    >
                        <x-lucide-folder class="w-3.5 h-3.5 {{ $activeTab === 'projects' ? 'text-emerald-600' : 'text-zinc-400' }}" />
                        <span>{{ __('Proyectos') }} ({{ $currentClient->activeOrders->count() + $currentClient->archivedOrders->count() }})</span>
                    </button>
                @endif
            </div>

            {{-- Panel Body --}}
            <div class="flex-1 overflow-y-auto p-6 custom-vertical-scrollbar">
                {{-- TAB 1: Client Info (General, Locaciones & Contactos) --}}
                @if($activeTab === 'general')
                    <div class="space-y-6">
                        {{-- Section 1: Información Principal del Cliente --}}
                        <div class="space-y-4">
                            {{-- 1. Company (client) name --}}
                            <div>
                                <label class="block text-[11px] font-bold uppercase tracking-wider text-zinc-500 mb-1">
                                    {{ __('Nombre del Cliente / Empresa') }} <span class="text-rose-500">*</span>
                                </label>
                                <input 
                                    type="text" 
                                    wire:model="name"
                                    placeholder="EJ. PORKYS REAL MEXICAN FOOD"
                                    class="w-full bg-transparent border border-transparent hover:bg-zinc-100/60 hover:border-zinc-200/60 focus:bg-white focus:border-zinc-300 focus:ring-2 focus:ring-zinc-900/5 rounded-md -mx-1 px-1 py-1.5 text-sm text-zinc-900 uppercase font-bold focus:outline-none transition"
                                />
                                @error('name') <span class="text-[11px] text-rose-500 mt-1 block font-medium">{{ $message }}</span> @enderror
                            </div>

                            {{-- 2. Main Responsible --}}
                            @if(isset($contacts[0]))
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3.5 pt-1">
                                    <div class="sm:col-span-2">
                                        <label class="block text-[10px] font-semibold uppercase tracking-wider text-zinc-500 mb-0.5 flex items-center gap-1">
                                            <x-lucide-user class="w-3.5 h-3.5 text-emerald-600" />
                                            <span>{{ __('Responsable Principal') }}</span>
                                        </label>
                                        <input 
                                            type="text" 
                                            wire:model="contacts.0.name"
                                            placeholder="Nombre del responsable principal"
                                            class="w-full bg-transparent border border-transparent hover:bg-zinc-100/60 hover:border-zinc-200/60 focus:bg-white focus:border-zinc-300 focus:ring-2 focus:ring-zinc-900/5 rounded-md -mx-1 px-1 py-1 text-xs text-zinc-900 font-semibold focus:outline-none transition"
                                        />
                                    </div>

                                    {{-- 3. Email & Phone Number --}}
                                    <div>
                                        <label class="block text-[10px] font-semibold uppercase tracking-wider text-zinc-500 mb-0.5 flex items-center gap-1">
                                            <x-lucide-mail class="w-3 h-3 text-zinc-400" />
                                            <span>{{ __('Correo Electrónico') }}</span>
                                        </label>
                                        <input 
                                            type="email" 
                                            wire:model="contacts.0.email"
                                            placeholder="correo@cliente.com"
                                            class="w-full bg-transparent border border-transparent hover:bg-zinc-100/60 hover:border-zinc-200/60 focus:bg-white focus:border-zinc-300 focus:ring-2 focus:ring-zinc-900/5 rounded-md -mx-1 px-1 py-1 text-xs text-zinc-800 focus:outline-none transition"
                                        />
                                    </div>

                                    <div>
                                        <label class="block text-[10px] font-semibold uppercase tracking-wider text-zinc-500 mb-0.5 flex items-center gap-1">
                                            <x-lucide-phone class="w-3 h-3 text-zinc-400" />
                                            <span>{{ __('Teléfono / WhatsApp') }}</span>
                                        </label>
                                        <input 
                                            type="text" 
                                            wire:model="contacts.0.phone"
                                            x-on:input="
                                                let val = $el.value.replace(/\D/g, '');
                                                if (val.length === 11 && val.startsWith('1')) val = val.substring(1);
                                                if (val.length > 10) val = val.substring(0, 10);
                                                if (val.length === 0) { $el.value = ''; }
                                                else if (val.length <= 3) { $el.value = '(' + val; }
                                                else if (val.length <= 6) { $el.value = '(' + val.substring(0, 3) + ') ' + val.substring(3); }
                                                else { $el.value = '(' + val.substring(0, 3) + ') ' + val.substring(3, 6) + '-' + val.substring(6); }
                                            "
                                            placeholder="(000) 000-0000"
                                            class="w-full bg-transparent border border-transparent hover:bg-zinc-100/60 hover:border-zinc-200/60 focus:bg-white focus:border-zinc-300 focus:ring-2 focus:ring-zinc-900/5 rounded-md -mx-1 px-1 py-1 text-xs text-zinc-800 font-mono focus:outline-none transition"
                                        />
                                    </div>
                                </div>
                            @endif

                            {{-- 4. Client Website --}}
                            <div>
                                <label class="block text-[10px] font-semibold uppercase tracking-wider text-zinc-500 mb-0.5 flex items-center gap-1">
                                    <x-lucide-globe class="w-3.5 h-3.5 text-zinc-400" />
                                    <span>{{ __('Sitio Web del Cliente') }}</span>
                                </label>
                                <input 
                                    type="url" 
                                    wire:model="website"
                                    placeholder="https://www.cliente.com"
                                    class="w-full bg-transparent border border-transparent hover:bg-zinc-100/60 hover:border-zinc-200/60 focus:bg-white focus:border-zinc-300 focus:ring-2 focus:ring-zinc-900/5 rounded-md -mx-1 px-1 py-1 text-xs text-zinc-800 font-mono focus:outline-none transition"
                                />
                            </div>

                            {{-- 5. Notes / Observaciones --}}
                            <div>
                                <label class="block text-[10px] font-semibold uppercase tracking-wider text-zinc-500 mb-0.5 flex items-center gap-1">
                                    <x-lucide-file-text class="w-3.5 h-3.5 text-zinc-400" />
                                    <span>{{ __('Notas Generales u Observaciones') }}</span>
                                </label>
                                <textarea 
                                    wire:model="notes" 
                                    rows="2" 
                                    placeholder="{{ __('Notas internas, observaciones o especificaciones sobre el cliente...') }}"
                                    class="w-full bg-transparent border border-transparent hover:bg-zinc-100/60 hover:border-zinc-200/60 focus:bg-white focus:border-zinc-300 focus:ring-2 focus:ring-zinc-900/5 rounded-md -mx-1 px-1 py-1 text-xs text-zinc-800 focus:outline-none transition"
                                ></textarea>
                            </div>
                        </div>

                        {{-- Section 2: Locaciones & Direcciones --}}
                        <div class="pt-6 border-t border-zinc-200/70 space-y-4">
                            <div class="space-y-6">
                                @foreach($locations as $index => $location)
                                    <div class="{{ $index > 0 ? 'pt-6 border-t border-zinc-200/60' : '' }} space-y-3">
                                        {{-- Inline Editable Title Header with Responsible Label --}}
                                        <div class="flex items-center justify-between gap-3">
                                            <div class="flex items-center gap-2 flex-1 min-w-0 flex-wrap">
                                                <x-lucide-map-pin class="w-3.5 h-3.5 text-rose-500 shrink-0" />
                                                <input 
                                                    type="text" 
                                                    wire:model.live.debounce.150ms="locations.{{ $index }}.name" 
                                                    placeholder="{{ __('NOMBRE LOCACIÓN (EJ. GRAYSON)') }}"
                                                    :size="Math.max(14, ({{ json_encode($location['name'] ?? '') }} || 'NOMBRE LOCACIÓN (EJ. GRAYSON)').length + 1)"
                                                    class="bg-transparent hover:bg-zinc-100/70 focus:bg-white border border-transparent hover:border-zinc-200/60 focus:border-zinc-300 focus:ring-2 focus:ring-zinc-900/5 rounded-md -mx-1 px-1 py-0.5 text-xs font-bold text-zinc-900 uppercase tracking-tight focus:outline-none transition shrink-0 max-w-full"
                                                />

                                                {{-- Responsible Contact Combobox Pill Label --}}
                                                <div 
                                                    x-data="{
                                                        openResp: false,
                                                        selectContact(name) {
                                                            $wire.set('locations.{{ $index }}.manager_name', name);
                                                            this.openResp = false;
                                                        },
                                                        createContactAndSelect(name) {
                                                            let clean = (name || '').trim();
                                                            if (clean) {
                                                                $wire.call('addContactWithName', clean).then(() => {
                                                                    $wire.set('locations.{{ $index }}.manager_name', clean);
                                                                });
                                                            }
                                                            this.openResp = false;
                                                        }
                                                    }"
                                                    class="relative shrink-0"
                                                    @click.outside="openResp = false"
                                                >
                                                    <div 
                                                        @click="openResp = !openResp"
                                                        class="inline-flex items-center gap-1 bg-zinc-100/90 hover:bg-zinc-200/70 border border-zinc-200/60 focus-within:border-zinc-300 rounded-full px-2.5 py-0.5 text-xs transition cursor-pointer select-none"
                                                    >
                                                        <x-lucide-user class="w-3 h-3 text-zinc-500 shrink-0" />
                                                        <input 
                                                            type="text" 
                                                            wire:model.live.debounce.150ms="locations.{{ $index }}.manager_name"
                                                            @focus="openResp = true"
                                                            placeholder="{{ __('Responsable...') }}"
                                                            :size="Math.max(10, ({{ json_encode($location['manager_name'] ?? '') }} || 'Responsable...').length + 1)"
                                                            class="bg-transparent border-none p-0 text-xs font-medium text-zinc-800 placeholder:text-zinc-400 placeholder:font-normal focus:outline-none transition shrink-0"
                                                        />
                                                        <x-lucide-chevron-down 
                                                            class="w-3 h-3 text-zinc-400 shrink-0 transition-transform duration-150"
                                                            ::class="{ 'rotate-180': openResp }"
                                                        />
                                                    </div>

                                                    {{-- Dropdown Panel --}}
                                                    <div 
                                                        x-show="openResp" 
                                                        x-transition:enter="transition ease-out duration-100"
                                                        x-transition:enter-start="opacity-0 scale-95"
                                                        x-transition:enter-end="opacity-100 scale-100"
                                                        class="absolute left-0 top-full mt-1 z-30 bg-white border border-zinc-200/80 rounded-lg shadow-xl py-1 w-64 max-h-48 overflow-y-auto text-xs"
                                                        style="display: none;"
                                                    >
                                                        @php
                                                            $currentManager = trim($location['manager_name'] ?? '');
                                                            $hasExactMatch = false;
                                                        @endphp

                                                        <div class="px-2.5 py-1 text-[10px] font-bold text-zinc-400 uppercase tracking-wider border-b border-zinc-100 flex items-center justify-between">
                                                            <span>{{ __('Contactos del cliente') }}</span>
                                                        </div>

                                                        @foreach($contacts as $c)
                                                            @if(!empty(trim($c['name'] ?? '')))
                                                                @php
                                                                    $cName = trim($c['name']);
                                                                    if ($currentManager && mb_strtolower($currentManager, 'UTF-8') === mb_strtolower($cName, 'UTF-8')) {
                                                                        $hasExactMatch = true;
                                                                    }
                                                                @endphp
                                                                <button 
                                                                    type="button" 
                                                                    @click="selectContact('{{ addslashes($cName) }}')"
                                                                    class="w-full text-left px-2.5 py-1.5 hover:bg-emerald-50 hover:text-emerald-900 flex items-center justify-between transition cursor-pointer group"
                                                                >
                                                                    <div class="flex items-center gap-2 min-w-0">
                                                                        <x-lucide-user class="w-3.5 h-3.5 text-zinc-400 group-hover:text-emerald-600 shrink-0" />
                                                                        <span class="font-semibold text-zinc-800 group-hover:text-emerald-950 truncate">{{ $cName }}</span>
                                                                        @if(!empty($c['department']))
                                                                            <span class="text-[10px] text-zinc-400">({{ $c['department'] }})</span>
                                                                        @endif
                                                                    </div>
                                                                    @if($currentManager && mb_strtolower($currentManager, 'UTF-8') === mb_strtolower($cName, 'UTF-8'))
                                                                        <x-lucide-check class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                                                                    @endif
                                                                </button>
                                                            @endif
                                                        @endforeach

                                                        @if(!$hasExactMatch && !empty($currentManager))
                                                            <button 
                                                                type="button" 
                                                                @click="createContactAndSelect('{{ addslashes($currentManager) }}')"
                                                                class="w-full text-left px-2.5 py-2 hover:bg-emerald-50 text-emerald-700 font-semibold border-t border-zinc-100 flex items-center gap-2 transition cursor-pointer"
                                                            >
                                                                <x-lucide-user-plus class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                                                                <span>{{ __('Crear contacto') }} "<strong>{{ $currentManager }}</strong>"</span>
                                                            </button>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                            <button 
                                                type="button" 
                                                wire:click="removeLocation({{ $index }})"
                                                class="text-[11px] text-zinc-400 hover:text-rose-600 transition cursor-pointer flex items-center gap-1 shrink-0"
                                            >
                                                <x-lucide-trash-2 class="w-3.5 h-3.5" />
                                                <span>{{ __('Eliminar') }}</span>
                                            </button>
                                        </div>

                                        {{-- Location Fields Grid --}}
                                        <div 
                                            x-data="{
                                                formatPhoneInput(el) {
                                                    let val = el.value || '';
                                                    let digits = val.replace(/\D/g, '');
                                                    if (digits.length === 11 && digits.startsWith('1')) {
                                                        digits = digits.substring(1);
                                                    }
                                                    if (digits.length > 10) digits = digits.substring(0, 10);
                                                    if (digits.length === 0) {
                                                        el.value = '';
                                                        return;
                                                    }
                                                    if (digits.length <= 3) {
                                                        el.value = '(' + digits;
                                                    } else if (digits.length <= 6) {
                                                        el.value = '(' + digits.substring(0, 3) + ') ' + digits.substring(3);
                                                    } else {
                                                        el.value = '(' + digits.substring(0, 3) + ') ' + digits.substring(3, 6) + '-' + digits.substring(6);
                                                    }
                                                }
                                            }"
                                            class="grid grid-cols-1 sm:grid-cols-2 gap-2"
                                        >
                                            <div class="sm:col-span-2">
                                                <input 
                                                    type="text" 
                                                    wire:model="locations.{{ $index }}.address" 
                                                    placeholder="{{ __('Location Address') }}"
                                                    class="w-full bg-transparent hover:bg-zinc-100/60 focus:bg-white border border-transparent hover:border-zinc-200/60 focus:border-zinc-300 focus:ring-2 focus:ring-zinc-900/5 rounded-md -mx-1 px-1 py-1 text-xs text-zinc-900 font-bold focus:outline-none transition"
                                                />
                                                @error("locations.{$index}.address")
                                                    <p class="text-[11px] text-rose-600 mt-0.5">{{ $message }}</p>
                                                @enderror
                                            </div>



                                            <div>
                                                <input 
                                                    type="text" 
                                                    wire:model.blur="locations.{{ $index }}.phone" 
                                                    x-on:input="formatPhoneInput($el)"
                                                    placeholder="{{ __('Teléfono locación (ej. (770) 864-9359)') }}"
                                                    class="w-full bg-transparent hover:bg-zinc-100/60 focus:bg-white border border-transparent hover:border-zinc-200/60 focus:border-zinc-300 focus:ring-2 focus:ring-zinc-900/5 rounded-md -mx-1 px-1 py-1 text-xs text-zinc-800 focus:outline-none transition font-mono"
                                                />
                                                @error("locations.{$index}.phone")
                                                    <p class="text-[11px] text-rose-600 mt-0.5">{{ $message }}</p>
                                                @enderror
                                            </div>
                                            <div>
                                                <input 
                                                    type="email" 
                                                    wire:model.blur="locations.{{ $index }}.email" 
                                                    placeholder="{{ __('Correo electrónico de locación (ej. suwanee@suvidha.com)') }}"
                                                    class="w-full bg-transparent hover:bg-zinc-100/60 focus:bg-white border border-transparent hover:border-zinc-200/60 focus:border-zinc-300 focus:ring-2 focus:ring-zinc-900/5 rounded-md -mx-1 px-1 py-1 text-xs text-zinc-800 focus:outline-none transition"
                                                />
                                                @error("locations.{$index}.email")
                                                    <p class="text-[11px] text-rose-600 mt-0.5">{{ $message }}</p>
                                                @enderror
                                            </div>
                                            <div class="sm:col-span-2">
                                                <textarea 
                                                    wire:model="locations.{{ $index }}.notes" 
                                                    rows="1.5" 
                                                    placeholder="{{ __('Notas / Especificaciones técnicas (medidas, horarios de entrega, observaciones...)') }}"
                                                    class="w-full bg-transparent hover:bg-zinc-100/60 focus:bg-white border border-transparent hover:border-zinc-200/60 focus:border-zinc-300 focus:ring-2 focus:ring-zinc-900/5 rounded-md -mx-1 px-1 py-1 text-xs text-zinc-800 focus:outline-none transition"
                                                ></textarea>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach

                                {{-- Green Style Add Location Button Below --}}
                                <div class="pt-1">
                                    <button 
                                        type="button" 
                                        wire:click="addLocation"
                                        class="inline-flex items-center gap-1 text-xs text-emerald-600 font-semibold hover:text-emerald-700 transition cursor-pointer"
                                    >
                                        <x-lucide-plus class="w-3.5 h-3.5" />
                                        <span>{{ __('Agregar locación') }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- Section 3: Contacts (Subtle Box with Click Toggle to Show More/Less) --}}
                        <div 
                            x-data="{ openContacts: false }" 
                            x-effect="if (openContacts) setTimeout(() => $refs.contactsBox.scrollIntoView({ behavior: 'smooth', block: 'end' }), 220)"
                            class="pt-4 border-t border-zinc-200/60"
                        >
                            <div x-ref="contactsBox" class="p-3.5 bg-zinc-50/60 border border-zinc-200/60 rounded-xl transition-all">
                                <div 
                                    class="flex items-center justify-between cursor-pointer select-none" 
                                    @click="openContacts = !openContacts"
                                >
                                    <div class="flex items-center gap-2">
                                        <x-lucide-users class="w-3.5 h-3.5 text-zinc-500 shrink-0" />
                                        <span class="text-xs font-bold text-zinc-800 uppercase tracking-wider">
                                            {{ __('Contacts') }} ({{ count($contacts) }})
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <button 
                                            type="button" 
                                            wire:click.stop="addContact; openContacts = true"
                                            class="inline-flex items-center gap-1 text-xs text-emerald-600 font-semibold hover:text-emerald-700 transition cursor-pointer"
                                        >
                                            <x-lucide-plus class="w-3.5 h-3.5" />
                                            <span>{{ __('Agregar Contacto') }}</span>
                                        </button>
                                        <button type="button" class="text-zinc-400 hover:text-zinc-700 p-0.5 flex items-center gap-1 text-[11px] font-medium">
                                            <span x-text="openContacts ? '{{ __('Ver menos') }}' : '{{ __('Ver más') }}'"></span>
                                            <x-lucide-chevron-down class="w-3.5 h-3.5 transform transition-transform duration-200" ::class="{ 'rotate-180': openContacts }" />
                                        </button>
                                    </div>
                                </div>

                                <div x-show="openContacts" x-collapse class="mt-3 pt-3 border-t border-zinc-200/50 space-y-4">
                                    @foreach($contacts as $index => $contact)
                                        @php
                                            $dupWarning = $this->getDuplicateWarningForContact($index);
                                        @endphp
                                        <div class="{{ $index > 0 ? 'pt-3.5 border-t border-zinc-200/50' : '' }} space-y-2">
                                            @if($dupWarning)
                                                <div class="px-2.5 py-1.5 bg-amber-50/90 border border-amber-200/80 rounded-lg flex items-center justify-between gap-2 text-amber-800 text-[11px] select-none">
                                                    <div class="flex items-center gap-1.5 min-w-0">
                                                        <x-lucide-alert-triangle class="w-3.5 h-3.5 text-amber-600 shrink-0" />
                                                        <span class="truncate">
                                                            {{ __('¡Posible contacto duplicado con') }} <strong>"{{ $dupWarning }}"</strong>!
                                                        </span>
                                                    </div>
                                                    <div class="flex items-center gap-1.5 shrink-0">
                                                        <button 
                                                            type="button" 
                                                            wire:click="confirmMergeContact({{ $index }}, '{{ addslashes($dupWarning) }}')"
                                                            class="text-[10px] font-bold text-amber-900 bg-amber-200/70 hover:bg-amber-300 px-1.5 py-0.5 rounded transition cursor-pointer"
                                                        >
                                                            {{ __('Confirmar') }}
                                                        </button>
                                                        <button 
                                                            type="button" 
                                                            wire:click="dismissDuplicateContact({{ $index }})"
                                                            class="text-[10px] text-amber-700 hover:text-amber-950 font-medium cursor-pointer"
                                                        >
                                                            {{ __('Descartar') }}
                                                        </button>
                                                    </div>
                                                </div>
                                            @endif
                                            {{-- Inline Editable Title Header with Contact Name & Subtle Principal Toggle --}}
                                            <div class="flex items-center justify-between gap-3">
                                                <div class="flex items-center gap-1.5 flex-1 min-w-0 flex-wrap">
                                                    <x-lucide-user class="w-3.5 h-3.5 text-zinc-500 shrink-0" />
                                                    <input 
                                                        type="text" 
                                                        wire:model.live.debounce.150ms="contacts.{{ $index }}.name" 
                                                        placeholder="{{ __('NOMBRE DEL CONTACTO') }}"
                                                        :size="Math.max(12, ({{ json_encode($contact['name'] ?? '') }} || 'NOMBRE DEL CONTACTO').length + 1)"
                                                        class="bg-transparent hover:bg-zinc-100/70 focus:bg-white border border-transparent hover:border-zinc-200/60 focus:border-zinc-300 focus:ring-2 focus:ring-zinc-900/5 rounded-md -mx-1 px-1 py-0.5 text-xs font-bold text-zinc-900 uppercase tracking-tight focus:outline-none transition shrink-0 max-w-full"
                                                    />
                                                    <label class="inline-flex items-center gap-1 text-[10px] text-zinc-400 hover:text-zinc-600 cursor-pointer select-none shrink-0" title="{{ __('Marcar como contacto principal') }}">
                                                        <input 
                                                            type="checkbox" 
                                                            wire:model="contacts.{{ $index }}.is_primary"
                                                            class="rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500 w-3 h-3 opacity-70 transition cursor-pointer"
                                                        />
                                                        <span class="font-normal">{{ __('Principal') }}</span>
                                                    </label>
                                                </div>
                                                @if($index > 0)
                                                    <button 
                                                        type="button" 
                                                        wire:click="removeContact({{ $index }})"
                                                        class="text-[11px] text-zinc-400 hover:text-rose-600 transition cursor-pointer flex items-center gap-1 shrink-0"
                                                    >
                                                        <x-lucide-trash-2 class="w-3.5 h-3.5" />
                                                        <span>{{ __('Eliminar') }}</span>
                                                    </button>
                                                @endif
                                            </div>

                                            {{-- Compact Notion-Style Fields Grid (Department, Phone, Email) --}}
                                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                                <input 
                                                    type="text" 
                                                    wire:model="contacts.{{ $index }}.department" 
                                                    placeholder="{{ __('Departamento / Rol (ej. Compras)') }}"
                                                    class="w-full bg-transparent hover:bg-zinc-100/60 focus:bg-white border border-transparent focus:border-zinc-300 focus:ring-2 focus:ring-zinc-900/5 rounded-md px-2 py-1 text-xs text-zinc-700 focus:outline-none transition"
                                                />
                                                <input 
                                                    type="text" 
                                                    wire:model="contacts.{{ $index }}.phone" 
                                                    x-on:input="
                                                        let val = $el.value.replace(/\D/g, '');
                                                        if (val.length === 11 && val.startsWith('1')) val = val.substring(1);
                                                        if (val.length > 10) val = val.substring(0, 10);
                                                        if (val.length === 0) { $el.value = ''; }
                                                        else if (val.length <= 3) { $el.value = '(' + val; }
                                                        else if (val.length <= 6) { $el.value = '(' + val.substring(0, 3) + ') ' + val.substring(3); }
                                                        else { $el.value = '(' + val.substring(0, 3) + ') ' + val.substring(3, 6) + '-' + val.substring(6); }
                                                    "
                                                    placeholder="(000) 000-0000"
                                                    class="w-full bg-transparent hover:bg-zinc-100/60 focus:bg-white border border-transparent focus:border-zinc-300 focus:ring-2 focus:ring-zinc-900/5 rounded-md px-2 py-1 text-xs text-zinc-700 font-mono focus:outline-none transition"
                                                />
                                                <input 
                                                    type="email" 
                                                    wire:model="contacts.{{ $index }}.email" 
                                                    placeholder="{{ __('Correo electrónico') }}"
                                                    class="w-full bg-transparent hover:bg-zinc-100/60 focus:bg-white border border-transparent focus:border-zinc-300 focus:ring-2 focus:ring-zinc-900/5 rounded-md px-2 py-1 text-xs text-zinc-700 focus:outline-none transition"
                                                />
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- TAB 2: Enlaces & Recursos (Drive, Brandbook, Assets) --}}
                @if($activeTab === 'links')
                    <div class="space-y-5">
                        <div class="flex items-center justify-between pb-1">
                            <div>
                                <h3 class="text-xs font-bold text-zinc-900 uppercase tracking-wider">
                                    {{ __('Enlaces y Recursos Agrupados') }}
                                </h3>
                                <p class="text-[11px] text-zinc-500 mt-0.5">{{ __('Google Drive (Brandbook, Assets) o recursos por departamento.') }}</p>
                            </div>
                            <button 
                                type="button" 
                                wire:click="addLink"
                                class="inline-flex items-center gap-1 text-xs text-emerald-600 font-semibold hover:text-emerald-700 transition cursor-pointer"
                            >
                                <x-lucide-plus class="w-3.5 h-3.5" />
                                <span>{{ __('Agregar Enlace') }}</span>
                            </button>
                        </div>

                        <div class="space-y-4">
                            @foreach($links as $index => $link)
                                <div class="{{ $index > 0 ? 'pt-4 border-t border-zinc-200/60' : '' }} space-y-3">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="flex items-center gap-1.5 flex-1 min-w-0">
                                            <x-lucide-link class="w-3.5 h-3.5 text-zinc-500 shrink-0" />
                                            <input 
                                                type="text" 
                                                wire:model="links.{{ $index }}.label" 
                                                placeholder="{{ __('Nombre / Etiqueta del enlace') }}"
                                                class="w-full bg-transparent border border-transparent hover:bg-zinc-100/60 hover:border-zinc-200/60 focus:bg-white focus:border-zinc-300 focus:ring-2 focus:ring-zinc-900/5 rounded-md -mx-1 px-1 py-0.5 text-xs text-zinc-900 font-bold uppercase tracking-wider focus:outline-none transition"
                                            />
                                        </div>
                                        <button 
                                            type="button" 
                                            wire:click="removeLink({{ $index }})"
                                            class="text-[11px] text-zinc-400 hover:text-rose-600 transition cursor-pointer flex items-center gap-1 shrink-0"
                                        >
                                            <x-lucide-trash-2 class="w-3.5 h-3.5" />
                                            <span>{{ __('Eliminar Enlace') }}</span>
                                        </button>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                        <input 
                                            type="text" 
                                            wire:model="links.{{ $index }}.department" 
                                            placeholder="{{ __('Notas (ej. Producción, versión...)') }}"
                                            class="w-full bg-transparent hover:bg-zinc-100/60 focus:bg-white border border-transparent hover:border-zinc-200/60 focus:border-zinc-300 focus:ring-2 focus:ring-zinc-900/5 rounded-md -mx-1 px-1 py-1 text-xs text-zinc-800 focus:outline-none transition"
                                        />
                                        <input 
                                            type="url" 
                                            wire:model="links.{{ $index }}.url" 
                                            placeholder="{{ __('URL Enlace (Drive/Web)') }}"
                                            class="w-full bg-transparent hover:bg-zinc-100/60 focus:bg-white border border-transparent hover:border-zinc-200/60 focus:border-zinc-300 focus:ring-2 focus:ring-zinc-900/5 rounded-md -mx-1 px-1 py-1 text-xs text-zinc-800 focus:outline-none transition font-mono"
                                        />
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- TAB 3: Proyectos --}}
                @if($activeTab === 'projects' && $currentClient)
                    <div class="space-y-4">
                        <h3 class="text-xs font-bold text-zinc-900 uppercase tracking-wider">
                            {{ __('Historial de Proyectos') }} ({{ $currentClient->orders->count() }})
                        </h3>

                        <div class="divide-y divide-zinc-200/60 border-t border-b border-zinc-200/60">
                            @forelse($currentClient->orders as $order)
                                <div 
                                    wire:click="$dispatch('open-order-detail', { orderId: {{ $order->id }} })"
                                    class="py-3 px-1 hover:bg-zinc-50 flex items-center justify-between text-xs cursor-pointer transition group"
                                >
                                    <div class="min-w-0 pr-2 space-y-1">
                                        <div class="font-semibold text-zinc-900 flex items-center gap-2 truncate">
                                            @if($order->wo_number)
                                                <span class="px-1.5 py-0.5 rounded font-mono text-[10px] font-bold bg-zinc-900 text-white tracking-wide shrink-0">
                                                    {{ $order->wo_number }}
                                                </span>
                                            @endif
                                            @if($order->designer)
                                                <span class="px-1.5 py-0.2 rounded text-[9px] border font-medium shrink-0 {{ $order->getDesignerBadgeStyle() }}">
                                                    {{ $order->designer->name }}
                                                </span>
                                            @endif
                                            @php
                                                $locName = $order->location_name ?: $order->clientLocation?->name;
                                                $cleanTask = $order->clean_task_name;
                                                $showLocBadge = $locName && mb_strtolower(trim($locName), 'UTF-8') !== mb_strtolower(trim($cleanTask), 'UTF-8');
                                            @endphp
                                            @if($showLocBadge)
                                                <span class="inline-flex items-center gap-1 font-semibold text-zinc-700 bg-zinc-100 px-2 py-0.5 rounded text-[10px] shrink-0" title="Locación del Cliente">
                                                    <x-lucide-map-pin class="w-3 h-3 text-rose-500 shrink-0" />
                                                    <span>{{ $locName }}</span>
                                                </span>
                                            @endif
                                            <span class="group-hover:text-emerald-700 transition truncate">{{ $cleanTask }}</span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-1.5 shrink-0">
                                        @if($order->substatus)
                                            <span class="px-2 py-0.5 rounded text-[9px] font-medium border shrink-0 whitespace-nowrap {{ $order->substatus->badgeStyle() }}">
                                                {{ $order->substatus->label() }}
                                            </span>
                                        @endif
                                        @if($order->core_status)
                                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold border shrink-0 {{ $order->core_status->badgeStyle() }}">
                                                {{ $order->core_status->label() }}
                                            </span>
                                        @endif
                                        <x-lucide-panel-right class="w-4 h-4 text-zinc-400 group-hover:text-zinc-700 transition shrink-0 ml-1" />
                                    </div>
                                </div>
                            @empty
                                <p class="text-xs text-zinc-400 italic py-4 text-center">{{ __('No hay proyectos registrados para este cliente.') }}</p>
                            @endforelse
                        </div>
                    </div>
                @endif
            </div>

            {{-- Panel Footer --}}
            <div class="px-6 py-4 border-t border-zinc-100 bg-white flex items-center justify-end gap-2.5 shrink-0">
                <button 
                    type="button" 
                    @click="confirmClose(() => $wire.close())"
                    class="px-4 py-2 bg-zinc-100 hover:bg-zinc-200 text-zinc-700 text-xs font-medium rounded-lg cursor-pointer transition"
                >
                    {{ __('Cancelar') }}
                </button>
                <button 
                    type="button" 
                    wire:click="save"
                    :disabled="!isDirty()"
                    :class="isDirty() ? 'bg-emerald-600 hover:bg-emerald-700 text-white cursor-pointer shadow-sm shadow-emerald-600/20' : 'bg-zinc-200 text-zinc-400 border border-zinc-200 cursor-not-allowed'"
                    class="px-4 py-2 text-xs font-semibold rounded-lg transition flex items-center gap-1.5"
                >
                    <x-lucide-check class="w-3.5 h-3.5" x-show="isDirty()" />
                    <span>{{ __('Guardar Cliente') }}</span>
                </button>
            </div>
        </div>
    @endif
</div>



