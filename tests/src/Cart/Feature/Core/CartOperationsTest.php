<?php

declare(strict_types=1);

use AIArmada\Cart\Facades\Cart;

describe('Cart Operations', function (): void {
    beforeEach(function (): void {
        Cart::clear();
    });

    it('can access cart facade and perform basic operations', function (): void {
        expect(Cart::isEmpty())->toBeTrue();
        expect(Cart::count())->toBe(0);
        expect(Cart::total()->getAmount())->toBe(0.0);

        $item = Cart::add('test-product', 'Test Product', 10.99, 2);

        expect(Cart::isEmpty())->toBeFalse();
        expect(Cart::getItems())->toHaveCount(1);
        expect(Cart::getTotalQuantity())->toBe(2);
        expect(Cart::subtotal()->getAmount())->toBe(21.98);
        expect($item->id)->toBe('test-product');
        expect($item->name)->toBe('Test Product');
        expect($item->price)->toBe(10.99);
        expect($item->quantity)->toBe(2);
    });

    it('can add multiple items to cart', function (): void {
        Cart::add('product-1', 'Product 1', 15.00, 1);
        Cart::add('product-2', 'Product 2', 25.00, 2);
        Cart::add('product-3', 'Product 3', 35.00, 3);

        expect(Cart::getItems())->toHaveCount(3);
        expect(Cart::getTotalQuantity())->toBe(6);
        expect(Cart::subtotal()->getAmount())->toBe(170.00);
    });

    it('can update item quantities', function (): void {
        Cart::add('product-1', 'Product 1', 15.00, 1);
        Cart::add('product-2', 'Product 2', 25.00, 2);

        Cart::update('product-2', ['quantity' => 5]);

        expect(Cart::getTotalQuantity())->toBe(8); // 1 + 2+5
        expect(Cart::subtotal()->getAmount())->toBe(190.00); // 15 + 175
    });

    it('can remove items from cart', function (): void {
        Cart::add('product-1', 'Product 1', 15.00, 1);
        Cart::add('product-2', 'Product 2', 25.00, 2);

        Cart::remove('product-1');

        expect(Cart::getItems())->toHaveCount(1);
        expect(Cart::getTotalQuantity())->toBe(2);
        expect(Cart::subtotal()->getAmount())->toBe(50.00);
    });

    it('can clear entire cart', function (): void {
        Cart::add('product-1', 'Product 1', 15.00, 1);
        Cart::add('product-2', 'Product 2', 25.00, 2);

        Cart::clear();

        expect(Cart::isEmpty())->toBeTrue();
        expect(Cart::count())->toBe(0);
        expect(Cart::getTotalQuantity())->toBe(0);
    });

    it('can search cart items', function (): void {
        Cart::add('product-1', 'Cheap Item', 10.00, 1);
        Cart::add('product-2', 'Expensive Item', 100.00, 1);

        $expensive = Cart::search(fn ($item) => $item->price > 50.00);

        expect($expensive)->toHaveCount(1);
        expect($expensive->first()->name)->toBe('Expensive Item');
    });

    it('merges quantities when adding duplicate items', function (): void {
        Cart::add('duplicate', 'Duplicate Test', 10.00, 1);
        Cart::add('duplicate', 'Duplicate Test', 10.00, 2);

        expect(Cart::getItems())->toHaveCount(1);
        expect(Cart::get('duplicate')->quantity)->toBe(3);
    });

    it('handles updating non-existent items gracefully', function (): void {
        $result = Cart::update('non-existent', ['quantity' => 5]);

        expect($result)->toBeNull();
    });

    it('handles removing non-existent items gracefully', function (): void {
        Cart::add('existing', 'Existing Item', 10.00, 1);

        Cart::remove('non-existent');

        expect(Cart::getItems())->toHaveCount(1);
    });

    it('returns null when getting non-existent items', function (): void {
        expect(Cart::get('non-existent'))->toBeNull();
    });

    it('can add single item as array', function (): void {
        $item = Cart::add([
            'id' => 'item-1',
            'name' => 'Single Item',
            'price' => 100.00,
            'quantity' => 2,
            'attributes' => ['color' => 'blue'],
        ]);

        expect($item)->toBeInstanceOf(AIArmada\Cart\Models\CartItem::class);
        expect(Cart::getItems())->toHaveCount(1);
        expect(Cart::get('item-1')->name)->toBe('Single Item');
        expect(Cart::get('item-1')->price)->toBe(100.00);
        expect(Cart::get('item-1')->quantity)->toBe(2);
        expect(Cart::get('item-1')->attributes->get('color'))->toBe('blue');
    });

    it('can add multiple items as array', function (): void {
        $items = Cart::add([
            [
                'id' => 'item-1',
                'name' => 'Item 1',
                'price' => 10.00,
                'quantity' => 1,
            ],
            [
                'id' => 'item-2',
                'name' => 'Item 2',
                'price' => 20.00,
                'quantity' => 2,
            ],
            [
                'id' => 'item-3',
                'name' => 'Item 3',
                'price' => 30.00,
                'quantity' => 3,
            ],
        ]);

        expect($items)->toBeInstanceOf(AIArmada\Cart\Collections\CartCollection::class);
        expect($items)->toHaveCount(3);
        expect(Cart::getItems())->toHaveCount(3);
        expect(Cart::getTotalQuantity())->toBe(6);
        expect(Cart::subtotal()->getAmount())->toBe(140.00); // 10 + 40 + 90
    });
});

