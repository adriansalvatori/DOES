<div 
    x-data="{
        isOpen: @js((bool) $isOpen),
        initialSnapshot: null,
        init() {
            if (this.isOpen) {
                this.snapshot();
                window.KudosDirtyGuard.register('client-flyout-panel', () => this.isDirty());
                window.KudosModalStack?.register('client-flyout-panel', this.$el, () => this.confirmClose(() => $wire.close()));
            }
            this.$watch('$wire.isOpen', (open) => {
                this.isOpen = Boolean(open);
                if (open) {
                    this.snapshot();
                    window.KudosDirtyGuard.register('client-flyout-panel', () => this.isDirty());
                    window.KudosModalStack?.register('client-flyout-panel', this.$el, () => this.confirmClose(() => $wire.close()));
                    window.KudosModalStack?.bringToFront('client-flyout-panel');
                } else {
                    window.KudosDirtyGuard.unregister('client-flyout-panel');
                    window.KudosModalStack?.unregister('client-flyout-panel');
                }
            });
            this.$watch('$wire.clientId', () => {
                if (this.isOpen) {
                    window.KudosModalStack?.bringToFront('client-flyout-panel');
                }
            });
            this.$cleanup(() => {
                window.KudosDirtyGuard.unregister('client-flyout-panel');
                window.KudosModalStack?.unregister('client-flyout-panel');
            });
        },
        openImmediate() {
            this.isOpen = true;
            window.KudosModalStack?.bringToFront('client-flyout-panel');
        },
        snapshotData() {
            return {
                name: ($wire.name || '').trim(),
                website: ($wire.website || '').trim(),
                aliases: ($wire.aliases || []).map(a => (a || '').trim()),
                phones: ($wire.phones || []).map(p => ({
                    label: (p.label || '').trim(),
                    phone: (p.phone || '').trim()
                })),
                notes: ($wire.notes || '').trim(),
                locations: ($wire.locations || []).map(l => ({
                    id: l.id || null,
                    name: (l.name || '').trim(),
                    manager_name: (l.manager_name || '').trim(),
                    address: (l.address || '').trim(),
                    phone: (l.phone || '').trim(),
                    email: (l.email || '').trim(),
                    notes: (l.notes || '').trim()
                })),
                contacts: ($wire.contacts || []).map(c => ({
                    id: c.id || null,
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
                })),
                brandKit: JSON.stringify($wire.brandKit || {})
            };
        },
        snapshot() {
            this.initialSnapshot = JSON.stringify(this.snapshotData());
        },
        isDirty() {
            if (!$wire.isOpen || !this.initialSnapshot) return false;
            return JSON.stringify(this.snapshotData()) !== this.initialSnapshot;
        },
        recordChanges() {
            const before = JSON.parse(this.initialSnapshot || '{}');
            const now = this.snapshotData();
            const out = [];
            const diff = (field, from, to) => { if ((from || '') !== (to || '')) out.push({ field, from: from || '', to: to || '' }); };
            diff(@js(__('Nombre de la compañía')), before.name, now.name);
            diff(@js(__('Sitio web')), before.website, now.website);
            const phoneText = (list) => (list || []).filter(p => p.phone).map(p => (p.label ? p.label + ': ' : '') + p.phone).join(', ');
            diff(@js(__('Teléfonos de la compañía')), phoneText(before.phones), phoneText(now.phones));
            const byId = (list) => Object.fromEntries((list || []).filter(i => i.id).map(i => [i.id, i]));
            const locBefore = byId(before.locations);
            (now.locations || []).forEach((l) => {
                const label = l.name || @js(__('Locación'));
                const old = l.id ? locBefore[l.id] : null;
                if (! old) {
                    if (l.name || l.address) diff(@js(__('Nueva locación')), '', [l.name, l.address].filter(Boolean).join(' · '));
                    return;
                }
                diff(@js(__('Nombre de la locación')), old.name, l.name);
                diff(@js(__('Dirección')) + ' (' + label + ')', old.address, l.address);
                diff(@js(__('Teléfono')) + ' (' + label + ')', old.phone, l.phone);
                diff(@js(__('Correo')) + ' (' + label + ')', old.email, l.email);
                diff(@js(__('Responsable')) + ' (' + label + ')', old.manager_name, l.manager_name);
            });
            const conBefore = byId(before.contacts);
            (now.contacts || []).forEach((c) => {
                const old = c.id ? conBefore[c.id] : null;
                if (! old) {
                    if (c.name) diff(@js(__('Nuevo contacto')), '', [c.name, c.phone, c.email].filter(Boolean).join(' · '));
                    return;
                }
                const who = c.name || old.name;
                diff(@js(__('Nombre del contacto')), old.name, c.name);
                diff(@js(__('Teléfono')) + ' (' + who + ')', old.phone, c.phone);
                diff(@js(__('Correo')) + ' (' + who + ')', old.email, c.email);
            });
            return out;
        },
        async confirmSave() {
            if (this.initialSnapshot && $wire.clientId) {
                for (const change of this.recordChanges()) {
                    if (! await KudosConfirmChange(change)) { return false; }
                }
            }
            $wire.save();
            return true;
        },
        confirmClose(action) {
            if (window.KudosDirtyGuard && window.KudosDirtyGuard.isConfirmModalOpen) {
                return;
            }
            if (this.isDirty()) {
                window.KudosDirtyGuard.openConfirmModal({
                    title: @js(__('¿Guardar cambios de la compañía?')),
                    description: @js(__('Tienes modificaciones en la información de la compañía que no han sido guardadas.')),
                    cancelText: @js(__('Cancelar')),
                    discardText: @js(__('No guardar')),
                    saveText: @js(__('Guardar')),
                    onCancel: () => {},
                    onDiscard: () => {
                        this.isOpen = false;
                        window.KudosDirtyGuard.unregister('client-flyout-panel');
                        window.KudosModalStack?.unregister('client-flyout-panel');
                        action();
                    },
                    onSave: () => {
                        this.isOpen = false;
                        window.KudosDirtyGuard.unregister('client-flyout-panel');
                        window.KudosModalStack?.unregister('client-flyout-panel');
                        this.confirmSave();
                    }
                });
            } else {
                this.isOpen = false;
                window.KudosDirtyGuard.unregister('client-flyout-panel');
                window.KudosModalStack?.unregister('client-flyout-panel');
                action();
            }
        }
    }"
    x-show="isOpen"
    x-cloak
    @open-client-flyout.window="openImmediate()"
    @keydown.window.escape.stop="if (window.KudosModalStack ? window.KudosModalStack.isTop('client-flyout-panel') : (isOpen && !window.KudosDirtyGuard.isConfirmModalOpen)) confirmClose(() => $wire.close())"
    id="client-flyout-container" 
    class="fixed inset-0 flex justify-end" 
    style="z-index: 300;"
    @pointerdown="window.KudosModalStack?.bringToFront('client-flyout-panel')"
