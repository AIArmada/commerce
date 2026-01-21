<?php

declare(strict_types=1);

namespace Database\Seeders;

use AIArmada\Products\Enums\AttributeType;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Enums\ProductVisibility;
use AIArmada\Products\Models\Attribute;
use AIArmada\Products\Models\AttributeGroup;
use AIArmada\Products\Models\AttributeSet;
use AIArmada\Products\Models\AttributeValue;
use AIArmada\Products\Models\Category;
use AIArmada\Products\Models\Collection;
use AIArmada\Products\Models\Option;
use AIArmada\Products\Models\OptionValue;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 🎭 SHOWCASE: Complete Products System
 *
 * Demonstrates ALL features of the products package:
 * - Categories (Hierarchical, Featured, with Metadata)
 * - Collections (Manual & Automatic/Rule-based)
 * - Products (Simple, Configurable, Digital, Subscription)
 * - Variants (with Options like Size/Color)
 * - Attributes (Text, Number, Select, Boolean, Color, Date)
 * - Attribute Groups & Sets (Organized attribute management)
 * - Tags (using Spatie Tags)
 */
final class ProductShowcaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🎭 Creating Products Showcase Data...');

        // =====================================================================
        // ATTRIBUTE GROUPS & SETS
        // =====================================================================
        $this->command->info('  → Creating Attribute Groups...');

        $generalGroup = AttributeGroup::create([
            'name' => 'General',
            'code' => 'general',
            'description' => 'General product attributes',
            'position' => 1,
            'is_visible' => true,
        ]);

        $technicalGroup = AttributeGroup::create([
            'name' => 'Technical Specifications',
            'code' => 'technical',
            'description' => 'Technical specifications and features',
            'position' => 2,
            'is_visible' => true,
        ]);

        $dimensionsGroup = AttributeGroup::create([
            'name' => 'Dimensions & Weight',
            'code' => 'dimensions',
            'description' => 'Physical dimensions and weight',
            'position' => 3,
            'is_visible' => true,
        ]);

        $fashionGroup = AttributeGroup::create([
            'name' => 'Fashion Details',
            'code' => 'fashion',
            'description' => 'Fashion-specific attributes',
            'position' => 4,
            'is_visible' => true,
        ]);

        $marketingGroup = AttributeGroup::create([
            'name' => 'Marketing',
            'code' => 'marketing',
            'description' => 'Marketing and promotional attributes',
            'position' => 5,
            'is_visible' => true,
        ]);

        // =====================================================================
        // ATTRIBUTES
        // =====================================================================
        $this->command->info('  → Creating Attributes...');

        // General Attributes
        $brandAttr = Attribute::create([
            'code' => 'brand',
            'name' => 'Brand',
            'description' => 'Product brand or manufacturer',
            'type' => AttributeType::Select,
            'options' => [
                ['value' => 'apple', 'label' => 'Apple'],
                ['value' => 'samsung', 'label' => 'Samsung'],
                ['value' => 'sony', 'label' => 'Sony'],
                ['value' => 'nike', 'label' => 'Nike'],
                ['value' => 'adidas', 'label' => 'Adidas'],
                ['value' => 'ikea', 'label' => 'IKEA'],
                ['value' => 'philips', 'label' => 'Philips'],
                ['value' => 'generic', 'label' => 'Generic'],
            ],
            'is_required' => false,
            'is_filterable' => true,
            'is_searchable' => true,
            'is_comparable' => true,
            'is_visible_on_front' => true,
            'position' => 1,
        ]);

        $countryAttr = Attribute::create([
            'code' => 'country_of_origin',
            'name' => 'Country of Origin',
            'description' => 'Where the product is manufactured',
            'type' => AttributeType::Select,
            'options' => [
                ['value' => 'MY', 'label' => 'Malaysia'],
                ['value' => 'CN', 'label' => 'China'],
                ['value' => 'US', 'label' => 'United States'],
                ['value' => 'JP', 'label' => 'Japan'],
                ['value' => 'KR', 'label' => 'South Korea'],
                ['value' => 'DE', 'label' => 'Germany'],
                ['value' => 'IT', 'label' => 'Italy'],
            ],
            'is_required' => false,
            'is_filterable' => true,
            'is_searchable' => false,
            'is_comparable' => true,
            'is_visible_on_front' => true,
            'position' => 2,
        ]);

        $warrantyAttr = Attribute::create([
            'code' => 'warranty_months',
            'name' => 'Warranty (Months)',
            'description' => 'Warranty period in months',
            'type' => AttributeType::Number,
            'validation' => ['integer', 'min:0', 'max:120'],
            'is_required' => false,
            'is_filterable' => true,
            'is_searchable' => false,
            'is_comparable' => true,
            'is_visible_on_front' => true,
            'position' => 3,
            'suffix' => 'months',
        ]);

        // Technical Attributes
        $screenSizeAttr = Attribute::create([
            'code' => 'screen_size',
            'name' => 'Screen Size',
            'description' => 'Display size in inches',
            'type' => AttributeType::Number,
            'validation' => ['numeric', 'min:0', 'max:100'],
            'is_required' => false,
            'is_filterable' => true,
            'is_searchable' => false,
            'is_comparable' => true,
            'is_visible_on_front' => true,
            'position' => 1,
            'suffix' => 'inches',
        ]);

        $storageAttr = Attribute::create([
            'code' => 'storage_capacity',
            'name' => 'Storage Capacity',
            'description' => 'Storage capacity',
            'type' => AttributeType::Select,
            'options' => [
                ['value' => '64gb', 'label' => '64 GB'],
                ['value' => '128gb', 'label' => '128 GB'],
                ['value' => '256gb', 'label' => '256 GB'],
                ['value' => '512gb', 'label' => '512 GB'],
                ['value' => '1tb', 'label' => '1 TB'],
                ['value' => '2tb', 'label' => '2 TB'],
            ],
            'is_required' => false,
            'is_filterable' => true,
            'is_searchable' => true,
            'is_comparable' => true,
            'is_visible_on_front' => true,
            'position' => 2,
        ]);

        $batteryAttr = Attribute::create([
            'code' => 'battery_capacity',
            'name' => 'Battery Capacity',
            'description' => 'Battery capacity in mAh',
            'type' => AttributeType::Number,
            'validation' => ['integer', 'min:0'],
            'is_required' => false,
            'is_filterable' => false,
            'is_searchable' => false,
            'is_comparable' => true,
            'is_visible_on_front' => true,
            'position' => 3,
            'suffix' => 'mAh',
        ]);

        $wirelessAttr = Attribute::create([
            'code' => 'wireless',
            'name' => 'Wireless',
            'description' => 'Is the product wireless?',
            'type' => AttributeType::Boolean,
            'is_required' => false,
            'is_filterable' => true,
            'is_searchable' => false,
            'is_comparable' => true,
            'is_visible_on_front' => true,
            'position' => 4,
        ]);

        $featuresAttr = Attribute::create([
            'code' => 'features',
            'name' => 'Features',
            'description' => 'Product features',
            'type' => AttributeType::Multiselect,
            'options' => [
                ['value' => 'waterproof', 'label' => 'Waterproof'],
                ['value' => 'dustproof', 'label' => 'Dustproof'],
                ['value' => 'wireless_charging', 'label' => 'Wireless Charging'],
                ['value' => 'fast_charging', 'label' => 'Fast Charging'],
                ['value' => 'nfc', 'label' => 'NFC'],
                ['value' => 'bluetooth', 'label' => 'Bluetooth'],
                ['value' => '5g', 'label' => '5G'],
                ['value' => 'face_id', 'label' => 'Face ID'],
            ],
            'is_required' => false,
            'is_filterable' => true,
            'is_searchable' => true,
            'is_comparable' => true,
            'is_visible_on_front' => true,
            'position' => 5,
        ]);

        // Dimension Attributes
        $productWeightAttr = Attribute::create([
            'code' => 'product_weight',
            'name' => 'Product Weight',
            'description' => 'Weight of the product',
            'type' => AttributeType::Number,
            'validation' => ['numeric', 'min:0'],
            'is_required' => false,
            'is_filterable' => false,
            'is_searchable' => false,
            'is_comparable' => true,
            'is_visible_on_front' => true,
            'position' => 1,
            'suffix' => 'kg',
        ]);

        // Fashion Attributes
        $materialAttr = Attribute::create([
            'code' => 'material',
            'name' => 'Material',
            'description' => 'Primary material',
            'type' => AttributeType::Select,
            'options' => [
                ['value' => 'cotton', 'label' => 'Cotton'],
                ['value' => 'polyester', 'label' => 'Polyester'],
                ['value' => 'leather', 'label' => 'Leather'],
                ['value' => 'wool', 'label' => 'Wool'],
                ['value' => 'silk', 'label' => 'Silk'],
                ['value' => 'denim', 'label' => 'Denim'],
                ['value' => 'nylon', 'label' => 'Nylon'],
            ],
            'is_required' => false,
            'is_filterable' => true,
            'is_searchable' => true,
            'is_comparable' => true,
            'is_visible_on_front' => true,
            'position' => 1,
        ]);

        $primaryColorAttr = Attribute::create([
            'code' => 'primary_color',
            'name' => 'Primary Color',
            'description' => 'Main color of the product',
            'type' => AttributeType::Color,
            'is_required' => false,
            'is_filterable' => true,
            'is_searchable' => false,
            'is_comparable' => false,
            'is_visible_on_front' => true,
            'position' => 2,
        ]);

        $genderAttr = Attribute::create([
            'code' => 'gender',
            'name' => 'Gender',
            'description' => 'Target gender',
            'type' => AttributeType::Select,
            'options' => [
                ['value' => 'men', 'label' => 'Men'],
                ['value' => 'women', 'label' => 'Women'],
                ['value' => 'unisex', 'label' => 'Unisex'],
                ['value' => 'kids', 'label' => 'Kids'],
            ],
            'is_required' => false,
            'is_filterable' => true,
            'is_searchable' => false,
            'is_comparable' => false,
            'is_visible_on_front' => true,
            'position' => 3,
        ]);

        // Marketing Attributes
        $launchDateAttr = Attribute::create([
            'code' => 'launch_date',
            'name' => 'Launch Date',
            'description' => 'Product launch date',
            'type' => AttributeType::Date,
            'is_required' => false,
            'is_filterable' => false,
            'is_searchable' => false,
            'is_comparable' => false,
            'is_visible_on_front' => true,
            'position' => 1,
        ]);

        $highlightsAttr = Attribute::create([
            'code' => 'highlights',
            'name' => 'Product Highlights',
            'description' => 'Key product highlights',
            'type' => AttributeType::Textarea,
            'is_required' => false,
            'is_filterable' => false,
            'is_searchable' => true,
            'is_comparable' => false,
            'is_visible_on_front' => true,
            'position' => 2,
        ]);

        // Attach attributes to groups
        $this->attachAttributesToGroup($generalGroup, [
            $brandAttr->id => ['position' => 1],
            $countryAttr->id => ['position' => 2],
            $warrantyAttr->id => ['position' => 3],
        ]);

        $this->attachAttributesToGroup($technicalGroup, [
            $screenSizeAttr->id => ['position' => 1],
            $storageAttr->id => ['position' => 2],
            $batteryAttr->id => ['position' => 3],
            $wirelessAttr->id => ['position' => 4],
            $featuresAttr->id => ['position' => 5],
        ]);

        $this->attachAttributesToGroup($dimensionsGroup, [
            $productWeightAttr->id => ['position' => 1],
        ]);

        $this->attachAttributesToGroup($fashionGroup, [
            $materialAttr->id => ['position' => 1],
            $primaryColorAttr->id => ['position' => 2],
            $genderAttr->id => ['position' => 3],
        ]);

        $this->attachAttributesToGroup($marketingGroup, [
            $launchDateAttr->id => ['position' => 1],
            $highlightsAttr->id => ['position' => 2],
        ]);

        // =====================================================================
        // ATTRIBUTE SETS
        // =====================================================================
        $this->command->info('  → Creating Attribute Sets...');

        $electronicsSet = AttributeSet::create([
            'name' => 'Electronics',
            'code' => 'electronics',
            'description' => 'Attribute set for electronic products',
            'is_default' => true,
            'position' => 1,
        ]);

        $fashionSet = AttributeSet::create([
            'name' => 'Fashion',
            'code' => 'fashion',
            'description' => 'Attribute set for clothing and accessories',
            'is_default' => false,
            'position' => 2,
        ]);

        $homeSet = AttributeSet::create([
            'name' => 'Home & Living',
            'code' => 'home',
            'description' => 'Attribute set for home products',
            'is_default' => false,
            'position' => 3,
        ]);

        // Attach groups to sets
        $this->attachGroupsToSet($electronicsSet, [
            $generalGroup->id => ['position' => 1],
            $technicalGroup->id => ['position' => 2],
            $dimensionsGroup->id => ['position' => 3],
            $marketingGroup->id => ['position' => 4],
        ]);

        $this->attachGroupsToSet($fashionSet, [
            $generalGroup->id => ['position' => 1],
            $fashionGroup->id => ['position' => 2],
            $dimensionsGroup->id => ['position' => 3],
            $marketingGroup->id => ['position' => 4],
        ]);

        $this->attachGroupsToSet($homeSet, [
            $generalGroup->id => ['position' => 1],
            $dimensionsGroup->id => ['position' => 2],
            $marketingGroup->id => ['position' => 3],
        ]);

        // Attach attributes to sets
        $this->attachAttributesToSet($electronicsSet, [
            $brandAttr->id => ['position' => 1],
            $countryAttr->id => ['position' => 2],
            $warrantyAttr->id => ['position' => 3],
            $screenSizeAttr->id => ['position' => 4],
            $storageAttr->id => ['position' => 5],
            $batteryAttr->id => ['position' => 6],
            $wirelessAttr->id => ['position' => 7],
            $featuresAttr->id => ['position' => 8],
            $productWeightAttr->id => ['position' => 9],
            $launchDateAttr->id => ['position' => 10],
            $highlightsAttr->id => ['position' => 11],
        ]);

        $this->attachAttributesToSet($fashionSet, [
            $brandAttr->id => ['position' => 1],
            $countryAttr->id => ['position' => 2],
            $materialAttr->id => ['position' => 3],
            $primaryColorAttr->id => ['position' => 4],
            $genderAttr->id => ['position' => 5],
            $productWeightAttr->id => ['position' => 6],
            $launchDateAttr->id => ['position' => 7],
            $highlightsAttr->id => ['position' => 8],
        ]);

        $this->attachAttributesToSet($homeSet, [
            $brandAttr->id => ['position' => 1],
            $countryAttr->id => ['position' => 2],
            $warrantyAttr->id => ['position' => 3],
            $productWeightAttr->id => ['position' => 4],
            $launchDateAttr->id => ['position' => 5],
            $highlightsAttr->id => ['position' => 6],
        ]);

        // =====================================================================
        // ADDITIONAL CATEGORIES (Add to existing)
        // =====================================================================
        $this->command->info('  → Adding more Categories...');

        // Find or create parent categories
        $electronicsCategory = Category::where('slug', 'electronics')->first();
        $fashionCategory = Category::where('slug', 'fashion')->first();
        $homeCategory = Category::where('slug', 'home-living')->first();

        // Add more child categories
        if ($electronicsCategory) {
            Category::firstOrCreate(
                ['slug' => 'tablets'],
                [
                    'name' => 'Tablets',
                    'description' => 'Tablet devices and accessories',
                    'parent_id' => $electronicsCategory->id,
                    'is_visible' => true,
                    'is_featured' => true,
                    'position' => 10,
                    'meta_title' => 'Tablets - Best Tablet Deals',
                    'meta_description' => 'Shop the latest tablets from top brands',
                ]
            );

            Category::firstOrCreate(
                ['slug' => 'wearables'],
                [
                    'name' => 'Wearables',
                    'description' => 'Smartwatches, fitness trackers, and wearable tech',
                    'parent_id' => $electronicsCategory->id,
                    'is_visible' => true,
                    'is_featured' => false,
                    'position' => 11,
                ]
            );

            Category::firstOrCreate(
                ['slug' => 'gaming'],
                [
                    'name' => 'Gaming',
                    'description' => 'Gaming consoles, accessories, and games',
                    'parent_id' => $electronicsCategory->id,
                    'is_visible' => true,
                    'is_featured' => true,
                    'position' => 12,
                ]
            );
        }

        if ($fashionCategory) {
            Category::firstOrCreate(
                ['slug' => 'accessories'],
                [
                    'name' => 'Accessories',
                    'description' => 'Bags, belts, watches, and more',
                    'parent_id' => $fashionCategory->id,
                    'is_visible' => true,
                    'is_featured' => false,
                    'position' => 10,
                ]
            );

            Category::firstOrCreate(
                ['slug' => 'sportswear'],
                [
                    'name' => 'Sportswear',
                    'description' => 'Athletic and sports clothing',
                    'parent_id' => $fashionCategory->id,
                    'is_visible' => true,
                    'is_featured' => true,
                    'position' => 11,
                ]
            );
        }

        // =====================================================================
        // COLLECTIONS
        // =====================================================================
        $this->command->info('  → Creating Collections...');

        // Manual Collection - Best Sellers
        $bestSellers = Collection::create([
            'name' => 'Best Sellers',
            'slug' => 'best-sellers',
            'description' => 'Our top-selling products across all categories',
            'type' => 'manual',
            'is_visible' => true,
            'is_featured' => true,
            'position' => 1,
            'published_at' => now(),
            'meta_title' => 'Best Sellers - Top Products',
            'meta_description' => 'Shop our most popular products',
        ]);

        // Manual Collection - New Arrivals
        $newArrivals = Collection::create([
            'name' => 'New Arrivals',
            'slug' => 'new-arrivals',
            'description' => 'Fresh products just added to our store',
            'type' => 'manual',
            'is_visible' => true,
            'is_featured' => true,
            'position' => 2,
            'published_at' => now(),
        ]);

        // Automatic Collection - On Sale
        $onSale = Collection::create([
            'name' => 'On Sale',
            'slug' => 'on-sale',
            'description' => 'Products currently on discount',
            'type' => 'automatic',
            'conditions' => [
                'rules' => [
                    ['field' => 'compare_price', 'operator' => 'is_set'],
                ],
                'match' => 'all',
            ],
            'is_visible' => true,
            'is_featured' => true,
            'position' => 3,
            'published_at' => now(),
        ]);

        // Automatic Collection - Featured Products
        $featured = Collection::create([
            'name' => 'Featured Products',
            'slug' => 'featured',
            'description' => 'Hand-picked featured products',
            'type' => 'automatic',
            'conditions' => [
                'rules' => [
                    ['field' => 'is_featured', 'operator' => 'equals', 'value' => true],
                ],
                'match' => 'all',
            ],
            'is_visible' => true,
            'is_featured' => false,
            'position' => 4,
            'published_at' => now(),
        ]);

        // Seasonal Collection - Summer Sale (with scheduling)
        $summerSale = Collection::create([
            'name' => 'Summer Sale 2026',
            'slug' => 'summer-sale-2026',
            'description' => 'Hot deals for the summer season',
            'type' => 'manual',
            'is_visible' => true,
            'is_featured' => true,
            'position' => 5,
            'published_at' => now()->addDays(30), // Future publish
            'unpublished_at' => now()->addDays(90),
            'metadata' => [
                'campaign_id' => 'SUMMER26',
                'discount_code' => 'SUN10',
            ],
        ]);

        // Premium Collection
        $premium = Collection::create([
            'name' => 'Premium Selection',
            'slug' => 'premium',
            'description' => 'Luxury and premium products',
            'type' => 'automatic',
            'conditions' => [
                'rules' => [
                    ['field' => 'price', 'operator' => 'greater_than', 'value' => 100000], // > RM1000
                ],
                'match' => 'all',
            ],
            'is_visible' => true,
            'is_featured' => false,
            'position' => 6,
            'published_at' => now(),
        ]);

        // =====================================================================
        // SHOWCASE PRODUCTS
        // =====================================================================
        $this->command->info('  → Creating Showcase Products...');

        $smartphoneCategory = Category::where('slug', 'smartphones')->first();
        $laptopCategory = Category::where('slug', 'laptops')->first();
        $audioCategory = Category::where('slug', 'audio')->first();
        $mensCategory = Category::where('slug', 'mens-clothing')->first();
        $shoesCategory = Category::where('slug', 'shoes')->first();

        // Product 1: iPhone 16 Pro Max - Configurable with full attributes
        $iphone = Product::create([
            'name' => 'iPhone 16 Pro Max',
            'slug' => 'iphone-16-pro-max',
            'sku' => 'IP16-PROMAX',
            'description' => 'The most advanced iPhone ever with A18 Pro chip, breakthrough camera system, and titanium design.',
            'short_description' => 'A18 Pro chip | 48MP Camera | Titanium Design',
            'type' => ProductType::Configurable,
            'status' => ProductStatus::Active,
            'visibility' => ProductVisibility::CatalogSearch,
            'price' => 629900, // RM6,299
            'compare_price' => 669900,
            'cost' => 450000,
            'currency' => 'MYR',
            'weight' => 0.221,
            'is_featured' => true,
            'is_taxable' => true,
            'requires_shipping' => true,
            'tax_class' => 'standard',
            'meta_title' => 'iPhone 16 Pro Max - Buy Now',
            'meta_description' => 'Get the new iPhone 16 Pro Max with A18 Pro chip.',
            'published_at' => now(),
            'metadata' => [
                'release_year' => 2025,
                'series' => 'iPhone 16',
            ],
        ]);

        // Add attribute values to iPhone
        AttributeValue::create([
            'attribute_id' => $brandAttr->id,
            'attributable_type' => Product::class,
            'attributable_id' => $iphone->id,
            'value' => 'apple',
        ]);

        AttributeValue::create([
            'attribute_id' => $countryAttr->id,
            'attributable_type' => Product::class,
            'attributable_id' => $iphone->id,
            'value' => 'CN',
        ]);

        AttributeValue::create([
            'attribute_id' => $warrantyAttr->id,
            'attributable_type' => Product::class,
            'attributable_id' => $iphone->id,
            'value' => '24',
        ]);

        AttributeValue::create([
            'attribute_id' => $screenSizeAttr->id,
            'attributable_type' => Product::class,
            'attributable_id' => $iphone->id,
            'value' => '6.9',
        ]);

        AttributeValue::create([
            'attribute_id' => $batteryAttr->id,
            'attributable_type' => Product::class,
            'attributable_id' => $iphone->id,
            'value' => '4685',
        ]);

        AttributeValue::create([
            'attribute_id' => $wirelessAttr->id,
            'attributable_type' => Product::class,
            'attributable_id' => $iphone->id,
            'value' => '1',
        ]);

        AttributeValue::create([
            'attribute_id' => $featuresAttr->id,
            'attributable_type' => Product::class,
            'attributable_id' => $iphone->id,
            'value' => json_encode(['waterproof', 'dustproof', 'wireless_charging', 'fast_charging', 'nfc', '5g', 'face_id']),
        ]);

        AttributeValue::create([
            'attribute_id' => $launchDateAttr->id,
            'attributable_type' => Product::class,
            'attributable_id' => $iphone->id,
            'value' => '2025-09-15',
        ]);

        AttributeValue::create([
            'attribute_id' => $highlightsAttr->id,
            'attributable_type' => Product::class,
            'attributable_id' => $iphone->id,
            'value' => "• A18 Pro chip for unprecedented performance\n• 48MP Fusion camera with 5x optical zoom\n• Titanium design - lightest Pro Max ever\n• All-day battery life\n• USB-C with USB 3 speeds",
        ]);

        // Add options and variants for iPhone
        $storageOption = Option::create([
            'product_id' => $iphone->id,
            'name' => 'storage',
            'display_name' => 'Storage',
            'position' => 1,
            'is_visible' => true,
        ]);

        $colorOption = Option::create([
            'product_id' => $iphone->id,
            'name' => 'color',
            'display_name' => 'Color',
            'position' => 2,
            'is_visible' => true,
        ]);

        $storage256 = OptionValue::create(['option_id' => $storageOption->id, 'name' => '256GB', 'position' => 1]);
        $storage512 = OptionValue::create(['option_id' => $storageOption->id, 'name' => '512GB', 'position' => 2]);
        $storage1tb = OptionValue::create(['option_id' => $storageOption->id, 'name' => '1TB', 'position' => 3]);

        $colorTitanium = OptionValue::create(['option_id' => $colorOption->id, 'name' => 'Natural Titanium', 'position' => 1]);
        $colorBlue = OptionValue::create(['option_id' => $colorOption->id, 'name' => 'Blue Titanium', 'position' => 2]);
        $colorWhite = OptionValue::create(['option_id' => $colorOption->id, 'name' => 'White Titanium', 'position' => 3]);
        $colorBlack = OptionValue::create(['option_id' => $colorOption->id, 'name' => 'Black Titanium', 'position' => 4]);

        // Create variants
        $variants = [
            ['storage' => $storage256, 'color' => $colorTitanium, 'price' => 629900, 'sku' => 'IP16PM-256-NT'],
            ['storage' => $storage256, 'color' => $colorBlue, 'price' => 629900, 'sku' => 'IP16PM-256-BT'],
            ['storage' => $storage256, 'color' => $colorWhite, 'price' => 629900, 'sku' => 'IP16PM-256-WT'],
            ['storage' => $storage256, 'color' => $colorBlack, 'price' => 629900, 'sku' => 'IP16PM-256-BK'],
            ['storage' => $storage512, 'color' => $colorTitanium, 'price' => 729900, 'sku' => 'IP16PM-512-NT'],
            ['storage' => $storage512, 'color' => $colorBlue, 'price' => 729900, 'sku' => 'IP16PM-512-BT'],
            ['storage' => $storage1tb, 'color' => $colorTitanium, 'price' => 829900, 'sku' => 'IP16PM-1TB-NT'],
            ['storage' => $storage1tb, 'color' => $colorBlack, 'price' => 829900, 'sku' => 'IP16PM-1TB-BK'],
        ];

        $isFirst = true;
        foreach ($variants as $v) {
            $variant = Variant::create([
                'product_id' => $iphone->id,
                'name' => $v['storage']->name . ' / ' . $v['color']->name,
                'sku' => $v['sku'],
                'price' => $v['price'],
                'is_default' => $isFirst,
                'is_enabled' => true,
            ]);
            $variant->optionValues()->attach([$v['storage']->id, $v['color']->id]);
            $isFirst = false;
        }

        // Attach iPhone to categories and collections
        if ($smartphoneCategory) {
            $iphone->categories()->attach($smartphoneCategory->id);
        }
        $bestSellers->products()->attach($iphone->id, ['position' => 1]);
        $newArrivals->products()->attach($iphone->id, ['position' => 1]);

        // Add tags
        $iphone->attachTags(['smartphone', 'apple', 'flagship', '5g', 'pro']);

        // Product 2: Digital Product - Software License
        $software = Product::create([
            'name' => 'Microsoft 365 Family',
            'slug' => 'microsoft-365-family',
            'sku' => 'MS365-FAM',
            'description' => '1-year subscription for up to 6 users. Includes Word, Excel, PowerPoint, Outlook, and 1TB OneDrive storage per user.',
            'short_description' => 'Office apps + 1TB cloud storage for 6 users',
            'type' => ProductType::Digital,
            'status' => ProductStatus::Active,
            'visibility' => ProductVisibility::CatalogSearch,
            'price' => 43900, // RM439
            'compare_price' => 49900,
            'currency' => 'MYR',
            'is_featured' => false,
            'is_taxable' => true,
            'requires_shipping' => false, // Digital product
            'tax_class' => 'digital',
            'published_at' => now(),
            'metadata' => [
                'license_type' => 'subscription',
                'duration_months' => 12,
                'max_users' => 6,
                'delivery' => 'email',
            ],
        ]);

        AttributeValue::create([
            'attribute_id' => $brandAttr->id,
            'attributable_type' => Product::class,
            'attributable_id' => $software->id,
            'value' => 'generic', // Microsoft not in our list, using generic
        ]);

        $bestSellers->products()->attach($software->id, ['position' => 2]);
        $software->attachTags(['software', 'subscription', 'office', 'productivity', 'digital']);

        // Product 3: Subscription Product
        $subscription = Product::create([
            'name' => 'Premium Membership',
            'slug' => 'premium-membership',
            'sku' => 'MEMBER-PREMIUM',
            'description' => 'Get exclusive access to premium content, early access to sales, free shipping, and special member-only discounts.',
            'short_description' => 'Exclusive perks & free shipping',
            'type' => ProductType::Subscription,
            'status' => ProductStatus::Active,
            'visibility' => ProductVisibility::CatalogSearch,
            'price' => 4900, // RM49/month
            'currency' => 'MYR',
            'is_featured' => true,
            'is_taxable' => true,
            'requires_shipping' => false,
            'tax_class' => 'digital',
            'published_at' => now(),
            'metadata' => [
                'billing_period' => 'monthly',
                'benefits' => ['free_shipping', 'early_access', 'exclusive_discounts', 'premium_support'],
            ],
        ]);

        $subscription->attachTags(['subscription', 'membership', 'premium']);

        // Product 4: Simple Fashion Product with Color attribute
        $tshirt = Product::create([
            'name' => 'Premium Cotton T-Shirt',
            'slug' => 'premium-cotton-tshirt',
            'sku' => 'TSHIRT-PREM',
            'description' => 'Ultra-soft 100% organic cotton t-shirt with a modern fit.',
            'short_description' => '100% Organic Cotton | Modern Fit',
            'type' => ProductType::Simple,
            'status' => ProductStatus::Active,
            'visibility' => ProductVisibility::CatalogSearch,
            'price' => 8900, // RM89
            'compare_price' => 12900,
            'currency' => 'MYR',
            'weight' => 0.2,
            'is_featured' => false,
            'is_taxable' => true,
            'requires_shipping' => true,
            'tax_class' => 'standard',
            'published_at' => now(),
        ]);

        AttributeValue::create([
            'attribute_id' => $materialAttr->id,
            'attributable_type' => Product::class,
            'attributable_id' => $tshirt->id,
            'value' => 'cotton',
        ]);

        AttributeValue::create([
            'attribute_id' => $primaryColorAttr->id,
            'attributable_type' => Product::class,
            'attributable_id' => $tshirt->id,
            'value' => '#1a1a1a', // Black
        ]);

        AttributeValue::create([
            'attribute_id' => $genderAttr->id,
            'attributable_type' => Product::class,
            'attributable_id' => $tshirt->id,
            'value' => 'unisex',
        ]);

        if ($mensCategory) {
            $tshirt->categories()->attach($mensCategory->id);
        }
        $tshirt->attachTags(['clothing', 'cotton', 't-shirt', 'casual', 'unisex']);

        // Product 5: Luxury Product for Premium Collection
        $luxuryWatch = Product::create([
            'name' => 'Luxury Chronograph Watch',
            'slug' => 'luxury-chronograph-watch',
            'sku' => 'WATCH-LUX-001',
            'description' => 'Swiss-made automatic chronograph with sapphire crystal, 100m water resistance, and genuine leather strap.',
            'short_description' => 'Swiss Automatic | Sapphire Crystal',
            'type' => ProductType::Simple,
            'status' => ProductStatus::Active,
            'visibility' => ProductVisibility::CatalogSearch,
            'price' => 899900, // RM8,999
            'compare_price' => 999900,
            'cost' => 500000,
            'currency' => 'MYR',
            'weight' => 0.15,
            'is_featured' => true,
            'is_taxable' => true,
            'requires_shipping' => true,
            'tax_class' => 'luxury',
            'published_at' => now(),
            'metadata' => [
                'movement' => 'automatic',
                'water_resistance' => '100m',
                'case_material' => 'stainless_steel',
            ],
        ]);

        AttributeValue::create([
            'attribute_id' => $countryAttr->id,
            'attributable_type' => Product::class,
            'attributable_id' => $luxuryWatch->id,
            'value' => 'CH', // Switzerland (not in our list, will show as-is)
        ]);

        AttributeValue::create([
            'attribute_id' => $warrantyAttr->id,
            'attributable_type' => Product::class,
            'attributable_id' => $luxuryWatch->id,
            'value' => '60', // 5 years
        ]);

        $premium->products()->attach($luxuryWatch->id, ['position' => 1]);
        $luxuryWatch->attachTags(['luxury', 'watch', 'swiss', 'automatic', 'premium']);

        // Product 6: Bundle Product (represented as Simple with metadata)
        $gamingBundle = Product::create([
            'name' => 'Ultimate Gaming Bundle',
            'slug' => 'ultimate-gaming-bundle',
            'sku' => 'BUNDLE-GAMING',
            'description' => 'Complete gaming setup including gaming headset, mechanical keyboard, and precision mouse.',
            'short_description' => 'Headset + Keyboard + Mouse',
            'type' => ProductType::Bundle,
            'status' => ProductStatus::Active,
            'visibility' => ProductVisibility::CatalogSearch,
            'price' => 59900, // RM599
            'compare_price' => 79900, // Save RM200
            'currency' => 'MYR',
            'weight' => 2.5,
            'is_featured' => true,
            'is_taxable' => true,
            'requires_shipping' => true,
            'tax_class' => 'standard',
            'published_at' => now(),
            'metadata' => [
                'bundle_items' => [
                    ['name' => 'Gaming Headset', 'sku' => 'HEADSET-G1', 'value' => 24900],
                    ['name' => 'Mechanical Keyboard', 'sku' => 'KB-MECH-1', 'value' => 34900],
                    ['name' => 'Gaming Mouse', 'sku' => 'MOUSE-G1', 'value' => 19900],
                ],
                'savings' => 19800,
            ],
        ]);

        $gamingCategory = Category::where('slug', 'gaming')->first();
        if ($gamingCategory) {
            $gamingBundle->categories()->attach($gamingCategory->id);
        }
        $bestSellers->products()->attach($gamingBundle->id, ['position' => 3]);
        $gamingBundle->attachTags(['gaming', 'bundle', 'accessories', 'value-pack']);

        // Product 7: Draft Product (not published)
        $draftProduct = Product::create([
            'name' => 'Upcoming Product (Draft)',
            'slug' => 'upcoming-product-draft',
            'sku' => 'DRAFT-001',
            'description' => 'This product is still being prepared and not yet visible to customers.',
            'type' => ProductType::Simple,
            'status' => ProductStatus::Draft,
            'visibility' => ProductVisibility::Hidden,
            'price' => 19900,
            'currency' => 'MYR',
            'is_featured' => false,
            'is_taxable' => true,
            'requires_shipping' => true,
            'published_at' => null,
        ]);

        // Product 8: Archived Product
        $archivedProduct = Product::create([
            'name' => 'Discontinued Model',
            'slug' => 'discontinued-model',
            'sku' => 'DISC-001',
            'description' => 'This product has been discontinued and is no longer available.',
            'type' => ProductType::Simple,
            'status' => ProductStatus::Archived,
            'visibility' => ProductVisibility::Hidden,
            'price' => 9900,
            'currency' => 'MYR',
            'is_featured' => false,
            'is_taxable' => true,
            'requires_shipping' => true,
            'published_at' => now()->subYear(),
        ]);

        $this->command->info('✅ Products Showcase Complete!');
        $this->command->info('   - ' . Category::count() . ' Categories');
        $this->command->info('   - ' . Collection::count() . ' Collections');
        $this->command->info('   - ' . Product::count() . ' Products');
        $this->command->info('   - ' . Variant::count() . ' Variants');
        $this->command->info('   - ' . Attribute::count() . ' Attributes');
        $this->command->info('   - ' . AttributeGroup::count() . ' Attribute Groups');
        $this->command->info('   - ' . AttributeSet::count() . ' Attribute Sets');
        $this->command->info('   - ' . AttributeValue::count() . ' Attribute Values');
    }

    /**
     * Attach attributes to a group with UUID pivot IDs.
     *
     * @param  array<string, array{position: int}>  $attributeData
     */
    private function attachAttributesToGroup(AttributeGroup $group, array $attributeData): void
    {
        $table = config('products.database.tables.attribute_attribute_group', 'product_attribute_attribute_group');
        $now = now();

        foreach ($attributeData as $attributeId => $pivotData) {
            DB::table($table)->insert([
                'id' => Str::uuid()->toString(),
                'attribute_id' => $attributeId,
                'attribute_group_id' => $group->id,
                'position' => $pivotData['position'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Attach groups to an attribute set with UUID pivot IDs.
     *
     * @param  array<string, array{position: int}>  $groupData
     */
    private function attachGroupsToSet(AttributeSet $set, array $groupData): void
    {
        $table = config('products.database.tables.attribute_group_attribute_set', 'product_attribute_group_attribute_set');
        $now = now();

        foreach ($groupData as $groupId => $pivotData) {
            DB::table($table)->insert([
                'id' => Str::uuid()->toString(),
                'attribute_group_id' => $groupId,
                'attribute_set_id' => $set->id,
                'position' => $pivotData['position'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Attach attributes to an attribute set with UUID pivot IDs.
     *
     * @param  array<string, array{position: int}>  $attributeData
     */
    private function attachAttributesToSet(AttributeSet $set, array $attributeData): void
    {
        $table = config('products.database.tables.attribute_attribute_set', 'product_attribute_attribute_set');
        $now = now();

        foreach ($attributeData as $attributeId => $pivotData) {
            DB::table($table)->insert([
                'id' => Str::uuid()->toString(),
                'attribute_id' => $attributeId,
                'attribute_set_id' => $set->id,
                'position' => $pivotData['position'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
