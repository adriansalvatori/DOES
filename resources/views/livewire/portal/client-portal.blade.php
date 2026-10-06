<div class="min-h-screen flex flex-col justify-between max-w-lg mx-auto w-full px-4 pt-6 pb-2 sm:px-6">

    {{-- TOP BRANDING & CLIENT HEADER --}}
    <header class="w-full space-y-4 shrink-0">
        {{-- Kudos Logo --}}
        <div class="flex justify-center">
            <img src="{{ asset('images/logo-kudos.svg') }}" alt="Kudos Print Media" class="h-14 sm:h-16 w-auto drop-shadow-2xs">
        </div>

        {{-- Client Title Card --}}
        <div class="bg-white rounded-2xl p-4 sm:p-5 shadow-xs border border-zinc-200/80 text-center space-y-2 relative overflow-hidden">
            {{-- Accent Top Bar --}}
            <div class="absolute top-0 inset-x-0 h-1.5 bg-gradient-to-r from-amber-400 via-amber-500 to-amber-600"></div>

            <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[11px] font-semibold">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>{{ __('Seguimiento en Vivo') }}</span>
            </div>

            <div class="space-y-1">
                <h1 class="text-xl sm:text-2xl font-black text-zinc-900 uppercase tracking-tight">
                    {{ $client->name }}
                </h1>
                {{-- Yellow Underline Accent --}}
                <div class="w-20 h-1 bg-amber-400 rounded-full mx-auto"></div>
                <p class="text-xs font-semibold text-amber-600">
                    {{ __('¡Eres parte de la familia Kudos!') }}
                </p>
            </div>

            <p class="text-xs text-zinc-500 max-w-xs mx-auto leading-relaxed">
                {{ __('Monitorea el estado y avance de todas tus órdenes activas en tiempo real.') }}
            </p>

            @if($client->locations->count() > 0 && $client->locations->first()->address)
                <div class="pt-2 border-t border-zinc-100 flex items-center justify-center gap-1.5 text-[11px] text-zinc-500 truncate">
                    <x-lucide-map-pin class="w-3.5 h-3.5 text-zinc-400 shrink-0" />
                    <span class="truncate">{{ $client->locations->first()->address }}</span>
                </div>
            @endif
        </div>
    </header>

    {{-- MAIN CONTENT: SEARCH, FILTERS & ORDERS LIST --}}
    <main class="flex-1 my-5 space-y-4 min-h-0">
        {{-- Search Bar --}}
        <div class="relative">
            <x-lucide-search class="w-4 h-4 text-zinc-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
            <input 
                type="text" 
                wire:model.live.debounce.250ms="search" 
                placeholder="{{ __('Buscar por número de WO u orden...') }}" 
                class="w-full bg-white border border-zinc-200/90 rounded-xl pl-10 pr-4 py-2.5 text-xs text-zinc-900 placeholder-zinc-400 shadow-2xs focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition"
            >
            @if(!empty($search))
                <button 
                    type="button" 
                    wire:click="$set('search', '')" 
                    class="absolute right-3 top-2.5 p-0.5 text-zinc-400 hover:text-zinc-600 rounded-full"
                >
                    <x-lucide-x class="w-3.5 h-3.5" />
                </button>
            @endif
        </div>

        {{-- Filter Pills (Horizontal Scrolling on mobile) --}}
        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 scrollbar-none text-xs select-none">
            <button 
                type="button" 
                wire:click="setFilter('all')" 
                class="px-3 py-1.5 rounded-full font-medium transition shrink-0 cursor-pointer {{ $statusFilter === 'all' ? 'bg-zinc-900 text-white shadow-2xs font-semibold' : 'bg-white text-zinc-600 border border-zinc-200/80 hover:bg-zinc-50' }}"
            >
                {{ __('Todas') }} ({{ $allCount }})
            </button>

            @if($reviewCount > 0)
                <button 
                    type="button" 
                    wire:click="setFilter('review')" 
                    class="px-3 py-1.5 rounded-full font-medium transition shrink-0 cursor-pointer flex items-center gap-1.5 {{ $statusFilter === 'review' ? 'bg-emerald-600 text-white shadow-2xs font-semibold' : 'bg-emerald-50 text-emerald-800 border border-emerald-200 hover:bg-emerald-100' }}"
                >
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-ping"></span>
                    <span>{{ __('¡Esperando tu respuesta!') }} ({{ $reviewCount }})</span>
                </button>
            @endif

            <button 
                type="button" 
                wire:click="setFilter('design')" 
                class="px-3 py-1.5 rounded-full font-medium transition shrink-0 cursor-pointer {{ $statusFilter === 'design' ? 'bg-sky-600 text-white shadow-2xs font-semibold' : 'bg-white text-zinc-600 border border-zinc-200/80 hover:bg-zinc-50' }}"
            >
                {{ __('En Diseño') }}
            </button>

            @if($productionCount > 0)
                <button 
                    type="button" 
                    wire:click="setFilter('production')" 
                    class="px-3 py-1.5 rounded-full font-medium transition shrink-0 cursor-pointer {{ $statusFilter === 'production' ? 'bg-pink-600 text-white shadow-2xs font-semibold' : 'bg-white text-zinc-600 border border-zinc-200/80 hover:bg-zinc-50' }}"
                >
                    {{ __('En Producción') }} ({{ $productionCount }})
                </button>
            @endif

            @if($holdCount > 0)
                <button 
                    type="button" 
                    wire:click="setFilter('hold')" 
                    class="px-3 py-1.5 rounded-full font-medium transition shrink-0 cursor-pointer {{ $statusFilter === 'hold' ? 'bg-amber-600 text-white shadow-2xs font-semibold' : 'bg-amber-50 text-amber-800 border border-amber-200 hover:bg-amber-100' }}"
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
                    class="group bg-white rounded-2xl p-4 sm:p-5 border border-zinc-200/80 shadow-xs hover:border-amber-300 hover:shadow-md transition active:scale-[0.99] cursor-pointer space-y-3 relative overflow-hidden"
                >
                    {{-- Status Banner Top Indicator --}}
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-1.5">
                            <span class="font-mono text-xs font-bold px-2 py-0.5 rounded-md bg-zinc-900 text-white tracking-tight">
                                {{ $order->wo_number ?: 'SIN WO' }}
                            </span>
                            @if($order->approved)
                                <span class="px-1.5 py-0.5 rounded bg-lime-100 text-lime-800 text-[10px] font-bold flex items-center gap-0.5">
                                    <x-lucide-check class="w-3 h-3 text-lime-700" />
                                    <span>Aprobado</span>
                                </span>
                            @endif
                        </div>

                        {{-- Friendly Customer Status Badge --}}
                        <div class="px-2.5 py-1 rounded-full text-xs font-semibold border flex items-center gap-1.5 {{ $statusInfo['badge_class'] }}" @if(!empty($statusInfo['badge_inline_style'])) style="{{ $statusInfo['badge_inline_style'] }}" @endif>
                            <span class="w-2 h-2 rounded-full {{ $statusInfo['dot_color'] }}" @if(!empty($statusInfo['dot_style'])) style="{{ $statusInfo['dot_style'] }}" @endif></span>
                            <span>{{ $statusInfo['label'] }}</span>
                        </div>
                    </div>

                    {{-- Order Title & Location --}}
                    <div>
                        <h2 class="text-sm sm:text-base font-bold text-zinc-900 group-hover:text-amber-600 transition leading-snug">
                            {{ $order->clean_task_name }}
                        </h2>
                        @if($order->location_text)
                            <div class="flex items-center gap-1 text-xs text-zinc-500 mt-0.5">
                                <x-lucide-map-pin class="w-3 h-3 text-zinc-400 shrink-0" />
                                <span class="truncate">{{ $order->location_text }}</span>
                            </div>
                        @endif
                    </div>

                    {{-- Description / Explanatory note --}}
                    <p class="text-xs text-zinc-600 bg-zinc-50/80 p-2.5 rounded-xl border border-zinc-100 leading-relaxed">
                        {{ $statusInfo['description'] }}
                    </p>

                    {{-- Latest Milestone Mini-Card --}}
                    @if($latestMilestone)
                        <div class="pt-2 border-t border-zinc-100 flex items-center justify-between text-xs text-zinc-500">
                            <div class="flex items-center gap-1.5 truncate min-w-0 pr-2">
                                <x-lucide-history class="w-3.5 h-3.5 text-zinc-400 shrink-0" />
                                <span class="truncate text-zinc-700 font-medium">
                                    {{ $latestMilestone['title'] }}
                                </span>
                            </div>
                            <span class="text-[11px] font-mono text-zinc-400 shrink-0">
                                {{ $latestMilestone['date_formatted'] }}
                            </span>
                        </div>
                    @endif

                    {{-- Tap to View Details Cue --}}
                    <div class="pt-1 flex items-center justify-between text-xs text-amber-600 font-semibold group-hover:translate-x-0.5 transition">
                        <span>{{ __('Ver detalles y línea de tiempo') }}</span>
                        <x-lucide-chevron-right class="w-4 h-4 text-amber-500" />
                    </div>
                </div>
            @empty
                <div class="bg-white rounded-2xl p-8 text-center border border-zinc-200/80 shadow-xs space-y-3">
                    <div class="w-12 h-12 rounded-2xl bg-zinc-100 text-zinc-400 flex items-center justify-center mx-auto">
                        <x-lucide-folder-open class="w-6 h-6" />
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-sm font-bold text-zinc-900">{{ __('No se encontraron órdenes') }}</h3>
                        <p class="text-xs text-zinc-500 max-w-xs mx-auto">
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
                            class="px-3.5 py-1.5 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-700 text-xs font-semibold transition cursor-pointer"
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
        <div class="p-3.5 bg-white rounded-2xl border border-zinc-200/80 shadow-2xs flex items-center justify-between gap-3 text-xs">
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0 border border-emerald-200/60">
                    <x-lucide-headphones class="w-4 h-4 text-emerald-600" />
                </div>
                <div class="min-w-0">
                    <span class="font-bold text-zinc-900 block truncate">{{ __('Atención al Cliente (CS)') }}</span>
                    <span class="text-[11px] text-zinc-500 block truncate font-mono">{{ \App\Livewire\Portal\ClientPortal::formatDisplayPhone($csPhone) }}</span>
                </div>
            </div>
            <a 
                href="https://wa.me/{{ $cleanGeneralCs }}?text={{ $generalCsText }}" 
                target="_blank"
                class="px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-2xs transition flex items-center gap-1.5 shrink-0"
            >
                <x-lucide-message-circle class="w-3.5 h-3.5" />
                <span>{{ __('WhatsApp') }}</span>
            </a>
        </div>
    </main>

    {{-- FOOTER WITH DECORATION --}}
    <footer class="w-full shrink-0 pt-4 pb-2 space-y-3 text-center">
        {{-- Decorative SVG Footer --}}
        <div class="w-full flex justify-center overflow-hidden rounded-xl">
            <img src="{{ asset('images/footer-decor.svg') }}" alt="Kudos Decor" class="w-full max-h-16 object-contain opacity-90">
        </div>

        <div class="text-[11px] text-zinc-400 space-y-0.5 font-medium">
            <p>Kudos Print Media &bull; Portal de Clientes</p>
            <p class="text-[10px] text-zinc-400/80">Solo vista y monitoreo de órdenes</p>
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
                class="relative bg-white w-full max-w-lg rounded-t-3xl sm:rounded-2xl shadow-2xl max-h-[92vh] sm:max-h-[85vh] flex flex-col z-10 border border-zinc-200/90 overflow-hidden transform transition-all"
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
                <div class="px-5 py-4 border-b border-zinc-100 flex items-start justify-between gap-3 bg-white shrink-0">
                    <div class="space-y-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-xs font-bold px-2 py-0.5 rounded-md bg-zinc-900 text-white tracking-tight">
                                {{ $selectedOrder->wo_number ?: 'SIN WO' }}
                            </span>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $selectedStatus['badge_class'] }}">
                                {{ $selectedStatus['label'] }}
                            </span>
                        </div>
                        <h2 class="text-base sm:text-lg font-bold text-zinc-900 leading-snug break-words">
                            {{ $selectedOrder->clean_task_name }}
                        </h2>
                    </div>

                    <button 
                        type="button" 
                        wire:click="closeOrder" 
                        class="p-2 rounded-full hover:bg-zinc-100 text-zinc-400 hover:text-zinc-700 transition cursor-pointer shrink-0"
                    >
                        <x-lucide-x class="w-5 h-5" />
                    </button>
                </div>

                {{-- Scrollable Content Body --}}
                <div class="flex-1 overflow-y-auto p-5 space-y-6 portal-scrollbar">
                    
                    {{-- Status Banner --}}
                    <div class="bg-zinc-50 border border-zinc-200/70 rounded-2xl p-4 space-y-1.5">
                        <div class="flex items-center gap-2 text-xs font-bold text-zinc-900">
                            <span class="w-2.5 h-2.5 rounded-full {{ $selectedStatus['dot_color'] }}"></span>
                            <span>{{ __('Estado Actual: :status', ['status' => $selectedStatus['label']]) }}</span>
                        </div>
                        <p class="text-xs text-zinc-600 leading-relaxed">
                            {{ $selectedStatus['description'] }}
                        </p>
                    </div>

                    {{-- Key Project Highlights Cards --}}
                    <div class="grid grid-cols-2 gap-2.5 text-xs">
                        <div class="bg-white p-3 rounded-xl border border-zinc-200/80 shadow-2xs space-y-1">
                            <span class="text-[11px] text-zinc-400 font-medium block">{{ __('Aprobación de Diseño') }}</span>
                            <div class="font-bold flex items-center gap-1.5 {{ $selectedOrder->approved ? 'text-lime-700' : 'text-zinc-700' }}">
                                @if($selectedOrder->approved)
                                    <x-lucide-check-circle class="w-4 h-4 text-lime-600" />
                                    <span>{{ __('Aprobado') }}</span>
                                @else
                                    <x-lucide-clock class="w-4 h-4 text-zinc-400" />
                                    <span>{{ __('En Proceso') }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="bg-white p-3 rounded-xl border border-zinc-200/80 shadow-2xs space-y-1">
                            <span class="text-[11px] text-zinc-400 font-medium block">{{ __('Entrega Estimada') }}</span>
                            <div class="font-bold text-zinc-800 flex items-center gap-1.5">
                                <x-lucide-calendar class="w-4 h-4 text-amber-500" />
                                <span>{{ $selectedOrder->current_due_date ? $selectedOrder->current_due_date->translatedFormat('d M, Y') : __('Por Definir') }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- SIMPLIFIED COMPACT CLIENT TIMELINE --}}
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xs font-bold text-zinc-900 uppercase tracking-wider flex items-center gap-1.5">
                                <x-lucide-history class="w-4 h-4 text-amber-500" />
                                <span>{{ __('Línea de Tiempo de la Orden') }}</span>
                            </h3>
                            <span class="text-[11px] text-zinc-400 font-medium font-mono">
                                {{ $selectedTimeline->count() }} {{ __('evento(s)') }}
                            </span>
                        </div>

                        <div class="bg-white rounded-2xl p-4 sm:p-5 border border-zinc-200/80 shadow-2xs">
                            <div class="relative pl-6 space-y-5">
                                @forelse($selectedTimeline as $index => $milestone)
                                    @php
                                        $isLast = $loop->last;
                                        $dotColors = match($milestone['color']) {
                                            'emerald' => 'bg-emerald-500 ring-4 ring-emerald-100 text-white',
                                            'lime' => 'bg-lime-500 ring-4 ring-lime-100 text-white',
                                            'purple' => 'bg-purple-500 ring-4 ring-purple-100 text-white',
                                            'amber', 'yellow' => 'bg-[#eda622] ring-4 ring-[#eda622]/25 text-white',
                                            'pink' => 'bg-pink-500 ring-4 ring-pink-100 text-white',
                                            'indigo' => 'bg-indigo-500 ring-4 ring-indigo-100 text-white',
                                            default => 'bg-[#eda622] ring-4 ring-[#eda622]/25 text-white',
                                        };
                                        $lineColor = match($milestone['color']) {
                                            'emerald', 'lime' => 'bg-emerald-300',
                                            'purple' => 'bg-purple-300',
                                            'amber', 'yellow' => 'bg-amber-300',
                                            'pink' => 'bg-pink-300',
                                            default => 'bg-zinc-200',
                                        };
                                    @endphp
                                    <div class="relative group">
                                        {{-- Vertical Line Connector --}}
                                        @if(!$isLast)
                                            <span class="absolute left-[-17px] top-3.5 bottom-[-24px] w-0.5 {{ $lineColor }}" @if(!empty($milestone['line_style'])) style="{{ $milestone['line_style'] }}" @endif aria-hidden="true"></span>
                                        @endif

                                        {{-- Node Dot --}}
                                        <span class="absolute left-[-23px] top-1 w-3.5 h-3.5 rounded-full flex items-center justify-center {{ $dotColors }}" @if(!empty($milestone['dot_style'])) style="{{ $milestone['dot_style'] }}" @endif aria-hidden="true"></span>

                                        {{-- Milestone Content --}}
                                        <div class="space-y-0.5">
                                            <div class="flex items-center justify-between gap-2">
                                                <h4 class="text-xs font-bold text-zinc-900">
                                                    {{ $milestone['title'] }}
                                                </h4>
                                                <span class="text-[11px] font-mono text-zinc-500 shrink-0 font-medium">
                                                    {{ $milestone['date_formatted'] }}
                                                </span>
                                            </div>

                                            @if(!empty($milestone['subtitle']))
                                                <p class="text-xs text-zinc-600 leading-relaxed">
                                                    {{ $milestone['subtitle'] }}
                                                </p>
                                            @endif
                                            
                                            <span class="text-[10px] text-zinc-400 block pt-0.5 font-mono">
                                                {{ $milestone['time_formatted'] }}
                                            </span>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-xs text-zinc-500 italic">
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

                    <div class="bg-zinc-50/70 border border-zinc-200/80 rounded-2xl p-4 sm:p-5 space-y-3.5 shadow-2xs">
                        <div class="space-y-0.5">
                            <h4 class="text-xs font-bold text-zinc-900 tracking-tight flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-[#16a34a] shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                                </svg>
                                <span>{{ __('¿Tienes dudas o ajustes con esta orden?') }}</span>
                            </h4>
                            <p class="text-[11px] text-zinc-500">{{ __('Comunícate directamente por WhatsApp con nuestro equipo.') }}</p>
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
                                        class="group w-full px-3.5 py-2.5 rounded-xl border hover:shadow-2xs active:scale-[0.99] transition-all flex items-center justify-between gap-3 cursor-pointer"
                                    >
                                        <div class="flex items-center gap-2.5 min-w-0">
                                            <svg class="w-5 h-5 shrink-0 transition-transform group-hover:scale-105" style="color: {{ $designerHex }};" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                                            </svg>
                                            <span class="text-xs sm:text-[13px] font-semibold text-zinc-900 block truncate">
                                                {{ __('Chat with :name, your designer', ['name' => $assignedDesigner->name]) }}
                                            </span>
                                        </div>
                                        <x-lucide-arrow-up-right class="w-4 h-4 text-zinc-400 group-hover:text-zinc-700 shrink-0 transition" />
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
                                        class="flex items-center justify-between px-1.5 text-[11px]"
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
                                            <span x-show="copied" x-cloak class="inline-flex items-center gap-1 font-sans font-bold text-emerald-600">
                                                <x-lucide-check class="w-3 h-3" />
                                                <span>{{ __('¡Copiado!') }}</span>
                                            </span>
                                        </button>
                                        <span class="text-[10px] text-zinc-400 select-none">{{ __('Clic para copiar') }}</span>
                                    </div>
                                </div>
                            @elseif($assignedDesigner)
                                <div class="w-full px-3.5 py-2.5 rounded-xl bg-zinc-100/80 border border-zinc-200/60 text-zinc-500 flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <svg class="w-5 h-5 text-zinc-400 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                                        </svg>
                                        <span class="text-xs font-medium text-zinc-700 block truncate">{{ $assignedDesigner->name }}</span>
                                    </div>
                                    <span class="text-[11px] text-zinc-400 block">{{ __('Sin WhatsApp directo') }}</span>
                                </div>
                            @endif

                            {{-- OPTION 2: Customer Service (CS in Kudos' Yellow) --}}
                            <div class="space-y-1">
                                <a 
                                    href="https://wa.me/{{ $cleanCsDigits }}?text={{ $csWaText }}" 
                                    target="_blank"
                                    style="background-color: #eda62214; border-color: #eda62235;"
                                    class="group w-full px-3.5 py-2.5 rounded-xl border hover:shadow-2xs active:scale-[0.99] transition-all flex items-center justify-between gap-3 cursor-pointer"
                                >
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <svg class="w-5 h-5 shrink-0 transition-transform group-hover:scale-105" style="color: #eda622;" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                                        </svg>
                                        <span class="text-xs sm:text-[13px] font-semibold text-zinc-900 block truncate">
                                            {{ __('Chat with Customer Service') }}
                                        </span>
                                    </div>
                                    <x-lucide-arrow-up-right class="w-4 h-4 text-amber-600/70 group-hover:text-amber-700 shrink-0 transition" />
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
                                    class="flex items-center justify-between px-1.5 text-[11px]"
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
                                        <span x-show="copied" x-cloak class="inline-flex items-center gap-1 font-sans font-bold text-emerald-600">
                                            <x-lucide-check class="w-3 h-3" />
                                            <span>{{ __('¡Copiado!') }}</span>
                                        </span>
                                    </button>
                                    <span class="text-[10px] text-zinc-400 select-none">{{ __('Clic para copiar') }}</span>
                                </div>
                            </div>
                        </div>

                        {{-- Kudos Office Contact Details --}}
                        <div class="pt-3 border-t border-zinc-200/70 space-y-1.5 text-[11px] text-zinc-500">
                            <div class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-zinc-400">
                                <x-lucide-building-2 class="w-3.5 h-3.5" />
                                <span>{{ __('Oficina Kudos') }}</span>
                            </div>
                            
                            <div class="flex items-start gap-2 text-zinc-600">
                                <x-lucide-map-pin class="w-3.5 h-3.5 text-zinc-400 shrink-0 mt-0.5" />
                                <span class="leading-relaxed">5555 Oakborook Pkwy. Ste. 185 Norcross, GA 30093</span>
                            </div>
                            
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 pt-0.5">
                                <a href="tel:+17706964048,301" class="flex items-center gap-1.5 text-zinc-600 hover:text-zinc-900 transition">
                                    <x-lucide-phone class="w-3.5 h-3.5 text-zinc-400 shrink-0" />
                                    <span class="font-mono font-medium">{{ \App\Livewire\Portal\ClientPortal::formatDisplayPhone('7706964048 Ext. 301') }}</span>
                                </a>
                                <a href="mailto:cs@kudosprintmedia.com" class="flex items-center gap-1.5 text-zinc-600 hover:text-zinc-900 transition">
                                    <x-lucide-mail class="w-3.5 h-3.5 text-zinc-400 shrink-0" />
                                    <span class="underline decoration-zinc-300 underline-offset-2">cs@kudosprintmedia.com</span>
                                </a>
                            </div>
                        </div>
                    </div>

                </div>

                {{-- Bottom Action Drawer Footer --}}
                <div class="p-4 border-t border-zinc-100 bg-zinc-50 flex items-center justify-end">
                    <button 
                        type="button" 
                        wire:click="closeOrder" 
                        class="w-full sm:w-auto px-5 py-2 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white font-semibold text-xs shadow-2xs transition cursor-pointer"
                    >
                        {{ __('Volver a la lista') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>
