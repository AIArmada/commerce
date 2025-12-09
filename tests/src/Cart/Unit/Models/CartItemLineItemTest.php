<?php

declare(strict_types=1);

use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Models\CartItem;
use AIArmada\CommerceSupport\Contracts\Payment\LineItemInterface;
use Akaunting\Money\Money;

describe('CartItem LineItemInterface implementation', function (): void {
    it('implements LineItemInterface', function (): void {
        $item = new CartItem(
            id: 'product-1',
            name: 'Test Product',
            price: 5000,
            quantity: 2,
        );

        expect($item)->toBeInstanceOf(LineItemInterface::class);
    });

    it('returns line item id', function (): void {
        $item = new CartItem(
            id: 'product-123',
            name: 'Test Product',
            price: 5000,
            quantity: 1,
        );

        expect($item->getLineItemId())->toBe('product-123');
    });

    it('returns line item name', function (): void {
        $item = new CartItem(
            id: 'product-1',
            name: 'Awesome Widget',
            price: 5000,
            quantity: 1,
        );

        expect($item->getLineItemName())->toBe('Awesome Widget');
    });

    it('returns line item price as Money', function (): void {
        $item = new CartItem(
            id: 'product-1',
            name: 'Test Product',
            price: 5000,
            quantity: 2,
        );

        $price = $item->getLineItemPrice();

        expect($price)->toBeInstanceOf(Money::class)
            ->and($price->getAmount())->toBe(5000);
    });

    it('returns line item quantity', function (): void {
        $item = new CartItem(
            id: 'product-1',
            name: 'Test Product',
            price: 5000,
            quantity: 3,
        );

        expect($item->getLineItemQuantity())->toBe(3);
    });

    it('returns line item discount as Money', function (): void {
        $discount = new CartCondition(
            name: 'item-discount',
            type: 'discount',
            target: 'items@item_discount/per-item',
            value: '-10%',
        );

        $item = new CartItem(
            id: 'product-1',
            name: 'Test Product',
            price: 10000,
            quantity: 1,
            conditions: [$discount],
        );

        $discountAmount = $item->getLineItemDiscount();

        expect($discountAmount)->toBeInstanceOf(Money::class)
            ->and($discountAmount->getAmount())->toEqual(1000); // 10% of 10000
    });

    it('returns zero discount when no discount conditions', function (): void {
        $item = new CartItem(
            id: 'product-1',
            name: 'Test Product',
            price: 5000,
            quantity: 1,
        );

        $discount = $item->getLineItemDiscount();

        expect($discount)->toBeInstanceOf(Money::class)
            ->and($discount->getAmount())->toBe(0);
    });

    it('returns line item tax percent from condition', function (): void {
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

        expect($item->getLineItemTaxPercent())->toBe(6.0);
    });

    it('returns line item tax percent from attribute', function (): void {
        $item = new CartItem(
            id: 'product-1',
            name: 'Test Product',
            price: 10000,
            quantity: 1,
            attributes: ['tax_percent' => 8.0],
        );

        expect($item->getLineItemTaxPercent())->toBe(8.0);
    });

    it('returns zero tax percent when not set', function (): void {
        $item = new CartItem(
            id: 'product-1',
            name: 'Test Product',
            price: 5000,
            quantity: 1,
        );

        expect($item->getLineItemTaxPercent())->toBe(0.0);
    });

    it('returns line item subtotal as Money', function (): void {
        $item = new CartItem(
            id: 'product-1',
            name: 'Test Product',
            price: 5000,
            quantity: 3,
        );

        $subtotal = $item->getLineItemSubtotal();

        expect($subtotal)->toBeInstanceOf(Money::class)
            ->and($subtotal->getAmount())->toBe(15000); // 5000 * 3
    });

    it('returns line item subtotal with discount applied', function (): void {
        $discount = new CartCondition(
            name: 'item-discount',
            type: 'discount',
            target: 'items@item_discount/per-item',
            value: '-1000',
        );

        $item = new CartItem(
            id: 'product-1',
            name: 'Test Product',
            price: 5000,
            quantity: 2,
            conditions: [$discount],
        );

        $subtotal = $item->getLineItemSubtotal();

        // 5000 - 1000 = 4000 per unit, * 2 = 8000
        expect($subtotal->getAmount())->toEqual(8000);
    });

    it('returns line item category from attributes', function (): void {
        $item = new CartItem(
            id: 'product-1',
            name: 'Test Product',
            price: 5000,
            quantity: 1,
            attributes: ['category' => 'electronics'],
        );

        expect($item->getLineItemCategory())->toBe('electronics');
    });

    it('returns null category when not set', function (): void {
        $item = new CartItem(
            id: 'product-1',
            name: 'Test Product',
            price: 5000,
            quantity: 1,
        );

        expect($item->getLineItemCategory())->toBeNull();
    });

    it('returns line item metadata', function (): void {
        $item = new CartItem(
            id: 'product-123',
            name: 'Test Product',
            price: 5000,
            quantity: 1,
            attributes: ['color' => 'red', 'size' => 'large'],
        );

        $metadata = $item->getLineItemMetadata();

        expect($metadata)->toBeArray()
            ->and($metadata['cart_item_id'])->toBe('product-123')
            ->and($metadata['attributes'])->toBe(['color' => 'red', 'size' => 'large']);
    });

    it('returns line item sku from attributes', function (): void {
        $item = new CartItem(
            id: 'product-1',
            name: 'Test Product',
            price: 5000,
            quantity: 1,
            attributes: ['sku' => 'SKU-12345'],
        );

        expect($item->getLineItemSku())->toBe('SKU-12345');
    });

    it('returns item id as sku when not set', function (): void {
        $item = new CartItem(
            id: 'product-xyz',
            name: 'Test Product',
            price: 5000,
            quantity: 1,
        );

        expect($item->getLineItemSku())->toBe('product-xyz');
    });

    it('returns line item description from attributes', function (): void {
        $item = new CartItem(
            id: 'product-1',
            name: 'Test Product',
            price: 5000,
            quantity: 1,
            attributes: ['description' => 'A wonderful product'],
        );

        expect($item->getLineItemDescription())->toBe('A wonderful product');
    });

    it('returns line item image url from attributes', function (): void {
        $item = new CartItem(
            id: 'product-1',
            name: 'Test Product',
            price: 5000,
            quantity: 1,
            attributes: ['image_url' => 'https://example.com/image.jpg'],
        );

        expect($item->getLineItemImageUrl())->toBe('https://example.com/image.jpg');
    });

    it('returns line item total as alias', function (): void {
        $item = new CartItem(
            id: 'product-1',
            name: 'Test Product',
            price: 3000,
            quantity: 4,
        );

        $total = $item->getLineItemTotal();

        expect($total)->toBeInstanceOf(Money::class)
            ->and($total->getAmount())->toBe(12000);
    });
});
