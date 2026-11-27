<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Support\CartMoney;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Shipping\Data\RateQuoteData;
use AIArmada\Shipping\Features\Rates\Api\FreeShippingResult;
use AIArmada\Shipping\Features\Rates\EntryPoints\Cart\ShippingConditionProvider;
use AIArmada\Shipping\Features\Rates\Queries\FreeShippingEvaluator;
use AIArmada\Shipping\Features\Rates\Queries\RateShoppingEngine;

// ============================================
// ShippingConditionProvider Tests
// ============================================

beforeEach(function (): void {
    $this->rateEngine = Mockery::mock(RateShoppingEngine::class);
    $this->freeShippingEvaluator = Mockery::mock(FreeShippingEvaluator::class);

    config(['shipping.defaults.origin' => [
        'name' => 'Test Store',
        'phone' => '123456',
        'line1' => '1 Main St',
        'postcode' => '00000',
        'country' => 'MY',
    ]]);
});

afterEach(function (): void {
    Mockery::close();
});

it('returns empty conditions when no shipping address', function (): void {
    $storage = new InMemoryStorage;
    $cart = new Cart($storage, 'test-shipping-' . uniqid(), events: null);

    $provider = new ShippingConditionProvider($this->rateEngine);

    $conditions = $provider->getConditionsFor($cart);

    expect($conditions)->toBeEmpty();
});

it('returns empty conditions when shipping address is not an array', function (): void {
    $storage = new InMemoryStorage;
    $cart = new Cart($storage, 'test-shipping-' . uniqid(), events: null);
    $cart->setMetadata('shipping_address', 'invalid');

    $provider = new ShippingConditionProvider($this->rateEngine);

    $conditions = $provider->getConditionsFor($cart);

    expect($conditions)->toBeEmpty();
});

it('returns empty conditions when no rate available', function (): void {
    $storage = new InMemoryStorage;
    $cart = new Cart($storage, 'test-shipping-' . uniqid(), events: null);
    $cart->setMetadata('shipping_address', [
        'name' => 'John Doe',
        'phone' => '123456',
        'line1' => '123 Test St',
        'postcode' => '12345',
        'country' => 'MY',
    ]);
    $cart->add('physical-item', 'Physical item', 1000, 1, ['weight' => 500]);

    $this->rateEngine->shouldReceive('getBestRate')
        ->andReturn(null);

    $provider = new ShippingConditionProvider($this->rateEngine);

    $conditions = $provider->getConditionsFor($cart);

    expect($conditions)->toBeEmpty();
});

it('returns shipping condition when rate is available', function (): void {
    $storage = new InMemoryStorage;
    $cart = new Cart($storage, 'test-shipping-' . uniqid(), events: null);
    $cart->setMetadata('shipping_address', [
        'name' => 'John Doe',
        'phone' => '123456',
        'line1' => '123 Test St',
        'postcode' => '12345',
        'country' => 'MY',
    ]);
    $cart->add('physical-item', 'Physical item', 1000, 1, ['weight' => 500]);

    $rate = new RateQuoteData(
        carrier: 'jnt',
        service: 'express',
        rate: 1000,
        currency: 'MYR',
        estimatedDays: 2,
        quoteId: 'QUOTE123',
        note: null,
    );

    $this->rateEngine->shouldReceive('getBestRate')
        ->andReturn($rate);

    $provider = new ShippingConditionProvider($this->rateEngine);

    $conditions = $provider->getConditionsFor($cart);

    expect($conditions)->toHaveCount(1);
    expect($conditions[0]->getValue())->toBe(1000);
});

it('applies free shipping when evaluator returns applies true', function (): void {
    $storage = new InMemoryStorage;
    $cart = new Cart($storage, 'test-shipping-' . uniqid(), events: null);
    $cart->setMetadata('shipping_address', [
        'name' => 'John Doe',
        'phone' => '123456',
        'line1' => '123 Test St',
        'postcode' => '12345',
        'country' => 'MY',
    ]);
    $cart->add('physical-item', 'Physical item', 1000, 1, ['weight' => 500]);

    $rate = new RateQuoteData(
        carrier: 'jnt',
        service: 'express',
        rate: 1000,
        currency: 'MYR',
        estimatedDays: 2,
    );

    $this->rateEngine->shouldReceive('getBestRate')
        ->andReturn($rate);

    $this->freeShippingEvaluator->shouldReceive('evaluate')
        ->andReturn(new FreeShippingResult(
            applies: true,
            message: 'Free shipping applied!',
        ));

    $provider = new ShippingConditionProvider($this->rateEngine, $this->freeShippingEvaluator);

    $conditions = $provider->getConditionsFor($cart);

    expect($conditions)->toHaveCount(1);
    expect($conditions[0]->getValue())->toBe(0); // Free shipping
});

it('validates shipping condition requires shipping address', function (): void {
    $storage = new InMemoryStorage;
    $cart = new Cart($storage, 'test-shipping-' . uniqid(), events: null);

    $condition = new CartCondition(
        name: 'Shipping',
        type: 'shipping',
        target: 'cart@cart_subtotal/aggregate',
        value: 1000,
    );

    $provider = new ShippingConditionProvider($this->rateEngine);

    expect($provider->validate($condition, $cart))->toBeFalse();
});

