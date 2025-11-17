<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Conditions\Enums\ConditionApplication;
use AIArmada\Cart\Conditions\Enums\ConditionPhase;
use AIArmada\Cart\Conditions\Target;
use AIArmada\Cart\Testing\InMemoryStorage;

it('applies shipping conditions using shipment resolver data', function (): void {
    $storage = new InMemoryStorage();
    $cart = new Cart($storage, 'pipeline-test', events: null);

    $cart->add('sku-1', 'Sample Item', 50, 2); // subtotal 100

    $cart->resolveShipmentsUsing(function (): iterable {
        return [
            ['id' => 'domestic', 'base_amount' => 10.0],
            ['id' => 'express', 'base_amount' => 5.0],
        ];
    });

    $shippingTarget = Target::shipments()
        ->applyAggregate()
        ->build();

    $condition = new CartCondition(
        name: 'standard-shipping',
        type: 'shipping',
        target: $shippingTarget,
        value: '0'
    );
    $cart->addCondition($condition);

    $total = $cart->total()->getAmount();

    expect($total)->toBe(115.0);
});

it('applies payment surcharges per payment source', function (): void {
    $storage = new InMemoryStorage();
    $cart = new Cart($storage, 'payments-test', events: null);

    $cart->add('sku-1', 'Sample Item', 100, 1);

    $cart->resolvePaymentsUsing(function () {
        return [
            ['id' => 'card', 'base_amount' => 100.0],
            ['id' => 'gift-card', 'base_amount' => 25.0],
        ];
    });

    $target = Target::payments()
        ->phase(ConditionPhase::PAYMENT)
        ->apply(ConditionApplication::PER_PAYMENT)
        ->build();

    $cart->addCondition(new CartCondition(
        name: 'payment-surcharge',
        type: 'fee',
        target: $target,
        value: '+2%'
    ));

    $total = $cart->total()->getAmount();

    // 100 + 25 items + payment surcharge (2% of each payment) = 125 + (2 + 0.5) = 127.5
    expect($total)->toBe(127.5);
});
