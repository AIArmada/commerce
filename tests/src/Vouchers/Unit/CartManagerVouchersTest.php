<?php

declare(strict_types=1);

use AIArmada\Cart\CartManager;
use AIArmada\Cart\Facades\Cart;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Data\VoucherValidationResult;
use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Facades\Voucher;
use AIArmada\Vouchers\Support\CartManagerWithVouchers;
use AIArmada\Vouchers\Support\CartWithVouchers;

beforeEach(function (): void {
    $manager = app(CartManager::class);

    if (! $manager instanceof CartManagerWithVouchers) {
        $proxy = CartManagerWithVouchers::fromCartManager($manager);

        Cart::swap($proxy);
        app()->instance('cart', $proxy);
        app()->instance(CartManager::class, $proxy);
    }

    Cart::clear();
});

afterEach(function (): void {
    Cart::clear();
});

it('proxies voucher interactions via cart manager wrapper', function (): void {
    $voucherData = VoucherData::fromArray([
        'id' => 99,
        'code' => 'STACK10',
        'name' => 'Stackable Voucher',
        'type' => VoucherType::Percentage->value,
        'value' => 10,
        'currency' => 'USD',
        'status' => VoucherStatus::Active->value,
    ]);

    Voucher::shouldReceive('validate')
        ->atLeast()
        ->once()
        ->andReturn(VoucherValidationResult::valid());

    Voucher::shouldReceive('find')
        ->once()
        ->andReturn($voucherData);

    $result = Cart::applyVoucher('STACK10');

    expect($result)->toBeInstanceOf(CartWithVouchers::class);

    expect(Cart::hasVoucher('STACK10'))->toBeTrue();
    expect(Cart::getAppliedVoucherCodes())->toBe(['STACK10']);
});
