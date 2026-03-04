<?php

declare(strict_types=1);

use AIArmada\Cart\Facades\Cart;
use AIArmada\Checkout\Contracts\CheckoutServiceInterface;
use AIArmada\Checkout\Steps\CalculateShippingStep;

it('uses shipping condition from cart snapshot when present', function (): void {
    Cart::setIdentifier('shipping-condition-test');
    Cart::add('sku-1', 'Test Item', 5000, 1);
    Cart::addShipping('Standard Shipping', 800, null, 'standard');

    $cartId = Cart::getId();

    $service = app(CheckoutServiceInterface::class);
    $session = $service->startCheckout($cartId);

    $session->update([
        'shipping_data' => [
            'name' => 'Test Customer',
            'line1' => '123 Test Street',
            'postcode' => '40000',
            'state' => 'Selangor',
            'country' => 'MY',
        ],
    ]);

    $step = app(CalculateShippingStep::class);
    $result = $step->handle($session);

    expect($result->isSuccessful())->toBeTrue();

    $session->refresh();
    expect($session->shipping_total)->toBe(800)
        ->and($session->shipping_data['source'])->toBe('cart_condition');
});

it('falls back to shipping adapter when no cart condition exists', function (): void {
    Cart::setIdentifier('no-condition-test');
    Cart::add('sku-1', 'Test Item', 5000, 1);

    $cartId = Cart::getId();

    $service = app(CheckoutServiceInterface::class);
    $session = $service->startCheckout($cartId);

    $session->update([
        'shipping_data' => [
            'name' => 'Test Customer',
            'line1' => '123 Test Street',
            'postcode' => '40000',
            'state' => 'Selangor',
            'country' => 'MY',
        ],
    ]);

    $step = app(CalculateShippingStep::class);
    $result = $step->handle($session);

    expect($result->isSuccessful())->toBeTrue();

    $session->refresh();
    expect($session->shipping_total)->toBeGreaterThanOrEqual(0)
        ->and($session->shipping_data['source'] ?? null)->toBe('shipping_adapter');
});

it('includes shipping condition in cart snapshot via addShipping', function (): void {
    Cart::setIdentifier('snapshot-condition-test');
    Cart::add('sku-1', 'Test Item', 5000, 2);
    Cart::addShipping('Express Shipping', 1500, null, 'express');

    $cartId = Cart::getId();

    $service = app(CheckoutServiceInterface::class);
    $session = $service->startCheckout($cartId);

    $snapshot = $session->cart_snapshot;
    $conditions = $snapshot['conditions'] ?? [];

    $shippingConditions = array_filter($conditions, fn (array $c) => ($c['type'] ?? '') === 'shipping');

    expect($shippingConditions)->not->toBeEmpty();

    $shippingCondition = array_values($shippingConditions)[0];
    expect((int) $shippingCondition['value'])->toBe(1500)
        ->and($shippingCondition['name'])->toBe('Express Shipping');
});

it('calculates grand total correctly with shipping condition', function (): void {
    Cart::setIdentifier('grand-total-test');
    Cart::add('sku-1', 'Test Item', 5000, 2);
    Cart::addShipping('Standard Shipping', 800, null, 'standard');

    $cartId = Cart::getId();

    $service = app(CheckoutServiceInterface::class);
    $session = $service->startCheckout($cartId);

    $session->update([
        'subtotal' => 10000,
        'shipping_data' => [
            'name' => 'Test Customer',
            'line1' => '123 Test Street',
            'postcode' => '40000',
            'state' => 'Selangor',
            'country' => 'MY',
        ],
    ]);

    $step = app(CalculateShippingStep::class);
    $step->handle($session);

    $session->refresh();
    expect($session->shipping_total)->toBe(800)
        ->and($session->grand_total)->toBe(10800);
});
