<?php

namespace Tests\Feature\Pricing;

use App\Enums\UserRole;
use App\Livewire\Pricing\DesignResourcesIndex;
use App\Livewire\Pricing\PricingIndex;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductPriceTier;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\ProductPricingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ProductPricingTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $managerUser;

    protected User $designerUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ProductPricingSeeder::class);

        $this->adminUser = User::factory()->create([
            'role' => UserRole::ADMIN,
        ]);

        $this->managerUser = User::factory()->create([
            'role' => UserRole::COORDINATOR,
        ]);

        $this->designerUser = User::factory()->create([
            'role' => UserRole::DESIGNER,
        ]);
    }

    public function test_pricing_page_renders_successfully(): void
    {
        $this->actingAs($this->adminUser)
            ->get('/pricing')
            ->assertStatus(200)
            ->assertSee('Business Card')
            ->assertSee('4over');
    }

    public function test_design_resources_page_renders_successfully(): void
    {
        $this->actingAs($this->designerUser)
            ->get('/design-resources')
            ->assertStatus(200)
            ->assertSee('Roll-Up Banner')
            ->assertSee('Picasso');
    }

    public function test_admin_and_manager_can_quick_update_pricing_tier(): void
    {
        $tier = ProductPriceTier::first();

        Livewire::actingAs($this->adminUser)
            ->test(PricingIndex::class)
            ->call('quickUpdateTier', $tier->id, 'production_cost', 50.00)
            ->assertDispatched('tier-saved');

        $tier->refresh();
        $this->assertEquals(50.00, (float) $tier->production_cost);
        $this->assertGreaterThan(50.00, (float) $tier->calculated_price);

        // Test manager (Coordinator) can also update
        Livewire::actingAs($this->managerUser)
            ->test(PricingIndex::class)
            ->call('quickUpdateTier', $tier->id, 'markup_percent', 75.00)
            ->assertDispatched('tier-saved');

        $tier->refresh();
        $this->assertEquals(75.00, (float) $tier->markup_percent);
        // Cost: 50, Markup: 75% -> Calculated: 50 * 1.75 = 87.50
        $this->assertEquals(87.50, (float) $tier->calculated_price);
    }

    public function test_designer_cannot_modify_pricing_tier(): void
    {
        $tier = ProductPriceTier::first();

        Livewire::actingAs($this->designerUser)
            ->test(PricingIndex::class)
            ->call('quickUpdateTier', $tier->id, 'production_cost', 999.00)
            ->assertStatus(403);
    }

    public function test_recalculate_logic(): void
    {
        $tier = new ProductPriceTier([
            'quantity' => 500,
            'production_cost' => 100.00,
            'markup_percent' => 50.00,
        ]);

        $tier->recalculate();

        // 100 * 1.50 = 150
        $this->assertEquals(150.00, (float) $tier->calculated_price);
        $this->assertEquals(150.00, (float) $tier->final_price);
        $this->assertEquals(0.30, (float) $tier->unit_price);
    }

    public function test_admin_can_create_product_and_add_tier(): void
    {
        $category = ProductCategory::first();
        $supplier = Supplier::first();

        Livewire::actingAs($this->adminUser)
            ->test(PricingIndex::class)
            ->set('newProductName', 'Custom Acrylic Signs')
            ->set('newCategoryId', $category->id)
            ->set('newSupplierId', $supplier->id)
            ->set('newInitialVariant', 'Frosted 5mm')
            ->set('newSizes', '12x12, 18x24')
            ->call('createProduct')
            ->assertHasNoErrors();

        $product = Product::where('name', 'Custom Acrylic Signs')->first();
        $this->assertNotNull($product);
        $this->assertEquals(['12x12', '18x24'], $product->possible_sizes);

        $variant = $product->variants->first();
        $this->assertNotNull($variant);
        $this->assertEquals('Frosted 5mm', $variant->name);

        // Add additional tier
        Livewire::actingAs($this->adminUser)
            ->test(PricingIndex::class)
            ->set('targetVariantId', $variant->id)
            ->set('newTierQty', 50)
            ->set('newTierCost', 45.00)
            ->set('newTierMarkup', 60.00)
            ->call('createTier')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('product_price_tiers', [
            'product_variant_id' => $variant->id,
            'quantity' => 50,
            'production_cost' => 45.00,
        ]);
    }

    public function test_manager_can_update_design_resource_links(): void
    {
        $product = Product::first();

        Livewire::actingAs($this->managerUser)
            ->test(DesignResourcesIndex::class)
            ->call('openEditModal', $product->id)
            ->set('editWebsiteUrl', 'https://example.com/updated-product')
            ->set('editMockupUrl', 'https://assets.kudos.com/new-mockup.zip')
            ->set('editTemplateUrl', 'https://assets.kudos.com/new-template.pdf')
            ->set('editSizes', '10x10, 20x20')
            ->call('saveResourceLinks')
            ->assertHasNoErrors();

        $product->refresh();
        $this->assertEquals('https://example.com/updated-product', $product->website_url);
        $this->assertEquals('https://assets.kudos.com/new-mockup.zip', $product->mockup_url);
        $this->assertEquals('https://assets.kudos.com/new-template.pdf', $product->template_url);
        $this->assertEquals(['10x10', '20x20'], $product->possible_sizes);
    }

    public function test_admin_can_update_variant_supplier_and_urls(): void
    {
        $product = Product::first();
        $variant = $product->variants->first();
        $supplier = Supplier::where('name', 'Picasso')->first();

        Livewire::actingAs($this->adminUser)
            ->test(PricingIndex::class)
            ->call('openEditVariantModal', $variant->id)
            ->set('editVariantName', 'Updated Variant Foil')
            ->set('editVariantSupplierId', $supplier->id)
            ->set('editVariantWebsiteUrl', 'https://picassoprints.com/custom-foil')
            ->set('editVariantMockupUrl', 'https://assets.kudos.com/mockup-foil.zip')
            ->set('editVariantTemplateUrl', 'https://assets.kudos.com/template-foil.pdf')
            ->call('saveVariant')
            ->assertHasNoErrors();

        $variant->refresh();
        $this->assertEquals('Updated Variant Foil', $variant->name);
        $this->assertEquals($supplier->id, $variant->supplier_id);
        $this->assertEquals('https://picassoprints.com/custom-foil', $variant->website_url);
        $this->assertEquals('https://assets.kudos.com/mockup-foil.zip', $variant->mockup_url);
        $this->assertEquals('https://assets.kudos.com/template-foil.pdf', $variant->template_url);
    }

    public function test_admin_can_crud_categories_and_products(): void
    {
        // 1. Create Category
        Livewire::actingAs($this->adminUser)
            ->test(PricingIndex::class)
            ->set('newCategoryName', 'Large Format Packaging')
            ->set('newCategoryDescription', 'Cajas y empaques rígidos')
            ->set('newCategoryImageUrl', 'https://example.com/packaging.jpg')
            ->call('createCategory')
            ->assertHasNoErrors();

        $category = ProductCategory::where('name', 'Large Format Packaging')->first();
        $this->assertNotNull($category);

        // 2. Edit Category
        Livewire::actingAs($this->adminUser)
            ->test(PricingIndex::class)
            ->call('openEditCategoryModal', $category->id)
            ->set('editCategoryName', 'Custom Packaging')
            ->set('editCategoryImageUrl', 'https://example.com/new-packaging.jpg')
            ->call('saveCategory')
            ->assertHasNoErrors();

        $category->refresh();
        $this->assertEquals('Custom Packaging', $category->name);

        // 3. Edit Product
        $product = Product::first();
        Livewire::actingAs($this->adminUser)
            ->test(PricingIndex::class)
            ->call('openEditProductModal', $product->id)
            ->set('editProductName', 'Updated Business Card Name')
            ->set('editProductSizes', '2x3.5, 3x4')
            ->call('saveProduct')
            ->assertHasNoErrors();

        $product->refresh();
        $this->assertEquals('Updated Business Card Name', $product->name);
        $this->assertEquals(['2x3.5', '3x4'], $product->possible_sizes);
    }

    public function test_admin_can_upload_category_and_product_image_files(): void
    {
        Storage::fake('public');

        $categoryFile = UploadedFile::fake()->image('category_cover.jpg');
        $productFile = UploadedFile::fake()->image('product_thumb.png');

        Livewire::actingAs($this->adminUser)
            ->test(PricingIndex::class)
            ->set('newCategoryName', 'Uploaded Category')
            ->set('newCategoryFile', $categoryFile)
            ->call('createCategory')
            ->assertHasNoErrors();

        $category = ProductCategory::where('name', 'Uploaded Category')->first();
        $this->assertNotNull($category);
        $this->assertNotNull($category->image_url);
        $this->assertStringContainsString('/storage/categories/', $category->image_url);

        $product = Product::first();
        Livewire::actingAs($this->adminUser)
            ->test(PricingIndex::class)
            ->call('openEditProductModal', $product->id)
            ->set('editProductFile', $productFile)
            ->call('saveProduct')
            ->assertHasNoErrors();

        $product->refresh();
        $this->assertNotNull($product->image_url);
        $this->assertStringContainsString('/storage/products/', $product->image_url);

        $variantFile = UploadedFile::fake()->image('variant_thumb.png');
        $variant = $product->variants->first();

        Livewire::actingAs($this->adminUser)
            ->test(PricingIndex::class)
            ->call('openEditVariantModal', $variant->id)
            ->set('editVariantFile', $variantFile)
            ->call('saveVariant')
            ->assertHasNoErrors();

        $variant->refresh();
        $this->assertNotNull($variant->image_url);
        $this->assertStringContainsString('/storage/variants/', $variant->image_url);
    }
}
