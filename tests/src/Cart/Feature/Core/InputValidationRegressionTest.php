<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Exceptions\InvalidCartItemException;
use AIArmada\Cart\Models\CartItem;
use AIArmada\Cart\Models\CartModel;
use AIArmada\Cart\Testing\InMemoryStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['cart.events' => false]);

    $this->cart = new Cart(new InMemoryStorage, 'validation-test');
});

it('imports nothing when any batch row is invalid', function (): void {
    expect(fn (): mixed => $this->cart->add([
        ['id' => 'good-1', 'name' => 'Good', 'price' => 100, 'quantity' => 1],
        ['name' => 'Missing Id', 'price' => 100, 'quantity' => 1],
    ]))->toThrow(InvalidCartItemException::class);

    expect($this->cart->getItems())->toHaveCount(0);
});

it('rejects malformed quantities with domain exceptions', function (): void {
    $this->cart->add('qty-1', 'Qty', 100, 2);

    expect(fn (): mixed => $this->cart->update('qty-1', ['quantity' => 'two']))
        ->toThrow(InvalidCartItemException::class, 'must be an integer');

    expect(fn (): mixed => $this->cart->update('qty-1', ['quantity' => ['value' => 'many']]))
        ->toThrow(InvalidCartItemException::class, 'must be an integer');

    $removed = $this->cart->update('qty-1', ['quantity' => ['nope' => 1]]);

    expect($removed)->toBeInstanceOf(CartItem::class);
    expect($this->cart->getItems())->toHaveCount(0);

    expect(fn (): mixed => $this->cart->add('qty-2', 'Qty', 100, 2.5))
        ->toThrow(InvalidCartItemException::class, 'must be an integer');
});

it('accepts validated integer numeric strings as quantities', function (): void {
    $item = $this->cart->add('qty-str', 'Qty', 100, '2');

    expect($item->quantity)->toBe(2);

    $updated = $this->cart->update('qty-str', ['quantity' => '3']);

    expect($updated?->quantity)->toBe(5);

    $absolute = $this->cart->update('qty-str', ['quantity' => ['value' => '4']]);

    expect($absolute?->quantity)->toBe(4);
});

it('treats thousand-separated price strings as major units', function (): void {
    expect((new CartItem(id: 'p1', name: 'P', price: '1,000', quantity: 1))->price)->toBe(100000);
    expect((new CartItem(id: 'p2', name: 'P', price: '1,000.00', quantity: 1))->price)->toBe(100000);

    $item = $this->cart->add('p3', 'P', '2,500', 1);

    expect($item->price)->toBe(250000);
});

it('marks carts converted exactly once', function (): void {
    $cart = CartModel::query()->create([
        'identifier' => 'convert-once',
        'instance' => 'default',
    ]);

    $cart->markAsConverted();
    $first = $cart->refresh()->checked_out_at;

    $cart->markAsConverted();

    expect($cart->refresh()->checked_out_at)->toEqual($first);
});

it('locks the fixed-value unit contract', function (): void {
    $minor = new CartCondition(
        name: 'Minor',
        type: 'discount',
        target: 'cart@cart_subtotal/aggregate',
        value: '+5',
    );
    $major = new CartCondition(
        name: 'Major',
        type: 'discount',
        target: 'cart@cart_subtotal/aggregate',
        value: '+5.00',
    );

    expect($minor->apply(1000))->toBe(1005);
    expect($major->apply(1000))->toBe(1500);
});
