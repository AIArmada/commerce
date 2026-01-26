<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Services\CartConditionResolver;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Vouchers\Conditions\VoucherCondition;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Exceptions\InvalidVoucherException;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\States\Active;
use AIArmada\Vouchers\Support\CartWithVouchers;
use Illuminate\Support\Facades\Config;

it('retrieves applied vouchers from cart conditions', function (): void {
    $storage = new InMemoryStorage;

    $cart = new Cart(
        storage: $storage,
        identifier: 'vouchers-test-user',
        events: null,
        instanceName: 'default',
        eventsEnabled: false,
        conditionResolver: new CartConditionResolver
    );

    $voucherData = VoucherData::fromArray([
        'id' => 42,
        'code' => 'STACK10',
        'name' => 'Stackable Voucher',
        'type' => VoucherType::Fixed->value,
        'value' => 25,
        'currency' => 'USD',
        'status' => Active::class,
    ]);

    $voucherCondition = new VoucherCondition($voucherData, order: 90, dynamic: false);
    $cart->addCondition($voucherCondition);

    $wrapper = new CartWithVouchers($cart);

    expect($wrapper->hasVoucher('STACK10'))->toBeTrue();

    $applied = $wrapper->getAppliedVouchers();

    expect($applied)->toHaveCount(1);

    /** @var VoucherCondition $appliedVoucher */
    $appliedVoucher = $applied[0];

    expect($appliedVoucher->getVoucherCode())->toBe('STACK10')
        ->and($wrapper->getAppliedVoucherCodes())->toBe(['STACK10']);

    // Test delegation to underlying cart
    expect($wrapper->getIdentifier())->toBe('vouchers-test-user');
});

it('collects voucher conditions from cart conditions', function (): void {
    $storage = new InMemoryStorage;

    $cart = new Cart(
        storage: $storage,
        identifier: 'cart-condition-test',
        events: null,
        instanceName: 'default',
        eventsEnabled: false,
        conditionResolver: new CartConditionResolver
    );

    $voucherData = VoucherData::fromArray([
        'id' => 43,
        'code' => 'CARTCOND',
        'name' => 'Cart Condition Test',
        'type' => VoucherType::Fixed->value,
        'value' => 25,
        'currency' => 'USD',
        'status' => Active::class,
    ]);

    // Add a CartCondition with type 'voucher'
    $cartCondition = new CartCondition(
        name: 'voucher_cartcond',
        type: 'voucher',
        target: 'cart@cart_subtotal/aggregate',
        value: '-25',
        attributes: [
            'voucher_id' => '43',
            'voucher_code' => 'CARTCOND',
            'voucher_type' => 'fixed',
            'description' => 'Cart Condition Test',
            'original_value' => 25,
            'voucher_data' => $voucherData->toArray(),
        ],
        order: 90
    );

    $cart->addCondition($cartCondition);

    $wrapper = new CartWithVouchers($cart);

    expect($wrapper->hasVoucher('CARTCOND'))->toBeTrue();

    $applied = $wrapper->getAppliedVouchers();

    expect($applied)->toHaveCount(1);
});

it('applies voucher successfully', function (): void {
    $storage = new InMemoryStorage;

    $cart = new Cart(
        storage: $storage,
        identifier: 'apply-test',
        events: null,
        instanceName: 'default',
        eventsEnabled: false,
        conditionResolver: new CartConditionResolver
    );

    $wrapper = new CartWithVouchers($cart);

    // Create a voucher
    $voucher = Voucher::create([
        'code' => 'APPLYTEST',
        'name' => 'Apply Test',
        'type' => 'percentage',
        'value' => 10,
        'currency' => 'MYR',
        'status' => Active::class,
    ]);

    $result = $wrapper->applyVoucher('applytest');

    expect($result)->toBe($wrapper)
        ->and($wrapper->hasVoucher('APPLYTEST'))->toBeTrue();
});

it('fails to apply invalid voucher code', function (): void {
    $storage = new InMemoryStorage;

    $cart = new Cart(
        storage: $storage,
        identifier: 'invalid-apply',
        events: null,
        instanceName: 'default',
        eventsEnabled: false,
        conditionResolver: new CartConditionResolver
    );

    $wrapper = new CartWithVouchers($cart);

    expect(fn () => $wrapper->applyVoucher('INVALID'))->toThrow(InvalidVoucherException::class);
});

it('removes voucher successfully', function (): void {
    $storage = new InMemoryStorage;

    $cart = new Cart(
        storage: $storage,
        identifier: 'remove-test',
        events: null,
        instanceName: 'default',
        eventsEnabled: false,
        conditionResolver: new CartConditionResolver
    );

    $wrapper = new CartWithVouchers($cart);

    // Add a voucher first
    $voucher = Voucher::create([
        'code' => 'REMOVETEST',
        'name' => 'Remove Test',
        'type' => 'percentage',
        'value' => 10,
        'currency' => 'MYR',
        'status' => Active::class,
    ]);

    $wrapper->applyVoucher('removetest');

    expect($wrapper->hasVoucher('REMOVETEST'))->toBeTrue();

    $result = $wrapper->removeVoucher('removetest');

    expect($result)->toBe($wrapper)
        ->and($wrapper->hasVoucher('REMOVETEST'))->toBeFalse();
});

it('removes non-existent voucher gracefully', function (): void {
    $storage = new InMemoryStorage;

    $cart = new Cart(
        storage: $storage,
        identifier: 'remove-nonexist',
        events: null,
        instanceName: 'default',
        eventsEnabled: false,
        conditionResolver: new CartConditionResolver
    );

    $wrapper = new CartWithVouchers($cart);

    $result = $wrapper->removeVoucher('NONEXISTENT');

    expect($result)->toBe($wrapper);
});

