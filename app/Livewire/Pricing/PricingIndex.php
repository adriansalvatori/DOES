<?php

namespace App\Livewire\Pricing;

use App\Models\InstallationType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductPriceTier;
use App\Models\ProductVariant;
use App\Models\Supplier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
class PricingIndex extends Component
{
    use WithFileUploads;

    public string $search = '';

    public string $filterCategory = 'all';

    public string $filterSupplier = 'all';

    // Interactive Calculator State
    public bool $calculatorOpen = false;

    public ?int $calcProductId = null;

    public ?int $calcVariantId = null;

    public int $calcQuantity = 100;

    public float $calcCost = 25.0;

    public float $calcMarkup = 60.0;

    // Create Product Modal State
    public bool $createProductModalOpen = false;

    public string $newProductName = '';

    public ?int $newCategoryId = null;

    public ?int $newSupplierId = null;

    public string $newSizes = '';

    public string $newDescription = '';

    public string $newInitialVariant = 'Regular';

    // Add Tier Modal State
    public bool $addTierModalOpen = false;

    public ?int $targetVariantId = null;

    public int $newTierQty = 250;

    public float $newTierCost = 20.0;

    public float $newTierMarkup = 50.0;

    // Add Variant Modal State
    public bool $addVariantModalOpen = false;

    public ?int $targetProductId = null;

    public string $newVariantName = '';

    public ?int $newVariantSupplierId = null;

    public string $newVariantWebsiteUrl = '';

    public string $newVariantMockupUrl = '';

    public string $newVariantTemplateUrl = '';

    public string $newVariantSpecs = '';

    public string $newVariantTurnaround = '';

    public string $newVariantImageUrl = '';

    public $newVariantFile = null;

    // Edit Variant Modal State
    public bool $editVariantModalOpen = false;

    public ?int $editingVariantId = null;

    public string $editVariantName = '';

    public ?int $editVariantSupplierId = null;

    public string $editVariantWebsiteUrl = '';

    public string $editVariantMockupUrl = '';

    public string $editVariantTemplateUrl = '';

    public string $editVariantImageUrl = '';

    public $editVariantFile = null;

    public string $editVariantSpecs = '';

    public string $editVariantTurnaround = '';

    // Edit Product Modal State
    public bool $editProductModalOpen = false;

    public ?int $editingProductId = null;

    public string $editProductName = '';

    public ?int $editProductCategoryId = null;

    public ?int $editProductSupplierId = null;

    public string $editProductSizes = '';

    public string $editProductDescription = '';

    public string $editProductImageUrl = '';

    public $editProductFile = null;

    public $newProductFile = null;

    // Create Category Modal State
    public bool $createCategoryModalOpen = false;

    public string $newCategoryName = '';

    public string $newCategoryDescription = '';

    public string $newCategoryImageUrl = '';

    public $newCategoryFile = null;

    // Edit Category Modal State
    public bool $editCategoryModalOpen = false;

    public ?int $editingCategoryId = null;

    public string $editCategoryName = '';

    public string $editCategoryImageUrl = '';

    public $editCategoryFile = null;

    public string $editCategoryDescription = '';

    // Flash message state
    public ?string $feedbackMessage = null;

    public function mount(): void
    {
        // Default category filter to all
        $firstCat = ProductCategory::orderBy('sort_order')->first();
        if ($firstCat && $this->filterCategory === 'all') {
            // Keep 'all' as default
        }
    }

    #[Computed]
    public function categories()
    {
        return ProductCategory::orderBy('sort_order')->orderBy('name')->get();
    }

    #[Computed]
    public function suppliers()
    {
        InstallationType::syncAllToSuppliers();

        return Supplier::active()->orderBy('name')->get();
    }

    #[Computed]
    public function products()
    {
        $query = Product::query()
            ->with([
                'category',
                'supplier',
                'variants' => function ($vq) {
                    $vq->active()
                        ->orderBy('sort_order')
                        ->orderBy('name')
                        ->with(['supplier', 'tiers' => function ($tq) {
                            $tq->orderBy('quantity');
                        }]);
                },
            ])
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($this->filterCategory !== 'all') {
            $query->where('category_id', $this->filterCategory);
        }

        if ($this->filterSupplier !== 'all') {
            $query->where(function ($sq) {
                $sq->where('supplier_id', $this->filterSupplier)
                    ->orWhereHas('variants', function ($vq) {
                        $vq->where('supplier_id', $this->filterSupplier);
                    });
            });
        }

        if (trim($this->search) !== '') {
            $query->search($this->search);
        }

        return $query->get();
    }

