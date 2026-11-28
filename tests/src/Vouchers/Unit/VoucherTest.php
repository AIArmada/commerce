<?php

declare(strict_types=1);

use AIArmada\Vouchers\Features\Lifecycle\Models\Voucher;
use AIArmada\Vouchers\Features\Lifecycle\Models\VoucherUsage;
use AIArmada\Vouchers\Features\Wallet\Api\WalletStatistics;
use AIArmada\Vouchers\Features\Wallet\Models\VoucherWallet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;

test('voucher has relations', function (): void {
    $voucher = Voucher::create([
        'code' => 'TEST',
        'name' => 'Test Voucher',
        'type' => 'percentage',
        'value' => 10,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    expect($voucher->usages())->toBeInstanceOf(HasMany::class)
        ->and($voucher->owner())->toBeInstanceOf(MorphTo::class);
});

test('voucher scope for owner', function (): void {
    // Test with owner disabled
    Config::set('vouchers.owner.enabled', false);
    $query = Voucher::forOwner(null);
    expect($query)->toBeInstanceOf(Builder::class);

    Config::set('vouchers.owner.enabled', true);

    // Test with no owner, include global
    $query = Voucher::forOwner(null, true);
    expect($query)->toBeInstanceOf(Builder::class);

    // Test with no owner, exclude global
    $query = Voucher::forOwner(null, false);
    expect($query)->toBeInstanceOf(Builder::class);

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
    expect($query)->toBeInstanceOf(Builder::class);
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

test('wallet statistics verify voucher visibility before aggregating wallet entries', function (): void {
    config()->set('vouchers.owner.enabled', false);

    $voucher = Voucher::create([
        'code' => 'WALLETCOUNTS',
        'name' => 'Wallet Counts Voucher',
        'type' => 'fixed',
        'value' => 10,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    foreach ([
        ['claimed_at' => null, 'redeemed_at' => null],
        ['claimed_at' => now(), 'redeemed_at' => null],
        ['claimed_at' => now(), 'redeemed_at' => now()],
    ] as $timestamps) {
        VoucherWallet::create([
            'voucher_id' => $voucher->id,
            ...$timestamps,
        ]);
    }

    $statistics = app(WalletStatistics::class);
    DB::enableQueryLog();
    DB::flushQueryLog();

    try {
        $counts = $statistics->forVoucher($voucher->id);
        $queries = DB::getQueryLog();
    } finally {
        DB::disableQueryLog();
    }

    expect($counts)->toBe(['total' => 3, 'claimed' => 2, 'redeemed' => 1, 'available' => 1])
        ->and($queries)->toHaveCount(2);
});
