<?php

declare(strict_types=1);

use AIArmada\Affiliates\Data\AffiliateAttributionData;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\States\Active;
use Carbon\Carbon;

test('AffiliateAttributionData constructor sets properties', function (): void {
    $expiresAt = Carbon::tomorrow();
    $data = new AffiliateAttributionData(
        id: '1',
        affiliateId: 'aff1',
        affiliateCode: 'AFF1',
        subjectType: 'event',
        subjectIdentifier: 'subject1',
        subjectInstance: 'page',
        subjectTitleSnapshot: 'Ramadan Night',
        cartIdentifier: 'cart1',
        cartInstance: 'instance1',
        cookieValue: 'cookie123',
        voucherCode: 'VOUCHER',
        source: 'google',
        medium: 'cpc',
        campaign: 'summer',
        expiresAt: $expiresAt,
        ownerType: 'users',
        ownerId: 'owner-1',
        metadata: ['key' => 'value'],
    );

    expect($data->id)->toBe('1');
    expect($data->affiliateId)->toBe('aff1');
    expect($data->affiliateCode)->toBe('AFF1');
    expect($data->subjectType)->toBe('event');
    expect($data->subjectIdentifier)->toBe('subject1');
    expect($data->subjectInstance)->toBe('page');
    expect($data->subjectTitleSnapshot)->toBe('Ramadan Night');
    expect($data->cartIdentifier)->toBe('cart1');
    expect($data->cartInstance)->toBe('instance1');
    expect($data->cookieValue)->toBe('cookie123');
    expect($data->voucherCode)->toBe('VOUCHER');
    expect($data->source)->toBe('google');
    expect($data->medium)->toBe('cpc');
    expect($data->campaign)->toBe('summer');
    expect($data->expiresAt)->toBe($expiresAt);
    expect($data->ownerType)->toBe('users');
    expect($data->ownerId)->toBe('owner-1');
    expect($data->metadata)->toBe(['key' => 'value']);
});

test('AffiliateAttributionData fromModel creates data from attribution', function (): void {
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
        'subject_type' => 'event',
        'subject_identifier' => 'event:1',
        'subject_instance' => 'share',
        'subject_title_snapshot' => 'Ramadan Night',
        'cart_identifier' => 'cart1',
        'cart_instance' => 'instance1',
        'cookie_value' => 'cookie123',
        'voucher_code' => 'VOUCHER',
        'source' => 'google',
        'medium' => 'cpc',
        'campaign' => 'summer',
        'owner_type' => 'users',
        'owner_id' => 'owner-1',
        'expires_at' => Carbon::tomorrow(),
        'metadata' => ['key' => 'value'],
    ]);

    $data = AffiliateAttributionData::fromModel($attribution);

    expect($data->id)->toBe($attribution->id);
    expect($data->affiliateId)->toBe($affiliate->id);
    expect($data->affiliateCode)->toBe('AFF1');
    expect($data->subjectType)->toBe('event');
    expect($data->subjectIdentifier)->toBe('event:1');
    expect($data->subjectInstance)->toBe('share');
    expect($data->subjectTitleSnapshot)->toBe('Ramadan Night');
    expect($data->cartIdentifier)->toBe('cart1');
    expect($data->cartInstance)->toBe('instance1');
    expect($data->cookieValue)->toBe('cookie123');
    expect($data->voucherCode)->toBe('VOUCHER');
    expect($data->source)->toBe('google');
    expect($data->medium)->toBe('cpc');
    expect($data->campaign)->toBe('summer');
    expect($data->expiresAt)->toBeInstanceOf(Carbon::class);
    expect($data->ownerType)->toBe('users');
    expect($data->ownerId)->toBe('owner-1');
    expect($data->metadata)->toBe(['key' => 'value']);
});

test('AffiliateAttributionData toArray returns array representation', function (): void {
    $expiresAt = Carbon::tomorrow();
    $data = new AffiliateAttributionData(
        id: '1',
        affiliateId: 'aff1',
        affiliateCode: 'AFF1',
        subjectType: 'event',
        subjectIdentifier: 'subject1',
        subjectInstance: 'page',
        subjectTitleSnapshot: 'Ramadan Night',
        cartIdentifier: 'cart1',
        cartInstance: 'instance1',
        cookieValue: 'cookie123',
        voucherCode: 'VOUCHER',
        source: 'google',
        medium: 'cpc',
        campaign: 'summer',
        expiresAt: $expiresAt,
        ownerType: 'users',
        ownerId: 'owner-1',
        metadata: ['key' => 'value'],
    );

    $array = $data->toArray();

    expect($array)->toBe([
        'id' => '1',
        'affiliate_id' => 'aff1',
        'affiliate_code' => 'AFF1',
        'subject_type' => 'event',
        'subject_identifier' => 'subject1',
        'subject_instance' => 'page',
        'subject_title_snapshot' => 'Ramadan Night',
        'cart_identifier' => 'cart1',
        'cart_instance' => 'instance1',
        'cookie_value' => 'cookie123',
        'voucher_code' => 'VOUCHER',
        'source' => 'google',
        'medium' => 'cpc',
        'campaign' => 'summer',
        'expires_at' => $expiresAt->format('c'),
        'owner_type' => 'users',
        'owner_id' => 'owner-1',
        'metadata' => ['key' => 'value'],
    ]);
});

test('AffiliateAttributionData toArray handles null expiresAt', function (): void {
    $data = new AffiliateAttributionData(
        id: '1',
        affiliateId: 'aff1',
        affiliateCode: 'AFF1',
        subjectIdentifier: 'subject1',
        subjectInstance: 'page',
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