    #[Computed]
    public function categoriesWithProducts()
    {
        $categoriesQuery = ProductCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($this->filterCategory !== 'all') {
            $categoriesQuery->where('id', $this->filterCategory);
        }

        $categories = $categoriesQuery->get();
        $allProducts = $this->products;

        return $categories->map(function ($cat) use ($allProducts) {
            $catProducts = $allProducts->where('category_id', $cat->id);
            $cat->setRelation('filtered_products', $catProducts);

            return $cat;
        })->filter(function ($cat) {
            return $cat->filtered_products->isNotEmpty() || (string) $this->filterCategory === (string) $cat->id;
        });
    }

    /**
     * Inline update for a price tier cell (Excel-style editing).
     */
    public function quickUpdateTier(int $tierId, string $field, mixed $value): void
    {
        abort_unless(Auth::user()?->canManagePricing(), 403, __('No tienes permiso para modificar precios.'));

        $tier = ProductPriceTier::findOrFail($tierId);

        if ($field === 'production_cost') {
            $tier->production_cost = max(0, (float) $value);
        } elseif ($field === 'markup_percent') {
            $tier->markup_percent = max(0, (float) $value);
        } elseif ($field === 'quantity') {
            $tier->quantity = max(1, (int) $value);
        } elseif ($field === 'notes') {
            $tier->notes = trim((string) $value) ?: null;
        }

        $tier->save();

        $this->dispatch('tier-saved', id: $tierId);
    }

    /**
     * Quick delete for a price tier.
     */
    public function deleteTier(int $tierId): void
    {
        abort_unless(Auth::user()?->canManagePricing(), 403);

        $tier = ProductPriceTier::findOrFail($tierId);
        $tier->delete();

        $this->feedbackMessage = __('Escala de precio eliminada.');
    }

    /**
     * Open interactive calculator modal for a variant or general.
     */
    public function openCalculator(?int $productId = null, ?int $variantId = null): void
    {
        $this->calcProductId = $productId;
        $this->calcVariantId = $variantId;

        if ($variantId) {
            $variant = ProductVariant::with(['tiers', 'product'])->find($variantId);
            if ($variant) {
                $this->calcProductId = $variant->product_id;
                $firstTier = $variant->tiers->first();
                if ($firstTier) {
                    $this->calcQuantity = $firstTier->quantity;
                    $this->calcCost = (float) $firstTier->production_cost;
                    $this->calcMarkup = (float) $firstTier->markup_percent;
                }
            }
        }

        $this->calculatorOpen = true;
    }

    public function closeCalculator(): void
    {
        $this->calculatorOpen = false;
    }

    public function applyPresetMarkup(float $percent): void
    {
        $this->calcMarkup = $percent;
    }

    /**
     * Save simulated values directly into the variant tier.
     */
    public function saveCalculatorToVariant(): void
    {
        abort_unless(Auth::user()?->canManagePricing(), 403);

        if (! $this->calcVariantId) {
            return;
        }

        $variant = ProductVariant::findOrFail($this->calcVariantId);

        $tier = ProductPriceTier::updateOrCreate(
            [
                'product_variant_id' => $variant->id,
                'quantity' => $this->calcQuantity,
            ],
            [
                'production_cost' => $this->calcCost,
                'markup_percent' => $this->calcMarkup,
            ]
        );

        $this->calculatorOpen = false;
        $this->feedbackMessage = __('Escala guardada exitosamente.');
    }

    /**
     * Open modal to add a new price tier to a specific variant.
     */
    public function openAddTierModal(int $variantId): void
    {
        abort_unless(Auth::user()?->canManagePricing(), 403);

        $this->targetVariantId = $variantId;
        $variant = ProductVariant::with('tiers')->find($variantId);

        $highestQty = $variant?->tiers->max('quantity') ?? 100;
        $this->newTierQty = $highestQty * 2;
        $this->newTierCost = 30.0;
        $this->newTierMarkup = 50.0;

        $this->addTierModalOpen = true;
    }

    public function createTier(): void
    {
        abort_unless(Auth::user()?->canManagePricing(), 403);

        $this->validate([
            'targetVariantId' => 'required|exists:product_variants,id',
            'newTierQty' => 'required|integer|min:1',
            'newTierCost' => 'required|numeric|min:0',
            'newTierMarkup' => 'required|numeric|min:0',
        ]);

        ProductPriceTier::create([
            'product_variant_id' => $this->targetVariantId,
            'quantity' => $this->newTierQty,
            'production_cost' => $this->newTierCost,
            'markup_percent' => $this->newTierMarkup,
        ]);

        $this->addTierModalOpen = false;
        $this->feedbackMessage = __('Nueva escala de cantidad agregada con éxito.');
    }

