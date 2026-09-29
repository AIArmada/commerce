<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\CommerceSupport\Contracts\Payment\CheckoutableInterface;
use AIArmada\CommerceSupport\Contracts\Payment\LineItemInterface;
use Akaunting\Money\Money;

beforeEach(function (): void {
    $this->storage = new InMemoryStorage;
    $this->cart = new Cart($this->storage, 'checkoutable-test');

    $this->cart->add('sku-1', 'Widget', 1000, 2);
    $this->cart->add('sku-2', 'Gadget', 500, 1);
});

it('implements the checkoutable contract', function (): void {
    expect($this->cart)->toBeInstanceOf(CheckoutableInterface::class);

    $items = iterator_to_array($this->cart->getCheckoutLineItems(), false);

    expect($items)->toHaveCount(2);

    foreach ($items as $item) {
        expect($item)->toBeInstanceOf(LineItemInterface::class);
    }
});

it('exposes money totals in the cart currency', function (): void {
    expect($this->cart->getCheckoutSubtotal())->toBeInstanceOf(Money::class);
    expect($this->cart->getCheckoutDiscount())->toBeInstanceOf(Money::class);
    expect($this->cart->getCheckoutTax())->toBeInstanceOf(Money::class);
    expect($this->cart->getCheckoutTotal())->toBeInstanceOf(Money::class);

    expect((int) $this->cart->getCheckoutSubtotal()->getAmount())->toBe(2500);
    expect((int) $this->cart->getCheckoutDiscount()->getAmount())->toBe(0);
    expect((int) $this->cart->getCheckoutTax()->getAmount())->toBe(0);
    expect((int) $this->cart->getCheckoutTotal()->getAmount())->toBe(2500);

    expect($this->cart->getCheckoutCurrency())->toBeString()->toHaveLength(3);
});

it('reconciles subtotal minus discount plus tax to the total', function (): void {
    $subtotal = (int) $this->cart->getCheckoutSubtotal()->getAmount();
    $discount = (int) $this->cart->getCheckoutDiscount()->getAmount();
    $tax = (int) $this->cart->getCheckoutTax()->getAmount();
    $total = (int) $this->cart->getCheckoutTotal()->getAmount();

    expect($total)->toBe($subtotal - $discount + $tax);
});

it('exposes reference, notes, and metadata', function (): void {
    expect($this->cart->getCheckoutReference())->toBeString()->not->toBeEmpty();
    expect($this->cart->getCheckoutNotes())->toBeNull();

    $metadata = $this->cart->getCheckoutMetadata();

    expect($metadata)->toBeArray();
    expect($metadata['identifier'])->toBe('checkoutable-test');
    expect($metadata['instance'])->toBe('default');
});

it('escapes the reference fallback unambiguously', function (): void {
    $storage = new class extends InMemoryStorage
    {
        public function getId(string $identifier, string $instance): ?string
        {
            return null;
        }
    };

    $cart = new Cart($storage, 'a:b', instanceName: 'c:d');

    expect($cart->getCheckoutReference())->toBe('a%3Ab:c%3Ad');
});

it('keeps generated identity keys ahead of user metadata', function (): void {
    $this->cart->setMetadata('instance', 'other');
    $this->cart->setMetadata('identifier', 'other');

    $metadata = $this->cart->getCheckoutMetadata();

    expect($metadata['identifier'])->toBe('checkoutable-test');
    expect($metadata['instance'])->toBe('default');
});

it('folds cart discounts into the discount term', function (): void {
    $this->cart->addDiscount('SAVE10', '10%');

    expect((int) $this->cart->getCheckoutSubtotal()->getAmount())->toBe(2500);
    expect((int) $this->cart->getCheckoutDiscount()->getAmount())->toBe(250);
    expect((int) $this->cart->getCheckoutTax()->getAmount())->toBe(0);
    expect((int) $this->cart->getCheckoutTotal()->getAmount())->toBe(2250);
});

