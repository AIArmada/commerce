<?php

declare(strict_types=1);

use AIArmada\Affiliates\Data\AffiliateData;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\AffiliateStatus;

test('AffiliateData constructor sets properties', function (): void {
    $data = new AffiliateData(
        id: '1',
        code: 'AFF1',
        name: 'Test Affiliate',
        status: AffiliateStatus::fromString(Active::class),
        commissionType: CommissionType::Percentage,
        commissionRate: 500,
        currency: 'USD',
        defaultVoucherCode: 'VOUCHER',
        metadata: ['key' => 'value'],
    );

    expect($data->id)->toBe('1');
    expect($data->code)->toBe('AFF1');
    expect($data->name)->toBe('Test Affiliate');
    expect($data->status->equals(Active::class))->toBeTrue();
    expect($data->commissionType)->toBe(CommissionType::Percentage);
    expect($data->commissionRate)->toBe(500);
    expect($data->currency)->toBe('USD');
    expect($data->defaultVoucherCode)->toBe('VOUCHER');
    expect($data->metadata)->toBe(['key' => 'value']);
});

test('AffiliateData fromModel creates data from affiliate', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'AFF1',
        'name' => 'Test Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
        'default_voucher_code' => 'VOUCHER',
        'metadata' => ['key' => 'value'],
    ]);

    $data = AffiliateData::fromModel($affiliate);

    expect($data->id)->toBe($affiliate->id);
    expect($data->code)->toBe('AFF1');
    expect($data->name)->toBe('Test Affiliate');
    expect($data->status->equals(Active::class))->toBeTrue();
    expect($data->commissionType)->toBe(CommissionType::Percentage);
    expect($data->commissionRate)->toBe(500);
    expect($data->currency)->toBe('USD');
    expect($data->defaultVoucherCode)->toBe('VOUCHER');
    expect($data->metadata)->toBe(['key' => 'value']);
});

test('AffiliateData toArray returns array representation', function (): void {
    $data = new AffiliateData(
        id: '1',
        code: 'AFF1',
        name: 'Test Affiliate',
        status: AffiliateStatus::fromString(Active::class),
        commissionType: CommissionType::Percentage,
        commissionRate: 500,
        currency: 'USD',
        defaultVoucherCode: 'VOUCHER',
        metadata: ['key' => 'value'],
    );

    $array = $data->toArray();

    expect($array['status'])->toBeInstanceOf(Active::class);
    expect($array['status']->equals(Active::class))->toBeTrue();
    expect($array)->toMatchArray([
        'id' => '1',
        'code' => 'AFF1',
        'name' => 'Test Affiliate',
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
        'default_voucher_code' => 'VOUCHER',
        'metadata' => ['key' => 'value'],
    ]);
});
