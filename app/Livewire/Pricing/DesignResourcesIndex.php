<?php

namespace App\Livewire\Pricing;

use App\Models\InstallationType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class DesignResourcesIndex extends Component
{
    public string $search = '';

    public string $filterCategory = 'all';

    public string $filterSupplier = 'all';

    // Edit Resource Modal
    public bool $editModalOpen = false;

    public ?int $editingProductId = null;

    public string $editProductName = '';

    public string $editWebsiteUrl = '';

    public string $editMockupUrl = '';

    public string $editTemplateUrl = '';

    public string $editSizes = '';

    public string $editDescription = '';

    public ?string $feedbackMessage = null;

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
            ->with(['category', 'supplier', 'variants'])
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($this->filterCategory !== 'all') {
            $query->where('category_id', $this->filterCategory);
        }

        if ($this->filterSupplier !== 'all') {
            $query->where('supplier_id', $this->filterSupplier);
        }

        if (trim($this->search) !== '') {
            $query->search($this->search);
        }

        return $query->get();
    }

    public function openEditModal(int $productId): void
    {
        abort_unless(Auth::user()?->canManagePricing(), 403);

        $product = Product::findOrFail($productId);
        $this->editingProductId = $product->id;
        $this->editProductName = $product->name;
        $this->editWebsiteUrl = $product->website_url ?? '';
        $this->editMockupUrl = $product->mockup_url ?? '';
        $this->editTemplateUrl = $product->template_url ?? '';
        $this->editSizes = is_array($product->possible_sizes) ? implode(', ', $product->possible_sizes) : ($product->possible_sizes ?? '');
        $this->editDescription = $product->description ?? '';

        $this->editModalOpen = true;
    }

    public function saveResourceLinks(): void
    {
        abort_unless(Auth::user()?->canManagePricing(), 403);

        $this->validate([
            'editingProductId' => 'required|exists:products,id',
            'editWebsiteUrl' => 'nullable|string|max:500',
            'editMockupUrl' => 'nullable|string|max:500',
            'editTemplateUrl' => 'nullable|string|max:500',
        ]);

        $product = Product::findOrFail($this->editingProductId);

        $sizesArray = [];
        if (trim($this->editSizes) !== '') {
            $sizesArray = array_values(array_filter(array_map('trim', explode(',', $this->editSizes))));
        }

        $product->update([
            'website_url' => trim($this->editWebsiteUrl) ?: null,
            'mockup_url' => trim($this->editMockupUrl) ?: null,
            'template_url' => trim($this->editTemplateUrl) ?: null,
            'possible_sizes' => $sizesArray,
            'description' => trim($this->editDescription) ?: null,
        ]);

        $this->editModalOpen = false;
        $this->feedbackMessage = __('Recursos actualizados correctamente.');
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->filterCategory = 'all';
        $this->filterSupplier = 'all';
    }

    public function render(): View
    {
        return view('livewire.pricing.design-resources-index');
    }
}
