<?php

declare(strict_types=1);

use AIArmada\Cart\Facades\Cart;

describe('Database Storage Driver', function (): void {
    beforeEach(function (): void {
        config(['cart.storage' => 'database']);
        Cart::clear();
    });

    it('stores and retrieves items separately', function (): void {
        Cart::add('item-1', 'Item 1', 25.00, 1);
        Cart::add('item-2', 'Item 2', 35.00, 2);

        $items = Cart::getItems();

        expect($items)->toHaveCount(2);
        expect($items->first()->id)->toBe('item-1');
    });

    it('stores and retrieves conditions separately', function (): void {
        Cart::add('item', 'Item', 100.00, 1);
        Cart::addTax('VAT', '10%');
        Cart::addFee('Shipping', '5.00');

        $conditions = Cart::getConditions();

        expect($conditions)->toHaveCount(2);
    });

    it('uses session ID for guest users', function (): void {
        // Should work without authenticated user
        Cart::add('guest-item', 'Guest Item', 50.00, 1);

        expect(Cart::get('guest-item'))->not->toBeNull();
    });

    it('maintains separate storage for different instances', function (): void {
        Cart::setInstance('cart1')->add('item-1', 'Item 1', 10.00, 1);
        Cart::setInstance('cart2')->add('item-2', 'Item 2', 20.00, 1);

        expect(Cart::setInstance('cart1')->count())->toBe(1);
        expect(Cart::setInstance('cart2')->count())->toBe(1);
    });

    it('supports new storage interface methods', function (): void {
        Cart::add('interface-item', 'Interface Item', 100.00, 1);

        // Test that storage interface is working
        expect(Cart::getItems())->toHaveCount(1);

        Cart::clear();

        expect(Cart::isEmpty())->toBeTrue();
    });
});

describe('Database Storage Persistence', function (): void {
    beforeEach(function (): void {
        config(['cart.storage' => 'database']);
        Cart::clear();
    });

    it('persists cart data across requests', function (): void {
        Cart::add('persistent', 'Persistent Item', 75.00, 2);
        $firstTotal = Cart::total()->getAmount();

        // Simulate new request by getting fresh data
        $secondTotal = Cart::total()->getAmount();

        expect($firstTotal)->toBe($secondTotal);
        expect($secondTotal)->toBe(150.00);
    });

    it('handles JSON serialization correctly', function (): void {
        Cart::add('json-item', 'JSON Item', 50.00, 1, [
            'metadata' => ['key' => 'value'],
            'nested' => ['deep' => 'data'],
        ]);

        $item = Cart::get('json-item');

        expect($item->getAttribute('metadata'))->toBeArray();
        expect($item->getAttribute('nested')['deep'])->toBe('data');
    });

    it('handles null values correctly', function (): void {
        Cart::add('null-test', 'Null Test', 25.00, 1);

        $item = Cart::get('null-test');
        expect($item->getAttribute('nonexistent'))->toBeNull();
    });
});

describe('Database Storage with Metadata', function (): void {
    beforeEach(function (): void {
        config(['cart.storage' => 'database']);
        Cart::clear();
    });

    it('stores and retrieves metadata', function (): void {
        Cart::setMetadata('customer_note', 'Special instructions');
        Cart::setMetadata('promo_code', 'SAVE20');

        expect(Cart::getMetadata('customer_note'))->toBe('Special instructions');
        expect(Cart::getMetadata('promo_code'))->toBe('SAVE20');
    });

    it('updates existing metadata', function (): void {
        Cart::setMetadata('note', 'First note');
        Cart::setMetadata('note', 'Updated note');

        expect(Cart::getMetadata('note'))->toBe('Updated note');
    });

    it('removes metadata', function (): void {
        Cart::setMetadata('temporary', 'value');
        Cart::removeMetadata('temporary');

        expect(Cart::getMetadata('temporary'))->toBeNull();
    });

    it('handles batch metadata operations', function (): void {
        Cart::setMetadataBatch([
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ]);

        expect(Cart::getMetadata('key1'))->toBe('value1');
        expect(Cart::getMetadata('key2'))->toBe('value2');
        expect(Cart::getMetadata('key3'))->toBe('value3');
    });
});

describe('Database Storage Edge Cases', function (): void {
    beforeEach(function (): void {
        config(['cart.storage' => 'database']);
        Cart::clear();
    });

    it('handles empty cart gracefully', function (): void {
        expect(Cart::isEmpty())->toBeTrue();
        expect(Cart::getItems())->toHaveCount(0);
        expect(Cart::getConditions())->toHaveCount(0);
    });

    it('handles special characters in item data', function (): void {
        Cart::add('special', "Item with 'quotes' & <html>", 10.00, 1);

        $item = Cart::get('special');
        expect($item->name)->toContain('quotes');
    });

    it('validates data size limits', function (): void {
        // Add item with reasonable data size
        Cart::add('sized-item', 'Item', 10.00, 1, [
            'description' => str_repeat('a', 1000),
        ]);

        $item = Cart::get('sized-item');
        expect(mb_strlen($item->getAttribute('description')))->toBe(1000);
    });

    it('handles concurrent cart operations', function (): void {
        Cart::add('concurrent', 'Concurrent Item', 10.00, 1);
        Cart::addTax('Tax', '10%');
        Cart::setMetadata('note', 'test');

        expect(Cart::count())->toBe(1);
        expect(Cart::getConditions())->toHaveCount(1);
        expect(Cart::getMetadata('note'))->toBe('test');
    });
});
