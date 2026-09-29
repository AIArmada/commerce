<?php

declare(strict_types=1);

use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Models\CartCheckoutLineItem;
use AIArmada\Cart\Models\CartItem;

describe('CartCheckoutLineItem', function (): void {
    it('reports zero tax percent for a tax-conditioned item', function (): void {
        $tax = new CartCondition(
            name: 'sst',
            type: 'tax',
            target: 'items@item_discount/per-item',
            value: '+6%',
        );

        $item = new CartItem(
            id: 'product-1',
            name: 'Test Product',
            price: 10000,
            quantity: 1,
            conditions: [$tax],
        );

        $line = new CartCheckoutLineItem($item);

        expect($item->getLineItemTaxPercent())->toBe(6.0)
            ->and($line->getLineItemTaxPercent())->toBe(0.0);
    });

    it('passes an attribute tax rate through', function (): void {
        $item = new CartItem(
            id: 'product-1',
            name: 'Test Product',
            price: 10000,
            quantity: 1,
            attributes: ['tax_percent' => 8.0],
        );

        $line = new CartCheckoutLineItem($item);

        expect($line->getLineItemTaxPercent())->toBe(8.0);
    });

    it('zeroes the rate when a tax condition and an attribute rate coexist', function (): void {
        $tax = new CartCondition(
            name: 'sst',
            type: 'tax',
            target: 'items@item_discount/per-item',
            value: '+6%',
        );

        $item = new CartItem(
            id: 'product-1',
            name: 'Test Product',
            price: 10000,
            quantity: 1,
            attributes: ['tax_percent' => 8.0],
            conditions: [$tax],
        );

        $line = new CartCheckoutLineItem($item);

        expect($line->getLineItemTaxPercent())->toBe(0.0);
    });

    it('still passes price, quantity, and category through', function (): void {
        $item = new CartItem(
            id: 'product-1',
            name: 'Test Product',
            price: 10000,
            quantity: 2,
            attributes: ['category' => 'Books'],
        );

        $line = new CartCheckoutLineItem($item);

        expect($line->getLineItemName())->toBe('Test Product')
            ->and($line->getLineItemQuantity())->toBe(2)
            ->and($line->getLineItemCategory())->toBe('Books')
            ->and($line->getLineItemPrice()->getAmount())->toBe($item->getLineItemPrice()->getAmount());
    });
});
