<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentVouchers\Services\VoucherStatsAggregator;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Models\VoucherUsage;
use AIArmada\Vouchers\States\Active;
use AIArmada\Vouchers\States\Expired;

uses(TestCase::class);

beforeEach(function (): void {
    $active = Voucher::create([
        'code' => 'ACTIVE-10',
        'name' => 'Active Voucher',
        'type' => VoucherType::Fixed,
        'value' => 1000,
        'currency' => 'USD',
        'status' => Active::class,
        'allows_manual_redemption' => true,
        'starts_at' => now()->subDay(),
    ]);

    Voucher::create([
        'code' => 'UPCOMING-15',
        'name' => 'Upcoming Voucher',
        'type' => VoucherType::Percentage,
        'value' => 1500,
        'currency' => 'USD',
        'status' => Active::class,
        'allows_manual_redemption' => true,
        'starts_at' => now()->addDay(),
    ]);

    Voucher::create([
        'code' => 'EXPIRED-5',
        'name' => 'Expired Voucher',
        'type' => VoucherType::Fixed,
        'value' => 500,
        'currency' => 'USD',
        'status' => Expired::class,
        'allows_manual_redemption' => false,
        'expires_at' => now()->subDay(),
    ]);

    VoucherUsage::create([
        'voucher_id' => $active->id,
        'discount_amount' => 2500,
        'currency' => 'USD',
        'channel' => VoucherUsage::CHANNEL_MANUAL,
        'used_at' => now()->subMinutes(10),
    ]);

    VoucherUsage::create([
        'voucher_id' => $active->id,
        'discount_amount' => 500,
        'currency' => 'USD',
        'channel' => VoucherUsage::CHANNEL_API,
        'used_at' => now()->subMinutes(5),
    ]);
});

it('aggregates voucher overview metrics', function (): void {
    $aggregator = app(VoucherStatsAggregator::class);

    $stats = $aggregator->overview();

    expect($stats['total'])->toBe(3);
    expect($stats['active'])->toBe(2);
    expect($stats['upcoming'])->toBe(1);
    expect($stats['expired'])->toBe(1);
    expect($stats['manual_redemptions'])->toBe(1);
    expect($stats['total_discount_minor'])->toBe(3000);
});
