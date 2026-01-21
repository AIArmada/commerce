<?php

declare(strict_types=1);

namespace Database\Seeders;

use AIArmada\Pricing\Models\Price;
use AIArmada\Pricing\Models\PriceList;
use AIArmada\Pricing\Models\PriceTier;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 🎭 SHOWCASE: Advanced Pricing Strategy
 *
 * Demonstrates the power of the Pricing package:
 * - Specific Price Lists (VIP, Wholesale)
 * - Quantity-based Price Breaks (Tiered Pricing)
 * - Variant-specific overrides
 */
final class PricingShowcaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🎭 Building Advanced Pricing Strategy...');

        $owner = User::where('email', 'admin@commerce.demo')->first();

        // Get some products for pricing demonstration
        $iphone = Product::where('sku', 'IP16-PROMAX')->first();
        $tshirt = Product::where('sku', 'TSHIRT-PREM')->first();

        if (!$iphone || !$tshirt) {
            $this->command->error('Products not found. Please run ProductShowcaseSeeder first.');
            return;
        }

        // =====================================================================
        // 1. VIP PRICE LIST (Exclusive discounts for top members)
        // =====================================================================
        $this->command->info('  💎 Creating VIP Price List...');

        $vipList = PriceList::updateOrCreate(
            ['slug' => 'vip-gold'],
            [
                'name' => '💎 VIP Gold Member Prices',
                'description' => 'Targeted pricing for our highest spending customers.',
                'currency' => 'MYR',
                'priority' => 10,
                'is_active' => true,
                'owner_type' => $owner?->getMorphClass(),
                'owner_id' => $owner?->id,
            ]
        );

        // Specific price for iPhone 16 Pro Max for VIPs: RM5,999 (instead of RM6,299)
        Price::updateOrCreate(
            [
                'price_list_id' => $vipList->id,
                'priceable_type' => $iphone->getMorphClass(),
                'priceable_id' => $iphone->id,
            ],
            [
                'amount' => 599900,
                'compare_amount' => 629900,
                'currency' => 'MYR',
                'owner_type' => $owner?->getMorphClass(),
                'owner_id' => $owner?->id,
            ]
        );

        // =====================================================================
        // 2. WHOLESALE PRICE LIST (Bulk buy incentives)
        // =====================================================================
        $this->command->info('  📦 Creating Wholesale Tiered Pricing...');

        $wholesaleList = PriceList::updateOrCreate(
            ['slug' => 'wholesale'],
            [
                'name' => '📦 B2B Wholesale Pricing',
                'description' => 'Volume-based pricing for business partners.',
                'currency' => 'MYR',
                'priority' => 5,
                'is_active' => true,
                'owner_type' => $owner?->getMorphClass(),
                'owner_id' => $owner?->id,
            ]
        );

        // Tiered pricing for Premium T-Shirt
        // 1-9 units: RM89 (standard)
        // 10-49 units: RM69
        // 50+ units: RM49
        PriceTier::updateOrCreate(
            [
                'price_list_id' => $wholesaleList->id,
                'tierable_type' => $tshirt->getMorphClass(),
                'tierable_id' => $tshirt->id,
                'min_quantity' => 10,
            ],
            [
                'max_quantity' => 49,
                'amount' => 6900,
                'currency' => 'MYR',
                'owner_type' => $owner?->getMorphClass(),
                'owner_id' => $owner?->id,
            ]
        );

        PriceTier::updateOrCreate(
            [
                'price_list_id' => $wholesaleList->id,
                'tierable_type' => $tshirt->getMorphClass(),
                'tierable_id' => $tshirt->id,
                'min_quantity' => 50,
            ],
            [
                'amount' => 4900,
                'currency' => 'MYR',
                'owner_type' => $owner?->getMorphClass(),
                'owner_id' => $owner?->id,
            ]
        );

        // =====================================================================
        // 3. FLASH SALE OVERRIDE (Temporary price list)
        // =====================================================================
        $this->command->info('  🔥 Creating Limited Time Flash Sale Prices...');

        $flashSaleList = PriceList::updateOrCreate(
            ['slug' => 'midnight-flash'],
            [
                'name' => '🔥 Midnight Flash Sale',
                'description' => 'Price levels active only during scheduled flash sales.',
                'currency' => 'MYR',
                'priority' => 100, // Very high priority overrides everything else
                'is_active' => true,
                'starts_at' => now(),
                'ends_at' => now()->addHours(24),
                'owner_type' => $owner?->getMorphClass(),
                'owner_id' => $owner?->id,
            ]
        );

        // T-Shirt goes to RM39 during Flash Sale
        Price::updateOrCreate(
            [
                'price_list_id' => $flashSaleList->id,
                'priceable_type' => $tshirt->getMorphClass(),
                'priceable_id' => $tshirt->id,
            ],
            [
                'amount' => 3900,
                'compare_amount' => 8900,
                'currency' => 'MYR',
                'owner_type' => $owner?->getMorphClass(),
                'owner_id' => $owner?->id,
            ]
        );

        $this->command->info('✅ Pricing Strategy Showcase Complete!');
    }
}
