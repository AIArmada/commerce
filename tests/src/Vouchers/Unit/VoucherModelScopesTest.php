<?php

declare(strict_types=1);

use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Models\VoucherUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function createVoucherForScopesTest(array $attributes = []): Voucher
{
    return Voucher::create(array_merge([
        'code' => 'SCOPE-TEST-' . uniqid(),
        'name' => 'Test Voucher',
        'type' => VoucherType::Percentage,
        'value' => 1000,
        'status' => VoucherStatus::Active,
        'stacking_priority' => 100,
    ], $attributes));
}

describe('Voucher Model Scopes', function (): void {
    it('filters vouchers by affiliate', function (): void {
        createVoucherForScopesTest(['affiliate_id' => 'affiliate-123']);
        createVoucherForScopesTest(['affiliate_id' => 'affiliate-123']);
        createVoucherForScopesTest(['affiliate_id' => 'affiliate-456']);

        $affiliateVouchers = Voucher::forAffiliate('affiliate-123')->get();

        expect($affiliateVouchers)->toHaveCount(2);
        $affiliateVouchers->each(fn ($v) => expect($v->affiliate_id)->toBe('affiliate-123'));
    });
});

describe('Voucher Model Methods', function (): void {
    it('checks if voucher is active', function (): void {
        $active = createVoucherForScopesTest(['status' => VoucherStatus::Active]);
        $paused = createVoucherForScopesTest(['status' => VoucherStatus::Paused]);

        expect($active->isActive())->toBeTrue()
            ->and($paused->isActive())->toBeFalse();
    });

    it('checks if voucher has started', function (): void {
        $started = createVoucherForScopesTest(['starts_at' => Carbon::now()->subHour()]);
        $notStarted = createVoucherForScopesTest(['starts_at' => Carbon::now()->addHour()]);
        $noStartDate = createVoucherForScopesTest(['starts_at' => null]);

        expect($started->hasStarted())->toBeTrue()
            ->and($notStarted->hasStarted())->toBeFalse()
            ->and($noStartDate->hasStarted())->toBeTrue();
    });

    it('checks if voucher is expired', function (): void {
        $expired = createVoucherForScopesTest(['expires_at' => Carbon::now()->subHour()]);
        $notExpired = createVoucherForScopesTest(['expires_at' => Carbon::now()->addHour()]);
        $noExpireDate = createVoucherForScopesTest(['expires_at' => null]);

        expect($expired->isExpired())->toBeTrue()
            ->and($notExpired->isExpired())->toBeFalse()
            ->and($noExpireDate->isExpired())->toBeFalse();
    });

    it('checks usage limit remaining', function (): void {
        $unlimited = createVoucherForScopesTest(['usage_limit' => null]);
        $limited = createVoucherForScopesTest(['usage_limit' => 5]);

        expect($unlimited->hasUsageLimitRemaining())->toBeTrue()
            ->and($limited->hasUsageLimitRemaining())->toBeTrue();
    });

    it('calculates remaining uses', function (): void {
        $unlimited = createVoucherForScopesTest(['usage_limit' => null]);
        $limited = createVoucherForScopesTest(['usage_limit' => 10]);

        // Add some usages
        VoucherUsage::create([
            'voucher_id' => $limited->id,
            'discount_amount' => 100,
            'currency' => 'MYR',
            'channel' => 'web',
            'used_at' => now(),
        ]);
        VoucherUsage::create([
            'voucher_id' => $limited->id,
            'discount_amount' => 100,
            'currency' => 'MYR',
            'channel' => 'web',
            'used_at' => now(),
        ]);

        expect($unlimited->getRemainingUses())->toBeNull()
            ->and($limited->getRemainingUses())->toBe(8);
    });

    it('calculates times used attribute', function (): void {
        $voucher = createVoucherForScopesTest();

        expect($voucher->times_used)->toBe(0);

        VoucherUsage::create([
            'voucher_id' => $voucher->id,
            'discount_amount' => 100,
            'currency' => 'MYR',
            'channel' => 'web',
            'used_at' => now(),
        ]);

        $voucher->refresh();

        expect($voucher->times_used)->toBe(1);
    });

    it('checks canBeRedeemed correctly', function (): void {
        $validVoucher = createVoucherForScopesTest([
            'status' => VoucherStatus::Active,
            'starts_at' => Carbon::now()->subDay(),
            'expires_at' => Carbon::now()->addDay(),
            'usage_limit' => 10,
        ]);

        $expiredVoucher = createVoucherForScopesTest([
            'status' => VoucherStatus::Active,
            'expires_at' => Carbon::now()->subHour(),
        ]);

        $pausedVoucher = createVoucherForScopesTest([
            'status' => VoucherStatus::Paused,
        ]);

        expect($validVoucher->canBeRedeemed())->toBeTrue()
            ->and($expiredVoucher->canBeRedeemed())->toBeFalse()
            ->and($pausedVoucher->canBeRedeemed())->toBeFalse();
    });

    it('checks manual redemption allowed', function (): void {
        $manualAllowed = createVoucherForScopesTest(['allows_manual_redemption' => true]);
        $manualNotAllowed = createVoucherForScopesTest(['allows_manual_redemption' => false]);

        expect($manualAllowed->allowsManualRedemption())->toBeTrue()
            ->and($manualNotAllowed->allowsManualRedemption())->toBeFalse();
    });

    it('calculates usage progress', function (): void {
        $unlimited = createVoucherForScopesTest(['usage_limit' => null]);
        $limited = createVoucherForScopesTest(['usage_limit' => 10]);

        // Add 3 usages to limited voucher
        for ($i = 0; $i < 3; $i++) {
            VoucherUsage::create([
                'voucher_id' => $limited->id,
                'discount_amount' => 100,
                'currency' => 'MYR',
                'channel' => 'web',
                'used_at' => now(),
            ]);
        }

        $limited->refresh();

        expect($unlimited->usageProgress)->toBeNull()
            ->and($limited->usageProgress)->toBe(30.0);
    });

    it('calculates conversion rate', function (): void {
        $voucher = createVoucherForScopesTest(['applied_count' => 10]);

        // No usages yet
        expect($voucher->getConversionRate())->toBe(0.0);

        // Add some usages
        VoucherUsage::create([
            'voucher_id' => $voucher->id,
            'discount_amount' => 100,
            'currency' => 'MYR',
            'channel' => 'web',
            'used_at' => now(),
        ]);
        VoucherUsage::create([
            'voucher_id' => $voucher->id,
            'discount_amount' => 100,
            'currency' => 'MYR',
            'channel' => 'web',
            'used_at' => now(),
        ]);

        $voucher->refresh();

        expect($voucher->getConversionRate())->toBe(20.0);
    });

    it('returns null conversion rate when never applied', function (): void {
        $voucher = createVoucherForScopesTest(['applied_count' => 0]);

        expect($voucher->getConversionRate())->toBeNull();
    });

    it('calculates abandoned count', function (): void {
        $voucher = createVoucherForScopesTest(['applied_count' => 10]);

        // Add 3 usages (7 abandoned)
        for ($i = 0; $i < 3; $i++) {
            VoucherUsage::create([
                'voucher_id' => $voucher->id,
                'discount_amount' => 100,
                'currency' => 'MYR',
                'channel' => 'web',
                'used_at' => now(),
            ]);
        }

        $voucher->refresh();

        expect($voucher->getAbandonedCount())->toBe(7);
    });

    it('returns comprehensive statistics', function (): void {
        $voucher = createVoucherForScopesTest([
            'applied_count' => 20,
            'usage_limit' => 50,
        ]);

        // Add 10 usages
        for ($i = 0; $i < 10; $i++) {
            VoucherUsage::create([
                'voucher_id' => $voucher->id,
                'discount_amount' => 100,
                'currency' => 'MYR',
                'channel' => 'web',
                'used_at' => now(),
            ]);
        }

        $voucher->refresh();
        $stats = $voucher->getStatistics();

        expect($stats)->toBeArray()
            ->and($stats['applied_count'])->toBe(20)
            ->and($stats['redeemed_count'])->toBe(10)
            ->and($stats['abandoned_count'])->toBe(10)
            ->and($stats['conversion_rate'])->toBe(50.0)
            ->and($stats['remaining_uses'])->toBe(40);
    });
});

