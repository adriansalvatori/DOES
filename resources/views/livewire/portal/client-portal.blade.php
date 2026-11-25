<div class="min-h-screen flex flex-col justify-between max-w-lg mx-auto w-full px-4 pt-6 pb-2 sm:px-6">

    {{-- TOP BRANDING & CLIENT HEADER --}}
    <header class="w-full space-y-4 shrink-0">
        {{-- Kudos Logo --}}
        <div class="flex justify-center">
            <img src="{{ asset('images/logo-kudos.svg') }}" alt="Kudos Print Media" class="h-14 sm:h-16 w-auto drop-">
        </div>

        {{-- Client Title Card --}}
        <div class="bg-surface rounded-panel p-4 sm:p-5  border border-line text-center space-y-2 relative overflow-hidden">
            {{-- Accent Top Bar --}}
            <div class="absolute top-0 inset-x-0 h-1 bg-gold"></div>

            <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-ok-soft text-ok text-meta font-medium">
                <span class="w-1.5 h-1.5 rounded-full bg-ok"></span>
                <span>{{ __('Live tracking') }}</span>
            </div>

            <div class="space-y-1">
                <h1 class="text-title font-semibold text-ink">
                    {{ \App\Support\TitleCase::from($client->name) }}
                </h1>
                {{-- Yellow Underline Accent --}}
                
                <p class="text-body font-medium text-ink-2">
                    {{ __('You\'re part of the Kudos family!') }}
                </p>
            </div>

            <p class="text-body text-ink-3 max-w-xs mx-auto">
                {{ __('Follow the status and progress of all your active orders in real time.') }}
            </p>

            @if($client->locations->count() > 0 && $client->locations->first()->address)
                <div class="pt-2 border-t border-line flex items-center justify-center gap-1.5 text-body text-ink-3 truncate">
                    <x-lucide-map-pin class="w-3.5 h-3.5 text-ink-3 shrink-0" />
                    <span class="truncate">{{ $client->locations->first()->address }}</span>
                </div>
            @endif
        </div>
    </header>

    {{-- MAIN CONTENT: WORK ORDERS, PHASES AND DELIVERABLES --}}
    <main class="flex-1 my-5 space-y-4 min-h-0">
        <div class="space-y-3.5">
            @forelse($workOrders as $workOrder)
                @php
                    $groups = $workOrder->deliverables->groupBy(fn ($deliverable) => $deliverable->phase_id ?? 0);
                    $locationName = $workOrder->clientLocation?->name;
                @endphp
                <section wire:key="work-order-{{ $workOrder->id }}" class="bg-surface rounded-panel p-4 sm:p-5 border border-line space-y-3">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 space-y-1">
                            <div class="flex items-center gap-2">
                                <x-wo-badge :number="$workOrder->number" />
                                @unless($workOrder->number)
                                    <span class="text-meta text-ink-3">{{ __('No work order number yet') }}</span>
                                @endunless
                            </div>
                            @if($workOrder->description)
                                <h2 class="text-row font-semibold text-ink leading-snug break-words">{{ $workOrder->description }}</h2>
                            @endif
                            @if($locationName)
                                <x-orders.location :location="\App\Support\TitleCase::from($locationName)" />
                            @endif
                        </div>
                        @if($workOrder->promised_date)
                            <div class="text-right shrink-0">
                                <p class="text-meta text-ink-3">{{ __('Promised for') }}</p>
                                <p class="text-body font-medium text-ink tabular-nums">{{ $workOrder->promised_date->isoFormat('ll') }}</p>
                            </div>
                        @endif
                    </div>

                    @foreach($groups as $phaseId => $deliverables)
                        @php $phase = $phaseId ? $workOrder->phases->firstWhere('id', $phaseId) : null; @endphp
                        <div wire:key="phase-{{ $workOrder->id }}-{{ $phaseId }}" class="space-y-2">
                            @if($phase)
                                <div class="flex items-center justify-between gap-2 pt-1 border-t border-line">
                                    <h3 class="text-body font-semibold text-ink-2 pt-2">{{ $phase->name }}</h3>
                                    @if($phase->promised_date)
                                        <span class="text-meta text-ink-3 tabular-nums pt-2">{{ __('Promised for') }} {{ $phase->promised_date->isoFormat('MMM D') }}</span>
                                    @endif
                                </div>
                            @endif

                            <ul class="space-y-2">
                                @foreach($deliverables as $deliverable)
                                    @php
                                        $receipt = $deliverable->receipts->sortByDesc('received_at')->first();
                                        $needsAction = \App\Livewire\Portal\ClientPortal::needsClientAction($deliverable);
                                    @endphp
                                    <li wire:key="deliverable-{{ $deliverable->id }}" class="rounded-card border border-line bg-canvas p-3 space-y-1.5">
                                        <div class="flex items-start justify-between gap-2">
                                            <p class="text-body font-medium text-ink leading-snug break-words min-w-0">{{ $deliverable->task_name }}</p>
                                            <span class="shrink-0 px-2.5 py-0.5 rounded-full text-meta font-semibold border border-line bg-surface {{ $needsAction ? 'text-warn' : 'text-ink-2' }}">
                                                {{ \App\Livewire\Portal\ClientPortal::customerStatus($deliverable) }}
                                            </span>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-x-4 gap-y-0.5 text-meta text-ink-3">
                                            @if($deliverable->due_target)
                                                <span class="inline-flex items-center gap-1">
                                                    <x-lucide-calendar class="w-3 h-3 shrink-0" aria-hidden="true" />
                                                    {{ __('Promised for') }} <span class="tabular-nums text-ink-2">{{ $deliverable->due_target->isoFormat('ll') }}</span>
                                                </span>
                                            @endif
                                            <span class="inline-flex items-center gap-1">
                                                <x-lucide-package-check class="w-3 h-3 shrink-0" aria-hidden="true" />
                                                @if($receipt)
                                                    {{ __('Received :date', ['date' => ($receipt->received_at ?? $receipt->created_at)->isoFormat('ll')]) }}
                                                @else
                                                    {{ __('Not received yet') }}
                                                @endif
                                            </span>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </section>
            @empty
                <div class="bg-surface rounded-panel p-8 text-center border border-line space-y-3">
                    <div class="w-12 h-12 rounded-panel bg-zinc-100 text-ink-3 flex items-center justify-center mx-auto">
                        <x-lucide-folder-open class="w-6 h-6" />
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-sm font-semibold text-ink">{{ __('No orders found') }}</h3>
                        <p class="text-meta text-ink-3 max-w-xs mx-auto">{{ __('You have no active orders right now.') }}</p>
                    </div>
                </div>
            @endforelse
        </div>

        {{-- General CS Support Help Bar --}}
        @php
            $cleanGeneralCs = preg_replace('/\D/', '', $csPhone ?? '+16783580594');
            if ($cleanGeneralCs && strlen($cleanGeneralCs) === 10) {
                $cleanGeneralCs = '1'.$cleanGeneralCs;
            }
            $generalCsText = rawurlencode(__('Hello Kudos Print Media, I am :name and I have a question about my orders.', ['name' => $client->name]));
        @endphp
        <div class="p-3.5 bg-surface rounded-panel border border-line  flex items-center justify-between gap-3 text-meta">
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="w-8 h-8 rounded-card bg-sunken text-ink-2 flex items-center justify-center shrink-0">
                    <x-lucide-headphones class="w-4 h-4" />
                </div>
                <div class="min-w-0">
                    <span class="font-semibold text-ink block truncate">{{ __('Customer service') }}</span>
                    <span class="text-body text-ink-3 block truncate font-mono">{{ \App\Livewire\Portal\ClientPortal::formatDisplayPhone($csPhone) }}</span>
                </div>
            </div>
            <a 
                href="https://wa.me/{{ $cleanGeneralCs }}?text={{ $generalCsText }}" 
                target="_blank" rel="noopener"
                class="px-3 py-1.5 ui-btn ui-btn-primary flex items-center gap-1.5 shrink-0"
            >
                <x-lucide-message-circle class="w-3.5 h-3.5" />
                <span>{{ __('WhatsApp') }}</span>
            </a>
        </div>

        {{-- Kudos office --}}
        <div class="px-1 space-y-1.5 text-body text-ink-3">
            <div class="flex items-center gap-1.5 text-meta font-semibold text-ink-3">
                <x-lucide-building-2 class="w-3.5 h-3.5" />
                <span>{{ __('Kudos office') }}</span>
            </div>
            <div class="flex items-start gap-2 text-ink-2">
                <x-lucide-map-pin class="w-3.5 h-3.5 text-ink-3 shrink-0 mt-0.5" />
                <span class="leading-relaxed">{{ '5555 Oakborook Pkwy. Ste. 185 Norcross, GA 30093' }}</span>
            </div>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 pt-0.5">
                <a href="tel:+17706964048,301" class="flex items-center gap-1.5 text-ink-2 hover:text-ink transition">
                    <x-lucide-phone class="w-3.5 h-3.5 text-ink-3 shrink-0" />
                    <span class="font-mono font-medium">{{ \App\Livewire\Portal\ClientPortal::formatDisplayPhone('7706964048 Ext. 301') }}</span>
                </a>
                <a href="mailto:cs@kudosprintmedia.com" class="flex items-center gap-1.5 text-ink-2 hover:text-ink transition">
                    <x-lucide-mail class="w-3.5 h-3.5 text-ink-3 shrink-0" />
                    <span class="underline decoration-zinc-300 underline-offset-2">{{ 'cs@kudosprintmedia.com' }}</span>
                </a>
            </div>
        </div>
    </main>

    {{-- FOOTER WITH DECORATION --}}
    <footer class="w-full shrink-0 pt-4 pb-2 space-y-3 text-center">
        {{-- Decorative SVG Footer --}}
        <div class="w-full flex justify-center overflow-hidden rounded-card">
            <img src="{{ asset('images/footer-decor.svg') }}" alt="Kudos Decor" class="w-full max-h-16 object-contain opacity-90">
        </div>

        <div class="text-body text-ink-3 space-y-0.5 font-medium">
            <p>{{ __('Kudos Print Media • Client Portal') }}</p>
            <p class="text-meta text-ink-3/80">{{ __('View-only order tracking') }}</p>
        </div>
    </footer>

</div>
