<?php

declare(strict_types=1);

use AIArmada\Affiliates\Data\AffiliateData;

test('AffiliateData toArray returns array representation', function (): void {
    $data = new AffiliateData(
        id: '1',
        code: 'AFF1',
        name: 'Test Affiliate',
        status: 'active',
        commissionType: 'percentage',
        commissionRate: 500,
        currency: 'USD',
        defaultVoucherCode: 'VOUCHER',
        metadata: ['key' => 'value'],
    );

    $array = $data->toArray();

    expect($array['status'])->toBe('active');
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