>
    {{-- Backdrop --}}
    <div 
        class="fixed inset-0 bg-stone-900/30"
        @click="confirmClose(() => $wire.close())"
    ></div>

    {{-- Slide-over Flyout Panel (Proportioned max-w-2xl ~ 672px width) --}}
    <div id="client-flyout-panel" class="fixed inset-y-0 right-0 w-full max-w-2xl bg-surface shadow-panel flex flex-col border-l border-line">
        @if($isOpen)
            
            {{-- Panel Header --}}
            <div class="px-6 py-4 border-b border-line bg-surface flex items-center justify-between gap-3 shrink-0">
                <div class="min-w-0">
                    <h2 class="text-section font-semibold text-ink truncate">
                        {{ $clientId ? ($name ?: __('Detalle de la compañía')) : __('Nueva compañía') }}
                    </h2>
                    @php
                        $primaryContact = ($currentClient?->primaryContact instanceof \Illuminate\Support\Collection ? $currentClient?->primaryContact->first() : $currentClient?->primaryContact) ?: $currentClient?->contacts->first();
                    @endphp
                    @if($currentClient)
                        <div class="flex items-center gap-3 text-meta text-ink-3 mt-0.5 flex-wrap">
                            @if($primaryContact && $primaryContact->name)
                                <span class="font-medium text-ink-2">{{ $primaryContact->name }}</span>
                            @endif
                            @if($primaryContact && $primaryContact->phone)
                                <a href="tel:{{ $primaryContact->phone }}" class="hover:text-ink transition">{{ $primaryContact->phone }}</a>
                            @endif
                            @if($primaryContact && $primaryContact->email)
                                <a href="mailto:{{ $primaryContact->email }}" class="hover:text-ink transition truncate">{{ $primaryContact->email }}</a>
                            @endif
                            @if($website)
                                <a href="{{ Str::startsWith($website, ['http://', 'https://']) ? $website : 'https://'.$website }}" target="_blank" class="hover:text-ink hover:underline transition">{{ preg_replace('/^https?:\/\//i', '', $website) }}</a>
                            @endif
                        </div>
                    @else
                        <p class="text-meta text-ink-3">{{ __('Complete la información para registrar la nueva compañía.') }}</p>
                    @endif
                </div>
                <button type="button" @click="confirmClose(() => $wire.close())" class="ui-btn ui-btn-ghost ui-btn-icon shrink-0" title="{{ __('Cerrar') }}" aria-label="{{ __('Cerrar') }}">
                    <x-lucide-x />
                </button>
            </div>

            {{-- Merge Suggestions Banner --}}
            @if(!empty($mergeSuggestions))
                <div class="mx-6 mt-4 p-3 bg-warn-soft rounded-card space-y-2 text-body shrink-0">
                    <p class="font-medium text-warn">{{ __('Sugerencia de fusión detectada') }}</p>
                    <p class="text-meta text-ink-2">{{ __('Se encontraron registros o clientes con nombres similares que pueden unificarse:') }}</p>
                    <div class="space-y-1.5 pt-1">
                        @foreach($mergeSuggestions as $suggestion)
                            <div class="flex items-center justify-between gap-2 p-2 rounded-control bg-surface border border-line">
                                <div class="flex items-center gap-1.5 min-w-0">
                                    <span class="font-medium text-ink truncate">{{ $suggestion['name'] }}</span>
                                    <span class="text-meta text-ink-3 shrink-0">{{ trans_choice('{1} :count orden|[0,*] :count órdenes', $suggestion['orders_count'], ['count' => $suggestion['orders_count']]) }}</span>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <button
                                        type="button"
                                        x-on:click="KudosConfirmChange({ field: @js(__('Fusionar compañías')), from: @js($suggestion['name']), to: @js($name), note: @js(__('Se transferirán sus órdenes, contactos y locaciones. Esta acción es irreversible.')), confirmText: @js(__('Fusionar')) }).then(ok => ok && $wire.mergeClient({{ $suggestion['id'] }}))"
                                        class="ui-btn ui-btn-secondary ui-btn-sm"
                                    >{{ __('Fusionar aquí') }}</button>
                                    <button type="button" wire:click="dismissMerge({{ $suggestion['id'] }})" class="ui-btn ui-btn-ghost ui-btn-sm">{{ __('Descartar') }}</button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Tabs Header --}}
            @php
                $flyTab = fn (bool $active) => 'py-2.5 -mb-px border-b-2 text-body font-medium transition cursor-pointer flex items-center gap-1.5 whitespace-nowrap ' . ($active ? 'border-ink text-ink' : 'border-transparent text-ink-3 hover:text-ink');
            @endphp
            <div class="flex border-b border-line bg-surface px-6 gap-5 shrink-0 overflow-x-auto">
                <button type="button" wire:click="$set('activeTab', 'general')" class="{{ $flyTab($activeTab === 'general') }}">{{ __('Información') }}</button>
                <button type="button" wire:click="$set('activeTab', 'brand_kit')" class="{{ $flyTab($activeTab === 'brand_kit' || $activeTab === 'links') }}">{{ __('Brand Kit') }}</button>
                @if($currentClient)
                    <button type="button" wire:click="$set('activeTab', 'projects')" class="{{ $flyTab($activeTab === 'projects') }}">{{ __('Proyectos') }} <span class="text-ink-3 tabular-nums">{{ $currentClient->activeOrders->count() }}</span></button>
                    <button type="button" wire:click="$set('activeTab', 'archived')" class="{{ $flyTab($activeTab === 'archived') }}">{{ __('Archivados') }} <span class="text-ink-3 tabular-nums">{{ $currentClient->archivedOrders->count() }}</span></button>
                    <button type="button" wire:click="$set('activeTab', 'backlog')" class="{{ $flyTab($activeTab === 'backlog') }}">{{ __('Backlog') }} <span class="text-ink-3 tabular-nums">{{ $currentClient->backlogOrders->count() }}</span></button>
                @endif
            </div>

            {{-- Panel Body --}}
            <div class="flex-1 overflow-y-auto p-6 custom-vertical-scrollbar">
                {{-- TAB 1: Client Info (General, Locaciones & Contactos) --}}
                @if($activeTab === 'general')
                    <div class="space-y-6">
                        {{-- Section 1: Información Principal del Cliente --}}
                        <div class="space-y-3.5">
                            {{-- 1. Company (client) name --}}
                            <div>
                                <input 
                                    type="text" 
                                    wire:model="name"
                                    x-on:blur="$event.target.value = $event.target.value.toUpperCase()"
                                    placeholder="{{ __('Nombre de la compañía') }}"
                                    class="w-full bg-transparent border border-transparent hover:bg-zinc-100/60 hover:border-zinc-200/60 focus:bg-white focus:border-zinc-300 focus:ring-2 focus:ring-zinc-900/5 rounded-md -mx-1 px-1 py-1 text-sm text-zinc-900 font-semibold focus:outline-none transition"
                                />
                                @error('name') <span class="text-meta text-danger mt-1 block font-medium">{{ $message }}</span> @enderror
                            </div>

                            {{-- Main Address Display Box (Read-only reflection with soft gray background) --}}
                            @php
                                $mainAddress = trim($locations[0]['address'] ?? '');
                            @endphp
                            <div 
                                x-data="{ 
                                    copied: false,
                                    copyText() {
                                        const text = {{ json_encode($mainAddress) }} || $wire.locations?.[0]?.address || '';
                                        if (!text) return;
                                        if (navigator.clipboard && window.isSecureContext) {
                                            navigator.clipboard.writeText(text).then(() => {
                                                this.copied = true;
                                                setTimeout(() => this.copied = false, 2000);
                                            }).catch(() => this.fallbackCopy(text));
                                        } else {
                                            this.fallbackCopy(text);
                                        }
                                    },
                                    fallbackCopy(text) {
                                        const ta = document.createElement('textarea');
                                        ta.value = text;
                                        ta.style.position = 'fixed';
                                        ta.style.opacity = '0';
                                        document.body.appendChild(ta);
                                        ta.focus();
                                        ta.select();
                                        try { document.execCommand('copy'); } catch (e) {}
                                        document.body.removeChild(ta);
                                        this.copied = true;
                                        setTimeout(() => this.copied = false, 2000);
                                    }
                                }"
                                class="p-2.5 px-3 bg-zinc-100/80 border border-zinc-200/70 rounded-lg flex items-center justify-between gap-3 text-xs select-none"
                            >
                                <div class="flex items-center gap-2 min-w-0 flex-1">
                                    <x-lucide-map-pin class="w-4 h-4 text-pin shrink-0" />
                                    <span class="font-medium text-zinc-700 truncate {{ empty($mainAddress) ? 'text-zinc-400 italic font-normal' : '' }}">
                                        {{ $mainAddress ?: __('Sin dirección principal registrada') }}
                                    </span>
                                </div>
                                @if(!empty($mainAddress))
                                    <button 
                                        type="button" 
                                        @click.stop.prevent="copyText()"
                                        class="px-2.5 py-1 rounded-md bg-white hover:bg-zinc-50 border border-zinc-200 text-zinc-700 hover:text-zinc-900 text-xs font-semibold transition cursor-pointer flex items-center gap-1.5 shrink-0 shadow-2xs"
                                        title="{{ __('Copiar dirección') }}"
                                    >
                                        <template x-if="!copied">
                                            <span class="flex items-center gap-1">
                                                <x-lucide-copy class="w-3.5 h-3.5 text-zinc-500" />
                                                <span>{{ __('Copiar') }}</span>
                                            </span>
                                        </template>
                                        <template x-if="copied">
                                            <span class="flex items-center gap-1 text-ink-2 font-semibold">
                                                <x-lucide-check class="w-3.5 h-3.5 text-ink-2" />
                                                <span>{{ __('¡Copiado!') }}</span>
                                            </span>
                                        </template>
                                    </button>
                                @endif
                            </div>

                            {{-- Portal del Cliente / QR Code Access Card --}}
                            @if($currentClient && $currentClient->portal_url)
                                <div 
                                    x-data="{
                                        copied: false,
                                        downloading: false,
                                        showCardPreview: false,
                                        copyUrl() {
                                            const url = {{ json_encode($currentClient->portal_url) }};
                                            navigator.clipboard.writeText(url).then(() => {
                                                this.copied = true;
                                                setTimeout(() => this.copied = false, 2000);
                                            });
                                        },
                                        downloadCard() {
                                            this.downloading = true;
                                            window.downloadClientQrCard({
                                                clientName: {{ json_encode($currentClient->name) }},
                                                portalUrl: {{ json_encode($currentClient->portal_url) }},
                                                qrSvgDataUri: {{ json_encode($currentClientQrDataUri) }},
                                                logoUrl: '{{ asset('images/logo-kudos.svg') }}',
                                                footerDecorUrl: '{{ asset('images/footer-decor.svg') }}'
                                            }).finally(() => {
                                                setTimeout(() => this.downloading = false, 600);
                                            });
                                        }
                                    }"
                                    class="bg-surface  via-white  rounded-xl p-3.5 border border-line shadow-2xs space-y-2.5"
                                >
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <div class="w-7 h-7 rounded-lg bg-ink text-white flex items-center justify-center shrink-0 shadow-2xs">
                                                <x-lucide-qr-code class="w-4 h-4" />
                                            </div>
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-1.5">
                                                    <h3 class="text-xs font-semibold text-zinc-900 ">{{ __('Portal de seguimiento (QR)') }}</h3>
                                                    <span class="px-1.5 py-0.2 rounded bg-sunken text-ink-2 text-meta font-semibold border border-line">{{ __('Auto-autenticado') }}</span>
                                                </div>
                                                <p class="text-meta text-zinc-500 truncate">{{ __('El cliente monitorea sus pedidos en vivo sin requerir login') }}</p>
                                            </div>
                                        </div>
                                        <a 
                                            href="{{ $currentClient->portal_url }}" 
                                            target="_blank" 
                                            class="px-2.5 py-1 rounded-md bg-white hover:bg-zinc-50 border border-zinc-200 text-zinc-700 hover:text-zinc-900 text-xs font-semibold transition flex items-center gap-1.5 shadow-2xs shrink-0 cursor-pointer"
                                            title="{{ __('Abrir portal del cliente en pestaña nueva') }}"
                                        >
                                            <x-lucide-external-link class="w-3.5 h-3.5 text-zinc-400" />
                                            <span>{{ __('Ver portal') }}</span>
                                        </a>
                                    </div>

                                    <div class="flex items-center gap-3.5 bg-white p-2.5 rounded-lg border border-line">
                                        {{-- Clickable QR Code Thumbnail with Card Download --}}
                                        <div 
                                            @click="downloadCard()" 
                                            class="relative group cursor-pointer shrink-0 p-1.5 bg-white rounded-lg border-2 border-line shadow-2xs hover:shadow-md transition hover:scale-105 active:scale-95"
                                            title="{{ __('Haz clic para descargar la tarjeta JPG') }}"
                                        >
                                            <div class="w-16 h-16 flex items-center justify-center">
                                                {!! $currentClientQrSvg !!}
                                            </div>
                                            <div class="absolute inset-0 bg-stone-900/70 rounded-md opacity-0 group-hover:opacity-100 transition flex flex-col items-center justify-center text-white text-meta font-semibold p-1 text-center">
                                                <x-lucide-download class="w-3.5 h-3.5 mb-0.5" />
                                                <span>{{ __('Tarjeta JPG') }}</span>
                                            </div>
                                        </div>

                                        {{-- URL & Action Buttons --}}
                                        <div class="min-w-0 flex-1 space-y-2">
                                            <div class="text-meta text-zinc-600 truncate font-mono bg-zinc-50 px-2 py-1 rounded border border-zinc-200/70 select-all">
                                                {{ $currentClient->portal_url }}
                                            </div>
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <button 
                                                    type="button" 
                                                    @click="downloadCard()" 
                                                    :disabled="downloading"
                                                    class="ui-btn ui-btn-secondary ui-btn-sm"
                                                >
                                                    <template x-if="!downloading">
                                                        <span class="flex items-center gap-1.5">
                                                            <x-lucide-download class="w-3.5 h-3.5 text-white" />
                                                            <span>{{ __('Descargar tarjeta JPG') }}</span>
                                                        </span>
                                                    </template>
                                                    <template x-if="downloading">
                                                        <span class="flex items-center gap-1.5 text-white">
                                                            <x-lucide-loader-2 class="w-3.5 h-3.5 animate-spin" />
                                                            <span>{{ __('Generando JPG...') }}</span>
                                                        </span>
                                                    </template>
                                                </button>

                                                <button 
                                                    type="button" 
                                                    @click="copyUrl()" 
                                                    class="px-2 py-1 rounded-md bg-zinc-100 hover:bg-zinc-200 text-zinc-700 text-xs font-medium transition cursor-pointer flex items-center gap-1"
                                                >
                                                    <template x-if="!copied">
                                                        <span class="flex items-center gap-1">
                                                            <x-lucide-copy class="w-3 h-3 text-zinc-500" />
                                                            <span>{{ __('Copiar') }}</span>
                                                        </span>
                                                    </template>
                                                    <template x-if="copied">
                                                        <span class="flex items-center gap-1 text-ink-2 font-semibold">
                                                            <x-lucide-check class="w-3 h-3 text-ink-2" />
                                                            <span>{{ __('¡Copiado!') }}</span>
                                                        </span>
                                                    </template>
                                                </button>

                                                <button 
                                                    type="button" 
                                                    @click="showCardPreview = !showCardPreview" 
                                                    class="px-2 py-1 rounded-md bg-zinc-100 hover:bg-zinc-200 text-zinc-700 text-xs font-medium transition cursor-pointer flex items-center gap-1"
                                                >
                                                    <x-lucide-eye class="w-3.5 h-3.5 text-zinc-500" />
                                                    <span x-text="showCardPreview ? '{{ __('Ocultar vista') }}' : '{{ __('Ver tarjeta') }}'"></span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Expandable Full Card Mockup & Details --}}
                                    <div x-show="showCardPreview" x-cloak class="pt-3 border-t border-line space-y-4">
                                        <div class="flex flex-col items-center">
                                            <p class="text-meta text-zinc-500 mb-2 font-medium">
                                                {{ __('Vista previa de la tarjeta JPG (Haz clic para descargar):') }}
                                            </p>

                                            <div 
                                                @click="downloadCard()" 
                                                class="w-full max-w-xs bg-white rounded-3xl shadow-lg border-2 border-zinc-100 overflow-hidden cursor-pointer group hover:border-line hover:shadow-xl transition duration-300 transform hover:-translate-y-0.5 relative select-none flex flex-col justify-between"
                                                style="aspect-ratio: 9 / 16;"
                                                title="{{ __('Haz clic para descargar la tarjeta JPG') }}"
                                            >
                                                {{-- Card Header: Kudos Logo --}}
                                                <div class="pt-8 px-6 flex justify-center">
                                                    <img src="{{ asset('images/logo-kudos.svg') }}" alt="Kudos" class="h-14 w-auto">
                                                </div>

                                                {{-- Card Body: Client Name & Underline --}}
                                                <div class="px-6 text-center space-y-1.5 my-auto">
                                                    <div class="space-y-1">
                                                        <h2 class="text-lg font-semibold text-zinc-900 line-clamp-2">
                                                            {{ $currentClient->name }}
                                                        </h2>
                                                        <div class="w-16 h-1 bg-ink rounded-full mx-auto"></div>
                                                    </div>
                                                    <p class="text-meta text-zinc-700 font-medium leading-tight">
                                                        Consulta el estado de tus<br>pedidos en línea.
                                                    </p>

                                                    {{-- QR Code with sunshine rays --}}
                                                    <div class="relative py-3 flex items-center justify-center">
                                                        <div class="absolute left-6 flex flex-col gap-2.5">
                                                            <div class="w-3.5 h-1 bg-ink rounded-full transform -rotate-25"></div>
                                                            <div class="w-4 h-1 bg-ink rounded-full"></div>
                                                            <div class="w-3.5 h-1 bg-ink rounded-full transform rotate-25"></div>
                                                        </div>

                                                        <div class="p-3 bg-white rounded-2xl border-4 border-line shadow-[0_0_20px_rgba(251,191,36,0.35)] w-36 h-36 flex items-center justify-center">
                                                            <div class="w-28 h-28">
                                                                {!! $currentClientQrSvg !!}
                                                            </div>
                                                        </div>

                                                        <div class="absolute right-6 flex flex-col gap-2.5">
                                                            <div class="w-3.5 h-1 bg-ink rounded-full transform rotate-25"></div>
                                                            <div class="w-4 h-1 bg-ink rounded-full"></div>
                                                            <div class="w-3.5 h-1 bg-ink rounded-full transform -rotate-25"></div>
                                                        </div>
                                                    </div>

                                                    <div class="bg-ink rounded-full py-1.5 px-4 inline-flex items-center gap-2 text-white font-semibold text-meta">
                                                        <x-lucide-smartphone class="w-4 h-4 text-zinc-900" />
                                                        <div class="leading-tight text-left">
                                                            <div>Escanea para acceder</div>
                                                            <div>a tus órdenes</div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="w-full shrink-0">
                                                    <img src="{{ asset('images/footer-decor.svg') }}" alt="" class="w-full h-14 object-cover object-bottom">
                                                </div>

                                                <div class="absolute inset-0 bg-stone-900/70 opacity-0 group-hover:opacity-100 transition flex flex-col items-center justify-center text-white space-y-2 backdrop-blur-2xs">
                                                    <div class="w-12 h-12 rounded-full bg-ink text-white flex items-center justify-center shadow-lg">
                                                        <x-lucide-download class="w-6 h-6" />
                                                    </div>
                                                    <span class="text-xs font-semibold ">{{ __('Descargar tarjeta JPG') }}</span>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Information Checklist --}}
                                        <div class="bg-white/90 border border-line rounded-lg p-3 text-xs text-zinc-600 space-y-1.5">
                                            <h4 class="font-semibold text-zinc-800 flex items-center gap-1.5 text-meta">
                                                <x-lucide-info class="w-3.5 h-3.5 text-ink-2" />
                                                <span>{{ __('Características del portal') }}</span>
                                            </h4>
                                            <ul class="space-y-1 text-meta text-zinc-500 list-disc list-inside">
                                                <li>{{ __('Acceso inmediato sin contraseñas: el código QR auto-autentica al cliente.') }}</li>
                                                <li>{{ __('Solo lectura y monitoreo: los clientes no pueden editar información del sistema.') }}</li>
                                                <li>{{ __('Línea de tiempo compacta con hitos importantes de cada proyecto.') }}</li>
                                                <li>{{ __('Optimizado para vista móvil en teléfonos inteligentes.') }}</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            {{-- 2. General Phones & Email Section (Supports multiple phone numbers with labels & hover '+' button) --}}
                            <div class="space-y-2 pt-1">
                                <div 
                                    x-data="{ 
                                        hovered: false,
                                        formatPhone(el) {
                                            let val = el.value.replace(/\D/g, '');
                                            if (val.length === 11 && val.startsWith('1')) val = val.substring(1);
                                            if (val.length > 10) val = val.substring(0, 10);
                                            if (val.length === 0) { el.value = ''; }
                                            else if (val.length <= 3) { el.value = '(' + val; }
                                            else if (val.length <= 6) { el.value = '(' + val.substring(0, 3) + ') ' + val.substring(3); }
                                            else { el.value = '(' + val.substring(0, 3) + ') ' + val.substring(3, 6) + '-' + val.substring(6); }
                                        }
                                    }" 
                                    @mouseenter="hovered = true" 
                                    @mouseleave="hovered = false"
                                    class="space-y-1.5"
                                >
                                    @foreach($phones as $pIndex => $pItem)
                                        <div class="flex items-center gap-2 group/phone">
                                            <x-lucide-phone class="w-4 h-4 text-zinc-400 shrink-0" />
                                            <input 
                                                type="text" 
                                                wire:model.live.debounce.150ms="phones.{{ $pIndex }}.label"
                                                placeholder="{{ __('GA / SC / AL...') }}"
                                                :size="Math.max(6, ({{ json_encode($pItem['label'] ?? '') }} || 'General').length + 1)"
                                                class="bg-zinc-100/90 hover:bg-zinc-200/70 focus:bg-white border border-zinc-200/70 focus:border-zinc-300 rounded px-1.5 py-0.5 text-meta font-semibold text-zinc-700 focus:outline-none transition shrink-0 max-w-[110px]"
                                            />
                                            <input 
                                                type="text" 
                                                wire:model.blur="phones.{{ $pIndex }}.phone"
                                                x-on:input="formatPhone($el)"
                                                placeholder="(000) 000-0000"
                                                class="flex-1 bg-transparent border border-transparent hover:bg-zinc-100/60 hover:border-zinc-200/60 focus:bg-white focus:border-zinc-300 focus:ring-2 focus:ring-zinc-900/5 rounded-md -mx-1 px-1 py-0.5 text-xs text-zinc-800 font-mono focus:outline-none transition min-w-[130px]"
                                            />

                                            {{-- Plus button (appears on hover over phone section) --}}
                                            @if($loop->last)
                                                <button 
                                                    type="button" 
                                                    wire:click="addPhone"
                                                    x-show="hovered"
                                                    x-transition:enter="transition ease-out duration-100"
                                                    x-transition:enter-start="opacity-0 scale-90"
                                                    x-transition:enter-end="opacity-100 scale-100"
                                                    class="p-1 rounded-md bg-sunken hover:bg-sunken text-ink-2 border border-line text-xs transition cursor-pointer shrink-0 flex items-center gap-0.5 shadow-2xs"
                                                    title="{{ __('Agregar otro número de teléfono') }}"
                                                >
                                                    <x-lucide-plus class="w-3.5 h-3.5 text-ink-2" />
                                                </button>
                                            @endif

                                            {{-- Delete phone row button --}}
                                            @if(count($phones) > 1)
                                                <button 
                                                    type="button" 
                                                    wire:click="removePhone({{ $pIndex }})"
                                                    class="p-1 rounded-md text-zinc-400 hover:text-danger hover:bg-danger-soft transition cursor-pointer opacity-0 group-hover/phone:opacity-100 shrink-0"
                                                    title="{{ __('Eliminar teléfono') }}"
                                                >
                                                    <x-lucide-x class="w-3.5 h-3.5" />
                                                </button>
                                            @endif
                                        </div>
                                        @error("phones.{$pIndex}.phone")
                                            <p class="text-meta text-danger font-medium pl-6 mt-0.5">{{ $message }}</p>
                                        @enderror
                                    @endforeach
                                </div>

                                <div class="flex items-center gap-2 pt-0.5">
                                    <x-lucide-mail class="w-4 h-4 text-zinc-400 shrink-0" />
                                    <input 
                                        type="email" 
                                        wire:model.blur="locations.0.email"
                                        placeholder="{{ __('Correo electrónico de la compañía (correo@cliente.com)') }}"
                                        class="w-full bg-transparent border border-transparent hover:bg-zinc-100/60 hover:border-zinc-200/60 focus:bg-white focus:border-zinc-300 focus:ring-2 focus:ring-zinc-900/5 rounded-md -mx-1 px-1 py-1 text-xs text-zinc-800 focus:outline-none transition"
                                    />
                                </div>
                            </div>

                            {{-- 3. Client Website --}}
                            <div class="flex items-center gap-2">
                                <x-lucide-globe class="w-4 h-4 text-zinc-400 shrink-0" />
                                <input 
                                    type="url" 
                                    wire:model="website"
                                    placeholder="{{ __('Sitio web (https://www.cliente.com)') }}"
                                    class="w-full bg-transparent border border-transparent hover:bg-zinc-100/60 hover:border-zinc-200/60 focus:bg-white focus:border-zinc-300 focus:ring-2 focus:ring-zinc-900/5 rounded-md -mx-1 px-1 py-1 text-xs text-zinc-800 font-mono focus:outline-none transition"
                                />
                            </div>

                            {{-- 4. Aliases / Nombres Alternativos (Interactive Tag Labels) --}}
                            <div class="flex items-start gap-2">
                                <x-lucide-tags class="w-4 h-4 text-ink-2 shrink-0 mt-1.5" />
                                <div class="w-full space-y-1">
                                    <div class="flex flex-wrap items-center gap-1.5 p-1.5 bg-zinc-50 hover:bg-zinc-100/60 focus-within:bg-white border border-zinc-200/70 focus-within:border-zinc-300 focus-within:ring-2 focus-within:ring-zinc-900/5 rounded-lg transition min-h-[38px]">
                                        @foreach($aliases as $index => $alias)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-semibold bg-sunken text-ink-2 border border-line shadow-2xs">
                                                <span>{{ $alias }}</span>
                                                <button 
                                                    type="button" 
                                                    wire:click="removeAlias({{ $index }})" 
                                                    class="hover:text-danger text-ink-2 transition cursor-pointer p-0.5 rounded"
                                                    title="{{ __('Eliminar alias') }}"
                                                >
                                                    <x-lucide-x class="w-3 h-3" />
                                                </button>
                                            </span>
                                        @endforeach

                                        <input 
                                            type="text" 
                                            wire:model="newAlias" 
                                            wire:keydown.enter.prevent="addAlias" 
                                            wire:keydown.comma.prevent="addAlias"
                                            wire:blur="addAlias"
                                            placeholder="{{ empty($aliases) ? __('Escribe un alias y presiona Enter...') : __('Agregar alias...') }}"
                                            class="flex-1 min-w-[140px] bg-transparent text-xs text-zinc-800 placeholder-zinc-400 focus:outline-none px-1 py-0.5"
                                        />
                                    </div>
                                    <span class="text-meta text-zinc-400 block px-0.5">
                                        {{ __('Presiona Enter para agregar cada alias. Se usarán para vincular tarjetas de Trello automáticamente.') }}
                                    </span>
                                </div>
                            </div>

                            {{-- 5. Notes / Observaciones --}}
                            <div class="flex items-start gap-2">
                                <x-lucide-file-text class="w-4 h-4 text-zinc-400 shrink-0 mt-1" />
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
                                                <x-lucide-map-pin class="w-3.5 h-3.5 text-pin shrink-0" />
                                                <input 
                                                    type="text" 
                                                    wire:model.live.debounce.150ms="locations.{{ $index }}.name" 
                                                    placeholder="{{ __('Nombre de la locación (ej. Grayson)') }}"
                                                    :size="Math.max(14, ({{ json_encode($location['name'] ?? '') }} || 'NOMBRE LOCACIÓN (EJ. GRAYSON)').length + 1)"
                                                    class="bg-transparent hover:bg-zinc-100/70 focus:bg-white border border-transparent hover:border-zinc-200/60 focus:border-zinc-300 focus:ring-2 focus:ring-zinc-900/5 rounded-md -mx-1 px-1 py-0.5 text-xs font-semibold text-zinc-900 focus:outline-none transition shrink-0 max-w-full"
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

                                                        <div class="px-2.5 py-1 text-meta font-semibold text-zinc-400 border-b border-zinc-100 flex items-center justify-between">
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
                                                                    class="w-full text-left px-2.5 py-1.5 hover:bg-sunken hover:text-ink-2 flex items-center justify-between transition cursor-pointer group"
                                                                >
                                                                    <div class="flex items-center gap-2 min-w-0">
                                                                        <x-lucide-user class="w-3.5 h-3.5 text-zinc-400 group-hover:text-ink-2 shrink-0" />
                                                                        <span class="font-semibold text-zinc-800 group-hover:text-ink-2 truncate">{{ $cName }}</span>
                                                                        @if(!empty($c['department']))
                                                                            <span class="text-meta text-zinc-400">({{ $c['department'] }})</span>
                                                                        @endif
                                                                    </div>
                                                                    @if($currentManager && mb_strtolower($currentManager, 'UTF-8') === mb_strtolower($cName, 'UTF-8'))
                                                                        <x-lucide-check class="w-3.5 h-3.5 text-ink-2 shrink-0" />
                                                                    @endif
                                                                </button>
                                                            @endif
                                                        @endforeach

                                                        @if(!$hasExactMatch && !empty($currentManager))
                                                            <button 
                                                                type="button" 
                                                                @click="createContactAndSelect('{{ addslashes($currentManager) }}')"
                                                                class="w-full text-left px-2.5 py-2 hover:bg-sunken text-ink-2 font-semibold border-t border-zinc-100 flex items-center gap-2 transition cursor-pointer"
                                                            >
                                                                <x-lucide-user-plus class="w-3.5 h-3.5 text-ink-2 shrink-0" />
                                                                <span>{{ __('Crear contacto') }} "<strong>{{ $currentManager }}</strong>"</span>
                                                            </button>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                            <button 
                                                type="button" 
                                                x-on:click="KudosConfirmChange({ field: @js(__('Locación')), from: @js(trim(($location['name'] ?? '').' '.($location['address'] ?? ''))), to: '', note: @js(__('La locación se elimina de inmediato y las órdenes asociadas quedan sin locación.')), confirmText: @js(__('Eliminar locación')) }).then(ok => ok && $wire.removeLocation({{ $index }}))"
                                                class="text-meta text-zinc-400 hover:text-danger transition cursor-pointer flex items-center gap-1 shrink-0"
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
                                                    wire:model.live.debounce.250ms="locations.{{ $index }}.address" 
                                                    placeholder="{{ __('Dirección de la locación') }}"
                                                    class="w-full bg-transparent hover:bg-zinc-100/60 focus:bg-white border border-transparent hover:border-zinc-200/60 focus:border-zinc-300 focus:ring-2 focus:ring-zinc-900/5 rounded-md -mx-1 px-1 py-1 text-xs text-zinc-900 font-semibold focus:outline-none transition"
                                                />
                                                @error("locations.{$index}.address")
                                                    <p class="text-meta text-danger mt-0.5">{{ $message }}</p>
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
                                                    <p class="text-meta text-danger mt-0.5">{{ $message }}</p>
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
                                                    <p class="text-meta text-danger mt-0.5">{{ $message }}</p>
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
                                        class="inline-flex items-center gap-1 text-xs text-ink-2 font-semibold hover:text-ink-2 transition cursor-pointer"
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
                                        <span class="text-xs font-semibold text-zinc-800 ">
                                            {{ __('Contactos') }} ({{ count($contacts) }})
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <button 
                                            type="button" 
                                            wire:click.stop="addContact; openContacts = true"
                                            class="inline-flex items-center gap-1 text-xs text-ink-2 font-semibold hover:text-ink-2 transition cursor-pointer"
                                        >
                                            <x-lucide-plus class="w-3.5 h-3.5" />
                                            <span>{{ __('Agregar contacto') }}</span>
                                        </button>
                                        <button type="button" class="text-zinc-400 hover:text-zinc-700 p-0.5 flex items-center gap-1 text-meta font-medium">
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
                                                <div class="px-2.5 py-1.5 bg-sunken border border-line rounded-lg flex items-center justify-between gap-2 text-ink-2 text-meta select-none">
                                                    <div class="flex items-center gap-1.5 min-w-0">
                                                        <x-lucide-alert-triangle class="w-3.5 h-3.5 text-ink-2 shrink-0" />
                                                        <span class="truncate">
                                                            {{ __('¡Posible contacto duplicado con') }} <strong>"{{ $dupWarning }}"</strong>!
                                                        </span>
                                                    </div>
                                                    <div class="flex items-center gap-1.5 shrink-0">
                                                        <button 
                                                            type="button" 
                                                            x-on:click="KudosConfirmChange({ field: @js(__('Fusionar contactos')), from: @js($contact['name'] ?? ''), to: @js($dupWarning), confirmText: @js(__('Fusionar')) }).then(ok => ok && $wire.confirmMergeContact({{ $index }}, @js($dupWarning)))"
                                                            class="text-meta font-semibold text-ink-2 bg-surface border border-line hover:bg-sunken px-1.5 py-0.5 rounded transition cursor-pointer"
                                                        >
                                                            {{ __('Confirmar') }}
                                                        </button>
                                                        <button 
                                                            type="button" 
                                                            wire:click="dismissDuplicateContact({{ $index }})"
                                                            class="text-meta text-ink-2 hover:text-ink-2 font-medium cursor-pointer"
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
                                                        placeholder="{{ __('Nombre del contacto') }}"
                                                        :size="Math.max(12, ({{ json_encode($contact['name'] ?? '') }} || 'NOMBRE DEL CONTACTO').length + 1)"
                                                        class="bg-transparent hover:bg-zinc-100/70 focus:bg-white border border-transparent hover:border-zinc-200/60 focus:border-zinc-300 focus:ring-2 focus:ring-zinc-900/5 rounded-md -mx-1 px-1 py-0.5 text-xs font-semibold text-zinc-900 focus:outline-none transition shrink-0 max-w-full"
                                                    />
                                                    <label class="inline-flex items-center gap-1 text-meta text-zinc-400 hover:text-zinc-600 cursor-pointer select-none shrink-0" title="{{ __('Marcar como contacto principal') }}">
                                                        <input 
                                                            type="checkbox" 
                                                            wire:model="contacts.{{ $index }}.is_primary"
                                                            class="rounded border-zinc-300 text-ink-2 focus:ring-ink/20 w-3 h-3 opacity-70 transition cursor-pointer"
                                                        />
                                                        <span class="font-normal">{{ __('Principal') }}</span>
                                                    </label>
                                                </div>
                                                @if($index > 0)
                                                    <button 
                                                        type="button" 
                                                        wire:click="removeContact({{ $index }})"
                                                        class="text-meta text-zinc-400 hover:text-danger transition cursor-pointer flex items-center gap-1 shrink-0"
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

                                            {{-- Channel & Language Preferences --}}
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-0.5">
                                                <div class="flex items-center gap-1.5 text-meta text-zinc-500">
                                                    <span class="shrink-0 font-medium text-meta text-zinc-400 ">{{ __('Canal:') }}</span>
                                                    <select 
                                                        wire:model="contacts.{{ $index }}.preferred_channel"
                                                        class="bg-transparent hover:bg-zinc-100/60 focus:bg-white border border-transparent focus:border-zinc-300 rounded px-1.5 py-0.5 text-xs text-zinc-700 focus:outline-none transition w-full"
                                                    >
                                                        <option value="any">{{ __('Cualquiera') }}</option>
                                                        <option value="whatsapp">WhatsApp</option>
                                                        <option value="call">{{ __('Llamada') }}</option>
                                                        <option value="email">Email</option>
                                                    </select>
                                                </div>
                                                <div class="flex items-center gap-1.5 text-meta text-zinc-500">
                                                    <span class="shrink-0 font-medium text-meta text-zinc-400 ">{{ __('Idioma:') }}</span>
                                                    <select 
                                                        wire:model="contacts.{{ $index }}.preferred_language"
                                                        class="bg-transparent hover:bg-zinc-100/60 focus:bg-white border border-transparent focus:border-zinc-300 rounded px-1.5 py-0.5 text-xs text-zinc-700 focus:outline-none transition w-full"
                                                    >
                                                        <option value="es">ES - {{ __('Español') }}</option>
                                                        <option value="en">EN - {{ __('Inglés') }}</option>
                                                        <option value="both">ES / EN - {{ __('Ambos / Bilingüe') }}</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- TAB 2: Brand Kit (Diseño, Recursos, Fuentes, Colores & Directrices) --}}
                @if($activeTab === 'brand_kit' || $activeTab === 'links')
                    <div 
                        x-data="{
                            copiedItem: null,
                            copyText(text, id) {
                                if (!text) return;
                                if (navigator.clipboard && window.isSecureContext) {
                                    navigator.clipboard.writeText(text).then(() => {
                                        this.copiedItem = id;
                                        setTimeout(() => { if (this.copiedItem === id) this.copiedItem = null; }, 2000);
                                    });
                                } else {
                                    const ta = document.createElement('textarea');
                                    ta.value = text;
                                    ta.style.position = 'fixed';
                                    ta.style.opacity = '0';
                                    document.body.appendChild(ta);
                                    ta.focus();
                                    ta.select();
                                    try { document.execCommand('copy'); } catch (e) {}
                                    document.body.removeChild(ta);
                                    this.copiedItem = id;
                                    setTimeout(() => { if (this.copiedItem === id) this.copiedItem = null; }, 2000);
                                }
                            }
                        }"
                        class="space-y-6"
                    >
                        {{-- Section 1: Activos y Enlaces Clave --}}
                        <div class="space-y-3">
                            <div class="flex items-center justify-between pb-1">
                                <div>
                                    <h3 class="text-xs font-semibold text-zinc-900 flex items-center gap-1.5">
                                        <x-lucide-folder class="w-3.5 h-3.5 text-ink-2" />
                                        <span>{{ __('Activos y enlaces clave') }}</span>
                                    </h3>
                                    <p class="text-meta text-zinc-500 mt-0.5">
                                        {{ __('Google Drive, Brandbook, logos maestros y catálogos. Soporta URLs y rutas de red/servidor.') }}
                                    </p>
                                </div>
                            </div>

                            {{-- 4 Predefined Core Slots --}}
                            <div class="space-y-2.5">
                                {{-- 1. Google Drive / Carpeta Raíz --}}
                                <div class="flex items-center gap-2 group/slot">
                                    <span class="w-24 sm:w-28 text-meta font-semibold text-zinc-600 flex items-center gap-1.5 shrink-0">
                                        <x-lucide-hard-drive class="w-3.5 h-3.5 text-zinc-400" />
                                        <span>{{ __('Drive / Raíz') }}</span>
                                    </span>
                                    <input 
                                        type="text" 
                                        wire:model="brandKit.drive_url" 
                                        placeholder="{{ __('Enlace de Drive o ruta de servidor') }}"
                                        class="flex-1 bg-transparent hover:bg-zinc-100/60 focus:bg-white border border-transparent hover:border-zinc-200/60 focus:border-zinc-300 focus:ring-2 focus:ring-zinc-900/5 rounded-md -mx-1 px-2 py-1 text-xs text-zinc-800 font-mono focus:outline-none transition min-w-0"
                                    />
                                    @php
                                        $driveVal = trim($brandKit['drive_url'] ?? '');
                                        $isDriveUrl = Str::startsWith($driveVal, ['http://', 'https://']);
                                    @endphp
                                    @if($isDriveUrl)
                                        <a 
                                            href="{{ $driveVal }}" 
                                            target="_blank" 
                                            class="p-1 rounded-md text-ink-2 hover:text-ink-2 hover:bg-sunken transition cursor-pointer shrink-0" 
                                            title="{{ __('Abrir enlace en nueva pestaña') }}"
                                        >
                                            <x-lucide-external-link class="w-3.5 h-3.5" />
                                        </a>
                                    @endif
                                    @if(!empty($driveVal))
                                        <button 
                                            type="button" 
                                            @click="copyText({{ json_encode($driveVal) }}, 'drive_slot')" 
                                            class="p-1 rounded-md text-zinc-400 hover:text-zinc-700 hover:bg-zinc-100 transition cursor-pointer shrink-0"
                                            title="{{ __('Copiar') }}"
                                        >
                                            <template x-if="copiedItem === 'drive_slot'">
                                                <x-lucide-check class="w-3.5 h-3.5 text-ink-2" />
                                            </template>
                                            <template x-if="copiedItem !== 'drive_slot'">
                                                <x-lucide-copy class="w-3.5 h-3.5" />
                                            </template>
                                        </button>
                                    @endif
                                </div>

                                {{-- 2. Manual de Marca / Brandbook --}}
                                <div class="flex items-center gap-2 group/slot">
                                    <span class="w-24 sm:w-28 text-meta font-semibold text-zinc-600 flex items-center gap-1.5 shrink-0">
                                        <x-lucide-book-open class="w-3.5 h-3.5 text-zinc-400" />
                                        <span>{{ __('Brandbook') }}</span>
                                    </span>
                                    <input 
                                        type="text" 
                                        wire:model="brandKit.brandbook_url" 
                                        placeholder="{{ __('URL de Figma, Canva, Drive o PDF...') }}"
                                        class="flex-1 bg-transparent hover:bg-zinc-100/60 focus:bg-white border border-transparent hover:border-zinc-200/60 focus:border-zinc-300 focus:ring-2 focus:ring-zinc-900/5 rounded-md -mx-1 px-2 py-1 text-xs text-zinc-800 font-mono focus:outline-none transition min-w-0"
                                    />
                                    @php
                                        $bbVal = trim($brandKit['brandbook_url'] ?? '');
                                        $isBbUrl = Str::startsWith($bbVal, ['http://', 'https://']);
                                    @endphp
                                    @if($isBbUrl)
                                        <a 
                                            href="{{ $bbVal }}" 
                                            target="_blank" 
                                            class="p-1 rounded-md text-ink-2 hover:text-ink-2 hover:bg-sunken transition cursor-pointer shrink-0" 
                                            title="{{ __('Abrir enlace en nueva pestaña') }}"
                                        >
                                            <x-lucide-external-link class="w-3.5 h-3.5" />
                                        </a>
                                    @endif
                                    @if(!empty($bbVal))
                                        <button 
                                            type="button" 
                                            @click="copyText({{ json_encode($bbVal) }}, 'bb_slot')" 
                                            class="p-1 rounded-md text-zinc-400 hover:text-zinc-700 hover:bg-zinc-100 transition cursor-pointer shrink-0"
                                            title="{{ __('Copiar') }}"
                                        >
                                            <template x-if="copiedItem === 'bb_slot'">
                                                <x-lucide-check class="w-3.5 h-3.5 text-ink-2" />
                                            </template>
                                            <template x-if="copiedItem !== 'bb_slot'">
                                                <x-lucide-copy class="w-3.5 h-3.5" />
                                            </template>
                                        </button>
                                    @endif
                                </div>

                                {{-- 3. Logos Oficiales --}}
                                <div class="flex items-center gap-2 group/slot">
                                    <span class="w-24 sm:w-28 text-meta font-semibold text-zinc-600 flex items-center gap-1.5 shrink-0">
                                        <x-lucide-image class="w-3.5 h-3.5 text-zinc-400" />
                                        <span>{{ __('Logos / Arte') }}</span>
                                    </span>
                                    <input 
                                        type="text" 
                                        wire:model="brandKit.logo_url" 
                                        placeholder="{{ __('Enlace o carpeta con vectores AI/EPS/SVG o PNG...') }}"
                                        class="flex-1 bg-transparent hover:bg-zinc-100/60 focus:bg-white border border-transparent hover:border-zinc-200/60 focus:border-zinc-300 focus:ring-2 focus:ring-zinc-900/5 rounded-md -mx-1 px-2 py-1 text-xs text-zinc-800 font-mono focus:outline-none transition min-w-0"
                                    />
                                    @php
                                        $logoVal = trim($brandKit['logo_url'] ?? '');
                                        $isLogoUrl = Str::startsWith($logoVal, ['http://', 'https://']);
                                    @endphp
                                    @if($isLogoUrl)
                                        <a 
                                            href="{{ $logoVal }}" 
                                            target="_blank" 
                                            class="p-1 rounded-md text-ink-2 hover:text-ink-2 hover:bg-sunken transition cursor-pointer shrink-0" 
                                            title="{{ __('Abrir enlace en nueva pestaña') }}"
                                        >
                                            <x-lucide-external-link class="w-3.5 h-3.5" />
                                        </a>
                                    @endif
                                    @if(!empty($logoVal))
                                        <button 
                                            type="button" 
                                            @click="copyText({{ json_encode($logoVal) }}, 'logo_slot')" 
                                            class="p-1 rounded-md text-zinc-400 hover:text-zinc-700 hover:bg-zinc-100 transition cursor-pointer shrink-0"
                                            title="{{ __('Copiar') }}"
                                        >
                                            <template x-if="copiedItem === 'logo_slot'">
                                                <x-lucide-check class="w-3.5 h-3.5 text-ink-2" />
                                            </template>
                                            <template x-if="copiedItem !== 'logo_slot'">
                                                <x-lucide-copy class="w-3.5 h-3.5" />
                                            </template>
                                        </button>
                                    @endif
                                </div>

                                {{-- 4. Catálogos de Productos / Uniformes --}}
                                <div class="flex items-center gap-2 group/slot">
                                    <span class="w-24 sm:w-28 text-meta font-semibold text-zinc-600 flex items-center gap-1.5 shrink-0">
                                        <x-lucide-layers class="w-3.5 h-3.5 text-zinc-400" />
                                        <span>{{ __('Catálogos') }}</span>
                                    </span>
                                    <input 
                                        type="text" 
                                        wire:model="brandKit.catalog_url" 
                                        placeholder="{{ __('Catálogo de productos, uniformes o muestras...') }}"
                                        class="flex-1 bg-transparent hover:bg-zinc-100/60 focus:bg-white border border-transparent hover:border-zinc-200/60 focus:border-zinc-300 focus:ring-2 focus:ring-zinc-900/5 rounded-md -mx-1 px-2 py-1 text-xs text-zinc-800 font-mono focus:outline-none transition min-w-0"
                                    />
                                    @php
                                        $catVal = trim($brandKit['catalog_url'] ?? '');
                                        $isCatUrl = Str::startsWith($catVal, ['http://', 'https://']);
                                    @endphp
                                    @if($isCatUrl)
                                        <a 
                                            href="{{ $catVal }}" 
                                            target="_blank" 
                                            class="p-1 rounded-md text-ink-2 hover:text-ink-2 hover:bg-sunken transition cursor-pointer shrink-0" 
                                            title="{{ __('Abrir enlace en nueva pestaña') }}"
                                        >
                                            <x-lucide-external-link class="w-3.5 h-3.5" />
                                        </a>
                                    @endif
                                    @if(!empty($catVal))
                                        <button 
                                            type="button" 
                                            @click="copyText({{ json_encode($catVal) }}, 'cat_slot')" 
                                            class="p-1 rounded-md text-zinc-400 hover:text-zinc-700 hover:bg-zinc-100 transition cursor-pointer shrink-0"
                                            title="{{ __('Copiar') }}"
                                        >
                                            <template x-if="copiedItem === 'cat_slot'">
                                                <x-lucide-check class="w-3.5 h-3.5 text-ink-2" />
                                            </template>
                                            <template x-if="copiedItem !== 'cat_slot'">
                                                <x-lucide-copy class="w-3.5 h-3.5" />
                                            </template>
                                        </button>
                                    @endif
                                </div>
                            </div>

                            {{-- Custom Additional Resources / Links --}}
                            <div class="pt-2 space-y-2">
                                @if(!empty($brandKit['custom_links']))
                                    <div class="space-y-2 pt-1 border-t border-zinc-200/50">
                                        <span class="text-meta font-semibold text-zinc-400 block">
                                            {{ __('Otros enlaces y recursos') }}
                                        </span>
                                        @foreach($brandKit['custom_links'] as $custIdx => $custLink)
                                            <div class="p-2.5 rounded-lg bg-stone-50/70 border border-stone-200/70 space-y-2">
                                                <div class="flex items-center justify-between gap-2">
                                                    <input 
                                                        type="text" 
                                                        wire:model="brandKit.custom_links.{{ $custIdx }}.label" 
                                                        placeholder="{{ __('Nombre / Etiqueta del recurso') }}" 
                                                        class="flex-1 bg-transparent hover:bg-white focus:bg-white border border-transparent hover:border-zinc-200 focus:border-zinc-300 rounded px-1.5 py-0.5 text-xs font-semibold text-zinc-900 focus:outline-none transition"
                                                    />
                                                    <button 
                                                        type="button" 
                                                        wire:click="removeCustomBrandLink({{ $custIdx }})" 
                                                        class="text-zinc-400 hover:text-danger p-1 transition cursor-pointer"
                                                        title="{{ __('Eliminar recurso') }}"
                                                    >
                                                        <x-lucide-trash-2 class="w-3.5 h-3.5" />
                                                    </button>
                                                </div>
                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                                    <div class="flex items-center gap-1 min-w-0">
                                                        <input 
                                                            type="text" 
                                                            wire:model="brandKit.custom_links.{{ $custIdx }}.url" 
                                                            placeholder="{{ __('URL o ruta local') }}" 
                                                            class="flex-1 bg-white border border-zinc-200/80 focus:border-zinc-300 rounded px-2 py-1 text-xs text-zinc-800 font-mono focus:outline-none transition"
                                                        />
                                                        @php
                                                            $cUrl = trim($custLink['url'] ?? '');
                                                            $isCustomUrl = Str::startsWith($cUrl, ['http://', 'https://']);
                                                        @endphp
                                                        @if($isCustomUrl)
                                                            <a href="{{ $cUrl }}" target="_blank" class="p-1 rounded text-ink-2 hover:bg-sunken shrink-0" title="{{ __('Abrir') }}">
                                                                <x-lucide-external-link class="w-3.5 h-3.5" />
                                                            </a>
                                                        @endif
                                                        @if(!empty($cUrl))
                                                            <button type="button" @click="copyText({{ json_encode($cUrl) }}, 'cust_{{ $custIdx }}')" class="p-1 rounded text-zinc-400 hover:text-zinc-700 hover:bg-zinc-100 shrink-0" title="{{ __('Copiar') }}">
                                                                <template x-if="copiedItem === 'cust_{{ $custIdx }}'">
                                                                    <x-lucide-check class="w-3.5 h-3.5 text-ink-2" />
                                                                </template>
                                                                <template x-if="copiedItem !== 'cust_{{ $custIdx }}'">
                                                                    <x-lucide-copy class="w-3.5 h-3.5" />
                                                                </template>
                                                            </button>
                                                        @endif
                                                    </div>
                                                    <input 
                                                        type="text" 
                                                        wire:model="brandKit.custom_links.{{ $custIdx }}.notes" 
                                                        placeholder="{{ __('Notas / Versión / Ubicación') }}" 
                                                        class="w-full bg-white border border-zinc-200/80 focus:border-zinc-300 rounded px-2 py-1 text-xs text-zinc-700 focus:outline-none transition"
                                                    />
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                <div class="pt-1">
                                    <button 
                                        type="button" 
                                        wire:click="addCustomBrandLink" 
                                        class="inline-flex items-center gap-1.5 text-xs text-ink-2 hover:text-ink-2 font-semibold transition cursor-pointer"
                                    >
                                        <x-lucide-plus class="w-3.5 h-3.5" />
                                        <span>{{ __('Agregar otro recurso o enlace') }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- Section 2: Paleta de Color Corporativa --}}
                        <div class="pt-5 border-t border-zinc-200/70 space-y-3">
                            <div class="flex items-center justify-between pb-1">
                                <div>
                                    <h3 class="text-xs font-semibold text-zinc-900 flex items-center gap-1.5">
                                        <x-lucide-pipette class="w-3.5 h-3.5 text-ink-2" />
                                        <span>{{ __('Paleta de color') }}</span>
                                    </h3>
                                    <p class="text-meta text-zinc-500 mt-0.5">
                                        {{ __('Colores oficiales del cliente. Haz clic en la muestra para cambiar el color y copia el HEX con 1 clic.') }}
                                    </p>
                                </div>
                                <button 
                                    type="button" 
                                    wire:click="addBrandColor" 
                                    class="inline-flex items-center gap-1 text-xs text-ink-2 hover:text-ink-2 font-semibold transition cursor-pointer"
                                >
                                    <x-lucide-plus class="w-3.5 h-3.5" />
                                    <span>{{ __('Agregar color') }}</span>
                                </button>
                            </div>

                            @if(!empty($brandKit['colors']))
                                <div class="space-y-2">
                                    @foreach($brandKit['colors'] as $cIdx => $color)
                                        @php
                                            $rawHex = trim($color['hex'] ?? '');
                                            $validHex = !empty($rawHex) ? (str_starts_with($rawHex, '#') ? $rawHex : '#'.$rawHex) : '#E4E4E7';
                                        @endphp
                                        <div class="flex items-center gap-2 p-2 rounded-lg bg-stone-50/60 border border-stone-200/60 group/color">
                                            {{-- Color Swatch Preview with native picker trigger --}}
                                            <div 
                                                class="relative w-8 h-8 rounded-lg border border-zinc-300/80 shadow-2xs overflow-hidden shrink-0 cursor-pointer flex items-center justify-center transition hover:scale-105"
                                                style="background-color: {{ $validHex }};"
                                                title="{{ __('Haz clic para seleccionar color') }}"
                                            >
                                                <input 
                                                    type="color" 
                                                    wire:model.live.debounce.150ms="brandKit.colors.{{ $cIdx }}.hex" 
                                                    class="absolute inset-0 opacity-0 cursor-pointer w-full h-full"
                                                />
                                            </div>

                                            {{-- HEX Input --}}
                                            <div class="relative shrink-0">
                                                <input 
                                                    type="text" 
                                                    wire:model.blur="brandKit.colors.{{ $cIdx }}.hex" 
                                                    placeholder="#000000" 
                                                    class="w-24 bg-white border border-zinc-200/80 focus:border-zinc-300 rounded px-2 py-1 text-xs text-zinc-900 font-mono font-semibold focus:outline-none transition"
                                                />
                                            </div>

                                            {{-- Name / Role --}}
                                            <input 
                                                type="text" 
                                                wire:model="brandKit.colors.{{ $cIdx }}.name" 
                                                placeholder="{{ __('Nombre (ej. Primario, Azul)') }}" 
                                                class="flex-1 min-w-[100px] bg-white border border-zinc-200/80 focus:border-zinc-300 rounded px-2 py-1 text-xs text-zinc-800 font-medium focus:outline-none transition"
                                            />

                                            {{-- Pantone / Notes --}}
                                            <input 
                                                type="text" 
                                                wire:model="brandKit.colors.{{ $cIdx }}.notes" 
                                                placeholder="{{ __('Pantone / Vinil / Notas') }}" 
                                                class="flex-1 min-w-[100px] bg-white border border-zinc-200/80 focus:border-zinc-300 rounded px-2 py-1 text-xs text-zinc-600 focus:outline-none transition"
                                            />

                                            {{-- Copy HEX Button --}}
                                            @if(!empty($rawHex))
                                                <button 
                                                    type="button" 
                                                    @click="copyText({{ json_encode($validHex) }}, 'col_{{ $cIdx }}')" 
                                                    class="px-2 py-1 rounded bg-zinc-100 hover:bg-zinc-200 text-zinc-700 text-meta font-semibold transition cursor-pointer flex items-center gap-1 shrink-0 border border-zinc-200/80 shadow-2xs"
                                                    title="{{ __('Copiar código HEX') }}"
                                                >
                                                    <template x-if="copiedItem === 'col_{{ $cIdx }}'">
                                                        <span class="text-ink-2 font-semibold flex items-center gap-0.5">
                                                            <x-lucide-check class="w-3 h-3 text-ink-2" />
                                                            <span>{{ __('Copiado') }}</span>
                                                        </span>
                                                    </template>
                                                    <template x-if="copiedItem !== 'col_{{ $cIdx }}'">
                                                        <span class="flex items-center gap-0.5">
                                                            <x-lucide-copy class="w-3 h-3 text-zinc-400" />
                                                            <span>HEX</span>
                                                        </span>
                                                    </template>
                                                </button>
                                            @endif

                                            {{-- Remove Color --}}
                                            <button 
                                                type="button" 
                                                wire:click="removeBrandColor({{ $cIdx }})" 
                                                class="p-1 rounded text-zinc-400 hover:text-danger transition cursor-pointer shrink-0"
                                                title="{{ __('Eliminar color') }}"
                                            >
                                                <x-lucide-trash-2 class="w-3.5 h-3.5" />
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="p-3 rounded-lg bg-stone-50/60 border border-stone-200/60 text-center text-xs text-zinc-500">
                                    {{ __('Sin colores registrados. Haz clic en "+ Agregar Color" para definir la paleta corporativa.') }}
                                </div>
                            @endif
                        </div>

                        {{-- Section 3: Tipografía & Fuentes --}}
                        <div class="pt-5 border-t border-zinc-200/70 space-y-3">
                            <div class="flex items-center justify-between pb-1">
                                <div>
                                    <h3 class="text-xs font-semibold text-zinc-900 flex items-center gap-1.5">
                                        <x-lucide-type class="w-3.5 h-3.5 text-ink-2" />
                                        <span>{{ __('Tipografía y fuentes') }}</span>
                                    </h3>
                                    <p class="text-meta text-zinc-500 mt-0.5">
                                        {{ __('Fuentes autorizadas, links de descarga o Google Fonts y notas de uso.') }}
                                    </p>
                                </div>
                                <button 
                                    type="button" 
                                    wire:click="addBrandFont" 
                                    class="inline-flex items-center gap-1 text-xs text-ink-2 hover:text-ink-2 font-semibold transition cursor-pointer"
                                >
                                    <x-lucide-plus class="w-3.5 h-3.5" />
                                    <span>{{ __('Agregar fuente') }}</span>
                                </button>
                            </div>

                            @if(!empty($brandKit['fonts']))
                                <div class="space-y-2">
                                    @foreach($brandKit['fonts'] as $fIdx => $font)
                                        @php
                                            $fName = trim($font['name'] ?? '');
                                            $fUrl = trim($font['url'] ?? '');
                                            $isFontUrl = Str::startsWith($fUrl, ['http://', 'https://']);
                                        @endphp
                                        <div class="p-2.5 rounded-lg bg-stone-50/60 border border-stone-200/60 space-y-2 group/font">
                                            <div class="flex items-center gap-2">
                                                {{-- Role / Application --}}
                                                <input 
                                                    type="text" 
                                                    wire:model="brandKit.fonts.{{ $fIdx }}.role" 
                                                    placeholder="{{ __('Uso (ej. Titulares, Textos)') }}" 
                                                    class="w-32 sm:w-36 bg-white border border-zinc-200/80 focus:border-zinc-300 rounded px-2 py-1 text-xs text-zinc-700 font-semibold focus:outline-none transition shrink-0"
                                                />

                                                {{-- Font Name --}}
                                                <div class="flex-1 flex items-center gap-1 min-w-0">
                                                    <input 
                                                        type="text" 
                                                        wire:model="brandKit.fonts.{{ $fIdx }}.name" 
                                                        placeholder="{{ __('Nombre de la fuente (ej. Montserrat Black)') }}" 
                                                        class="flex-1 bg-white border border-zinc-200/80 focus:border-zinc-300 rounded px-2 py-1 text-xs text-zinc-900 font-semibold focus:outline-none transition min-w-0"
                                                    />
                                                    @if(!empty($fName))
                                                        <button 
                                                            type="button" 
                                                            @click="copyText({{ json_encode($fName) }}, 'font_{{ $fIdx }}')" 
                                                            class="p-1 rounded text-zinc-400 hover:text-zinc-700 hover:bg-zinc-100 transition cursor-pointer shrink-0"
                                                            title="{{ __('Copiar nombre de fuente') }}"
                                                        >
                                                            <template x-if="copiedItem === 'font_{{ $fIdx }}'">
                                                                <x-lucide-check class="w-3.5 h-3.5 text-ink-2" />
                                                            </template>
                                                            <template x-if="copiedItem !== 'font_{{ $fIdx }}'">
                                                                <x-lucide-copy class="w-3.5 h-3.5" />
                                                            </template>
                                                        </button>
                                                    @endif
                                                </div>

                                                {{-- Remove Font --}}
                                                <button 
                                                    type="button" 
                                                    wire:click="removeBrandFont({{ $fIdx }})" 
                                                    class="p-1 rounded text-zinc-400 hover:text-danger transition cursor-pointer shrink-0"
                                                    title="{{ __('Eliminar fuente') }}"
                                                >
                                                    <x-lucide-trash-2 class="w-3.5 h-3.5" />
                                                </button>
                                            </div>

                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                                <div class="flex items-center gap-1 min-w-0">
                                                    <input 
                                                        type="text" 
                                                        wire:model="brandKit.fonts.{{ $fIdx }}.url" 
                                                        placeholder="{{ __('URL de descarga o Google Fonts (opcional)') }}" 
                                                        class="flex-1 bg-white border border-zinc-200/80 focus:border-zinc-300 rounded px-2 py-1 text-xs text-zinc-700 font-mono focus:outline-none transition min-w-0"
                                                    />
                                                    @if($isFontUrl)
                                                        <a href="{{ $fUrl }}" target="_blank" class="p-1 rounded text-ink-2 hover:bg-sunken shrink-0" title="{{ __('Abrir enlace') }}">
                                                            <x-lucide-external-link class="w-3.5 h-3.5" />
                                                        </a>
                                                    @endif
                                                </div>
                                                <input 
                                                    type="text" 
                                                    wire:model="brandKit.fonts.{{ $fIdx }}.notes" 
                                                    placeholder="{{ __('Notas (ej. Solo mayúsculas, tracking +50)') }}" 
                                                    class="w-full bg-white border border-zinc-200/80 focus:border-zinc-300 rounded px-2 py-1 text-xs text-zinc-600 focus:outline-none transition"
                                                />
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="p-3 rounded-lg bg-stone-50/60 border border-stone-200/60 text-center text-xs text-zinc-500">
                                    {{ __('Sin tipografías registradas. Haz clic en "+ Agregar Fuente" para indicar las fuentes que usa el cliente.') }}
                                </div>
                            @endif
                        </div>

                        {{-- Section 4: Criterios de Diseño & Preferencias del Cliente --}}
                        <div class="pt-5 border-t border-zinc-200/70 space-y-4">
                            <div>
                                <h3 class="text-xs font-semibold text-zinc-900 flex items-center gap-1.5">
                                    <x-lucide-message-square-quote class="w-3.5 h-3.5 text-ink-2" />
                                    <span>{{ __('Criterios de diseño y preferencias') }}</span>
                                </h3>
                                <p class="text-meta text-zinc-500 mt-0.5">
                                    {{ __('Directrices de marca que todo diseñador debe considerar al preparar artes de este cliente.') }}
                                </p>
                            </div>

                            <div class="space-y-3.5">
                                {{-- 1. Do's & Don'ts --}}
                                <div class="space-y-1">
                                    <label class="text-meta font-semibold text-zinc-700 flex items-center gap-1">
                                        <x-lucide-shield-alert class="w-3.5 h-3.5 text-zinc-400" />
                                        <span>{{ __('Reglas de diseño (qué hacer y qué evitar)') }}</span>
                                    </label>
                                    <textarea 
                                        wire:model="brandKit.guidelines" 
                                        rows="2" 
                                        placeholder="{{ __('Ej: Siempre usar versión horizontal del logo en uniformes. No alterar colores corporativos. Dejar 1 pulgada de margen en letreros...') }}" 
                                        class="w-full bg-stone-50/60 hover:bg-white focus:bg-white border border-stone-200/80 focus:border-zinc-300 rounded-lg p-2.5 text-xs text-zinc-800 focus:outline-none transition"
                                    ></textarea>
                                </div>

                                {{-- 2. Client Feedback / Quirks --}}
                                <div class="space-y-1">
                                    <label class="text-meta font-semibold text-zinc-700 flex items-center gap-1">
                                        <x-lucide-message-circle class="w-3.5 h-3.5 text-zinc-400" />
                                        <span>{{ __('Comentarios y preferencias de la compañía') }}</span>
                                    </label>
                                    <textarea 
                                        wire:model="brandKit.client_notes" 
                                        rows="2" 
                                        placeholder="{{ __('Ej: Don Carlos prefiere tipografías gruesas y fáciles de leer. Le gusta que le envíen 2 opciones de color en cada propuesta...') }}" 
                                        class="w-full bg-stone-50/60 hover:bg-white focus:bg-white border border-stone-200/80 focus:border-zinc-300 rounded-lg p-2.5 text-xs text-zinc-800 focus:outline-none transition"
                                    ></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- TAB 3: Proyectos --}}
                @if($activeTab === 'projects' && $currentClient)
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xs font-semibold text-zinc-900 ">
                                {{ __('Proyectos activos') }} ({{ $currentClient->activeOrders->count() }})
                            </h3>
                            @if($currentClient->archivedOrders->count() > 0)
                                <button 
                                    type="button" 
                                    wire:click="$set('activeTab', 'archived')"
                                    class="text-xs text-ink-2 hover:text-ink-2 font-semibold transition flex items-center gap-1 cursor-pointer"
                                >
                                    <span>{{ __('Ver archivados') }} ({{ $currentClient->archivedOrders->count() }})</span>
                                    <x-lucide-arrow-right class="w-3.5 h-3.5" />
                                </button>
                            @endif
                        </div>

                        @if($currentClient->activeOrders->count() > 1)
                            <div class="flex items-center gap-1.5 flex-wrap bg-stone-50/80 p-2 rounded-lg border border-stone-200/60 text-xs">
                                <span class="text-meta font-semibold text-zinc-400 mr-1">{{ __('Ordenar:') }}</span>

                                <button type="button" wire:click="sortByActiveOrders('wo')" class="px-2 py-0.5 rounded text-meta font-medium transition cursor-pointer flex items-center gap-1 {{ $activeOrdersSortField === 'wo' ? 'bg-zinc-900 text-white font-semibold shadow-2xs' : 'bg-white hover:bg-stone-100 text-zinc-700 border border-stone-200/80' }}" title="{{ __('Ordenar por WO') }}">
                                    <span>WO</span>
                                    @if($activeOrdersSortField === 'wo')
                                        @if($activeOrdersSortDirection === 'asc')
                                            <x-lucide-arrow-up class="w-3 h-3 text-white stroke-[2.5]" />
                                        @else
                                            <x-lucide-arrow-down class="w-3 h-3 text-white stroke-[2.5]" />
                                        @endif
                                    @endif
                                </button>

                                <button type="button" wire:click="sortByActiveOrders('designer')" class="px-2 py-0.5 rounded text-meta font-medium transition cursor-pointer flex items-center gap-1 {{ $activeOrdersSortField === 'designer' ? 'bg-zinc-900 text-white font-semibold shadow-2xs' : 'bg-white hover:bg-stone-100 text-zinc-700 border border-stone-200/80' }}" title="{{ __('Ordenar por Diseñador') }}">
                                    <span>{{ __('Diseñador') }}</span>
                                    @if($activeOrdersSortField === 'designer')
                                        @if($activeOrdersSortDirection === 'asc')
                                            <x-lucide-arrow-up class="w-3 h-3 text-white stroke-[2.5]" />
                                        @else
                                            <x-lucide-arrow-down class="w-3 h-3 text-white stroke-[2.5]" />
                                        @endif
                                    @endif
                                </button>

                                <button type="button" wire:click="sortByActiveOrders('location')" class="px-2 py-0.5 rounded text-meta font-medium transition cursor-pointer flex items-center gap-1 {{ $activeOrdersSortField === 'location' ? 'bg-zinc-900 text-white font-semibold shadow-2xs' : 'bg-white hover:bg-stone-100 text-zinc-700 border border-stone-200/80' }}" title="{{ __('Ordenar por Locación') }}">
                                    <span>{{ __('Locación') }}</span>
                                    @if($activeOrdersSortField === 'location')
                                        @if($activeOrdersSortDirection === 'asc')
                                            <x-lucide-arrow-up class="w-3 h-3 text-white stroke-[2.5]" />
                                        @else
                                            <x-lucide-arrow-down class="w-3 h-3 text-white stroke-[2.5]" />
                                        @endif
                                    @endif
                                </button>

                                <button type="button" wire:click="sortByActiveOrders('core_status')" class="px-2 py-0.5 rounded text-meta font-medium transition cursor-pointer flex items-center gap-1 {{ $activeOrdersSortField === 'core_status' ? 'bg-zinc-900 text-white font-semibold shadow-2xs' : 'bg-white hover:bg-stone-100 text-zinc-700 border border-stone-200/80' }}" title="{{ __('Ordenar por Estado') }}">
                                    <span>{{ __('Estado') }}</span>
                                    @if($activeOrdersSortField === 'core_status')
                                        @if($activeOrdersSortDirection === 'asc')
                                            <x-lucide-arrow-up class="w-3 h-3 text-white stroke-[2.5]" />
                                        @else
                                            <x-lucide-arrow-down class="w-3 h-3 text-white stroke-[2.5]" />
                                        @endif
                                    @endif
                                </button>

                                <button type="button" wire:click="sortByActiveOrders('task')" class="px-2 py-0.5 rounded text-meta font-medium transition cursor-pointer flex items-center gap-1 {{ $activeOrdersSortField === 'task' ? 'bg-zinc-900 text-white font-semibold shadow-2xs' : 'bg-white hover:bg-stone-100 text-zinc-700 border border-stone-200/80' }}" title="{{ __('Ordenar por Tarea') }}">
                                    <span>{{ __('Tarea') }}</span>
                                    @if($activeOrdersSortField === 'task')
                                        @if($activeOrdersSortDirection === 'asc')
                                            <x-lucide-arrow-up class="w-3 h-3 text-white stroke-[2.5]" />
                                        @else
                                            <x-lucide-arrow-down class="w-3 h-3 text-white stroke-[2.5]" />
                                        @endif
                                    @endif
                                </button>
                            </div>
                        @endif

                        <div class="divide-y divide-zinc-200/60 border-t border-b border-zinc-200/60">
                            @php $previousGroup = null; @endphp
                            @forelse($sortedActiveOrders as $order)
                                @php
                                    $currentGroup = match($activeOrdersSortField) {
                                        'designer' => $order->designer?->name ?: __('Sin Diseñador Asignado'),
                                        'location' => $order->location_name ?: ($order->clientLocation?->name ?: __('Sin Locación')),
                                        'core_status' => $order->core_status?->label() ?: __('Sin Estado'),
                                        default => null,
                                    };
                                @endphp

                                @if($currentGroup !== null && $currentGroup !== $previousGroup)
                                    @php 
                                        $previousGroup = $currentGroup; 
                                        $groupCount = match($activeOrdersSortField) {
                                            'designer' => $sortedActiveOrders->filter(fn($o) => ($o->designer?->name ?: __('Sin Diseñador Asignado')) === $currentGroup)->count(),
                                            'location' => $sortedActiveOrders->filter(fn($o) => ($o->location_name ?: ($o->clientLocation?->name ?: __('Sin Locación'))) === $currentGroup)->count(),
                                            'core_status' => $sortedActiveOrders->filter(fn($o) => ($o->core_status?->label() ?: __('Sin Estado')) === $currentGroup)->count(),
                                            default => 0,
                                        };
                                    @endphp
                                    <div class="px-3 py-1.5 bg-[#f7f7f5] text-meta font-semibold text-zinc-600 flex items-center justify-between border-y border-zinc-200/80 first:border-t-0 select-none">
                                        <div class="flex items-center gap-1.5">
                                            @if($activeOrdersSortField === 'designer')
                                                <x-lucide-user class="w-3.5 h-3.5 text-ink-2 shrink-0" />
                                            @elseif($activeOrdersSortField === 'location')
                                                <x-lucide-map-pin class="w-3.5 h-3.5 text-pin shrink-0" />
                                            @elseif($activeOrdersSortField === 'core_status')
                                                <x-lucide-layers class="w-3.5 h-3.5 text-ink-2 shrink-0" />
                                            @endif
                                            <span>{{ $currentGroup }}</span>
                                        </div>
                                        <span class="text-meta font-mono text-zinc-400 font-semibold">({{ $groupCount }})</span>
                                    </div>
                                @endif

                                <div 
                                    wire:click="$dispatch('open-order-detail', { orderId: {{ $order->id }} })"
                                    class="py-3 px-1 hover:bg-zinc-50 flex items-center justify-between text-xs cursor-pointer transition group"
                                >
                                    <div class="min-w-0 pr-2 space-y-1">
                                        <div class="font-semibold text-zinc-900 flex items-center gap-2 truncate">
                                            @if($order->wo_number)
                                                <x-wo-badge :number="$order->wo_number" variant="dark" />
                                            @endif
                                            @if($order->designer)
                                                <span class="px-1.5 py-0.2 rounded text-meta border font-medium shrink-0 {{ $order->getDesignerBadgeStyle() }}" style="{{ $order->getDesignerBadgeInlineStyle() }}">
                                                    {{ $order->designer->name }}
                                                </span>
                                            @endif
                                            @php
                                                $locName = $order->location_name ?: $order->clientLocation?->name;
                                                $cleanTask = $order->clean_task_name;
                                                $showLocBadge = $locName && mb_strtolower(trim($locName), 'UTF-8') !== mb_strtolower(trim($cleanTask), 'UTF-8');
                                            @endphp
                                            @if($showLocBadge)
                                                <span class="inline-flex items-center gap-1 font-semibold text-zinc-700 bg-zinc-100 px-2 py-0.5 rounded text-meta shrink-0" title="{{ __('Locación de la compañía') }}">
                                                    <x-lucide-map-pin class="w-3 h-3 text-pin shrink-0" />
                                                    <span>{{ $locName }}</span>
                                                </span>
                                            @endif
                                            <span class="group-hover:text-ink-2 transition truncate">{{ $cleanTask }}</span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-1.5 shrink-0">
                                        @if($order->substatus)
                                            <span class="px-2 py-0.5 rounded text-meta font-medium border shrink-0 whitespace-nowrap {{ $order->substatus->badgeStyle() }}" style="{{ $order->substatus->getInlineBadgeStyle() }}">
                                                {{ $order->substatus->label() }}
                                            </span>
                                        @endif
                                        @if($order->core_status)
                                            <span class="px-2 py-0.5 rounded text-meta font-semibold border shrink-0 {{ $order->core_status->badgeStyle() }}" style="{{ $order->core_status->badgeInlineStyle() }}">
                                                {{ $order->core_status->label() }}
                                            </span>
                                        @endif
                                        <x-lucide-panel-right class="w-4 h-4 text-zinc-400 group-hover:text-zinc-700 transition shrink-0 ml-1" />
                                    </div>
                                </div>
                            @empty
                                <p class="text-xs text-zinc-400 italic py-4 text-center">{{ __('No hay proyectos activos registrados para este cliente.') }}</p>
                            @endforelse
                        </div>
                    </div>
                @endif

                {{-- TAB 4: Archivados --}}
                @if($activeTab === 'archived' && $currentClient)
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xs font-semibold text-zinc-900 flex items-center gap-1.5">
                                <x-lucide-archive class="w-3.5 h-3.5 text-zinc-500" />
                                <span>{{ __('Órdenes Archivadas') }} ({{ $currentClient->archivedOrders->count() }})</span>
                            </h3>
                        </div>

                        <div class="divide-y divide-zinc-200/60 border-t border-b border-zinc-200/60">
                            @forelse($currentClient->archivedOrders as $order)
                                <div 
                                    wire:click="$dispatch('open-order-detail', { orderId: {{ $order->id }} })"
                                    class="py-3 px-1 hover:bg-zinc-50 flex items-center justify-between text-xs cursor-pointer transition group"
                                >
                                    <div class="min-w-0 pr-2 space-y-1">
                                        <div class="font-semibold text-zinc-900 flex items-center gap-2 truncate">
                                            @if($order->wo_number)
                                                <x-wo-badge :number="$order->wo_number" variant="dark" />
                                            @endif
                                            @if($order->designer)
                                                <span class="px-1.5 py-0.2 rounded text-meta border font-medium shrink-0 {{ $order->getDesignerBadgeStyle() }}" style="{{ $order->getDesignerBadgeInlineStyle() }}">
                                                    {{ $order->designer->name }}
                                                </span>
                                            @endif
                                            @php
                                                $locName = $order->location_name ?: $order->clientLocation?->name;
                                                $cleanTask = $order->clean_task_name;
                                                $showLocBadge = $locName && mb_strtolower(trim($locName), 'UTF-8') !== mb_strtolower(trim($cleanTask), 'UTF-8');
                                            @endphp
                                            @if($showLocBadge)
                                                <span class="inline-flex items-center gap-1 font-semibold text-zinc-700 bg-zinc-100 px-2 py-0.5 rounded text-meta shrink-0" title="{{ __('Locación de la compañía') }}">
                                                    <x-lucide-map-pin class="w-3 h-3 text-pin shrink-0" />
                                                    <span>{{ $locName }}</span>
                                                </span>
                                            @endif
                                            <span class="group-hover:text-ink-2 transition truncate text-zinc-600">{{ $cleanTask }}</span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-1.5 shrink-0">
                                        @if($order->substatus)
                                            <span class="px-2 py-0.5 rounded text-meta font-medium border shrink-0 whitespace-nowrap {{ $order->substatus->badgeStyle() }}" style="{{ $order->substatus->getInlineBadgeStyle() }}">
                                                {{ $order->substatus->label() }}
                                            </span>
                                        @endif
                                        <span class="px-2 py-0.5 rounded text-meta font-semibold border shrink-0 bg-stone-100 text-stone-600 border-stone-200">
                                            {{ __('Archivado') }}
                                        </span>
                                        <x-lucide-panel-right class="w-4 h-4 text-zinc-400 group-hover:text-zinc-700 transition shrink-0 ml-1" />
                                    </div>
                                </div>
                            @empty
                                <p class="text-xs text-zinc-400 italic py-4 text-center">{{ __('No hay órdenes archivadas para este cliente.') }}</p>
                            @endforelse
                        </div>
                    </div>
                @endif

                {{-- TAB 5: Backlog --}}
                @if($activeTab === 'backlog' && $currentClient)
                    <div class="space-y-4">
                        <div class="divide-y divide-zinc-200/60 border-t border-b border-zinc-200/60">
                            @forelse($currentClient->backlogOrders as $order)
                                <div 
                                    wire:click="$dispatch('open-order-detail', { orderId: {{ $order->id }} })"
                                    class="py-3 px-1 hover:bg-zinc-50 flex items-center justify-between text-xs cursor-pointer transition group"
                                >
                                    <div class="min-w-0 pr-2 space-y-1">
                                        <div class="font-semibold text-zinc-900 flex items-center gap-2 truncate">
                                            @if($order->wo_number)
                                                <x-wo-badge :number="$order->wo_number" variant="dark" />
                                            @endif
                                            @if($order->designer)
                                                <span class="px-1.5 py-0.2 rounded text-meta border font-medium shrink-0 {{ $order->getDesignerBadgeStyle() }}" style="{{ $order->getDesignerBadgeInlineStyle() }}">
                                                    {{ $order->designer->name }}
                                                </span>
                                            @endif
                                            @php
                                                $locName = $order->location_name ?: $order->clientLocation?->name;
                                                $cleanTask = $order->clean_task_name;
                                                $showLocBadge = $locName && mb_strtolower(trim($locName), 'UTF-8') !== mb_strtolower(trim($cleanTask), 'UTF-8');
                                            @endphp
                                            @if($showLocBadge)
                                                <span class="inline-flex items-center gap-1 font-semibold text-zinc-700 bg-zinc-100 px-2 py-0.5 rounded text-meta shrink-0" title="{{ __('Locación de la compañía') }}">
                                                    <x-lucide-map-pin class="w-3 h-3 text-pin shrink-0" />
                                                    <span>{{ $locName }}</span>
                                                </span>
                                            @endif
                                            <span class="group-hover:text-ink-2 transition truncate text-zinc-600">{{ $cleanTask }}</span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-1.5 shrink-0">
                                        @if($order->substatus)
                                            <span class="px-2 py-0.5 rounded text-meta font-medium border shrink-0 whitespace-nowrap {{ $order->substatus->badgeStyle() }}" style="{{ $order->substatus->getInlineBadgeStyle() }}">
                                                {{ $order->substatus->label() }}
                                            </span>
                                        @endif
                                        <span class="px-2 py-0.5 rounded text-meta font-semibold border shrink-0 bg-sunken text-ink-2 border-line">
                                            {{ __('Backlog') }}
                                        </span>
                                        <x-lucide-panel-right class="w-4 h-4 text-zinc-400 group-hover:text-zinc-700 transition shrink-0 ml-1" />
                                    </div>
                                </div>
                            @empty
                                <p class="text-xs text-zinc-400 italic py-4 text-center">{{ __('No hay órdenes en backlog para este cliente.') }}</p>
                            @endforelse
                        </div>
                    </div>
                @endif
            </div>

            {{-- Panel Footer --}}
            <div class="px-6 py-3 border-t border-line bg-surface flex items-center justify-between shrink-0">
                <div>
                    @if($clientId)
                        <button
                            type="button"
                            wire:click="deleteClient"
                            wire:confirm="{{ __('¿Eliminar la compañía :name? Se moverá a la papelera.', ['name' => $name]) }}"
                            class="ui-btn ui-btn-danger"
                        >
                            <x-lucide-trash-2 />
                            {{ __('Eliminar compañía') }}
                        </button>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" @click="confirmClose(() => $wire.close())" class="ui-btn ui-btn-secondary">{{ __('Cancelar') }}</button>
                    <button type="button" @click="confirmSave()" :disabled="!isDirty()" class="ui-btn ui-btn-primary">
                        {{ __('Guardar compañía') }}
                    </button>
                </div>
            </div>
        @else
            @include('livewire.clients.partials.client-flyout-skeleton')
        @endif
    </div>
</div>



