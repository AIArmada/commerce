<?php

declare(strict_types=1);

use AIArmada\CashierChip\Subscription;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Product;
use App\Models\User;
use Database\Seeders\BillingShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('uses chip-only billing schema for the demo', function (): void {
    expect(Schema::hasColumn('users', 'chip_id'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'default_pm_id'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'pm_type'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'pm_last_four'))->toBeTrue()
        ->and(Schema::hasTable('cashier_chip_subscriptions'))->toBeTrue()
        ->and(Schema::hasTable('cashier_chip_subscription_items'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'stripe_id'))->toBeFalse()
        ->and(Schema::hasTable('subscriptions'))->toBeFalse()
        ->and(Schema::hasTable('subscription_items'))->toBeFalse();
});

it('seeds billing showcase data with chip subscriptions only', function (): void {
    $admin = User::factory()->create([
        'email' => 'admin@commerce.demo',
    ]);

    $this->seed(BillingShowcaseSeeder::class);

    $admin->refresh();

    expect($admin->chip_id)->not->toBeNull()
        ->and($admin->default_pm_id)->not->toBeNull()
        ->and(Subscription::query()->count())->toBe(2)
        ->and(Subscription::query()->pluck('chip_status')->all())
        ->toContain(Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIALING);
});

it('does not expose stripe subscription routes in the chip-only demo', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/subscribe/stripe/pro')
        ->assertNotFound();

    $this->actingAs($user)
        ->post('/subscribe/stripe', [])
        ->assertNotFound();
});

it('returns 404 when viewing a single-product checkout for another owner', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    $productA = OwnerContext::withOwner($ownerA, function (): Product {
        return Product::create([
            'name' => 'iPhone 15 Pro',
            'sku' => 'IP15-PRO-001',
            'price' => 539900,
            'currency' => 'MYR',
            'status' => ProductStatus::Active,
        ]);
    });

    $this->withSession(['demo_owner_id' => $ownerB->id])
        ->get('/checkout/single/'.$productA->slug)
        ->assertNotFound();
});

it('returns 404 when posting checkout for a product_id belonging to another owner', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    $productA = OwnerContext::withOwner($ownerA, function (): Product {
        return Product::create([
            'name' => 'Nike Air Jordan 1',
            'sku' => 'AJ1-001',
            'price' => 45900,
            'currency' => 'MYR',
            'status' => ProductStatus::Active,
        ]);
    });

    $this->withSession(['demo_owner_id' => $ownerB->id])
        ->post('/checkout/single', [
            'chip_token' => 'demo-token',
            'product_id' => (string) $productA->id,
            'email' => 'guest@example.com',
        ])
        ->assertNotFound();
});
