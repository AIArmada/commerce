<?php

declare(strict_types=1);

use AIArmada\Affiliates\Data\AffiliateAttributionData;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use Carbon\Carbon;

test('AffiliateAttributionData constructor sets properties', function (): void {
    $expiresAt = Carbon::tomorrow();
    $data = new AffiliateAttributionData(
        id: '1',
        affiliateId: 'aff1',
        affiliateCode: 'AFF1',
        cartIdentifier: 'cart1',
        cartInstance: 'instance1',
        cookieValue: 'cookie123',
        voucherCode: 'VOUCHER',
        source: 'google',
        medium: 'cpc',
        campaign: 'summer',
        expiresAt: $expiresAt,
        metadata: ['key' => 'value'],
    );

    expect($data->id)->toBe('1');
    expect($data->affiliateId)->toBe('aff1');
    expect($data->affiliateCode)->toBe('AFF1');
    expect($data->cartIdentifier)->toBe('cart1');
    expect($data->cartInstance)->toBe('instance1');
    expect($data->cookieValue)->toBe('cookie123');
    expect($data->voucherCode)->toBe('VOUCHER');
    expect($data->source)->toBe('google');
    expect($data->medium)->toBe('cpc');
    expect($data->campaign)->toBe('summer');
    expect($data->expiresAt)->toBe($expiresAt);
    expect($data->metadata)->toBe(['key' => 'value']);
});

test('AffiliateAttributionData fromModel creates data from attribution', function (): void {
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
        'cart_instance' => 'instance1',
        'cookie_value' => 'cookie123',
        'voucher_code' => 'VOUCHER',
        'source' => 'google',
        'medium' => 'cpc',
        'campaign' => 'summer',
        'expires_at' => Carbon::tomorrow(),
        'metadata' => ['key' => 'value'],
    ]);

    $data = AffiliateAttributionData::fromModel($attribution);

    expect($data->id)->toBe($attribution->id);
    expect($data->affiliateId)->toBe($affiliate->id);
    expect($data->affiliateCode)->toBe('AFF1');
    expect($data->cartIdentifier)->toBe('cart1');
    expect($data->cartInstance)->toBe('instance1');
    expect($data->cookieValue)->toBe('cookie123');
    expect($data->voucherCode)->toBe('VOUCHER');
    expect($data->source)->toBe('google');
    expect($data->medium)->toBe('cpc');
    expect($data->campaign)->toBe('summer');
    expect($data->expiresAt)->toBeInstanceOf(Carbon::class);
    expect($data->metadata)->toBe(['key' => 'value']);
});

test('AffiliateAttributionData toArray returns array representation', function (): void {
    $expiresAt = Carbon::tomorrow();
    $data = new AffiliateAttributionData(
        id: '1',
        affiliateId: 'aff1',
        affiliateCode: 'AFF1',
        cartIdentifier: 'cart1',
        cartInstance: 'instance1',
        cookieValue: 'cookie123',
        voucherCode: 'VOUCHER',
        source: 'google',
        medium: 'cpc',
        campaign: 'summer',
        expiresAt: $expiresAt,
        metadata: ['key' => 'value'],
    );

    $array = $data->toArray();

    expect($array)->toBe([
        'id' => '1',
        'affiliate_id' => 'aff1',
        'affiliate_code' => 'AFF1',
        'cart_identifier' => 'cart1',
        'cart_instance' => 'instance1',
        'cookie_value' => 'cookie123',
        'voucher_code' => 'VOUCHER',
        'source' => 'google',
        'medium' => 'cpc',
        'campaign' => 'summer',
        'expires_at' => $expiresAt->format('c'),
        'metadata' => ['key' => 'value'],
    ]);
});

test('AffiliateAttributionData toArray handles null expiresAt', function (): void {
    $data = new AffiliateAttributionData(
        id: '1',
        affiliateId: 'aff1',
        affiliateCode: 'AFF1',
        cartIdentifier: 'cart1',
        cartInstance: 'instance1',
        cookieValue: 'cookie123',
        voucherCode: 'VOUCHER',
        source: 'google',
        medium: 'cpc',
        campaign: 'summer',
        expiresAt: null,
        metadata: ['key' => 'value'],
    );

    $array = $data->toArray();

    expect($array['expires_at'])->toBeNull();
});