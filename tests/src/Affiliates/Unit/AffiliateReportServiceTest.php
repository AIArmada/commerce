<?php

declare(strict_types=1);

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Services\AffiliateReportService;
use AIArmada\Affiliates\Services\AffiliateService;
use AIArmada\Affiliates\States\Active;
use AIArmada\Cart\Facades\Cart;

beforeEach(function (): void {
    $this->affiliate = Affiliate::create([
        'code' => 'REPORT-1',
        'name' => 'Reporter',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 100,
        'currency' => 'USD',
    ]);

    Cart::attachAffiliate($this->affiliate->code);
});

test('affiliate report service summarizes totals and utm', function (): void {
    $service = app(AffiliateService::class);

    $cart = app('cart')->getCurrentCart();

    $service->recordConversion($cart, [
        'order_reference' => 'RPT-1',
        'subtotal' => 1000,
        'metadata' => ['source' => 'newsletter', 'campaign' => 'spring'],
    ]);

    $service->recordConversion($cart, [
        'order_reference' => 'RPT-2',
        'subtotal' => 2000,
        'metadata' => ['source' => 'ads', 'campaign' => 'spring'],
    ]);

    $summary = app(AffiliateReportService::class)->affiliateSummary($this->affiliate->getKey());

    expect($summary['totals']['commission_minor'])->toBe(30)
        ->and($summary['totals']['conversions'])->toBe(2)
        ->and($summary['utm']['campaigns']['spring'])->toBe(2);
});
