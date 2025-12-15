<?php

declare(strict_types=1);

use AIArmada\FilamentAffiliates\FilamentAffiliatesPlugin;
use AIArmada\FilamentAffiliates\Policies\AffiliatePayoutPolicy;
use AIArmada\FilamentAffiliates\Services\AffiliateStatsAggregator;
use AIArmada\FilamentAffiliates\Services\PayoutExportService;
use AIArmada\FilamentAffiliates\Support\Integrations\CartBridge;
use AIArmada\FilamentAffiliates\Support\Integrations\VoucherBridge;

it('registers plugin as singleton', function (): void {
    $plugin1 = app(FilamentAffiliatesPlugin::class);
    $plugin2 = app(FilamentAffiliatesPlugin::class);

    expect($plugin1)->toBe($plugin2);
});

it('registers AffiliateStatsAggregator as singleton', function (): void {
    $service1 = app(AffiliateStatsAggregator::class);
    $service2 = app(AffiliateStatsAggregator::class);

    expect($service1)->toBe($service2);
});

it('registers CartBridge as singleton', function (): void {
    $bridge1 = app(CartBridge::class);
    $bridge2 = app(CartBridge::class);

    expect($bridge1)->toBe($bridge2);
});

it('registers VoucherBridge as singleton', function (): void {
    $bridge1 = app(VoucherBridge::class);
    $bridge2 = app(VoucherBridge::class);

    expect($bridge1)->toBe($bridge2);
});

it('registers PayoutExportService as singleton', function (): void {
    $service1 = app(PayoutExportService::class);
    $service2 = app(PayoutExportService::class);

    expect($service1)->toBe($service2);
});

it('registers AffiliatePayoutPolicy in Gate', function (): void {
    $policy = app(AffiliatePayoutPolicy::class);

    expect($policy)->toBeInstanceOf(AffiliatePayoutPolicy::class);
});
