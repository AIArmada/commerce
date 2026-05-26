<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Services\VoucherDiscountCalculator;
use AIArmada\Vouchers\States\Active;

it('calculates percentage discounts using basis points', function (): void {
    $calculator = app(VoucherDiscountCalculator::class);
    $voucher = VoucherData::fromArray([
        'id' => 'voucher-percent',
        'code' => 'PERCENT1250',
        'name' => '12.5% Off',
        'type' => VoucherType::Percentage->value,
        'value' => 1250,
        'currency' => 'USD',
        'status' => Active::class,
    ]);

    expect($calculator->calculate($voucher, 10000))->toBe(1250);
});

it('applies max discount caps for simple vouchers', function (): void {
    $calculator = app(VoucherDiscountCalculator::class);
    $voucher = VoucherData::fromArray([
        'id' => 'voucher-capped',
        'code' => 'CAP50',
        'name' => '50% Off up to $12',
        'type' => VoucherType::Percentage->value,
        'value' => 5000,
        'max_discount' => 1200,
        'currency' => 'USD',
        'status' => Active::class,
    ]);

    expect($calculator->calculate($voucher, 10000))->toBe(1200);
});

it('calculates buy x get y discounts against the cart', function (): void {
    $calculator = app(VoucherDiscountCalculator::class);
    $cart = new Cart(new InMemoryStorage, 'voucher-discount-calculator-bogo');

    $cart->add('SHIRT-001', 'Shirt', 2000, 3, ['sku' => 'SHIRT-001']);

    $voucher = VoucherData::fromArray([
        'id' => 'voucher-bogo',
        'code' => 'BOGO2GET1',
        'name' => 'Buy 2 Get 1 Free',
        'type' => VoucherType::BuyXGetY->value,
        'value' => 0,
        'value_config' => [
            'buy' => [
                'quantity' => 2,
                'product_matcher' => ['type' => 'sku', 'skus' => ['SHIRT-001']],
            ],
            'get' => [
                'quantity' => 1,
                'discount' => '100%',
                'selection' => 'cheapest',
                'product_matcher' => ['type' => 'same_as_buy'],
            ],
        ],
        'currency' => 'USD',
        'status' => Active::class,
    ]);

    expect($calculator->calculate($voucher, 6000, $cart))->toBe(2000);
});
