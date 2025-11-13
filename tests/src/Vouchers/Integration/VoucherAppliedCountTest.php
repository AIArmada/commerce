<?php

declare(strict_types=1);

use AIArmada\Cart\CartManager;
use AIArmada\Cart\Facades\Cart;
use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Events\VoucherApplied;
use AIArmada\Vouchers\Models\Voucher as VoucherModel;
use AIArmada\Vouchers\Support\CartManagerWithVouchers;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $manager = app(CartManager::class);

    if (! $manager instanceof CartManagerWithVouchers) {
        $proxy = CartManagerWithVouchers::fromCartManager($manager);

        Cart::swap($proxy);
        app()->instance('cart', $proxy);
        app()->instance(CartManager::class, $proxy);
    }

    Cart::clear();
    Cart::clearConditions();
    Cart::clearMetadata();
    Cart::clearVouchers();

    VoucherModel::query()->forceDelete();

    config([
        'vouchers.cart.max_vouchers_per_cart' => 1,
        'vouchers.validation.check_user_limit' => false,
        'vouchers.validation.check_global_limit' => true,
        'vouchers.validation.check_min_cart_value' => true,
        'vouchers.code.case_sensitive' => true,
    ]);
});

test('applied_count is incremented when voucher is applied to cart', function (): void {
    $voucher = VoucherModel::create([
        'name' => 'Test Count Voucher',
        'code' => 'COUNT10',
        'type' => VoucherType::Percentage,
        'value' => 10,
        'currency' => 'MYR',
        'description' => '10% off',
        'status' => VoucherStatus::Active,
        'starts_at' => now()->subDay(),
        'expires_at' => now()->addMonth(),
    ]);

    $voucher = $voucher->fresh(); // Refresh to get database defaults

    expect($voucher->applied_count)->toBe(0);

    Cart::add('sku-test-count', 'Test Product', 100);
    Cart::applyVoucher('COUNT10');

    $voucher->refresh();

    expect($voucher->applied_count)->toBe(1);
});

test('applied_count increments multiple times for different sessions', function (): void {
    $voucher = VoucherModel::create([
        'name' => 'Multi Apply Voucher',
        'code' => 'MULTI10',
        'type' => VoucherType::Percentage,
        'value' => 10,
        'currency' => 'MYR',
        'description' => '10% off',
        'status' => VoucherStatus::Active,
        'starts_at' => now()->subDay(),
        'expires_at' => now()->addMonth(),
    ]);

    $voucher = $voucher->fresh(); // Refresh to get database defaults

    expect($voucher->applied_count)->toBe(0);

    // First application
    Cart::add('sku-1', 'Product 1', 100);
    Cart::applyVoucher('MULTI10');

    $voucher->refresh();
    expect($voucher->applied_count)->toBe(1);

    // Clear cart and apply again (simulating different session)
    Cart::clear();
    Cart::clearVouchers();

    Cart::add('sku-2', 'Product 2', 200);
    Cart::applyVoucher('MULTI10');

    $voucher->refresh();
    expect($voucher->applied_count)->toBe(2);
});

test('VoucherApplied event is fired when voucher is applied', function (): void {
    Event::fake([VoucherApplied::class]);

    VoucherModel::create([
        'name' => 'Event Test Voucher',
        'code' => 'EVENT10',
        'type' => VoucherType::Percentage,
        'value' => 10,
        'currency' => 'MYR',
        'description' => '10% off',
        'status' => VoucherStatus::Active,
        'starts_at' => now()->subDay(),
        'expires_at' => now()->addMonth(),
    ]);

    Cart::add('sku-event', 'Test Product', 100);
    Cart::applyVoucher('EVENT10');

    Event::assertDispatched(VoucherApplied::class, function (VoucherApplied $event) {
        return $event->voucher->code === 'EVENT10';
    });
});

test('getConversionRate returns null when voucher has never been applied', function (): void {
    $voucher = VoucherModel::create([
        'name' => 'No Apply Voucher',
        'code' => 'NOAPPLY',
        'type' => VoucherType::Percentage,
        'value' => 10,
        'currency' => 'MYR',
        'status' => VoucherStatus::Active,
    ]);

    expect($voucher->getConversionRate())->toBeNull();
});

