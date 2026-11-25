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
            diff(@js(__('Company name')), before.name, now.name);
            diff(@js(__('Website')), before.website, now.website);
            const phoneText = (list) => (list || []).filter(p => p.phone).map(p => (p.label ? p.label + ': ' : '') + p.phone).join(', ');
            diff(@js(__('Company phones')), phoneText(before.phones), phoneText(now.phones));
            const byId = (list) => Object.fromEntries((list || []).filter(i => i.id).map(i => [i.id, i]));
            const locBefore = byId(before.locations);
            (now.locations || []).forEach((l) => {
                const label = l.name || @js(__('Location'));
                const old = l.id ? locBefore[l.id] : null;
                if (! old) {
                    if (l.name || l.address) diff(@js(__('New location')), '', [l.name, l.address].filter(Boolean).join(' · '));
                    return;
                }
                diff(@js(__('Location name')), old.name, l.name);
                diff(@js(__('Address')) + ' (' + label + ')', old.address, l.address);
                diff(@js(__('Phone')) + ' (' + label + ')', old.phone, l.phone);
                diff(@js(__('Email')) + ' (' + label + ')', old.email, l.email);
                diff(@js(__('Account Rep')) + ' (' + label + ')', old.manager_name, l.manager_name);
            });
            const conBefore = byId(before.contacts);
            (now.contacts || []).forEach((c) => {
                const old = c.id ? conBefore[c.id] : null;
                if (! old) {
                    if (c.name) diff(@js(__('New contact')), '', [c.name, c.phone, c.email].filter(Boolean).join(' · '));
                    return;
                }
                const who = c.name || old.name;
                diff(@js(__('Contact name')), old.name, c.name);
                diff(@js(__('Phone')) + ' (' + who + ')', old.phone, c.phone);
                diff(@js(__('Email')) + ' (' + who + ')', old.email, c.email);
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
                    title: @js(__('Save changes to this company?')),
                    description: @js(__('You have unsaved changes to this company.')),
                    cancelText: @js(__('Cancel')),
                    discardText: @js(__('Don\'t save')),
                    saveText: @js(__('Save')),
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
                        {{ $clientId ? ($name ?: __('Company details')) : __('New company') }}
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
                        <p class="text-meta text-ink-3">{{ __('Fill in the details to add the new company.') }}</p>
                    @endif
                </div>
                <button type="button" @click="confirmClose(() => $wire.close())" class="ui-btn ui-btn-ghost ui-btn-icon shrink-0" title="{{ __('Close') }}" aria-label="{{ __('Close') }}">
                    <x-lucide-x />
                </button>
            </div>

            {{-- Merge Suggestions Banner --}}
            @if(!empty($mergeSuggestions))
                <div class="mx-6 mt-4 p-3 bg-warn-soft rounded-card space-y-2 text-body shrink-0">
                    <p class="font-medium text-warn">{{ __('Merge suggestion') }}</p>
                    <p class="text-meta text-ink-2">{{ __('Records with similar names were found that can be merged:') }}</p>
                    <div class="space-y-1.5 pt-1">
                        @foreach($mergeSuggestions as $suggestion)
                            <div class="flex items-center justify-between gap-2 p-2 rounded-control bg-surface border border-line">
                                <div class="flex items-center gap-1.5 min-w-0">
                                    <span class="font-medium text-ink truncate">{{ $suggestion['name'] }}</span>
                                    <span class="text-meta text-ink-3 shrink-0">{{ trans_choice('{1} :count order|[0,*] :count orders', $suggestion['orders_count'], ['count' => $suggestion['orders_count']]) }}</span>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <button
                                        type="button"
                                        x-on:click="KudosConfirmChange({ field: @js(__('Merge companies')), from: @js($suggestion['name']), to: @js($name), note: @js(__('Its orders, contacts and locations will be transferred. This can\'t be undone.')), confirmText: @js(__('Merge')) }).then(ok => ok && $wire.mergeClient({{ $suggestion['id'] }}))"
                                        class="ui-btn ui-btn-secondary ui-btn-sm"
                                    >{{ __('Merge here') }}</button>
                                    <button type="button" wire:click="dismissMerge({{ $suggestion['id'] }})" class="ui-btn ui-btn-ghost ui-btn-sm">{{ __('Dismiss') }}</button>
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
                <button type="button" wire:click="$set('activeTab', 'general')" class="{{ $flyTab($activeTab === 'general') }}">{{ __('Info') }}</button>
                <button type="button" wire:click="$set('activeTab', 'brand_kit')" class="{{ $flyTab($activeTab === 'brand_kit' || $activeTab === 'links') }}">{{ __('Brand kit') }}</button>
                @if($currentClient)
                    <button type="button" wire:click="$set('activeTab', 'projects')" class="{{ $flyTab($activeTab === 'projects') }}">{{ __('Projects') }} <span class="text-ink-3 tabular-nums">{{ $currentClient->activeOrders->count() }}</span></button>
                    <button type="button" wire:click="$set('activeTab', 'archived')" class="{{ $flyTab($activeTab === 'archived') }}">{{ __('Archived') }} <span class="text-ink-3 tabular-nums">{{ $currentClient->archivedOrders->count() }}</span></button>
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
                                    placeholder="{{ __('Company name') }}"
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
                                        {{ $mainAddress ?: __('No main address on file') }}
                                    </span>
                                </div>
                                @if(!empty($mainAddress))
                                    <button 
                                        type="button" 
                                        @click.stop.prevent="copyText()"
                                        class="px-2.5 py-1 rounded-md bg-white hover:bg-zinc-50 border border-zinc-200 text-zinc-700 hover:text-zinc-900 text-xs font-semibold transition cursor-pointer flex items-center gap-1.5 shrink-0 shadow-2xs"
                                        title="{{ __('Copy address') }}"
                                    >
                                        <template x-if="!copied">
                                            <span class="flex items-center gap-1">
                                                <x-lucide-copy class="w-3.5 h-3.5 text-zinc-500" />
                                                <span>{{ __('Copy') }}</span>
                                            </span>
                                        </template>
                                        <template x-if="copied">
                                            <span class="flex items-center gap-1 text-ink-2 font-semibold">
                                                <x-lucide-check class="w-3.5 h-3.5 text-ink-2" />
                                                <span>{{ __('Copied') }}</span>
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
                                                    <h3 class="text-xs font-semibold text-zinc-900 ">{{ __('Tracking portal (QR)') }}</h3>
                                                    <span class="px-1.5 py-0.2 rounded bg-sunken text-ink-2 text-meta font-semibold border border-line">{{ __('Auto sign-in') }}</span>
                                                </div>
                                                <p class="text-meta text-zinc-500 truncate">{{ __('Clients follow their orders live, no login needed') }}</p>
                                            </div>
                                        </div>
                                        <a 
                                            href="{{ $currentClient->portal_url }}" 
                                            target="_blank" 
                                            class="px-2.5 py-1 rounded-md bg-white hover:bg-zinc-50 border border-zinc-200 text-zinc-700 hover:text-zinc-900 text-xs font-semibold transition flex items-center gap-1.5 shadow-2xs shrink-0 cursor-pointer"
                                            title="{{ __('Open the client portal in a new tab') }}"
                                        >
                                            <x-lucide-external-link class="w-3.5 h-3.5 text-zinc-400" />
                                            <span>{{ __('View portal') }}</span>
                                        </a>
                                    </div>

                                    <div class="flex items-center gap-3.5 bg-white p-2.5 rounded-lg border border-line">
                                        {{-- Clickable QR Code Thumbnail with Card Download --}}
                                        <div 
                                            @click="downloadCard()" 
                                            class="relative group cursor-pointer shrink-0 p-1.5 bg-white rounded-lg border-2 border-line shadow-2xs hover:shadow-md transition hover:scale-105 active:scale-95"
                                            title="{{ __('Click to download the JPG card') }}"
                                        >
                                            <div class="w-16 h-16 flex items-center justify-center">
                                                {!! $currentClientQrSvg !!}
                                            </div>
                                            <div class="absolute inset-0 bg-stone-900/70 rounded-md opacity-0 group-hover:opacity-100 transition flex flex-col items-center justify-center text-white text-meta font-semibold p-1 text-center">
                                                <x-lucide-download class="w-3.5 h-3.5 mb-0.5" />
                                                <span>{{ __('JPG card') }}</span>
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
                                                            <span>{{ __('Download JPG card') }}</span>
                                                        </span>
                                                    </template>
                                                    <template x-if="downloading">
                                                        <span class="flex items-center gap-1.5 text-white">
                                                            <x-lucide-loader-2 class="w-3.5 h-3.5 animate-spin" />
                                                            <span>{{ __('Generating JPG...') }}</span>
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
                                                            <span>{{ __('Copy') }}</span>
                                                        </span>
                                                    </template>
                                                    <template x-if="copied">
                                                        <span class="flex items-center gap-1 text-ink-2 font-semibold">
                                                            <x-lucide-check class="w-3 h-3 text-ink-2" />
                                                            <span>{{ __('Copied') }}</span>
                                                        </span>
                                                    </template>
                                                </button>

                                                <button 
                                                    type="button" 
                                                    @click="showCardPreview = !showCardPreview" 
                                                    class="px-2 py-1 rounded-md bg-zinc-100 hover:bg-zinc-200 text-zinc-700 text-xs font-medium transition cursor-pointer flex items-center gap-1"
                                                >
                                                    <x-lucide-eye class="w-3.5 h-3.5 text-zinc-500" />
                                                    <span x-text="showCardPreview ? '{{ __('Hide preview') }}' : '{{ __('View card') }}'"></span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Expandable Full Card Mockup & Details --}}
                                    <div x-show="showCardPreview" x-cloak class="pt-3 border-t border-line space-y-4">
                                        <div class="flex flex-col items-center">
                                            <p class="text-meta text-zinc-500 mb-2 font-medium">
                                                {{ __('JPG card preview (click to download):') }}
                                            </p>

                                            <div 
                                                @click="downloadCard()" 
                                                class="w-full max-w-xs bg-white rounded-3xl shadow-lg border-2 border-zinc-100 overflow-hidden cursor-pointer group hover:border-line hover:shadow-xl transition duration-300 transform hover:-translate-y-0.5 relative select-none flex flex-col justify-between"
                                                style="aspect-ratio: 9 / 16;"
                                                title="{{ __('Click to download the JPG card') }}"
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
                                                    <span class="text-xs font-semibold ">{{ __('Download JPG card') }}</span>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Information Checklist --}}
                                        <div class="bg-white/90 border border-line rounded-lg p-3 text-xs text-zinc-600 space-y-1.5">
                                            <h4 class="font-semibold text-zinc-800 flex items-center gap-1.5 text-meta">
                                                <x-lucide-info class="w-3.5 h-3.5 text-ink-2" />
                                                <span>{{ __('Portal features') }}</span>
                                            </h4>
                                            <ul class="space-y-1 text-meta text-zinc-500 list-disc list-inside">
                                                <li>{{ __('Instant access with no passwords: the QR code signs the client in.') }}</li>
                                                <li>{{ __('Read-only: clients can\'t edit anything in the system.') }}</li>
                                                <li>{{ __('Compact timeline with the key milestones of each project.') }}</li>
                                                <li>{{ __('Optimized for phones.') }}</li>
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
                                                    title="{{ __('Add another phone number') }}"
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
                                                    title="{{ __('Remove phone') }}"
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
                                        placeholder="{{ __('Company email (name@company.com)') }}"
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
                                    placeholder="{{ __('Website (https://www.company.com)') }}"
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
                                                    title="{{ __('Remove alias') }}"
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
                                            placeholder="{{ empty($aliases) ? __('Type an alias and press Enter...') : __('Add alias...') }}"
                                            class="flex-1 min-w-[140px] bg-transparent text-xs text-zinc-800 placeholder-zinc-400 focus:outline-none px-1 py-0.5"
                                        />
                                    </div>
                                    <span class="text-meta text-zinc-400 block px-0.5">
                                        {{ __('Press Enter to add each alias. They are used to link Trello cards automatically.') }}
                                    </span>
                                </div>
                            </div>

                            {{-- 5. Notes / Observaciones --}}
                            <div class="flex items-start gap-2">
                                <x-lucide-file-text class="w-4 h-4 text-zinc-400 shrink-0 mt-1" />
                                <textarea 
                                    wire:model="notes" 
                                    rows="2" 
                                    placeholder="{{ __('Internal notes, remarks or specs about the company...') }}"
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
                                                    placeholder="{{ __('Location name (e.g. Grayson)') }}"
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
                                                            placeholder="{{ __('Manager...') }}"
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
                                                            <span>{{ __('Client contacts') }}</span>
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
                                                                <span>{{ __('Create contact') }} "<strong>{{ $currentManager }}</strong>"</span>
                                                            </button>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                            <button 
                                                type="button" 
                                                x-on:click="KudosConfirmChange({ field: @js(__('Location')), from: @js(trim(($location['name'] ?? '').' '.($location['address'] ?? ''))), to: '', note: @js(__('The location is deleted right away and its orders are left without a location.')), confirmText: @js(__('Delete location')) }).then(ok => ok && $wire.removeLocation({{ $index }}))"
                                                class="text-meta text-zinc-400 hover:text-danger transition cursor-pointer flex items-center gap-1 shrink-0"
                                            >
                                                <x-lucide-trash-2 class="w-3.5 h-3.5" />
                                                <span>{{ __('Delete') }}</span>
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
                                                    placeholder="{{ __('Location address') }}"
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
                                                    placeholder="{{ __('Location phone (e.g. (770) 864-9359)') }}"
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
                                                    placeholder="{{ __('Location email (e.g. name@company.com)') }}"
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
                                                    placeholder="{{ __('Notes / technical specs (sizes, delivery hours, remarks...)') }}"
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
                                        <span>{{ __('Add location') }}</span>
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
                                            {{ __('Contacts') }} ({{ count($contacts) }})
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <button 
                                            type="button" 
                                            wire:click.stop="addContact; openContacts = true"
                                            class="inline-flex items-center gap-1 text-xs text-ink-2 font-semibold hover:text-ink-2 transition cursor-pointer"
                                        >
                                            <x-lucide-plus class="w-3.5 h-3.5" />
                                            <span>{{ __('Add contact') }}</span>
                                        </button>
                                        <button type="button" class="text-zinc-400 hover:text-zinc-700 p-0.5 flex items-center gap-1 text-meta font-medium">
                                            <span x-text="openContacts ? '{{ __('Show less') }}' : '{{ __('Show more') }}'"></span>
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
                                                            {{ __('Possible duplicate contact:') }} <strong>"{{ $dupWarning }}"</strong>!
                                                        </span>
                                                    </div>
                                                    <div class="flex items-center gap-1.5 shrink-0">
                                                        <button 
                                                            type="button" 
                                                            x-on:click="KudosConfirmChange({ field: @js(__('Merge contacts')), from: @js($contact['name'] ?? ''), to: @js($dupWarning), confirmText: @js(__('Merge')) }).then(ok => ok && $wire.confirmMergeContact({{ $index }}, @js($dupWarning)))"
                                                            class="text-meta font-semibold text-ink-2 bg-surface border border-line hover:bg-sunken px-1.5 py-0.5 rounded transition cursor-pointer"
                                                        >
                                                            {{ __('Confirm') }}
                                                        </button>
                                                        <button 
                                                            type="button" 
                                                            wire:click="dismissDuplicateContact({{ $index }})"
                                                            class="text-meta text-ink-2 hover:text-ink-2 font-medium cursor-pointer"
                                                        >
                                                            {{ __('Dismiss') }}
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
                                                        placeholder="{{ __('Contact name') }}"
                                                        :size="Math.max(12, ({{ json_encode($contact['name'] ?? '') }} || 'NOMBRE DEL CONTACTO').length + 1)"
                                                        class="bg-transparent hover:bg-zinc-100/70 focus:bg-white border border-transparent hover:border-zinc-200/60 focus:border-zinc-300 focus:ring-2 focus:ring-zinc-900/5 rounded-md -mx-1 px-1 py-0.5 text-xs font-semibold text-zinc-900 focus:outline-none transition shrink-0 max-w-full"
                                                    />
                                                    <label class="inline-flex items-center gap-1 text-meta text-zinc-400 hover:text-zinc-600 cursor-pointer select-none shrink-0" title="{{ __('Mark as primary contact') }}">
                                                        <input 
                                                            type="checkbox" 
                                                            wire:model="contacts.{{ $index }}.is_primary"
                                                            class="rounded border-zinc-300 text-ink-2 focus:ring-ink/20 w-3 h-3 opacity-70 transition cursor-pointer"
                                                        />
                                                        <span class="font-normal">{{ __('Primary') }}</span>
                                                    </label>
                                                </div>
                                                @if($index > 0)
                                                    <button 
                                                        type="button" 
                                                        wire:click="removeContact({{ $index }})"
                                                        class="text-meta text-zinc-400 hover:text-danger transition cursor-pointer flex items-center gap-1 shrink-0"
                                                    >
                                                        <x-lucide-trash-2 class="w-3.5 h-3.5" />
                                                        <span>{{ __('Delete') }}</span>
                                                    </button>
                                                @endif
                                            </div>

                                            {{-- Compact Notion-Style Fields Grid (Department, Phone, Email) --}}
                                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                                <input 
                                                    type="text" 
                                                    wire:model="contacts.{{ $index }}.department" 
                                                    placeholder="{{ __('Department / role (e.g. Purchasing)') }}"
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
                                                    placeholder="{{ __('Email') }}"
                                                    class="w-full bg-transparent hover:bg-zinc-100/60 focus:bg-white border border-transparent focus:border-zinc-300 focus:ring-2 focus:ring-zinc-900/5 rounded-md px-2 py-1 text-xs text-zinc-700 focus:outline-none transition"
                                                />
                                            </div>

                                            {{-- Channel & Language Preferences --}}
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-0.5">
                                                <div class="flex items-center gap-1.5 text-meta text-zinc-500">
                                                    <span class="shrink-0 font-medium text-meta text-zinc-400 ">{{ __('Channel:') }}</span>
                                                    <select 
                                                        wire:model="contacts.{{ $index }}.preferred_channel"
                                                        class="bg-transparent hover:bg-zinc-100/60 focus:bg-white border border-transparent focus:border-zinc-300 rounded px-1.5 py-0.5 text-xs text-zinc-700 focus:outline-none transition w-full"
                                                    >
                                                        <option value="any">{{ __('Any') }}</option>
                                                        <option value="whatsapp">WhatsApp</option>
                                                        <option value="call">{{ __('Call') }}</option>
                                                        <option value="email">Email</option>
                                                    </select>
                                                </div>
                                                <div class="flex items-center gap-1.5 text-meta text-zinc-500">
                                                    <span class="shrink-0 font-medium text-meta text-zinc-400 ">{{ __('Language:') }}</span>
                                                    <select 
                                                        wire:model="contacts.{{ $index }}.preferred_language"
                                                        class="bg-transparent hover:bg-zinc-100/60 focus:bg-white border border-transparent focus:border-zinc-300 rounded px-1.5 py-0.5 text-xs text-zinc-700 focus:outline-none transition w-full"
                                                    >
                                                        <option value="es">ES - {{ __('Spanish') }}</option>
                                                        <option value="en">EN - {{ __('English') }}</option>
                                                        <option value="both">ES / EN - {{ __('Both / Bilingual') }}</option>
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
                                        <span>{{ __('Key assets and links') }}</span>
                                    </h3>
                                    <p class="text-meta text-zinc-500 mt-0.5">
                                        {{ __('Google Drive, brand book, master logos and catalogs. URLs and network paths are supported.') }}
                                    </p>
                                </div>
                            </div>

                            {{-- 4 Predefined Core Slots --}}
                            <div class="space-y-2.5">
                                {{-- 1. Google Drive / Carpeta Raíz --}}
                                <div class="flex items-center gap-2 group/slot">
                                    <span class="w-24 sm:w-28 text-meta font-semibold text-zinc-600 flex items-center gap-1.5 shrink-0">
                                        <x-lucide-hard-drive class="w-3.5 h-3.5 text-zinc-400" />
                                        <span>{{ __('Drive / Root') }}</span>
                                    </span>
                                    <input 
                                        type="text" 
                                        wire:model="brandKit.drive_url" 
                                        placeholder="{{ __('Drive link or server path') }}"
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
                                            title="{{ __('Open link in a new tab') }}"
                                        >
                                            <x-lucide-external-link class="w-3.5 h-3.5" />
                                        </a>
                                    @endif
                                    @if(!empty($driveVal))
                                        <button 
                                            type="button" 
                                            @click="copyText({{ json_encode($driveVal) }}, 'drive_slot')" 
                                            class="p-1 rounded-md text-zinc-400 hover:text-zinc-700 hover:bg-zinc-100 transition cursor-pointer shrink-0"
                                            title="{{ __('Copy') }}"
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
                                        <span>{{ __('Brand book') }}</span>
                                    </span>
                                    <input 
                                        type="text" 
                                        wire:model="brandKit.brandbook_url" 
                                        placeholder="{{ __('Figma, Canva, Drive or PDF URL...') }}"
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
                                            title="{{ __('Open link in a new tab') }}"
                                        >
                                            <x-lucide-external-link class="w-3.5 h-3.5" />
                                        </a>
                                    @endif
                                    @if(!empty($bbVal))
                                        <button 
                                            type="button" 
                                            @click="copyText({{ json_encode($bbVal) }}, 'bb_slot')" 
                                            class="p-1 rounded-md text-zinc-400 hover:text-zinc-700 hover:bg-zinc-100 transition cursor-pointer shrink-0"
                                            title="{{ __('Copy') }}"
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
                                        <span>{{ __('Logos / Artwork') }}</span>
                                    </span>
                                    <input 
                                        type="text" 
                                        wire:model="brandKit.logo_url" 
                                        placeholder="{{ __('Link or folder with AI/EPS/SVG vectors or PNGs...') }}"
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
                                            title="{{ __('Open link in a new tab') }}"
                                        >
                                            <x-lucide-external-link class="w-3.5 h-3.5" />
                                        </a>
                                    @endif
                                    @if(!empty($logoVal))
                                        <button 
                                            type="button" 
                                            @click="copyText({{ json_encode($logoVal) }}, 'logo_slot')" 
                                            class="p-1 rounded-md text-zinc-400 hover:text-zinc-700 hover:bg-zinc-100 transition cursor-pointer shrink-0"
                                            title="{{ __('Copy') }}"
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
                                        <span>{{ __('Catalogs') }}</span>
                                    </span>
                                    <input 
                                        type="text" 
                                        wire:model="brandKit.catalog_url" 
                                        placeholder="{{ __('Product catalog, uniforms or samples...') }}"
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
                                            title="{{ __('Open link in a new tab') }}"
                                        >
                                            <x-lucide-external-link class="w-3.5 h-3.5" />
                                        </a>
                                    @endif
                                    @if(!empty($catVal))
                                        <button 
                                            type="button" 
                                            @click="copyText({{ json_encode($catVal) }}, 'cat_slot')" 
                                            class="p-1 rounded-md text-zinc-400 hover:text-zinc-700 hover:bg-zinc-100 transition cursor-pointer shrink-0"
                                            title="{{ __('Copy') }}"
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
                                            {{ __('Other links and resources') }}
                                        </span>
                                        @foreach($brandKit['custom_links'] as $custIdx => $custLink)
                                            <div class="p-2.5 rounded-lg bg-stone-50/70 border border-stone-200/70 space-y-2">
                                                <div class="flex items-center justify-between gap-2">
                                                    <input 
                                                        type="text" 
                                                        wire:model="brandKit.custom_links.{{ $custIdx }}.label" 
                                                        placeholder="{{ __('Resource name / label') }}" 
                                                        class="flex-1 bg-transparent hover:bg-white focus:bg-white border border-transparent hover:border-zinc-200 focus:border-zinc-300 rounded px-1.5 py-0.5 text-xs font-semibold text-zinc-900 focus:outline-none transition"
                                                    />
                                                    <button 
                                                        type="button" 
                                                        wire:click="removeCustomBrandLink({{ $custIdx }})" 
                                                        class="text-zinc-400 hover:text-danger p-1 transition cursor-pointer"
                                                        title="{{ __('Remove resource') }}"
                                                    >
                                                        <x-lucide-trash-2 class="w-3.5 h-3.5" />
                                                    </button>
                                                </div>
                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                                    <div class="flex items-center gap-1 min-w-0">
                                                        <input 
                                                            type="text" 
                                                            wire:model="brandKit.custom_links.{{ $custIdx }}.url" 
                                                            placeholder="{{ __('URL or local path') }}" 
                                                            class="flex-1 bg-white border border-zinc-200/80 focus:border-zinc-300 rounded px-2 py-1 text-xs text-zinc-800 font-mono focus:outline-none transition"
                                                        />
                                                        @php
                                                            $cUrl = trim($custLink['url'] ?? '');
                                                            $isCustomUrl = Str::startsWith($cUrl, ['http://', 'https://']);
                                                        @endphp
                                                        @if($isCustomUrl)
                                                            <a href="{{ $cUrl }}" target="_blank" class="p-1 rounded text-ink-2 hover:bg-sunken shrink-0" title="{{ __('Open') }}">
                                                                <x-lucide-external-link class="w-3.5 h-3.5" />
                                                            </a>
                                                        @endif
                                                        @if(!empty($cUrl))
                                                            <button type="button" @click="copyText({{ json_encode($cUrl) }}, 'cust_{{ $custIdx }}')" class="p-1 rounded text-zinc-400 hover:text-zinc-700 hover:bg-zinc-100 shrink-0" title="{{ __('Copy') }}">
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
                                                        placeholder="{{ __('Notes / version / location') }}" 
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
                                        <span>{{ __('Add another resource or link') }}</span>
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
                                        <span>{{ __('Color palette') }}</span>
                                    </h3>
                                    <p class="text-meta text-zinc-500 mt-0.5">
                                        {{ __('The company\'s official colors. Click a swatch to change it, and copy the HEX in one click.') }}
                                    </p>
                                </div>
                                <button 
                                    type="button" 
                                    wire:click="addBrandColor" 
                                    class="inline-flex items-center gap-1 text-xs text-ink-2 hover:text-ink-2 font-semibold transition cursor-pointer"
                                >
                                    <x-lucide-plus class="w-3.5 h-3.5" />
                                    <span>{{ __('Add color') }}</span>
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
                                                title="{{ __('Click to pick a color') }}"
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
                                                placeholder="{{ __('Name (e.g. Primary, Blue)') }}" 
                                                class="flex-1 min-w-[100px] bg-white border border-zinc-200/80 focus:border-zinc-300 rounded px-2 py-1 text-xs text-zinc-800 font-medium focus:outline-none transition"
                                            />

                                            {{-- Pantone / Notes --}}
                                            <input 
                                                type="text" 
                                                wire:model="brandKit.colors.{{ $cIdx }}.notes" 
                                                placeholder="{{ __('Pantone / Vinyl / Notes') }}" 
                                                class="flex-1 min-w-[100px] bg-white border border-zinc-200/80 focus:border-zinc-300 rounded px-2 py-1 text-xs text-zinc-600 focus:outline-none transition"
                                            />

                                            {{-- Copy HEX Button --}}
                                            @if(!empty($rawHex))
                                                <button 
                                                    type="button" 
                                                    @click="copyText({{ json_encode($validHex) }}, 'col_{{ $cIdx }}')" 
                                                    class="px-2 py-1 rounded bg-zinc-100 hover:bg-zinc-200 text-zinc-700 text-meta font-semibold transition cursor-pointer flex items-center gap-1 shrink-0 border border-zinc-200/80 shadow-2xs"
                                                    title="{{ __('Copy HEX code') }}"
                                                >
                                                    <template x-if="copiedItem === 'col_{{ $cIdx }}'">
                                                        <span class="text-ink-2 font-semibold flex items-center gap-0.5">
                                                            <x-lucide-check class="w-3 h-3 text-ink-2" />
                                                            <span>{{ __('Copied') }}</span>
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
                                                title="{{ __('Remove color') }}"
                                            >
                                                <x-lucide-trash-2 class="w-3.5 h-3.5" />
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="p-3 rounded-lg bg-stone-50/60 border border-stone-200/60 text-center text-xs text-zinc-500">
                                    {{ __('No colors yet. Click "Add color" to define the brand palette.') }}
                                </div>
                            @endif
                        </div>

                        {{-- Section 3: Tipografía & Fuentes --}}
                        <div class="pt-5 border-t border-zinc-200/70 space-y-3">
                            <div class="flex items-center justify-between pb-1">
                                <div>
                                    <h3 class="text-xs font-semibold text-zinc-900 flex items-center gap-1.5">
                                        <x-lucide-type class="w-3.5 h-3.5 text-ink-2" />
                                        <span>{{ __('Typography and fonts') }}</span>
                                    </h3>
                                    <p class="text-meta text-zinc-500 mt-0.5">
                                        {{ __('Approved fonts, download links or Google Fonts, and usage notes.') }}
                                    </p>
                                </div>
                                <button 
                                    type="button" 
                                    wire:click="addBrandFont" 
                                    class="inline-flex items-center gap-1 text-xs text-ink-2 hover:text-ink-2 font-semibold transition cursor-pointer"
                                >
                                    <x-lucide-plus class="w-3.5 h-3.5" />
                                    <span>{{ __('Add font') }}</span>
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
                                                    placeholder="{{ __('Usage (e.g. Headlines, Body)') }}" 
                                                    class="w-32 sm:w-36 bg-white border border-zinc-200/80 focus:border-zinc-300 rounded px-2 py-1 text-xs text-zinc-700 font-semibold focus:outline-none transition shrink-0"
                                                />

                                                {{-- Font Name --}}
                                                <div class="flex-1 flex items-center gap-1 min-w-0">
                                                    <input 
                                                        type="text" 
                                                        wire:model="brandKit.fonts.{{ $fIdx }}.name" 
                                                        placeholder="{{ __('Font name (e.g. Montserrat Black)') }}" 
                                                        class="flex-1 bg-white border border-zinc-200/80 focus:border-zinc-300 rounded px-2 py-1 text-xs text-zinc-900 font-semibold focus:outline-none transition min-w-0"
                                                    />
                                                    @if(!empty($fName))
                                                        <button 
                                                            type="button" 
                                                            @click="copyText({{ json_encode($fName) }}, 'font_{{ $fIdx }}')" 
                                                            class="p-1 rounded text-zinc-400 hover:text-zinc-700 hover:bg-zinc-100 transition cursor-pointer shrink-0"
                                                            title="{{ __('Copy font name') }}"
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
                                                    title="{{ __('Remove font') }}"
                                                >
                                                    <x-lucide-trash-2 class="w-3.5 h-3.5" />
                                                </button>
                                            </div>

                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                                <div class="flex items-center gap-1 min-w-0">
                                                    <input 
                                                        type="text" 
                                                        wire:model="brandKit.fonts.{{ $fIdx }}.url" 
                                                        placeholder="{{ __('Download URL or Google Fonts (optional)') }}" 
                                                        class="flex-1 bg-white border border-zinc-200/80 focus:border-zinc-300 rounded px-2 py-1 text-xs text-zinc-700 font-mono focus:outline-none transition min-w-0"
                                                    />
                                                    @if($isFontUrl)
                                                        <a href="{{ $fUrl }}" target="_blank" class="p-1 rounded text-ink-2 hover:bg-sunken shrink-0" title="{{ __('Open link') }}">
                                                            <x-lucide-external-link class="w-3.5 h-3.5" />
                                                        </a>
                                                    @endif
                                                </div>
                                                <input 
                                                    type="text" 
                                                    wire:model="brandKit.fonts.{{ $fIdx }}.notes" 
                                                    placeholder="{{ __('Notes (e.g. Caps only, tracking +50)') }}" 
                                                    class="w-full bg-white border border-zinc-200/80 focus:border-zinc-300 rounded px-2 py-1 text-xs text-zinc-600 focus:outline-none transition"
                                                />
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="p-3 rounded-lg bg-stone-50/60 border border-stone-200/60 text-center text-xs text-zinc-500">
                                    {{ __('No fonts yet. Click "Add font" to list the fonts the company uses.') }}
                                </div>
                            @endif
                        </div>

                        {{-- Section 4: Criterios de Diseño & Preferencias del Cliente --}}
                        <div class="pt-5 border-t border-zinc-200/70 space-y-4">
                            <div>
                                <h3 class="text-xs font-semibold text-zinc-900 flex items-center gap-1.5">
                                    <x-lucide-message-square-quote class="w-3.5 h-3.5 text-ink-2" />
                                    <span>{{ __('Design criteria and preferences') }}</span>
                                </h3>
                                <p class="text-meta text-zinc-500 mt-0.5">
                                    {{ __('Brand guidelines every designer should follow when preparing this company\'s artwork.') }}
                                </p>
                            </div>

                            <div class="space-y-3.5">
                                {{-- 1. Do's & Don'ts --}}
                                <div class="space-y-1">
                                    <label class="text-meta font-semibold text-zinc-700 flex items-center gap-1">
                                        <x-lucide-shield-alert class="w-3.5 h-3.5 text-zinc-400" />
                                        <span>{{ __('Design rules (do\'s and don\'ts)') }}</span>
                                    </label>
                                    <textarea 
                                        wire:model="brandKit.guidelines" 
                                        rows="2" 
                                        placeholder="{{ __('E.g. Always use the horizontal logo on uniforms. Never alter brand colors. Leave a 1-inch margin on signs...') }}" 
                                        class="w-full bg-stone-50/60 hover:bg-white focus:bg-white border border-stone-200/80 focus:border-zinc-300 rounded-lg p-2.5 text-xs text-zinc-800 focus:outline-none transition"
                                    ></textarea>
                                </div>

                                {{-- 2. Client Feedback / Quirks --}}
                                <div class="space-y-1">
                                    <label class="text-meta font-semibold text-zinc-700 flex items-center gap-1">
                                        <x-lucide-message-circle class="w-3.5 h-3.5 text-zinc-400" />
                                        <span>{{ __('Company comments and preferences') }}</span>
                                    </label>
                                    <textarea 
                                        wire:model="brandKit.client_notes" 
                                        rows="2" 
                                        placeholder="{{ __('E.g. Prefers bold, easy-to-read type. Likes to receive 2 color options in each proposal...') }}" 
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
                                {{ __('Active projects') }} ({{ $currentClient->activeOrders->count() }})
                            </h3>
                            @if($currentClient->archivedOrders->count() > 0)
                                <button 
                                    type="button" 
                                    wire:click="$set('activeTab', 'archived')"
                                    class="text-xs text-ink-2 hover:text-ink-2 font-semibold transition flex items-center gap-1 cursor-pointer"
                                >
                                    <span>{{ __('View archived') }} ({{ $currentClient->archivedOrders->count() }})</span>
                                    <x-lucide-arrow-right class="w-3.5 h-3.5" />
                                </button>
                            @endif
                        </div>

                        @if($currentClient->activeOrders->count() > 1)
                            <div class="flex items-center gap-1.5 flex-wrap bg-stone-50/80 p-2 rounded-lg border border-stone-200/60 text-xs">
                                <span class="text-meta font-semibold text-zinc-400 mr-1">{{ __('Sort by:') }}</span>

                                <button type="button" wire:click="sortByActiveOrders('wo')" class="px-2 py-0.5 rounded text-meta font-medium transition cursor-pointer flex items-center gap-1 {{ $activeOrdersSortField === 'wo' ? 'bg-zinc-900 text-white font-semibold shadow-2xs' : 'bg-white hover:bg-stone-100 text-zinc-700 border border-stone-200/80' }}" title="{{ __('Sort by WO') }}">
                                    <span>WO</span>
                                    @if($activeOrdersSortField === 'wo')
                                        @if($activeOrdersSortDirection === 'asc')
                                            <x-lucide-arrow-up class="w-3 h-3 text-white stroke-[2.5]" />
                                        @else
                                            <x-lucide-arrow-down class="w-3 h-3 text-white stroke-[2.5]" />
                                        @endif
                                    @endif
                                </button>

                                <button type="button" wire:click="sortByActiveOrders('designer')" class="px-2 py-0.5 rounded text-meta font-medium transition cursor-pointer flex items-center gap-1 {{ $activeOrdersSortField === 'designer' ? 'bg-zinc-900 text-white font-semibold shadow-2xs' : 'bg-white hover:bg-stone-100 text-zinc-700 border border-stone-200/80' }}" title="{{ __('Sort by Designer') }}">
                                    <span>{{ __('Designer') }}</span>
                                    @if($activeOrdersSortField === 'designer')
                                        @if($activeOrdersSortDirection === 'asc')
                                            <x-lucide-arrow-up class="w-3 h-3 text-white stroke-[2.5]" />
                                        @else
                                            <x-lucide-arrow-down class="w-3 h-3 text-white stroke-[2.5]" />
                                        @endif
                                    @endif
                                </button>

                                <button type="button" wire:click="sortByActiveOrders('location')" class="px-2 py-0.5 rounded text-meta font-medium transition cursor-pointer flex items-center gap-1 {{ $activeOrdersSortField === 'location' ? 'bg-zinc-900 text-white font-semibold shadow-2xs' : 'bg-white hover:bg-stone-100 text-zinc-700 border border-stone-200/80' }}" title="{{ __('Sort by Location') }}">
                                    <span>{{ __('Location') }}</span>
                                    @if($activeOrdersSortField === 'location')
                                        @if($activeOrdersSortDirection === 'asc')
                                            <x-lucide-arrow-up class="w-3 h-3 text-white stroke-[2.5]" />
                                        @else
                                            <x-lucide-arrow-down class="w-3 h-3 text-white stroke-[2.5]" />
                                        @endif
                                    @endif
                                </button>

                                <button type="button" wire:click="sortByActiveOrders('core_status')" class="px-2 py-0.5 rounded text-meta font-medium transition cursor-pointer flex items-center gap-1 {{ $activeOrdersSortField === 'core_status' ? 'bg-zinc-900 text-white font-semibold shadow-2xs' : 'bg-white hover:bg-stone-100 text-zinc-700 border border-stone-200/80' }}" title="{{ __('Sort by Status') }}">
                                    <span>{{ __('Status') }}</span>
                                    @if($activeOrdersSortField === 'core_status')
                                        @if($activeOrdersSortDirection === 'asc')
                                            <x-lucide-arrow-up class="w-3 h-3 text-white stroke-[2.5]" />
                                        @else
                                            <x-lucide-arrow-down class="w-3 h-3 text-white stroke-[2.5]" />
                                        @endif
                                    @endif
                                </button>

                                <button type="button" wire:click="sortByActiveOrders('task')" class="px-2 py-0.5 rounded text-meta font-medium transition cursor-pointer flex items-center gap-1 {{ $activeOrdersSortField === 'task' ? 'bg-zinc-900 text-white font-semibold shadow-2xs' : 'bg-white hover:bg-stone-100 text-zinc-700 border border-stone-200/80' }}" title="{{ __('Sort by Task') }}">
                                    <span>{{ __('Task') }}</span>
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
                                        'designer' => $order->designer?->name ?: __('No Designer Assigned'),
                                        'location' => $order->location_name ?: ($order->clientLocation?->name ?: __('No Location')),
                                        'core_status' => $order->core_status?->label() ?: __('No Status'),
                                        default => null,
                                    };
                                @endphp

                                @if($currentGroup !== null && $currentGroup !== $previousGroup)
                                    @php 
                                        $previousGroup = $currentGroup; 
                                        $groupCount = match($activeOrdersSortField) {
                                            'designer' => $sortedActiveOrders->filter(fn($o) => ($o->designer?->name ?: __('No Designer Assigned')) === $currentGroup)->count(),
                                            'location' => $sortedActiveOrders->filter(fn($o) => ($o->location_name ?: ($o->clientLocation?->name ?: __('No Location'))) === $currentGroup)->count(),
                                            'core_status' => $sortedActiveOrders->filter(fn($o) => ($o->core_status?->label() ?: __('No Status')) === $currentGroup)->count(),
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
                                                <span class="inline-flex items-center gap-1 font-semibold text-zinc-700 bg-zinc-100 px-2 py-0.5 rounded text-meta shrink-0" title="{{ __('Company location') }}">
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
                                <p class="text-xs text-zinc-400 italic py-4 text-center">{{ __('No active projects for this company.') }}</p>
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
                                <span>{{ __('Archived Orders') }} ({{ $currentClient->archivedOrders->count() }})</span>
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
                                                <span class="inline-flex items-center gap-1 font-semibold text-zinc-700 bg-zinc-100 px-2 py-0.5 rounded text-meta shrink-0" title="{{ __('Company location') }}">
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
                                            {{ __('Archived') }}
                                        </span>
                                        <x-lucide-panel-right class="w-4 h-4 text-zinc-400 group-hover:text-zinc-700 transition shrink-0 ml-1" />
                                    </div>
                                </div>
                            @empty
                                <p class="text-xs text-zinc-400 italic py-4 text-center">{{ __('No archived orders for this company.') }}</p>
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
                                                <span class="inline-flex items-center gap-1 font-semibold text-zinc-700 bg-zinc-100 px-2 py-0.5 rounded text-meta shrink-0" title="{{ __('Company location') }}">
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
                                <p class="text-xs text-zinc-400 italic py-4 text-center">{{ __('No backlog orders for this company.') }}</p>
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
                            wire:confirm="{{ __('Delete :name? The company will be moved to the trash.', ['name' => $name]) }}"
                            class="ui-btn ui-btn-danger"
                        >
                            <x-lucide-trash-2 />
                            {{ __('Delete company') }}
                        </button>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" @click="confirmClose(() => $wire.close())" class="ui-btn ui-btn-secondary">{{ __('Cancel') }}</button>
                    <button type="button" @click="confirmSave()" :disabled="!isDirty()" class="ui-btn ui-btn-primary">
                        {{ __('Save company') }}
                    </button>
                </div>
            </div>
        @else
            @include('livewire.clients.partials.client-flyout-skeleton')
        @endif
    </div>
</div>