describe('Voucher Stacking Methods', function (): void {
    it('checks stacking with empty exclusion groups', function (): void {
        $voucher1 = createVoucherForScopesTest(['exclusion_groups' => null]);
        $voucher2 = createVoucherForScopesTest(['exclusion_groups' => ['flash_sale']]);

        expect($voucher1->canStackWith($voucher2))->toBeTrue()
            ->and($voucher2->canStackWith($voucher1))->toBeTrue();
    });

    it('allows stacking for different exclusion groups', function (): void {
        $voucher1 = createVoucherForScopesTest(['exclusion_groups' => ['flash_sale']]);
        $voucher2 = createVoucherForScopesTest(['exclusion_groups' => ['clearance']]);

        expect($voucher1->canStackWith($voucher2))->toBeTrue();
    });

    it('denies stacking for overlapping exclusion groups', function (): void {
        $voucher1 = createVoucherForScopesTest(['exclusion_groups' => ['flash_sale', 'summer']]);
        $voucher2 = createVoucherForScopesTest(['exclusion_groups' => ['flash_sale', 'winter']]);

        expect($voucher1->canStackWith($voucher2))->toBeFalse();
    });

    it('returns stacking priority', function (): void {
        // Test default priority when not explicitly set
        $defaultPriority = createVoucherForScopesTest();
        $customPriority = createVoucherForScopesTest(['stacking_priority' => 50]);

        expect($defaultPriority->getStackingPriority())->toBe(100)
            ->and($customPriority->getStackingPriority())->toBe(50);
    });
});

describe('Voucher Relationships', function (): void {
    it('has many usages', function (): void {
        $voucher = createVoucherForScopesTest();

        VoucherUsage::create([
            'voucher_id' => $voucher->id,
            'discount_amount' => 100,
            'currency' => 'MYR',
            'channel' => 'web',
            'used_at' => now(),
        ]);

        expect($voucher->usages)->toHaveCount(1)
            ->and($voucher->usages->first())->toBeInstanceOf(VoucherUsage::class);
    });

    it('uses correct table name from config', function (): void {
        $voucher = new Voucher;

        expect($voucher->getTable())->toBe('vouchers');
    });
});
