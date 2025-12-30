<?php

declare(strict_types=1);

namespace Database\Seeders;

use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Enums\ProductVisibility;
use AIArmada\Products\Models\Category;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;
use Illuminate\Database\Seeder;

final class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            // Electronics - Smartphones
            [
                'category' => 'smartphones',
                'name' => 'iPhone 15 Pro',
                'sku' => 'IP15-PRO-001',
                'description' => 'Latest Apple smartphone with A17 Pro chip',
                'price' => 539900,
                'compare_at_price' => 599900,
                'variants' => [
                    ['size' => '128GB', 'color' => 'Natural Titanium', 'sku' => 'IP15-PRO-128-NT', 'price' => 539900],
                    ['size' => '256GB', 'color' => 'Natural Titanium', 'sku' => 'IP15-PRO-256-NT', 'price' => 589900],
                    ['size' => '128GB', 'color' => 'Blue Titanium', 'sku' => 'IP15-PRO-128-BT', 'price' => 539900],
                ],
            ],
            [
                'category' => 'smartphones',
                'name' => 'Samsung Galaxy S24 Ultra',
                'sku' => 'SG-S24U-001',
                'description' => 'Samsung flagship with S Pen',
                'price' => 499900,
                'compare_at_price' => null,
                'variants' => [
                    ['size' => '256GB', 'color' => 'Titanium Gray', 'sku' => 'SG-S24U-256-TG', 'price' => 499900],
                    ['size' => '512GB', 'color' => 'Titanium Gray', 'sku' => 'SG-S24U-512-TG', 'price' => 559900],
                ],
            ],
            // Electronics - Laptops
            [
                'category' => 'laptops',
                'name' => 'MacBook Pro 14"',
                'sku' => 'MBP14-M3-001',
                'description' => 'Apple MacBook Pro with M3 Pro chip',
                'price' => 899900,
                'compare_at_price' => 999900,
                'variants' => [
                    ['size' => '512GB', 'color' => 'Space Black', 'sku' => 'MBP14-M3-512-SB', 'price' => 899900],
                    ['size' => '1TB', 'color' => 'Space Black', 'sku' => 'MBP14-M3-1TB-SB', 'price' => 1099900],
                ],
            ],
            // Electronics - Audio
            [
                'category' => 'audio',
                'name' => 'AirPods Pro',
                'sku' => 'APP-2-001',
                'description' => 'Apple AirPods Pro 2nd generation with USB-C',
                'price' => 109900,
                'compare_at_price' => null,
                'variants' => [],
            ],
            [
                'category' => 'audio',
                'name' => 'Sony WH-1000XM5',
                'sku' => 'SONY-WH5-001',
                'description' => 'Premium noise cancelling headphones',
                'price' => 169900,
                'compare_at_price' => 189900,
                'variants' => [
                    ['size' => null, 'color' => 'Black', 'sku' => 'SONY-WH5-BLK', 'price' => 169900],
                    ['size' => null, 'color' => 'Silver', 'sku' => 'SONY-WH5-SLV', 'price' => 169900],
                ],
            ],
            // Fashion - Men's Clothing
            [
                'category' => 'mens-clothing',
                'name' => 'Classic Polo Shirt',
                'sku' => 'POLO-M-001',
                'description' => 'Premium cotton polo shirt',
                'price' => 12900,
                'compare_at_price' => 15900,
                'variants' => [
                    ['size' => 'S', 'color' => 'Navy', 'sku' => 'POLO-M-S-NVY', 'price' => 12900],
                    ['size' => 'M', 'color' => 'Navy', 'sku' => 'POLO-M-M-NVY', 'price' => 12900],
                    ['size' => 'L', 'color' => 'Navy', 'sku' => 'POLO-M-L-NVY', 'price' => 12900],
                    ['size' => 'S', 'color' => 'White', 'sku' => 'POLO-M-S-WHT', 'price' => 12900],
                    ['size' => 'M', 'color' => 'White', 'sku' => 'POLO-M-M-WHT', 'price' => 12900],
                ],
            ],
            // Fashion - Women's Clothing
            [
                'category' => 'womens-clothing',
                'name' => 'Floral Summer Dress',
                'sku' => 'DRESS-W-001',
                'description' => 'Light and breezy summer dress',
                'price' => 18900,
                'compare_at_price' => null,
                'variants' => [
                    ['size' => 'XS', 'color' => 'Blue Floral', 'sku' => 'DRESS-W-XS-BF', 'price' => 18900],
                    ['size' => 'S', 'color' => 'Blue Floral', 'sku' => 'DRESS-W-S-BF', 'price' => 18900],
                    ['size' => 'M', 'color' => 'Blue Floral', 'sku' => 'DRESS-W-M-BF', 'price' => 18900],
                ],
            ],
            // Fashion - Shoes
            [
                'category' => 'shoes',
                'name' => 'Running Sneakers',
                'sku' => 'SNKR-R-001',
                'description' => 'Comfortable running shoes',
                'price' => 45900,
                'compare_at_price' => 55900,
                'variants' => [
                    ['size' => '40', 'color' => 'Black/Red', 'sku' => 'SNKR-R-40-BR', 'price' => 45900],
                    ['size' => '41', 'color' => 'Black/Red', 'sku' => 'SNKR-R-41-BR', 'price' => 45900],
                    ['size' => '42', 'color' => 'Black/Red', 'sku' => 'SNKR-R-42-BR', 'price' => 45900],
                    ['size' => '43', 'color' => 'Black/Red', 'sku' => 'SNKR-R-43-BR', 'price' => 45900],
                ],
            ],
            // Home & Living - Furniture
            [
                'category' => 'furniture',
                'name' => 'Ergonomic Office Chair',
                'sku' => 'CHAIR-ERG-001',
                'description' => 'Adjustable ergonomic chair for home office',
                'price' => 79900,
                'compare_at_price' => null,
                'variants' => [
                    ['size' => null, 'color' => 'Black', 'sku' => 'CHAIR-ERG-BLK', 'price' => 79900],
                    ['size' => null, 'color' => 'Gray', 'sku' => 'CHAIR-ERG-GRY', 'price' => 79900],
                ],
            ],
            // Home & Living - Kitchen
            [
                'category' => 'kitchen',
                'name' => 'Smart Air Fryer',
                'sku' => 'AIRFRY-S-001',
                'description' => 'WiFi-enabled air fryer with app control',
                'price' => 39900,
                'compare_at_price' => 49900,
                'variants' => [],
            ],
        ];

        foreach ($products as $productData) {
            $category = Category::where('slug', $productData['category'])->first();
            $variants = $productData['variants'];
            $compareAtPrice = $productData['compare_at_price'];

            unset($productData['category'], $productData['variants']);
            unset($productData['compare_at_price']);

            $product = Product::create([
                ...$productData,
                'status' => ProductStatus::Active,
                'visibility' => ProductVisibility::CatalogSearch,
                'currency' => 'MYR',
                'compare_price' => $compareAtPrice,
            ]);

            if ($category !== null) {
                $product->categories()->syncWithoutDetaching([$category->id]);
            }

            $defaultVariantId = null;
            foreach ($variants as $variantData) {
                $variantName = implode(' / ', array_filter([$variantData['size'] ?? null, $variantData['color'] ?? null])) ?: null;

                $variant = Variant::create([
                    'product_id' => $product->id,
                    'name' => $variantName,
                    'sku' => $variantData['sku'],
                    'price' => $variantData['price'],
                    'is_enabled' => true,
                ]);

                $defaultVariantId ??= $variant->id;
            }

            if ($defaultVariantId !== null) {
                Variant::where('id', $defaultVariantId)->update(['is_default' => true]);
            }
        }
    }
}
