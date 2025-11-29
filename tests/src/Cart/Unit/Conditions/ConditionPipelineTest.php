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

    $cart->add('sku-1', 'Sample Item', 5000, 2); // subtotal 10000 cents ($100)

    $cart->resolveShipmentsUsing(function (): iterable {
        return [
            ['id' => 'domestic', 'base_amount' => 1000],  // $10 in cents
            ['id' => 'express', 'base_amount' => 500],    // $5 in cents
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

    expect($total)->toBe(11500);  // 10000 + 1000 + 500 = 11500 cents ($115)
});

it('applies payment surcharges per payment source', function (): void {
    $storage = new InMemoryStorage();
    $cart = new Cart($storage, 'payments-test', events: null);

    $cart->add('sku-1', 'Sample Item', 10000, 1);  // $100 in cents

    $cart->resolvePaymentsUsing(function () {
        return [
            ['id' => 'card', 'base_amount' => 10000],      // $100 in cents
            ['id' => 'gift-card', 'base_amount' => 2500],  // $25 in cents
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

    // 10000 + 2500 items + payment surcharge (2% of each payment) = 12500 + (200 + 50) = 12750 cents
    expect($total)->toBe(12750);
});
