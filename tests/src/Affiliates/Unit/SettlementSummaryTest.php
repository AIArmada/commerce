<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\CommissionRuleType;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Services\AffiliateReportService;
use AIArmada\Affiliates\Services\Commissions\CommissionRuleEngine;
use AIArmada\Affiliates\Services\PayoutReconciliationService;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\PendingPayout;
use AIArmada\CommerceSupport\Settings\ExchangeRateSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    // Settings cache outlives RefreshDatabase rollbacks within a process.
    Artisan::call('settings:clear-cache');
});

function settlementAffiliate(string $code, ?string $id = null): Affiliate
{
    return Affiliate::create(array_filter([
        'id' => $id,
        'code' => $code,
        'name' => 'Settlement Test',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]));
}

function settlementRates(): void
{
    (new ExchangeRateSettings([
        'base' => 'USD',
        'rates' => ['MYR' => 4.7],
        'history' => ['2026-01-01' => ['MYR' => 4.0]],
    ]))->save();
}

test('reconciliation report discloses conversion provenance and uses period-end rates', function (): void {
    settlementRates();

    $affiliate = settlementAffiliate('SETTLE001');

    foreach ([['USD', 10000, 'SETTLE-USD'], ['MYR', 47000, 'SETTLE-MYR']] as [$currency, $total, $ref]) {
        AffiliatePayout::create([
            'reference' => $ref,
            'payee_type' => $affiliate->getMorphClass(),
            'payee_id' => $affiliate->id,
            'total_minor' => $total,
            'currency' => $currency,
            'status' => PendingPayout::class,
            'created_at' => CarbonImmutable::parse('2026-06-15 12:00:00'),
        ]);
    }

    $report = app(PayoutReconciliationService::class)->generateReport('2026-01-01', '2026-12-31');

    expect($report['by_currency'])->toHaveKeys(['USD', 'MYR'])
        ->and($report['summary']['converted'])->toBeTrue()
        // 10,000 USD @ 4.0 = 40,000 MYR + 47,000 MYR leg (current 4.7 would give 94,000).
        ->and($report['summary']['total_amount_minor'])->toBe(87000)
        ->and($report['summary']['conversion'])->toBe([
            'currency' => 'MYR',
            'as_of' => '2026-12-31',
            'source' => 'SettingsExchangeRateProvider',
        ]);
});

test('report summaries disclose conversion provenance', function (): void {
    settlementRates();

    $affiliate = settlementAffiliate('SETTLE002');
    $start = CarbonImmutable::parse('2026-06-01');
    $end = CarbonImmutable::parse('2026-06-30');

    foreach ([['USD', 10000, 'SETTLE-SUM-USD'], ['MYR', 40000, 'SETTLE-SUM-MYR']] as [$currency, $value, $ref]) {
        AffiliateConversion::create([
            'affiliate_id' => $affiliate->id,
            'affiliate_code' => $affiliate->code,
            'order_reference' => $ref,
            'value_minor' => $value,
            'commission_minor' => (int) ($value / 10),
            'commission_currency' => $currency,
            'status' => ApprovedConversion::class,
            'occurred_at' => CarbonImmutable::parse('2026-06-15 12:00:00'),
        ]);
    }

    $summary = app(AffiliateReportService::class)->getSummary($start, $end);

    expect($summary['converted'])->toBeTrue()
        ->and($summary['conversion'])->toBe([
            'currency' => 'MYR',
            'as_of' => '2026-06-30',
            'source' => 'SettingsExchangeRateProvider',
        ]);
});

test('top affiliates break ranking ties by affiliate id', function (): void {
    settlementRates();
    // Created out of id order so insertion order cannot mask the tiebreak.
    $affiliates = [
        settlementAffiliate('SETTLE003A', 'aaaaaaaa-0000-0000-0000-000000000002'),
        settlementAffiliate('SETTLE003B', 'aaaaaaaa-0000-0000-0000-000000000001'),
    ];
    $start = CarbonImmutable::now()->startOfMonth();
    $end = CarbonImmutable::now()->endOfMonth();

    foreach ($affiliates as $index => $affiliate) {
        AffiliateConversion::create([
            'affiliate_id' => $affiliate->id,
            'affiliate_code' => $affiliate->code,
            'order_reference' => 'SETTLE-TIE-' . $index,
            'value_minor' => 10000,
            'commission_minor' => 1000,
            'commission_currency' => 'USD',
            'status' => ApprovedConversion::class,
            'occurred_at' => CarbonImmutable::now(),
        ]);
    }

    $expected = collect($affiliates)->map(fn (Affiliate $a): string => (string) $a->id)->sort()->values()->all();
    $actual = collect(app(AffiliateReportService::class)->getTopAffiliates($start, $end, 10))
        ->pluck('affiliate_id')->all();

    expect($actual)->toBe($expected);
});

test('performance leaderboard breaks revenue ties by affiliate id', function (): void {
    settlementRates();
    config(['affiliates.bonuses.top_performer.enabled' => true]);
    config(['affiliates.bonuses.top_performer.positions' => [1 => 5000, 2 => 2500]]);
    config(['affiliates.bonuses.top_performer.min_revenue' => 0]);

    $affiliates = [
        settlementAffiliate('SETTLE004A', 'bbbbbbbb-0000-0000-0000-000000000002'),
        settlementAffiliate('SETTLE004B', 'bbbbbbbb-0000-0000-0000-000000000001'),
    ];

    foreach ($affiliates as $index => $affiliate) {
        AffiliateConversion::create([
            'affiliate_id' => $affiliate->id,
            'affiliate_code' => $affiliate->code,
            'order_reference' => 'SETTLE-BOARD-' . $index,
            'value_minor' => 10000,
            'commission_minor' => 1000,
            'commission_currency' => 'USD',
            'status' => ApprovedConversion::class,
            'occurred_at' => CarbonImmutable::now(),
        ]);
    }

    $bonuses = app(CommissionRuleEngine::class)->calculatePerformanceBonuses(
        CommissionRuleType::TopPerformer,
        CarbonImmutable::now()->startOfMonth(),
        CarbonImmutable::now()->endOfMonth(),
    );

    $expected = collect($affiliates)->map(fn (Affiliate $a): string => (string) $a->id)->sort()->values()->all();

    expect(collect($bonuses)->pluck('affiliate_id')->all())->toBe($expected);
});
