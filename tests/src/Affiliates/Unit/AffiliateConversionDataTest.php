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
        cartIdentifier: 'cart1',
        cartInstance: 'instance1',
        voucherCode: 'VOUCHER',
        orderReference: 'ORD123',
        subtotalMinor: 1000,
        totalMinor: 1200,
        commissionMinor: 120,
        commissionCurrency: 'USD',
        status: ConversionStatus::fromString(ApprovedConversion::class),
        occurredAt: $occurredAt,
        metadata: ['key' => 'value'],
    );

    expect($data->id)->toBe('1');
    expect($data->affiliateId)->toBe('aff1');
    expect($data->affiliateCode)->toBe('AFF1');
    expect($data->cartIdentifier)->toBe('cart1');
    expect($data->cartInstance)->toBe('instance1');
    expect($data->voucherCode)->toBe('VOUCHER');
    expect($data->orderReference)->toBe('ORD123');
    expect($data->subtotalMinor)->toBe(1000);
    expect($data->totalMinor)->toBe(1200);
    expect($data->commissionMinor)->toBe(120);
    expect($data->commissionCurrency)->toBe('USD');
    expect($data->status->equals(ApprovedConversion::class))->toBeTrue();
    expect($data->occurredAt)->toBe($occurredAt);
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
        'cart_identifier' => 'cart1',
        'cart_instance' => 'instance1',
        'voucher_code' => 'VOUCHER',
        'order_reference' => 'ORD123',
        'subtotal_minor' => 1000,
        'total_minor' => 1200,
        'commission_minor' => 120,
        'commission_currency' => 'USD',
        'status' => ApprovedConversion::class,
        'occurred_at' => Carbon::now(),
        'metadata' => ['key' => 'value'],
    ]);

    $data = AffiliateConversionData::fromModel($conversion);

    expect($data->id)->toBe($conversion->id);
    expect($data->affiliateId)->toBe($affiliate->id);
    expect($data->affiliateCode)->toBe('AFF1');
    expect($data->cartIdentifier)->toBe('cart1');
    expect($data->cartInstance)->toBe('instance1');
    expect($data->voucherCode)->toBe('VOUCHER');
    expect($data->orderReference)->toBe('ORD123');
    expect($data->subtotalMinor)->toBe(1000);
    expect($data->totalMinor)->toBe(1200);
    expect($data->commissionMinor)->toBe(120);
    expect($data->commissionCurrency)->toBe('USD');
    expect($data->status->equals(ApprovedConversion::class))->toBeTrue();
    expect($data->occurredAt)->toBeInstanceOf(Carbon::class);
    expect($data->metadata)->toBe(['key' => 'value']);
});

test('AffiliateConversionData toArray returns array representation', function (): void {
    $occurredAt = Carbon::now();
    $data = new AffiliateConversionData(
        id: '1',
        affiliateId: 'aff1',
        affiliateCode: 'AFF1',
        cartIdentifier: 'cart1',
        cartInstance: 'instance1',
        voucherCode: 'VOUCHER',
        orderReference: 'ORD123',
        subtotalMinor: 1000,
        totalMinor: 1200,
        commissionMinor: 120,
        commissionCurrency: 'USD',
        status: ConversionStatus::fromString(ApprovedConversion::class),
        occurredAt: $occurredAt,
        metadata: ['key' => 'value'],
    );

    $array = $data->toArray();

    expect($array['status'])->toBeInstanceOf(ApprovedConversion::class);

    unset($array['status']);

    expect($array)->toBe([
        'id' => '1',
        'affiliate_id' => 'aff1',
        'affiliate_code' => 'AFF1',
        'cart_identifier' => 'cart1',
        'cart_instance' => 'instance1',
        'voucher_code' => 'VOUCHER',
        'order_reference' => 'ORD123',
        'subtotal_minor' => 1000,
        'total_minor' => 1200,
        'commission_minor' => 120,
        'commission_currency' => 'USD',
        'occurred_at' => $occurredAt->format('c'),
        'metadata' => ['key' => 'value'],
    ]);
});

test('AffiliateConversionData toArray handles null occurredAt', function (): void {
    $data = new AffiliateConversionData(
        id: '1',
        affiliateId: 'aff1',
        affiliateCode: 'AFF1',
        cartIdentifier: 'cart1',
        cartInstance: 'instance1',
        voucherCode: 'VOUCHER',
        orderReference: 'ORD123',
        subtotalMinor: 1000,
        totalMinor: 1200,
        commissionMinor: 120,
        commissionCurrency: 'USD',
        status: ConversionStatus::fromString(ApprovedConversion::class),
        occurredAt: null,
        metadata: ['key' => 'value'],
    );

    $array = $data->toArray();

    expect($array['occurred_at'])->toBeNull();
});
