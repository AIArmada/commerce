<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Services\CartConditionResolver;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Cashier\Checkout\CartCheckoutBuilder;
use AIArmada\Cashier\Contracts\GatewayContract;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;

uses(CashierTestCase::class);

it('uses checkout inventory settings as the canonical cart checkout configuration', function (): void {
    config()->set('checkout.integrations.inventory.enabled', false);
    config()->set('checkout.integrations.inventory.reservation_ttl', 601);
    config()->set('checkout.integrations.inventory.validate_stock', false);

    $builder = new CartCheckoutBuilder(
        new Cart(
            new InMemoryStorage,
            'cart-configuration-test',
            events: null,
            conditionResolver: new CartConditionResolver,
        ),
        Mockery::mock(GatewayContract::class),
    );

    $reflection = new ReflectionClass($builder);

    expect($reflection->getProperty('allocateInventory')->getValue($builder))->toBeFalse()
        ->and($reflection->getProperty('inventoryTtl')->getValue($builder))->toBe(11)
        ->and($reflection->getProperty('validateStock')->getValue($builder))->toBeFalse()
        ->and(config('cashier.cart'))->not->toHaveKeys([
            'allocate_inventory',
            'inventory_ttl_minutes',
            'validate_stock',
            'failure_mode',
            'retry_window_minutes',
            'hard_failure_codes',
        ]);
});
