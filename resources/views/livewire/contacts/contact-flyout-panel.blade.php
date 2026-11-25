<div 
    x-data="{
        isOpen: @js((bool) $isOpen),
        init() {
            this.$watch('$wire.isOpen', (val) => {
                this.isOpen = Boolean(val);
            });
        },
        openImmediate() {
            this.isOpen = true;
        },
        async confirmSave() {
            const changes = await $wire.pendingChanges();
            for (const change of changes) {
                if (! await KudosConfirmChange(change)) { return; }
            }
            $wire.save();
        },
        closeModal() {
            this.isOpen = false;
            $wire.close();
        }
    }"
    x-show="isOpen"
    x-cloak
    @open-contact-flyout.window="openImmediate()"
    @keydown.escape.window="closeModal()"
    class="fixed inset-0 flex justify-end"
    style="z-index: 300;"
>
    {{-- Backdrop --}}
    <div @click="closeModal()" class="fixed inset-0 bg-stone-900/30"></div>

    {{-- Slide-over Panel --}}
    <div class="fixed inset-y-0 right-0 w-full max-w-2xl bg-canvas border-l border-line shadow-panel flex flex-col overflow-hidden">
        @if($isOpen)
            {{-- Header --}}
            <div class="px-5 py-4 bg-surface border-b border-line flex items-center justify-between gap-3 shrink-0">
                <div class="flex items-center gap-3 min-w-0">
                    <x-ui.person :name="$name !== '' ? $name : '?'" color="#1c1917" size="lg" />
                    <div class="min-w-0">
                        <p class="text-meta text-ink-3">
                            {{ $contactId ? __('Ficha de contacto') : __('Nuevo contacto') }}
                        </p>
                        <h2 class="text-section font-semibold text-ink truncate">
                            {{ !empty($name) ? $name : __('Sin nombre') }}
                        </h2>
                    </div>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <button type="button" @click="confirmSave()" class="ui-btn ui-btn-primary">
                        <x-lucide-check />
                        {{ __('Guardar') }}
                    </button>
                    <button type="button" wire:click="close" class="ui-btn ui-btn-ghost ui-btn-icon" title="{{ __('Cerrar') }}" aria-label="{{ __('Cerrar') }}">
                        <x-lucide-x />
                    </button>
                </div>
            </div>

            {{-- Tabs --}}
            <div class="bg-surface border-b border-line px-5 flex items-center gap-5 shrink-0 overflow-x-auto">
                @php
                    $tabClass = fn (string $tab) => 'py-2.5 -mb-px border-b-2 text-body font-medium transition cursor-pointer flex items-center gap-1.5 whitespace-nowrap '
                        . ($activeTab === $tab ? 'border-ink text-ink' : 'border-transparent text-ink-3 hover:text-ink');
                @endphp
                <button type="button" wire:click="$set('activeTab', 'general')" class="{{ $tabClass('general') }}">{{ __('General') }}</button>

                @if($contactId)
                    <button type="button" wire:click="$set('activeTab', 'orders')" class="{{ $tabClass('orders') }}">
                        {{ __('Órdenes asignadas') }}
                        @if($assignedOrders->count() > 0)
                            <span class="rounded-full bg-sunken px-1.5 text-meta tabular-nums text-ink-2">{{ $assignedOrders->count() }}</span>
                        @endif
                    </button>

                    <button type="button" wire:click="$set('activeTab', 'merge')" class="{{ $tabClass('merge') }}">
                        {{ __('Fusionar / duplicados') }}
                        @if(count($mergeSuggestions) > 0)
                            <span class="rounded-full bg-warn-soft px-1.5 text-meta tabular-nums text-warn">{{ count($mergeSuggestions) }}</span>
                        @endif
                    </button>
                @endif
            </div>

            {{-- Body Content --}}
            <div class="flex-1 min-h-0 overflow-y-auto p-5 custom-vertical-scrollbar space-y-4">

                {{-- TAB: GENERAL --}}
                @if($activeTab === 'general')
                    <div class="ui-card p-4 space-y-4">
                        <h3 class="ui-section-title">{{ __('Datos personales y contacto') }}</h3>

                        <div class="space-y-3">
                            <div>
                                <label class="ui-label">{{ __('Nombre completo') }} <span class="text-danger">*</span></label>
                                <input type="text" wire:model.defer="name" placeholder="{{ __('Ej: MARCELA DUQUE') }}" class="ui-input font-medium" />
                                @error('name') <span class="text-danger text-meta mt-0.5 block">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="ui-label">{{ __('Cargo / rol / departamento (opcional)') }}</label>
                                <input type="text" wire:model.defer="role_title" placeholder="{{ __('Ej: Gerente de Compras, Project Manager, Owner') }}" class="ui-input" />
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="ui-label">{{ __('Teléfono') }}</label>
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
                                        class="ui-input"
                                    />
                                    @error('phone') <span class="text-danger text-meta mt-0.5 block">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="ui-label">{{ __('Correo electrónico') }}</label>
                                    <input type="email" wire:model.defer="email" placeholder="{{ __('ejemplo@empresa.com') }}" class="ui-input" />
                                    @error('email') <span class="text-danger text-meta mt-0.5 block">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="ui-card p-4 space-y-4">
                        <h3 class="ui-section-title">{{ __('Preferencias de comunicación') }}</h3>

                        <div>
                            <label class="ui-label">{{ __('Canal preferido') }}</label>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach(['whatsapp' => 'WhatsApp', 'call' => __('Llamada'), 'email' => 'Email', 'any' => __('Cualquiera')] as $channelKey => $channelLabel)
                                    <button type="button" wire:click="$set('preferred_channel', '{{ $channelKey }}')" class="ui-chip" aria-pressed="{{ $preferred_channel === $channelKey ? 'true' : 'false' }}">{{ $channelLabel }}</button>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <label class="ui-label">{{ __('Idioma de contacto') }}</label>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach(['es' => __('Español'), 'en' => __('Inglés'), 'both' => __('Bilingüe')] as $langKey => $langLabel)
                                    <button type="button" wire:click="$set('preferred_language', '{{ $langKey }}')" class="ui-chip" aria-pressed="{{ $preferred_language === $langKey ? 'true' : 'false' }}">{{ $langLabel }}</button>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- Linked companies --}}
                    <div class="ui-card p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <h3 class="ui-section-title">{{ __('Compañías vinculadas') }}</h3>
                            <span class="text-meta text-ink-3">{{ trans_choice('{0} Ninguna vinculada|{1} :count vinculada|[2,*] :count vinculadas', count($linkedClientIds), ['count' => count($linkedClientIds)]) }}</span>
                        </div>
                        <p class="text-meta text-ink-3">{{ __('Este contacto podrá atender órdenes de cualquiera de las compañías vinculadas a continuación:') }}</p>

                        <div class="flex flex-wrap gap-1.5 min-h-10 p-2 bg-sunken/60 border border-line rounded-control">
                            @php
                                $linkedClients = \App\Models\Client::whereIn('id', $linkedClientIds)->orderBy('name')->get();
                            @endphp
                            @forelse($linkedClients as $lClient)
                                @php
                                    $isPrimary = $currentContact ? (bool) $currentContact->clients->firstWhere('id', $lClient->id)?->pivot?->is_primary : false;
                                    $currentMainName = $isPrimary || ! $currentContact
                                        ? ''
                                        : (string) $lClient->contacts()->wherePivot('is_primary', true)->value('contacts.name');
                                @endphp
                                <span class="inline-flex items-center gap-1.5 h-7 pl-2.5 pr-1.5 rounded-full border border-line bg-surface text-meta font-medium text-ink">
                                    <span>{{ $lClient->name }}</span>

                                    @if($isPrimary)
                                        <x-ui.tag icon="star" title="{{ __('Contacto principal de esta compañía') }}">{{ __('Principal') }}</x-ui.tag>
                                    @elseif($currentContact)
                                        <button
                                            type="button"
                                            x-on:click="KudosConfirmChange({ field: @js(__('Contacto principal de :company', ['company' => $lClient->name])), from: @js($currentMainName), to: @js($currentContact->name) }).then(ok => ok && $wire.setAsPrimaryClient({{ $lClient->id }}))"
                                            class="text-ink-3 hover:text-ink hover:underline cursor-pointer"
                                            title="{{ __('Establecer como contacto principal de esta compañía') }}"
                                        >{{ __('Hacer principal') }}</button>
                                    @endif

                                    <button type="button" wire:click="toggleClientLink({{ $lClient->id }})" class="text-ink-3 hover:text-danger transition cursor-pointer rounded-full p-0.5" title="{{ __('Desvincular compañía') }}" aria-label="{{ __('Desvincular compañía') }}">
                                        <x-lucide-x class="w-3.5 h-3.5" />
                                    </button>
                                </span>
                            @empty
                                <span class="text-body text-ink-3 py-0.5">{{ __('Sin compañías vinculadas. Busca y agrega una abajo:') }}</span>
                            @endforelse
                        </div>

                        <div class="pt-1">
                            <div class="relative mb-2">
                                <x-lucide-search class="pointer-events-none absolute left-2.5 top-2 h-4 w-4 text-ink-3" />
                                <input type="text" wire:model.live.debounce.150ms="clientSearch" placeholder="{{ __('Buscar compañía para vincular...') }}" class="ui-input pl-8" />
                            </div>

                            <div class="max-h-40 overflow-y-auto custom-vertical-scrollbar border border-line rounded-control p-1 bg-surface">
                                @foreach($allClients as $clientOpt)
                                    @php $isLinked = in_array($clientOpt->id, $linkedClientIds, true); @endphp
                                    <button
                                        type="button"
                                        wire:click="toggleClientLink({{ $clientOpt->id }})"
                                        class="w-full text-left px-2.5 py-1.5 rounded-control text-body flex items-center justify-between transition cursor-pointer hover:bg-sunken {{ $isLinked ? 'text-ink font-medium' : 'text-ink-2' }}"
                                    >
                                        <span class="truncate">{{ $clientOpt->name }}</span>
                                        @if($isLinked)
                                            <span class="text-meta text-ink-3 flex items-center gap-1"><x-lucide-check class="w-3.5 h-3.5" />{{ __('Vinculada') }}</span>
                                        @else
                                            <span class="text-meta text-ink-3">{{ __('Vincular') }}</span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="ui-card p-4 space-y-2">
                        <h3 class="ui-section-title">{{ __('Notas de atención') }}</h3>
                        <textarea wire:model.defer="notes" rows="3" placeholder="{{ __('Ej: Solo contactar por las mañanas. Muy exigente con los pantones. etc.') }}" class="ui-input"></textarea>
                    </div>
                @endif

                {{-- TAB: ASSIGNED ORDERS --}}
                @if($activeTab === 'orders' && $currentContact)
                    <div class="space-y-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h3 class="ui-section-title">{{ __('Órdenes de :name', ['name' => $currentContact->name]) }}</h3>
                                <p class="text-meta text-ink-3">{{ __('Solo las órdenes donde este contacto es el responsable asignado:') }}</p>
                            </div>
                            <span class="text-meta text-ink-3 whitespace-nowrap">{{ trans_choice('{1} :count activa|[0,*] :count activas', $assignedOrders->count(), ['count' => $assignedOrders->count()]) }}</span>
                        </div>

                        <div class="rounded-card border border-line bg-surface overflow-hidden">
                            <div class="max-h-96 overflow-y-auto custom-vertical-scrollbar">
                                <table class="ui-table">
                                    <thead>
                                        <tr>
                                            <th>{{ __('WO') }}</th>
                                            <th>{{ __('Tarea / Compañía') }}</th>
                                            <th>{{ __('Diseñador') }}</th>
                                            <th>{{ __('Estado') }}</th>
                                            <th class="is-num">{{ __('Entrega') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($assignedOrders as $ord)
                                            <tr class="cursor-pointer" wire:click="$dispatch('open-order-detail', { orderId: {{ $ord->id }} })">
                                                <td class="whitespace-nowrap">
                                                    @if($ord->wo_number)<x-wo-badge :number="$ord->wo_number" />@else<span class="text-ink-3">—</span>@endif
                                                </td>
                                                <td class="max-w-0 w-[40%]">
                                                    <span class="block truncate font-medium text-ink">{{ $ord->clean_task_name ?: $ord->task_name }}</span>
                                                    <span class="flex items-center gap-1.5 min-w-0 text-meta text-ink-3">
                                                        <span class="truncate">{{ $ord->company_name ?: ($ord->client?->name ?? '-') }}</span>
                                                        @if($ord->contact_id !== $currentContact->id && $ord->responsible_person !== $currentContact->name)
                                                            <span class="shrink-0">· {{ __('Vía contacto principal') }}</span>
                                                        @endif
                                                    </span>
                                                </td>
                                                <td class="truncate text-ink-2">{{ $ord->designer?->name ?: '—' }}</td>
                                                <td class="text-ink-2 whitespace-nowrap">{{ $ord->core_status?->label() }}</td>
                                                <td class="is-num whitespace-nowrap text-ink-2">{{ $ord->current_due_date ? $ord->current_due_date->format('d/m/Y') : '—' }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="!h-auto py-8 text-center text-ink-3">{{ __('No tiene órdenes activas en este momento.') }}</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="ui-card p-3">
                                <span class="text-meta text-ink-3 block">{{ __('Órdenes en backlog') }}</span>
                                <span class="text-title font-semibold text-ink tabular-nums">{{ $backlogOrders->count() }}</span>
                            </div>
                            <div class="ui-card p-3">
                                <span class="text-meta text-ink-3 block">{{ __('Órdenes archivadas') }}</span>
                                <span class="text-title font-semibold text-ink tabular-nums">{{ $archivedOrders->count() }}</span>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- TAB: MERGE / DUPLICATES --}}
                @if($activeTab === 'merge' && $currentContact)
                    <div class="space-y-4">
                        <div>
                            <h3 class="ui-section-title">{{ __('Herramienta de fusión de contactos') }}</h3>
                            <p class="text-meta text-ink-3">{{ __('Si este contacto está duplicado, puedes fusionarlo con otro. Se transferirán todas sus órdenes y compañías vinculadas:') }}</p>
                        </div>

                        @if(count($mergeSuggestions) > 0)
                            <div class="ui-card p-4 space-y-3">
                                <h4 class="text-body font-medium text-ink">{{ __('Posibles duplicados detectados') }}</h4>
                                <div class="space-y-2">
                                    @foreach($mergeSuggestions as $sugg)
                                        @php $candidate = $sugg['contact']; @endphp
                                        <div class="flex items-center justify-between p-2.5 rounded-control bg-sunken/60 text-body gap-3">
                                            <div class="min-w-0">
                                                <span class="font-medium text-ink">{{ $candidate->name }}</span>
                                                <div class="flex items-center gap-2 text-meta text-ink-3">
                                                    @if($candidate->phone) <span>{{ $candidate->phone }}</span> @endif
                                                    @if($candidate->email) <span class="truncate">{{ $candidate->email }}</span> @endif
                                                </div>
                                            </div>
                                            <button
                                                type="button"
                                                x-on:click="KudosConfirmChange({ field: @js(__('Fusionar contactos')), from: @js($currentContact->name), to: @js($candidate->name), note: @js(__('Se transferirán todas sus órdenes y compañías vinculadas. Esta acción es irreversible.')), confirmText: @js(__('Fusionar')) }).then(ok => ok && $wire.executeMerge({{ $candidate->id }}))"
                                                class="ui-btn ui-btn-secondary ui-btn-sm"
                                            >{{ __('Fusionar aquí') }}</button>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div class="ui-card p-4 space-y-3">
                            <h4 class="text-body font-medium text-ink">{{ __('Fusionar manualmente con otro contacto') }}</h4>
                            <p class="text-meta text-ink-3">{{ __('Selecciona el contacto destino que conservará toda la información:') }}</p>

                            <div class="space-y-2">
                                <select wire:model="selectedMergeTargetId" class="ui-select">
                                    <option value="">{{ __('-- Seleccionar contacto destino --') }}</option>
                                    @foreach($allOtherContacts as $otherC)
                                        <option value="{{ $otherC->id }}">{{ $otherC->name }} ({{ $otherC->phone ?: __('Sin teléfono') }})</option>
                                    @endforeach
                                </select>

                                @if($selectedMergeTargetId)
                                    @php $mergeTarget = $allOtherContacts->firstWhere('id', $selectedMergeTargetId); @endphp
                                    <button
                                        type="button"
                                        x-on:click="KudosConfirmChange({ field: @js(__('Fusionar contactos')), from: @js($currentContact->name), to: @js($mergeTarget?->name ?? ''), note: @js(__('Todas las órdenes y compañías se transferirán.')), confirmText: @js(__('Fusionar')) }).then(ok => ok && $wire.executeMerge({{ $selectedMergeTargetId }}))"
                                        class="ui-btn ui-btn-primary w-full"
                                    >{{ __('Confirmar fusión') }}</button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @else
            @include('livewire.contacts.partials.contact-flyout-skeleton')
        @endif
    </div>
</div>
