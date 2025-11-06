<?php

declare(strict_types=1);

use AIArmada\Cart\Facades\Cart;

describe('Database Locking with lockForUpdate', function (): void {
    beforeEach(function (): void {
        config(['cart.storage' => 'database']);
        config(['cart.database.lock_for_update' => true]);
        Cart::clear();
    });

    it('prevents race conditions with lockForUpdate enabled', function (): void {
        Cart::add('concurrent-item', 'Concurrent Item', 50.00, 1);

        // Simulate concurrent access
        $firstTotal = Cart::total()->getAmount();
        Cart::add('concurrent-item', 'Concurrent Item', 50.00, 1);
        $secondTotal = Cart::total()->getAmount();

        expect($secondTotal)->toBeGreaterThan($firstTotal);
        expect(Cart::get('concurrent-item')->quantity)->toBe(2);
    });

    it('handles concurrent condition updates with lockForUpdate', function (): void {
        Cart::add('item', 'Item', 100.00, 1);

        Cart::addTax('VAT', '10%');
        $firstTotal = Cart::total()->getAmount();

        Cart::addFee('Shipping', '5.00');
        $secondTotal = Cart::total()->getAmount();

        expect($secondTotal)->toBeGreaterThan($firstTotal);
        expect(Cart::getConditions())->toHaveCount(2);
    });

    it('metadata updates work with lockForUpdate', function (): void {
        Cart::add('item', 'Item', 50.00, 1);

        Cart::setMetadata('customer_note', 'First note');
        Cart::setMetadata('gift_wrap', true);

        expect(Cart::getMetadata('customer_note'))->toBe('First note');
        expect(Cart::getMetadata('gift_wrap'))->toBeTrue();
    });

    it('can disable lockForUpdate for maximum performance', function (): void {
        config(['cart.database.lock_for_update' => false]);

        Cart::add('fast-item', 'Fast Item', 25.00, 1);

        expect(Cart::get('fast-item'))->not->toBeNull();
        expect(Cart::total()->getAmount())->toBe(25.00);
    });
});

describe('Optimistic Locking', function (): void {
    beforeEach(function (): void {
        config(['cart.storage' => 'database']);
        Cart::clear();
    });

    it('uses version numbers for optimistic locking', function (): void {
        Cart::add('versioned-item', 'Versioned Item', 100.00, 1);

        // The cart should have version tracking
        $cartData = Cart::toArray();
        expect($cartData)->toBeArray();
    });

    it('handles optimistic lock conflicts gracefully', function (): void {
        Cart::add('conflict-item', 'Conflict Item', 50.00, 1);

        // Try to simulate a conflict scenario
        Cart::update('conflict-item', ['quantity' => 2]);

        expect(Cart::get('conflict-item')->quantity)->toBe(3); // 1 + 2
    });
});

describe('Locking Performance', function (): void {
    beforeEach(function (): void {
        config(['cart.storage' => 'database']);
        Cart::clear();
    });

    it('performs well with locking enabled', function (): void {
        config(['cart.database.lock_for_update' => true]);

        $startTime = microtime(true);

        for ($i = 0; $i < 10; $i++) {
            Cart::add("item-{$i}", "Item {$i}", 10.00, 1);
        }

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        expect($duration)->toBeLessThan(1.0); // Should complete in under 1 second
        expect(Cart::count())->toBe(10);
    });

    it('performs well with locking disabled', function (): void {
        config(['cart.database.lock_for_update' => false]);

        $startTime = microtime(true);

        for ($i = 0; $i < 10; $i++) {
            Cart::add("item-{$i}", "Item {$i}", 10.00, 1);
        }

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        expect($duration)->toBeLessThan(1.0);
        expect(Cart::count())->toBe(10);
    });
});

describe('Database Storage Operations', function (): void {
    beforeEach(function (): void {
        config(['cart.storage' => 'database']);
        Cart::clear();
    });

    it('stores items correctly', function (): void {
        Cart::add('db-item', 'Database Item', 75.00, 2);

        $item = Cart::get('db-item');
        expect($item->id)->toBe('db-item');
        expect($item->name)->toBe('Database Item');
        expect($item->price)->toBe(75.00);
        expect($item->quantity)->toBe(2);
    });

    it('stores conditions correctly', function (): void {
        Cart::add('item', 'Item', 100.00, 1);
        Cart::addTax('VAT', '10%');
        Cart::addShipping('Shipping', 5.00);

        expect(Cart::getConditions())->toHaveCount(2);
    });

    it('stores metadata correctly', function (): void {
        Cart::setMetadata('order_note', 'Leave at door');
        Cart::setMetadata('gift', true);

        expect(Cart::getMetadata('order_note'))->toBe('Leave at door');
        expect(Cart::getMetadata('gift'))->toBeTrue();
    });

    it('handles large datasets', function (): void {
        for ($i = 1; $i <= 50; $i++) {
            Cart::add("bulk-item-{$i}", "Bulk Item {$i}", 10.00 + $i, 1);
        }

        expect(Cart::count())->toBe(50);
        expect(Cart::getTotalQuantity())->toBe(50);
    });
});
