<?php

declare(strict_types=1);

namespace Database\Seeders;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Models\Product;
use AIArmada\Promotions\Enums\PromotionType;
use AIArmada\Promotions\Models\Promotion;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 🎭 SHOWCASE: Promotions That Actually Move the Needle
 *
 * Demonstrates the dedicated promotions package with:
 * - Automatic cart-wide discounts
 * - Code-based campaigns
 * - Product-targeted promotions
 */
final class PromotionsShowcaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🎭 Creating Promotions Showcase Data...');

        $owner = OwnerContext::resolve();

        if (! $owner instanceof User) {
            return;
        }

        $confidenceBooster = Promotion::query()->updateOrCreate(
            ['name' => 'Checkout Confidence Booster'],
            [
                'description' => 'Automatic 12% discount for premium carts to encourage checkout completion.',
                'type' => PromotionType::Percentage,
                'discount_value' => 12,
                'priority' => 100,
                'is_stackable' => false,
                'is_active' => true,
                'usage_limit' => 500,
                'per_customer_limit' => 1,
                'min_purchase_amount' => 25000,
                'min_quantity' => 1,
                'conditions' => null,
                'starts_at' => now()->subDays(7),
                'ends_at' => now()->addDays(30),
            ],
        );

        Promotion::query()->updateOrCreate(
            ['code' => 'WELCOME15'],
            [
                'name' => 'Welcome Back Offer',
                'description' => 'Code-based fixed discount campaign for returning customers.',
                'type' => PromotionType::Fixed,
                'discount_value' => 1500,
                'priority' => 80,
                'is_stackable' => true,
                'is_active' => true,
                'usage_limit' => 250,
                'per_customer_limit' => 1,
                'min_purchase_amount' => 12000,
                'min_quantity' => 1,
                'conditions' => null,
                'starts_at' => now()->subDays(3),
                'ends_at' => now()->addDays(45),
            ],
        );

        Promotion::query()->updateOrCreate(
            ['code' => 'BUNDLEBOOST'],
            [
                'name' => 'Bundle Booster',
                'description' => 'Buy-more incentive for bundled accessory purchases.',
                'type' => PromotionType::BuyXGetY,
                'discount_value' => 1,
                'priority' => 60,
                'is_stackable' => false,
                'is_active' => true,
                'usage_limit' => null,
                'per_customer_limit' => 2,
                'min_purchase_amount' => 0,
                'min_quantity' => 3,
                'conditions' => null,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addDays(21),
            ],
        );

        $flagshipProduct = Product::query()->where('sku', 'IP16-PROMAX')->first();

        if ($flagshipProduct instanceof Product) {
            $confidenceBooster->products()->syncWithoutDetaching([$flagshipProduct->getKey()]);
        }

        $this->command->info('✅ Promotions Showcase Complete!');
    }
}
