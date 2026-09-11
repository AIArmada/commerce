<?php

declare(strict_types=1);

use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Exceptions\InvalidVoucherDataException;
use AIArmada\Vouchers\States\Active;

it('rejects floats for every integer monetary or limit field at the DTO boundary', function (): void {
    foreach ([
        'value',
        'min_cart_value',
        'max_discount',
        'credit_delay_hours',
        'usage_limit',
        'usage_limit_per_user',
        'affiliate_commission_value',
    ] as $field) {
        expect(fn () => VoucherData::fromArray([
            'id' => 'voucher-id',
            'code' => 'FLOAT-TEST',
            'name' => 'Float Test',
            'type' => VoucherType::Fixed->value,
            'value' => $field === 'value' ? 100.5 : 100,
            'currency' => 'MYR',
            'status' => Active::class,
            $field => 100.5,
        ]))->toThrow(InvalidVoucherDataException::class);
    }
});

it('rejects float aliases at the DTO boundary as well', function (): void {
    expect(fn () => VoucherData::fromArray([
        'id' => 'voucher-id',
        'code' => 'FLOAT-ALIAS',
        'name' => 'Float Alias',
        'type' => VoucherType::Fixed->value,
        'value' => 100,
        'currency' => 'MYR',
        'status' => Active::class,
        'minCartValue' => 100.5,
    ]))->toThrow(InvalidVoucherDataException::class);
});
