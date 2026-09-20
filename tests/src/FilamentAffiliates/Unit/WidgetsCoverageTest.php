<?php

declare(strict_types=1);

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\CommerceSupport\Support\OwnerCache;
use AIArmada\FilamentAffiliates\Services\AffiliateStatsAggregator;
use AIArmada\FilamentAffiliates\Widgets\AffiliateStatsWidget;
use AIArmada\FilamentAffiliates\Widgets\PerformanceOverviewWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

beforeEach(function (): void {
    AffiliateConversion::query()->delete();
    Affiliate::query()->delete();
});

it('AffiliateStatsWidget builds stats', function (): void {
    $this->app->instance(AffiliateStatsAggregator::class, new class
    {
        public function overview(): array
        {
            return [
                'active_affiliates' => 2,
                'total_affiliates' => 5,
                'pending_affiliates' => 1,
                'pending_commission_minor' => 12345,
                'paid_commission_minor' => 67890,
                'commission_currency' => 'USD',
                'commission_converted' => false,
                'conversion_rate' => 12.3,
            ];
        }
    });

    $widget = new AffiliateStatsWidget;

    $reflection = new ReflectionClass($widget);
    $method = $reflection->getMethod('getStats');

    $stats = $method->invoke($widget);

    expect($stats)->toBeArray()->and(count($stats))->toBe(5);
});

it('PerformanceOverviewWidget builds stats and computes changes', function (): void {
    Carbon::setTestNow('2024-03-15 12:00:00');
    OwnerCache::forget(null, 'filament-affiliates.performance-overview');

    $affiliate = Affiliate::create([
        'code' => 'PERF-' . Str::uuid(),
        'name' => 'Perf Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    // This month
    AffiliateConversion::create([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_code' => $affiliate->code,
        'external_reference' => 'ORDER-MONTH',
        'value_minor' => 9000,
        'commission_minor' => 1000,
        'commission_currency' => 'USD',
        'status' => ApprovedConversion::class,
        'occurred_at' => now()->startOfMonth()->addDay(),
    ]);

    // Last month
    AffiliateConversion::create([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_code' => $affiliate->code,
        'external_reference' => 'ORDER-LAST',
        'value_minor' => 4000,
        'commission_minor' => 500,
        'commission_currency' => 'USD',
        'status' => ApprovedConversion::class,
        'occurred_at' => now()->subMonth()->startOfMonth()->addDays(9),
    ]);

    $widget = new PerformanceOverviewWidget;

    $reflection = new ReflectionClass($widget);
    $method = $reflection->getMethod('getStats');

    $stats = $method->invoke($widget);

    expect($stats)->toBeArray()->and(count($stats))->toBe(4)
        ->and($stats[1]->getDescription())->toBe('+125.0% from last month');
});

it('PerformanceOverviewWidget refuses to trend mixed-currency revenue without rates', function (): void {
    Carbon::setTestNow('2024-03-15 12:00:00');
    OwnerCache::forget(null, 'filament-affiliates.performance-overview');

    $affiliate = Affiliate::create([
        'code' => 'PERFMC-' . Str::uuid(),
        'name' => 'Perf MC Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    AffiliateConversion::create([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_code' => $affiliate->code,
        'external_reference' => 'ORDER-MC-USD',
        'value_minor' => 9000,
        'commission_minor' => 1000,
        'commission_currency' => 'USD',
        'status' => ApprovedConversion::class,
        'occurred_at' => now()->startOfMonth()->addDay(),
    ]);

    AffiliateConversion::create([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_code' => $affiliate->code,
        'external_reference' => 'ORDER-MC-MYR',
        'value_minor' => 42300,
        'commission_minor' => 4700,
        'commission_currency' => 'MYR',
        'status' => ApprovedConversion::class,
        'occurred_at' => now()->startOfMonth()->addDays(2),
    ]);

    $widget = new PerformanceOverviewWidget;

    $reflection = new ReflectionClass($widget);
    $method = $reflection->getMethod('getStats');

    $stats = $method->invoke($widget);

    expect($stats[1]->getDescription())->toBe('Mixed currencies — set exchange rates');
});

it('RealTimeActivityWidget shows neutral reference semantics', function (): void {
    $repositoryRoot = dirname(__DIR__, 4);
    $source = file_get_contents($repositoryRoot . '/packages/filament-affiliates/src/Widgets/RealTimeActivityWidget.php');

    expect($source)
        ->toContain("TextColumn::make('external_reference')")
        ->toContain("->label('Reference')")
        ->toContain("TextColumn::make('value_minor')")
        ->toContain("->label('Value')");
});
