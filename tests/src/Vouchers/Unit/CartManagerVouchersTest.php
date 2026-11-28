<?php

declare(strict_types=1);

use AIArmada\Cart\Facades\Cart;
use AIArmada\Cart\Runtime\CartManagerInterface;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Data\VoucherType;
use AIArmada\Vouchers\Data\VoucherValidationResult;
use AIArmada\Vouchers\Exceptions\InvalidVoucherException;
use AIArmada\Vouchers\Features\CartIntegration\Api\VoucherValidation;
use AIArmada\Vouchers\Features\CartIntegration\EntryPoints\CartManagerWithVouchers;
use AIArmada\Vouchers\Features\CartIntegration\EntryPoints\CartWithVouchers;
use AIArmada\Vouchers\Stacking\StackingPolicy;

beforeEach(function (): void {
    $manager = app(CartManagerInterface::class);

    if (! $manager instanceof CartManagerWithVouchers) {
        $proxy = CartManagerWithVouchers::fromCartManager($manager);

        Cart::swap($proxy);
        app()->instance('cart', $proxy);
    }

    Cart::clear();
    $this->validator = Mockery::mock(VoucherValidation::class);
    app()->instance(VoucherValidation::class, $this->validator);
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
        'status' => 'active',
    ]);

    $this->validator->shouldReceive('validate')
        ->atLeast()
        ->once()
        ->andReturn(VoucherValidationResult::valid(['voucher' => $voucherData->toArray()]));

    $result = Cart::applyVoucher('STACK10');

    expect($result)->toBeInstanceOf(CartWithVouchers::class);

    expect(Cart::hasVoucher('STACK10'))->toBeTrue();
    expect(Cart::getAppliedVoucherCodes())->toBe(['STACK10']);
});

it('rejects vouchers when stacking is disabled even if auto replacement is enabled', function (): void {
    $voucherData = VoucherData::fromArray([
        'id' => 'blocked',
        'code' => 'BLOCKED',
        'name' => 'Blocked',
        'type' => 'fixed',
        'value' => 100,
        'currency' => 'USD',
    ]);

    $this->validator->shouldReceive('validate')->once()->andReturn(VoucherValidationResult::valid(['voucher' => $voucherData->toArray()]));

    $cart = new CartWithVouchers(Cart::getCurrentCart());
    $cart->setStackingPolicy(new StackingPolicy(
        rules: [['type' => 'max_vouchers', 'value' => 0]],
        autoReplace: true,
    ));

    expect(fn () => $cart->applyVoucher('BLOCKED'))
        ->toThrow(InvalidVoucherException::class);
    expect($cart->hasVoucher())->toBeFalse();
});

it('removes a cart voucher when its code resolves to a different voucher identity', function (): void {
    $original = VoucherData::fromArray([
        'id' => 'original-voucher',
        'code' => 'REUSED',
        'name' => 'Original',
        'type' => 'fixed',
        'value' => 100,
        'currency' => 'USD',
    ]);

    $replacement = VoucherData::fromArray([
        ...$original->toArray(),
        'id' => 'replacement-voucher',
    ]);

    $current = $original;
    $this->validator->shouldReceive('validate')
        ->atLeast()
        ->twice()
        ->andReturnUsing(function () use (&$current): VoucherValidationResult {
            return VoucherValidationResult::valid(['voucher' => $current->toArray()]);
        });

    $cart = new CartWithVouchers(Cart::getCurrentCart());
    $cart->applyVoucher('REUSED');
    $current = $replacement;

    expect($cart->validateAppliedVouchers())->toBe(['REUSED'])
        ->and($cart->hasVoucher('REUSED'))->toBeFalse();
});

it('removes an applied voucher when its discount terms change without changing its identity', function (): void {
    $original = VoucherData::fromArray([
        'id' => 'same-voucher',
        'code' => 'CHANGED',
        'name' => 'Original',
        'type' => 'fixed',
        'value' => 100,
        'currency' => 'USD',
    ]);

    $updated = VoucherData::fromArray([
        ...$original->toArray(),
        'value' => 50,
    ]);

    $current = $original;
    $this->validator->shouldReceive('validate')
        ->atLeast()
        ->twice()
        ->andReturnUsing(function () use (&$current): VoucherValidationResult {
            return VoucherValidationResult::valid(['voucher' => $current->toArray()]);
        });

    $cart = new CartWithVouchers(Cart::getCurrentCart());
    $cart->applyVoucher('CHANGED');
    $current = $updated;

    expect($cart->validateAppliedVouchers())->toBe(['CHANGED'])
        ->and($cart->hasVoucher('CHANGED'))->toBeFalse();
});
