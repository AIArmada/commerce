<?php

declare(strict_types=1);

use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Models\VoucherUsage;
use Illuminate\Database\Eloquent\Model;

test('voucher has relations', function (): void {
    $voucher = Voucher::create([
        'code' => 'TEST',
        'name' => 'Test Voucher',
        'type' => 'percentage',
        'value' => 10,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    expect($voucher->usages())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\HasMany::class)
        ->and($voucher->walletEntries())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\HasMany::class)
        ->and($voucher->owner())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\MorphTo::class);
});

test('voucher scope for owner', function (): void {
    // Test with owner disabled
    Config::set('vouchers.owner.enabled', false);
    $query = Voucher::forOwner(null);
    expect($query)->toBeInstanceOf(Illuminate\Database\Eloquent\Builder::class);

    Config::set('vouchers.owner.enabled', true);

    // Test with no owner, include global
    $query = Voucher::forOwner(null, true);
    expect($query)->toBeInstanceOf(Illuminate\Database\Eloquent\Builder::class);

    // Test with no owner, exclude global
    $query = Voucher::forOwner(null, false);
    expect($query)->toBeInstanceOf(Illuminate\Database\Eloquent\Builder::class);

    // Test with owner
    $user = new class extends Model
    {
        protected $table = 'users';

        public function getMorphClass()
        {
            return 'User';
        }

        public function getKey()
        {
            return 1;
        }
    };

    $query = Voucher::forOwner($user, true);
    expect($query)->toBeInstanceOf(Illuminate\Database\Eloquent\Builder::class);
});

test('voucher has uses left', function (): void {
    $voucher = Voucher::create([
        'code' => 'LIMITED',
        'name' => 'Limited Voucher',
        'type' => 'fixed',
        'value' => 10,
        'currency' => 'MYR',
        'status' => 'active',
        'usage_limit' => 5,
    ]);

    expect($voucher->hasUsageLimitRemaining())->toBeTrue();

    // Add usages
    for ($i = 0; $i < 5; $i++) {
        VoucherUsage::create([
            'voucher_id' => $voucher->id,
            'discount_amount' => 100,
            'currency' => 'MYR',
            'used_at' => now(),
            'redeemed_by_id' => $i + 1,
            'redeemed_by_type' => 'User',
        ]);
    }

    expect($voucher->hasUsageLimitRemaining())->toBeFalse();
});

test('voucher get remaining uses', function (): void {
    $voucher = Voucher::create([
        'code' => 'REMAIN',
        'name' => 'Remaining Voucher',
        'type' => 'fixed',
        'value' => 10,
        'currency' => 'MYR',
        'status' => 'active',
        'usage_limit' => 10,
    ]);

    expect($voucher->getRemainingUses())->toBe(10);

    VoucherUsage::create([
        'voucher_id' => $voucher->id,
        'discount_amount' => 100,
        'currency' => 'MYR',
        'used_at' => now(),
        'redeemed_by_id' => 1,
        'redeemed_by_type' => 'User',
    ]);

    expect($voucher->getRemainingUses())->toBe(9);

    // No limit
    $unlimited = Voucher::create([
        'code' => 'UNLIMITED',
        'name' => 'Unlimited Voucher',
        'type' => 'fixed',
        'value' => 10,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    expect($unlimited->getRemainingUses())->toBeNull();
});

test('voucher increment usage', function (): void {
    $voucher = Voucher::create([
        'code' => 'INCREMENT',
        'name' => 'Increment Voucher',
        'type' => 'fixed',
        'value' => 10,
        'currency' => 'MYR',
        'status' => 'active',
        'usage_limit' => 1,
    ]);

    VoucherUsage::create([
        'voucher_id' => $voucher->id,
        'discount_amount' => 100,
        'currency' => 'MYR',
        'used_at' => now(),
        'redeemed_by_id' => 1,
        'redeemed_by_type' => 'User',
    ]);

    $voucher->incrementUsage();

    $voucher->refresh();
    expect($voucher->status)->toBe(VoucherStatus::Depleted);
});
