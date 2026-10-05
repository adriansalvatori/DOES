<div class="min-h-screen bg-[#fafaf9] p-4 sm:p-6 lg:p-8 space-y-6">
    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-stone-200 shadow-2xs">
        <div class="space-y-1">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-indigo-100 text-indigo-700 flex items-center justify-center shrink-0">
                    <x-lucide-folder-down class="w-4 h-4" />
                </div>
                <h1 class="text-xl sm:text-2xl font-black text-stone-900 tracking-tight">
                    {{ __('Recursos de Diseño & Especificaciones') }}
                </h1>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                    {{ __('Diseño & Producción') }}
                </span>
            </div>
            <p class="text-xs text-stone-500">
                {{ __('Biblioteca compartida de enlaces web, mockups para presentaciones, plantillas técnicas de impresión y tamaños disponibles.') }}
            </p>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            <a 
                href="{{ route('pricing') }}"
                wire:navigate
                class="px-3.5 py-2 rounded-xl text-xs font-bold bg-stone-900 hover:bg-stone-800 text-white transition flex items-center gap-2 cursor-pointer shadow-2xs"
            >
                <x-lucide-badge-percent class="w-4 h-4" />
                <span>{{ __('Ver Lista de Precios') }}</span>
            </a>
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

    <!-- Toolbar: Filters & Search -->
    <div class="bg-white rounded-xl border border-stone-200 p-4 shadow-2xs space-y-3">
        <!-- Category Pills -->
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

        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-2 border-t border-stone-100">
            <!-- Search Input -->
            <div class="relative w-full sm:w-80">
                <x-lucide-search class="w-4 h-4 text-stone-400 absolute left-3 top-2.5" />
                <input 
                    type="text" 
                    wire:model.live.debounce.250ms="search"
                    placeholder="{{ __('Buscar recursos por producto, material...') }}"
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

    <!-- Products Resources Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($this->products as $product)
            <div class="bg-white rounded-2xl border border-stone-200 shadow-2xs hover:shadow-md transition p-5 flex flex-col justify-between space-y-4 group">
                <!-- Top Card Info -->
                <div class="space-y-3">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-stone-400 block">
                                {{ $product->category?->name }}
                            </span>
                            <h2 class="text-base font-extrabold text-stone-900 group-hover:text-indigo-600 transition">
                                {{ $product->name }}
                            </h2>
                        </div>

                        <div class="flex items-center gap-1.5">
                            @if($product->supplier)
                                @php $pSupStyle = $product->supplier->getBadgeStyle(); @endphp
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border transition-colors" style="{{ $pSupStyle['inline'] }}">
                                    {{ $product->supplier->name }}
                                </span>
                            @endif

                            @if(auth()->user()?->canManagePricing())
                                <button 
                                    wire:click="openEditModal({{ $product->id }})"
                                    title="{{ __('Editar enlaces y medidas') }}"
                                    class="p-1 rounded-md text-stone-400 hover:text-stone-800 hover:bg-stone-100 transition cursor-pointer"
                                >
                                    <x-lucide-pencil class="w-3.5 h-3.5" />
                                </button>
                            @endif
                        </div>
                    </div>

                    @if($product->description)
                        <p class="text-xs text-stone-500 leading-relaxed text-left">
                            {{ $product->description }}
                        </p>
                    @endif

                    <!-- Tamaños y Medidas Disponibles -->
                    @php $sizes = $product->getSizesList(); @endphp
                    @if(!empty($sizes))
                        <div class="space-y-1 pt-1 border-t border-stone-100">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-stone-400 block">{{ __('Medidas / Tamaños Posibles:') }}</span>
                            <div class="flex flex-wrap gap-1">
                                @foreach($sizes as $size)
                                    <span 
                                        x-data="{ copied: false }"
                                        @click="
                                            navigator.clipboard.writeText('{{ addslashes($size) }}');
                                            copied = true;
                                            setTimeout(() => copied = false, 1500);
                                        "
                                        title="{{ __('Clic para copiar tamaño') }}"
                                        class="px-2 py-0.5 rounded-md text-[10.5px] font-mono font-medium bg-stone-50 hover:bg-stone-100 text-stone-700 border border-stone-200 cursor-pointer transition flex items-center gap-1"
                                    >
                                        <span x-text="copied ? '✓ Copiado' : '{{ $size }}'"></span>
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Subproductos / Variantes & Enlaces por Subproducto -->
                    @if($product->variants->isNotEmpty())
                        <div class="space-y-2.5 pt-2 border-t border-stone-100">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-stone-400 block">{{ __('Subproductos & Recursos por Proveedor:') }}</span>
                            <div class="space-y-2 divide-y divide-stone-100">
                                @foreach($product->variants as $variant)
                                    @php 
                                        $vSupplier = $variant->getEffectiveSupplier();
                                        $vWeb = $variant->getWebsiteUrl();
                                        $vMockup = $variant->getMockupUrl();
                                        $vTemplate = $variant->getTemplateUrl();
                                    @endphp
                                    <div class="pt-2 first:pt-0 space-y-1.5">
                                        <div class="flex items-center justify-between gap-1 flex-wrap">
                                            <span class="text-xs font-extrabold text-stone-800">
                                                {{ $variant->name }}
                                            </span>
                                            @if($vSupplier)
                                                @php $vSupStyle = $vSupplier->getBadgeStyle(); @endphp
                                                <span class="inline-flex items-center gap-1 px-1.5 py-0.2 rounded text-[9.5px] font-bold border transition-colors" style="{{ $vSupStyle['inline'] }}">
                                                    <x-lucide-building-2 class="w-3 h-3" style="color: {{ $vSupStyle['text'] }}" />
                                                    <span>{{ $vSupplier->name }}</span>
                                                </span>
                                            @endif
                                        </div>

                                        @if($variant->specs)
                                            <p class="text-[10.5px] text-stone-500 italic leading-snug">
                                                {{ $variant->specs }}
                                            </p>
                                        @endif

                                        <div class="flex items-center gap-1 flex-wrap pt-0.5">
                                            @if($vWeb)
                                                <a href="{{ $vWeb }}" target="_blank" rel="noopener noreferrer" class="px-2 py-0.5 rounded text-[10.5px] font-bold bg-stone-50 hover:bg-stone-100 text-stone-700 border border-stone-200 transition flex items-center gap-1">
                                                    <x-lucide-globe class="w-3 h-3 text-emerald-600" />
                                                    <span>{{ __('Web') }}</span>
                                                </a>
                                            @endif

                                            @if($vMockup)
                                                <a href="{{ $vMockup }}" target="_blank" rel="noopener noreferrer" class="px-2 py-0.5 rounded text-[10.5px] font-bold bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 transition flex items-center gap-1">
                                                    <x-lucide-image class="w-3 h-3 text-amber-600" />
                                                    <span>{{ __('Mockup') }}</span>
                                                </a>
                                            @endif

                                            @if($vTemplate)
                                                <a href="{{ $vTemplate }}" target="_blank" rel="noopener noreferrer" class="px-2 py-0.5 rounded text-[10.5px] font-bold bg-indigo-50 hover:bg-indigo-100 text-indigo-800 border border-indigo-200 transition flex items-center gap-1">
                                                    <x-lucide-box class="w-3 h-3 text-indigo-600" />
                                                    <span>{{ __('Template') }}</span>
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="col-span-full py-16 bg-white rounded-2xl border border-stone-200 text-center text-stone-400">
                <x-lucide-folder-open class="w-10 h-10 mx-auto mb-2 text-stone-300" />
                <div class="text-base font-bold text-stone-700">{{ __('No se encontraron productos o recursos.') }}</div>
                <div class="text-xs text-stone-400 mt-1">{{ __('Verifica los filtros seleccionados o agrega enlaces desde la gestión de productos.') }}</div>
            </div>
        @endforelse
    </div>

    <!-- MODAL: Edit Resource Links (Manager / Admin) -->
    @if($editModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-stone-900/50 backdrop-blur-xs">
            <div class="bg-white rounded-2xl border border-stone-200 shadow-2xl max-w-lg w-full p-6 space-y-4 animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center justify-between pb-2 border-b border-stone-100">
                    <div>
                        <h3 class="text-base font-extrabold text-stone-900">{{ __('Editar Recursos de Diseño') }}</h3>
                        <p class="text-xs text-stone-500">{{ $editProductName }}</p>
                    </div>
                    <button wire:click="$set('editModalOpen', false)" class="text-stone-400 hover:text-stone-700 text-sm font-bold cursor-pointer">✕</button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block font-bold text-stone-700 mb-1 flex items-center gap-1.5">
                            <x-lucide-globe class="w-3.5 h-3.5 text-emerald-600" />
                            <span>{{ __('URL Página del Producto / Proveedor') }}</span>
                        </label>
                        <input 
                            type="url" 
                            wire:model="editWebsiteUrl"
                            placeholder="https://proveedor.com/producto"
                            class="w-full px-3 py-1.5 bg-stone-50 border border-stone-200 rounded-lg text-xs font-mono"
                        >
                        @error('editWebsiteUrl') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-bold text-stone-700 mb-1 flex items-center gap-1.5">
                            <x-lucide-image class="w-3.5 h-3.5 text-amber-600" />
                            <span>{{ __('URL Enlace de Mockups (Drive / Dropbox / Figma)') }}</span>
                        </label>
                        <input 
                            type="url" 
                            wire:model="editMockupUrl"
                            placeholder="https://drive.google.com/..."
                            class="w-full px-3 py-1.5 bg-stone-50 border border-stone-200 rounded-lg text-xs font-mono"
                        >
                        @error('editMockupUrl') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-bold text-stone-700 mb-1 flex items-center gap-1.5">
                            <x-lucide-box class="w-3.5 h-3.5 text-indigo-600" />
                            <span>{{ __('URL Enlace de Templates / Plantillas Técnicas') }}</span>
                        </label>
                        <input 
                            type="url" 
                            wire:model="editTemplateUrl"
                            placeholder="https://proveedor.com/templates/archivo.pdf"
                            class="w-full px-3 py-1.5 bg-stone-50 border border-stone-200 rounded-lg text-xs font-mono"
                        >
                        @error('editTemplateUrl') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-bold text-stone-700 mb-1 flex items-center gap-1.5">
                            <x-lucide-ruler class="w-3.5 h-3.5 text-stone-500" />
                            <span>{{ __('Medidas / Tamaños Posibles (separadas por coma)') }}</span>
                        </label>
                        <input 
                            type="text" 
                            wire:model="editSizes"
                            placeholder="ej. 33'' x 81'', 38'' x 81'', 60'' x 81''"
                            class="w-full px-3 py-1.5 bg-stone-50 border border-stone-200 rounded-lg text-xs"
                        >
                    </div>

                    <div>
                        <label class="block font-bold text-stone-700 mb-1">{{ __('Descripción del Producto') }}</label>
                        <textarea 
                            wire:model="editDescription"
                            rows="2"
                            class="w-full px-3 py-1.5 bg-stone-50 border border-stone-200 rounded-lg text-xs"
                        ></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-stone-100">
                    <button 
                        wire:click="$set('editModalOpen', false)"
                        class="px-3.5 py-1.5 rounded-lg text-xs font-semibold text-stone-600 hover:bg-stone-100 transition cursor-pointer"
                    >
                        {{ __('Cancelar') }}
                    </button>
                    <button 
                        wire:click="saveResourceLinks"
                        class="px-4 py-1.5 rounded-lg text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white transition cursor-pointer shadow-2xs"
                    >
                        {{ __('Guardar Enlaces') }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
