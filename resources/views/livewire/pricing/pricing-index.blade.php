<div 
    x-data="{
        savedTierId: null,
        openCategories: {},
        allExpanded: false,
        toggleAll(state) {
            this.allExpanded = state;
            document.querySelectorAll('[data-category-id]').forEach(el => {
                const id = el.getAttribute('data-category-id');
                this.openCategories[id] = state;
            });
        },
        init() {
            window.addEventListener('tier-saved', (e) => {
                this.savedTierId = e.detail.id;
                setTimeout(() => {
                    if (this.savedTierId === e.detail.id) {
                        this.savedTierId = null;
                    }
                }, 1200);
            });
        }
    }"
    class="min-h-screen bg-[#fafaf9] p-4 sm:p-6 lg:p-8 space-y-6"
>
    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-stone-200 shadow-2xs">
        <div class="space-y-1">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                    <x-lucide-badge-percent class="w-4 h-4" />
                </div>
                <h1 class="text-xl sm:text-2xl font-black text-stone-900 tracking-tight">
                    {{ __('Lista de Precios & Calculadora') }}
                </h1>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-stone-100 text-stone-600 border border-stone-200">
                    {{ __('Catálogo Desplegable') }}
                </span>
            </div>
            <p class="text-xs text-stone-500">
                {{ __('Catálogo estructurado por categorías y productos desplegables, imágenes de portada, costos de proveedor y calculadora de precios.') }}
            </p>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            <!-- Botón Calculadora Rápida -->
            <button 
                wire:click="openCalculator()"
                class="px-3.5 py-2 rounded-xl text-xs font-bold bg-stone-100 hover:bg-stone-200 text-stone-800 transition flex items-center gap-2 cursor-pointer shadow-2xs border border-stone-200"
            >
                <x-lucide-calculator class="w-4 h-4 text-emerald-600" />
                <span>{{ __('Calculadora') }}</span>
            </button>

            <!-- Acceso a Recursos de Diseño -->
            <a 
                href="{{ route('design-resources') }}"
                wire:navigate
                class="px-3.5 py-2 rounded-xl text-xs font-bold bg-stone-100 hover:bg-stone-200 text-stone-800 transition flex items-center gap-2 cursor-pointer shadow-2xs border border-stone-200"
            >
                <x-lucide-folder-down class="w-4 h-4 text-indigo-600" />
                <span>{{ __('Recursos de Diseño') }}</span>
            </a>

            @if(auth()->user()?->canManagePricing())
                <button 
                    wire:click="openCreateCategoryModal()"
                    class="px-3.5 py-2 rounded-xl text-xs font-bold bg-stone-100 hover:bg-stone-200 text-stone-800 transition flex items-center gap-2 cursor-pointer shadow-2xs border border-stone-200"
                >
                    <x-lucide-folder-plus class="w-4 h-4 text-stone-700" />
                    <span>{{ __('Nueva Categoría') }}</span>
                </button>

                <button 
                    wire:click="openCreateProductModal()"
                    class="px-3.5 py-2 rounded-xl text-xs font-bold bg-stone-900 hover:bg-stone-800 text-white transition flex items-center gap-2 cursor-pointer shadow-2xs"
                >
                    <x-lucide-plus class="w-4 h-4" />
                    <span>{{ __('Nuevo Producto') }}</span>
                </button>
            @endif
        </div>
    </div>

    <!-- Alert / Feedback Notification -->
    @if($feedbackMessage)
        <div 
            x-data="{ show: true }" 
            x-show="show" 
            x-init="setTimeout(() => show = false, 4000)"
            class="p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs rounded-xl flex items-center justify-between shadow-2xs transition"
        >
            <div class="flex items-center gap-2">
                <x-lucide-check-circle-2 class="w-4 h-4 text-emerald-600 shrink-0" />
                <span class="font-medium">{{ $feedbackMessage }}</span>
            </div>
            <button @click="show = false" class="text-emerald-600 hover:text-emerald-900 text-xs cursor-pointer">✕</button>
        </div>
    @endif

    <!-- Toolbar: Filters, Expand/Collapse All & Search -->
    <div class="bg-white rounded-xl border border-stone-200 p-4 shadow-2xs space-y-3">
        <!-- Category Pills & Accordion Toggles -->
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-1.5 overflow-x-auto pb-1 text-xs">
                <span class="text-[10px] font-bold uppercase tracking-wider text-stone-400 mr-1 shrink-0">{{ __('Categoría:') }}</span>
                <button 
                    wire:click="$set('filterCategory', 'all')"
                    class="px-2.5 py-1 rounded-lg text-xs font-semibold transition cursor-pointer shrink-0 {{ $filterCategory === 'all' ? 'bg-stone-900 text-white shadow-2xs' : 'bg-stone-100 text-stone-600 hover:bg-stone-200' }}"
                >
                    {{ __('Todas') }} ({{ $this->products->count() }})
                </button>
                @foreach($this->categories as $cat)
                    <button 
                        wire:click="$set('filterCategory', '{{ $cat->id }}')"
                        class="px-2.5 py-1 rounded-lg text-xs font-semibold transition cursor-pointer shrink-0 {{ (string)$filterCategory === (string)$cat->id ? 'bg-stone-900 text-white shadow-2xs' : 'bg-stone-100 text-stone-600 hover:bg-stone-200' }}"
                    >
                        {{ $cat->name }}
                    </button>
                @endforeach
            </div>

            <!-- Global Expand / Collapse Controls -->
            <div class="flex items-center gap-1 shrink-0">
                <button 
                    @click="toggleAll(true)"
                    class="px-2.5 py-1 rounded-md text-[11px] font-medium text-stone-600 bg-stone-100 hover:bg-stone-200 transition cursor-pointer flex items-center gap-1"
                    title="{{ __('Expandir todas las categorías') }}"
                >
                    <x-lucide-chevron-down class="w-3.5 h-3.5 text-stone-500" />
                    <span>{{ __('Expandir Todas') }}</span>
                </button>
                <button 
                    @click="toggleAll(false)"
                    class="px-2.5 py-1 rounded-md text-[11px] font-medium text-stone-600 bg-stone-100 hover:bg-stone-200 transition cursor-pointer flex items-center gap-1"
                    title="{{ __('Colapsar todas las categorías') }}"
                >
                    <x-lucide-chevron-right class="w-3.5 h-3.5 text-stone-500" />
                    <span>{{ __('Colapsar Todas') }}</span>
                </button>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-2 border-t border-stone-100">
            <!-- Search Input -->
            <div class="relative w-full sm:w-80">
                <x-lucide-search class="w-4 h-4 text-stone-400 absolute left-3 top-2.5" />
                <input 
                    type="text" 
                    wire:model.live.debounce.250ms="search"
                    placeholder="{{ __('Buscar producto, variante, proveedor...') }}"
                    class="w-full pl-9 pr-3 py-1.5 bg-stone-50 border border-stone-200 rounded-lg text-xs focus:ring-2 focus:ring-stone-900 focus:bg-white transition"
                >
            </div>

            <!-- Supplier Filter & Reset -->
            <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                <div class="flex items-center gap-1.5">
                    <span class="text-[11px] font-medium text-stone-500 whitespace-nowrap">{{ __('Proveedor:') }}</span>
                    <select 
                        wire:model.live="filterSupplier"
                        class="bg-stone-50 border border-stone-200 rounded-lg px-2.5 py-1 text-xs focus:ring-2 focus:ring-stone-900 focus:bg-white text-stone-700"
                    >
                        <option value="all">{{ __('Todos los proveedores') }}</option>
                        @foreach($this->suppliers as $sup)
                            <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                        @endforeach
                    </select>
                </div>

                @if($search !== '' || $filterCategory !== 'all' || $filterSupplier !== 'all')
                    <button 
                        wire:click="resetFilters" 
                        class="px-2.5 py-1 rounded-lg text-xs font-medium text-rose-600 bg-rose-50 hover:bg-rose-100 transition flex items-center gap-1 cursor-pointer border border-rose-200"
                    >
                        <x-lucide-rotate-ccw class="w-3 h-3" />
                        <span>{{ __('Limpiar') }}</span>
                    </button>
                @endif
            </div>
        </div>
    </div>

    <!-- CATEGORY ACCORDIONS LIST (DEFAULT CLOSED) -->
    <div class="space-y-5">
        @forelse($this->categoriesWithProducts as $category)
            @php 
                $catProducts = $category->filtered_products; 
                $hasProducts = $catProducts->isNotEmpty();
                // Default closed if no search query, open if searching
                $defaultOpen = (trim($search) !== '' || $filterCategory !== 'all');
            @endphp

            <div 
                data-category-id="{{ $category->id }}"
                x-data="{ 
                    open: {{ $defaultOpen ? 'true' : 'false' }},
                    init() {
                        this.$watch('allExpanded', val => this.open = val);
                    }
                }"
                class="bg-white rounded-2xl border border-stone-200/80 shadow-2xs overflow-hidden transition-all duration-200"
            >
                <!-- CATEGORY ACCORDION HEADER (Cover Image Fills Entire Left Height) -->
                <div 
                    @click="open = !open"
                    class="flex items-stretch min-h-[110px] sm:min-h-[130px] bg-white hover:bg-stone-50/50 transition cursor-pointer select-none group border-b border-stone-100"
                >
                    <!-- IMAGEN DE PORTADA DE CATEGORÍA (LLENA LA SECCIÓN COMPLETA A LA IZQUIERDA) -->
                    <div class="w-28 sm:w-44 md:w-56 shrink-0 relative overflow-hidden bg-stone-100 self-stretch" x-data="{ imgError: false }">
                        @if($category->image_url)
                            <img 
                                src="{{ $category->image_url }}" 
                                alt="{{ $category->name }}" 
                                x-show="!imgError" 
                                x-on:error="imgError = true" 
                                class="w-full h-full object-cover absolute inset-0"
                            >
                            <div 
                                x-show="imgError" 
                                x-cloak 
                                class="w-full h-full bg-gradient-to-br from-stone-100 to-stone-200 flex flex-col items-center justify-center text-stone-400 p-3 text-center absolute inset-0"
                            >
                                <x-lucide-image class="w-7 h-7 stroke-[1.5]" />
                                <span class="text-[9px] font-bold tracking-wider uppercase mt-1 text-stone-400">{{ __('Portada') }}</span>
                            </div>
                        @else
                            <div class="w-full h-full bg-gradient-to-br from-stone-100 to-stone-200 flex flex-col items-center justify-center text-stone-400 p-3 text-center absolute inset-0">
                                <x-lucide-image class="w-7 h-7 stroke-[1.5]" />
                                <span class="text-[9px] font-bold tracking-wider uppercase mt-1 text-stone-400">{{ __('Portada') }}</span>
                            </div>
                        @endif

                        @if(auth()->user()?->canManagePricing())
                            <button 
                                @click.stop="$wire.openEditCategoryModal({{ $category->id }})"
                                title="{{ __('Editar imagen de portada y detalles') }}"
                                class="absolute inset-0 bg-stone-900/60 text-white opacity-0 group-hover:opacity-100 transition flex items-center justify-center text-xs font-bold gap-1 cursor-pointer backdrop-blur-[1px]"
                            >
                                <x-lucide-pencil class="w-4 h-4" />
                                <span class="hidden sm:inline">{{ __('Cambiar Portada') }}</span>
                            </button>
                        @endif
                    </div>

                    <!-- Título & Descripción de Categoría -->
                    <div class="p-4 sm:p-5 flex-1 flex flex-col sm:flex-row sm:items-center justify-between gap-4 min-w-0">
                        <div class="space-y-1.5 min-w-0">
                            <div class="flex items-center gap-2.5 flex-wrap">
                                <!-- Chevron Indicator -->
                                <div class="w-5 h-5 rounded-md bg-stone-100 text-stone-700 flex items-center justify-center shrink-0 transition-transform duration-200" :class="open ? 'rotate-0' : '-rotate-90'">
                                    <x-lucide-chevron-down class="w-3.5 h-3.5" />
                                </div>

                                <h2 class="text-lg sm:text-xl font-black text-stone-900 tracking-tight">
                                    {{ $category->name }}
                                </h2>
                                <span class="px-2 py-0.5 rounded-full text-[10.5px] font-bold bg-stone-100 text-stone-700 border border-stone-200/60">
                                    {{ $catProducts->count() }} {{ $catProducts->count() === 1 ? __('producto') : __('productos') }}
                                </span>
                            </div>

                            @if($category->description)
                                <p class="text-xs text-stone-500 max-w-3xl leading-relaxed font-normal line-clamp-2 pl-7">
                                    {{ $category->description }}
                                </p>
                            @endif
                        </div>

                        <div class="flex items-center gap-2 shrink-0 self-end sm:self-center" @click.stop>
                            @if(auth()->user()?->canManagePricing())
                                <button 
                                    wire:click="openEditCategoryModal({{ $category->id }})"
                                    class="px-3 py-1.5 rounded-xl text-xs font-semibold bg-stone-100 hover:bg-stone-200 text-stone-700 transition flex items-center gap-1.5 cursor-pointer border border-stone-200/80"
                                    title="{{ __('Editar nombre, portada y descripción de esta categoría') }}"
                                >
                                    <x-lucide-pencil class="w-3.5 h-3.5 text-stone-500" />
                                    <span>{{ __('Editar Categoría') }}</span>
                                </button>
                            @endif

                            <span class="text-[11px] font-bold text-stone-400 group-hover:text-stone-700 transition">
                                <span x-text="open ? '{{ __('Ocultar') }} ▲' : '{{ __('Mostrar') }} ▼'"></span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- CATEGORY CONTENT (SEAMLESS PRODUCT LIST, NO BOX-IN-A-BOX) -->
                <div x-show="open" x-collapse class="divide-y divide-stone-100 bg-white">
                    @forelse($catProducts as $product)
                        @php 
                            $defaultProductOpen = (trim($search) !== '');
                        @endphp

                        <!-- PRODUCT LIST ITEM -->
                        <div 
                            x-data="{ openProduct: {{ $defaultProductOpen ? 'true' : 'false' }} }"
                            class="transition group/item"
                        >
                            <!-- PRODUCT ROW (CLEAN MINIMALIST LIST ROW) -->
                            <div 
                                @click="openProduct = !openProduct"
                                class="py-3.5 px-4 sm:px-6 hover:bg-stone-50/70 transition cursor-pointer select-none flex items-center justify-between gap-3"
                            >
                                <div class="flex items-center gap-3 min-w-0">
                                    <!-- Chevron Indicator for Product -->
                                    <div class="w-5 h-5 rounded-md text-stone-400 group-hover/item:text-stone-800 flex items-center justify-center shrink-0 transition-transform duration-200" :class="openProduct ? 'rotate-0 text-stone-900 font-bold' : '-rotate-90'">
                                        <x-lucide-chevron-down class="w-4 h-4" />
                                    </div>

                                    <!-- Product Name (Clean & Simple) -->
                                    <h3 class="text-sm sm:text-base font-extrabold text-stone-900 tracking-tight truncate">
                                        {{ $product->name }}
                                    </h3>
                                </div>

                                <!-- Actions on Product Header (Edit & Create Variant) -->
                                <div class="flex items-center gap-2 shrink-0" @click.stop>
                                    @if(auth()->user()?->canManagePricing())
                                        <button 
                                            wire:click="openEditProductModal({{ $product->id }})"
                                            class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-white hover:bg-stone-100 text-stone-700 border border-stone-200 transition flex items-center gap-1 cursor-pointer shadow-2xs"
                                            title="{{ __('Editar detalles del producto') }}"
                                        >
                                            <x-lucide-pencil class="w-3.5 h-3.5 text-stone-500" />
                                            <span>{{ __('Editar') }}</span>
                                        </button>

                                        <button 
                                            wire:click="openAddVariantModal({{ $product->id }})"
                                            class="px-2.5 py-1 rounded-lg text-xs font-bold bg-stone-900 hover:bg-stone-800 text-white transition flex items-center gap-1 cursor-pointer shadow-2xs"
                                        >
                                            <x-lucide-plus class="w-3.5 h-3.5" />
                                            <span>{{ __('Nueva Variante') }}</span>
                                        </button>
                                    @endif
                                </div>
                            </div>

                            <!-- SUBPRODUCTS & PRODUCT DETAILS (UNFOLDS AS PART OF LIST ITEM) -->
                            <div x-show="openProduct" x-collapse class="px-4 sm:px-6 pb-5 pt-2 space-y-4 bg-stone-50/30 border-t border-stone-100">
                                <!-- Expanded Product Details Bar (Thumbnail, Description, Sizes) -->
                                <div class="flex flex-col sm:flex-row sm:items-start justify-start gap-4 py-1 px-1">
                                    <div class="flex items-start gap-3 min-w-0 text-left">
                                        <!-- Thumbnail Image (Only shown if image_url exists) -->
                                        @if($product->image_url)
                                            <div x-data="{ imgError: false }" x-show="!imgError" class="w-12 h-12 rounded-xl bg-stone-100 border border-stone-200 shrink-0 flex items-center justify-center overflow-hidden">
                                                <img src="{{ $product->image_url }}" alt="{{ $product->name }}" x-on:error="imgError = true" class="w-full h-full object-cover">
                                            </div>
                                        @endif

                                        <div class="space-y-1 min-w-0 text-left">
                                            <div class="flex items-center gap-2 flex-wrap text-left">
                                                <span class="text-[11px] text-stone-400 font-medium text-left">
                                                    ({{ $product->variants->count() }} {{ $product->variants->count() === 1 ? __('variante') : __('variantes') }})
                                                </span>
                                            </div>

                                            @if($product->description)
                                                <p class="text-xs text-stone-500 font-normal text-left">
                                                    {{ $product->description }}
                                                </p>
                                            @endif

                                            <!-- Badges de Tamaños Disponibles (Medidas) -->
                                            @php $sizes = $product->getSizesList(); @endphp
                                            @if(!empty($sizes))
                                                <div class="flex items-center gap-1 flex-wrap text-left pt-1 justify-start">
                                                    <span class="text-[9.5px] font-bold uppercase tracking-wider text-stone-400 mr-1 text-left">{{ __('Medidas:') }}</span>
                                                    @foreach($sizes as $sz)
                                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-mono font-medium bg-white text-stone-700 border border-stone-200">
                                                            {{ $sz }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                @forelse($product->variants as $variant)
                                    @php 
                                        $variantSupplier = $variant->getEffectiveSupplier();
                                        $tiers = $variant->tiers;
                                        $vWeb = $variant->getWebsiteUrl();
                                        $vMockup = $variant->getMockupUrl();
                                        $vTemplate = $variant->getTemplateUrl();
                                    @endphp

                                    <div class="bg-white rounded-xl border border-stone-200/90 overflow-hidden shadow-2xs">
                                        <!-- Subproduct Variant Bar Header -->
                                        <div class="p-3 bg-stone-100/60 border-b border-stone-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                            <div class="flex items-center gap-2.5 flex-wrap">
                                                <!-- Subproduct Thumbnail Image Box -->
                                                <button 
                                                    type="button"
                                                    x-data="{ imgError: false }"
                                                    @if(auth()->user()?->canManagePricing()) wire:click="openEditVariantModal({{ $variant->id }})" @endif
                                                    class="w-9 h-9 rounded-xl bg-white border border-stone-200 shrink-0 overflow-hidden flex items-center justify-center relative group/vimg cursor-pointer transition hover:border-stone-400 shadow-2xs"
                                                    title="{{ __('Subir o cambiar imagen de este subproducto') }}"
                                                >
                                                    @if($variant->image_url)
                                                        <img src="{{ $variant->image_url }}" alt="{{ $variant->name }}" x-show="!imgError" x-on:error="imgError = true" class="w-full h-full object-cover">
                                                        <x-lucide-image x-show="imgError" x-cloak class="w-4 h-4 text-stone-400 group-hover/vimg:text-stone-700 transition stroke-[1.5]" />
                                                    @else
                                                        <x-lucide-image class="w-4 h-4 text-stone-400 group-hover/vimg:text-stone-700 transition stroke-[1.5]" />
                                                    @endif

                                                    @if(auth()->user()?->canManagePricing())
                                                        <div class="absolute inset-0 bg-stone-900/40 opacity-0 group-hover/vimg:opacity-100 transition flex items-center justify-center text-white">
                                                            <x-lucide-upload class="w-3.5 h-3.5" />
                                                        </div>
                                                    @endif
                                                </button>

                                                <h4 class="text-sm font-extrabold text-stone-900 tracking-tight">
                                                    {{ $variant->name }}
                                                </h4>

                                                @if($variantSupplier)
                                                    @php $vSupStyle = $variantSupplier->getBadgeStyle(); @endphp
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10.5px] font-bold border transition-colors" style="{{ $vSupStyle['inline'] }}" title="{{ __('Proveedor de este subproducto') }}">
                                                        <x-lucide-building-2 class="w-3 h-3" style="color: {{ $vSupStyle['text'] }}" />
                                                        <span>{{ $variantSupplier->name }}</span>
                                                    </span>
                                                @endif

                                                @if($variant->turnaround_time)
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-900 border border-amber-300">
                                                        <x-lucide-clock class="w-3 h-3 text-amber-700" />
                                                        <span>{{ $variant->turnaround_time }}</span>
                                                    </span>
                                                @endif

                                                @if($variant->specs)
                                                    <span class="text-xs text-stone-500 font-normal italic">
                                                        — {{ $variant->specs }}
                                                    </span>
                                                @endif
                                            </div>

                                            <div class="flex items-center gap-2 flex-wrap">
                                                <!-- Resource links per subproduct -->
                                                @if($vWeb)
                                                    <a href="{{ $vWeb }}" target="_blank" rel="noopener noreferrer" title="{{ __('Web del proveedor para este subproducto') }}" class="px-2 py-1 rounded-lg text-xs font-semibold bg-white hover:bg-stone-100 text-stone-700 border border-stone-200 transition flex items-center gap-1">
                                                        <x-lucide-globe class="w-3.5 h-3.5 text-emerald-600" />
                                                        <span>{{ __('Web') }}</span>
                                                    </a>
                                                @endif

                                                @if($vMockup)
                                                    <a href="{{ $vMockup }}" target="_blank" rel="noopener noreferrer" title="{{ __('Mockup para este subproducto') }}" class="px-2 py-1 rounded-lg text-xs font-semibold bg-white hover:bg-amber-50 text-amber-800 border border-stone-200 transition flex items-center gap-1">
                                                        <x-lucide-image class="w-3.5 h-3.5 text-amber-600" />
                                                        <span>{{ __('Mockup') }}</span>
                                                    </a>
                                                @endif

                                                @if($vTemplate)
                                                    <a href="{{ $vTemplate }}" target="_blank" rel="noopener noreferrer" title="{{ __('Template para este subproducto') }}" class="px-2 py-1 rounded-lg text-xs font-semibold bg-white hover:bg-indigo-50 text-indigo-800 border border-stone-200 transition flex items-center gap-1">
                                                        <x-lucide-box class="w-3.5 h-3.5 text-indigo-600" />
                                                        <span>{{ __('Template') }}</span>
                                                    </a>
                                                @endif

                                                @if(auth()->user()?->canManagePricing())
                                                    <button 
                                                        wire:click="openEditVariantModal({{ $variant->id }})"
                                                        class="px-2 py-1 rounded-md text-[11px] font-bold bg-white hover:bg-stone-100 text-stone-700 border border-stone-200 transition cursor-pointer flex items-center gap-1"
                                                        title="{{ __('Editar proveedor y recursos de este subproducto') }}"
                                                    >
                                                        <x-lucide-pencil class="w-3 h-3 text-stone-500" />
                                                        <span>{{ __('Editar') }}</span>
                                                    </button>
                                                @endif
                                            </div>
                                        </div>

                                        <!-- Spreadsheet Table for this Variant (Compact & Sleek Layout) -->
                                        <div class="overflow-x-auto">
                                            <table class="w-full text-left border-collapse text-[11.5px]">
                                                <thead class="bg-stone-50/90 border-b border-stone-200 text-[9.5px] font-extrabold text-stone-500 uppercase tracking-wider select-none">
                                                    <tr>
                                                        <th class="py-1.5 px-2.5 border-r border-stone-200/80 text-right w-24 bg-stone-100/40">{{ __('Cantidad') }}</th>
                                                        <th class="py-1.5 px-2.5 border-r border-stone-200/80 text-right w-28 bg-amber-50/30">
                                                            {{ __('Costo Material') }}
                                                            @if(auth()->user()?->canManagePricing())
                                                                <span class="text-[8.5px] text-amber-600">✏️</span>
                                                            @endif
                                                        </th>
                                                        <th class="py-1.5 px-2.5 border-r border-stone-200/80 text-right w-20 bg-amber-50/30">
                                                            {{ __('% Margen') }}
                                                            @if(auth()->user()?->canManagePricing())
                                                                <span class="text-[8.5px] text-amber-600">✏️</span>
                                                            @endif
                                                        </th>
                                                        <th class="py-1.5 px-3 border-r border-stone-200/80 text-right w-28 bg-emerald-100/50 font-black text-emerald-950">{{ __('Precio Final') }}</th>
                                                        <th class="py-1.5 px-2.5 border-r border-stone-200/80 text-right w-24 text-stone-500">{{ __('Por Unidad') }}</th>
                                                        <th class="py-1.5 px-2 text-center w-14">{{ __('Acciones') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-stone-200/80 font-normal">
                                                    @forelse($tiers as $tier)
                                                        <tr 
                                                            wire:key="vt-row-{{ $tier->id }}"
                                                            :class="savedTierId === {{ $tier->id }} ? 'bg-emerald-50/80 transition-colors duration-300' : 'hover:bg-stone-50/70 transition-colors'"
                                                            class="group"
                                                        >
                                                            <!-- Cantidad -->
                                                            <td class="py-1 px-2.5 border-r border-stone-200/80 text-right font-mono font-bold text-stone-900 bg-stone-50/20">
                                                                @if(auth()->user()?->canManagePricing())
                                                                    <input 
                                                                        type="number"
                                                                        value="{{ $tier->quantity }}"
                                                                        @change.stop="$wire.quickUpdateTier({{ $tier->id }}, 'quantity', $el.value)"
                                                                        @keydown.enter.prevent="$el.blur()"
                                                                        class="w-full text-right font-mono text-[11px] px-1 py-0.5 bg-transparent hover:bg-stone-100 focus:bg-white focus:ring-1 focus:ring-stone-800 rounded border border-transparent focus:border-stone-300 transition"
                                                                    >
                                                                @else
                                                                    <span class="px-1 text-[11px]">{{ number_format($tier->quantity) }}</span>
                                                                @endif
                                                            </td>

                                                            <!-- Costo Material -->
                                                            <td class="py-1 px-2.5 border-r border-stone-200/80 text-right font-mono bg-amber-50/10">
                                                                @if(auth()->user()?->canManagePricing())
                                                                    <div class="relative flex items-center justify-end">
                                                                        <span class="text-[9.5px] text-stone-400 mr-0.5">$</span>
                                                                        <input 
                                                                            type="number"
                                                                            step="0.01"
                                                                            value="{{ $tier->production_cost }}"
                                                                            @change.stop="$wire.quickUpdateTier({{ $tier->id }}, 'production_cost', $el.value)"
                                                                            @keydown.enter.prevent="$el.blur()"
                                                                            class="w-20 text-right font-mono text-[11px] px-1 py-0.5 bg-transparent hover:bg-amber-100/60 focus:bg-white focus:ring-1 focus:ring-amber-500 rounded border border-transparent focus:border-amber-300 text-stone-800 transition"
                                                                        >
                                                                    </div>
                                                                @else
                                                                    <span class="text-stone-700 text-[11px]">${{ number_format($tier->production_cost, 2) }}</span>
                                                                @endif
                                                            </td>

                                                            <!-- % Margen -->
                                                            <td class="py-1 px-2.5 border-r border-stone-200/80 text-right font-mono bg-amber-50/10">
                                                                @if(auth()->user()?->canManagePricing())
                                                                    <div class="relative flex items-center justify-end">
                                                                        <input 
                                                                            type="number"
                                                                            step="1"
                                                                            value="{{ $tier->markup_percent }}"
                                                                            @change.stop="$wire.quickUpdateTier({{ $tier->id }}, 'markup_percent', $el.value)"
                                                                            @keydown.enter.prevent="$el.blur()"
                                                                            class="w-14 text-right font-mono text-[11px] px-1 py-0.5 bg-transparent hover:bg-amber-100/60 focus:bg-white focus:ring-1 focus:ring-amber-500 rounded border border-transparent focus:border-amber-300 text-stone-800 transition"
                                                                        >
                                                                        <span class="text-[9.5px] text-stone-400 ml-0.5">%</span>
                                                                    </div>
                                                                @else
                                                                    <span class="text-stone-700 text-[11px]">+{{ number_format($tier->markup_percent, 0) }}%</span>
                                                                @endif
                                                            </td>

                                                            <!-- Precio Final Efectivo -->
                                                            <td class="py-1 px-3 border-r border-stone-200/80 text-right font-mono font-black text-[11.5px] text-emerald-950 bg-emerald-100/40">
                                                                ${{ number_format($tier->final_price, 2) }}
                                                            </td>

                                                            <!-- Precio Unitario -->
                                                            <td class="py-1 px-2.5 border-r border-stone-200/80 text-right font-mono text-[10.5px] text-stone-500">
                                                                ${{ number_format($tier->unit_price, 3) }}/u
                                                            </td>

                                                            <!-- Acciones -->
                                                            <td class="py-1 px-2 text-center">
                                                                <div class="flex items-center justify-center gap-0.5 opacity-80 group-hover:opacity-100 transition">
                                                                    <button 
                                                                        wire:click="openCalculator({{ $product->id }}, {{ $variant->id }})"
                                                                        title="{{ __('Abrir en Calculadora') }}"
                                                                        class="p-0.5 rounded text-stone-400 hover:text-emerald-600 hover:bg-emerald-50 transition cursor-pointer"
                                                                    >
                                                                        <x-lucide-calculator class="w-3.5 h-3.5" />
                                                                    </button>

                                                                    @if(auth()->user()?->canManagePricing())
                                                                        <button 
                                                                            wire:click="deleteTier({{ $tier->id }})"
                                                                            wire:confirm="{{ __('¿Seguro que deseas eliminar esta escala de precio?') }}"
                                                                            title="{{ __('Eliminar escala') }}"
                                                                            class="p-0.5 rounded text-stone-400 hover:text-rose-600 hover:bg-rose-50 transition cursor-pointer"
                                                                        >
                                                                            <x-lucide-trash-2 class="w-3.5 h-3.5" />
                                                                        </button>
                                                                    @endif
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    @empty
                                                        <tr>
                                                            <td colspan="6" class="py-4 text-center text-stone-400 italic text-xs">
                                                                {{ __('Sin escalas de cantidad configuradas para este subproducto.') }}
                                                            </td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                                @if(auth()->user()?->canManagePricing())
                                                    <tfoot class="border-t border-stone-200/80 bg-stone-50/20">
                                                        <tr>
                                                            <td colspan="6" class="p-0">
                                                                <button 
                                                                    wire:click="openAddTierModal({{ $variant->id }})"
                                                                    class="w-full py-2 px-3 flex items-center gap-1.5 text-[11.5px] font-medium text-stone-500 hover:text-stone-900 hover:bg-stone-100/70 transition cursor-pointer group"
                                                                >
                                                                    <x-lucide-plus class="w-3.5 h-3.5 text-stone-400 group-hover:text-stone-700 transition" />
                                                                    <span>{{ __('Agrega nueva opción de precio') }}</span>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    </tfoot>
                                                @endif
                                            </table>
                                        </div>
                                    </div>
                                @empty
                                    <div class="p-6 text-center text-stone-400 bg-stone-50/50 rounded-xl border border-stone-200/80 text-xs">
                                        {{ __('No hay variantes o subproductos creados para este producto.') }}
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center text-stone-400 text-xs">
                            {{ __('No hay productos en esta categoría con el filtro seleccionado.') }}
                        </div>
                    @endforelse
                </div>
            </div>
        @empty
            <div class="bg-white rounded-2xl border border-stone-200 p-12 text-center text-stone-400">
                <x-lucide-package-open class="w-10 h-10 mx-auto mb-2 text-stone-300" />
                <div class="text-base font-bold text-stone-700">{{ __('No se encontraron categorías ni productos.') }}</div>
                <div class="text-xs text-stone-400 mt-1">{{ __('Prueba ajustando los filtros de búsqueda o categoría.') }}</div>
            </div>
        @endforelse
    </div>

    <!-- MODAL 1: Interactive Price Calculator -->
    @if($calculatorOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-stone-900/50 backdrop-blur-xs">
            <div class="bg-white rounded-2xl border border-stone-200 shadow-2xl max-w-lg w-full p-6 space-y-5 animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center justify-between pb-3 border-b border-stone-100">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center">
                            <x-lucide-calculator class="w-4 h-4" />
                        </div>
                        <div>
                            <h3 class="text-base font-extrabold text-stone-900">{{ __('Calculadora de Precios & Margen') }}</h3>
                            <p class="text-xs text-stone-500">{{ __('Simula costos, porcentajes de utilidad y precios por volumen.') }}</p>
                        </div>
                    </div>
                    <button wire:click="closeCalculator" class="text-stone-400 hover:text-stone-700 text-sm font-bold cursor-pointer">✕</button>
                </div>

                @php
                    $simCost = max(0, (float)$calcCost);
                    $simQty = max(1, (int)$calcQuantity);
                    $simMarkup = max(0, (float)$calcMarkup);
                    $simFinalPrice = round($simCost * (1 + ($simMarkup / 100)), 2);
                    $simNetProfit = round($simFinalPrice - $simCost, 2);
                    $simUnitPrice = round($simFinalPrice / $simQty, 4);
                @endphp

                <!-- Inputs Grid -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-stone-500 mb-1">
                            {{ __('Cantidad (unidades)') }}
                        </label>
                        <input 
                            type="number" 
                            wire:model.live="calcQuantity"
                            min="1"
                            class="w-full px-3 py-1.5 bg-stone-50 border border-stone-200 rounded-lg text-xs font-mono font-bold focus:ring-2 focus:ring-stone-900 focus:bg-white"
                        >
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-stone-500 mb-1">
                            {{ __('Costo Material / Proveedor ($)') }}
                        </label>
                        <input 
                            type="number" 
                            step="0.01"
                            wire:model.live="calcCost"
                            class="w-full px-3 py-1.5 bg-stone-50 border border-stone-200 rounded-lg text-xs font-mono font-bold focus:ring-2 focus:ring-stone-900 focus:bg-white"
                        >
                    </div>
                </div>

                <!-- Markup Presets -->
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between text-xs">
                        <label class="text-[10px] font-bold uppercase tracking-wider text-stone-500">{{ __('% Margen sobre Costo') }}</label>
                        <span class="font-mono font-bold text-amber-700">+{{ $calcMarkup }}%</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        @foreach([30, 40, 50, 60, 75, 100] as $preset)
                            <button 
                                type="button"
                                wire:click="applyPresetMarkup({{ $preset }})"
                                class="flex-1 py-1 rounded text-xs font-bold transition cursor-pointer border {{ (float)$calcMarkup === (float)$preset ? 'bg-amber-100 text-amber-900 border-amber-300' : 'bg-stone-50 text-stone-600 border-stone-200 hover:bg-stone-100' }}"
                            >
                                +{{ $preset }}%
                            </button>
                        @endforeach
                    </div>
                </div>

                <!-- Live Results Cards -->
                <div class="grid grid-cols-3 gap-2 bg-stone-50 p-3.5 rounded-xl border border-stone-200">
                    <div class="text-center">
                        <span class="text-[10px] font-semibold uppercase text-stone-400 block">{{ __('Ganancia Neta') }}</span>
                        <span class="text-sm font-extrabold font-mono text-emerald-700">${{ number_format($simNetProfit, 2) }}</span>
                    </div>
                    <div class="text-center border-x border-stone-200">
                        <span class="text-[10px] font-semibold uppercase text-stone-400 block">{{ __('Precio Final') }}</span>
                        <span class="text-base font-black font-mono text-stone-900">${{ number_format($simFinalPrice, 2) }}</span>
                    </div>
                    <div class="text-center">
                        <span class="text-[10px] font-semibold uppercase text-stone-400 block">{{ __('Por Unidad') }}</span>
                        <span class="text-sm font-extrabold font-mono text-stone-600">${{ number_format($simUnitPrice, 4) }}/u</span>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-between pt-2">
                    <button 
                        wire:click="closeCalculator" 
                        class="px-4 py-2 rounded-xl text-xs font-semibold text-stone-600 hover:bg-stone-100 transition cursor-pointer"
                    >
                        {{ __('Cerrar') }}
                    </button>

                    @if($calcVariantId && auth()->user()?->canManagePricing())
                        <button 
                            wire:click="saveCalculatorToVariant"
                            class="px-4 py-2 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white transition cursor-pointer shadow-2xs flex items-center gap-1.5"
                        >
                            <x-lucide-save class="w-3.5 h-3.5" />
                            <span>{{ __('Aplicar a la Lista') }}</span>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL 2: Create Product Modal -->
    @if($createProductModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-stone-900/50 backdrop-blur-xs">
            <div class="bg-white rounded-2xl border border-stone-200 shadow-2xl max-w-md w-full p-6 space-y-4 animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center justify-between pb-2 border-b border-stone-100">
                    <h3 class="text-base font-extrabold text-stone-900">{{ __('Nuevo Producto') }}</h3>
                    <button wire:click="$set('createProductModalOpen', false)" class="text-stone-400 hover:text-stone-700 text-sm font-bold cursor-pointer">✕</button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">{{ __('Nombre del Producto') }} *</label>
                        <input 
                            type="text" 
                            wire:model="newProductName"
                            placeholder="ej. Business Card, Roll-Up Banner"
                            class="w-full px-3 py-1.5 bg-stone-50 border border-stone-200 rounded-lg text-xs focus:ring-2 focus:ring-stone-900 focus:bg-white"
                        >
                        @error('newProductName') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block font-bold text-stone-700 mb-1">{{ __('Categoría') }} *</label>
                            <select 
                                wire:model="newCategoryId"
                                class="w-full px-2.5 py-1.5 bg-stone-50 border border-stone-200 rounded-lg text-xs"
                            >
                                @foreach($this->categories as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-stone-700 mb-1">{{ __('Proveedor Principal') }}</label>
                            <select 
                                wire:model="newSupplierId"
                                class="w-full px-2.5 py-1.5 bg-stone-50 border border-stone-200 rounded-lg text-xs"
                            >
                                <option value="">{{ __('Sin proveedor') }}</option>
                                @foreach($this->suppliers as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-stone-700 mb-1">{{ __('Subproducto / Variante Inicial') }} *</label>
                        <input 
                            type="text" 
                            wire:model="newInitialVariant"
                            placeholder="ej. Regular, Rush, Foil"
                            class="w-full px-3 py-1.5 bg-stone-50 border border-stone-200 rounded-lg text-xs"
                        >
                    </div>

                    <div>
                        <label class="block font-bold text-stone-700 mb-1">{{ __('Tamaños Posibles') }} (separados por coma)</label>
                        <input 
                            type="text" 
                            wire:model="newSizes"
                            placeholder="ej. 33'' x 81'', 38'' x 81'', 60'' x 81''"
                            class="w-full px-3 py-1.5 bg-stone-50 border border-stone-200 rounded-lg text-xs"
                        >
                    </div>

                    <!-- Dual File Upload / URL Option for Product Thumbnail -->
                    <div class="space-y-1.5">
                        <label class="block font-bold text-stone-700 flex items-center justify-between">
                            <span class="flex items-center gap-1.5">
                                <x-lucide-image class="w-3.5 h-3.5 text-indigo-600" />
                                <span>{{ __('Imagen de Miniatura') }}</span>
                            </span>
                            <span class="text-[10px] text-stone-400 font-normal">{{ __('Subir archivo o URL') }}</span>
                        </label>

                        <div class="p-3 bg-stone-50 rounded-xl border border-stone-200 space-y-2.5">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-xl bg-stone-200 border border-stone-300 overflow-hidden shrink-0 flex items-center justify-center relative shadow-2xs">
                                    @if ($newProductFile && method_exists($newProductFile, 'temporaryUrl'))
                                        <img src="{{ $newProductFile->temporaryUrl() }}" class="w-full h-full object-cover">
                                    @else
                                        <x-lucide-box class="w-5 h-5 text-stone-400 stroke-[1.5]" />
                                    @endif
                                    <div wire:loading wire:target="newProductFile" class="absolute inset-0 bg-stone-900/60 flex items-center justify-center text-white">
                                        <x-lucide-loader-2 class="w-4 h-4 animate-spin" />
                                    </div>
                                </div>

                                <div class="flex-1 min-w-0">
                                    <input 
                                        type="file" 
                                        id="newProductFileInput"
                                        wire:model="newProductFile" 
                                        accept="image/*"
                                        class="hidden"
                                    >
                                    <label 
                                        for="newProductFileInput"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white hover:bg-stone-100 text-stone-700 text-xs font-semibold rounded-lg border border-stone-300 shadow-2xs transition cursor-pointer select-none"
                                    >
                                        <x-lucide-upload class="w-3.5 h-3.5 text-stone-600" />
                                        <span>{{ __('Subir Imagen...') }}</span>
                                    </label>
                                    <span class="block text-[10px] text-stone-400 mt-1">PNG, JPG, WEBP (máx 5MB)</span>
                                </div>
                            </div>
                        </div>
                        @error('newProductFile') <span class="text-rose-600 text-[10px] block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-bold text-stone-700 mb-1">{{ __('Descripción / Notas') }}</label>
                        <textarea 
                            wire:model="newDescription"
                            rows="2"
                            placeholder="Detalles sobre materiales, uso y aplicaciones..."
                            class="w-full px-3 py-1.5 bg-stone-50 border border-stone-200 rounded-lg text-xs"
                        ></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-stone-100">
                    <button 
                        wire:click="$set('createProductModalOpen', false)"
                        class="px-3.5 py-1.5 rounded-lg text-xs font-semibold text-stone-600 hover:bg-stone-100 transition cursor-pointer"
                    >
                        {{ __('Cancelar') }}
                    </button>
                    <button 
                        wire:click="createProduct"
                        class="px-4 py-1.5 rounded-lg text-xs font-bold bg-stone-900 hover:bg-stone-800 text-white transition cursor-pointer shadow-2xs"
                    >
                        {{ __('Crear Producto') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL 3: Add Variant Modal -->
    @if($addVariantModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-stone-900/50 backdrop-blur-xs">
            <div class="bg-white rounded-2xl border border-stone-200 shadow-2xl max-w-lg w-full p-6 space-y-4 animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center justify-between pb-2 border-b border-stone-100">
                    <h3 class="text-base font-extrabold text-stone-900">{{ __('Nuevo Subproducto / Variante') }}</h3>
                    <button wire:click="$set('addVariantModalOpen', false)" class="text-stone-400 hover:text-stone-700 text-sm font-bold cursor-pointer">✕</button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">{{ __('Nombre del Subproducto') }} *</label>
                        <input 
                            type="text" 
                            wire:model="newVariantName"
                            placeholder="ej. Foil Front & Back, Rush 24h, Plastic 20pt"
                            class="w-full px-3 py-1.5 bg-stone-50 border border-stone-200 rounded-lg text-xs font-semibold"
                        >
                        @error('newVariantName') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block font-bold text-stone-700 mb-1">{{ __('Proveedor de este Subproducto') }}</label>
                            <select 
                                wire:model="newVariantSupplierId"
                                class="w-full px-2.5 py-1.5 bg-stone-50 border border-stone-200 rounded-lg text-xs"
                            >
                                <option value="">{{ __('Sin proveedor específico') }}</option>
                                @foreach($this->suppliers as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-stone-700 mb-1">{{ __('Tiempo de Entrega (Turnaround)') }}</label>
                            <input 
                                type="text" 
                                wire:model="newVariantTurnaround"
                                placeholder="ej. 24h, 2-3 días hábiles"
                                class="w-full px-3 py-1.5 bg-stone-50 border border-stone-200 rounded-lg text-xs"
                            >
                        </div>
                    </div>

                    <div class="space-y-2 pt-1 border-t border-stone-100">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-stone-400 block">{{ __('Recursos de Diseño & Enlaces:') }}</span>
                        
                        <div>
                            <label class="block font-semibold text-stone-600 mb-0.5">{{ __('URL Web del Proveedor') }}</label>
                            <input 
                                type="text" 
                                wire:model="newVariantWebsiteUrl"
                                placeholder="https://4over.com/producto..."
                                class="w-full px-3 py-1.5 bg-stone-50 border border-stone-200 rounded-lg text-xs font-mono"
                            >
                            @error('newVariantWebsiteUrl') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block font-semibold text-stone-600 mb-0.5">{{ __('URL de Mockup') }}</label>
                                <input 
                                    type="text" 
                                    wire:model="newVariantMockupUrl"
                                    placeholder="https://.../mockup.zip"
                                    class="w-full px-3 py-1.5 bg-stone-50 border border-stone-200 rounded-lg text-xs font-mono"
                                >
                            </div>
                            <div>
                                <label class="block font-semibold text-stone-600 mb-0.5">{{ __('URL de Template') }}</label>
                                <input 
                                    type="text" 
                                    wire:model="newVariantTemplateUrl"
                                    placeholder="https://.../template.zip"
                                    class="w-full px-3 py-1.5 bg-stone-50 border border-stone-200 rounded-lg text-xs font-mono"
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Dual File Upload / URL Option for Subproduct Image -->
                    <div class="space-y-1.5 pt-1 border-t border-stone-100">
                        <label class="block font-bold text-stone-700 flex items-center justify-between">
                            <span class="flex items-center gap-1.5">
                                <x-lucide-image class="w-3.5 h-3.5 text-indigo-600" />
                                <span>{{ __('Imagen del Subproducto') }}</span>
                            </span>
                            <span class="text-[10px] text-stone-400 font-normal">{{ __('Subir archivo o pegar URL') }}</span>
                        </label>

                        <div class="p-3 bg-stone-50 rounded-xl border border-stone-200 space-y-2.5">
                            <div class="flex items-center gap-3">
                                <div x-data="{ imgError: false }" class="w-12 h-12 rounded-xl bg-stone-200 border border-stone-300 overflow-hidden shrink-0 flex items-center justify-center relative shadow-2xs">
                                    @if ($newVariantFile && method_exists($newVariantFile, 'temporaryUrl'))
                                        <img src="{{ $newVariantFile->temporaryUrl() }}" class="w-full h-full object-cover">
                                    @elseif ($newVariantImageUrl)
                                        <img src="{{ $newVariantImageUrl }}" x-show="!imgError" x-on:error="imgError = true" class="w-full h-full object-cover">
                                        <x-lucide-image x-show="imgError" x-cloak class="w-5 h-5 text-stone-400 stroke-[1.5]" />
                                    @else
                                        <x-lucide-image class="w-5 h-5 text-stone-400 stroke-[1.5]" />
                                    @endif
                                    <div wire:loading wire:target="newVariantFile" class="absolute inset-0 bg-stone-900/60 flex items-center justify-center text-white">
                                        <x-lucide-loader-2 class="w-4 h-4 animate-spin" />
                                    </div>
                                </div>

                                <div class="flex-1 min-w-0">
                                    <input 
                                        type="file" 
                                        id="newVariantFileInput"
                                        wire:model="newVariantFile" 
                                        accept="image/*"
                                        class="hidden"
                                    >
                                    <label 
                                        for="newVariantFileInput"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white hover:bg-stone-100 text-stone-700 text-xs font-semibold rounded-lg border border-stone-300 shadow-2xs transition cursor-pointer select-none"
                                    >
                                        <x-lucide-upload class="w-3.5 h-3.5 text-stone-600" />
                                        <span>{{ __('Subir Imagen...') }}</span>
                                    </label>
                                    <span class="block text-[10px] text-stone-400 mt-1">PNG, JPG, WEBP (máx 5MB)</span>
                                </div>
                            </div>

                            <div class="pt-2 border-t border-stone-200/60">
                                <label class="block text-[10px] font-semibold text-stone-500 mb-1">{{ __('O pegar URL directa de imagen:') }}</label>
                                <input 
                                    type="text" 
                                    wire:model.live="newVariantImageUrl"
                                    placeholder="https://ejemplo.com/subproducto.jpg"
                                    class="w-full px-2.5 py-1 bg-white border border-stone-200 rounded-lg text-xs font-mono focus:ring-1 focus:ring-stone-800"
                                >
                            </div>
                        </div>
                        @error('newVariantFile') <span class="text-rose-600 text-[10px] block">{{ $message }}</span> @enderror
                        @error('newVariantImageUrl') <span class="text-rose-600 text-[10px] block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-bold text-stone-700 mb-1">{{ __('Especificaciones de Material / Acabado') }}</label>
                        <textarea 
                            wire:model="newVariantSpecs"
                            rows="2"
                            placeholder="ej. 16pt Premium Cardstock con laminado brillante..."
                            class="w-full px-3 py-1.5 bg-stone-50 border border-stone-200 rounded-lg text-xs"
                        ></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-stone-100">
                    <button 
                        wire:click="$set('addVariantModalOpen', false)"
                        class="px-3.5 py-1.5 rounded-lg text-xs font-semibold text-stone-600 hover:bg-stone-100 transition cursor-pointer"
                    >
                        {{ __('Cancelar') }}
                    </button>
                    <button 
                        wire:click="createVariant"
                        class="px-4 py-1.5 rounded-lg text-xs font-bold bg-stone-900 hover:bg-stone-800 text-white transition cursor-pointer shadow-2xs"
                    >
                        {{ __('Crear Subproducto') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL 3.5: Edit Variant Modal -->
    @if($editVariantModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-stone-900/50 backdrop-blur-xs">
            <div class="bg-white rounded-2xl border border-stone-200 shadow-2xl max-w-lg w-full p-6 space-y-4 animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center justify-between pb-2 border-b border-stone-100">
                    <h3 class="text-base font-extrabold text-stone-900">{{ __('Editar Subproducto') }}</h3>
                    <button wire:click="$set('editVariantModalOpen', false)" class="text-stone-400 hover:text-stone-700 text-sm font-bold cursor-pointer">✕</button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">{{ __('Nombre del Subproducto') }} *</label>
                        <input 
                            type="text" 
                            wire:model="editVariantName"
                            placeholder="ej. Foil Front & Back, Rush 24h, Plastic 20pt"
                            class="w-full px-3 py-1.5 bg-stone-50 border border-stone-200 rounded-lg text-xs font-semibold"
                        >
                        @error('editVariantName') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block font-bold text-stone-700 mb-1">{{ __('Proveedor de este Subproducto') }}</label>
                            <select 
                                wire:model="editVariantSupplierId"
                                class="w-full px-2.5 py-1.5 bg-stone-50 border border-stone-200 rounded-lg text-xs"
                            >
                                <option value="">{{ __('Sin proveedor específico') }}</option>
                                @foreach($this->suppliers as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-stone-700 mb-1">{{ __('Tiempo de Entrega (Turnaround)') }}</label>
                            <input 
                                type="text" 
                                wire:model="editVariantTurnaround"
                                placeholder="ej. 24h, 2-3 días hábiles"
                                class="w-full px-3 py-1.5 bg-stone-50 border border-stone-200 rounded-lg text-xs"
                            >
                        </div>
                    </div>

                    <div class="space-y-2 pt-1 border-t border-stone-100">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-stone-400 block">{{ __('Recursos de Diseño & Enlaces:') }}</span>
                        
                        <div>
                            <label class="block font-semibold text-stone-600 mb-0.5">{{ __('URL Web del Proveedor') }}</label>
                            <input 
                                type="text" 
                                wire:model="editVariantWebsiteUrl"
                                placeholder="https://4over.com/producto..."
                                class="w-full px-3 py-1.5 bg-stone-50 border border-stone-200 rounded-lg text-xs font-mono"
                            >
                            @error('editVariantWebsiteUrl') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block font-semibold text-stone-600 mb-0.5">{{ __('URL de Mockup') }}</label>
                                <input 
                                    type="text" 
                                    wire:model="editVariantMockupUrl"
                                    placeholder="https://.../mockup.zip"
                                    class="w-full px-3 py-1.5 bg-stone-50 border border-stone-200 rounded-lg text-xs font-mono"
                                >
                            </div>
                            <div>
                                <label class="block font-semibold text-stone-600 mb-0.5">{{ __('URL de Template') }}</label>
                                <input 
                                    type="text" 
                                    wire:model="editVariantTemplateUrl"
                                    placeholder="https://.../template.zip"
                                    class="w-full px-3 py-1.5 bg-stone-50 border border-stone-200 rounded-lg text-xs font-mono"
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Dual File Upload / URL Option for Edit Subproduct Image -->
                    <div class="space-y-1.5 pt-1 border-t border-stone-100">
                        <label class="block font-bold text-stone-700 flex items-center justify-between">
                            <span class="flex items-center gap-1.5">
                                <x-lucide-image class="w-3.5 h-3.5 text-indigo-600" />
                                <span>{{ __('Imagen del Subproducto') }}</span>
                            </span>
                            <span class="text-[10px] text-stone-400 font-normal">{{ __('Subir archivo o pegar URL') }}</span>
                        </label>

                        <div class="p-3 bg-stone-50 rounded-xl border border-stone-200 space-y-2.5">
                            <div class="flex items-center gap-3">
                                <div x-data="{ imgError: false }" class="w-12 h-12 rounded-xl bg-stone-200 border border-stone-300 overflow-hidden shrink-0 flex items-center justify-center relative shadow-2xs">
                                    @if ($editVariantFile && method_exists($editVariantFile, 'temporaryUrl'))
                                        <img src="{{ $editVariantFile->temporaryUrl() }}" class="w-full h-full object-cover">
                                    @elseif ($editVariantImageUrl)
                                        <img src="{{ $editVariantImageUrl }}" x-show="!imgError" x-on:error="imgError = true" class="w-full h-full object-cover">
                                        <x-lucide-image x-show="imgError" x-cloak class="w-5 h-5 text-stone-400 stroke-[1.5]" />
                                    @else
                                        <x-lucide-image class="w-5 h-5 text-stone-400 stroke-[1.5]" />
                                    @endif
                                    <div wire:loading wire:target="editVariantFile" class="absolute inset-0 bg-stone-900/60 flex items-center justify-center text-white">
                                        <x-lucide-loader-2 class="w-4 h-4 animate-spin" />
                                    </div>
                                </div>

                                <div class="flex-1 min-w-0">
                                    <input 
                                        type="file" 
                                        id="editVariantFileInput"
                                        wire:model="editVariantFile" 
                                        accept="image/*"
                                        class="hidden"
                                    >
                                    <label 
                                        for="editVariantFileInput"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white hover:bg-stone-100 text-stone-700 text-xs font-semibold rounded-lg border border-stone-300 shadow-2xs transition cursor-pointer select-none"
                                    >
                                        <x-lucide-upload class="w-3.5 h-3.5 text-stone-600" />
                                        <span>{{ __('Subir Nueva Imagen...') }}</span>
                                    </label>
                                    <span class="block text-[10px] text-stone-400 mt-1">PNG, JPG, WEBP (máx 5MB)</span>
                                </div>
                            </div>

                            <div class="pt-2 border-t border-stone-200/60">
                                <label class="block text-[10px] font-semibold text-stone-500 mb-1">{{ __('O editar URL directa:') }}</label>
                                <input 
                                    type="text" 
                                    wire:model.live="editVariantImageUrl"
                                    placeholder="https://ejemplo.com/subproducto.jpg"
                                    class="w-full px-2.5 py-1 bg-white border border-stone-200 rounded-lg text-xs font-mono focus:ring-1 focus:ring-stone-800"
                                >
                            </div>
                        </div>
                        @error('editVariantFile') <span class="text-rose-600 text-[10px] block">{{ $message }}</span> @enderror
                        @error('editVariantImageUrl') <span class="text-rose-600 text-[10px] block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-bold text-stone-700 mb-1">{{ __('Especificaciones de Material / Acabado') }}</label>
                        <textarea 
                            wire:model="editVariantSpecs"
                            rows="2"
                            placeholder="ej. 16pt Premium Cardstock con laminado brillante..."
                            class="w-full px-3 py-1.5 bg-stone-50 border border-stone-200 rounded-lg text-xs"
                        ></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-2 pt-2 border-t border-stone-100">
                    <button 
                        wire:click="deleteVariant({{ $editingVariantId }})"
                        wire:confirm="{{ __('¿Estás seguro de eliminar este subproducto y todas sus escalas?') }}"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold text-rose-600 hover:bg-rose-50 border border-rose-200 transition cursor-pointer"
                    >
                        {{ __('Eliminar Subproducto') }}
                    </button>

                    <div class="flex items-center gap-2">
                        <button 
                            wire:click="$set('editVariantModalOpen', false)"
                            class="px-3.5 py-1.5 rounded-lg text-xs font-semibold text-stone-600 hover:bg-stone-100 transition cursor-pointer"
                        >
                            {{ __('Cancelar') }}
                        </button>
                        <button 
                            wire:click="saveVariant"
                            class="px-4 py-1.5 rounded-lg text-xs font-bold bg-stone-900 hover:bg-stone-800 text-white transition cursor-pointer shadow-2xs"
                        >
                            {{ __('Guardar Cambios') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL 4: Add Tier Modal -->
    @if($addTierModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-stone-900/50 backdrop-blur-xs">
            <div class="bg-white rounded-2xl border border-stone-200 shadow-2xl max-w-sm w-full p-5 space-y-4 animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center justify-between pb-2 border-b border-stone-100">
                    <h3 class="text-sm font-extrabold text-stone-900">{{ __('Nueva Escala de Cantidad') }}</h3>
                    <button wire:click="$set('addTierModalOpen', false)" class="text-stone-400 hover:text-stone-700 text-sm font-bold cursor-pointer">✕</button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">{{ __('Cantidad (unidades)') }} *</label>
                        <input 
                            type="number" 
                            wire:model="newTierQty"
                            class="w-full px-3 py-1.5 bg-stone-50 border border-stone-200 rounded-lg text-xs font-mono font-bold"
                        >
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block font-bold text-stone-700 mb-1">{{ __('Costo ($)') }} *</label>
                            <input 
                                type="number" 
                                step="0.01"
                                wire:model="newTierCost"
                                class="w-full px-3 py-1.5 bg-stone-50 border border-stone-200 rounded-lg text-xs font-mono"
                            >
                        </div>
                        <div>
                            <label class="block font-bold text-stone-700 mb-1">{{ __('% Margen') }} *</label>
                            <input 
                                type="number" 
                                step="1"
                                wire:model="newTierMarkup"
                                class="w-full px-3 py-1.5 bg-stone-50 border border-stone-200 rounded-lg text-xs font-mono"
                            >
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-stone-100">
                    <button 
                        wire:click="$set('addTierModalOpen', false)"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold text-stone-600 hover:bg-stone-100 transition cursor-pointer"
                    >
                        {{ __('Cancelar') }}
                    </button>
                    <button 
                        wire:click="createTier"
                        class="px-4 py-1.5 rounded-lg text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white transition cursor-pointer shadow-2xs"
                    >
                        {{ __('Agregar Escala') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL 5: Create Category Modal -->
    @if($createCategoryModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-stone-900/50 backdrop-blur-xs">
            <div class="bg-white rounded-2xl border border-stone-200 shadow-2xl max-w-md w-full p-6 space-y-4 animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center justify-between pb-2 border-b border-stone-100">
                    <h3 class="text-base font-extrabold text-stone-900">{{ __('Nueva Categoría') }}</h3>
                    <button wire:click="$set('createCategoryModalOpen', false)" class="text-stone-400 hover:text-stone-700 text-sm font-bold cursor-pointer">✕</button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">{{ __('Nombre de la Categoría') }} *</label>
                        <input 
                            type="text" 
                            wire:model="newCategoryName"
                            placeholder="ej. Stationary, Signs & Banners, Packaging"
                            class="w-full px-3 py-1.5 bg-stone-50 border border-stone-200 rounded-lg text-xs font-semibold"
                        >
                        @error('newCategoryName') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror
                    </div>

                    <!-- Dual File Upload / URL Option for Category Cover -->
                    <div class="space-y-1.5">
                        <label class="block font-bold text-stone-700 flex items-center justify-between">
                            <span class="flex items-center gap-1.5">
                                <x-lucide-image class="w-3.5 h-3.5 text-indigo-600" />
                                <span>{{ __('Imagen de Portada') }}</span>
                            </span>
                            <span class="text-[10px] text-stone-400 font-normal">{{ __('Subir archivo o pegar URL') }}</span>
                        </label>

                        <div class="p-3 bg-stone-50 rounded-xl border border-stone-200 space-y-2.5">
                            <div class="flex items-center gap-3">
                                <div x-data="{ imgError: false }" class="w-16 h-12 rounded-lg bg-stone-200 border border-stone-300 overflow-hidden shrink-0 flex items-center justify-center relative shadow-2xs">
                                    @if ($newCategoryFile && method_exists($newCategoryFile, 'temporaryUrl'))
                                        <img src="{{ $newCategoryFile->temporaryUrl() }}" class="w-full h-full object-cover">
                                    @elseif ($newCategoryImageUrl)
                                        <img src="{{ $newCategoryImageUrl }}" x-show="!imgError" x-on:error="imgError = true" class="w-full h-full object-cover">
                                        <x-lucide-image x-show="imgError" x-cloak class="w-5 h-5 text-stone-400 stroke-[1.5]" />
                                    @else
                                        <x-lucide-image class="w-5 h-5 text-stone-400 stroke-[1.5]" />
                                    @endif
                                    <div wire:loading wire:target="newCategoryFile" class="absolute inset-0 bg-stone-900/60 flex items-center justify-center text-white">
                                        <x-lucide-loader-2 class="w-4 h-4 animate-spin" />
                                    </div>
                                </div>

                                <div class="flex-1 min-w-0">
                                    <input 
                                        type="file" 
                                        id="newCategoryFileInput"
                                        wire:model="newCategoryFile" 
                                        accept="image/*"
                                        class="hidden"
                                    >
                                    <label 
                                        for="newCategoryFileInput"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white hover:bg-stone-100 text-stone-700 text-xs font-semibold rounded-lg border border-stone-300 shadow-2xs transition cursor-pointer select-none"
                                    >
                                        <x-lucide-upload class="w-3.5 h-3.5 text-stone-600" />
                                        <span>{{ __('Subir Imagen...') }}</span>
                                    </label>
                                    <span class="block text-[10px] text-stone-400 mt-1">PNG, JPG, WEBP (máx 5MB)</span>
                                </div>
                            </div>

                            <div class="pt-2 border-t border-stone-200/60">
                                <label class="block text-[10px] font-semibold text-stone-500 mb-1">{{ __('O pegar URL directa de imagen:') }}</label>
                                <input 
                                    type="text" 
                                    wire:model.live="newCategoryImageUrl"
                                    placeholder="https://ejemplo.com/portada.jpg"
                                    class="w-full px-2.5 py-1 bg-white border border-stone-200 rounded-lg text-xs font-mono focus:ring-1 focus:ring-stone-800"
                                >
                            </div>
                        </div>
                        @error('newCategoryFile') <span class="text-rose-600 text-[10px] block">{{ $message }}</span> @enderror
                        @error('newCategoryImageUrl') <span class="text-rose-600 text-[10px] block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-bold text-stone-700 mb-1">{{ __('Descripción') }}</label>
                        <textarea 
                            wire:model="newCategoryDescription"
                            rows="2"
                            placeholder="Descripción general de la categoría..."
                            class="w-full px-3 py-1.5 bg-stone-50 border border-stone-200 rounded-lg text-xs"
                        ></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-stone-100">
                    <button 
                        wire:click="$set('createCategoryModalOpen', false)"
                        class="px-3.5 py-1.5 rounded-lg text-xs font-semibold text-stone-600 hover:bg-stone-100 transition cursor-pointer"
                    >
                        {{ __('Cancelar') }}
                    </button>
                    <button 
                        wire:click="createCategory"
                        class="px-4 py-1.5 rounded-lg text-xs font-bold bg-stone-900 hover:bg-stone-800 text-white transition cursor-pointer shadow-2xs"
                    >
                        {{ __('Crear Categoría') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL 6: Edit Category Modal -->
    @if($editCategoryModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-stone-900/50 backdrop-blur-xs">
            <div class="bg-white rounded-2xl border border-stone-200 shadow-2xl max-w-md w-full p-6 space-y-4 animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center justify-between pb-2 border-b border-stone-100">
                    <h3 class="text-base font-extrabold text-stone-900">{{ __('Editar Categoría') }}</h3>
                    <button wire:click="$set('editCategoryModalOpen', false)" class="text-stone-400 hover:text-stone-700 text-sm font-bold cursor-pointer">✕</button>
                </div>

                @error('deleteCategoryError')
                    <div class="p-2.5 bg-rose-50 border border-rose-200 text-rose-800 text-xs rounded-xl font-medium">
                        {{ $message }}
                    </div>
                @enderror

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">{{ __('Nombre de la Categoría') }} *</label>
                        <input 
                            type="text" 
                            wire:model="editCategoryName"
                            placeholder="ej. Stationary, Apparel"
                            class="w-full px-3 py-1.5 bg-stone-50 border border-stone-200 rounded-lg text-xs font-semibold"
                        >
                        @error('editCategoryName') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror
                    </div>

                    <!-- Dual File Upload / URL Option for Edit Category Cover -->
                    <div class="space-y-1.5">
                        <label class="block font-bold text-stone-700 flex items-center justify-between">
                            <span class="flex items-center gap-1.5">
                                <x-lucide-image class="w-3.5 h-3.5 text-indigo-600" />
                                <span>{{ __('Imagen de Portada') }}</span>
                            </span>
                            <span class="text-[10px] text-stone-400 font-normal">{{ __('Subir archivo o pegar URL') }}</span>
                        </label>

                        <div class="p-3 bg-stone-50 rounded-xl border border-stone-200 space-y-2.5">
                            <div class="flex items-center gap-3">
                                <div x-data="{ imgError: false }" class="w-16 h-12 rounded-lg bg-stone-200 border border-stone-300 overflow-hidden shrink-0 flex items-center justify-center relative shadow-2xs">
                                    @if ($editCategoryFile && method_exists($editCategoryFile, 'temporaryUrl'))
                                        <img src="{{ $editCategoryFile->temporaryUrl() }}" class="w-full h-full object-cover">
                                    @elseif ($editCategoryImageUrl)
                                        <img src="{{ $editCategoryImageUrl }}" x-show="!imgError" x-on:error="imgError = true" class="w-full h-full object-cover">
                                        <x-lucide-image x-show="imgError" x-cloak class="w-5 h-5 text-stone-400 stroke-[1.5]" />
                                    @else
                                        <x-lucide-image class="w-5 h-5 text-stone-400 stroke-[1.5]" />
                                    @endif
                                    <div wire:loading wire:target="editCategoryFile" class="absolute inset-0 bg-stone-900/60 flex items-center justify-center text-white">
                                        <x-lucide-loader-2 class="w-4 h-4 animate-spin" />
                                    </div>
                                </div>

                                <div class="flex-1 min-w-0">
                                    <input 
                                        type="file" 
                                        id="editCategoryFileInput"
                                        wire:model="editCategoryFile" 
                                        accept="image/*"
                                        class="hidden"
                                    >
                                    <label 
                                        for="editCategoryFileInput"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white hover:bg-stone-100 text-stone-700 text-xs font-semibold rounded-lg border border-stone-300 shadow-2xs transition cursor-pointer select-none"
                                    >
                                        <x-lucide-upload class="w-3.5 h-3.5 text-stone-600" />
                                        <span>{{ __('Subir Nueva Imagen...') }}</span>
                                    </label>
                                    <span class="block text-[10px] text-stone-400 mt-1">PNG, JPG, WEBP (máx 5MB)</span>
                                </div>
                            </div>

                            <div class="pt-2 border-t border-stone-200/60">
                                <label class="block text-[10px] font-semibold text-stone-500 mb-1">{{ __('O editar URL directa:') }}</label>
                                <input 
                                    type="text" 
                                    wire:model.live="editCategoryImageUrl"
                                    placeholder="https://ejemplo.com/portada-categoria.jpg"
                                    class="w-full px-2.5 py-1 bg-white border border-stone-200 rounded-lg text-xs font-mono focus:ring-1 focus:ring-stone-800"
                                >
                            </div>
                        </div>
                        @error('editCategoryFile') <span class="text-rose-600 text-[10px] block">{{ $message }}</span> @enderror
                        @error('editCategoryImageUrl') <span class="text-rose-600 text-[10px] block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-bold text-stone-700 mb-1">{{ __('Descripción de la Categoría') }}</label>
                        <textarea 
                            wire:model="editCategoryDescription"
                            rows="2"
                            placeholder="Descripción de los productos pertenecientes a esta categoría..."
                            class="w-full px-3 py-1.5 bg-stone-50 border border-stone-200 rounded-lg text-xs"
                        ></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-2 pt-2 border-t border-stone-100">
                    <button 
                        wire:click="deleteCategory({{ $editingCategoryId }})"
                        wire:confirm="{{ __('¿Estás seguro de eliminar esta categoría?') }}"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold text-rose-600 hover:bg-rose-50 border border-rose-200 transition cursor-pointer"
                    >
                        {{ __('Eliminar Categoría') }}
                    </button>

                    <div class="flex items-center gap-2">
                        <button 
                            wire:click="$set('editCategoryModalOpen', false)"
                            class="px-3.5 py-1.5 rounded-lg text-xs font-semibold text-stone-600 hover:bg-stone-100 transition cursor-pointer"
                        >
                            {{ __('Cancelar') }}
                        </button>
                        <button 
                            wire:click="saveCategory"
                            class="px-4 py-1.5 rounded-lg text-xs font-bold bg-stone-900 hover:bg-stone-800 text-white transition cursor-pointer shadow-2xs"
                        >
                            {{ __('Guardar Cambios') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL 7: Edit Product Modal -->
    @if($editProductModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-stone-900/50 backdrop-blur-xs">
            <div class="bg-white rounded-2xl border border-stone-200 shadow-2xl max-w-md w-full p-6 space-y-4 animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center justify-between pb-2 border-b border-stone-100">
                    <h3 class="text-base font-extrabold text-stone-900">{{ __('Editar Producto') }}</h3>
                    <button wire:click="$set('editProductModalOpen', false)" class="text-stone-400 hover:text-stone-700 text-sm font-bold cursor-pointer">✕</button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">{{ __('Nombre del Producto') }} *</label>
                        <input 
                            type="text" 
                            wire:model="editProductName"
                            placeholder="ej. Business Card, Roll-Up Banner"
                            class="w-full px-3 py-1.5 bg-stone-50 border border-stone-200 rounded-lg text-xs font-semibold"
                        >
                        @error('editProductName') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block font-bold text-stone-700 mb-1">{{ __('Categoría') }} *</label>
                            <select 
                                wire:model="editProductCategoryId"
                                class="w-full px-2.5 py-1.5 bg-stone-50 border border-stone-200 rounded-lg text-xs"
                            >
                                @foreach($this->categories as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-stone-700 mb-1">{{ __('Proveedor Principal') }}</label>
                            <select 
                                wire:model="editProductSupplierId"
                                class="w-full px-2.5 py-1.5 bg-stone-50 border border-stone-200 rounded-lg text-xs"
                            >
                                <option value="">{{ __('Sin proveedor principal') }}</option>
                                @foreach($this->suppliers as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-stone-700 mb-1">{{ __('Tamaños Posibles') }} (separados por coma)</label>
                        <input 
                            type="text" 
                            wire:model="editProductSizes"
                            placeholder="ej. 33'' x 81'', 38'' x 81''"
                            class="w-full px-3 py-1.5 bg-stone-50 border border-stone-200 rounded-lg text-xs"
                        >
                    </div>

                    <!-- Dual File Upload / URL Option for Edit Product Thumbnail -->
                    <div class="space-y-1.5">
                        <label class="block font-bold text-stone-700 flex items-center justify-between">
                            <span class="flex items-center gap-1.5">
                                <x-lucide-image class="w-3.5 h-3.5 text-indigo-600" />
                                <span>{{ __('Imagen de Miniatura') }}</span>
                            </span>
                            <span class="text-[10px] text-stone-400 font-normal">{{ __('Subir archivo o pegar URL') }}</span>
                        </label>

                        <div class="p-3 bg-stone-50 rounded-xl border border-stone-200 space-y-2.5">
                            <div class="flex items-center gap-3">
                                <div x-data="{ imgError: false }" class="w-12 h-12 rounded-xl bg-stone-200 border border-stone-300 overflow-hidden shrink-0 flex items-center justify-center relative shadow-2xs">
                                    @if ($editProductFile && method_exists($editProductFile, 'temporaryUrl'))
                                        <img src="{{ $editProductFile->temporaryUrl() }}" class="w-full h-full object-cover">
                                    @elseif ($editProductImageUrl)
                                        <img src="{{ $editProductImageUrl }}" x-show="!imgError" x-on:error="imgError = true" class="w-full h-full object-cover">
                                        <x-lucide-box x-show="imgError" x-cloak class="w-5 h-5 text-stone-400 stroke-[1.5]" />
                                    @else
                                        <x-lucide-box class="w-5 h-5 text-stone-400 stroke-[1.5]" />
                                    @endif
                                    <div wire:loading wire:target="editProductFile" class="absolute inset-0 bg-stone-900/60 flex items-center justify-center text-white">
                                        <x-lucide-loader-2 class="w-4 h-4 animate-spin" />
                                    </div>
                                </div>

                                <div class="flex-1 min-w-0">
                                    <input 
                                        type="file" 
                                        id="editProductFileInput"
                                        wire:model="editProductFile" 
                                        accept="image/*"
                                        class="hidden"
                                    >
                                    <label 
                                        for="editProductFileInput"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white hover:bg-stone-100 text-stone-700 text-xs font-semibold rounded-lg border border-stone-300 shadow-2xs transition cursor-pointer select-none"
                                    >
                                        <x-lucide-upload class="w-3.5 h-3.5 text-stone-600" />
                                        <span>{{ __('Subir Nueva Imagen...') }}</span>
                                    </label>
                                    <span class="block text-[10px] text-stone-400 mt-1">PNG, JPG, WEBP (máx 5MB)</span>
                                </div>
                            </div>

                            <div class="pt-2 border-t border-stone-200/60">
                                <label class="block text-[10px] font-semibold text-stone-500 mb-1">{{ __('O editar URL directa:') }}</label>
                                <input 
                                    type="text" 
                                    wire:model.live="editProductImageUrl"
                                    placeholder="https://ejemplo.com/producto.jpg"
                                    class="w-full px-2.5 py-1 bg-white border border-stone-200 rounded-lg text-xs font-mono focus:ring-1 focus:ring-stone-800"
                                >
                            </div>
                        </div>
                        @error('editProductFile') <span class="text-rose-600 text-[10px] block">{{ $message }}</span> @enderror
                        @error('editProductImageUrl') <span class="text-rose-600 text-[10px] block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-bold text-stone-700 mb-1">{{ __('Descripción / Notas') }}</label>
                        <textarea 
                            wire:model="editProductDescription"
                            rows="2"
                            placeholder="Detalles sobre el producto..."
                            class="w-full px-3 py-1.5 bg-stone-50 border border-stone-200 rounded-lg text-xs"
                        ></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-2 pt-2 border-t border-stone-100">
                    <button 
                        wire:click="deleteProduct({{ $editingProductId }})"
                        wire:confirm="{{ __('¿Estás seguro de eliminar este producto y todas sus variantes?') }}"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold text-rose-600 hover:bg-rose-50 border border-rose-200 transition cursor-pointer"
                    >
                        {{ __('Eliminar Producto') }}
                    </button>

                    <div class="flex items-center gap-2">
                        <button 
                            wire:click="$set('editProductModalOpen', false)"
                            class="px-3.5 py-1.5 rounded-lg text-xs font-semibold text-stone-600 hover:bg-stone-100 transition cursor-pointer"
                        >
                            {{ __('Cancelar') }}
                        </button>
                        <button 
                            wire:click="saveProduct"
                            class="px-4 py-1.5 rounded-lg text-xs font-bold bg-stone-900 hover:bg-stone-800 text-white transition cursor-pointer shadow-2xs"
                        >
                            {{ __('Guardar Cambios') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
