<?php

declare(strict_types=1);

use AIArmada\Affiliates\Data\AffiliateConversionData;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\ConversionStatus;
use Carbon\Carbon;

test('AffiliateConversionData constructor sets properties', function (): void {
    $occurredAt = Carbon::now();
    $data = new AffiliateConversionData(
        id: '1',
        affiliateId: 'aff1',
        affiliateCode: 'AFF1',
        subjectType: 'event',
        subjectIdentifier: 'event:1',
        subjectInstance: 'share',
        subjectTitleSnapshot: 'Ramadan Night',
        cartIdentifier: 'cart1',
        cartInstance: 'instance1',
        voucherCode: 'VOUCHER',
        externalReference: 'OUTCOME123',
        orderReference: 'ORD123',
        conversionType: 'registration',
        subtotalMinor: 1000,
        valueMinor: 1200,
        totalMinor: 1200,
        commissionMinor: 120,
        commissionCurrency: 'USD',
        status: ConversionStatus::fromString(ApprovedConversion::class),
        occurredAt: $occurredAt,
        ownerType: 'users',
        ownerId: 'owner-1',
        metadata: ['key' => 'value'],
    );

    expect($data->id)->toBe('1');
    expect($data->affiliateId)->toBe('aff1');
    expect($data->affiliateCode)->toBe('AFF1');
    expect($data->subjectType)->toBe('event');
    expect($data->subjectIdentifier)->toBe('event:1');
    expect($data->subjectInstance)->toBe('share');
    expect($data->subjectTitleSnapshot)->toBe('Ramadan Night');
    expect($data->cartIdentifier)->toBe('cart1');
    expect($data->cartInstance)->toBe('instance1');
    expect($data->voucherCode)->toBe('VOUCHER');
    expect($data->externalReference)->toBe('OUTCOME123');
    expect($data->orderReference)->toBe('ORD123');
    expect($data->conversionType)->toBe('registration');
    expect($data->subtotalMinor)->toBe(1000);
    expect($data->valueMinor)->toBe(1200);
    expect($data->totalMinor)->toBe(1200);
    expect($data->commissionMinor)->toBe(120);
    expect($data->commissionCurrency)->toBe('USD');
    expect($data->status->equals(ApprovedConversion::class))->toBeTrue();
    expect($data->occurredAt)->toBe($occurredAt);
    expect($data->ownerType)->toBe('users');
    expect($data->ownerId)->toBe('owner-1');
    expect($data->metadata)->toBe(['key' => 'value']);
});

test('AffiliateConversionData fromModel creates data from conversion', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'AFF1',
        'name' => 'Test Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    $conversion = AffiliateConversion::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => 'AFF1',
        'subject_type' => 'event',
        'subject_identifier' => 'event:1',
        'subject_instance' => 'share',
        'subject_title_snapshot' => 'Ramadan Night',
        'cart_identifier' => 'cart1',
        'cart_instance' => 'instance1',
        'voucher_code' => 'VOUCHER',
        'external_reference' => 'OUTCOME123',
        'order_reference' => 'ORD123',
        'subtotal_minor' => 1000,
        'value_minor' => 1200,
        'total_minor' => 1200,
        'commission_minor' => 120,
        'commission_currency' => 'USD',
        'conversion_type' => 'registration',
        'status' => ApprovedConversion::class,
        'occurred_at' => Carbon::now(),
        'owner_type' => 'users',
        'owner_id' => 'owner-1',
        'metadata' => ['key' => 'value'],
    ]);

    $data = AffiliateConversionData::fromModel($conversion);

    expect($data->id)->toBe($conversion->id);
    expect($data->affiliateId)->toBe($affiliate->id);
    expect($data->affiliateCode)->toBe('AFF1');
    expect($data->subjectType)->toBe('event');
    expect($data->subjectIdentifier)->toBe('event:1');
    expect($data->subjectInstance)->toBe('share');
    expect($data->subjectTitleSnapshot)->toBe('Ramadan Night');
    expect($data->cartIdentifier)->toBe('cart1');
    expect($data->cartInstance)->toBe('instance1');
    expect($data->voucherCode)->toBe('VOUCHER');
    expect($data->externalReference)->toBe('OUTCOME123');
    expect($data->orderReference)->toBe('ORD123');
    expect($data->subtotalMinor)->toBe(1000);
    expect($data->valueMinor)->toBe(1200);
    expect($data->totalMinor)->toBe(1200);
    expect($data->commissionMinor)->toBe(120);
    expect($data->commissionCurrency)->toBe('USD');
    expect($data->conversionType)->toBe('registration');
    expect($data->status->equals(ApprovedConversion::class))->toBeTrue();
    expect($data->occurredAt)->toBeInstanceOf(Carbon::class);
    expect($data->ownerType)->toBe('users');
    expect($data->ownerId)->toBe('owner-1');
    expect($data->metadata)->toBe(['key' => 'value']);
});

