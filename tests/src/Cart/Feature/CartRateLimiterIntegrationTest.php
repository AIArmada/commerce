<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Exceptions\RateLimitExceededException;
use AIArmada\Cart\Security\CartRateLimiter;
use AIArmada\Cart\Testing\InMemoryStorage;

it('blocks cart add operation when rate limit exceeded via integrated cart', function (): void {
    // Create cart with rate limiter injected
    $storage = new InMemoryStorage;
    $identifier = uniqid('session-', true);
    $limiter = new CartRateLimiter([
        'add_item' => ['perMinute' => 3, 'perHour' => 100],
    ]);
    $cart = new Cart($storage, $identifier, events: null, rateLimiter: $limiter);

    $itemsAdded = 0;
    $blocked = false;

    // Try to add 5 items - should only allow 3 (rate limit is enforced internally)
    for ($i = 1; $i <= 5; $i++) {
        try {
            $cart->add("item-{$i}", "Product {$i}", 1000, 1);
            $itemsAdded++;
        } catch (RateLimitExceededException) {
            $blocked = true;

            break;
        }
    }

    expect($itemsAdded)->toBe(3);
    expect($blocked)->toBeTrue();
    expect($cart->count())->toBe(3);
});

it('allows different users to add items independently', function (): void {
    $storage = new InMemoryStorage;
    $limiter = new CartRateLimiter([
        'add_item' => ['perMinute' => 2, 'perHour' => 100],
    ]);

    $user1Id = uniqid('user1-', true);
    $user2Id = uniqid('user2-', true);

    // User 1 cart with rate limiter
    $cart1 = new Cart($storage, $user1Id, events: null, rateLimiter: $limiter);

    // User 2 cart with same rate limiter (limits are per-user)
    $cart2 = new Cart($storage, $user2Id, events: null, rateLimiter: $limiter);

    // User 1 uses up their limit
    $cart1->add('item-1', 'Product 1', 1000, 1);
    $cart1->add('item-2', 'Product 2', 1000, 1);

    // User 1 should be blocked
    $user1Blocked = false;

    try {
        $cart1->add('item-3', 'Product 3', 1000, 1);
    } catch (RateLimitExceededException) {
        $user1Blocked = true;
    }
    expect($user1Blocked)->toBeTrue();

    // User 2 should still be able to add
    $cart2->add('item-1', 'Product 1', 1000, 1);
    expect($cart2->count())->toBe(1);
});

it('rate limits checkout operations more strictly via standalone limiter', function (): void {
    $limiter = new CartRateLimiter([
        'checkout' => ['perMinute' => 2, 'perHour' => 10],
    ]);

    $identifier = uniqid('checkout-', true);
    $checkoutAttempts = 0;

    // Try 5 checkout attempts - should only allow 2
    for ($i = 0; $i < 5; $i++) {
        $result = $limiter->check($identifier, 'checkout');
        if ($result->allowed) {
            $checkoutAttempts++;
        }
    }

    expect($checkoutAttempts)->toBe(2);
});

it('protects against cart manipulation attacks', function (): void {
    $storage = new InMemoryStorage;
    $identifier = uniqid('attacker-', true);
    $limiter = new CartRateLimiter([
        'add_item' => ['perMinute' => 10, 'perHour' => 50],
    ]);
    $cart = new Cart($storage, $identifier, events: null, rateLimiter: $limiter);

    // Simulate rapid add attack
    $blockedOperations = 0;

    for ($i = 0; $i < 15; $i++) {
        try {
            $cart->add("item-{$i}", "Product {$i}", 1000, 1);
        } catch (RateLimitExceededException) {
            $blockedOperations++;
        }
    }

    // Should have blocked some operations
    expect($blockedOperations)->toBeGreaterThan(0);
    expect($cart->count())->toBe(10); // Only 10 allowed per minute
});

it('provides rate limit info for UI feedback', function (): void {
    $limiter = new CartRateLimiter([
        'add_item' => ['perMinute' => 5, 'perHour' => 100],
    ]);

    $identifier = uniqid('feedback-', true);

    // Use some of the limit
    $limiter->check($identifier, 'add_item');
    $limiter->check($identifier, 'add_item');
    $limiter->check($identifier, 'add_item');

    $remaining = $limiter->remaining($identifier, 'add_item');

    expect($remaining['minute'])->toBe(2);
    expect($remaining['hour'])->toBe(97);

    // This info can be used in UI: "You can add 2 more items this minute"
});

it('can disable rate limiting for specific cart instance', function (): void {
    $storage = new InMemoryStorage;
    $identifier = uniqid('disabled-', true);
    $limiter = new CartRateLimiter([
        'add_item' => ['perMinute' => 2, 'perHour' => 100],
    ]);

    // Create cart with rate limiting disabled
    $cart = new Cart($storage, $identifier, events: null, rateLimiter: $limiter);
    $cart->withoutRateLimiting();

    // Should be able to add more than 2 items since rate limiting is disabled
    for ($i = 1; $i <= 5; $i++) {
        $cart->add("item-{$i}", "Product {$i}", 1000, 1);
    }

    expect($cart->count())->toBe(5);
});
