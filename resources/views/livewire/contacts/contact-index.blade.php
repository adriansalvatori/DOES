<div class="h-full flex flex-col space-y-3 min-h-0 max-w-7xl mx-auto w-full">
    
    <!-- Header Controls -->
    <div id="tour-contact-header" class="bg-white border border-[#e9e9e7] rounded-xl p-3.5 flex flex-col space-y-3 shadow-2xs shrink-0">
        {{-- Row 1: Title & Badges --}}
        <div class="flex items-center justify-between gap-2.5 min-w-0 flex-wrap">
            <div class="flex items-center gap-2.5 min-w-0 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-extrabold text-zinc-900 tracking-tight">{{ __('Directorio de Clientes') }}</h1>
                <span class="px-2.5 py-1 rounded-full bg-stone-100 border border-stone-200 text-[11px] font-bold text-zinc-600">{{ $totalContactsCount }}</span>
            </div>

            <!-- Nuevo Cliente -->
            <button 
                type="button" 
                wire:click="openContactDetail"
                class="px-3 py-1 h-7 rounded-md bg-stone-900 hover:bg-stone-800 text-white font-medium text-xs shadow-2xs transition flex items-center gap-1 cursor-pointer shrink-0"
            >
                <x-lucide-plus class="w-3 h-3 text-stone-100" />
                <span>{{ __('Nuevo Cliente') }}</span>
            </button>
        </div>

        {{-- Row 2: Searchbar and Filters --}}
        <div class="grid grid-cols-1 sm:grid-cols-12 gap-2 w-full">
            <!-- Searchbar -->
            <div class="relative sm:col-span-5">
                <x-lucide-search class="w-3.5 h-3.5 text-zinc-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                <input 
                    type="text" 
                    wire:model.live.debounce.200ms="search" 
                    placeholder="{{ __('Buscar por nombre, teléfono, email, marca...') }}" 
                    class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-md pl-7 pr-2.5 py-1 text-xs text-zinc-800 placeholder-zinc-400 focus:outline-none focus:border-stone-400 w-full"
                >
            </div>

            <!-- Canal Filter -->
            <div class="sm:col-span-2">
                <select 
                    wire:model.live="channelFilter"
                    class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-md px-2 py-1 text-xs text-zinc-700 focus:outline-none focus:border-stone-400 w-full"
                >
                    <option value="">{{ __('Todos los canales') }}</option>
                    <option value="whatsapp">WhatsApp</option>
                    <option value="call">{{ __('Llamada') }}</option>
                    <option value="email">Email</option>
                    <option value="any">{{ __('Cualquiera') }}</option>
                </select>
            </div>

            <!-- Language Filter -->
            <div class="sm:col-span-2">
                <select 
                    wire:model.live="languageFilter"
                    class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-md px-2 py-1 text-xs text-zinc-700 focus:outline-none focus:border-stone-400 w-full"
                >
                    <option value="">{{ __('Todos los idiomas') }}</option>
                    <option value="es">ES - {{ __('Español') }}</option>
                    <option value="en">EN - {{ __('Inglés') }}</option>
                    <option value="both">ES / EN - {{ __('Bilingüe') }}</option>
                </select>
            </div>

            <!-- Client / Company Filter -->
            <div class="sm:col-span-3">
                <select 
                    wire:model.live="clientFilter"
                    class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-md px-2 py-1 text-xs text-zinc-700 focus:outline-none focus:border-stone-400 w-full"
                >
                    <option value="">{{ __('Todas las compañías') }}</option>
                    @foreach($allClients as $cl)
                        <option value="{{ $cl->id }}">{{ $cl->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <!-- Notification Flash Message -->
    @if(session()->has('message'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-2.5 rounded-lg text-xs font-medium flex items-center justify-between shrink-0">
            <div class="flex items-center gap-2">
                <x-lucide-check-circle-2 class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                <span>{{ session('message') }}</span>
            </div>
            <button @click="$el.parentElement.remove()" class="text-emerald-600 hover:text-emerald-900 cursor-pointer">
                <x-lucide-x class="w-3 h-3" />
            </button>
        </div>
    @endif

    <!-- Contact List Card -->
    <div class="flex-1 min-h-0 bg-white border border-[#e9e9e7] rounded-xl shadow-2xs flex flex-col overflow-hidden">
        <div class="flex-1 min-h-0 overflow-y-auto overflow-x-auto custom-vertical-scrollbar">
            <table class="w-full text-left text-xs text-zinc-700 table-fixed min-w-[960px]">
                <colgroup>
                    <col class="w-48 sm:w-56">       {{-- Cliente --}}
                    <col class="w-auto">             {{-- Compañías --}}
                    <col class="w-36 sm:w-40">       {{-- Teléfono --}}
                    <col class="w-48 sm:w-56">       {{-- Email --}}
                    <col class="w-20 sm:w-24">       {{-- Idioma --}}
                    <col class="w-28 sm:w-32">       {{-- Preferencia --}}
                    <col class="w-24 sm:w-28 text-right">  {{-- Órdenes --}}
                    <col class="w-10 text-right">    {{-- Acciones --}}
                </colgroup>
                <thead class="bg-[#f7f7f5] text-zinc-500 font-semibold border-b border-[#e9e9e7] uppercase text-[10px] tracking-wider sticky top-0 z-10">
                    <tr>
                        <th class="py-2.5 px-3.5">{{ __('Cliente') }}</th>
                        <th class="py-2.5 px-3">{{ __('Compañías Vinculadas') }}</th>
                        <th class="py-2.5 px-3">{{ __('Teléfono') }}</th>
                        <th class="py-2.5 px-3">{{ __('Email') }}</th>
                        <th class="py-2.5 px-3 text-center">{{ __('Idioma') }}</th>
                        <th class="py-2.5 px-3">{{ __('Preferencia') }}</th>
                        <th class="py-2.5 px-3 text-right">{{ __('Órdenes') }}</th>
                        <th class="py-2.5 pr-3 pl-1 w-10 text-right"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#e9e9e7]">
                    @forelse($contacts as $contact)
                        @php
                            $channelBadge = $contact->channel_badge;
                            $langBadge = $contact->language_badge;
                            $whatsappUrl = $contact->whatsapp_url;
                        @endphp
                        <tr 
                            wire:click="openContactDetail({{ $contact->id }})" 
                            class="group hover:bg-[#f7f7f5] transition cursor-pointer"
                        >
                            {{-- Col 1: Cliente / Responsable --}}
                            <td class="py-2.5 px-3.5 min-w-0">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <div class="w-7 h-7 rounded-full bg-stone-100 border border-stone-200 text-stone-700 flex items-center justify-center font-bold text-xs shrink-0">
                                        {{ mb_substr($contact->name, 0, 1, 'UTF-8') }}
                                    </div>
                                    <div class="flex flex-col min-w-0">
                                        <span class="font-bold text-zinc-900 text-xs tracking-tight uppercase truncate group-hover:text-stone-900">
                                            {{ $contact->name }}
                                        </span>
                                        @if($contact->role_title)
                                            <span class="text-[10px] text-zinc-500 truncate mt-0.5">
                                                {{ $contact->role_title }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            {{-- Col 2: Marcas / Compañías Vinculadas --}}
                            <td class="py-2.5 px-3 min-w-0">
                                <div class="flex flex-wrap gap-1 min-w-0">
                                    @forelse($contact->clients as $client)
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-zinc-100 hover:bg-zinc-200/80 text-zinc-800 text-[10px] font-medium border border-zinc-200/60 transition truncate">
                                            <x-lucide-building-2 class="w-2.5 h-2.5 text-zinc-400 shrink-0" />
                                            <span class="truncate">{{ $client->name }}</span>
                                        </span>
                                    @empty
                                        <span class="text-[11px] text-zinc-400 italic">
                                            {{ __('Sin compañía vinculada') }}
                                        </span>
                                    @endforelse
                                </div>
                            </td>

                            {{-- Col 3: Teléfono (+ Enlace WhatsApp) --}}
                            <td class="py-2.5 px-3 min-w-0">
                                @if($contact->phone)
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="flex items-center gap-1 text-zinc-700 font-mono text-[11px] shrink-0">
                                            <x-lucide-phone class="w-3 h-3 text-zinc-400 shrink-0" />
                                            <span>{{ $contact->phone }}</span>
                                        </span>
                                        @if($whatsappUrl)
                                            <a 
                                                href="{{ $whatsappUrl }}" 
                                                target="_blank" 
                                                rel="noopener noreferrer" 
                                                @click.stop
                                                class="inline-flex items-center gap-0.5 px-1 py-0.2 rounded bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 text-[9px] font-semibold transition shrink-0"
                                                title="{{ __('Abrir chat en WhatsApp') }}"
                                            >
                                                <x-lucide-message-circle class="w-2.5 h-2.5 text-emerald-600" />
                                                <span>WA</span>
                                            </a>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-zinc-300 text-[11px]">—</span>
                                @endif
                            </td>

                            {{-- Col 4: Correo Electrónico --}}
                            <td class="py-2.5 px-3 min-w-0">
                                @if($contact->email)
                                    <a 
                                        href="mailto:{{ $contact->email }}" 
                                        @click.stop
                                        class="inline-flex items-center gap-1 text-zinc-600 hover:text-zinc-900 truncate text-[11px] min-w-0 max-w-full"
                                        title="{{ $contact->email }}"
                                    >
                                        <x-lucide-mail class="w-3 h-3 text-zinc-400 shrink-0" />
                                        <span class="truncate">{{ $contact->email }}</span>
                                    </a>
                                @else
                                    <span class="text-zinc-300 text-[11px]">—</span>
                                @endif
                            </td>

                            {{-- Col 5: Idioma Preferido --}}
                            <td class="py-2.5 px-3 text-center whitespace-nowrap">
                                @if($langBadge['code'] === 'EN')
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold font-mono bg-indigo-50 text-indigo-700 border border-indigo-200/80" title="Idioma: {{ $langBadge['label'] }}">
                                        <x-lucide-languages class="w-2.5 h-2.5 text-indigo-400 shrink-0" />
                                        <span>{{ $langBadge['code'] }}</span>
                                    </span>
                                @elseif($langBadge['code'] === 'ES / EN')
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold font-mono bg-purple-50 text-purple-700 border border-purple-200/80" title="Idioma: {{ $langBadge['label'] }}">
                                        <x-lucide-languages class="w-2.5 h-2.5 text-purple-400 shrink-0" />
                                        <span>{{ $langBadge['code'] }}</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold font-mono bg-stone-100 text-stone-700 border border-stone-200/80" title="Idioma: {{ $langBadge['label'] }}">
                                        <x-lucide-languages class="w-2.5 h-2.5 text-zinc-400 shrink-0" />
                                        <span>{{ $langBadge['code'] }}</span>
                                    </span>
                                @endif
                            </td>

                            {{-- Col 6: Canal Preferido --}}
                            <td class="py-2.5 px-3 whitespace-nowrap min-w-0">
                                @if($contact->preferred_channel === 'whatsapp')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-medium bg-emerald-50 text-emerald-700 border border-emerald-200/80">
                                        <x-lucide-message-circle class="w-2.5 h-2.5 text-emerald-600 shrink-0" />
                                        <span>WhatsApp</span>
                                    </span>
                                @elseif($contact->preferred_channel === 'call')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-medium bg-blue-50 text-blue-700 border border-blue-200/80">
                                        <x-lucide-phone class="w-2.5 h-2.5 text-blue-600 shrink-0" />
                                        <span>{{ __('Llamada') }}</span>
                                    </span>
                                @elseif($contact->preferred_channel === 'email')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-medium bg-amber-50 text-amber-700 border border-amber-200/80">
                                        <x-lucide-mail class="w-2.5 h-2.5 text-amber-600 shrink-0" />
                                        <span>Email</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-medium bg-stone-100 text-stone-600 border border-stone-200/80">
                                        <x-lucide-messages-square class="w-2.5 h-2.5 text-zinc-400 shrink-0" />
                                        <span>{{ __('Cualquiera') }}</span>
                                    </span>
                                @endif
                            </td>

                            {{-- Col 7: Órdenes Contadores --}}
                            <td class="py-2.5 px-3 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1.5 flex-wrap">
                                    @if($contact->active_orders_count > 0)
                                        <span class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200/70 text-[10px] font-bold" title="{{ __('Órdenes activas asignadas a este cliente') }}">
                                            {{ $contact->active_orders_count }} {{ __('activas') }}
                                        </span>
                                    @else
                                        <span class="text-zinc-400 text-[10px]">0 {{ __('activas') }}</span>
                                    @endif

                                    @if($contact->backlog_orders_count > 0)
                                        <span class="px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-200/70 text-[10px] font-semibold" title="{{ __('Órdenes en backlog') }}">
                                            {{ $contact->backlog_orders_count }} backlog
                                        </span>
                                    @endif
                                </div>
                            </td>

                            {{-- Col 8: Acciones --}}
                            <td class="py-2.5 pr-3 pl-1 text-right">
                                <div class="flex items-center justify-end">
                                    <button 
                                        type="button" 
                                        @click.stop
                                        wire:click="deleteContact({{ $contact->id }})" 
                                        wire:confirm="¿Seguro que deseas eliminar a {{ $contact->name }} del directorio?"
                                        class="p-1 rounded hover:bg-rose-50 text-zinc-300 hover:text-rose-600 transition cursor-pointer"
                                        title="{{ __('Eliminar cliente') }}"
                                    >
                                        <x-lucide-trash-2 class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center text-zinc-400">
                                <div class="flex flex-col items-center justify-center space-y-2">
                                    <x-lucide-users class="w-8 h-8 text-zinc-300" />
                                    <p class="text-xs font-medium">{{ __('No se encontraron clientes con los filtros seleccionados.') }}</p>
                                    @if(!empty($search) || !empty($channelFilter) || !empty($languageFilter) || !empty($clientFilter))
                                        <button 
                                            type="button" 
                                            wire:click="resetFilters" 
                                            class="text-xs text-emerald-600 hover:underline font-semibold cursor-pointer"
                                        >
                                            {{ __('Limpiar todos los filtros') }}
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
