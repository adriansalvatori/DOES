<div
    x-data="{
        savedTierId: null,
        allExpanded: false,
        toggleAll(state) { this.allExpanded = state; },
        init() {
            window.addEventListener('tier-saved', (e) => {
                this.savedTierId = e.detail.id;
                setTimeout(() => { if (this.savedTierId === e.detail.id) { this.savedTierId = null; } }, 1200);
            });
        },
        confirmTierChange(el, tierId, field, label, prefix = '', suffix = '') {
            const from = el.dataset.from ?? '';
            const to = el.value;
            if (String(from) === String(to)) { return; }
            window.KudosConfirmChange({ field: label, from: prefix + from + suffix, to: prefix + to + suffix })
                .then((ok) => { if (ok) { $wire.quickUpdateTier(tierId, field, to); } else { el.value = from; } });
        }
    }"
    class="space-y-4"
>
    <x-ui.page-header :title="__('Price list')" :subtitle="__('Catalog by category and product, supplier costs and a price calculator.')">
        <x-slot:actions>
            <button type="button" wire:click="openCalculator()" class="ui-btn ui-btn-secondary"><x-lucide-calculator />{{ __('Calculator') }}</button>
            <a href="{{ route('design-resources') }}" wire:navigate class="ui-btn ui-btn-secondary"><x-lucide-folder-down />{{ __('Design Resources') }}</a>
            @if(auth()->user()?->canManagePricing())
                <button type="button" wire:click="openCreateCategoryModal()" class="ui-btn ui-btn-secondary"><x-lucide-folder-plus />{{ __('New category') }}</button>
                <button type="button" wire:click="openCreateProductModal()" class="ui-btn ui-btn-primary"><x-lucide-plus />{{ __('New Product') }}</button>
            @endif
        </x-slot:actions>
        <x-slot:toolbar>
            <div class="relative w-72">
                <x-lucide-search class="pointer-events-none absolute left-2.5 top-2 h-4 w-4 text-ink-3" />
                <input type="text" wire:model.live.debounce.250ms="search" placeholder="{{ __('Search product, variant, supplier...') }}" class="ui-input pl-8">
            </div>
            <select wire:model.live="filterCategory" class="ui-select w-48" aria-label="{{ __('Category') }}">
                <option value="all">{{ __('All categories') }}</option>
                @foreach($this->categories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                @endforeach
            </select>
            <select wire:model.live="filterSupplier" class="ui-select w-48" aria-label="{{ __('Supplier') }}">
                <option value="all">{{ __('All suppliers') }}</option>
                @foreach($this->suppliers as $sup)
                    <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                @endforeach
            </select>
            @if($search !== '' || $filterCategory !== 'all' || $filterSupplier !== 'all')
                <button type="button" wire:click="resetFilters" class="ui-btn ui-btn-ghost"><x-lucide-rotate-ccw />{{ __('Clear') }}</button>
            @endif
            <div class="ml-auto flex items-center gap-1">
                <button type="button" @click="toggleAll(true)" class="ui-btn ui-btn-ghost ui-btn-sm" title="{{ __('Expand all categories') }}"><x-lucide-chevrons-down-up class="rotate-180" />{{ __('Expand all') }}</button>
                <button type="button" @click="toggleAll(false)" class="ui-btn ui-btn-ghost ui-btn-sm" title="{{ __('Collapse all categories') }}"><x-lucide-chevrons-down-up />{{ __('Collapse all') }}</button>
            </div>
        </x-slot:toolbar>
    </x-ui.page-header>

    @if($feedbackMessage)
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="flex items-center justify-between gap-2 rounded-card border border-line bg-surface px-3 py-2 text-body text-ink">
            <span class="flex items-center gap-2"><x-lucide-check-circle-2 class="h-4 w-4 shrink-0 text-ok" />{{ $feedbackMessage }}</span>
            <button type="button" @click="show = false" class="ui-btn ui-btn-ghost ui-btn-icon ui-btn-sm" aria-label="{{ __('Close') }}"><x-lucide-x /></button>
        </div>
    @endif

    <div class="space-y-3">
        @forelse($this->categoriesWithProducts as $category)
            @php
                $catProducts = $category->filtered_products;
                $defaultOpen = (trim($search) !== '' || $filterCategory !== 'all');
                $canManage = auth()->user()?->canManagePricing();
            @endphp

            <section data-category-id="{{ $category->id }}" wire:key="cat-{{ $category->id }}"
                     x-data="{ open: {{ $defaultOpen ? 'true' : 'false' }}, init() { this.$watch('allExpanded', val => this.open = val); } }"
                     class="ui-card overflow-hidden">
                <div @click="open = !open" class="group flex cursor-pointer select-none items-center gap-3 px-4 py-3 transition-colors hover:bg-sunken/40">
                    <x-lucide-chevron-down class="h-4 w-4 shrink-0 text-ink-3 transition-transform" ::class="open ? '' : '-rotate-90'" />
                    <div wire:key="cat-cover-{{ $category->id }}-{{ md5($category->image_url ?? 'none') }}" x-data="{ imgError: false }" x-effect="imgError = false"
                         class="relative h-10 w-10 shrink-0 overflow-hidden rounded-control bg-sunken">
                        @if($category->image_url)
                            <img src="{{ $category->image_url }}" alt="" x-show="!imgError" x-on:error="imgError = true" class="absolute inset-0 h-full w-full object-cover">
                        @endif
                        <x-lucide-image class="absolute inset-0 m-auto h-4 w-4 text-ink-3" x-show="{{ $category->image_url ? 'imgError' : 'true' }}" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <h2 class="ui-section-title truncate">{{ $category->name }}</h2>
                            <span class="rounded-full bg-sunken px-2 py-0.5 text-meta tabular-nums text-ink-2">{{ trans_choice('{1} :count product|[0,*] :count products', $catProducts->count()) }}</span>
                        </div>
                        @if($category->description)
                            <p class="line-clamp-1 text-body text-ink-3">{{ $category->description }}</p>
                        @endif
                    </div>
                    @if($canManage)
                        <button type="button" x-on:click.stop="" wire:click="openEditCategoryModal({{ $category->id }})" class="ui-btn ui-btn-ghost ui-btn-sm" title="{{ __('Edit this category\'s name, cover and description') }}">
                            <x-lucide-pencil />{{ __('Edit category') }}
                        </button>
                    @endif
                </div>

                <div x-show="open" x-collapse class="divide-y divide-line border-t border-line">
                    @forelse($catProducts as $product)
                        <div x-data="{ openProduct: {{ trim($search) !== '' ? 'true' : 'false' }} }" wire:key="prod-{{ $product->id }}">
                            <div @click="openProduct = !openProduct" class="flex cursor-pointer select-none items-center justify-between gap-3 px-4 py-2.5 transition-colors hover:bg-sunken/40 sm:px-6">
                                <div class="flex min-w-0 items-center gap-2">
                                    <x-lucide-chevron-down class="h-4 w-4 shrink-0 text-ink-3 transition-transform" ::class="openProduct ? '' : '-rotate-90'" />
                                    <h3 class="truncate text-row font-medium text-ink">{{ $product->name }}</h3>
                                    <span class="text-meta text-ink-3">{{ trans_choice('{1} :count variant|[0,*] :count variants', $product->variants->count()) }}</span>
                                </div>
                                @if($canManage)
                                    <div class="flex shrink-0 items-center gap-1.5" x-on:click.stop="">
                                        <button type="button" wire:click="openEditProductModal({{ $product->id }})" class="ui-btn ui-btn-ghost ui-btn-sm" title="{{ __('Edit product details') }}"><x-lucide-pencil />{{ __('Edit') }}</button>
                                        <button type="button" wire:click="openAddVariantModal({{ $product->id }})" class="ui-btn ui-btn-secondary ui-btn-sm"><x-lucide-plus />{{ __('New variant') }}</button>
                                    </div>
                                @endif
                            </div>

                            <div x-show="openProduct" x-collapse class="space-y-3 border-t border-line bg-canvas px-4 pb-4 pt-3 sm:px-6">
                                @php $sizes = $product->getSizesList(); @endphp
                                @if($product->image_url || $product->description || !empty($sizes))
                                    <div class="flex items-start gap-3">
                                        @if($product->image_url)
                                            <div wire:key="prod-img-{{ $product->id }}-{{ md5($product->image_url) }}" x-data="{ imgError: false }" x-effect="imgError = false" x-show="!imgError" class="h-12 w-12 shrink-0 overflow-hidden rounded-control border border-line bg-sunken">
                                                <img src="{{ $product->image_url }}" alt="{{ $product->name }}" x-on:error="imgError = true" class="h-full w-full object-cover">
                                            </div>
                                        @endif
                                        <div class="min-w-0 space-y-1">
                                            @if($product->description)<p class="text-body text-ink-2">{{ $product->description }}</p>@endif
                                            @if(!empty($sizes))
                                                <div class="flex flex-wrap items-center gap-1">
                                                    <span class="mr-1 text-meta text-ink-3">{{ __('Dimensions:') }}</span>
                                                    @foreach($sizes as $sz)<span class="rounded-control border border-line bg-surface px-1.5 py-0.5 text-meta tabular-nums text-ink-2">{{ $sz }}</span>@endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endif

                                @forelse($product->variants as $variant)
                                    @php
                                        $variantSupplier = $variant->getEffectiveSupplier();
                                        $tiers = $variant->tiers;
                                        $vWeb = $variant->getWebsiteUrl();
                                        $vMockup = $variant->getMockupUrl();
                                        $vTemplate = $variant->getTemplateUrl();
                                    @endphp
                                    <div class="ui-card overflow-hidden" wire:key="variant-{{ $variant->id }}">
                                        <div class="flex flex-col justify-between gap-3 border-b border-line px-3 py-2.5 sm:flex-row sm:items-center">
                                            <div class="flex flex-wrap items-center gap-2.5">
                                                <button type="button" wire:key="variant-thumb-{{ $variant->id }}-{{ md5($variant->image_url ?? 'none') }}" x-data="{ imgError: false }" x-effect="imgError = false"
                                                        @if($canManage) wire:click="openEditVariantModal({{ $variant->id }})" @endif
                                                        class="relative flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-control border border-line bg-surface hover:border-line-strong"
                                                        title="{{ __('Upload or change this subproduct\'s image') }}">
                                                    @if($variant->image_url)
                                                        <img src="{{ $variant->image_url }}" alt="{{ $variant->name }}" x-show="!imgError" x-on:error="imgError = true" class="h-full w-full object-cover">
                                                        <x-lucide-image x-show="imgError" x-cloak class="h-4 w-4 text-ink-3" />
                                                    @else
                                                        <x-lucide-image class="h-4 w-4 text-ink-3" />
                                                    @endif
                                                </button>
                                                <h4 class="text-row font-medium text-ink">{{ $variant->name }}</h4>
                                                @if($variantSupplier)<x-ui.tag tone="neutral" title="{{ __('Supplier for this subproduct') }}">{{ $variantSupplier->name }}</x-ui.tag>@endif
                                                @if($variant->turnaround_time)<x-ui.tag tone="neutral" icon="clock">{{ $variant->turnaround_time }}</x-ui.tag>@endif
                                                @if($variant->specs)<span class="text-body text-ink-3">{{ $variant->specs }}</span>@endif
                                            </div>
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                @if($vWeb)<a href="{{ $vWeb }}" target="_blank" rel="noopener noreferrer" title="{{ __('Supplier website for this subproduct') }}" class="ui-btn ui-btn-secondary ui-btn-sm"><x-lucide-globe />{{ __('Web') }}</a>@endif
                                                @if($vMockup)<a href="{{ $vMockup }}" target="_blank" rel="noopener noreferrer" title="{{ __('Mockup for this subproduct') }}" class="ui-btn ui-btn-secondary ui-btn-sm"><x-lucide-image />{{ __('Mockup') }}</a>@endif
                                                @if($vTemplate)<a href="{{ $vTemplate }}" target="_blank" rel="noopener noreferrer" title="{{ __('Template for this subproduct') }}" class="ui-btn ui-btn-secondary ui-btn-sm"><x-lucide-box />{{ __('Template') }}</a>@endif
                                                @if($canManage)
                                                    <button type="button" wire:click="openEditVariantModal({{ $variant->id }})" class="ui-btn ui-btn-ghost ui-btn-sm" title="{{ __('Edit this subproduct\'s supplier and resources') }}"><x-lucide-pencil />{{ __('Edit') }}</button>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="overflow-x-auto">
                                            <table class="ui-table">
                                                <thead>
                                                    <tr>
                                                        <th class="is-num w-28">{{ __('Quantity') }}</th>
                                                        <th class="is-num w-32">{{ __('Material cost') }}</th>
                                                        <th class="is-num w-24">{{ __('Markup %') }}</th>
                                                        <th class="is-num w-32">{{ __('Final Price') }}</th>
                                                        <th class="is-num w-28">{{ __('Per unit') }}</th>
                                                        <th class="w-20 text-right"><span class="sr-only">{{ __('Actions') }}</span></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse($tiers as $tier)
                                                        <tr wire:key="vt-row-{{ $tier->id }}" :class="savedTierId === {{ $tier->id }} ? '[&>td]:bg-ok-soft' : ''">
                                                            <td class="is-num font-medium">
                                                                @if($canManage)
                                                                    <input type="number" value="{{ $tier->quantity }}" data-from="{{ $tier->quantity }}" aria-label="{{ __('Quantity') }}"
                                                                           @change.stop="confirmTierChange($el, {{ $tier->id }}, 'quantity', @js(__('Quantity')))"
                                                                           @keydown.enter.prevent="$el.blur()" class="ui-input h-7 w-24 text-right tabular-nums">
                                                                @else
                                                                    {{ number_format($tier->quantity) }}
                                                                @endif
                                                            </td>
                                                            <td class="is-num">
                                                                @if($canManage)
                                                                    <input type="number" step="0.01" value="{{ $tier->production_cost }}" data-from="{{ $tier->production_cost }}" aria-label="{{ __('Material cost') }}"
                                                                           @change.stop="confirmTierChange($el, {{ $tier->id }}, 'production_cost', @js(__('Material cost')), '$')"
                                                                           @keydown.enter.prevent="$el.blur()" class="ui-input h-7 w-28 text-right tabular-nums">
                                                                @else
                                                                    ${{ number_format($tier->production_cost, 2) }}
                                                                @endif
                                                            </td>
                                                            <td class="is-num">
                                                                @if($canManage)
                                                                    <input type="number" step="1" value="{{ $tier->markup_percent }}" data-from="{{ $tier->markup_percent }}" aria-label="{{ __('Markup %') }}"
                                                                           @change.stop="confirmTierChange($el, {{ $tier->id }}, 'markup_percent', @js(__('Markup %')), '', '%')"
                                                                           @keydown.enter.prevent="$el.blur()" class="ui-input h-7 w-20 text-right tabular-nums">
                                                                @else
                                                                    +{{ number_format($tier->markup_percent, 0) }}%
                                                                @endif
                                                            </td>
                                                            <td class="is-num font-semibold">${{ number_format($tier->final_price, 2) }}</td>
                                                            <td class="is-num text-ink-3">${{ number_format($tier->unit_price, 3) }}/u</td>
                                                            <td class="whitespace-nowrap text-right">
                                                                <button type="button" wire:click="openCalculator({{ $product->id }}, {{ $variant->id }})" title="{{ __('Open in calculator') }}" aria-label="{{ __('Open in calculator') }}" class="ui-btn ui-btn-ghost ui-btn-icon ui-btn-sm"><x-lucide-calculator /></button>
                                                                @if($canManage)
                                                                    <button type="button" wire:click="deleteTier({{ $tier->id }})" wire:confirm="{{ __('Delete this price tier?') }}" title="{{ __('Delete tier') }}" aria-label="{{ __('Delete tier') }}" class="ui-btn ui-btn-ghost ui-btn-icon ui-btn-sm"><x-lucide-trash-2 /></button>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    @empty
                                                        <tr><td colspan="6" class="py-4 text-center text-ink-3">{{ __('No quantity tiers set for this subproduct.') }}</td></tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                        @if($canManage)
                                            <button type="button" wire:click="openAddTierModal({{ $variant->id }})" class="flex w-full items-center gap-1.5 border-t border-line px-3 py-2 text-body text-ink-2 transition-colors hover:bg-sunken/50 hover:text-ink">
                                                <x-lucide-plus class="h-4 w-4" />{{ __('Add new price option') }}
                                            </button>
                                        @endif
                                    </div>
                                @empty
                                    <p class="rounded-card border border-line bg-surface p-6 text-center text-body text-ink-3">{{ __('No variants or subproducts exist for this product.') }}</p>
                                @endforelse
                            </div>
                        </div>
                    @empty
                        <p class="p-8 text-center text-body text-ink-3">{{ __('No products in this category match the selected filter.') }}</p>
                    @endforelse
                </div>
            </section>
        @empty
            <div class="ui-card"><x-ui.empty icon="package-open" :title="__('No categories or products found.')" :description="__('Try adjusting the search or category filters.')" /></div>
        @endforelse
    </div>

    <!-- MODAL 1: Interactive Price Calculator -->
    @if($calculatorOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
            <div class="bg-surface rounded-panel border border-line shadow-panel max-w-lg w-full p-6 space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-line">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-sunken text-ink-3 flex items-center justify-center">
                            <x-lucide-calculator class="w-4 h-4" />
                        </div>
                        <div>
                            <h3 class="text-base font-semibold text-ink">{{ __('Pricing & Margin Calculator') }}</h3>
                            <p class="text-xs text-ink-3">{{ __('Simulate costs, markups and volume pricing.') }}</p>
                        </div>
                    </div>
                    <button wire:click="closeCalculator" class="ui-btn ui-btn-ghost ui-btn-icon ui-btn-sm" aria-label="{{ __('Close') }}"><x-lucide-x /></button>
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
                        <label class="ui-label">
                            {{ __('Quantity (units)') }}
                        </label>
                        <input 
                            type="number" 
                            wire:model.live="calcQuantity"
                            min="1"
                            class="ui-input"
                        >
                    </div>

                    <div>
                        <label class="ui-label">
                            {{ __('Costo Material / Proveedor ($)') }}
                        </label>
                        <input 
                            type="number" 
                            step="0.01"
                            wire:model.live="calcCost"
                            class="ui-input"
                        >
                    </div>
                </div>

                <!-- Markup Presets -->
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between text-xs">
                        <label class="text-meta font-semibold   text-ink-3">{{ __('Markup on cost') }}</label>
                        <span class="font-mono font-semibold text-ink-3">+{{ $calcMarkup }}%</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        @foreach([30, 40, 50, 60, 75, 100] as $preset)
                            <button 
                                type="button"
                                wire:click="applyPresetMarkup({{ $preset }})"
                                class="ui-chip flex-1 justify-center" aria-pressed="{{ (float)$calcMarkup === (float)$preset ? 'true' : 'false' }}"
                            >
                                +{{ $preset }}%
                            </button>
                        @endforeach
                    </div>
                </div>

                <!-- Live Results Cards -->
                <div class="grid grid-cols-3 gap-2 bg-sunken p-3.5 rounded-card border border-line">
                    <div class="text-center">
                        <span class="text-meta font-semibold  text-ink-3 block">{{ __('Net profit') }}</span>
                        <span class="text-sm font-semibold font-mono text-ink-3">${{ number_format($simNetProfit, 2) }}</span>
                    </div>
                    <div class="text-center border-x border-line">
                        <span class="text-meta font-semibold  text-ink-3 block">{{ __('Final Price') }}</span>
                        <span class="text-base font-semibold font-mono text-ink">${{ number_format($simFinalPrice, 2) }}</span>
                    </div>
                    <div class="text-center">
                        <span class="text-meta font-semibold  text-ink-3 block">{{ __('Per unit') }}</span>
                        <span class="text-sm font-semibold font-mono text-ink-2">${{ number_format($simUnitPrice, 4) }}/u</span>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-between pt-2">
                    <button 
                        wire:click="closeCalculator" 
                        class="ui-btn ui-btn-secondary"
                    >
                        {{ __('Close') }}
                    </button>

                    @if($calcVariantId && auth()->user()?->canManagePricing())
                        <button 
                            wire:click="saveCalculatorToVariant"
                            class="ui-btn ui-btn-primary"
                        >
                            <x-lucide-save class="w-3.5 h-3.5" />
                            <span>{{ __('Apply to price list') }}</span>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL 2: Create Product Modal -->
    @if($createProductModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
            <div class="bg-surface rounded-panel border border-line shadow-panel max-w-md w-full p-6 space-y-4">
                <div class="flex items-center justify-between pb-2 border-b border-line">
                    <h3 class="text-base font-semibold text-ink">{{ __('New Product') }}</h3>
                    <button wire:click="$set('createProductModalOpen', false)" class="ui-btn ui-btn-ghost ui-btn-icon ui-btn-sm" aria-label="{{ __('Close') }}"><x-lucide-x /></button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="ui-label">{{ __('Product Name') }} *</label>
                        <input 
                            type="text" 
                            wire:model="newProductName"
                            placeholder="{{ __('e.g. Business card, roll-up banner') }}"
                            class="ui-input"
                        >
                        @error('newProductName') <span class="text-danger text-meta">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="ui-label">{{ __('Category') }} *</label>
                            <select 
                                wire:model="newCategoryId"
                                class="ui-select"
                            >
                                @foreach($this->categories as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="ui-label">{{ __('Main supplier') }}</label>
                            <select 
                                wire:model="newSupplierId"
                                class="ui-select"
                            >
                                <option value="">{{ __('No supplier') }}</option>
                                @foreach($this->suppliers as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="ui-label">{{ __('Initial subproduct / variant') }} *</label>
                        <input 
                            type="text" 
                            wire:model="newInitialVariant"
                            placeholder="{{ __('e.g. Regular, rush, foil') }}"
                            class="ui-input"
                        >
                    </div>

                    <div>
                        <label class="ui-label">{{ __('Possible sizes') }} (separados por coma)</label>
                        <input 
                            type="text" 
                            wire:model="newSizes"
                            placeholder="{{ __('e.g. 33\'\' x 81\'\', 38\'\' x 81\'\', 60\'\' x 81\'\'') }}"
                            class="ui-input"
                        >
                    </div>

                    <!-- Dual File Upload / URL Option for Product Thumbnail -->
                    <div class="space-y-1.5">
                        <label class="ui-label">
                            <span class="flex items-center gap-1.5">
                                <x-lucide-image class="w-3.5 h-3.5 text-ink-3" />
                                <span>{{ __('Thumbnail image') }}</span>
                            </span>
                            <span class="text-meta text-ink-3 font-normal">{{ __('Upload a file or URL') }}</span>
                        </label>

                        <div class="p-3 bg-sunken rounded-card border border-line space-y-2.5">
                            <div class="flex items-center gap-3">
                                <div wire:key="new-prod-prev-{{ $newProductFile ? 'file' : 'nofile' }}" class="w-12 h-12 rounded-card bg-stone-200 border border-line overflow-hidden shrink-0 flex items-center justify-center relative ">
                                    @php
                                        $newProductTempUrl = null;
                                        if ($newProductFile && method_exists($newProductFile, 'temporaryUrl')) {
                                            try {
                                                $newProductTempUrl = $newProductFile->temporaryUrl();
                                            } catch (\Throwable $e) {
                                                $newProductTempUrl = null;
                                            }
                                        }
                                    @endphp
                                    @if ($newProductTempUrl)
                                        <img src="{{ $newProductTempUrl }}" class="w-full h-full object-cover">
                                    @else
                                        <x-lucide-box class="w-5 h-5 text-ink-3 stroke-[1.5]" />
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
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white hover:bg-sunken text-ink-2 text-xs font-semibold rounded-lg border border-line  transition cursor-pointer select-none"
                                    >
                                        <x-lucide-upload class="w-3.5 h-3.5 text-ink-2" />
                                        <span>{{ __('Upload image...') }}</span>
                                    </label>
                                    <span class="block text-meta text-ink-3 mt-1">{{ __('PNG, JPG, WEBP (max 5MB)') }}</span>
                                </div>
                            </div>
                        </div>
                        @error('newProductFile') <span class="text-danger text-meta block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="ui-label">{{ __('Description / notes') }}</label>
                        <textarea 
                            wire:model="newDescription"
                            rows="2"
                            placeholder="{{ __('Details about materials, use and applications...') }}"
                            class="ui-input"
                        ></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-line">
                    <button 
                        wire:click="$set('createProductModalOpen', false)"
                        class="ui-btn ui-btn-secondary"
                    >
                        {{ __('Cancel') }}
                    </button>
                    <button 
                        wire:click="createProduct"
                        class="ui-btn ui-btn-primary"
                    >
                        {{ __('Create product') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL 3: Add Variant Modal -->
    @if($addVariantModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
            <div class="bg-surface rounded-panel border border-line shadow-panel max-w-lg w-full p-6 space-y-4">
                <div class="flex items-center justify-between pb-2 border-b border-line">
                    <h3 class="text-base font-semibold text-ink">{{ __('New subproduct / variant') }}</h3>
                    <button wire:click="$set('addVariantModalOpen', false)" class="ui-btn ui-btn-ghost ui-btn-icon ui-btn-sm" aria-label="{{ __('Close') }}"><x-lucide-x /></button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="ui-label">{{ __('Subproduct name') }} *</label>
                        <input 
                            type="text" 
                            wire:model="newVariantName"
                            placeholder="{{ __('e.g. Foil front & back, rush 24h, plastic 20pt') }}"
                            class="ui-input"
                        >
                        @error('newVariantName') <span class="text-danger text-meta">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="ui-label">{{ __('Supplier for this subproduct') }}</label>
                            <select 
                                wire:model="newVariantSupplierId"
                                class="ui-select"
                            >
                                <option value="">{{ __('No specific supplier') }}</option>
                                @foreach($this->suppliers as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="ui-label">{{ __('Turnaround time') }}</label>
                            <input 
                                type="text" 
                                wire:model="newVariantTurnaround"
                                placeholder="{{ __('e.g. 24h, 2-3 business days') }}"
                                class="ui-input"
                            >
                        </div>
                    </div>

                    <div class="space-y-2 pt-1 border-t border-line">
                        <span class="text-meta font-semibold   text-ink-3 block">{{ __('Design resources and links:') }}</span>
                        
                        <div>
                            <label class="ui-label">{{ __('Supplier website URL') }}</label>
                            <input 
                                type="text" 
                                wire:model="newVariantWebsiteUrl"
                                placeholder="https://4over.com/producto..."
                                class="ui-input"
                            >
                            @error('newVariantWebsiteUrl') <span class="text-danger text-meta">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="ui-label">{{ __('Mockup URL') }}</label>
                                <input 
                                    type="text" 
                                    wire:model="newVariantMockupUrl"
                                    placeholder="https://.../mockup.zip"
                                    class="ui-input"
                                >
                            </div>
                            <div>
                                <label class="ui-label">{{ __('Template URL') }}</label>
                                <input 
                                    type="text" 
                                    wire:model="newVariantTemplateUrl"
                                    placeholder="https://.../template.zip"
                                    class="ui-input"
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Dual File Upload / URL Option for Subproduct Image -->
                    <div class="space-y-1.5 pt-1 border-t border-line">
                        <label class="ui-label">
                            <span class="flex items-center gap-1.5">
                                <x-lucide-image class="w-3.5 h-3.5 text-ink-3" />
                                <span>{{ __('Subproduct image') }}</span>
                            </span>
                            <span class="text-meta text-ink-3 font-normal">{{ __('Upload a file or paste a URL') }}</span>
                        </label>

                        <div class="p-3 bg-sunken rounded-card border border-line space-y-2.5">
                            <div class="flex items-center gap-3">
                                <div wire:key="new-variant-prev-{{ md5($newVariantImageUrl ?? 'none') }}-{{ $newVariantFile ? 'file' : 'nofile' }}" x-data="{ imgError: false }" x-effect="imgError = false" class="w-12 h-12 rounded-card bg-stone-200 border border-line overflow-hidden shrink-0 flex items-center justify-center relative ">
                                    @php
                                        $newVariantTempUrl = null;
                                        if ($newVariantFile && method_exists($newVariantFile, 'temporaryUrl')) {
                                            try {
                                                $newVariantTempUrl = $newVariantFile->temporaryUrl();
                                            } catch (\Throwable $e) {
                                                $newVariantTempUrl = null;
                                            }
                                        }
                                    @endphp
                                    @if ($newVariantTempUrl)
                                        <img src="{{ $newVariantTempUrl }}" class="w-full h-full object-cover">
                                    @elseif ($newVariantImageUrl)
                                        <img src="{{ $newVariantImageUrl }}" x-show="!imgError" x-on:error="imgError = true" class="w-full h-full object-cover">
                                        <x-lucide-image x-show="imgError" x-cloak class="w-5 h-5 text-ink-3 stroke-[1.5]" />
                                    @else
                                        <x-lucide-image class="w-5 h-5 text-ink-3 stroke-[1.5]" />
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
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white hover:bg-sunken text-ink-2 text-xs font-semibold rounded-lg border border-line  transition cursor-pointer select-none"
                                    >
                                        <x-lucide-upload class="w-3.5 h-3.5 text-ink-2" />
                                        <span>{{ __('Upload image...') }}</span>
                                    </label>
                                    <span class="block text-meta text-ink-3 mt-1">{{ __('PNG, JPG, WEBP (max 5MB)') }}</span>
                                </div>
                            </div>

                            <div class="pt-2 border-t border-line">
                                <label class="ui-label">{{ __('Or paste a direct image URL:') }}</label>
                                <input 
                                    type="text" 
                                    wire:model.live="newVariantImageUrl"
                                    placeholder="https://ejemplo.com/subproducto.jpg"
                                    class="ui-input"
                                >
                            </div>
                        </div>
                        @error('newVariantFile') <span class="text-danger text-meta block">{{ $message }}</span> @enderror
                        @error('newVariantImageUrl') <span class="text-danger text-meta block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="ui-label">{{ __('Material / finish specs') }}</label>
                        <textarea 
                            wire:model="newVariantSpecs"
                            rows="2"
                            placeholder="{{ __('e.g. 16pt premium cardstock with gloss lamination...') }}"
                            class="ui-input"
                        ></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-line">
                    <button 
                        wire:click="$set('addVariantModalOpen', false)"
                        class="ui-btn ui-btn-secondary"
                    >
                        {{ __('Cancel') }}
                    </button>
                    <button 
                        wire:click="createVariant"
                        class="ui-btn ui-btn-primary"
                    >
                        {{ __('Create subproduct') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL 3.5: Edit Variant Modal -->
    @if($editVariantModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
            <div class="bg-surface rounded-panel border border-line shadow-panel max-w-lg w-full p-6 space-y-4">
                <div class="flex items-center justify-between pb-2 border-b border-line">
                    <h3 class="text-base font-semibold text-ink">{{ __('Edit subproduct') }}</h3>
                    <button wire:click="$set('editVariantModalOpen', false)" class="ui-btn ui-btn-ghost ui-btn-icon ui-btn-sm" aria-label="{{ __('Close') }}"><x-lucide-x /></button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="ui-label">{{ __('Subproduct name') }} *</label>
                        <input 
                            type="text" 
                            wire:model="editVariantName"
                            placeholder="{{ __('e.g. Foil front & back, rush 24h, plastic 20pt') }}"
                            class="ui-input"
                        >
                        @error('editVariantName') <span class="text-danger text-meta">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="ui-label">{{ __('Supplier for this subproduct') }}</label>
                            <select 
                                wire:model="editVariantSupplierId"
                                class="ui-select"
                            >
                                <option value="">{{ __('No specific supplier') }}</option>
                                @foreach($this->suppliers as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="ui-label">{{ __('Turnaround time') }}</label>
                            <input 
                                type="text" 
                                wire:model="editVariantTurnaround"
                                placeholder="{{ __('e.g. 24h, 2-3 business days') }}"
                                class="ui-input"
                            >
                        </div>
                    </div>

                    <div class="space-y-2 pt-1 border-t border-line">
                        <span class="text-meta font-semibold   text-ink-3 block">{{ __('Design resources and links:') }}</span>
                        
                        <div>
                            <label class="ui-label">{{ __('Supplier website URL') }}</label>
                            <input 
                                type="text" 
                                wire:model="editVariantWebsiteUrl"
                                placeholder="https://4over.com/producto..."
                                class="ui-input"
                            >
                            @error('editVariantWebsiteUrl') <span class="text-danger text-meta">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="ui-label">{{ __('Mockup URL') }}</label>
                                <input 
                                    type="text" 
                                    wire:model="editVariantMockupUrl"
                                    placeholder="https://.../mockup.zip"
                                    class="ui-input"
                                >
                            </div>
                            <div>
                                <label class="ui-label">{{ __('Template URL') }}</label>
                                <input 
                                    type="text" 
                                    wire:model="editVariantTemplateUrl"
                                    placeholder="https://.../template.zip"
                                    class="ui-input"
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Dual File Upload / URL Option for Edit Subproduct Image -->
                    <div class="space-y-1.5 pt-1 border-t border-line">
                        <label class="ui-label">
                            <span class="flex items-center gap-1.5">
                                <x-lucide-image class="w-3.5 h-3.5 text-ink-3" />
                                <span>{{ __('Subproduct image') }}</span>
                            </span>
                            <span class="text-meta text-ink-3 font-normal">{{ __('Upload a file or paste a URL') }}</span>
                        </label>

                        <div class="p-3 bg-sunken rounded-card border border-line space-y-2.5">
                            <div class="flex items-center gap-3">
                                <div wire:key="edit-variant-prev-{{ $editingVariantId }}-{{ md5($editVariantImageUrl ?? 'none') }}-{{ $editVariantFile ? 'file' : 'nofile' }}" x-data="{ imgError: false }" x-effect="imgError = false" class="w-12 h-12 rounded-card bg-stone-200 border border-line overflow-hidden shrink-0 flex items-center justify-center relative ">
                                    @php
                                        $editVariantTempUrl = null;
                                        if ($editVariantFile && method_exists($editVariantFile, 'temporaryUrl')) {
                                            try {
                                                $editVariantTempUrl = $editVariantFile->temporaryUrl();
                                            } catch (\Throwable $e) {
                                                $editVariantTempUrl = null;
                                            }
                                        }
                                    @endphp
                                    @if ($editVariantTempUrl)
                                        <img src="{{ $editVariantTempUrl }}" class="w-full h-full object-cover">
                                    @elseif ($editVariantImageUrl)
                                        <img src="{{ $editVariantImageUrl }}" x-show="!imgError" x-on:error="imgError = true" class="w-full h-full object-cover">
                                        <x-lucide-image x-show="imgError" x-cloak class="w-5 h-5 text-ink-3 stroke-[1.5]" />
                                    @else
                                        <x-lucide-image class="w-5 h-5 text-ink-3 stroke-[1.5]" />
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
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white hover:bg-sunken text-ink-2 text-xs font-semibold rounded-lg border border-line  transition cursor-pointer select-none"
                                    >
                                        <x-lucide-upload class="w-3.5 h-3.5 text-ink-2" />
                                        <span>{{ __('Upload new image...') }}</span>
                                    </label>
                                    <span class="block text-meta text-ink-3 mt-1">{{ __('PNG, JPG, WEBP (max 5MB)') }}</span>
                                </div>
                            </div>

                            <div class="pt-2 border-t border-line">
                                <label class="ui-label">{{ __('Or edit the direct URL:') }}</label>
                                <input 
                                    type="text" 
                                    wire:model.live="editVariantImageUrl"
                                    placeholder="https://ejemplo.com/subproducto.jpg"
                                    class="ui-input"
                                >
                            </div>
                        </div>
                        @error('editVariantFile') <span class="text-danger text-meta block">{{ $message }}</span> @enderror
                        @error('editVariantImageUrl') <span class="text-danger text-meta block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="ui-label">{{ __('Material / finish specs') }}</label>
                        <textarea 
                            wire:model="editVariantSpecs"
                            rows="2"
                            placeholder="{{ __('e.g. 16pt premium cardstock with gloss lamination...') }}"
                            class="ui-input"
                        ></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-2 pt-2 border-t border-line">
                    <button 
                        wire:click="deleteVariant({{ $editingVariantId }})"
                        wire:confirm="{{ __('Delete this subproduct and all its tiers?') }}"
                        class="ui-btn ui-btn-danger"
                    >
                        {{ __('Delete subproduct') }}
                    </button>

                    <div class="flex items-center gap-2">
                        <button 
                            wire:click="$set('editVariantModalOpen', false)"
                            class="ui-btn ui-btn-secondary"
                        >
                            {{ __('Cancel') }}
                        </button>
                        <button 
                            wire:click="saveVariant"
                            class="ui-btn ui-btn-primary"
                        >
                            {{ __('Save Changes') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL 4: Add Tier Modal -->
    @if($addTierModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
            <div class="bg-surface rounded-panel border border-line shadow-panel max-w-sm w-full p-5 space-y-4">
                <div class="flex items-center justify-between pb-2 border-b border-line">
                    <h3 class="text-sm font-semibold text-ink">{{ __('New quantity tier') }}</h3>
                    <button wire:click="$set('addTierModalOpen', false)" class="ui-btn ui-btn-ghost ui-btn-icon ui-btn-sm" aria-label="{{ __('Close') }}"><x-lucide-x /></button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="ui-label">{{ __('Quantity (units)') }} *</label>
                        <input 
                            type="number" 
                            wire:model="newTierQty"
                            class="ui-input"
                        >
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="ui-label">{{ __('Costo ($)') }} *</label>
                            <input 
                                type="number" 
                                step="0.01"
                                wire:model="newTierCost"
                                class="ui-input"
                            >
                        </div>
                        <div>
                            <label class="ui-label">{{ __('Markup %') }} *</label>
                            <input 
                                type="number" 
                                step="1"
                                wire:model="newTierMarkup"
                                class="ui-input"
                            >
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-line">
                    <button 
                        wire:click="$set('addTierModalOpen', false)"
                        class="ui-btn ui-btn-secondary"
                    >
                        {{ __('Cancel') }}
                    </button>
                    <button 
                        wire:click="createTier"
                        class="ui-btn ui-btn-primary"
                    >
                        {{ __('Add tier') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL 5: Create Category Modal -->
    @if($createCategoryModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
            <div class="bg-surface rounded-panel border border-line shadow-panel max-w-md w-full p-6 space-y-4">
                <div class="flex items-center justify-between pb-2 border-b border-line">
                    <h3 class="text-base font-semibold text-ink">{{ __('New category') }}</h3>
                    <button wire:click="$set('createCategoryModalOpen', false)" class="ui-btn ui-btn-ghost ui-btn-icon ui-btn-sm" aria-label="{{ __('Close') }}"><x-lucide-x /></button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="ui-label">{{ __('Category name') }} *</label>
                        <input 
                            type="text" 
                            wire:model="newCategoryName"
                            placeholder="{{ __('e.g. Stationery, signs & banners, packaging') }}"
                            class="ui-input"
                        >
                        @error('newCategoryName') <span class="text-danger text-meta">{{ $message }}</span> @enderror
                    </div>

                    <!-- Dual File Upload / URL Option for Category Cover -->
                    <div class="space-y-1.5">
                        <label class="ui-label">
                            <span class="flex items-center gap-1.5">
                                <x-lucide-image class="w-3.5 h-3.5 text-ink-3" />
                                <span>{{ __('Cover image') }}</span>
                            </span>
                            <span class="text-meta text-ink-3 font-normal">{{ __('Upload a file or paste a URL') }}</span>
                        </label>

                        <div class="p-3 bg-sunken rounded-card border border-line space-y-2.5">
                            <div class="flex items-center gap-3">
                                <div wire:key="new-cat-prev-{{ md5($newCategoryImageUrl ?? 'none') }}-{{ $newCategoryFile ? 'file' : 'nofile' }}" x-data="{ imgError: false }" x-effect="imgError = false" class="w-16 h-12 rounded-lg bg-stone-200 border border-line overflow-hidden shrink-0 flex items-center justify-center relative ">
                                    @php
                                        $newCategoryTempUrl = null;
                                        if ($newCategoryFile && method_exists($newCategoryFile, 'temporaryUrl')) {
                                            try {
                                                $newCategoryTempUrl = $newCategoryFile->temporaryUrl();
                                            } catch (\Throwable $e) {
                                                $newCategoryTempUrl = null;
                                            }
                                        }
                                    @endphp
                                    @if ($newCategoryTempUrl)
                                        <img src="{{ $newCategoryTempUrl }}" class="w-full h-full object-cover">
                                    @elseif ($newCategoryImageUrl)
                                        <img src="{{ $newCategoryImageUrl }}" x-show="!imgError" x-on:error="imgError = true" class="w-full h-full object-cover">
                                        <x-lucide-image x-show="imgError" x-cloak class="w-5 h-5 text-ink-3 stroke-[1.5]" />
                                    @else
                                        <x-lucide-image class="w-5 h-5 text-ink-3 stroke-[1.5]" />
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
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white hover:bg-sunken text-ink-2 text-xs font-semibold rounded-lg border border-line  transition cursor-pointer select-none"
                                    >
                                        <x-lucide-upload class="w-3.5 h-3.5 text-ink-2" />
                                        <span>{{ __('Upload image...') }}</span>
                                    </label>
                                    <span class="block text-meta text-ink-3 mt-1">{{ __('PNG, JPG, WEBP (max 5MB)') }}</span>
                                </div>
                            </div>

                            <div class="pt-2 border-t border-line">
                                <label class="ui-label">{{ __('Or paste a direct image URL:') }}</label>
                                <input 
                                    type="text" 
                                    wire:model.live="newCategoryImageUrl"
                                    placeholder="https://ejemplo.com/portada.jpg"
                                    class="ui-input"
                                >
                            </div>
                        </div>
                        @error('newCategoryFile') <span class="text-danger text-meta block">{{ $message }}</span> @enderror
                        @error('newCategoryImageUrl') <span class="text-danger text-meta block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="ui-label">{{ __('Description') }}</label>
                        <textarea 
                            wire:model="newCategoryDescription"
                            rows="2"
                            placeholder="{{ __('General description of the category...') }}"
                            class="ui-input"
                        ></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-line">
                    <button 
                        wire:click="$set('createCategoryModalOpen', false)"
                        class="ui-btn ui-btn-secondary"
                    >
                        {{ __('Cancel') }}
                    </button>
                    <button 
                        wire:click="createCategory"
                        class="ui-btn ui-btn-primary"
                    >
                        {{ __('Create category') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL 6: Edit Category Modal -->
    @if($editCategoryModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
            <div class="bg-surface rounded-panel border border-line shadow-panel max-w-md w-full p-6 space-y-4">
                <div class="flex items-center justify-between pb-2 border-b border-line">
                    <h3 class="text-base font-semibold text-ink">{{ __('Edit category') }}</h3>
                    <button wire:click="$set('editCategoryModalOpen', false)" class="ui-btn ui-btn-ghost ui-btn-icon ui-btn-sm" aria-label="{{ __('Close') }}"><x-lucide-x /></button>
                </div>

                @error('deleteCategoryError')
                    <div class="p-2.5 bg-sunken border border-line text-danger text-xs rounded-card font-medium">
                        {{ $message }}
                    </div>
                @enderror

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="ui-label">{{ __('Category name') }} *</label>
                        <input 
                            type="text" 
                            wire:model="editCategoryName"
                            placeholder="{{ __('e.g. Stationery, apparel') }}"
                            class="ui-input"
                        >
                        @error('editCategoryName') <span class="text-danger text-meta">{{ $message }}</span> @enderror
                    </div>

                    <!-- Dual File Upload / URL Option for Edit Category Cover -->
                    <div class="space-y-1.5">
                        <label class="ui-label">
                            <span class="flex items-center gap-1.5">
                                <x-lucide-image class="w-3.5 h-3.5 text-ink-3" />
                                <span>{{ __('Cover image') }}</span>
                            </span>
                            <span class="text-meta text-ink-3 font-normal">{{ __('Upload a file or paste a URL') }}</span>
                        </label>

                        <div class="p-3 bg-sunken rounded-card border border-line space-y-2.5">
                            <div class="flex items-center gap-3">
                                <div wire:key="edit-cat-prev-{{ $editingCategoryId }}-{{ md5($editCategoryImageUrl ?? 'none') }}-{{ $editCategoryFile ? 'file' : 'nofile' }}" x-data="{ imgError: false }" x-effect="imgError = false" class="w-16 h-12 rounded-lg bg-stone-200 border border-line overflow-hidden shrink-0 flex items-center justify-center relative ">
                                    @php
                                        $editCategoryTempUrl = null;
                                        if ($editCategoryFile && method_exists($editCategoryFile, 'temporaryUrl')) {
                                            try {
                                                $editCategoryTempUrl = $editCategoryFile->temporaryUrl();
                                            } catch (\Throwable $e) {
                                                $editCategoryTempUrl = null;
                                            }
                                        }
                                    @endphp
                                    @if ($editCategoryTempUrl)
                                        <img src="{{ $editCategoryTempUrl }}" class="w-full h-full object-cover">
                                    @elseif ($editCategoryImageUrl)
                                        <img src="{{ $editCategoryImageUrl }}" x-show="!imgError" x-on:error="imgError = true" class="w-full h-full object-cover">
                                        <x-lucide-image x-show="imgError" x-cloak class="w-5 h-5 text-ink-3 stroke-[1.5]" />
                                    @else
                                        <x-lucide-image class="w-5 h-5 text-ink-3 stroke-[1.5]" />
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
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white hover:bg-sunken text-ink-2 text-xs font-semibold rounded-lg border border-line  transition cursor-pointer select-none"
                                    >
                                        <x-lucide-upload class="w-3.5 h-3.5 text-ink-2" />
                                        <span>{{ __('Upload new image...') }}</span>
                                    </label>
                                    <span class="block text-meta text-ink-3 mt-1">{{ __('PNG, JPG, WEBP (max 5MB)') }}</span>
                                </div>
                            </div>

                            <div class="pt-2 border-t border-line">
                                <label class="ui-label">{{ __('Or edit the direct URL:') }}</label>
                                <input 
                                    type="text" 
                                    wire:model.live="editCategoryImageUrl"
                                    placeholder="https://ejemplo.com/portada-categoria.jpg"
                                    class="ui-input"
                                >
                            </div>
                        </div>
                        @error('editCategoryFile') <span class="text-danger text-meta block">{{ $message }}</span> @enderror
                        @error('editCategoryImageUrl') <span class="text-danger text-meta block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="ui-label">{{ __('Category description') }}</label>
                        <textarea 
                            wire:model="editCategoryDescription"
                            rows="2"
                            placeholder="{{ __('Description of the products in this category...') }}"
                            class="ui-input"
                        ></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-2 pt-2 border-t border-line">
                    <button 
                        wire:click="deleteCategory({{ $editingCategoryId }})"
                        wire:confirm="{{ __('Delete this category?') }}"
                        class="ui-btn ui-btn-danger"
                    >
                        {{ __('Delete category') }}
                    </button>

                    <div class="flex items-center gap-2">
                        <button 
                            wire:click="$set('editCategoryModalOpen', false)"
                            class="ui-btn ui-btn-secondary"
                        >
                            {{ __('Cancel') }}
                        </button>
                        <button 
                            wire:click="saveCategory"
                            class="ui-btn ui-btn-primary"
                        >
                            {{ __('Save Changes') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL 7: Edit Product Modal -->
    @if($editProductModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
            <div class="bg-surface rounded-panel border border-line shadow-panel max-w-md w-full p-6 space-y-4">
                <div class="flex items-center justify-between pb-2 border-b border-line">
                    <h3 class="text-base font-semibold text-ink">{{ __('Edit product') }}</h3>
                    <button wire:click="$set('editProductModalOpen', false)" class="ui-btn ui-btn-ghost ui-btn-icon ui-btn-sm" aria-label="{{ __('Close') }}"><x-lucide-x /></button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="ui-label">{{ __('Product Name') }} *</label>
                        <input 
                            type="text" 
                            wire:model="editProductName"
                            placeholder="{{ __('e.g. Business card, roll-up banner') }}"
                            class="ui-input"
                        >
                        @error('editProductName') <span class="text-danger text-meta">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="ui-label">{{ __('Category') }} *</label>
                            <select 
                                wire:model="editProductCategoryId"
                                class="ui-select"
                            >
                                @foreach($this->categories as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="ui-label">{{ __('Main supplier') }}</label>
                            <select 
                                wire:model="editProductSupplierId"
                                class="ui-select"
                            >
                                <option value="">{{ __('No main supplier') }}</option>
                                @foreach($this->suppliers as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="ui-label">{{ __('Possible sizes') }} (separados por coma)</label>
                        <input 
                            type="text" 
                            wire:model="editProductSizes"
                            placeholder="{{ __('e.g. 33\'\' x 81\'\', 38\'\' x 81\'\'') }}"
                            class="ui-input"
                        >
                    </div>

                    <!-- Dual File Upload / URL Option for Edit Product Thumbnail -->
                    <div class="space-y-1.5">
                        <label class="ui-label">
                            <span class="flex items-center gap-1.5">
                                <x-lucide-image class="w-3.5 h-3.5 text-ink-3" />
                                <span>{{ __('Thumbnail image') }}</span>
                            </span>
                            <span class="text-meta text-ink-3 font-normal">{{ __('Upload a file or paste a URL') }}</span>
                        </label>

                        <div class="p-3 bg-sunken rounded-card border border-line space-y-2.5">
                            <div class="flex items-center gap-3">
                                <div wire:key="edit-prod-prev-{{ $editingProductId }}-{{ md5($editProductImageUrl ?? 'none') }}-{{ $editProductFile ? 'file' : 'nofile' }}" x-data="{ imgError: false }" x-effect="imgError = false" class="w-12 h-12 rounded-card bg-stone-200 border border-line overflow-hidden shrink-0 flex items-center justify-center relative ">
                                    @php
                                        $editProductTempUrl = null;
                                        if ($editProductFile && method_exists($editProductFile, 'temporaryUrl')) {
                                            try {
                                                $editProductTempUrl = $editProductFile->temporaryUrl();
                                            } catch (\Throwable $e) {
                                                $editProductTempUrl = null;
                                            }
                                        }
                                    @endphp
                                    @if ($editProductTempUrl)
                                        <img src="{{ $editProductTempUrl }}" class="w-full h-full object-cover">
                                    @elseif ($editProductImageUrl)
                                        <img src="{{ $editProductImageUrl }}" x-show="!imgError" x-on:error="imgError = true" class="w-full h-full object-cover">
                                        <x-lucide-box x-show="imgError" x-cloak class="w-5 h-5 text-ink-3 stroke-[1.5]" />
                                    @else
                                        <x-lucide-box class="w-5 h-5 text-ink-3 stroke-[1.5]" />
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
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white hover:bg-sunken text-ink-2 text-xs font-semibold rounded-lg border border-line  transition cursor-pointer select-none"
                                    >
                                        <x-lucide-upload class="w-3.5 h-3.5 text-ink-2" />
                                        <span>{{ __('Upload new image...') }}</span>
                                    </label>
                                    <span class="block text-meta text-ink-3 mt-1">{{ __('PNG, JPG, WEBP (max 5MB)') }}</span>
                                </div>
                            </div>

                            <div class="pt-2 border-t border-line">
                                <label class="ui-label">{{ __('Or edit the direct URL:') }}</label>
                                <input 
                                    type="text" 
                                    wire:model.live="editProductImageUrl"
                                    placeholder="https://ejemplo.com/producto.jpg"
                                    class="ui-input"
                                >
                            </div>
                        </div>
                        @error('editProductFile') <span class="text-danger text-meta block">{{ $message }}</span> @enderror
                        @error('editProductImageUrl') <span class="text-danger text-meta block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="ui-label">{{ __('Description / notes') }}</label>
                        <textarea 
                            wire:model="editProductDescription"
                            rows="2"
                            placeholder="{{ __('Details about the product...') }}"
                            class="ui-input"
                        ></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-2 pt-2 border-t border-line">
                    <button 
                        wire:click="deleteProduct({{ $editingProductId }})"
                        wire:confirm="{{ __('Delete this product and all its variants?') }}"
                        class="ui-btn ui-btn-danger"
                    >
                        {{ __('Delete product') }}
                    </button>

                    <div class="flex items-center gap-2">
                        <button 
                            wire:click="$set('editProductModalOpen', false)"
                            class="ui-btn ui-btn-secondary"
                        >
                            {{ __('Cancel') }}
                        </button>
                        <button 
                            wire:click="saveProduct"
                            class="ui-btn ui-btn-primary"
                        >
                            {{ __('Save Changes') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