test('getConversionRate calculates correct percentage', function (): void {
    $voucher = VoucherModel::create([
        'name' => 'Conversion Test',
        'code' => 'CONVERT',
        'type' => VoucherType::Percentage,
        'value' => 10,
        'currency' => 'MYR',
        'status' => VoucherStatus::Active,
    ]);

    // Apply 5 times
    $voucher->applied_count = 5;
    $voucher->save();

    // Create 2 actual usages
    $voucher->usages()->create([
        'discount_amount' => 1000,
        'currency' => 'MYR',
        'channel' => 'automatic',
        'used_at' => now(),
    ]);

    $voucher->usages()->create([
        'discount_amount' => 1000,
        'currency' => 'MYR',
        'channel' => 'automatic',
        'used_at' => now(),
    ]);

    $voucher->refresh();

    // 2 out of 5 = 40%
    expect($voucher->getConversionRate())->toBe(40.0);
});

test('getAbandonedCount returns correct count', function (): void {
    $voucher = VoucherModel::create([
        'name' => 'Abandoned Test',
        'code' => 'ABANDON',
        'type' => VoucherType::Percentage,
        'value' => 10,
        'currency' => 'MYR',
        'status' => VoucherStatus::Active,
    ]);

    // Apply 10 times
    $voucher->applied_count = 10;
    $voucher->save();

    // Create 3 actual usages
    $voucher->usages()->create([
        'discount_amount' => 1000,
        'currency' => 'MYR',
        'channel' => 'automatic',
        'used_at' => now(),
    ]);

    $voucher->usages()->create([
        'discount_amount' => 1000,
        'currency' => 'MYR',
        'channel' => 'automatic',
        'used_at' => now(),
    ]);

    $voucher->usages()->create([
        'discount_amount' => 1000,
        'currency' => 'MYR',
        'channel' => 'automatic',
        'used_at' => now(),
    ]);

    $voucher->refresh();

    // 10 applied - 3 redeemed = 7 abandoned
    expect($voucher->getAbandonedCount())->toBe(7);
});

test('getStatistics returns comprehensive stats', function (): void {
    $voucher = VoucherModel::create([
        'name' => 'Stats Test',
        'code' => 'STATS',
        'type' => VoucherType::Percentage,
        'value' => 10,
        'currency' => 'MYR',
        'status' => VoucherStatus::Active,
        'usage_limit' => 100,
    ]);

    // Apply 20 times
    $voucher->applied_count = 20;
    $voucher->save();

    // Create 15 actual usages
    for ($i = 0; $i < 15; $i++) {
        $voucher->usages()->create([
            'discount_amount' => 1000,
            'currency' => 'MYR',
            'channel' => 'automatic',
            'used_at' => now(),
        ]);
    }

    $voucher->refresh();

    $stats = $voucher->getStatistics();

    expect($stats)->toHaveKey('applied_count')
        ->and($stats)->toHaveKey('redeemed_count')
        ->and($stats)->toHaveKey('abandoned_count')
        ->and($stats)->toHaveKey('conversion_rate')
        ->and($stats)->toHaveKey('remaining_uses')
        ->and($stats['applied_count'])->toBe(20)
        ->and($stats['redeemed_count'])->toBe(15)
        ->and($stats['abandoned_count'])->toBe(5)
        ->and($stats['conversion_rate'])->toBe(75.0)
        ->and($stats['remaining_uses'])->toBe(85);
});

test('applied_count defaults to zero for new vouchers', function (): void {
    $voucher = VoucherModel::create([
        'name' => 'Default Test',
        'code' => 'DEFAULT',
        'type' => VoucherType::Percentage,
        'value' => 10,
        'currency' => 'MYR',
        'status' => VoucherStatus::Active,
    ]);

    $voucher = $voucher->fresh(); // Refresh to get database defaults

    expect($voucher->applied_count)->toBe(0);
});

test('applied_count is not incremented when tracking is disabled', function (): void {
    config(['vouchers.tracking.track_applications' => false]);

    $voucher = VoucherModel::create([
        'name' => 'No Track Voucher',
        'code' => 'NOTRACK',
        'type' => VoucherType::Percentage,
        'value' => 10,
        'currency' => 'MYR',
        'status' => VoucherStatus::Active,
    ]);

    $voucher = $voucher->fresh();

    expect($voucher->applied_count)->toBe(0);

    Cart::add('sku-no-track', 'Test Product', 100);
    Cart::applyVoucher('NOTRACK');

    $voucher->refresh();

    // Should still be 0 because tracking is disabled
    expect($voucher->applied_count)->toBe(0);
});
