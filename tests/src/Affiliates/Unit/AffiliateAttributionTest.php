<?php

declare(strict_types=1);

use AIArmada\Affiliates\Features\Attribution\Api\AttributionActivity;
use AIArmada\Affiliates\Features\Attribution\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\States\Active;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

test('Attribution activity updates last_seen_at', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'AFF1',
        'name' => 'Test Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    $attribution = AffiliateAttribution::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => 'AFF1',
        'cart_identifier' => 'cart1',
        'last_seen_at' => Carbon::yesterday(),
        'cookie_value' => 'refresh-last-seen',
    ]);

    app(AttributionActivity::class)->touch('refresh-last-seen');

    $attribution->refresh();

    expect($attribution->last_seen_at)->toBeInstanceOf(CarbonImmutable::class);
    expect($attribution->last_seen_at->isToday())->toBeTrue();
});
