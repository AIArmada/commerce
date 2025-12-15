<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Security\Fraud\FraudContext;
use AIArmada\Cart\Testing\InMemoryStorage;

describe('FraudContext', function (): void {
    beforeEach(function (): void {
        $this->storage = new InMemoryStorage;
    });

    it('can be instantiated with all parameters', function (): void {
        $cart = new Cart($this->storage, 'user-123');
        $cart->add('item-1', 'Product', 1000, 2);
        $timestamp = new DateTimeImmutable('2024-01-15 10:00:00');

        $context = new FraudContext(
            cart: $cart,
            userId: 'user-123',
            ipAddress: '192.168.1.1',
            userAgent: 'Mozilla/5.0',
            sessionId: 'session-abc',
            timestamp: $timestamp
        );

        expect($context->cart)->toBe($cart)
            ->and($context->userId)->toBe('user-123')
            ->and($context->ipAddress)->toBe('192.168.1.1')
            ->and($context->userAgent)->toBe('Mozilla/5.0')
            ->and($context->sessionId)->toBe('session-abc')
            ->and($context->timestamp)->toBe($timestamp);
    });

    it('gets cart identifier', function (): void {
        $cart = new Cart($this->storage, 'fraud-test-cart');
        $cart->add('item-1', 'Product', 100, 1);
        $timestamp = new DateTimeImmutable;

        $context = new FraudContext(
            cart: $cart,
            userId: 'user-1',
            ipAddress: '127.0.0.1',
            userAgent: null,
            sessionId: null,
            timestamp: $timestamp
        );

        // getCartId returns storage-generated ID, so just verify it's a string
        expect($context->getCartId())->toBeString()
            ->and($context->getCartId())->not->toBeEmpty();
    });

    it('gets cart total', function (): void {
        $cart = new Cart($this->storage, 'cart-total');
        $cart->add('item-1', 'Product A', 1000, 2); // 2000 cents
        $cart->add('item-2', 'Product B', 500, 3);  // 1500 cents

        $context = new FraudContext(
            cart: $cart,
            userId: null,
            ipAddress: null,
            userAgent: null,
            sessionId: null,
            timestamp: new DateTimeImmutable
        );

        expect($context->getCartTotal())->toBe(3500);
    });

    it('gets item count', function (): void {
        $cart = new Cart($this->storage, 'cart-count');
        $cart->add('item-1', 'Product A', 100, 1);
        $cart->add('item-2', 'Product B', 200, 1);
        $cart->add('item-3', 'Product C', 300, 1);

        $context = new FraudContext(
            cart: $cart,
            userId: null,
            ipAddress: null,
            userAgent: null,
            sessionId: null,
            timestamp: new DateTimeImmutable
        );

        expect($context->getItemCount())->toBe(3);
    });

    it('gets total quantity', function (): void {
        $cart = new Cart($this->storage, 'cart-qty');
        $cart->add('item-1', 'Product A', 100, 5);
        $cart->add('item-2', 'Product B', 200, 3);

        $context = new FraudContext(
            cart: $cart,
            userId: null,
            ipAddress: null,
            userAgent: null,
            sessionId: null,
            timestamp: new DateTimeImmutable
        );

        expect($context->getTotalQuantity())->toBe(8);
    });

    it('detects authenticated user', function (): void {
        $cart = new Cart($this->storage, 'auth-cart');

        $authenticated = new FraudContext(
            cart: $cart,
            userId: 'user-123',
            ipAddress: null,
            userAgent: null,
            sessionId: null,
            timestamp: new DateTimeImmutable
        );

        $anonymous = new FraudContext(
            cart: $cart,
            userId: null,
            ipAddress: null,
            userAgent: null,
            sessionId: null,
            timestamp: new DateTimeImmutable
        );

        expect($authenticated->isAuthenticated())->toBeTrue()
            ->and($anonymous->isAuthenticated())->toBeFalse();
    });

    it('converts to array', function (): void {
        $cart = new Cart($this->storage, 'array-cart');
        $cart->add('item-1', 'Product', 1000, 2);
        $timestamp = new DateTimeImmutable('2024-01-15 14:30:00');

        $context = new FraudContext(
            cart: $cart,
            userId: 'user-array',
            ipAddress: '10.0.0.1',
            userAgent: 'TestAgent/1.0',
            sessionId: 'sess-123',
            timestamp: $timestamp
        );

        $array = $context->toArray();

        // cart_id is storage-generated, just verify it exists
        expect($array['cart_id'])->toBeString()
            ->and($array['cart_total'])->toBe(2000)
            ->and($array['item_count'])->toBe(1)
            ->and($array['total_quantity'])->toBe(2)
            ->and($array['user_id'])->toBe('user-array')
            ->and($array['ip_address'])->toBe('10.0.0.1')
            ->and($array['user_agent'])->toBe('TestAgent/1.0')
            ->and($array['session_id'])->toBe('sess-123')
            ->and($array['timestamp'])->toBe('2024-01-15 14:30:00')
            ->and($array['is_authenticated'])->toBeTrue();
    });
});
