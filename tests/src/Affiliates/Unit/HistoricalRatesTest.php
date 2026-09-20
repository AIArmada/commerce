<?php

declare(strict_types=1);

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Services\DailyAggregationService;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\CommerceSupport\Settings\ExchangeRateSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    // Settings cache outlives RefreshDatabase rollbacks within a process.
    Artisan::call('settings:clear-cache');
});

function historicalRatesAffiliate(): Affiliate
{
    return Affiliate::create([
        'code' => 'HIST001',
        'name' => 'Historical Rates Test',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);
}

function historicalRatesConfig(): void
{
    (new ExchangeRateSettings([
        'base' => 'USD',
        'rates' => ['MYR' => 5.0, 'EUR' => 1.0],
        'history' => ['2026-01-01' => ['MYR' => 4.0, 'EUR' => 0.8]],
    ]))->save();
}

test('recorded conversions stamp the rate effective at occurred_at', function (): void {
    historicalRatesConfig();
    $affiliate = historicalRatesAffiliate();

    $conversion = AffiliateConversion::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'order_reference' => 'ORD-HIST-001',
        'value_minor' => 4000,
        'commission_minor' => 400,
        'commission_currency' => 'MYR',
        'status' => ApprovedConversion::class,
        'occurred_at' => CarbonImmutable::parse('2026-02-01 12:00:00'),
    ]);

    // Historic MYR->USD rate is 1/4.0, not the current 1/5.0.
    expect($conversion->commission_rate_base)->toBe('USD')
        ->and((float) $conversion->commission_rate_to_base)->toBe(0.25);
});

test('baseCommissionMinor prefers the stored rate over live rates', function (): void {
    historicalRatesConfig();
    $affiliate = historicalRatesAffiliate();

    $conversion = AffiliateConversion::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'order_reference' => 'ORD-HIST-002',
        'value_minor' => 4000,
        'commission_minor' => 400,
        'commission_currency' => 'MYR',
        'commission_rate_to_base' => 0.25,
        'commission_rate_base' => 'USD',
        'status' => ApprovedConversion::class,
        'occurred_at' => CarbonImmutable::parse('2026-02-01 12:00:00'),
    ]);

    // 400 * 0.25 = 100; live rate would give 80.
    expect($conversion->fresh()?->baseCommissionMinor())->toBe(100);
});

test('aggregated period stats use period-end rates, not current rates', function (): void {
    historicalRatesConfig();
    $affiliate = historicalRatesAffiliate();
    $day = CarbonImmutable::parse('2026-02-01');

    AffiliateConversion::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'order_reference' => 'ORD-HIST-003',
        'value_minor' => 4000,
        'commission_minor' => 400,
        'commission_currency' => 'MYR',
        'status' => ApprovedConversion::class,
        'occurred_at' => $day->setTime(10, 0),
    ]);

    AffiliateConversion::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'order_reference' => 'ORD-HIST-004',
        'value_minor' => 800,
        'commission_minor' => 80,
        'commission_currency' => 'EUR',
        'status' => ApprovedConversion::class,
        'occurred_at' => $day->setTime(11, 0),
    ]);

    app(DailyAggregationService::class)->aggregateForAffiliate($affiliate, $day);

    $stats = app(DailyAggregationService::class)->getAggregatedStats($affiliate, $day, $day);

    // Historic: 4000 MYR @ 4.0 = 1000 USD, 800 EUR @ 0.8 = 1000 USD.
    expect($stats['converted'])->toBeTrue()
        ->and($stats['revenue_cents'])->toBe(2000)
        ->and($stats['commission_cents'])->toBe(200);
});
