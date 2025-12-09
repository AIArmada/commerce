<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\CommerceSupport\Contracts\Payment\CheckoutableInterface;
use AIArmada\CommerceSupport\Contracts\Payment\LineItemInterface;
use Akaunting\Money\Money;

describe('Cart CheckoutableInterface implementation', function (): void {
    beforeEach(function (): void {
        $this->storage = new InMemoryStorage;
        $this->cart = new Cart($this->storage, 'test-user');
    });

    it('implements CheckoutableInterface', function (): void {
        expect($this->cart)->toBeInstanceOf(CheckoutableInterface::class);
    });

    it('returns checkout line items as LineItemInterface instances', function (): void {
        $this->cart->add('product-1', 'Widget', 5000, 2);
        $this->cart->add('product-2', 'Gadget', 3000, 1);

        $lineItems = $this->cart->getCheckoutLineItems();

        expect($lineItems)->toBeIterable();

        foreach ($lineItems as $item) {
            expect($item)->toBeInstanceOf(LineItemInterface::class);
        }
    });

    it('returns checkout subtotal as Money', function (): void {
        $this->cart->add('product-1', 'Widget', 5000, 2);
        $this->cart->add('product-2', 'Gadget', 3000, 1);

        $subtotal = $this->cart->getCheckoutSubtotal();

        expect($subtotal)->toBeInstanceOf(Money::class)
            ->and($subtotal->getAmount())->toEqual(13000); // (5000 * 2) + 3000
    });

    it('returns checkout discount as Money', function (): void {
        $this->cart->add('product-1', 'Widget', 10000, 1);
        $this->cart->addDiscount('coupon', '-10%');

        $discount = $this->cart->getCheckoutDiscount();

        expect($discount)->toBeInstanceOf(Money::class);
        // The discount should be calculated based on conditions
        expect($discount->getAmount())->toBeGreaterThanOrEqual(0);
    });

    it('returns checkout tax as Money', function (): void {
        $this->cart->add('product-1', 'Widget', 10000, 1);
        $this->cart->addTax('sst', '+6%');

        $tax = $this->cart->getCheckoutTax();

        expect($tax)->toBeInstanceOf(Money::class);
        // Tax should be positive
        expect($tax->getAmount())->toBeGreaterThanOrEqual(0);
    });

    it('returns checkout total as Money', function (): void {
        $this->cart->add('product-1', 'Widget', 5000, 2);

        $total = $this->cart->getCheckoutTotal();

        expect($total)->toBeInstanceOf(Money::class)
            ->and($total->getAmount())->toEqual(10000);
    });

    it('returns checkout currency', function (): void {
        $currency = $this->cart->getCheckoutCurrency();

        expect($currency)->toBeString()
            ->and($currency)->toBe('USD'); // Default from config
    });

    it('returns checkout reference', function (): void {
        $this->cart->add('product-1', 'Widget', 5000, 1);

        $reference = $this->cart->getCheckoutReference();

        expect($reference)->toBeString()
            ->and($reference)->not->toBeEmpty();
    });

    it('returns checkout reference using cart ID when available', function (): void {
        // The cart might have an ID from the storage layer
        $this->cart->add('product-1', 'Widget', 5000, 1);

        $reference = $this->cart->getCheckoutReference();

        // Should return a valid reference string
        expect($reference)->toBeString();

        // If cart has UUID-based ID, reference should contain it
        $cartId = $this->cart->getId();
        if ($cartId !== null) {
            expect($reference)->toBe($cartId);
        }
    });

    it('returns checkout notes from metadata', function (): void {
        $this->cart->add('product-1', 'Widget', 5000, 1);
        $this->cart->setMetadata('notes', 'Please gift wrap');

        $notes = $this->cart->getCheckoutNotes();

        expect($notes)->toBe('Please gift wrap');
    });

    it('returns checkout notes from checkout_notes metadata', function (): void {
        $this->cart->add('product-1', 'Widget', 5000, 1);
        $this->cart->setMetadata('checkout_notes', 'Special delivery');

        $notes = $this->cart->getCheckoutNotes();

        expect($notes)->toBe('Special delivery');
    });

    it('returns null notes when not set', function (): void {
        $this->cart->add('product-1', 'Widget', 5000, 1);

        $notes = $this->cart->getCheckoutNotes();

        expect($notes)->toBeNull();
    });

    it('returns checkout metadata with cart info', function (): void {
        $this->cart->add('product-1', 'Widget', 5000, 2);
        $this->cart->add('product-2', 'Gadget', 3000, 3);

        $metadata = $this->cart->getCheckoutMetadata();

        expect($metadata)->toBeArray()
            ->and($metadata)->toHaveKey('cart_identifier')
            ->and($metadata)->toHaveKey('cart_instance')
            ->and($metadata)->toHaveKey('item_count')
            ->and($metadata)->toHaveKey('total_quantity')
            ->and($metadata['cart_identifier'])->toBe('test-user')
            ->and($metadata['item_count'])->toBe(2)
            ->and($metadata['total_quantity'])->toBe(5); // 2 + 3
    });

    it('includes shipping method in metadata when set', function (): void {
        $this->cart->add('product-1', 'Widget', 5000, 1);
        $this->cart->addShipping('Express Delivery', 1500, null, 'express');

        $metadata = $this->cart->getCheckoutMetadata();

        expect($metadata)->toHaveKey('shipping_method')
            ->and($metadata['shipping_method'])->toBe('express');
    });

    it('includes conditions summary in metadata when conditions exist', function (): void {
        $this->cart->add('product-1', 'Widget', 10000, 1);
        $this->cart->addDiscount('coupon', '-10%');
        $this->cart->addTax('sst', '+6%');

        $metadata = $this->cart->getCheckoutMetadata();

        expect($metadata)->toHaveKey('conditions')
            ->and($metadata['conditions'])->toBeArray()
            ->and(count($metadata['conditions']))->toBe(2);

        $conditionNames = array_column($metadata['conditions'], 'name');
        expect($conditionNames)->toContain('coupon')
            ->and($conditionNames)->toContain('sst');
    });

    it('returns empty line items for empty cart', function (): void {
        $lineItems = iterator_to_array($this->cart->getCheckoutLineItems());

        expect($lineItems)->toBeEmpty();
    });

    it('returns zero totals for empty cart', function (): void {
        expect($this->cart->getCheckoutSubtotal()->getAmount())->toEqual(0)
            ->and($this->cart->getCheckoutTotal()->getAmount())->toEqual(0)
            ->and($this->cart->getCheckoutDiscount()->getAmount())->toEqual(0)
            ->and($this->cart->getCheckoutTax()->getAmount())->toEqual(0);
    });

    it('line items implement all required interface methods', function (): void {
        $this->cart->add('product-1', 'Premium Widget', 8000, 2, [
            'sku' => 'SKU-001',
            'category' => 'electronics',
            'description' => 'A premium widget',
        ]);

        $lineItems = iterator_to_array($this->cart->getCheckoutLineItems());
        $item = $lineItems['product-1'];

        expect($item->getLineItemId())->toBe('product-1')
            ->and($item->getLineItemName())->toBe('Premium Widget')
            ->and($item->getLineItemPrice())->toBeInstanceOf(Money::class)
            ->and($item->getLineItemQuantity())->toBe(2)
            ->and($item->getLineItemDiscount())->toBeInstanceOf(Money::class)
            ->and($item->getLineItemTaxPercent())->toBe(0.0)
            ->and($item->getLineItemSubtotal())->toBeInstanceOf(Money::class)
            ->and($item->getLineItemCategory())->toBe('electronics')
            ->and($item->getLineItemMetadata())->toBeArray();
    });
});
