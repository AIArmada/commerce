<?php

declare(strict_types=1);

use AIArmada\Cart\Facades\Cart;

describe('Shipping Conditions', function (): void {
    beforeEach(function (): void {
        Cart::clear();
    });

    it('can add shipping using addShipping method', function (): void {
        Cart::add('item', 'Item', 50.00, 1);

        Cart::addShipping('Standard Shipping', 10.00);

        expect(Cart::getConditions())->toHaveCount(1);
        expect(Cart::total()->getAmount())->toBe(60.00);

        $shipping = Cart::getConditions()->first();
        expect($shipping->getName())->toBe('Standard Shipping');
        expect($shipping->getType())->toBe('shipping');
    });

    it('can remove shipping using removeShipping method', function (): void {
        Cart::add('item', 'Item', 50.00, 1);
        Cart::addShipping('Express Shipping', 20.00);

        Cart::removeShipping();

        expect(Cart::getConditionsByType('shipping'))->toHaveCount(0);
        expect(Cart::total()->getAmount())->toBe(50.00);
    });

    it('replaces existing shipping when adding new shipping', function (): void {
        Cart::add('item', 'Item', 50.00, 1);
        Cart::addShipping('Standard', 10.00);
        Cart::addShipping('Express', 25.00);

        $shippingConditions = Cart::getConditionsByType('shipping');

        expect($shippingConditions)->toHaveCount(1);
        expect($shippingConditions->first()->getName())->toBe('Express');
        expect(Cart::total()->getAmount())->toBe(75.00);
    });

    it('handles string shipping values', function (): void {
        Cart::add('item', 'Item', 100.00, 1);

        Cart::addShipping('Shipping', '15.00');

        expect(Cart::total()->getAmount())->toBe(115.00);
    });

    it('handles numeric shipping values', function (): void {
        Cart::add('item', 'Item', 100.00, 1);

        Cart::addShipping('Shipping', 20);

        expect(Cart::total()->getAmount())->toBe(120.00);
    });

    it('can add percentage-based shipping', function (): void {
        Cart::add('item', 'Item', 100.00, 1);

        Cart::addShipping('Calculated Shipping', '10%');

        expect(Cart::total()->getAmount())->toBe(110.00);
    });

    it('works with Cart facade', function (): void {
        Cart::add('item', 'Item', 50.00, 1);

        Cart::addShipping('Facade Shipping', 5.00);

        expect(Cart::total()->getAmount())->toBe(55.00);
    });
});

describe('Multiple Shipping Scenarios', function (): void {
    beforeEach(function (): void {
        Cart::clear();
    });

    it('applies shipping after item conditions', function (): void {
        Cart::add('item', 'Item', 100.00, 1);
        Cart::addDiscount('SAVE10', '-10%');
        Cart::addShipping('Shipping', 15.00);

        // 100 - 10% = 90, then 90 + 15 = 105
        expect(Cart::total()->getAmount())->toBe(105.00);
    });

    it('applies shipping after tax (shipping targets total)', function (): void {
        Cart::add('item', 'Item', 100.00, 1);
        Cart::addShipping('Shipping', 10.00);
        Cart::addTax('VAT', '10%');

        // Tax applies to subtotal: 100 + 10% = 110
        // Shipping applies to total: 110 + 10 = 120
        expect(Cart::total()->getAmount())->toBe(120.00);
    });

    it('calculates free shipping correctly', function (): void {
        Cart::add('item', 'Item', 100.00, 1);

        Cart::addShipping('Free Shipping', 0.00);

        expect(Cart::total()->getAmount())->toBe(100.00);
    });
});

describe('Shipping Methods', function (): void {
    beforeEach(function (): void {
        Cart::clear();
    });

    it('supports different shipping methods', function (): void {
        $methods = [
            ['name' => 'Standard', 'cost' => 5.00],
            ['name' => 'Express', 'cost' => 15.00],
            ['name' => 'Overnight', 'cost' => 30.00],
        ];

        Cart::add('item', 'Item', 50.00, 1);

        foreach ($methods as $method) {
            Cart::clear();
            Cart::add('item', 'Item', 50.00, 1);
            Cart::addShipping($method['name'], $method['cost']);

            expect(Cart::total()->getAmount())->toBe(50.00 + $method['cost']);
        }
    });

    it('can query current shipping method', function (): void {
        Cart::add('item', 'Item', 50.00, 1);
        Cart::addShipping('Express Shipping', 25.00);

        $shipping = Cart::getConditionsByType('shipping')->first();

        expect($shipping)->not->toBeNull();
        expect($shipping->getName())->toBe('Express Shipping');
        expect($shipping->getCalculatedValue(50.00))->toBe(25.00);
    });
});
