<?php

declare(strict_types=1);

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

test('AffiliateAttribution has affiliate relationship', function (): void {
    $attribution = new AffiliateAttribution;

    expect($attribution->affiliate())->toBeInstanceOf(BelongsTo::class);
});

test('AffiliateAttribution has conversions relationship', function (): void {
    $attribution = new AffiliateAttribution;

    expect($attribution->conversions())->toBeInstanceOf(HasMany::class);
});

test('AffiliateAttribution has touchpoints relationship', function (): void {
    $attribution = new AffiliateAttribution;

    expect($attribution->touchpoints())->toBeInstanceOf(HasMany::class);
});

test('AffiliateAttribution scopeActive filters expired attributions', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'AFF1',
        'name' => 'Test Affiliate',
        'status' => 'active',
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    $active = AffiliateAttribution::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => 'AFF1',
        'cart_identifier' => 'cart1',
        'expires_at' => Carbon::tomorrow(),
    ]);

    $expired = AffiliateAttribution::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => 'AFF1',
        'cart_identifier' => 'cart2',
        'expires_at' => Carbon::yesterday(),
    ]);

    $noExpiry = AffiliateAttribution::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => 'AFF1',
        'cart_identifier' => 'cart3',
    ]);

    $activeResults = AffiliateAttribution::active()->pluck('id');

    expect($activeResults)->toContain($active->id);
    expect($activeResults)->toContain($noExpiry->id);
    expect($activeResults)->not->toContain($expired->id);
});

test('AffiliateAttribution refreshLastSeen updates last_seen_at', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'AFF1',
        'name' => 'Test Affiliate',
        'status' => 'active',
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    $attribution = AffiliateAttribution::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => 'AFF1',
        'cart_identifier' => 'cart1',
        'last_seen_at' => Carbon::yesterday(),
    ]);

    $attribution->refreshLastSeen();

    $attribution->refresh();

    expect($attribution->last_seen_at)->toBeInstanceOf(Carbon::class);
    expect($attribution->last_seen_at->isToday())->toBeTrue();
});