it('validates shipping condition when address and physical items exist', function (): void {
    $storage = new InMemoryStorage;
    $cart = new Cart($storage, 'test-shipping-' . uniqid(), events: null);
    $cart->setMetadata('shipping_address', [
        'name' => 'John Doe',
        'phone' => '123456',
        'line1' => '123 Test St',
        'postcode' => '12345',
        'country' => 'MY',
    ]);
    $cart->add('physical-item', 'Physical item', 1000, 1, ['weight' => 500]);

    $condition = new CartCondition(
        name: 'Shipping',
        type: 'shipping',
        target: 'cart@cart_subtotal/aggregate',
        value: 1000,
    );

    $provider = new ShippingConditionProvider($this->rateEngine);

    expect($provider->validate($condition, $cart))->toBeTrue();
});

it('validates non-shipping conditions always pass', function (): void {
    $storage = new InMemoryStorage;
    $cart = new Cart($storage, 'test-shipping-' . uniqid(), events: null);

    $condition = new CartCondition(
        name: 'Tax',
        type: 'tax',
        target: 'cart@cart_subtotal/aggregate',
        value: 100,
    );

    $provider = new ShippingConditionProvider($this->rateEngine);

    expect($provider->validate($condition, $cart))->toBeTrue();
});

it('gets selected rate when method is specified', function (): void {
    $storage = new InMemoryStorage;
    $cart = new Cart($storage, 'test-shipping-' . uniqid(), events: null);
    $cart->setMetadata('shipping_address', [
        'name' => 'John Doe',
        'phone' => '123456',
        'line1' => '123 Test St',
        'postcode' => '12345',
        'country' => 'MY',
    ]);
    $cart->add('physical-item', 'Physical item', 1000, 1, ['weight' => 500]);
    $cart->setMetadata('selected_shipping_method', ['carrier' => 'jnt', 'service' => 'standard']);

    $selectedRate = new RateQuoteData(
        carrier: 'jnt',
        service: 'standard',
        rate: 800,
        currency: 'MYR',
        estimatedDays: 3,
    );

    $this->rateEngine->shouldReceive('getAllRates')
        ->andReturn(collect([
            new RateQuoteData(
                carrier: 'jnt',
                service: 'express',
                rate: 1500,
                currency: 'MYR',
                estimatedDays: 1,
            ),
            $selectedRate,
        ]));

    $provider = new ShippingConditionProvider($this->rateEngine);

    $conditions = $provider->getConditionsFor($cart);

    expect($conditions)->toHaveCount(1);
    expect($conditions[0]->getValue())->toBe(800);
});

it('calculates package weight from cart items', function (): void {
    $storage = new InMemoryStorage;
    $cart = new Cart($storage, 'test-shipping-' . uniqid(), events: null);
    $cart->setMetadata('shipping_address', [
        'name' => 'John Doe',
        'phone' => '123456',
        'line1' => '123 Test St',
        'postcode' => '12345',
        'country' => 'MY',
    ]);
    $cart->add('item1', 'Product with weight', 5000, 2, ['weight' => 500]);

    $rate = new RateQuoteData(
        carrier: 'manual',
        service: 'standard',
        rate: 1000,
        currency: 'MYR',
        estimatedDays: 3,
    );

    $currency = CartMoney::currency();

    $this->rateEngine->shouldReceive('getBestRate')
        ->once()
        ->withArgs(fn ($origin, $destination, $packages, $options) => $options['cart_total'] === 10000 && $options['item_count'] === 2 && $options['currency'] === $currency && $packages[0]->weight === 1000)
        ->andReturn($rate);

    $provider = new ShippingConditionProvider($this->rateEngine);

    $conditions = $provider->getConditionsFor($cart);

    expect($conditions)->toHaveCount(1);
});

it('does not request shipping rates for empty or service-only carts', function (): void {
    $cart = new Cart(new InMemoryStorage, 'nonphysical-' . uniqid(), events: null);
    $cart->setMetadata('shipping_address', [
        'name' => 'John Doe',
        'phone' => '123456',
        'line1' => '123 Test St',
        'postcode' => '12345',
        'country' => 'MY',
    ]);
    $this->rateEngine->shouldNotReceive('getBestRate');
    $this->rateEngine->shouldNotReceive('getAllRates');
    $provider = new ShippingConditionProvider($this->rateEngine);

    expect($provider->getConditionsFor($cart))->toBeEmpty();

    $cart->add('service', 'Digital service', 5000, 1, ['requires_shipping' => false, 'weight' => 500]);
    expect($provider->getConditionsFor($cart))->toBeEmpty()
        ->and($provider->validate(new CartCondition(name: 'Shipping', type: 'shipping', target: 'cart@cart_subtotal/aggregate', value: 1000), $cart))->toBeFalse();
});

it('retains a zero-weight fallback only for physical items missing weight', function (): void {
    $cart = new Cart(new InMemoryStorage, 'missing-weight-' . uniqid(), events: null);
    $cart->setMetadata('shipping_address', [
        'name' => 'John Doe',
        'phone' => '123456',
        'line1' => '123 Test St',
        'postcode' => '12345',
        'country' => 'MY',
    ]);
    $cart->add('physical', 'Physical item', 1500, 2, ['requires_shipping' => true]);
    $cart->add('service', 'Digital service', 5000, 1, ['requires_shipping' => false]);

    $this->rateEngine->shouldReceive('getBestRate')
        ->once()
        ->withArgs(fn ($origin, $destination, $packages, $options) => count($packages) === 1 && $packages[0]->weight === 0 && $packages[0]->declaredValue === 3000)
        ->andReturnNull();

    expect((new ShippingConditionProvider($this->rateEngine))->getConditionsFor($cart))->toBeEmpty();
});