    /**
     * Open modal to create a new product.
     */
    public function openCreateProductModal(): void
    {
        abort_unless(Auth::user()?->canManagePricing(), 403);

        $this->newProductName = '';
        $this->newCategoryId = $this->categories->first()?->id;
        $this->newSupplierId = $this->suppliers->first()?->id;
        $this->newSizes = '';
        $this->newDescription = '';
        $this->newInitialVariant = 'Regular';
        $this->newProductFile = null;
        $this->createProductModalOpen = true;
    }

    public function createProduct(): void
    {
        abort_unless(Auth::user()?->canManagePricing(), 403);

        $this->validate([
            'newProductName' => 'required|string|max:255',
            'newCategoryId' => 'required|exists:product_categories,id',
            'newSupplierId' => 'nullable|exists:suppliers,id',
            'newInitialVariant' => 'required|string|max:100',
            'newProductFile' => 'nullable|image|max:5120',
        ]);

        $sizesArray = [];
        if (trim($this->newSizes) !== '') {
            $sizesArray = array_values(array_filter(array_map('trim', explode(',', $this->newSizes))));
        }

        $imageUrl = null;
        if ($this->newProductFile) {
            $path = $this->newProductFile->store('products', 'public');
            $imageUrl = Storage::url($path);
        }

        $product = Product::create([
            'name' => trim($this->newProductName),
            'category_id' => $this->newCategoryId,
            'supplier_id' => $this->newSupplierId,
            'possible_sizes' => $sizesArray,
            'description' => trim($this->newDescription) ?: null,
            'image_url' => $imageUrl,
            'is_active' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'supplier_id' => $this->newSupplierId,
            'name' => trim($this->newInitialVariant),
            'is_active' => true,
        ]);

        // Default initial tier
        ProductPriceTier::create([
            'product_variant_id' => $variant->id,
            'quantity' => 100,
            'production_cost' => 20.00,
            'markup_percent' => 50.00,
        ]);

        $this->newProductFile = null;
        $this->createProductModalOpen = false;
        $this->feedbackMessage = __('Producto creado correctamente.');
    }

    public function openAddVariantModal(int $productId): void
    {
        abort_unless(Auth::user()?->canManagePricing(), 403);

        $product = Product::find($productId);
        $this->targetProductId = $productId;
        $this->newVariantName = '';
        $this->newVariantSpecs = '';
        $this->newVariantTurnaround = '';
        $this->newVariantSupplierId = $product?->supplier_id;
        $this->newVariantWebsiteUrl = '';
        $this->newVariantMockupUrl = '';
        $this->newVariantTemplateUrl = '';
        $this->newVariantImageUrl = '';
        $this->newVariantFile = null;
        $this->addVariantModalOpen = true;
    }

    public function createVariant(): void
    {
        abort_unless(Auth::user()?->canManagePricing(), 403);

        $this->validate([
            'targetProductId' => 'required|exists:products,id',
            'newVariantName' => 'required|string|max:255',
            'newVariantSupplierId' => 'nullable|exists:suppliers,id',
            'newVariantWebsiteUrl' => 'nullable|string|max:500',
            'newVariantMockupUrl' => 'nullable|string|max:500',
            'newVariantTemplateUrl' => 'nullable|string|max:500',
            'newVariantImageUrl' => 'nullable|string|max:500',
            'newVariantFile' => 'nullable|image|max:5120',
        ]);

        $imageUrl = trim($this->newVariantImageUrl) ?: null;
        if ($this->newVariantFile) {
            $path = $this->newVariantFile->store('variants', 'public');
            $imageUrl = Storage::url($path);
        }

        $variant = ProductVariant::create([
            'product_id' => $this->targetProductId,
            'supplier_id' => $this->newVariantSupplierId,
            'name' => trim($this->newVariantName),
            'specs' => trim($this->newVariantSpecs) ?: null,
            'turnaround_time' => trim($this->newVariantTurnaround) ?: null,
            'website_url' => trim($this->newVariantWebsiteUrl) ?: null,
            'mockup_url' => trim($this->newVariantMockupUrl) ?: null,
            'template_url' => trim($this->newVariantTemplateUrl) ?: null,
            'image_url' => $imageUrl,
            'is_active' => true,
        ]);

        // Default initial tier for new variant
        ProductPriceTier::create([
            'product_variant_id' => $variant->id,
            'quantity' => 100,
            'production_cost' => 20.00,
            'markup_percent' => 50.00,
        ]);

        $this->newVariantFile = null;
        $this->addVariantModalOpen = false;
        $this->feedbackMessage = __('Nueva variante agregada con éxito.');
    }

