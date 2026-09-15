<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Shipping\Services\FreeShippingEvaluator;
use AIArmada\Shipping\Services\FreeShippingResult;

// ============================================
// FreeShippingEvaluator Tests
// ============================================

it('returns null when free shipping is disabled', function (): void {
    $evaluator = new FreeShippingEvaluator(['enabled' => false]);

    $storage = new InMemoryStorage;
    $cart = new Cart($storage, 'test-free-shipping-' . uniqid(), events: null);

    $result = $evaluator->evaluate($cart);

    expect($result)->toBeNull();
});

it('returns null when no threshold configured', function (): void {
    $evaluator = new FreeShippingEvaluator([
        'enabled' => true,
        'threshold' => null,
    ]);

    $storage = new InMemoryStorage;
    $cart = new Cart($storage, 'test-free-shipping-' . uniqid(), events: null);

    $result = $evaluator->evaluate($cart);

    expect($result)->toBeNull();
});

it('applies free shipping when cart meets threshold', function (): void {
    $evaluator = new FreeShippingEvaluator([
        'enabled' => true,
        'threshold' => 10000, // RM100
    ]);

    $storage = new InMemoryStorage;
    $cart = new Cart($storage, 'test-free-shipping-' . uniqid(), events: null);
    // Add items totaling 15000 (RM150)
    $cart->add('item1', 'Test Product', 15000, 1);

    $result = $evaluator->evaluate($cart);

    expect($result)->toBeInstanceOf(FreeShippingResult::class);
    expect($result->applies)->toBeTrue();
    expect($result->message)->toBe('Free shipping applied!');
});

it('returns remaining amount when below threshold', function (): void {
    $evaluator = new FreeShippingEvaluator([
        'enabled' => true,
        'threshold' => 10000, // RM100
        'currency' => 'RM',
    ]);

    $storage = new InMemoryStorage;
    $cart = new Cart($storage, 'test-free-shipping-' . uniqid(), events: null);
    // Add items totaling 7500 (RM75)
    $cart->add('item1', 'Test Product', 7500, 1);

    $result = $evaluator->evaluate($cart);

    expect($result)->toBeInstanceOf(FreeShippingResult::class);
    expect($result->applies)->toBeFalse();
    expect($result->nearThreshold)->toBeTrue();
    expect($result->remainingAmount)->toBe(2500); // RM25
    expect($result->message)->toBe('Add RM25.00 more for free shipping!');
});

it('applies free shipping at exact threshold', function (): void {
    $evaluator = new FreeShippingEvaluator([
        'enabled' => true,
        'threshold' => 10000, // RM100
    ]);

    $storage = new InMemoryStorage;
    $cart = new Cart($storage, 'test-free-shipping-' . uniqid(), events: null);
    // Add items totaling exactly 10000 (RM100)
    $cart->add('item1', 'Test Product', 10000, 1);

    $result = $evaluator->evaluate($cart);

    expect($result)->toBeInstanceOf(FreeShippingResult::class);
    expect($result->applies)->toBeTrue();
});

it('uses default currency when not configured', function (): void {
    $evaluator = new FreeShippingEvaluator([
        'enabled' => true,
        'threshold' => 10000,
    ]);

    $storage = new InMemoryStorage;
    $cart = new Cart($storage, 'test-free-shipping-' . uniqid(), events: null);
    // Add items totaling 5000 (RM50)
    $cart->add('item1', 'Test Product', 5000, 1);

    $result = $evaluator->evaluate($cart);

    expect($result->message)->toBe('Add RM50.00 more for free shipping!');
});
