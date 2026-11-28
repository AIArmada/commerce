<?php

declare(strict_types=1);

use AIArmada\Vouchers\Data\VoucherType;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Models\VoucherUsage;
use AIArmada\Vouchers\States\Depleted;
use Carbon\CarbonImmutable;
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
        'status' => 'active',
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

    it('uses the same inclusive start and expiry boundaries for model and live query', function (): void {
        $now = CarbonImmutable::parse('2026-01-01 12:00:00');
        CarbonImmutable::setTestNow($now);

        try {
            $starting = createVoucherForScopesTest(['starts_at' => $now]);
            $expiring = createVoucherForScopesTest(['expires_at' => $now]);

            expect($starting->canBeRedeemed())->toBeTrue()
                ->and($expiring->canBeRedeemed())->toBeTrue()
                ->and(Voucher::query()->live()->pluck('id')->all())->toContain($starting->id, $expiring->id);
        } finally {
            CarbonImmutable::setTestNow();
        }
    });

    it('filters live vouchers by state, wall-clock window, and usage limit', function (): void {
        $live = createVoucherForScopesTest([
            'starts_at' => Carbon::now()->subHour(),
            'expires_at' => Carbon::now()->addHour(),
            'usage_limit' => 2,
        ]);
        $upcoming = createVoucherForScopesTest(['starts_at' => Carbon::now()->addHour()]);
        $expired = createVoucherForScopesTest(['expires_at' => Carbon::now()->subHour()]);
        $paused = createVoucherForScopesTest(['status' => 'paused']);
        $depleted = createVoucherForScopesTest(['status' => 'depleted']);
        $limitReached = createVoucherForScopesTest(['usage_limit' => 1]);

        VoucherUsage::create([
            'voucher_id' => $limitReached->id,
            'discount_amount' => 100,
            'currency' => 'MYR',
            'channel' => 'web',
            'used_at' => now(),
        ]);

        $liveIds = Voucher::query()->live()->pluck('id')->all();

        expect($liveIds)->toContain($live->id)
            ->not->toContain($upcoming->id)
            ->not->toContain($expired->id)
            ->not->toContain($paused->id)
            ->not->toContain($depleted->id)
            ->not->toContain($limitReached->id);
    });
});

describe('Voucher Model Methods', function (): void {
    /* isActive/hasStarted/isExpired/hasUsageLimitRemaining removed; fuller matrices in Models/VoucherModelTest. */

    it('transitions to depleted through the state machine after usage reaches the limit', function (): void {
        $voucher = createVoucherForScopesTest(['usage_limit' => 1]);

        VoucherUsage::create([
            'voucher_id' => $voucher->id,
            'discount_amount' => 100,
            'currency' => 'MYR',
            'channel' => 'web',
            'used_at' => now(),
        ]);

        $voucher->checkIfDepleted();

        expect($voucher->fresh()?->status)->toBeInstanceOf(Depleted::class);
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
            'status' => 'active',
            'starts_at' => Carbon::now()->subDay(),
            'expires_at' => Carbon::now()->addDay(),
            'usage_limit' => 10,
        ]);

        $expiredVoucher = createVoucherForScopesTest([
            'status' => 'active',
            'expires_at' => Carbon::now()->subHour(),
        ]);

        $pausedVoucher = createVoucherForScopesTest([
            'status' => 'paused',
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

    /* Conversion/abandoned/statistics removed; covered by Integration/VoucherAppliedCountTest. */
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

});