describe('Cart Conditions', function (): void {
    beforeEach(function (): void {
        Cart::clear();
    });

    it('can add and apply cart conditions', function (): void {
        Cart::add('taxable-item', 'Taxable Item', 100.00, 1);

        $taxCondition = new AIArmada\Cart\Conditions\CartCondition(
            name: 'VAT',
            type: 'tax',
            target: 'cart@cart_subtotal/aggregate',
            value: '10%'
        );

        Cart::addCondition($taxCondition);

        expect(Cart::getConditions())->toHaveCount(1);
        expect(Cart::total()->getAmount())->toBe(110.00);
    });

    it('can remove cart conditions', function (): void {
        Cart::add('taxable-item', 'Taxable Item', 100.00, 1);

        $taxCondition = new AIArmada\Cart\Conditions\CartCondition(
            name: 'VAT',
            type: 'tax',
            target: 'cart@cart_subtotal/aggregate',
            value: '10%'
        );

        Cart::addCondition($taxCondition);
        Cart::removeCondition('VAT');

        expect(Cart::getConditions())->toHaveCount(0);
        expect(Cart::total()->getAmount())->toBe(100.00);
    });

    it('can add discount conditions', function (): void {
        Cart::add('item', 'Item', 100.00, 1);

        Cart::addDiscount('SAVE10', '10%');

        expect(Cart::total()->getAmount())->toBe(90.00);
    });

    it('can add fee conditions', function (): void {
        Cart::add('item', 'Test Item', 100.00, 1);

        Cart::addFee('Processing Fee', '10.00');

        $total = Cart::total()->getAmount();
        expect($total)->toBeGreaterThan(100.00);
    });

    it('can add tax conditions', function (): void {
        Cart::add('item', 'Item', 100.00, 1);

        Cart::addTax('Sales Tax', '8.5%');

        expect(Cart::total()->getAmount())->toBe(108.50);
    });
});

describe('Cart Data Export', function (): void {
    beforeEach(function (): void {
        Cart::clear();
    });

    it('exports cart data to array', function (): void {
        Cart::add('persistent-item', 'Persistent Item', 50.00, 2);

        $cartArray = Cart::toArray();

        expect($cartArray)->toHaveKey('items');
        expect($cartArray['items'])->toHaveCount(1);
        expect($cartArray['quantity'])->toBe(2);
        expect($cartArray['subtotal'])->toBe(100.00);
    });

    it('includes conditions in export', function (): void {
        Cart::add('item', 'Item', 100.00, 1);
        Cart::addTax('VAT', '10%');

        $cartArray = Cart::toArray();

        expect($cartArray)->toHaveKey('conditions');
        expect($cartArray['conditions'])->toHaveCount(1);
        expect($cartArray['total'])->toBe(110.00);
    });
});

describe('Enhanced API', function (): void {
    beforeEach(function (): void {
        Cart::clear();
    });

    it('provides intuitive method aliases', function (): void {
        Cart::add('item', 'Item', 50.00, 1);

        expect(Cart::subtotal())->toBeInstanceOf(Akaunting\Money\Money::class);
        expect(Cart::total())->toBeInstanceOf(Akaunting\Money\Money::class);
        expect(Cart::subtotal()->getAmount())->toBe(50.00);
    });

    it('can group items by attributes', function (): void {
        Cart::add('item-1', 'Item 1', 10.00, 1, ['category' => 'electronics']);
        Cart::add('item-2', 'Item 2', 20.00, 1, ['category' => 'electronics']);
        Cart::add('item-3', 'Item 3', 30.00, 1, ['category' => 'books']);

        $grouped = Cart::getItems()->groupBy(fn ($item) => $item->getAttribute('category'));

        expect($grouped)->toHaveKey('electronics');
        expect($grouped)->toHaveKey('books');
        expect($grouped['electronics'])->toHaveCount(2);
        expect($grouped['books'])->toHaveCount(1);
    });

    it('can filter items by attributes', function (): void {
        Cart::add('item-1', 'Item 1', 10.00, 1, ['featured' => true]);
        Cart::add('item-2', 'Item 2', 20.00, 1, ['featured' => false]);

        $featured = Cart::getItems()->filter(fn ($item) => $item->getAttribute('featured') === true);

        expect($featured)->toHaveCount(1);
    });
});
