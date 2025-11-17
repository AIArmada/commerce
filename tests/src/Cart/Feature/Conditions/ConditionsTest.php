<?php

declare(strict_types=1);

use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Facades\Cart;

describe('Cart-Level Conditions', function (): void {
    beforeEach(function (): void {
        Cart::clear();
    });

    it('applies conditions to cart subtotal', function (): void {
        Cart::add('item', 'Item', 100.00, 1);

        $condition = new CartCondition(
            name: 'Tax',
            type: 'tax',
            target: 'cart@cart_subtotal/aggregate',
            value: '10%'
        );

        Cart::addCondition($condition);

        expect(Cart::total()->getAmount())->toBe(110.00);
    });

    it('applies multiple conditions in order', function (): void {
        Cart::add('item', 'Item', 100.00, 1);

        Cart::addCondition(new CartCondition(
            name: 'Discount',
            type: 'discount',
            target: 'cart@cart_subtotal/aggregate',
            value: '-10%',
            order: 1
        ));

        Cart::addCondition(new CartCondition(
            name: 'Tax',
            type: 'tax',
            target: 'cart@grand_total/aggregate',
            value: '10%',
            order: 2
        ));

        // 100 - 10% = 90, then 90 + 10% = 99
        expect(Cart::total()->getAmount())->toBe(99.00);
    });

    it('applies subtotal conditions before total conditions', function (): void {
        Cart::add('item', 'Item', 100.00, 1);

        Cart::addCondition(new CartCondition(
            name: 'Subtotal Tax',
            type: 'tax',
            target: 'cart@cart_subtotal/aggregate',
            value: '5%'
        ));

        Cart::addCondition(new CartCondition(
            name: 'Total Fee',
            type: 'fee',
            target: 'cart@grand_total/aggregate',
            value: '10.00'
        ));

        // 100 + 5% = 105, then 105 + 10 = 115
        expect(Cart::total()->getAmount())->toBe(115.00);
    });

    it('can remove specific conditions', function (): void {
        Cart::add('item', 'Item', 100.00, 1);
        Cart::addTax('VAT', '10%');
        Cart::addFee('Shipping', '5.00');

        Cart::removeCondition('VAT');

        expect(Cart::getConditions())->toHaveCount(1);
        expect(Cart::total()->getAmount())->toBe(105.00);
    });

    it('can clear all conditions', function (): void {
        Cart::add('item', 'Item', 100.00, 1);
        Cart::addTax('VAT', '10%');
        Cart::addFee('Shipping', '5.00');

        Cart::clearConditions();

        expect(Cart::getConditions())->toHaveCount(0);
        expect(Cart::total()->getAmount())->toBe(100.00);
    });

    it('can get conditions by type', function (): void {
        Cart::add('item', 'Item', 100.00, 1);
        Cart::addTax('VAT', '10%');
        Cart::addTax('State Tax', '5%');
        Cart::addFee('Shipping', '5.00');

        $taxes = Cart::getConditionsByType('tax');

        expect($taxes)->toHaveCount(2);
    });
});

