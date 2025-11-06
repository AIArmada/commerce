<?php

declare(strict_types=1);

use AIArmada\Cart\Facades\Cart;

describe('Cart Persistence', function (): void {
    beforeEach(function (): void {
        Cart::clear();
    });

    it('persists cart data with session storage', function (): void {
        Cart::add('persistent-item', 'Persistent Item', 50.00, 2);

        $items = Cart::getItems();

        expect($items)->toHaveCount(1);
        expect($items->first()->id)->toBe('persistent-item');
        expect($items->first()->quantity)->toBe(2);
    });

    it('persists cart data with conditions', function (): void {
        Cart::add('item', 'Item', 100.00, 1);
        Cart::addTax('VAT', '10%');

        expect(Cart::getConditions())->toHaveCount(1);
        expect(Cart::total()->getAmount())->toBe(110.00);
    });

    it('persists metadata across operations', function (): void {
        Cart::setMetadata('customer_note', 'Please gift wrap');
        Cart::add('item', 'Item', 50.00, 1);

        expect(Cart::getMetadata('customer_note'))->toBe('Please gift wrap');
    });

    it('maintains data integrity after multiple operations', function (): void {
        Cart::add('item-1', 'Item 1', 10.00, 1);
        Cart::add('item-2', 'Item 2', 20.00, 2);
        Cart::addTax('VAT', '10%');
        Cart::setMetadata('coupon', 'SAVE10');

        Cart::update('item-1', ['quantity' => 3]);
        Cart::remove('item-2');

        expect(Cart::getItems())->toHaveCount(1);
        expect(Cart::get('item-1')->quantity)->toBe(4); // 1 + 3
        expect(Cart::getConditions())->toHaveCount(1);
        expect(Cart::getMetadata('coupon'))->toBe('SAVE10');
    });

    it('clears all data when clearing cart', function (): void {
        Cart::add('item', 'Item', 50.00, 1);
        Cart::addTax('VAT', '10%');
        Cart::setMetadata('note', 'test');

        Cart::clear();

        expect(Cart::isEmpty())->toBeTrue();
        expect(Cart::getConditions())->toHaveCount(0);
        expect(Cart::getMetadata('note'))->toBeNull();
    });

    it('exports complete cart state', function (): void {
        Cart::add('item', 'Item', 100.00, 1);
        Cart::addTax('VAT', '10%');
        Cart::setMetadata('customer_id', 123);

        $state = Cart::toArray();

        expect($state)->toHaveKey('items');
        expect($state)->toHaveKey('conditions');
        expect($state)->toHaveKey('subtotal');
        expect($state)->toHaveKey('total');
        expect($state)->toHaveKey('quantity');
    });
});

describe('Storage Drivers', function (): void {
    it('works with session storage', function (): void {
        config(['cart.storage' => 'session']);

        Cart::clear();
        Cart::add('session-item', 'Session Item', 25.00, 1);

        expect(Cart::get('session-item'))->not->toBeNull();
        expect(Cart::total()->getAmount())->toBe(25.00);
    });

    it('works with cache storage', function (): void {
        config(['cart.storage' => 'cache']);

        Cart::clear();
        Cart::add('cache-item', 'Cache Item', 35.00, 1);

        expect(Cart::get('cache-item'))->not->toBeNull();
        expect(Cart::total()->getAmount())->toBe(35.00);
    });

    it('works with database storage', function (): void {
        config(['cart.storage' => 'database']);

        Cart::clear();
        Cart::add('db-item', 'Database Item', 45.00, 1);

        expect(Cart::get('db-item'))->not->toBeNull();
        expect(Cart::total()->getAmount())->toBe(45.00);
    });
});

describe('Data Integrity', function (): void {
    beforeEach(function (): void {
        Cart::clear();
    });

    it('maintains item properties after save', function (): void {
        $item = Cart::add('test-item', 'Test Item', 50.00, 2, ['color' => 'red']);

        // Cart auto-persists, no need to call save()

        $retrieved = Cart::get('test-item');
        expect($retrieved->id)->toBe('test-item');
        expect($retrieved->name)->toBe('Test Item');
        expect($retrieved->price)->toBe(50.00);
        expect($retrieved->quantity)->toBe(2);
        expect($retrieved->getAttribute('color'))->toBe('red');
    });

    it('handles special characters in item data', function (): void {
        Cart::add('special-item', "Item with 'quotes' and \"double quotes\"", 10.00, 1, [
            'description' => 'Special chars: <>&"\'',
        ]);

        $item = Cart::get('special-item');
        expect($item->name)->toContain('quotes');
        expect($item->getAttribute('description'))->toContain('Special chars');
    });

    it('preserves decimal precision', function (): void {
        Cart::add('precise-item', 'Precise Item', 99.99, 1);
        Cart::add('another-item', 'Another Item', 0.01, 1);

        expect(Cart::subtotal()->getAmount())->toBe(100.00);
    });

    it('handles large quantities', function (): void {
        Cart::add('bulk-item', 'Bulk Item', 1.00, 10000);

        expect(Cart::getTotalQuantity())->toBe(10000);
        expect(Cart::subtotal()->getAmount())->toBe(10000.00);
    });
});
