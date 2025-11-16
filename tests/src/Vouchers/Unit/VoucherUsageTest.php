<?php

declare(strict_types=1);

use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Models\VoucherUsage;

test('voucher usage has relations', function (): void {
    $voucher = Voucher::create([
        'code' => 'TEST',
        'name' => 'Test Voucher',
        'type' => 'percentage',
        'value' => 10,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    $usage = VoucherUsage::create([
        'voucher_id' => $voucher->id,
        'discount_amount' => 100,
        'currency' => 'MYR',
        'used_at' => now(),
    ]);

    expect($usage->voucher())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

test('voucher usage is manual', function (): void {
    $voucher = Voucher::create([
        'code' => 'TEST',
        'name' => 'Test Voucher',
        'type' => 'percentage',
        'value' => 10,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    $manualUsage = VoucherUsage::create([
        'voucher_id' => $voucher->id,
        'discount_amount' => 100,
        'currency' => 'MYR',
        'channel' => 'manual',
        'used_at' => now(),
    ]);

    $autoUsage = VoucherUsage::create([
        'voucher_id' => $voucher->id,
        'discount_amount' => 100,
        'currency' => 'MYR',
        'channel' => 'cart',
        'used_at' => now(),
    ]);

    expect($manualUsage->isManual())->toBeTrue()
        ->and($autoUsage->isManual())->toBeFalse();
});