<?php

declare(strict_types=1);

use AIArmada\Affiliates\Data\AffiliateData;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\AffiliateStatus;

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
