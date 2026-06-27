<?php

declare(strict_types=1);

use AIArmada\CashierChip\Enums\SubscriptionStatus;
use AIArmada\CashierChip\Subscription;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Growth\Models\Assignment;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Product;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\TrackedProperty;
use App\Models\User;
use Database\Seeders\AnalyticsShowcaseSeeder;
use Database\Seeders\BillingShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('loads checkout, signals, growth, and chip-backed billing schema for the demo', function (): void {
    expect(Schema::hasColumn('users', 'chip_id'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'default_pm_id'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'pm_type'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'pm_last_four'))->toBeTrue()
        ->and(Schema::hasTable('checkout_sessions'))->toBeTrue()
        ->and(Schema::hasTable('signal_tracked_properties'))->toBeTrue()
        ->and(Schema::hasTable('signal_identities'))->toBeTrue()
        ->and(Schema::hasTable('signal_sessions'))->toBeTrue()
        ->and(Schema::hasTable('signal_events'))->toBeTrue()
        ->and(Schema::hasTable('growth_experiments'))->toBeTrue()
        ->and(Schema::hasTable('growth_variants'))->toBeTrue()
        ->and(Schema::hasTable('growth_assignments'))->toBeTrue()
        ->and(Schema::hasTable('cashier_chip_subscriptions'))->toBeTrue()
        ->and(Schema::hasTable('cashier_chip_subscription_items'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'stripe_id'))->toBeFalse()
        ->and(Schema::hasTable('subscriptions'))->toBeFalse()
        ->and(Schema::hasTable('subscription_items'))->toBeFalse();
});

it('seeds chip billing and analytics showcase data for the active demo owner', function (): void {
    $admin = User::factory()->create([
        'email' => 'admin@commerce.demo',
    ]);

    OwnerContext::withOwner($admin, function (): void {
        $this->seed(BillingShowcaseSeeder::class);
        $this->seed(AnalyticsShowcaseSeeder::class);
    });

    $admin->refresh();

    expect($admin->chip_id)->not->toBeNull()
        ->and($admin->default_pm_id)->not->toBeNull()
        ->and(Subscription::query()->count())->toBe(2)
        ->and(Subscription::query()->pluck('chip_status')->all())
        ->toContain(SubscriptionStatus::Active, SubscriptionStatus::Trialing)
        ->and(TrackedProperty::query()->count())->toBe(1)
        ->and(TrackedProperty::query()->first()?->domain)->toBe('cdemo.test')
        ->and(Experiment::query()->count())->toBe(1)
        ->and(Variant::query()->count())->toBe(2)
        ->and(Assignment::query()->count())->toBe(2)
        ->and(SignalEvent::query()->count())->toBe(6);
});

it('renders the storefront Signals tracker for the active demo owner', function (): void {
    $admin = User::factory()->create([
        'email' => 'admin@commerce.demo',
    ]);

    OwnerContext::withOwner($admin, function (): void {
        $this->seed(AnalyticsShowcaseSeeder::class);
    });

    $this->withSession(['demo_owner_id' => $admin->id])
        ->get('/')
        ->assertOk()
        ->assertSee('/api/signals/tracker.js', false)
        ->assertSee('data-write-key="demo-storefront-write-key-0000000000000"', false);

    $this->actingAs($admin)
        ->get('/subscribe/stripe/pro')
        ->assertNotFound();

    $this->actingAs($admin)
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