describe('Item-Level Conditions', function (): void {
    beforeEach(function (): void {
        Cart::clear();
    });

    it('applies conditions to specific items', function (): void {
        Cart::add('item-1', 'Item 1', 100.00, 1);
        Cart::add('item-2', 'Item 2', 50.00, 1);

        Cart::addItemCondition('item-1', new CartCondition(
            name: 'Discount',
            type: 'discount',
            target: 'cart@cart_subtotal/aggregate',
            value: '-20%'
        ));

        // Item 1: 100 - 20% = 80, Item 2: 50
        expect(Cart::total()->getAmount())->toBe(130.00);
    });

    it('item conditions do not affect other items', function (): void {
        Cart::add('item-1', 'Item 1', 100.00, 1);
        Cart::add('item-2', 'Item 2', 100.00, 1);

        Cart::addItemCondition('item-1', new CartCondition(
            name: 'Special Discount',
            type: 'discount',
            target: 'cart@cart_subtotal/aggregate',
            value: '-50%'
        ));

        $item1 = Cart::get('item-1');
        $item2 = Cart::get('item-2');

        expect($item1->getSubtotal()->getAmount())->toBe(50.00);
        expect($item2->getSubtotal()->getAmount())->toBe(100.00);
    });

    it('can remove item-specific conditions', function (): void {
        Cart::add('item', 'Item', 100.00, 1);

        Cart::addItemCondition('item', new CartCondition(
            name: 'Discount',
            type: 'discount',
            target: 'cart@cart_subtotal/aggregate',
            value: '-10%'
        ));

        Cart::removeItemCondition('item', 'Discount');

        $item = Cart::get('item');
        expect($item->conditions)->toHaveCount(0);
        expect(Cart::total()->getAmount())->toBe(100.00);
    });

    it('can clear all item conditions', function (): void {
        Cart::add('item', 'Item', 100.00, 1);

        Cart::addItemCondition('item', new CartCondition(name: 'Discount1', type: 'discount', target: 'cart@cart_subtotal/aggregate', value: '-5%'));
        Cart::addItemCondition('item', new CartCondition(name: 'Discount2', type: 'discount', target: 'cart@cart_subtotal/aggregate', value: '-5%'));

        Cart::clearItemConditions('item');

        $item = Cart::get('item');
        expect($item->conditions)->toHaveCount(0);
    });
});

describe('Condition Calculations', function (): void {
    beforeEach(function (): void {
        Cart::clear();
    });

    it('calculates percentage discounts correctly', function (): void {
        Cart::add('item', 'Item', 100.00, 1);
        Cart::addDiscount('SAVE20', '-20%');

        expect(Cart::total()->getAmount())->toBe(80.00);
    });

    it('calculates fixed amount discounts correctly', function (): void {
        Cart::add('item', 'Item', 100.00, 1);
        Cart::addDiscount('SAVE15', '-15.00');

        expect(Cart::total()->getAmount())->toBe(85.00);
    });

    it('calculates percentage fees correctly', function (): void {
        Cart::add('item', 'Item', 100.00, 1);
        Cart::addFee('Service Fee', '5%');

        expect(Cart::total()->getAmount())->toBe(105.00);
    });

    it('calculates fixed amount fees correctly', function (): void {
        Cart::add('item', 'Item', 100.00, 1);
        Cart::addFee('Handling', '3.50');

        expect(Cart::total()->getAmount())->toBe(103.50);
    });

    it('prevents negative totals', function (): void {
        Cart::add('item', 'Item', 10.00, 1);
        Cart::addDiscount('MASSIVE', '-100.00');

        expect(Cart::total()->getAmount())->toBeGreaterThanOrEqual(0.00);
    });
});

describe('Condition Compatibility', function (): void {
    beforeEach(function (): void {
        Cart::clear();
    });

    it('handles missing features from legacy implementations', function (): void {
        Cart::add('item', 'Item', 100.00, 1);

        // Get subtotal without conditions (legacy feature)
        $subtotalWithoutConditions = Cart::subtotalWithoutConditions();

        Cart::addTax('VAT', '10%');

        expect($subtotalWithoutConditions->getAmount())->toBe(100.00);
        expect(Cart::subtotalWithoutConditions()->getAmount())->toBe(100.00); // Always 100 (base items)
        expect(Cart::subtotal()->getAmount())->toBe(110.00); // 100 + 10% tax
        expect(Cart::total()->getAmount())->toBe(110.00); // Same as subtotal since no total-level conditions
    });

    it('counts items using count() method', function (): void {
        Cart::add('item-1', 'Item 1', 10.00, 1);
        Cart::add('item-2', 'Item 2', 20.00, 2);

        expect(Cart::count())->toBe(3); // count() returns total quantity: 1 + 2 = 3
        expect(Cart::getTotalQuantity())->toBe(3); // Total quantity
    });
});
