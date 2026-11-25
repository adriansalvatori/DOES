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
                <span>{{ __('Seguimiento en Vivo') }}</span>
            </div>

            <div class="space-y-1">
                <h1 class="text-title font-semibold text-ink">
                    {{ $client->name }}
                </h1>
                {{-- Yellow Underline Accent --}}
                
                <p class="text-body font-medium text-ink-2">
                    {{ __('¡Eres parte de la familia Kudos!') }}
                </p>
            </div>

            <p class="text-body text-ink-3 max-w-xs mx-auto">
                {{ __('Monitorea el estado y avance de todas tus órdenes activas en tiempo real.') }}
            </p>

            @if($client->locations->count() > 0 && $client->locations->first()->address)
                <div class="pt-2 border-t border-line flex items-center justify-center gap-1.5 text-body text-ink-3 truncate">
                    <x-lucide-map-pin class="w-3.5 h-3.5 text-ink-3 shrink-0" />
                    <span class="truncate">{{ $client->locations->first()->address }}</span>
                </div>
            @endif
        </div>
    </header>

    {{-- MAIN CONTENT: SEARCH, FILTERS & ORDERS LIST --}}
    <main class="flex-1 my-5 space-y-4 min-h-0">
        {{-- Search Bar --}}
        <div class="relative">
            <x-lucide-search class="w-4 h-4 text-ink-3 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
            <input 
                type="text" 
                wire:model.live.debounce.250ms="search" 
                placeholder="{{ __('Buscar por número de WO u orden...') }}" 
                class="w-full bg-surface border border-line rounded-card pl-10 pr-4 py-2.5 text-meta text-ink placeholder:text-ink-3  focus:outline-none focus:ring-2 focus:ring-ink/10 focus:border-ink transition"
            >
            @if(!empty($search))
                <button 
                    type="button" 
                    wire:click="$set('search', '')" 
                    class="absolute right-3 top-2.5 p-0.5 text-ink-3 hover:text-ink-2 rounded-full"
                >
                    <x-lucide-x class="w-3.5 h-3.5" />
                </button>
            @endif
        </div>

        {{-- Filter Pills (Horizontal Scrolling on mobile) --}}
        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 scrollbar-none text-meta select-none">
            <button 
                type="button" 
                wire:click="setFilter('all')" 
                class="px-3 py-1.5 rounded-full font-medium transition shrink-0 cursor-pointer {{ $statusFilter === 'all' ? 'bg-ink text-white font-medium' : 'bg-surface text-ink-2 border border-line hover:bg-sunken' }}"
            >
                {{ __('Todas') }} ({{ $allCount }})
            </button>

            @if($reviewCount > 0)
                <button 
                    type="button" 
                    wire:click="setFilter('review')" 
                    class="px-3 py-1.5 rounded-full font-medium transition shrink-0 cursor-pointer flex items-center gap-1.5 {{ $statusFilter === 'review' ? 'bg-ink text-white font-medium' : 'bg-surface text-ink-2 border border-line hover:bg-sunken' }}"
                >
                    <span class="w-1.5 h-1.5 rounded-full bg-ok"></span>
                    <span>{{ __('¡Esperando tu respuesta!') }} ({{ $reviewCount }})</span>
                </button>
            @endif

            <button 
                type="button" 
                wire:click="setFilter('design')" 
                class="px-3 py-1.5 rounded-full font-medium transition shrink-0 cursor-pointer {{ $statusFilter === 'design' ? 'bg-ink text-white font-medium' : 'bg-surface text-ink-2 border border-line hover:bg-sunken' }}"
            >
                {{ __('En Diseño') }}
            </button>

            @if($productionCount > 0)
                <button 
                    type="button" 
                    wire:click="setFilter('production')" 
                    class="px-3 py-1.5 rounded-full font-medium transition shrink-0 cursor-pointer {{ $statusFilter === 'production' ? 'bg-ink text-white font-medium' : 'bg-surface text-ink-2 border border-line hover:bg-sunken' }}"
                >
                    {{ __('En Producción') }} ({{ $productionCount }})
                </button>
            @endif

            @if($holdCount > 0)
                <button 
                    type="button" 
                    wire:click="setFilter('hold')" 
                    class="px-3 py-1.5 rounded-full font-medium transition shrink-0 cursor-pointer {{ $statusFilter === 'hold' ? 'bg-ink text-white font-medium' : 'bg-surface text-ink-2 border border-line hover:bg-sunken' }}"
                >
                    {{ __('En Pausa') }} ({{ $holdCount }})
                </button>
            @endif
        </div>

        {{-- ORDERS CARDS LIST --}}
        <div class="space-y-3.5">
            @forelse($orders as $order)
                @php
                    $statusInfo = $timelineService->getCustomerStatus($order);
                    $timeline = $timelineService->getClientTimeline($order);
                    $latestMilestone = $timeline->last();
                @endphp
                <div 
                    wire:key="order-card-{{ $order->id }}"
                    wire:click="selectOrder({{ $order->id }})" 
                    class="group bg-surface rounded-panel p-4 sm:p-5 border border-line  hover:border-line-strong transition  cursor-pointer space-y-3 relative overflow-hidden"
                >
                    {{-- Status Banner Top Indicator --}}
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-1.5">
                            <span class="font-mono text-meta font-bold px-2 py-0.5 rounded-md bg-wo text-white">
                                {{ $order->wo_number ?: 'SIN WO' }}
                            </span>
                            @if($order->approved)
                                <span class="px-1.5 py-0.5 rounded bg-ok-soft text-ok text-meta font-semibold flex items-center gap-0.5">
                                    <x-lucide-check class="w-3 h-3 text-ok" />
                                    <span>{{ __('Aprobado') }}</span>
                                </span>
                            @endif
                        </div>

                        {{-- Friendly Customer Status Badge --}}
                        <div class="px-2.5 py-1 rounded-full text-meta font-semibold border border-line bg-surface text-ink-2 flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-ink-3"></span>
                            <span>{{ $statusInfo['label'] }}</span>
                        </div>
                    </div>

                    {{-- Order Title & Location --}}
                    <div>
                        <h2 class="text-sm sm:text-base font-semibold text-ink group-hover:text-ink transition leading-snug">
                            {{ $order->clean_task_name }}
                        </h2>
                        @if($order->location_text)
                            <div class="flex items-center gap-1 text-meta text-ink-3 mt-0.5">
                                <x-lucide-map-pin class="w-3 h-3 text-ink-3 shrink-0" />
                                <span class="truncate">{{ $order->location_text }}</span>
                            </div>
                        @endif
                    </div>

                    {{-- Description / Explanatory note --}}
                    <p class="text-meta text-ink-2 bg-canvas p-2.5 rounded-card border border-line leading-relaxed">
                        {{ $statusInfo['description'] }}
                    </p>

                    {{-- Latest Milestone Mini-Card --}}
                    @if($latestMilestone)
                        <div class="pt-2 border-t border-line flex items-center justify-between text-meta text-ink-3">
                            <div class="flex items-center gap-1.5 truncate min-w-0 pr-2">
                                <x-lucide-history class="w-3.5 h-3.5 text-ink-3 shrink-0" />
                                <span class="truncate text-ink-2 font-medium">
                                    {{ $latestMilestone['title'] }}
                                </span>
                            </div>
                            <span class="text-body font-mono text-ink-3 shrink-0">
                                {{ $latestMilestone['date_formatted'] }}
                            </span>
                        </div>
                    @endif

                    {{-- Tap to View Details Cue --}}
                    <div class="pt-1 flex items-center justify-between text-meta text-ink-2 font-medium">
                        <span>{{ __('Ver detalles y línea de tiempo') }}</span>
                        <x-lucide-chevron-right class="w-4 h-4 text-ink-3" />
                    </div>
                </div>
            @empty
                <div class="bg-surface rounded-panel p-8 text-center border border-line  space-y-3">
                    <div class="w-12 h-12 rounded-panel bg-zinc-100 text-ink-3 flex items-center justify-center mx-auto">
                        <x-lucide-folder-open class="w-6 h-6" />
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-sm font-semibold text-ink">{{ __('No se encontraron órdenes') }}</h3>
                        <p class="text-meta text-ink-3 max-w-xs mx-auto">
                            @if(!empty($search))
                                {{ __('No hay órdenes que coincidan con ":search". Prueba con otro término.', ['search' => $search]) }}
                            @else
                                {{ __('Actualmente no tienes órdenes activas en esta sección.') }}
                            @endif
                        </p>
                    </div>
                    @if(!empty($search) || $statusFilter !== 'all')
                        <button 
                            type="button" 
                            wire:click="$set('search', ''); $set('statusFilter', 'all');" 
                            class="px-3.5 py-1.5 rounded-control bg-sunken hover:bg-line text-ink-2 text-meta font-semibold transition cursor-pointer"
                        >
                            {{ __('Ver todas las órdenes') }}
                        </button>
                    @endif
                </div>
            @endforelse
        </div>

        {{-- General CS Support Help Bar --}}
        @php
            $cleanGeneralCs = preg_replace('/\D/', '', $csPhone ?? '+16783580594');
            if ($cleanGeneralCs && strlen($cleanGeneralCs) === 10) {
                $cleanGeneralCs = '1'.$cleanGeneralCs;
            }
            $generalCsText = rawurlencode("Hola Kudos Print Media, soy {$client->name} y tengo una consulta sobre mis órdenes.");
        @endphp
        <div class="p-3.5 bg-surface rounded-panel border border-line  flex items-center justify-between gap-3 text-meta">
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="w-8 h-8 rounded-card bg-sunken text-ink-2 flex items-center justify-center shrink-0">
                    <x-lucide-headphones class="w-4 h-4" />
                </div>
                <div class="min-w-0">
                    <span class="font-semibold text-ink block truncate">{{ __('Atención al Cliente (CS)') }}</span>
                    <span class="text-body text-ink-3 block truncate font-mono">{{ \App\Livewire\Portal\ClientPortal::formatDisplayPhone($csPhone) }}</span>
                </div>
            </div>
            <a 
                href="https://wa.me/{{ $cleanGeneralCs }}?text={{ $generalCsText }}" 
                target="_blank"
                class="px-3 py-1.5 ui-btn ui-btn-primary flex items-center gap-1.5 shrink-0"
            >
                <x-lucide-message-circle class="w-3.5 h-3.5" />
                <span>{{ __('WhatsApp') }}</span>
            </a>
        </div>
    </main>

    {{-- FOOTER WITH DECORATION --}}
    <footer class="w-full shrink-0 pt-4 pb-2 space-y-3 text-center">
        {{-- Decorative SVG Footer --}}
        <div class="w-full flex justify-center overflow-hidden rounded-card">
            <img src="{{ asset('images/footer-decor.svg') }}" alt="Kudos Decor" class="w-full max-h-16 object-contain opacity-90">
        </div>

        <div class="text-body text-ink-3 space-y-0.5 font-medium">
            <p>{{ __('Kudos Print Media • Portal de Clientes') }}</p>
            <p class="text-meta text-ink-3/80">{{ __('Solo vista y monitoreo de órdenes') }}</p>
        </div>
    </footer>

    {{-- MODAL / BOTTOM SHEET: ORDER DETAILS & CLIENT TIMELINE --}}
    @if($selectedOrder && $selectedStatus)
        <div 
            class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4"
            x-data="{ show: true }"
            x-show="show"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
        >
            {{-- Backdrop --}}
            <div 
                class="fixed inset-0 bg-zinc-900/60 backdrop-blur-xs transition-opacity"
                wire:click="closeOrder"
            ></div>

            {{-- Bottom Sheet Container --}}
            <div 
                class="relative bg-surface w-full max-w-lg rounded-t-3xl sm:rounded-panel shadow-2xl max-h-[92vh] sm:max-h-[85vh] flex flex-col z-10 border border-line overflow-hidden transform transition-all"
                x-transition:enter="transition ease-out duration-250 transform"
                x-transition:enter-start="translate-y-full sm:translate-y-4 sm:scale-95"
                x-transition:enter-end="translate-y-0 sm:scale-100"
                x-transition:leave="transition ease-in duration-200 transform"
                x-transition:leave-start="translate-y-0 sm:scale-100"
                x-transition:leave-end="translate-y-full sm:translate-y-4 sm:scale-95"
            >
                {{-- Drag indicator on mobile --}}
                <div class="w-full flex justify-center pt-3 pb-1 sm:hidden">
                    <div class="w-12 h-1.5 rounded-full bg-zinc-300"></div>
                </div>

                {{-- Header with Close Button --}}
                <div class="px-5 py-4 border-b border-line flex items-start justify-between gap-3 bg-surface shrink-0">
                    <div class="space-y-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-meta font-bold px-2 py-0.5 rounded-md bg-wo text-white">
                                {{ $selectedOrder->wo_number ?: 'SIN WO' }}
                            </span>
                            <span class="px-2.5 py-0.5 rounded-full text-meta font-medium border border-line bg-surface text-ink-2">
                                {{ $selectedStatus['label'] }}
                            </span>
                        </div>
                        <h2 class="text-base sm:text-lg font-semibold text-ink leading-snug break-words">
                            {{ $selectedOrder->clean_task_name }}
                        </h2>
                    </div>

                    <button 
                        type="button" 
                        wire:click="closeOrder" 
                        class="p-2 rounded-full hover:bg-zinc-100 text-ink-3 hover:text-ink-2 transition cursor-pointer shrink-0"
                    >
                        <x-lucide-x class="w-5 h-5" />
                    </button>
                </div>

                {{-- Scrollable Content Body --}}
                <div class="flex-1 overflow-y-auto p-5 space-y-6 portal-scrollbar">
                    
                    {{-- Status Banner --}}
                    <div class="bg-zinc-50 border border-line rounded-panel p-4 space-y-1.5">
                        <div class="flex items-center gap-2 text-meta font-semibold text-ink">
                            <span class="w-2.5 h-2.5 rounded-full {{ $selectedStatus['dot_color'] }}"></span>
                            <span>{{ __('Estado Actual: :status', ['status' => $selectedStatus['label']]) }}</span>
                        </div>
                        <p class="text-meta text-ink-2 leading-relaxed">
                            {{ $selectedStatus['description'] }}
                        </p>
                    </div>

                    {{-- Key Project Highlights Cards --}}
                    <div class="grid grid-cols-2 gap-2.5 text-meta">
                        <div class="bg-surface p-3 rounded-card border border-line  space-y-1">
                            <span class="text-body text-ink-3 font-medium block">{{ __('Aprobación de Diseño') }}</span>
                            <div class="font-semibold flex items-center gap-1.5 {{ $selectedOrder->approved ? 'text-ok' : 'text-ink-2' }}">
                                @if($selectedOrder->approved)
                                    <x-lucide-check-circle class="w-4 h-4 text-ok" />
                                    <span>{{ __('Aprobado') }}</span>
                                @else
                                    <x-lucide-clock class="w-4 h-4 text-ink-3" />
                                    <span>{{ __('En Proceso') }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="bg-surface p-3 rounded-card border border-line  space-y-1">
                            <span class="text-body text-ink-3 font-medium block">{{ __('Entrega Estimada') }}</span>
                            <div class="font-semibold text-ink flex items-center gap-1.5">
                                <x-lucide-calendar class="w-4 h-4 text-ink-3" />
                                <span>{{ $selectedOrder->current_due_date ? $selectedOrder->current_due_date->translatedFormat('d M, Y') : __('Por Definir') }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- SIMPLIFIED COMPACT CLIENT TIMELINE --}}
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <h3 class="text-meta font-semibold text-ink  flex items-center gap-1.5">
                                <x-lucide-history class="w-4 h-4 text-ink-3" />
                                <span>{{ __('Línea de Tiempo de la Orden') }}</span>
                            </h3>
                            <span class="text-body text-ink-3 font-medium font-mono">
                                {{ $selectedTimeline->count() }} {{ __('evento(s)') }}
                            </span>
                        </div>

                        <div class="bg-surface rounded-panel p-4 sm:p-5 border border-line ">
                            <div class="relative pl-6 space-y-5">
                                @forelse($selectedTimeline as $index => $milestone)
                                    @php
                                        $isLast = $loop->last;
                                        $dotColors = 'bg-ink text-white';
                                        $lineColor = 'bg-line';
                                    @endphp
                                    <div class="relative group">
                                        {{-- Vertical Line Connector --}}
                                        @if(!$isLast)
                                            <span class="absolute left-[-17px] top-3.5 bottom-[-24px] w-0.5 {{ $lineColor }}"  aria-hidden="true"></span>
                                        @endif

                                        {{-- Node Dot --}}
                                        <span class="absolute left-[-23px] top-1 w-3.5 h-3.5 rounded-full flex items-center justify-center {{ $dotColors }}" aria-hidden="true"></span>

                                        {{-- Milestone Content --}}
                                        <div class="space-y-0.5">
                                            <div class="flex items-center justify-between gap-2">
                                                <h4 class="text-meta font-semibold text-ink">
                                                    {{ $milestone['title'] }}
                                                </h4>
                                                <span class="text-body font-mono text-ink-3 shrink-0 font-medium">
                                                    {{ $milestone['date_formatted'] }}
                                                </span>
                                            </div>

                                            @if(!empty($milestone['subtitle']))
                                                <p class="text-meta text-ink-2 leading-relaxed">
                                                    {{ $milestone['subtitle'] }}
                                                </p>
                                            @endif
                                            
                                            <span class="text-meta text-ink-3 block pt-0.5 font-mono">
                                                {{ $milestone['time_formatted'] }}
                                            </span>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-meta text-ink-3 italic">
                                        {{ __('Iniciando registro de eventos para esta orden.') }}
                                    </p>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    {{-- WHATSAPP / CONTACT DIRECT SHORTCUTS --}}
                    @php
                        $assignedDesigner = $selectedOrder->assigned_designers->first() ?: $selectedOrder->designer;
                        $designerPhone = $assignedDesigner?->contact_phone;
                        $cleanDesignerDigits = $designerPhone ? preg_replace('/\D/', '', $designerPhone) : null;
                        if ($cleanDesignerDigits && strlen($cleanDesignerDigits) === 10) {
                            $cleanDesignerDigits = '1'.$cleanDesignerDigits;
                        }
                        $designerWaText = rawurlencode('Hola '.($assignedDesigner?->name ?: 'Diseñador').", soy cliente de Kudos y quisiera consultar sobre mi diseño para la orden {$selectedOrder->wo_number} ({$selectedOrder->clean_task_name}).");

                        $designerHex = $assignedDesigner?->hex_color ?: match($assignedDesigner?->color_type) {
                            'magenta' => '#d946ef',
                            'cyan' => '#06b6d4',
                            'green', 'emerald' => '#10b981',
                            'amber', 'yellow' => '#f59e0b',
                            default => '#6366f1',
                        };

                        $cleanCsDigits = preg_replace('/\D/', '', $csPhone ?? '+16783580594');
                        if ($cleanCsDigits && strlen($cleanCsDigits) === 10) {
                            $cleanCsDigits = '1'.$cleanCsDigits;
                        }
                        $csWaText = rawurlencode("Hola Kudos Print Media (Atención al Cliente), quisiera consultar el estatus de mi orden {$selectedOrder->wo_number} ({$selectedOrder->clean_task_name}).");
                    @endphp

                    <div class="bg-zinc-50/70 border border-line rounded-panel p-4 sm:p-5 space-y-3.5 ">
                        <div class="space-y-0.5">
                            <h4 class="text-meta font-semibold text-ink  flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-[#16a34a] shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                                </svg>
                                <span>{{ __('¿Tienes dudas o ajustes con esta orden?') }}</span>
                            </h4>
                            <p class="text-body text-ink-3">{{ __('Comunícate directamente por WhatsApp con nuestro equipo.') }}</p>
                        </div>

                        {{-- Action Buttons & Contact Numbers --}}
                        <div class="space-y-3 pt-0.5">
                            {{-- OPTION 1: Chat with designer --}}
                            @if($assignedDesigner && $cleanDesignerDigits)
                                <div class="space-y-1">
                                    <a 
                                        href="https://wa.me/{{ $cleanDesignerDigits }}?text={{ $designerWaText }}" 
                                        target="_blank"
                                        style="background-color: {{ $designerHex }}10; border-color: {{ $designerHex }}30;"
                                        class="group w-full px-3.5 py-2.5 rounded-card border hover:  transition-all flex items-center justify-between gap-3 cursor-pointer"
                                    >
                                        <div class="flex items-center gap-2.5 min-w-0">
                                            <svg class="w-5 h-5 shrink-0 transition-transform group-hover:scale-105" style="color: {{ $designerHex }};" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                                            </svg>
                                            <span class="text-meta sm:text-body font-semibold text-ink block truncate">
                                                {{ __('Chat with :name, your designer', ['name' => $assignedDesigner->name]) }}
                                            </span>
                                        </div>
                                        <x-lucide-arrow-up-right class="w-4 h-4 text-ink-3 group-hover:text-ink-2 shrink-0 transition" />
                                    </a>

                                    {{-- Phone number under designer button with copy action --}}
                                    <div 
                                        x-data="{ 
                                            copied: false,
                                            copy(text) {
                                                try {
                                                    if (navigator.clipboard && window.isSecureContext) {
                                                        navigator.clipboard.writeText(text);
                                                    } else {
                                                        const ta = document.createElement('textarea');
                                                        ta.value = text;
                                                        ta.style.position = 'fixed';
                                                        ta.style.left = '-9999px';
                                                        document.body.appendChild(ta);
                                                        ta.select();
                                                        document.execCommand('copy');
                                                        document.body.removeChild(ta);
                                                    }
                                                } catch (e) {}
                                                this.copied = true;
                                                setTimeout(() => this.copied = false, 2000);
                                            }
                                        }" 
                                        class="flex items-center justify-between px-1.5 text-body"
                                    >
                                        <button 
                                            type="button" 
                                            @click="copy('{{ \App\Livewire\Portal\ClientPortal::formatDisplayPhone($designerPhone) }}')"
                                            class="inline-flex items-center gap-1.5 font-mono font-semibold transition cursor-pointer hover:opacity-80 active:scale-95"
                                            style="color: {{ $designerHex }};"
                                            title="{{ __('Copiar número al portapapeles') }}"
                                        >
                                            <span x-show="!copied" class="inline-flex items-center gap-1">
                                                <x-lucide-copy class="w-3 h-3 opacity-80" />
                                                <span>{{ \App\Livewire\Portal\ClientPortal::formatDisplayPhone($designerPhone) }}</span>
                                            </span>
                                            <span x-show="copied" x-cloak class="inline-flex items-center gap-1 font-sans font-semibold text-ok">
                                                <x-lucide-check class="w-3 h-3" />
                                                <span>{{ __('¡Copiado!') }}</span>
                                            </span>
                                        </button>
                                        <span class="text-meta text-ink-3 select-none">{{ __('Clic para copiar') }}</span>
                                    </div>
                                </div>
                            @elseif($assignedDesigner)
                                <div class="w-full px-3.5 py-2.5 rounded-card bg-zinc-100/80 border border-zinc-200/60 text-ink-3 flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <svg class="w-5 h-5 text-ink-3 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                                        </svg>
                                        <span class="text-meta font-medium text-ink-2 block truncate">{{ $assignedDesigner->name }}</span>
                                    </div>
                                    <span class="text-body text-ink-3 block">{{ __('Sin WhatsApp directo') }}</span>
                                </div>
                            @endif

                            {{-- OPTION 2: Customer Service (CS in Kudos' Yellow) --}}
                            <div class="space-y-1">
                                <a 
                                    href="https://wa.me/{{ $cleanCsDigits }}?text={{ $csWaText }}" 
                                    target="_blank"
                                    style="background-color: #eda62214; border-color: #eda62235;"
                                    class="group w-full px-3.5 py-2.5 rounded-card border hover:  transition-all flex items-center justify-between gap-3 cursor-pointer"
                                >
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <svg class="w-5 h-5 shrink-0 transition-transform group-hover:scale-105" style="color: #eda622;" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                                        </svg>
                                        <span class="text-meta sm:text-body font-semibold text-ink block truncate">
                                            {{ __('Chat with Customer Service') }}
                                        </span>
                                    </div>
                                    <x-lucide-arrow-up-right class="w-4 h-4 text-ink-3 group-hover:text-ink shrink-0 transition" />
                                </a>

                                {{-- Phone number under CS button with copy action --}}
                                <div 
                                    x-data="{ 
                                        copied: false,
                                        copy(text) {
                                            try {
                                                if (navigator.clipboard && window.isSecureContext) {
                                                    navigator.clipboard.writeText(text);
                                                } else {
                                                    const ta = document.createElement('textarea');
                                                    ta.value = text;
                                                    ta.style.position = 'fixed';
                                                    ta.style.left = '-9999px';
                                                    document.body.appendChild(ta);
                                                    ta.select();
                                                    document.execCommand('copy');
                                                    document.body.removeChild(ta);
                                                }
                                            } catch (e) {}
                                            this.copied = true;
                                            setTimeout(() => this.copied = false, 2000);
                                        }
                                    }" 
                                    class="flex items-center justify-between px-1.5 text-body"
                                >
                                    <button 
                                        type="button" 
                                        @click="copy('{{ \App\Livewire\Portal\ClientPortal::formatDisplayPhone($csPhone) }}')"
                                        class="inline-flex items-center gap-1.5 font-mono font-semibold transition cursor-pointer active:scale-95"
                                        style="color: #b45309;"
                                        title="{{ __('Copiar número al portapapeles') }}"
                                    >
                                        <span x-show="!copied" class="inline-flex items-center gap-1">
                                            <x-lucide-copy class="w-3 h-3 opacity-80" />
                                            <span>{{ \App\Livewire\Portal\ClientPortal::formatDisplayPhone($csPhone) }}</span>
                                        </span>
                                        <span x-show="copied" x-cloak class="inline-flex items-center gap-1 font-sans font-semibold text-ok">
                                            <x-lucide-check class="w-3 h-3" />
                                            <span>{{ __('¡Copiado!') }}</span>
                                        </span>
                                    </button>
                                    <span class="text-meta text-ink-3 select-none">{{ __('Clic para copiar') }}</span>
                                </div>
                            </div>
                        </div>

                        {{-- Kudos Office Contact Details --}}
                        <div class="pt-3 border-t border-line space-y-1.5 text-body text-ink-3">
                            <div class="flex items-center gap-1.5 text-meta font-semibold  text-ink-3">
                                <x-lucide-building-2 class="w-3.5 h-3.5" />
                                <span>{{ __('Oficina Kudos') }}</span>
                            </div>
                            
                            <div class="flex items-start gap-2 text-ink-2">
                                <x-lucide-map-pin class="w-3.5 h-3.5 text-ink-3 shrink-0 mt-0.5" />
                                <span class="leading-relaxed">5555 Oakborook Pkwy. Ste. 185 Norcross, GA 30093</span>
                            </div>
                            
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 pt-0.5">
                                <a href="tel:+17706964048,301" class="flex items-center gap-1.5 text-ink-2 hover:text-ink transition">
                                    <x-lucide-phone class="w-3.5 h-3.5 text-ink-3 shrink-0" />
                                    <span class="font-mono font-medium">{{ \App\Livewire\Portal\ClientPortal::formatDisplayPhone('7706964048 Ext. 301') }}</span>
                                </a>
                                <a href="mailto:cs@kudosprintmedia.com" class="flex items-center gap-1.5 text-ink-2 hover:text-ink transition">
                                    <x-lucide-mail class="w-3.5 h-3.5 text-ink-3 shrink-0" />
                                    <span class="underline decoration-zinc-300 underline-offset-2">cs@kudosprintmedia.com</span>
                                </a>
                            </div>
                        </div>
                    </div>

                </div>

                {{-- Bottom Action Drawer Footer --}}
                <div class="p-4 border-t border-line bg-zinc-50 flex items-center justify-end">
                    <button 
                        type="button" 
                        wire:click="closeOrder" 
                        class="w-full sm:w-auto px-5 py-2 rounded-card bg-zinc-900 hover:bg-zinc-800 text-white font-semibold text-meta  transition cursor-pointer"
                    >
                        {{ __('Volver a la lista') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>
