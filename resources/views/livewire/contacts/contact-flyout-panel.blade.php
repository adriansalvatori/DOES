<div>
    @if($isOpen)
        {{-- Backdrop --}}
        <div 
            x-data
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            wire:click="close"
            class="fixed inset-0 bg-stone-900/30 backdrop-blur-xs z-50"
        ></div>

        {{-- Slide-over Panel --}}
        <div 
            x-data
            x-transition:enter="transition ease-out duration-250"
            x-transition:enter-start="translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="translate-x-full"
            class="fixed inset-y-0 right-0 w-full max-w-2xl bg-[#fafafa] border-l border-[#e9e9e7] shadow-2xl z-50 flex flex-col overflow-hidden"
            @keydown.escape.window="$wire.close()"
        >
            {{-- Header --}}
            <div class="p-4 bg-white border-b border-[#e9e9e7] flex items-center justify-between gap-3 shrink-0">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="w-8 h-8 rounded-full bg-stone-900 text-white flex items-center justify-center font-bold text-sm shrink-0">
                        {{ !empty($name) ? mb_substr($name, 0, 1, 'UTF-8') : '?' }}
                    </div>
                    <div class="min-w-0">
                        <span class="text-[10px] uppercase font-semibold text-zinc-400 tracking-wider block">
                            {{ $contactId ? __('Ficha de Cliente / Responsable') : __('Nuevo Cliente / Contacto') }}
                        </span>
                        <h2 class="text-base font-bold text-zinc-900 truncate uppercase">
                            {{ !empty($name) ? $name : __('Sin nombre') }}
                        </h2>
                    </div>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <button 
                        type="button" 
                        wire:click="save"
                        class="px-3 py-1.5 rounded-md bg-stone-900 hover:bg-stone-800 text-white font-medium text-xs shadow-2xs transition flex items-center gap-1.5 cursor-pointer"
                    >
                        <x-lucide-check class="w-3.5 h-3.5 text-stone-200" />
                        <span>{{ __('Guardar') }}</span>
                    </button>
                    <button 
                        type="button" 
                        wire:click="close"
                        class="p-1.5 rounded-md hover:bg-stone-100 text-zinc-400 hover:text-zinc-700 transition cursor-pointer"
                        title="{{ __('Cerrar') }}"
                    >
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                </div>
            </div>

            {{-- Flash Message --}}
            @if(session()->has('message'))
                <div class="bg-emerald-50 border-b border-emerald-200 text-emerald-800 px-4 py-2 text-xs font-medium flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-2">
                        <x-lucide-check-circle-2 class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                        <span>{{ session('message') }}</span>
                    </div>
                    <button @click="$el.parentElement.remove()" class="text-emerald-600 hover:text-emerald-900 cursor-pointer">
                        <x-lucide-x class="w-3 h-3" />
                    </button>
                </div>
            @endif

            {{-- Navigation Tabs --}}
            <div class="bg-white border-b border-[#e9e9e7] px-4 flex items-center gap-4 shrink-0 overflow-x-auto">
                <button 
                    type="button" 
                    wire:click="$set('activeTab', 'general')"
                    class="py-2.5 border-b-2 font-medium transition cursor-pointer flex items-center gap-1.5 text-xs {{ $activeTab === 'general' ? 'border-zinc-900 text-zinc-900 font-bold' : 'border-transparent text-zinc-500 hover:text-zinc-800' }}"
                >
                    <x-lucide-user class="w-3.5 h-3.5" />
                    <span>{{ __('General') }}</span>
                </button>

                @if($contactId)
                    <button 
                        type="button" 
                        wire:click="$set('activeTab', 'orders')"
                        class="py-2.5 border-b-2 font-medium transition cursor-pointer flex items-center gap-1.5 text-xs {{ $activeTab === 'orders' ? 'border-zinc-900 text-zinc-900 font-bold' : 'border-transparent text-zinc-500 hover:text-zinc-800' }}"
                    >
                        <x-lucide-folder class="w-3.5 h-3.5" />
                        <span>{{ __('Órdenes Asignadas') }}</span>
                        @if($assignedOrders->count() > 0)
                            <span class="px-1.5 py-0.2 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-bold">
                                {{ $assignedOrders->count() }}
                            </span>
                        @endif
                    </button>

                    <button 
                        type="button" 
                        wire:click="$set('activeTab', 'merge')"
                        class="py-2.5 border-b-2 font-medium transition cursor-pointer flex items-center gap-1.5 text-xs {{ $activeTab === 'merge' ? 'border-zinc-900 text-zinc-900 font-bold' : 'border-transparent text-zinc-500 hover:text-zinc-800' }}"
                    >
                        <x-lucide-git-merge class="w-3.5 h-3.5" />
                        <span>{{ __('Fusionar / Duplicados') }}</span>
                        @if(count($mergeSuggestions) > 0)
                            <span class="px-1.5 py-0.2 rounded-full bg-amber-100 text-amber-800 text-[10px] font-bold">
                                {{ count($mergeSuggestions) }}
                            </span>
                        @endif
                    </button>
                @endif
            </div>

            {{-- Body Content --}}
            <div class="flex-1 min-h-0 overflow-y-auto p-5 custom-vertical-scrollbar space-y-6">
                
                {{-- TAB: GENERAL --}}
                @if($activeTab === 'general')
                    {{-- Section 1: Basic Info --}}
                    <div class="bg-white border border-[#e9e9e7] rounded-xl p-4 shadow-2xs space-y-4">
                        <h3 class="text-xs font-bold text-zinc-900 uppercase tracking-wider flex items-center gap-1.5">
                            <x-lucide-user-check class="w-3.5 h-3.5 text-zinc-500" />
                            <span>{{ __('Datos Personales y Contacto') }}</span>
                        </h3>

                        <div class="space-y-3">
                            {{-- Nombre --}}
                            <div>
                                <label class="block text-[11px] font-bold text-zinc-700 uppercase tracking-wider mb-1">
                                    {{ __('Nombre Completo') }} <span class="text-rose-500">*</span>
                                </label>
                                <input 
                                    type="text" 
                                    wire:model.defer="name" 
                                    placeholder="{{ __('Ej: MARCELA DUQUE') }}" 
                                    class="w-full bg-[#fbfbfa] border border-[#e9e9e7] rounded-md px-3 py-1.5 text-xs font-semibold text-zinc-900 focus:outline-none focus:border-stone-400 uppercase"
                                />
                                @error('name') <span class="text-rose-500 text-[11px] mt-0.5 block">{{ $message }}</span> @enderror
                            </div>

                            {{-- Cargo / Rol --}}
                            <div>
                                <label class="block text-[11px] font-bold text-zinc-700 uppercase tracking-wider mb-1">
                                    {{ __('Cargo / Rol / Departamento (Opcional)') }}
                                </label>
                                <input 
                                    type="text" 
                                    wire:model.defer="role_title" 
                                    placeholder="{{ __('Ej: Gerente de Compras, Project Manager, Owner') }}" 
                                    class="w-full bg-[#fbfbfa] border border-[#e9e9e7] rounded-md px-3 py-1.5 text-xs text-zinc-800 focus:outline-none focus:border-stone-400"
                                />
                            </div>

                            {{-- Teléfono & Email --}}
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[11px] font-bold text-zinc-700 uppercase tracking-wider mb-1">
                                        {{ __('Teléfono') }}
                                    </label>
                                    <div class="relative">
                                        <input 
                                            type="text" 
                                            wire:model.defer="phone" 
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
                                            class="w-full bg-[#fbfbfa] border border-[#e9e9e7] rounded-md px-3 py-1.5 text-xs font-mono text-zinc-900 focus:outline-none focus:border-stone-400"
                                        />
                                    </div>
                                    @error('phone') <span class="text-rose-500 text-[11px] mt-0.5 block">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-[11px] font-bold text-zinc-700 uppercase tracking-wider mb-1">
                                        {{ __('Correo Electrónico') }}
                                    </label>
                                    <input 
                                        type="email" 
                                        wire:model.defer="email" 
                                        placeholder="{{ __('ejemplo@empresa.com') }}" 
                                        class="w-full bg-[#fbfbfa] border border-[#e9e9e7] rounded-md px-3 py-1.5 text-xs text-zinc-900 focus:outline-none focus:border-stone-400"
                                    />
                                    @error('email') <span class="text-rose-500 text-[11px] mt-0.5 block">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Section 2: Communication Preferences --}}
                    <div class="bg-white border border-[#e9e9e7] rounded-xl p-4 shadow-2xs space-y-4">
                        <h3 class="text-xs font-bold text-zinc-900 uppercase tracking-wider flex items-center gap-1.5">
                            <x-lucide-message-square class="w-3.5 h-3.5 text-zinc-500" />
                            <span>{{ __('Preferencias de Comunicación') }}</span>
                        </h3>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            {{-- Canal Preferido --}}
                            <div>
                                <label class="block text-[11px] font-bold text-zinc-700 uppercase tracking-wider mb-1.5">
                                    {{ __('Canal Preferido') }}
                                </label>
                                <div class="grid grid-cols-2 gap-1.5">
                                    <button 
                                        type="button" 
                                        wire:click="$set('preferred_channel', 'whatsapp')"
                                        class="p-2 rounded-lg border text-xs font-semibold flex items-center gap-1.5 transition cursor-pointer {{ $preferred_channel === 'whatsapp' ? 'bg-emerald-50 border-emerald-500 text-emerald-900 shadow-2xs' : 'bg-[#fbfbfa] border-[#e9e9e7] text-zinc-600 hover:bg-stone-50' }}"
                                    >
                                        <x-lucide-message-circle class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                                        <span>WhatsApp</span>
                                    </button>

                                    <button 
                                        type="button" 
                                        wire:click="$set('preferred_channel', 'call')"
                                        class="p-2 rounded-lg border text-xs font-semibold flex items-center gap-1.5 transition cursor-pointer {{ $preferred_channel === 'call' ? 'bg-blue-50 border-blue-500 text-blue-900 shadow-2xs' : 'bg-[#fbfbfa] border-[#e9e9e7] text-zinc-600 hover:bg-stone-50' }}"
                                    >
                                        <x-lucide-phone class="w-3.5 h-3.5 text-blue-600 shrink-0" />
                                        <span>{{ __('Llamada') }}</span>
                                    </button>

                                    <button 
                                        type="button" 
                                        wire:click="$set('preferred_channel', 'email')"
                                        class="p-2 rounded-lg border text-xs font-semibold flex items-center gap-1.5 transition cursor-pointer {{ $preferred_channel === 'email' ? 'bg-amber-50 border-amber-500 text-amber-900 shadow-2xs' : 'bg-[#fbfbfa] border-[#e9e9e7] text-zinc-600 hover:bg-stone-50' }}"
                                    >
                                        <x-lucide-mail class="w-3.5 h-3.5 text-amber-600 shrink-0" />
                                        <span>Email</span>
                                    </button>

                                    <button 
                                        type="button" 
                                        wire:click="$set('preferred_channel', 'any')"
                                        class="p-2 rounded-lg border text-xs font-semibold flex items-center gap-1.5 transition cursor-pointer {{ $preferred_channel === 'any' ? 'bg-stone-100 border-stone-500 text-zinc-900 shadow-2xs' : 'bg-[#fbfbfa] border-[#e9e9e7] text-zinc-600 hover:bg-stone-50' }}"
                                    >
                                        <x-lucide-messages-square class="w-3.5 h-3.5 text-zinc-500 shrink-0" />
                                        <span>{{ __('Cualquiera') }}</span>
                                    </button>
                                </div>
                            </div>

                            {{-- Idioma de Contacto --}}
                            <div>
                                <label class="block text-[11px] font-bold text-zinc-700 uppercase tracking-wider mb-1.5">
                                    {{ __('Idioma de Contacto') }}
                                </label>
                                <div class="grid grid-cols-1 gap-1.5">
                                    <button 
                                        type="button" 
                                        wire:click="$set('preferred_language', 'es')"
                                        class="p-2 rounded-lg border text-xs font-semibold flex items-center justify-between transition cursor-pointer {{ $preferred_language === 'es' ? 'bg-stone-100 border-stone-500 text-zinc-900 shadow-2xs' : 'bg-[#fbfbfa] border-[#e9e9e7] text-zinc-600 hover:bg-stone-50' }}"
                                    >
                                        <div class="flex items-center gap-2">
                                            <x-lucide-languages class="w-3.5 h-3.5 text-zinc-500 shrink-0" />
                                            <span class="font-mono font-bold text-[10px] px-1.5 py-0.2 rounded bg-stone-200/80 text-stone-800">ES</span>
                                            <span>{{ __('Español') }}</span>
                                        </div>
                                        @if($preferred_language === 'es') <x-lucide-check class="w-3.5 h-3.5 text-stone-700" /> @endif
                                    </button>

                                    <button 
                                        type="button" 
                                        wire:click="$set('preferred_language', 'en')"
                                        class="p-2 rounded-lg border text-xs font-semibold flex items-center justify-between transition cursor-pointer {{ $preferred_language === 'en' ? 'bg-indigo-50 border-indigo-500 text-indigo-900 shadow-2xs' : 'bg-[#fbfbfa] border-[#e9e9e7] text-zinc-600 hover:bg-stone-50' }}"
                                    >
                                        <div class="flex items-center gap-2">
                                            <x-lucide-languages class="w-3.5 h-3.5 text-indigo-500 shrink-0" />
                                            <span class="font-mono font-bold text-[10px] px-1.5 py-0.2 rounded bg-indigo-100 text-indigo-800">EN</span>
                                            <span>{{ __('Inglés (English)') }}</span>
                                        </div>
                                        @if($preferred_language === 'en') <x-lucide-check class="w-3.5 h-3.5 text-indigo-700" /> @endif
                                    </button>

                                    <button 
                                        type="button" 
                                        wire:click="$set('preferred_language', 'both')"
                                        class="p-2 rounded-lg border text-xs font-semibold flex items-center justify-between transition cursor-pointer {{ $preferred_language === 'both' ? 'bg-purple-50 border-purple-500 text-purple-900 shadow-2xs' : 'bg-[#fbfbfa] border-[#e9e9e7] text-zinc-600 hover:bg-stone-50' }}"
                                    >
                                        <div class="flex items-center gap-2">
                                            <x-lucide-languages class="w-3.5 h-3.5 text-purple-500 shrink-0" />
                                            <span class="font-mono font-bold text-[10px] px-1.5 py-0.2 rounded bg-purple-100 text-purple-800">ES / EN</span>
                                            <span>{{ __('Bilingüe / Ambos idiomas') }}</span>
                                        </div>
                                        @if($preferred_language === 'both') <x-lucide-check class="w-3.5 h-3.5 text-purple-700" /> @endif
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Section 3: Linked Brands / Companies --}}
                    <div class="bg-white border border-[#e9e9e7] rounded-xl p-4 shadow-2xs space-y-3">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xs font-bold text-zinc-900 uppercase tracking-wider flex items-center gap-1.5">
                                <x-lucide-building-2 class="w-3.5 h-3.5 text-zinc-500" />
                                <span>{{ __('Marcas / Compañías Vinculadas') }}</span>
                            </h3>
                            <span class="text-[11px] text-zinc-400">
                                {{ count($linkedClientIds) }} {{ __('vinculadas') }}
                            </span>
                        </div>
                        <p class="text-[11px] text-zinc-500">
                            {{ __('Este cliente podrá atender órdenes de cualquiera de las marcas vinculadas a continuación:') }}
                        </p>

                        {{-- Chips of currently linked brands --}}
                        <div class="flex flex-wrap gap-1.5 min-h-8 p-2 bg-[#fbfbfa] border border-[#e9e9e7] rounded-lg">
                            @php
                                $linkedClients = \App\Models\Client::whereIn('id', $linkedClientIds)->orderBy('name')->get();
                            @endphp
                            @forelse($linkedClients as $lClient)
                                @php
                                    $isPrimary = $currentContact ? (bool) $currentContact->clients->firstWhere('id', $lClient->id)?->pivot?->is_primary : false;
                                @endphp
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-stone-900 text-white text-xs font-medium shadow-2xs">
                                    <x-lucide-building-2 class="w-3 h-3 text-stone-300" />
                                    <span>{{ $lClient->name }}</span>

                                    @if($isPrimary)
                                        <span class="px-1.5 py-0.5 rounded text-[9px] font-black uppercase bg-amber-400 text-stone-950 flex items-center gap-0.5 shadow-2xs" title="{{ __('Contacto Principal de esta compañía') }}">
                                            <x-lucide-star class="w-2.5 h-2.5 fill-current" />
                                            <span>MAIN</span>
                                        </span>
                                    @else
                                        <button 
                                            type="button" 
                                            wire:click="setAsPrimaryClient({{ $lClient->id }})"
                                            class="text-[10px] text-zinc-400 hover:text-amber-300 transition cursor-pointer flex items-center gap-0.5 ml-0.5 hover:underline"
                                            title="{{ __('Establecer como contacto principal (MAIN) de esta compañía') }}"
                                        >
                                            <x-lucide-star class="w-2.5 h-2.5" />
                                            <span>Hacer MAIN</span>
                                        </button>
                                    @endif

                                    <button 
                                        type="button" 
                                        wire:click="toggleClientLink({{ $lClient->id }})"
                                        class="hover:text-rose-300 transition cursor-pointer ml-1"
                                        title="{{ __('Desvincular marca') }}"
                                    >
                                        <x-lucide-x class="w-3 h-3" />
                                    </button>
                                </span>
                            @empty
                                <span class="text-xs text-zinc-400 italic py-0.5">
                                    {{ __('Sin marcas vinculadas. Busca y agrega una abajo:') }}
                                </span>
                            @endforelse
                        </div>

                        {{-- Search & Picker to link more brands --}}
                        <div class="pt-2">
                            <div class="relative mb-2">
                                <x-lucide-search class="w-3.5 h-3.5 text-zinc-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                                <input 
                                    type="text" 
                                    wire:model.live.debounce.150ms="clientSearch" 
                                    placeholder="{{ __('Buscar marca para vincular...') }}" 
                                    class="w-full bg-[#fbfbfa] border border-[#e9e9e7] rounded-md pl-7 pr-3 py-1 text-xs text-zinc-800 placeholder-zinc-400 focus:outline-none focus:border-stone-400"
                                />
                            </div>

                            <div class="max-h-36 overflow-y-auto space-y-1 custom-vertical-scrollbar border border-zinc-100 rounded-lg p-1 bg-white">
                                @foreach($allClients as $clientOpt)
                                    @php $isLinked = in_array($clientOpt->id, $linkedClientIds, true); @endphp
                                    <button 
                                        type="button" 
                                        wire:click="toggleClientLink({{ $clientOpt->id }})"
                                        class="w-full text-left px-2.5 py-1 rounded-md text-xs flex items-center justify-between transition cursor-pointer {{ $isLinked ? 'bg-stone-100 text-zinc-900 font-bold' : 'hover:bg-stone-50 text-zinc-700' }}"
                                    >
                                        <span class="truncate">{{ $clientOpt->name }}</span>
                                        @if($isLinked)
                                            <span class="text-[10px] text-emerald-600 font-semibold flex items-center gap-0.5">
                                                <x-lucide-check class="w-3 h-3" />
                                                <span>{{ __('Vinculada') }}</span>
                                            </span>
                                        @else
                                            <span class="text-[10px] text-zinc-400 font-medium">
                                                + {{ __('Vincular') }}
                                            </span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- Section 4: Internal Notes --}}
                    <div class="bg-white border border-[#e9e9e7] rounded-xl p-4 shadow-2xs space-y-2">
                        <h3 class="text-xs font-bold text-zinc-900 uppercase tracking-wider flex items-center gap-1.5">
                            <x-lucide-file-text class="w-3.5 h-3.5 text-zinc-500" />
                            <span>{{ __('Notas de Atención') }}</span>
                        </h3>
                        <textarea 
                            wire:model.defer="notes" 
                            rows="3" 
                            placeholder="{{ __('Ej: Solo contactar por las mañanas. Muy exigente con los pantones. etc.') }}"
                            class="w-full bg-[#fbfbfa] border border-[#e9e9e7] rounded-md p-2.5 text-xs text-zinc-800 focus:outline-none focus:border-stone-400"
                        ></textarea>
                    </div>
                @endif

                {{-- TAB: ORDENES ASIGNADAS --}}
                @if($activeTab === 'orders' && $currentContact)
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-xs font-bold text-zinc-900 uppercase tracking-wider">
                                    {{ __('Órdenes de') }} {{ $currentContact->name }}
                                </h3>
                                <p class="text-[11px] text-zinc-500">
                                    {{ __('Solo las órdenes donde este cliente es el responsable asignado:') }}
                                </p>
                            </div>
                            <span class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200 text-xs font-bold">
                                {{ $assignedOrders->count() }} {{ __('activas') }}
                            </span>
                        </div>

                        {{-- Active Orders List --}}
                        <div class="bg-white border border-[#e9e9e7] rounded-xl shadow-2xs overflow-hidden">
                            <div class="max-h-96 overflow-y-auto custom-vertical-scrollbar">
                                <table class="w-full text-left text-xs text-zinc-700 table-fixed">
                                    <colgroup>
                                        <col class="w-20">
                                        <col class="w-auto">
                                        <col class="w-28">
                                        <col class="w-24">
                                        <col class="w-24">
                                    </colgroup>
                                    <thead class="bg-[#f7f7f5] text-zinc-500 font-semibold border-b border-[#e9e9e7] uppercase text-[10px] tracking-wider sticky top-0 z-10">
                                        <tr>
                                            <th class="py-2 px-3">{{ __('WO') }}</th>
                                            <th class="py-2 px-3">{{ __('Tarea / Compañía') }}</th>
                                            <th class="py-2 px-3">{{ __('Diseñador') }}</th>
                                            <th class="py-2 px-3">{{ __('Estado') }}</th>
                                            <th class="py-2 px-3 text-right">{{ __('Entrega') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-[#e9e9e7]">
                                        @forelse($assignedOrders as $ord)
                                            <tr 
                                                class="hover:bg-stone-50 transition cursor-pointer"
                                                wire:click="$dispatch('open-order-detail', { orderId: {{ $ord->id }} })"
                                            >
                                                <td class="py-2 px-3 font-mono font-bold text-zinc-900">
                                                    {{ $ord->wo_number ?: '-' }}
                                                </td>
                                                <td class="py-2 px-3 min-w-0">
                                                    <div class="flex flex-col min-w-0">
                                                        <span class="font-medium text-zinc-900 truncate">
                                                            {{ $ord->clean_task_name ?: $ord->task_name }}
                                                        </span>
                                                        <div class="flex items-center gap-1.5 min-w-0">
                                                            <span class="text-[10px] text-zinc-500 uppercase truncate">
                                                                {{ $ord->company_name ?: ($ord->client?->name ?? '-') }}
                                                            </span>
                                                            @if($ord->contact_id !== $currentContact->id && $ord->responsible_person !== $currentContact->name)
                                                                <span class="px-1 py-0.2 rounded text-[8px] font-bold bg-amber-50 text-amber-700 border border-amber-200 shrink-0">
                                                                    {{ __('Vía Main Contact') }}
                                                                </span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="py-2 px-3 text-zinc-700 truncate">
                                                    {{ $ord->designer?->name ?: '-' }}
                                                </td>
                                                <td class="py-2 px-3">
                                                    @if($ord->core_status)
                                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-stone-100 text-stone-700 border border-stone-200">
                                                            {{ $ord->core_status->label() }}
                                                        </span>
                                                    @endif
                                                </td>
                                                <td class="py-2 px-3 text-right text-zinc-600 font-mono text-[10px]">
                                                    {{ $ord->current_due_date ? $ord->current_due_date->format('d/m/Y') : '-' }}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="py-8 text-center text-zinc-400 text-xs">
                                                    {{ __('No tiene órdenes activas en este momento.') }}
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- Archived and Backlog counts --}}
                        <div class="grid grid-cols-2 gap-3 pt-2">
                            <div class="p-3 bg-stone-50 border border-stone-200/80 rounded-lg text-xs">
                                <span class="text-zinc-500 block text-[10px] uppercase font-semibold">{{ __('Órdenes en Backlog') }}</span>
                                <span class="text-base font-bold text-zinc-900">{{ $backlogOrders->count() }}</span>
                            </div>
                            <div class="p-3 bg-stone-50 border border-stone-200/80 rounded-lg text-xs">
                                <span class="text-zinc-500 block text-[10px] uppercase font-semibold">{{ __('Órdenes Archivadas') }}</span>
                                <span class="text-base font-bold text-zinc-900">{{ $archivedOrders->count() }}</span>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- TAB: FUSIONAR / DUPLICADOS --}}
                @if($activeTab === 'merge' && $currentContact)
                    <div class="space-y-4">
                        <div>
                            <h3 class="text-xs font-bold text-zinc-900 uppercase tracking-wider">
                                {{ __('Herramienta de Fusión de Clientes') }}
                            </h3>
                            <p class="text-[11px] text-zinc-500">
                                {{ __('Si este cliente está duplicado, puedes fusionarlo con otro. Se transferirán todas sus órdenes y marcas vinculadas:') }}
                            </p>
                        </div>

                        {{-- Sugerencias Automáticas --}}
                        @if(count($mergeSuggestions) > 0)
                            <div class="bg-amber-50/90 border border-amber-200 rounded-xl p-4 space-y-3">
                                <div class="flex items-center gap-1.5 text-amber-900 font-bold text-xs">
                                    <x-lucide-alert-triangle class="w-4 h-4 text-amber-600" />
                                    <span>{{ __('Sugerencias de duplicados detectadas:') }}</span>
                                </div>
                                <div class="space-y-2">
                                    @foreach($mergeSuggestions as $sugg)
                                        @php $candidate = $sugg['contact']; @endphp
                                        <div class="flex items-center justify-between p-2.5 bg-white rounded-lg border border-amber-200/70 text-xs gap-3">
                                            <div>
                                                <span class="font-bold text-zinc-900 uppercase">{{ $candidate->name }}</span>
                                                <div class="flex items-center gap-2 text-[10px] text-zinc-500 mt-0.5">
                                                    @if($candidate->phone) <span>{{ $candidate->phone }}</span> @endif
                                                    @if($candidate->email) <span>{{ $candidate->email }}</span> @endif
                                                </div>
                                            </div>
                                            <button 
                                                type="button" 
                                                wire:click="executeMerge({{ $candidate->id }})"
                                                wire:confirm="¿Seguro que deseas fusionar a {{ $currentContact->name }} dentro de {{ $candidate->name }}? Esta acción es irreversible."
                                                class="px-2.5 py-1 rounded bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs shadow-2xs transition cursor-pointer shrink-0"
                                            >
                                                {{ __('Fusionar aquí') }}
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Fusión manual --}}
                        <div class="bg-white border border-[#e9e9e7] rounded-xl p-4 shadow-2xs space-y-3">
                            <h4 class="text-xs font-bold text-zinc-800 uppercase tracking-wider">
                                {{ __('Fusionar manualmente con otro contacto') }}
                            </h4>
                            <p class="text-[11px] text-zinc-500">
                                {{ __('Selecciona el contacto destino que conservará toda la información:') }}
                            </p>

                            <div class="space-y-2">
                                <select 
                                    wire:model="selectedMergeTargetId"
                                    class="w-full bg-[#fbfbfa] border border-[#e9e9e7] rounded-md px-3 py-1.5 text-xs text-zinc-800 focus:outline-none focus:border-stone-400"
                                >
                                    <option value="">{{ __('-- Seleccionar contacto destino --') }}</option>
                                    @foreach($allOtherContacts as $otherC)
                                        <option value="{{ $otherC->id }}">{{ $otherC->name }} ({{ $otherC->phone ?: 'Sin teléfono' }})</option>
                                    @endforeach
                                </select>

                                @if($selectedMergeTargetId)
                                    <button 
                                        type="button" 
                                        wire:click="executeMerge({{ $selectedMergeTargetId }})"
                                        wire:confirm="¿Confirmas fusionar {{ $currentContact->name }} en el contacto seleccionado? Todas las órdenes y marcas se transferirán."
                                        class="w-full py-1.5 rounded-md bg-stone-900 hover:bg-stone-800 text-white font-bold text-xs shadow-2xs transition cursor-pointer"
                                    >
                                        {{ __('Confirmar Fusión') }}
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

            </div>
        </div>
    @endif
</div>