it('calculates voucher discount', function (): void {
    $storage = new InMemoryStorage;

    $cart = new Cart(
        storage: $storage,
        identifier: 'discount-test',
        events: null,
        instanceName: 'default',
        eventsEnabled: false,
        conditionResolver: new CartConditionResolver
    );

    $wrapper = new CartWithVouchers($cart);

    // Add a voucher
    $voucher = Voucher::create([
        'code' => 'DISCOUNTTEST',
        'name' => 'Discount Test',
        'type' => 'fixed',
        'value' => 50,
        'currency' => 'MYR',
        'status' => Active::class,
    ]);

    // Add an item to the cart
    $cart->add([
        'id' => 'item1',
        'name' => 'Test Item',
        'price' => 100,
        'quantity' => 1,
        'attributes' => [],
    ]);

    $wrapper->applyVoucher('discounttest');

    $discount = $wrapper->getVoucherDiscount();

    expect($discount)->toBe(50.0);
});

it('checks if can add voucher', function (): void {
    $storage = new InMemoryStorage;

    $cart = new Cart(
        storage: $storage,
        identifier: 'can-add-test',
        events: null,
        instanceName: 'default',
        eventsEnabled: false,
        conditionResolver: new CartConditionResolver
    );

    $wrapper = new CartWithVouchers($cart);

    expect($wrapper->canAddVoucher())->toBeTrue();

    // Test with max vouchers disabled
    Config::set('vouchers.cart.max_vouchers_per_cart', 0);
    expect($wrapper->canAddVoucher())->toBeFalse();
    Config::set('vouchers.cart.max_vouchers_per_cart', 1);
});

it('validates applied vouchers', function (): void {
    $storage = new InMemoryStorage;

    $cart = new Cart(
        storage: $storage,
        identifier: 'validate-test',
        events: null,
        instanceName: 'default',
        eventsEnabled: false,
        conditionResolver: new CartConditionResolver
    );

    $wrapper = new CartWithVouchers($cart);

    // Add a voucher
    $voucher = Voucher::create([
        'code' => 'INVALIDATE',
        'name' => 'Invalidate Test',
        'type' => 'percentage',
        'value' => 10,
        'currency' => 'MYR',
        'status' => Active::class,
    ]);

    $wrapper->applyVoucher('invalidate');

    expect($wrapper->hasVoucher('INVALIDATE'))->toBeTrue();

    // Make voucher invalid by changing status
    $voucher->update(['status' => 'expired']);

    // Validate - should remove because voucher is expired
    $removed = $wrapper->validateAppliedVouchers();

    expect($removed)->toBe(['INVALIDATE'])
        ->and($wrapper->hasVoucher('INVALIDATE'))->toBeFalse();
});

it('calculates voucher discount with stacking', function (): void {
    Config::set('vouchers.cart.allow_stacking', true);
    Config::set('vouchers.cart.max_vouchers_per_cart', 2);

    $storage = new InMemoryStorage;

    $cart = new Cart(
        storage: $storage,
        identifier: 'stacking-test',
        events: null,
        instanceName: 'default',
        eventsEnabled: false,
        conditionResolver: new CartConditionResolver
    );

    $wrapper = new CartWithVouchers($cart);

    // Add an item
    $cart->add([
        'id' => 'item1',
        'name' => 'Test Item',
        'price' => 200,
        'quantity' => 1,
        'attributes' => [],
    ]);

    // Add two vouchers
    $voucher1 = Voucher::create([
        'code' => 'STACK1',
        'name' => 'Stack 1',
        'type' => 'fixed',
        'value' => 20,
        'currency' => 'MYR',
        'status' => Active::class,
    ]);

    $voucher2 = Voucher::create([
        'code' => 'STACK2',
        'name' => 'Stack 2',
        'type' => 'fixed',
        'value' => 30,
        'currency' => 'MYR',
        'status' => Active::class,
    ]);

    $wrapper->applyVoucher('stack1');
    $wrapper->applyVoucher('stack2');

    $discount = $wrapper->getVoucherDiscount();

    expect($discount)->toBe(50.0); // 20 + 30
});

it('tests cart with vouchers get underlying cart', function (): void {
    $storage = new InMemoryStorage;

    $cart = new Cart(
        storage: $storage,
        identifier: 'underlying-test',
        events: null,
        instanceName: 'default',
        eventsEnabled: false,
        conditionResolver: new CartConditionResolver
    );

    $wrapper = new CartWithVouchers($cart);

    expect($wrapper->getCart())->toBe($cart);
});

it('removes static voucher condition', function (): void {
    $storage = new InMemoryStorage;

    $cart = new Cart(
        storage: $storage,
        identifier: 'static-remove',
        events: null,
        instanceName: 'default',
        eventsEnabled: false,
        conditionResolver: new CartConditionResolver
    );

    $voucherData = VoucherData::fromArray([
        'id' => 44,
        'code' => 'STATICREMOVE',
        'name' => 'Static Remove',
        'type' => VoucherType::Fixed->value,
        'value' => 25,
        'currency' => 'USD',
        'status' => Active::class,
    ]);

    $voucherCondition = new VoucherCondition($voucherData, order: 90, dynamic: false);
    $cart->addCondition($voucherCondition);

    $wrapper = new CartWithVouchers($cart);

    expect($wrapper->hasVoucher('STATICREMOVE'))->toBeTrue();

    $result = $wrapper->removeVoucher('staticremove');

    expect($result)->toBe($wrapper)
        ->and($wrapper->hasVoucher('STATICREMOVE'))->toBeFalse();
});
