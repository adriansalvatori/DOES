<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductPriceTier;
use App\Models\ProductVariant;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class ProductPricingSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Suppliers
        $fourOver = Supplier::updateOrCreate(
            ['name' => '4over'],
            [
                'website_url' => 'https://4over.com',
                'contact_info' => 'support@4over.com | 1-877-782-3983',
                'notes' => 'Proveedor principal para Stationary, tarjetas, flyers y folletos con despacho rápido.',
                'is_active' => true,
            ]
        );

        $fourImprint = Supplier::updateOrCreate(
            ['name' => '4imprint'],
            [
                'website_url' => 'https://4imprint.com',
                'contact_info' => 'service@4imprint.com | 1-877-446-7746',
                'notes' => 'Especialistas en artículos promocionales, bolígrafos, termos y merchandising corporativo.',
                'is_active' => true,
            ]
        );

        $picasso = Supplier::updateOrCreate(
            ['name' => 'Picasso'],
            [
                'website_url' => 'https://picassoprints.com',
                'contact_info' => 'orders@picassoprints.com | 1-800-555-0199',
                'notes' => 'Proveedor de gran formato, lonas, roll-ups, vinyl adhesivo y rotulación rígida.',
                'is_active' => true,
            ]
        );

        // 2. Categories
        $stationary = ProductCategory::updateOrCreate(
            ['name' => 'Stationary'],
            [
                'slug' => 'stationary',
                'description' => 'Papelería corporativa de alta calidad, tarjetas de presentación, hojas membretadas y sobres.',
                'icon' => 'files',
                'sort_order' => 1,
            ]
        );

        $signs = ProductCategory::updateOrCreate(
            ['name' => 'Signs & Banners'],
            [
                'slug' => 'signs-banners',
                'description' => 'Rotulación de gran formato, lonas publicitarias, banners retráctiles y letreros exteriores.',
                'icon' => 'flag',
                'sort_order' => 2,
            ]
        );

        $promotional = ProductCategory::updateOrCreate(
            ['name' => 'Promotional'],
            [
                'slug' => 'promotional',
                'description' => 'Artículos promocionales para marcas, bolígrafos, mugs, termos y merchandising corporativo.',
                'icon' => 'gift',
                'sort_order' => 3,
            ]
        );

        $apparel = ProductCategory::updateOrCreate(
            ['name' => 'Apparel'],
            [
                'slug' => 'apparel',
                'description' => 'Textil y ropa personalizada, camisetas, gorras y bordados corporativos.',
                'icon' => 'shirt',
                'sort_order' => 4,
            ]
        );

        // 3. Product: Business Card
        $businessCard = Product::updateOrCreate(
            ['name' => 'Business Card', 'category_id' => $stationary->id],
            [
                'supplier_id' => $fourOver->id,
                'description' => 'Tarjetas de presentación profesionales en diversos materiales y acabados de lujo.',
                'possible_sizes' => ['2" x 3.5" (Standard US)', '3.35" x 2.16" (European)'],
                'website_url' => 'https://4over.com/business-cards',
                'mockup_url' => 'https://assets.kudos.com/mockups/business-cards-bundle.zip',
                'template_url' => 'https://assets.kudos.com/templates/business-card-standard-ai-psd.zip',
                'default_markup_percent' => 60.00,
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        // Variants for Business Card
        $bcVariants = [
            'Regular' => [
                'supplier_id' => $fourOver->id,
                'specs' => '16pt Premium Cardstock con acabado Mate o UV Brillante',
                'turnaround' => '2-4 días hábiles',
                'website_url' => 'https://4over.com/business-cards',
                'mockup_url' => 'https://assets.kudos.com/mockups/business-cards-regular-psd.zip',
                'template_url' => 'https://assets.kudos.com/templates/bc-regular-template.zip',
                'tiers' => [
                    ['qty' => 100, 'cost' => 15.00, 'markup' => 70],
                    ['qty' => 250, 'cost' => 20.00, 'markup' => 65],
                    ['qty' => 500, 'cost' => 26.00, 'markup' => 60],
                    ['qty' => 1000, 'cost' => 36.00, 'markup' => 55],
                    ['qty' => 5000, 'cost' => 120.00, 'markup' => 50],
                ],
            ],
            'Rush' => [
                'supplier_id' => $fourOver->id,
                'specs' => 'Impresión express el mismo día / 24 horas',
                'turnaround' => '24 horas',
                'website_url' => 'https://4over.com/same-day-printing',
                'mockup_url' => 'https://assets.kudos.com/mockups/business-cards-rush.zip',
                'template_url' => 'https://assets.kudos.com/templates/bc-rush-template.zip',
                'tiers' => [
                    ['qty' => 100, 'cost' => 25.00, 'markup' => 80],
                    ['qty' => 250, 'cost' => 38.00, 'markup' => 75],
                    ['qty' => 500, 'cost' => 52.00, 'markup' => 70],
                    ['qty' => 1000, 'cost' => 78.00, 'markup' => 65],
                ],
            ],
            'Digital' => [
                'supplier_id' => $fourOver->id,
                'specs' => 'Tirajes cortos de alta definición en prensa digital',
                'turnaround' => '1-2 días hábiles',
                'website_url' => 'https://4over.com/digital-short-run',
                'mockup_url' => null,
                'template_url' => 'https://assets.kudos.com/templates/bc-digital-template.zip',
                'tiers' => [
                    ['qty' => 100, 'cost' => 18.00, 'markup' => 65],
                    ['qty' => 250, 'cost' => 24.00, 'markup' => 60],
                    ['qty' => 500, 'cost' => 32.00, 'markup' => 55],
                ],
            ],
            'Foil front and back' => [
                'supplier_id' => $fourImprint->id,
                'specs' => 'Hot foil stamping dorado/plateado/holográfico ambas caras en 16pt Silk Laminated',
                'turnaround' => '5-7 días hábiles',
                'website_url' => 'https://4imprint.com/foil-cards',
                'mockup_url' => 'https://assets.kudos.com/mockups/bc-foil-gold-silver-psd.zip',
                'template_url' => 'https://assets.kudos.com/templates/bc-foil-layers-template.zip',
                'tiers' => [
                    ['qty' => 250, 'cost' => 75.00, 'markup' => 60],
                    ['qty' => 500, 'cost' => 98.00, 'markup' => 55],
                    ['qty' => 1000, 'cost' => 145.00, 'markup' => 50],
                    ['qty' => 5000, 'cost' => 420.00, 'markup' => 45],
                ],
            ],
            'Plastic' => [
                'supplier_id' => $picasso->id,
                'specs' => '20pt plástico impermeable duradero (Blanco, Transparente o Esmerilado)',
                'turnaround' => '5-8 días hábiles',
                'website_url' => 'https://picassoprints.com/plastic-cards',
                'mockup_url' => 'https://assets.kudos.com/mockups/plastic-cards-transparent-psd.zip',
                'template_url' => 'https://assets.kudos.com/templates/bc-plastic-clear-template.zip',
                'tiers' => [
                    ['qty' => 100, 'cost' => 45.00, 'markup' => 65],
                    ['qty' => 250, 'cost' => 70.00, 'markup' => 60],
                    ['qty' => 500, 'cost' => 110.00, 'markup' => 55],
                    ['qty' => 1000, 'cost' => 180.00, 'markup' => 50],
                ],
            ],
        ];

        foreach ($bcVariants as $vName => $vData) {
            $variant = ProductVariant::updateOrCreate(
                ['product_id' => $businessCard->id, 'name' => $vName],
                [
                    'supplier_id' => $vData['supplier_id'],
                    'specs' => $vData['specs'],
                    'turnaround_time' => $vData['turnaround'],
                    'website_url' => $vData['website_url'] ?? null,
                    'mockup_url' => $vData['mockup_url'] ?? null,
                    'template_url' => $vData['template_url'] ?? null,
                    'is_active' => true,
                ]
            );

            foreach ($vData['tiers'] as $tier) {
                ProductPriceTier::updateOrCreate(
                    ['product_variant_id' => $variant->id, 'quantity' => $tier['qty']],
                    [
                        'production_cost' => $tier['cost'],
                        'markup_percent' => $tier['markup'],
                    ]
                );
            }
        }

        // 4. Product: Roll-Up Banner
        $rollUp = Product::updateOrCreate(
            ['name' => 'Roll-Up Banner', 'category_id' => $signs->id],
            [
                'supplier_id' => $picasso->id,
                'description' => 'Banner retráctil portátil de alta durabilidad con lona antienrollamiento y bolso de transporte.',
                'possible_sizes' => ['33" x 81"', '38" x 81"', '60" x 81"'],
                'website_url' => 'https://picassoprints.com/roll-up-banners',
                'mockup_url' => 'https://assets.kudos.com/mockups/rollup-banner-3d-psd.zip',
                'template_url' => 'https://assets.kudos.com/templates/rollup-33-38-60-templates.zip',
                'default_markup_percent' => 50.00,
                'sort_order' => 2,
                'is_active' => true,
            ]
        );

        $rollupVariants = [
            'Standard Silver Base' => [
                'specs' => 'Base estándar de aluminio plateado con dos patas de soporte',
                'turnaround' => '2-3 días hábiles',
                'tiers' => [
                    ['qty' => 1, 'cost' => 45.00, 'markup' => 65],
                    ['qty' => 2, 'cost' => 82.00, 'markup' => 60],
                    ['qty' => 5, 'cost' => 185.00, 'markup' => 55],
                    ['qty' => 10, 'cost' => 340.00, 'markup' => 50],
                ],
            ],
            'Premium Teardrop Base' => [
                'specs' => 'Base ancha de lujo sin patas visibles con acabado cromado en los extremos',
                'turnaround' => '3-4 días hábiles',
                'tiers' => [
                    ['qty' => 1, 'cost' => 75.00, 'markup' => 60],
                    ['qty' => 2, 'cost' => 140.00, 'markup' => 55],
                    ['qty' => 5, 'cost' => 320.00, 'markup' => 50],
                ],
            ],
        ];

        foreach ($rollupVariants as $vName => $vData) {
            $variant = ProductVariant::updateOrCreate(
                ['product_id' => $rollUp->id, 'name' => $vName],
                [
                    'supplier_id' => $picasso->id,
                    'specs' => $vData['specs'],
                    'turnaround_time' => $vData['turnaround'],
                    'is_active' => true,
                ]
            );

            foreach ($vData['tiers'] as $tier) {
                ProductPriceTier::updateOrCreate(
                    ['product_variant_id' => $variant->id, 'quantity' => $tier['qty']],
                    [
                        'production_cost' => $tier['cost'],
                        'markup_percent' => $tier['markup'],
                    ]
                );
            }
        }
    }
}
