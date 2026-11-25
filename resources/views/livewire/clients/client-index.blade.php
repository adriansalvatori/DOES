<div class="h-full flex flex-col min-h-0">
    <div id="tour-client-header">
        <x-ui.page-header :title="__('Compañías')" :count="$totalClientsCount">
            <x-slot:actions>
                <button id="tour-client-new-btn" type="button" wire:click="openClientDetail" class="ui-btn ui-btn-primary">
                    <x-lucide-plus />
                    {{ __('Nueva compañía') }}
                </button>
            </x-slot:actions>
            <x-slot:toolbar>
                <div class="relative w-72 max-w-full">
                    <x-lucide-search class="pointer-events-none absolute left-2.5 top-2 h-4 w-4 text-ink-3" />
                    <input id="tour-client-search" type="text" wire:model.live.debounce.200ms="search" placeholder="{{ __('Buscar compañía...') }}" class="ui-input pl-8">
                </div>
            </x-slot:toolbar>
        </x-ui.page-header>
    </div>

    <div class="mt-4 flex-1 min-h-0 overflow-auto rounded-card border border-line bg-surface custom-vertical-scrollbar">
        <table class="ui-table">
            <thead>
                <tr>
                    <th>{{ __('Compañía') }}</th>
                    <th>{{ __('Contacto principal') }}</th>
                    <th>{{ __('Teléfono o email') }}</th>
                    <th class="is-num">{{ __('Activas') }}</th>
                    <th class="is-num">{{ __('Archivadas') }}</th>
                    <th class="is-num">{{ __('Backlog') }}</th>
                    <th class="w-10"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($clients as $client)
                    @php
                        $primaryContact = ($client->primaryContact instanceof \Illuminate\Support\Collection ? $client->primaryContact->first() : $client->primaryContact) ?: $client->contacts->first();
                        $locations = $client->locations;
                        $mainLocation = $locations->first();
                        $mainLocationText = trim($mainLocation?->name ?: ($mainLocation?->address ?? ''));
                        $mainAddress = trim($mainLocation?->address ?? '');
                        $reach = $primaryContact?->phone ?: $primaryContact?->email;
                    @endphp
                    <tr wire:key="client-row-{{ $client->id }}" wire:click="openClientDetail({{ $client->id }})" class="cursor-pointer">
                        <td class="max-w-0 w-[34%]">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="truncate font-medium text-ink">{{ $client->name }}</span>
                                @if($mainLocationText !== '')
                                    <x-orders.location :location="$mainLocationText" class="min-w-0 shrink" />
                                    @if($locations->count() > 1)
                                        <span class="shrink-0 text-meta text-ink-3" title="{{ trans_choice('{1} :count ubicación|[2,*] :count ubicaciones', $locations->count(), ['count' => $locations->count()]) }}">+{{ $locations->count() - 1 }}</span>
                                    @endif
                                @endif
                            </div>
                        </td>
                        <td class="max-w-0 w-[22%]">
                            @if($primaryContact?->name)
                                <span class="block truncate text-ink-2">{{ $primaryContact->name }}</span>
                            @else
                                <span class="text-ink-3">—</span>
                            @endif
                        </td>
                        <td class="max-w-0 w-[22%]">
                            @if($reach)
                                <span class="block truncate text-ink-2">{{ $reach }}</span>
                            @else
                                <span class="text-ink-3">—</span>
                            @endif
                        </td>
                        <td class="is-num {{ $client->active_orders_count > 0 ? 'text-ink font-medium' : 'text-ink-3' }}">{{ $client->active_orders_count }}</td>
                        <td class="is-num text-ink-3">{{ $client->archived_orders_count }}</td>
                        <td class="is-num text-ink-3">{{ $client->backlog_orders_count }}</td>
                        <td class="text-right" x-on:click.stop="">
                            <div
                                x-data="{
                                    copy(text) {
                                        const done = () => $dispatch('toast', { message: @js(__('Dirección copiada')) });
                                        if (navigator.clipboard && window.isSecureContext) {
                                            navigator.clipboard.writeText(text).then(done).catch(() => this.fallback(text, done));
                                        } else { this.fallback(text, done); }
                                    },
                                    fallback(text, done) {
                                        const ta = document.createElement('textarea');
                                        ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
                                        document.body.appendChild(ta); ta.focus(); ta.select();
                                        try { document.execCommand('copy'); } catch (e) {}
                                        document.body.removeChild(ta); done();
                                    }
                                }"
                            >
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" aria-label="{{ __('Acciones') }}" />
                                    <flux:menu>
                                        @if($mainAddress !== '')
                                            <flux:menu.item icon="document-duplicate" x-on:click="copy({{ \Illuminate\Support\Js::from($mainAddress) }})">{{ __('Copiar dirección') }}</flux:menu.item>
                                            <flux:menu.separator />
                                        @endif
                                        <flux:menu.item
                                            variant="danger"
                                            icon="trash"
                                            wire:click="deleteClient({{ $client->id }})"
                                            wire:confirm="{{ __('¿Eliminar la compañía :name? Se moverá a la papelera.', ['name' => $client->name]) }}">{{ __('Eliminar compañía') }}</flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="!h-auto py-10">
                            <x-ui.empty icon="building-2" :title="__('No hay compañías')" :description="filled($search) ? __('Ninguna compañía coincide con la búsqueda.') : null" />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
