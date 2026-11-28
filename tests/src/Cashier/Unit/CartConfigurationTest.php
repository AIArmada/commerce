<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Services\CartConditionResolver;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Cashier\Checkout\CartCheckoutBuilder;
use AIArmada\Cashier\Contracts\GatewayContract;
use AIArmada\Cashier\Contracts\PaymentContract;
use AIArmada\Cashier\Events\PaymentSucceeded;
use AIArmada\Cashier\Support\CartIntegrationRegistrar;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;
use AIArmada\Inventory\Features\Reservations\Api\CartStockReservations;

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

it('commits and releases the explicit payment cart identity rather than the ambient cart', function (): void {
    $reservations = Mockery::mock(CartStockReservations::class);
    $reservations->shouldReceive('commit')->once()->with('paid-cart-id', 'order-id')->andReturn([]);
    $reservations->shouldReceive('releaseAllForCart')->once()->with('failed-cart-id')->andReturn(2);
    app()->instance(CartStockReservations::class, $reservations);

    $payment = Mockery::mock(PaymentContract::class);
    $payment->shouldReceive('metadata')->andReturn(['order_id' => 'order-id']);
    $payment->shouldReceive('id')->andReturn('payment-id');

    $registrar = new CartIntegrationRegistrar(app(), app('events'));
    $reflection = new ReflectionClass($registrar);
    $reflection->getMethod('commitInventoryAllocations')->invoke($registrar, 'paid-cart-id', new PaymentSucceeded($payment, 'stripe'));
    $reflection->getMethod('releaseInventoryAllocations')->invoke($registrar, 'failed-cart-id');
});
