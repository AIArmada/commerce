<?php

declare(strict_types=1);

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\PendingPayout;
use AIArmada\FilamentAffiliates\Widgets\AffiliateStatsWidget;
use AIArmada\FilamentAffiliates\Widgets\FraudAlertWidget;
use AIArmada\FilamentAffiliates\Widgets\PayoutQueueWidget;
use AIArmada\FilamentAffiliates\Widgets\PerformanceOverviewWidget;
use AIArmada\FilamentAffiliates\Widgets\RealTimeActivityWidget;
use AIArmada\FilamentAffiliates\Widgets\UplineVisualizationWidget;
use Illuminate\Support\Str;

beforeEach(function (): void {
    AffiliateFraudSignal::query()->delete();
    AffiliatePayout::query()->delete();
    AffiliateConversion::query()->delete();
    Affiliate::query()->delete();
});

// AffiliateStatsWidget Tests
// PerformanceOverviewWidget Tests
// RealTimeActivityWidget Tests
// FraudAlertWidget Tests
// PayoutQueueWidget Tests
it('PayoutQueueWidget table heading includes pending count', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'WIDG-' . Str::uuid(),
        'name' => 'Widget Test Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    AffiliatePayout::create([
        'reference' => 'PAY-WID-' . Str::uuid(),
        'total_minor' => 5000,
        'currency' => 'USD',
        'status' => PendingPayout::class,
        'payee_type' => $affiliate->getMorphClass(),
        'payee_id' => $affiliate->getKey(),
    ]);

    $widget = new PayoutQueueWidget;
    $reflection = new ReflectionClass($widget);
    $method = $reflection->getMethod('getTableHeading');

    $heading = $method->invoke($widget);

    expect($heading)->toContain('Pending Payouts');
});

// UplineVisualizationWidget Tests
it('UplineVisualizationWidget can mount with affiliate id', function (): void {
    $widget = new UplineVisualizationWidget;
    $widget->mount('test-affiliate-id');

    expect($widget->affiliateId)->toBe('test-affiliate-id');
});

it('UplineVisualizationWidget can mount without affiliate id', function (): void {
    $widget = new UplineVisualizationWidget;
    $widget->mount(null);

    expect($widget->affiliateId)->toBeNull();
});

it('UplineVisualizationWidget returns empty network data for non-existent affiliate', function (): void {
    $widget = new UplineVisualizationWidget;
    $widget->mount('non-existent-id');

    expect($widget->getUplineData())->toBeEmpty();
});

it('UplineVisualizationWidget returns network stats', function (): void {
    Affiliate::create([
        'code' => 'NET-' . Str::uuid(),
        'name' => 'Network Test Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    $widget = new UplineVisualizationWidget;
    $stats = $widget->getUplineStats();

    expect($stats)
        ->toBeArray()
        ->toHaveKey('total_affiliates')
        ->toHaveKey('active_affiliates')
        ->toHaveKey('max_depth')
        ->toHaveKey('avg_children');
});

it('UplineVisualizationWidget returns root affiliates when no affiliate_id', function (): void {
    Affiliate::create([
        'code' => 'ROOT-' . Str::uuid(),
        'name' => 'Root Affiliate',
        'status' => Active::class,
        'parent_affiliate_id' => null,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    $widget = new UplineVisualizationWidget;
    $widget->mount(null);
    $data = $widget->getUplineData();

    expect($data)->toBeArray()
        ->and(count($data))->toBeGreaterThanOrEqual(1);
});
