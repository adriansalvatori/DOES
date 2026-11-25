<div class="space-y-4">
    <x-ui.page-header :title="__('Recursos de diseño')" :count="$this->products->count()" :subtitle="__('Biblioteca compartida de enlaces web, mockups para presentaciones, plantillas técnicas de impresión y tamaños disponibles.')">
        <x-slot:actions>
            <a href="{{ route('pricing') }}" wire:navigate class="ui-btn ui-btn-secondary"><x-lucide-badge-percent />{{ __('Ver Lista de Precios') }}</a>
        </x-slot:actions>
        <x-slot:toolbar>
            <div class="relative w-72">
                <x-lucide-search class="pointer-events-none absolute left-2.5 top-2 h-4 w-4 text-ink-3" />
                <input type="text" wire:model.live.debounce.250ms="search" placeholder="{{ __('Buscar recursos por producto, material...') }}" class="ui-input pl-8">
            </div>
            <select wire:model.live="filterCategory" class="ui-select w-48" aria-label="{{ __('Categoría') }}">
                <option value="all">{{ __('Todas las categorías') }}</option>
                @foreach($this->categories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                @endforeach
            </select>
            <select wire:model.live="filterSupplier" class="ui-select w-48" aria-label="{{ __('Proveedor') }}">
                <option value="all">{{ __('Todos los proveedores') }}</option>
                @foreach($this->suppliers as $sup)
                    <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                @endforeach
            </select>
            @if($search !== '' || $filterCategory !== 'all' || $filterSupplier !== 'all')
                <button type="button" wire:click="resetFilters" class="ui-btn ui-btn-ghost"><x-lucide-rotate-ccw />{{ __('Limpiar') }}</button>
            @endif
        </x-slot:toolbar>
    </x-ui.page-header>

    @if($feedbackMessage)
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="flex items-center justify-between gap-2 rounded-card border border-line bg-surface px-3 py-2 text-body text-ink">
            <span class="flex items-center gap-2"><x-lucide-check-circle-2 class="h-4 w-4 shrink-0 text-ok" />{{ $feedbackMessage }}</span>
            <button type="button" @click="show = false" class="ui-btn ui-btn-ghost ui-btn-icon ui-btn-sm" aria-label="{{ __('Cerrar') }}"><x-lucide-x /></button>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
        @forelse($this->products as $product)
            <div wire:key="res-{{ $product->id }}" class="ui-card flex flex-col space-y-3 p-4">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <span class="block text-meta text-ink-3">{{ $product->category?->name }}</span>
                        <h2 class="ui-section-title">{{ $product->name }}</h2>
                    </div>
                    <div class="flex shrink-0 items-center gap-1.5">
                        @if($product->supplier)
                            <x-ui.tag tone="neutral">{{ $product->supplier->name }}</x-ui.tag>
                        @endif
                        @if(auth()->user()?->canManagePricing())
                            <button type="button" wire:click="openEditModal({{ $product->id }})" title="{{ __('Editar enlaces y medidas') }}" aria-label="{{ __('Editar enlaces y medidas') }}" class="ui-btn ui-btn-ghost ui-btn-icon ui-btn-sm"><x-lucide-pencil /></button>
                        @endif
                    </div>
                </div>

                @if($product->description)
                    <p class="text-body text-ink-2">{{ $product->description }}</p>
                @endif

                @php $sizes = $product->getSizesList(); @endphp
                @if(!empty($sizes))
                    <div class="space-y-1.5 border-t border-line pt-3">
                        <span class="block text-meta font-medium text-ink-3">{{ __('Medidas / Tamaños Posibles:') }}</span>
                        <div class="flex flex-wrap gap-1">
                            @foreach($sizes as $size)
                                <button type="button" x-data="{ copied: false }"
                                        @click="navigator.clipboard.writeText(@js($size)); copied = true; setTimeout(() => copied = false, 1500);"
                                        title="{{ __('Clic para copiar tamaño') }}"
                                        class="ui-chip h-6 tabular-nums">
                                    <span x-text="copied ? @js(__('Copiado')) : @js($size)"></span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if($product->variants->isNotEmpty())
                    <div class="space-y-2 border-t border-line pt-3">
                        <span class="block text-meta font-medium text-ink-3">{{ __('Subproductos & Recursos por Proveedor:') }}</span>
                        <div class="divide-y divide-line">
                            @foreach($product->variants as $variant)
                                @php
                                    $vSupplier = $variant->getEffectiveSupplier();
                                    $vWeb = $variant->getWebsiteUrl();
                                    $vMockup = $variant->getMockupUrl();
                                    $vTemplate = $variant->getTemplateUrl();
                                @endphp
                                <div class="space-y-1.5 py-2 first:pt-0 last:pb-0">
                                    <div class="flex flex-wrap items-center justify-between gap-1">
                                        <span class="text-body font-medium text-ink">{{ $variant->name }}</span>
                                        @if($vSupplier)<span class="text-meta text-ink-3">{{ $vSupplier->name }}</span>@endif
                                    </div>
                                    @if($variant->specs)
                                        <p class="text-meta text-ink-3">{{ $variant->specs }}</p>
                                    @endif
                                    @if($vWeb || $vMockup || $vTemplate)
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            @if($vWeb)<a href="{{ $vWeb }}" target="_blank" rel="noopener noreferrer" class="ui-btn ui-btn-secondary ui-btn-sm"><x-lucide-globe />{{ __('Web') }}</a>@endif
                                            @if($vMockup)<a href="{{ $vMockup }}" target="_blank" rel="noopener noreferrer" class="ui-btn ui-btn-secondary ui-btn-sm"><x-lucide-image />{{ __('Mockup') }}</a>@endif
                                            @if($vTemplate)<a href="{{ $vTemplate }}" target="_blank" rel="noopener noreferrer" class="ui-btn ui-btn-secondary ui-btn-sm"><x-lucide-box />{{ __('Template') }}</a>@endif
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <div class="ui-card col-span-full">
                <x-ui.empty icon="folder-open" :title="__('No se encontraron productos o recursos.')" :description="__('Verifica los filtros seleccionados o agrega enlaces desde la gestión de productos.')" />
            </div>
        @endforelse
    </div>

    @if($editModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-lg space-y-4 rounded-panel border border-line bg-surface p-6 shadow-panel">
                <div class="flex items-center justify-between border-b border-line pb-3">
                    <div>
                        <h3 class="ui-section-title">{{ __('Editar Recursos de Diseño') }}</h3>
                        <p class="text-body text-ink-3">{{ $editProductName }}</p>
                    </div>
                    <button type="button" wire:click="$set('editModalOpen', false)" class="ui-btn ui-btn-ghost ui-btn-icon ui-btn-sm" aria-label="{{ __('Cerrar') }}"><x-lucide-x /></button>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="ui-label">{{ __('URL Página del Producto / Proveedor') }}</label>
                        <input type="url" wire:model="editWebsiteUrl" placeholder="https://proveedor.com/producto" class="ui-input">
                        @error('editWebsiteUrl') <span class="text-meta text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="ui-label">{{ __('URL Enlace de Mockups (Drive / Dropbox / Figma)') }}</label>
                        <input type="url" wire:model="editMockupUrl" placeholder="https://drive.google.com/..." class="ui-input">
                        @error('editMockupUrl') <span class="text-meta text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="ui-label">{{ __('URL Enlace de Templates / Plantillas Técnicas') }}</label>
                        <input type="url" wire:model="editTemplateUrl" placeholder="https://proveedor.com/templates/archivo.pdf" class="ui-input">
                        @error('editTemplateUrl') <span class="text-meta text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="ui-label">{{ __('Medidas / Tamaños Posibles (separadas por coma)') }}</label>
                        <input type="text" wire:model="editSizes" placeholder="{{ __("ej. 33'' x 81'', 38'' x 81'', 60'' x 81''") }}" class="ui-input">
                    </div>
                    <div>
                        <label class="ui-label">{{ __('Descripción del Producto') }}</label>
                        <textarea wire:model="editDescription" rows="2" class="ui-input"></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-line pt-3">
                    <button type="button" wire:click="$set('editModalOpen', false)" class="ui-btn ui-btn-secondary">{{ __('Cancelar') }}</button>
                    <button type="button" wire:click="saveResourceLinks" class="ui-btn ui-btn-primary">{{ __('Guardar Enlaces') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
