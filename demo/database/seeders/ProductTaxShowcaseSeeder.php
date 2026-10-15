<?php

declare(strict_types=1);

namespace Database\Seeders;

use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Enums\ProductVisibility;
use AIArmada\Products\Models\Category;
use AIArmada\Products\Models\Collection;
use AIArmada\Products\Models\Product;
use Illuminate\Database\Seeder;

final class ProductTaxShowcaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🧾 Creating Product Tax Showcase Data...');

        $homeCategory = Category::query()->where('slug', 'home-living')->first();
        $featuredCollection = Collection::query()->where('slug', 'featured')->first();
        $bestSellersCollection = Collection::query()->where('slug', 'best-sellers')->first();

        $reducedProduct = Product::firstOrCreate(
            ['sku' => 'TAX-REDUCED-001'],
            [
                'name' => 'Student Essentials Starter Pack',
                'slug' => 'student-essentials-starter-pack',
                'description' => 'A practical stationery and classroom bundle used to demonstrate reduced-rate tax handling in the demo storefront.',
                'short_description' => 'Reduced-rate educational bundle',
                'type' => ProductType::Simple,
                'status' => ProductStatus::Active,
                'visibility' => ProductVisibility::CatalogSearch,
                'price' => 12900,
                'currency' => 'MYR',
                'is_featured' => true,
                'is_taxable' => true,
                'requires_shipping' => true,
                'tax_class' => 'reduced',
                'published_at' => now(),
                'metadata' => [
                    'tax_story' => 'Reduced-rate educational essentials showcase',
                ],
            ],
        );

        $zeroRatedProduct = Product::firstOrCreate(
            ['sku' => 'TAX-ZERO-001'],
            [
                'name' => 'Basic Pantry Rice Bundle',
                'slug' => 'basic-pantry-rice-bundle',
                'description' => 'A zero-rated pantry staple bundle used to show how the checkout estimate respects product-level tax classes.',
                'short_description' => 'Zero-rated pantry essentials',
                'type' => ProductType::Simple,
                'status' => ProductStatus::Active,
                'visibility' => ProductVisibility::CatalogSearch,
                'price' => 15900,
                'currency' => 'MYR',
                'is_featured' => true,
                'is_taxable' => true,
                'requires_shipping' => true,
                'tax_class' => 'zero',
                'published_at' => now(),
                'metadata' => [
                    'tax_story' => 'Zero-rated essentials showcase',
                ],
            ],
        );

        if ($homeCategory !== null) {
            $reducedProduct->categories()->syncWithoutDetaching([$homeCategory->id]);
            $zeroRatedProduct->categories()->syncWithoutDetaching([$homeCategory->id]);
        }

        if ($featuredCollection !== null) {
            $featuredCollection->products()->syncWithoutDetaching([
                $reducedProduct->id => ['position' => 5],
                $zeroRatedProduct->id => ['position' => 6],
            ]);
        }

        if ($bestSellersCollection !== null) {
            $bestSellersCollection->products()->syncWithoutDetaching([
                $reducedProduct->id => ['position' => 4],
            ]);
        }

        $this->seedInventory($reducedProduct, 32, 8);
        $this->seedInventory($zeroRatedProduct, 28, 6);

        $reducedProduct->attachTags(['tax-showcase', 'reduced-rate', 'education', 'essentials']);
        $zeroRatedProduct->attachTags(['tax-showcase', 'zero-rate', 'pantry', 'essentials']);

        $this->command->info('   ✓ Added reduced-rate and zero-rate catalog examples with live inventory');
    }

    private function seedInventory(Product $product, int $quantityOnHand, int $reorderPoint): void
    {
        $locations = InventoryLocation::query()
            ->active()
            ->orderByDesc('priority')
            ->take(2)
            ->get();

        if ($locations->isEmpty()) {
            $locations = collect([InventoryLocation::getOrCreateDefault()]);
        }

        $perLocationQuantity = max(1, (int) ceil($quantityOnHand / max(1, $locations->count())));

        foreach ($locations as $location) {
            InventoryLevel::updateOrCreate(
                [
                    'inventoryable_type' => $product->getMorphClass(),
                    'inventoryable_id' => $product->id,
                    'location_id' => $location->id,
                ],
                [
                    'quantity_on_hand' => $perLocationQuantity,
                    'quantity_reserved' => 0,
                    'reorder_point' => $reorderPoint,
                    'safety_stock' => max(2, (int) floor($reorderPoint / 2)),
                    'max_stock' => max($perLocationQuantity * 2, $perLocationQuantity + 10),
                    'unit_of_measure' => 'EA',
                    'unit_conversion_factor' => 1.0,
                    'lead_time_days' => 5,
                    'metadata' => [
                        'source' => 'product-tax-showcase-seeder',
                        'tax_story' => $product->tax_class,
                    ],
                ],
            );
        }
    }
}
