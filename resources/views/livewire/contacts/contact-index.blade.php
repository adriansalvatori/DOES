<div class="h-full flex flex-col min-h-0">
    <div id="tour-contact-header">
        <x-ui.page-header :title="__('Directorio de Clientes')" :count="$totalContactsCount">
            <x-slot:actions>
                <button type="button" wire:click="openContactDetail" class="ui-btn ui-btn-primary">
                    <x-lucide-plus />
                    {{ __('Nuevo contacto') }}
                </button>
            </x-slot:actions>
            <x-slot:toolbar>
                <div class="relative w-72 max-w-full">
                    <x-lucide-search class="pointer-events-none absolute left-2.5 top-2 h-4 w-4 text-ink-3" />
                    <input type="text" wire:model.live.debounce.200ms="search" placeholder="{{ __('Buscar por nombre, teléfono, email, marca...') }}" class="ui-input pl-8">
                </div>
                <select wire:model.live="channelFilter" class="ui-select w-44" aria-label="{{ __('Canal preferido') }}">
                    <option value="">{{ __('Todos los canales') }}</option>
                    <option value="whatsapp">WhatsApp</option>
                    <option value="call">{{ __('Llamada') }}</option>
                    <option value="email">Email</option>
                    <option value="any">{{ __('Cualquiera') }}</option>
                </select>
                <select wire:model.live="languageFilter" class="ui-select w-44" aria-label="{{ __('Idioma') }}">
                    <option value="">{{ __('Todos los idiomas') }}</option>
                    <option value="es">ES - {{ __('Español') }}</option>
                    <option value="en">EN - {{ __('Inglés') }}</option>
                    <option value="both">ES / EN - {{ __('Bilingüe') }}</option>
                </select>
                <select wire:model.live="clientFilter" class="ui-select w-52" aria-label="{{ __('Compañía') }}">
                    <option value="">{{ __('Todas las compañías') }}</option>
                    @foreach($allClients as $cl)
                        <option value="{{ $cl->id }}">{{ $cl->name }}</option>
                    @endforeach
                </select>
                @if(!empty($search) || !empty($channelFilter) || !empty($languageFilter) || !empty($clientFilter))
                    <button type="button" wire:click="resetFilters" class="ui-btn ui-btn-ghost ui-btn-sm">{{ __('Limpiar todos los filtros') }}</button>
                @endif
            </x-slot:toolbar>
        </x-ui.page-header>
    </div>

    <div class="mt-4 flex-1 min-h-0 overflow-auto rounded-card border border-line bg-surface custom-vertical-scrollbar">
        <table class="ui-table min-w-[960px]">
            <thead>
                <tr>
                    <th>{{ __('Contacto') }}</th>
                    <th>{{ __('Compañías') }}</th>
                    <th>{{ __('Teléfono') }}</th>
                    <th>{{ __('Email') }}</th>
                    <th>{{ __('Idioma') }}</th>
                    <th>{{ __('Canal') }}</th>
                    <th class="is-num">{{ __('Activas') }}</th>
                    <th class="is-num">{{ __('Backlog') }}</th>
                    <th class="w-10"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($contacts as $contact)
                    @php
                        $langBadge = $contact->language_badge;
                        $whatsappUrl = $contact->whatsapp_url;
                        $channelLabel = match ($contact->preferred_channel) {
                            'whatsapp' => 'WhatsApp',
                            'call' => __('Llamada'),
                            'email' => 'Email',
                            default => __('Cualquiera'),
                        };
                        $contactClients = $contact->clients;
                    @endphp
                    <tr wire:key="contact-row-{{ $contact->id }}" wire:click="openContactDetail({{ $contact->id }})" class="cursor-pointer">
                        <td class="max-w-0 w-[20%]">
                            <div class="min-w-0">
                                <span class="block truncate font-medium text-ink">{{ $contact->name }}</span>
                                @if($contact->role_title)
                                    <span class="block truncate text-meta text-ink-3">{{ $contact->role_title }}</span>
                                @endif
                            </div>
                        </td>
                        <td class="max-w-0 w-[22%]">
                            @if($contactClients->isEmpty())
                                <span class="text-ink-3">—</span>
                            @else
                                <span class="block truncate text-ink-2" title="{{ $contactClients->pluck('name')->join(', ') }}">
                                    {{ $contactClients->first()->name }}@if($contactClients->count() > 1) <span class="text-ink-3">+{{ $contactClients->count() - 1 }}</span>@endif
                                </span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap">
                            @if($contact->phone)
                                <span class="text-ink-2">{{ $contact->phone }}</span>
                                @if($whatsappUrl)
                                    <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer" x-on:click.stop="" class="ml-1.5 text-meta text-ink-3 hover:text-ink underline-offset-2 hover:underline" title="{{ __('Abrir chat en WhatsApp') }}">WhatsApp</a>
                                @endif
                            @else
                                <span class="text-ink-3">—</span>
                            @endif
                        </td>
                        <td class="max-w-0 w-[18%]">
                            @if($contact->email)
                                <a href="mailto:{{ $contact->email }}" x-on:click.stop="" class="block truncate text-ink-2 hover:text-ink hover:underline underline-offset-2" title="{{ $contact->email }}">{{ $contact->email }}</a>
                            @else
                                <span class="text-ink-3">—</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap text-ink-2" title="{{ __($langBadge['label']) }}">{{ $langBadge['code'] }}</td>
                        <td class="whitespace-nowrap text-ink-2">{{ $channelLabel }}</td>
                        <td class="is-num {{ $contact->active_orders_count > 0 ? 'text-ink font-medium' : 'text-ink-3' }}">{{ $contact->active_orders_count }}</td>
                        <td class="is-num text-ink-3">{{ $contact->backlog_orders_count }}</td>
                        <td class="text-right" x-on:click.stop="">
                            <flux:dropdown position="bottom" align="end">
                                <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" aria-label="{{ __('Acciones') }}" />
                                <flux:menu>
                                    <flux:menu.item
                                        variant="danger"
                                        icon="trash"
                                        wire:click="deleteContact({{ $contact->id }})"
                                        wire:confirm="{{ __('¿Seguro que deseas eliminar a :name del directorio?', ['name' => $contact->name]) }}">{{ __('Eliminar contacto') }}</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="!h-auto py-10">
                            <x-ui.empty icon="users" :title="__('No se encontraron clientes con los filtros seleccionados.')" />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