it('reports net surcharges on the tax term without negative money', function (): void {
    $this->cart->addTax('VAT', '10%');

    expect((int) $this->cart->getCheckoutSubtotal()->getAmount())->toBe(2500);
    expect((int) $this->cart->getCheckoutDiscount()->getAmount())->toBe(0);
    expect((int) $this->cart->getCheckoutTax()->getAmount())->toBe(250);
    expect((int) $this->cart->getCheckoutTotal()->getAmount())->toBe(2750);
});

it('includes attribute-declared line taxes in the tax and total terms', function (): void {
    $this->cart->add('sku-3', 'Taxed Widget', 1000, 2, ['tax_percent' => 8.0]);

    $subtotal = (int) $this->cart->getCheckoutSubtotal()->getAmount();
    $discount = (int) $this->cart->getCheckoutDiscount()->getAmount();
    $tax = (int) $this->cart->getCheckoutTax()->getAmount();
    $total = (int) $this->cart->getCheckoutTotal()->getAmount();

    expect($subtotal)->toBe(4500);
    expect($discount)->toBe(0);
    expect($tax)->toBe(160);
    expect($total)->toBe(4660);
    expect($total)->toBe($subtotal - $discount + $tax);
});

it('keeps attribute tax on the tax term when discounts pull the total below the subtotal', function (): void {
    $this->cart->add('sku-3', 'Taxed Widget', 1000, 2, ['tax_percent' => 8.0]);
    $this->cart->addDiscount('SAVE10', '10%');

    $subtotal = (int) $this->cart->getCheckoutSubtotal()->getAmount();
    $discount = (int) $this->cart->getCheckoutDiscount()->getAmount();
    $tax = (int) $this->cart->getCheckoutTax()->getAmount();
    $total = (int) $this->cart->getCheckoutTotal()->getAmount();

    expect($subtotal)->toBe(4500);
    expect($discount)->toBe(450);
    expect($tax)->toBe(160);
    expect($total)->toBe(4210);
    expect($total)->toBe($subtotal - $discount + $tax);
});

it('adds attribute tax on top of condition surcharges without double counting', function (): void {
    $this->cart->add('sku-3', 'Taxed Widget', 1000, 2, ['tax_percent' => 8.0]);
    $this->cart->addTax('VAT', '10%');

    $subtotal = (int) $this->cart->getCheckoutSubtotal()->getAmount();
    $discount = (int) $this->cart->getCheckoutDiscount()->getAmount();
    $tax = (int) $this->cart->getCheckoutTax()->getAmount();
    $total = (int) $this->cart->getCheckoutTotal()->getAmount();

    expect($subtotal)->toBe(4500);
    expect($discount)->toBe(0);
    expect($tax)->toBe(610);
    expect($total)->toBe(5110);
    expect($total)->toBe($subtotal - $discount + $tax);
});

it('reports conditioned line prices with zero line discounts', function (): void {
    $discount = new CartCondition(
        name: 'half-off',
        type: 'discount',
        target: 'items@item_discount/per-item',
        value: '-50%',
    );

    $this->cart->add('sku-3', 'Half Off', 1000, 2, [], [$discount]);

    $lines = iterator_to_array($this->cart->getCheckoutLineItems(), false);

    expect($lines)->toHaveCount(3);

    foreach ($lines as $line) {
        expect((int) $line->getLineItemDiscount()->getAmount())->toBe(0);
    }

    expect((int) $lines[2]->getLineItemPrice()->getAmount())->toBe(500);
    expect((int) $this->cart->getCheckoutSubtotal()->getAmount())->toBe(3500);

    $subtotal = (int) $this->cart->getCheckoutSubtotal()->getAmount();
    $discount = (int) $this->cart->getCheckoutDiscount()->getAmount();
    $tax = (int) $this->cart->getCheckoutTax()->getAmount();
    $total = (int) $this->cart->getCheckoutTotal()->getAmount();

    expect($total)->toBe($subtotal - $discount + $tax);
});
