<?php

declare(strict_types=1);

use AIArmada\Affiliates\Settings\AffiliatePayoutSettings;
use AIArmada\Affiliates\Support\PayoutMinimums;
use Illuminate\Support\Facades\Artisan;

function seedAffiliatePayoutSettings(int $minimumAmount, array $map): void
{
    $settings = new AffiliatePayoutSettings([
        'minimumAmount' => $minimumAmount,
        'minimumAmountsByCurrency' => $map,
    ]);
    $settings->save();
}

describe('PayoutMinimums settings', function (): void {
    beforeEach(function (): void {
        // Settings cache outlives RefreshDatabase rollbacks within a process.
        Artisan::call('settings:clear-cache');
    });

    it('ships migration defaults through the normal migrate flow', function (): void {
        $settings = app(AffiliatePayoutSettings::class);

        expect($settings->minimumAmount)->toBe(5000)
            ->and($settings->minimumAmountsByCurrency)->toBe([]);
    });

    it('falls back to config when settings cannot load', function (): void {
        config([
            'affiliates.payouts.minimum_amount' => 5000,
            'affiliates.payouts.minimum_amounts_by_currency' => ['USD' => 10000],
        ]);

        // An unloadable settings object: property access throws before any
        // value is read, exercising the config fallback branch.
        $unloadable = (new ReflectionClass(AffiliatePayoutSettings::class))->newInstanceWithoutConstructor();
        app()->instance(AffiliatePayoutSettings::class, $unloadable);

        expect(PayoutMinimums::forCurrency('USD'))->toBe(10000)
            ->and(PayoutMinimums::forCurrency('MYR'))->toBe(5000);
    });

    it('prefers settings values once saved', function (): void {
        config([
            'affiliates.payouts.minimum_amount' => 5000,
            'affiliates.payouts.minimum_amounts_by_currency' => ['USD' => 10000],
        ]);
        seedAffiliatePayoutSettings(7000, ['USD' => 20000]);

        expect(PayoutMinimums::forCurrency('USD'))->toBe(20000)
            ->and(PayoutMinimums::forCurrency('usd'))->toBe(20000)
            ->and(PayoutMinimums::forCurrency('MYR'))->toBe(7000);
    });

    it('uses the settings group name', function (): void {
        expect(AffiliatePayoutSettings::group())->toBe('affiliate-payouts');
    });
});
