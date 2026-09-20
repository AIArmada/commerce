<?php

declare(strict_types=1);

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\Draft;
use AIArmada\Affiliates\States\PaidConversion;
use AIArmada\Affiliates\States\Pending;
use AIArmada\Affiliates\States\PendingConversion;
use AIArmada\CommerceSupport\Settings\ExchangeRateSettings;
use AIArmada\FilamentAffiliates\Services\AffiliateStatsAggregator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Affiliate::query()->delete();
    AffiliateConversion::query()->delete();
    // Settings cache outlives RefreshDatabase rollbacks within a process.
    Artisan::call('settings:clear-cache');
});

function createAffiliate(array $overrides = []): Affiliate
{
    return Affiliate::create(array_merge([
        'code' => 'AFF-' . Str::uuid(),
        'name' => 'Partner',
        'description' => null,
        'status' => Draft::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ], $overrides));
}

test('stats widget aggregator summarizes affiliate program health', function (): void {
    $active = createAffiliate(['status' => Active::class]);
    createAffiliate(['status' => Pending::class, 'code' => 'PENDING1']);
    createAffiliate(['status' => Draft::class, 'code' => 'DRAFT1']);

    AffiliateConversion::create([
        'affiliate_id' => $active->getKey(),
        'affiliate_code' => $active->code,
        'commission_minor' => 500,
        'commission_currency' => 'USD',
        'status' => PendingConversion::class,
    ]);

    AffiliateConversion::create([
        'affiliate_id' => $active->getKey(),
        'affiliate_code' => $active->code,
        'commission_minor' => 200,
        'commission_currency' => 'USD',
        'status' => PaidConversion::class,
    ]);

    AffiliateConversion::create([
        'affiliate_id' => $active->getKey(),
        'affiliate_code' => $active->code,
        'commission_minor' => 300,
        'commission_currency' => 'USD',
        'status' => ApprovedConversion::class,
    ]);

    $stats = app(AffiliateStatsAggregator::class)->overview();

    expect($stats)
        ->toMatchArray([
            'total_affiliates' => 3,
            'active_affiliates' => 1,
            'pending_affiliates' => 1,
            'total_conversions' => 3,
            'pending_commission_minor' => 500,
            'paid_commission_minor' => 200,
            'total_commission_minor' => 1000,
        ])
        ->and($stats['conversion_rate'])->toBeGreaterThan(66)
        ->and($stats['conversion_rate'])->toBeLessThan(67);
});

test('stats widget aggregator refuses to blend mixed-currency commissions without rates', function (): void {
    $active = createAffiliate(['status' => Active::class]);

    AffiliateConversion::create([
        'affiliate_id' => $active->getKey(),
        'affiliate_code' => $active->code,
        'commission_minor' => 1000,
        'commission_currency' => 'USD',
        'status' => PendingConversion::class,
    ]);

    AffiliateConversion::create([
        'affiliate_id' => $active->getKey(),
        'affiliate_code' => $active->code,
        'commission_minor' => 4700,
        'commission_currency' => 'MYR',
        'status' => PendingConversion::class,
    ]);

    $stats = app(AffiliateStatsAggregator::class)->overview();

    expect($stats['pending_commission_minor'])->toBeNull()
        ->and($stats['commission_converted'])->toBeFalse()
        ->and($stats['commission_by_currency'])->toHaveKeys(['USD', 'MYR']);
});

test('stats widget aggregator converts mixed-currency commissions when rates exist', function (): void {
    config(['filament-affiliates.widgets.currency' => 'USD']);
    (new ExchangeRateSettings(['base' => 'USD', 'rates' => ['MYR' => 4.7], 'history' => []]))->save();

    $active = createAffiliate(['status' => Active::class]);

    AffiliateConversion::create([
        'affiliate_id' => $active->getKey(),
        'affiliate_code' => $active->code,
        'commission_minor' => 1000,
        'commission_currency' => 'USD',
        'status' => PendingConversion::class,
    ]);

    AffiliateConversion::create([
        'affiliate_id' => $active->getKey(),
        'affiliate_code' => $active->code,
        'commission_minor' => 4700,
        'commission_currency' => 'MYR',
        'status' => PendingConversion::class,
    ]);

    $stats = app(AffiliateStatsAggregator::class)->overview();

    expect($stats['pending_commission_minor'])->toBe(2000)
        ->and($stats['commission_currency'])->toBe('USD')
        ->and($stats['commission_converted'])->toBeTrue();
});
