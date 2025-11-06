<?php

declare(strict_types=1);

use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Vouchers\Conditions\VoucherCondition;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Enums\VoucherType;

it('converts voucher condition to cart condition and back', function (): void {
    $voucherData = VoucherData::fromArray([
        'id' => 1,
        'code' => 'TEST10',
        'name' => 'Test Voucher',
        'type' => VoucherType::Percentage->value,
        'value' => 10,
        'currency' => 'USD',
        'status' => VoucherStatus::Active->value,
    ]);

    $voucherCondition = new VoucherCondition($voucherData, order: 75, dynamic: false);

    $cartCondition = $voucherCondition->toCartCondition();

    expect($cartCondition)->toBeInstanceOf(CartCondition::class)
        ->and($cartCondition->getType())->toBe('voucher')
        ->and($cartCondition->getAttributes()['voucher_code'] ?? null)->toBe('TEST10')
        ->and($cartCondition->getAttributes()['voucher_data']['code'] ?? null)->toBe('TEST10');

    $rehydrated = VoucherCondition::fromCartCondition($cartCondition);

    expect($rehydrated)->toBeInstanceOf(VoucherCondition::class);

    /** @var VoucherCondition $rehydratedVoucher */
    $rehydratedVoucher = $rehydrated;

    expect($rehydratedVoucher->getVoucherCode())->toBe('TEST10')
        ->and($rehydratedVoucher->getOrder())->toBe($cartCondition->getOrder());
});