test('AffiliateConversionData toArray returns array representation', function (): void {
    $occurredAt = Carbon::now();
    $data = new AffiliateConversionData(
        id: '1',
        affiliateId: 'aff1',
        affiliateCode: 'AFF1',
        subjectType: 'event',
        subjectIdentifier: 'event:1',
        subjectInstance: 'share',
        subjectTitleSnapshot: 'Ramadan Night',
        cartIdentifier: 'cart1',
        cartInstance: 'instance1',
        voucherCode: 'VOUCHER',
        externalReference: 'OUTCOME123',
        orderReference: 'ORD123',
        subtotalMinor: 1000,
        valueMinor: 1200,
        totalMinor: 1200,
        commissionMinor: 120,
        commissionCurrency: 'USD',
        conversionType: 'registration',
        status: ConversionStatus::fromString(ApprovedConversion::class),
        occurredAt: $occurredAt,
        ownerType: 'users',
        ownerId: 'owner-1',
        metadata: ['key' => 'value'],
    );

    $array = $data->toArray();

    expect($array['status'])->toBeInstanceOf(ApprovedConversion::class);

    unset($array['status']);

    expect($array)->toBe([
        'id' => '1',
        'affiliate_id' => 'aff1',
        'affiliate_code' => 'AFF1',
        'subject_type' => 'event',
        'subject_identifier' => 'event:1',
        'subject_instance' => 'share',
        'subject_title_snapshot' => 'Ramadan Night',
        'cart_identifier' => 'cart1',
        'cart_instance' => 'instance1',
        'voucher_code' => 'VOUCHER',
        'external_reference' => 'OUTCOME123',
        'order_reference' => 'ORD123',
        'conversion_type' => 'registration',
        'subtotal_minor' => 1000,
        'value_minor' => 1200,
        'total_minor' => 1200,
        'commission_minor' => 120,
        'commission_currency' => 'USD',
        'occurred_at' => $occurredAt->format('c'),
        'owner_type' => 'users',
        'owner_id' => 'owner-1',
        'metadata' => ['key' => 'value'],
    ]);
});

test('AffiliateConversionData toArray handles null occurredAt', function (): void {
    $data = new AffiliateConversionData(
        id: '1',
        affiliateId: 'aff1',
        affiliateCode: 'AFF1',
        subjectIdentifier: 'event:1',
        subjectInstance: 'share',
        cartIdentifier: 'cart1',
        cartInstance: 'instance1',
        voucherCode: 'VOUCHER',
        externalReference: 'OUTCOME123',
        orderReference: 'ORD123',
        subtotalMinor: 1000,
        valueMinor: 1200,
        totalMinor: 1200,
        commissionMinor: 120,
        commissionCurrency: 'USD',
        conversionType: 'registration',
        status: ConversionStatus::fromString(ApprovedConversion::class),
        occurredAt: null,
        metadata: ['key' => 'value'],
    );

    $array = $data->toArray();

    expect($array['occurred_at'])->toBeNull();
});