    public function openEditVariantModal(int $variantId): void
    {
        abort_unless(Auth::user()?->canManagePricing(), 403);

        $variant = ProductVariant::findOrFail($variantId);
        $this->editingVariantId = $variant->id;
        $this->editVariantName = $variant->name;
        $this->editVariantSupplierId = $variant->supplier_id;
        $this->editVariantWebsiteUrl = $variant->website_url ?? '';
        $this->editVariantMockupUrl = $variant->mockup_url ?? '';
        $this->editVariantTemplateUrl = $variant->template_url ?? '';
        $this->editVariantImageUrl = $variant->image_url ?? '';
        $this->editVariantFile = null;
        $this->editVariantSpecs = $variant->specs ?? '';
        $this->editVariantTurnaround = $variant->turnaround_time ?? '';

        $this->editVariantModalOpen = true;
    }

    public function saveVariant(): void
    {
        abort_unless(Auth::user()?->canManagePricing(), 403);

        $this->validate([
            'editingVariantId' => 'required|exists:product_variants,id',
            'editVariantName' => 'required|string|max:255',
            'editVariantSupplierId' => 'nullable|exists:suppliers,id',
            'editVariantWebsiteUrl' => 'nullable|string|max:500',
            'editVariantMockupUrl' => 'nullable|string|max:500',
            'editVariantTemplateUrl' => 'nullable|string|max:500',
            'editVariantImageUrl' => 'nullable|string|max:500',
            'editVariantFile' => 'nullable|image|max:5120',
        ]);

        $imageUrl = trim($this->editVariantImageUrl) ?: null;
        if ($this->editVariantFile) {
            $path = $this->editVariantFile->store('variants', 'public');
            $imageUrl = Storage::url($path);
        }

        $variant = ProductVariant::findOrFail($this->editingVariantId);
        $variant->update([
            'name' => trim($this->editVariantName),
            'supplier_id' => $this->editVariantSupplierId,
            'website_url' => trim($this->editVariantWebsiteUrl) ?: null,
            'mockup_url' => trim($this->editVariantMockupUrl) ?: null,
            'template_url' => trim($this->editVariantTemplateUrl) ?: null,
            'image_url' => $imageUrl,
            'specs' => trim($this->editVariantSpecs) ?: null,
            'turnaround_time' => trim($this->editVariantTurnaround) ?: null,
        ]);

        $this->editVariantFile = null;
        $this->editVariantModalOpen = false;
        $this->feedbackMessage = __('Subproducto actualizado correctamente.');
    }

    public function deleteVariant(int $variantId): void
    {
        abort_unless(Auth::user()?->canManagePricing(), 403);

        $variant = ProductVariant::findOrFail($variantId);
        $variant->delete();

        $this->feedbackMessage = __('Subproducto eliminado.');
    }

    public function openEditProductModal(int $productId): void
    {
        abort_unless(Auth::user()?->canManagePricing(), 403);

        $product = Product::findOrFail($productId);
        $this->editingProductId = $product->id;
        $this->editProductName = $product->name;
        $this->editProductCategoryId = $product->category_id;
        $this->editProductSupplierId = $product->supplier_id;
        $this->editProductSizes = is_array($product->possible_sizes) ? implode(', ', $product->possible_sizes) : ($product->possible_sizes ?? '');
        $this->editProductDescription = $product->description ?? '';
        $this->editProductImageUrl = $product->image_url ?? '';
        $this->editProductFile = null;

        $this->editProductModalOpen = true;
    }

    public function saveProduct(): void
    {
        abort_unless(Auth::user()?->canManagePricing(), 403);

        $this->validate([
            'editingProductId' => 'required|exists:products,id',
            'editProductName' => 'required|string|max:255',
            'editProductCategoryId' => 'required|exists:product_categories,id',
            'editProductSupplierId' => 'nullable|exists:suppliers,id',
            'editProductImageUrl' => 'nullable|string|max:500',
            'editProductFile' => 'nullable|image|max:5120',
        ]);

        $sizesArray = [];
        if (trim($this->editProductSizes) !== '') {
            $sizesArray = array_values(array_filter(array_map('trim', explode(',', $this->editProductSizes))));
        }

        $imageUrl = trim($this->editProductImageUrl) ?: null;
        if ($this->editProductFile) {
            $path = $this->editProductFile->store('products', 'public');
            $imageUrl = Storage::url($path);
        }

        $product = Product::findOrFail($this->editingProductId);
        $product->update([
            'name' => trim($this->editProductName),
            'category_id' => $this->editProductCategoryId,
            'supplier_id' => $this->editProductSupplierId,
            'possible_sizes' => $sizesArray,
            'description' => trim($this->editProductDescription) ?: null,
            'image_url' => $imageUrl,
        ]);

        $this->editProductFile = null;
        $this->editProductModalOpen = false;
        $this->feedbackMessage = __('Producto actualizado correctamente.');
    }

