<?php

declare(strict_types=1);

use AIArmada\Cart\Facades\Cart as CartFacade;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('cart payment intent metadata storage and retrieval works', function (): void {
    // Clear any existing cart
    CartFacade::clear();

    // Add items to cart (no database lookup needed)
    CartFacade::add('test-product-1', 'Test Product 1', 100.00, 2);
    CartFacade::add('test-product-2', 'Test Product 2', 50.00, 1);

    $cart = CartFacade::getCurrentCart();

    // Verify cart has items
    expect($cart->getItems())->toHaveCount(2);
    expect($cart->total()->getAmount())->toBe(250.0);

    // Check no payment intent exists initially
    expect($cart->getMetadata('payment_intent'))->toBeNull();

    // Set payment intent metadata
    $paymentIntentData = [
        'purchase_id' => 'test_purchase_123',
        'status' => 'created',
        'amount' => 250.0,
        'created_at' => now()->toIso8601String(),
    ];

    $cart->setMetadata('payment_intent', $paymentIntentData);

    // Verify metadata was stored
    $retrieved = $cart->getMetadata('payment_intent');
    expect($retrieved)->not->toBeNull();
    expect($retrieved['purchase_id'])->toBe('test_purchase_123');
    expect($retrieved['status'])->toBe('created');
    expect($retrieved['amount'])->toBe(250); // JSON decoding converts to int

    // Verify hasMetadata works
    expect($cart->hasMetadata('payment_intent'))->toBeTrue();
    expect($cart->hasMetadata('non_existent_key'))->toBeFalse();
});