    public function deleteProduct(int $productId): void
    {
        abort_unless(Auth::user()?->canManagePricing(), 403);

        $product = Product::findOrFail($productId);
        $product->delete();

        $this->editProductModalOpen = false;
        $this->feedbackMessage = __('Producto y sus variantes eliminados.');
    }

    public function openCreateCategoryModal(): void
    {
        abort_unless(Auth::user()?->canManagePricing(), 403);

        $this->newCategoryName = '';
        $this->newCategoryDescription = '';
        $this->newCategoryImageUrl = '';
        $this->newCategoryFile = null;
        $this->createCategoryModalOpen = true;
    }

    public function createCategory(): void
    {
        abort_unless(Auth::user()?->canManagePricing(), 403);

        $this->validate([
            'newCategoryName' => 'required|string|max:255|unique:product_categories,name',
            'newCategoryImageUrl' => 'nullable|string|max:500',
            'newCategoryFile' => 'nullable|image|max:5120',
        ]);

        $imageUrl = trim($this->newCategoryImageUrl) ?: null;
        if ($this->newCategoryFile) {
            $path = $this->newCategoryFile->store('categories', 'public');
            $imageUrl = Storage::url($path);
        }

        $slug = Str::slug($this->newCategoryName);

        ProductCategory::create([
            'name' => trim($this->newCategoryName),
            'slug' => $slug,
            'description' => trim($this->newCategoryDescription) ?: null,
            'image_url' => $imageUrl,
            'sort_order' => ProductCategory::count() + 1,
        ]);

        $this->newCategoryFile = null;
        $this->createCategoryModalOpen = false;
        $this->feedbackMessage = __('Categoría creada correctamente.');
    }

    public function openEditCategoryModal(int $catId): void
    {
        abort_unless(Auth::user()?->canManagePricing(), 403);

        $category = ProductCategory::findOrFail($catId);
        $this->editingCategoryId = $category->id;
        $this->editCategoryName = $category->name;
        $this->editCategoryImageUrl = $category->image_url ?? '';
        $this->editCategoryFile = null;
        $this->editCategoryDescription = $category->description ?? '';
        $this->editCategoryModalOpen = true;
    }

    public function saveCategory(): void
    {
        abort_unless(Auth::user()?->canManagePricing(), 403);

        $this->validate([
            'editingCategoryId' => 'required|exists:product_categories,id',
            'editCategoryName' => 'required|string|max:255',
            'editCategoryImageUrl' => 'nullable|string|max:500',
            'editCategoryFile' => 'nullable|image|max:5120',
        ]);

        $category = ProductCategory::findOrFail($this->editingCategoryId);
        $slug = Str::slug($this->editCategoryName);

        $imageUrl = trim($this->editCategoryImageUrl) ?: null;
        if ($this->editCategoryFile) {
            $path = $this->editCategoryFile->store('categories', 'public');
            $imageUrl = Storage::url($path);
        }

        $category->update([
            'name' => trim($this->editCategoryName),
            'slug' => $slug,
            'image_url' => $imageUrl,
            'description' => trim($this->editCategoryDescription) ?: null,
        ]);

        $this->editCategoryFile = null;
        $this->editCategoryModalOpen = false;
        $this->feedbackMessage = __('Categoría actualizada correctamente.');
    }

    public function deleteCategory(int $catId): void
    {
        abort_unless(Auth::user()?->canManagePricing(), 403);

        $category = ProductCategory::withCount('products')->findOrFail($catId);

        if ($category->products_count > 0) {
            $this->addError('deleteCategoryError', __('No se puede eliminar la categoría porque contiene productos. Elimina o mueve los productos primero.'));

            return;
        }

        $category->delete();

        $this->editCategoryModalOpen = false;
        $this->feedbackMessage = __('Categoría eliminada.');
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->filterCategory = 'all';
        $this->filterSupplier = 'all';
    }

    public function render(): View
    {
        return view('livewire.pricing.pricing-index');
    }
}
