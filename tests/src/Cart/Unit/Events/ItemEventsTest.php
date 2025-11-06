<?php

declare(strict_types=1);

use AIArmada\Cart\CartManager;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Events\ItemAdded;
use AIArmada\Cart\Events\ItemConditionAdded;
use AIArmada\Cart\Events\ItemConditionRemoved;
use AIArmada\Cart\Events\ItemRemoved;
use AIArmada\Cart\Events\ItemUpdated;
use AIArmada\Cart\Events\MetadataAdded;
use AIArmada\Cart\Events\MetadataRemoved;
use AIArmada\Cart\Models\CartItem;

describe('ItemAdded Event', function (): void {
    it('creates event with item and cart', function (): void {
        $cart = app(CartManager::class)->getCurrentCart();
        $item = new CartItem('item-1', 'Test Item', 10.00, 1);

        $event = new ItemAdded($item, $cart);

        expect($event)->toBeInstanceOf(ItemAdded::class);
        expect($event->item)->toBe($item);
        expect($event->cart)->toBe($cart);
    });

    it('converts to array', function (): void {
        $cart = app(CartManager::class)->getCurrentCart();
        $item = new CartItem('item-1', 'Test Item', 10.00, 2);

        $event = new ItemAdded($item, $cart);
        $array = $event->toArray();

        expect($array)->toBeArray();
        expect($array)->toHaveKey('item_id', 'item-1');
        expect($array)->toHaveKey('item_name', 'Test Item');
        expect($array)->toHaveKey('price', 10.00);
        expect($array)->toHaveKey('quantity', 2);
        expect($array)->toHaveKey('identifier');
        expect($array)->toHaveKey('timestamp');
    });
});

describe('ItemRemoved Event', function (): void {
    it('creates event with item and cart', function (): void {
        $cart = app(CartManager::class)->getCurrentCart();
        $item = new CartItem('item-1', 'Test Item', 10.00, 1);

        $event = new ItemRemoved($item, $cart);

        expect($event)->toBeInstanceOf(ItemRemoved::class);
        expect($event->item)->toBe($item);
        expect($event->cart)->toBe($cart);
    });

    it('converts to array', function (): void {
        $cart = app(CartManager::class)->getCurrentCart();
        $item = new CartItem('item-1', 'Removed Item', 25.00, 3);

        $event = new ItemRemoved($item, $cart);
        $array = $event->toArray();

        expect($array)->toBeArray();
        expect($array)->toHaveKey('item_id', 'item-1');
        expect($array)->toHaveKey('item_name', 'Removed Item');
        expect($array)->toHaveKey('price', 25.00);
        expect($array)->toHaveKey('quantity', 3);
        expect($array)->toHaveKey('identifier');
        expect($array)->toHaveKey('timestamp');
    });
});

describe('ItemUpdated Event', function (): void {
    it('creates event with item and cart', function (): void {
        $cart = app(CartManager::class)->getCurrentCart();
        $item = new CartItem('item-1', 'Test Item', 10.00, 1);

        $event = new ItemUpdated($item, $cart);

        expect($event)->toBeInstanceOf(ItemUpdated::class);
        expect($event->item)->toBe($item);
        expect($event->cart)->toBe($cart);
    });

    it('converts to array', function (): void {
        $cart = app(CartManager::class)->getCurrentCart();
        $item = new CartItem('item-1', 'Updated Item', 15.00, 5);

        $event = new ItemUpdated($item, $cart);
        $array = $event->toArray();

        expect($array)->toBeArray();
        expect($array)->toHaveKey('item_id', 'item-1');
        expect($array)->toHaveKey('item_name', 'Updated Item');
        expect($array)->toHaveKey('price', 15.00);
        expect($array)->toHaveKey('quantity', 5);
        expect($array)->toHaveKey('identifier');
        expect($array)->toHaveKey('timestamp');
    });
});

describe('ItemConditionAdded Event', function (): void {
    it('creates event with condition, cart, and item ID', function (): void {
        $cart = app(CartManager::class)->getCurrentCart();
        $cart->add('item-1', 'Test Item', 100.00, 1);

        $condition = new CartCondition('tax', 'percentage', 'subtotal', '5.0');

        $event = new ItemConditionAdded($condition, $cart, 'item-1');

        expect($event)->toBeInstanceOf(ItemConditionAdded::class);
        expect($event->condition)->toBe($condition);
        expect($event->cart)->toBe($cart);
        expect($event->itemId)->toBe('item-1');
    });

    it('converts to array', function (): void {
        $cart = app(CartManager::class)->getCurrentCart();
        $cart->add('item-1', 'Test Item', 100.00, 1);

        $condition = new CartCondition('discount', 'percentage', 'subtotal', '-10.0');

        $event = new ItemConditionAdded($condition, $cart, 'item-1');
        $array = $event->toArray();

        expect($array)->toBeArray();
        expect($array)->toHaveKey('condition');
        expect($array)->toHaveKey('item');
        expect($array)->toHaveKey('cart');
        expect($array)->toHaveKey('impact');
        expect($array)->toHaveKey('timestamp');
        expect($array['condition']['name'])->toBe('discount');
        expect($array['condition']['type'])->toBe('percentage');
        expect($array['item']['id'])->toBe('item-1');
    });

    it('calculates condition impact', function (): void {
        $cart = app(CartManager::class)->getCurrentCart();
        $cart->add('item-1', 'Test Item', 100.00, 2); // subtotal 200

        $condition = new CartCondition('discount', 'percentage', 'subtotal', '-10.0');

        $event = new ItemConditionAdded($condition, $cart, 'item-1');
        $impact = $event->getConditionImpact();

        expect($impact)->toBe(-10.0); // calculated value for this item
    });
});

describe('ItemConditionRemoved Event', function (): void {
    it('creates event with condition, cart, and item ID', function (): void {
        $cart = app(CartManager::class)->getCurrentCart();
        $cart->add('item-1', 'Test Item', 100.00, 1);

        $condition = new CartCondition('tax', 'percentage', 'subtotal', '5.0');

        $event = new ItemConditionRemoved($condition, $cart, 'item-1', 'Tax exemption');

        expect($event)->toBeInstanceOf(ItemConditionRemoved::class);
        expect($event->condition)->toBe($condition);
        expect($event->cart)->toBe($cart);
        expect($event->itemId)->toBe('item-1');
        expect($event->reason)->toBe('Tax exemption');
    });

    it('converts to array', function (): void {
        $cart = app(CartManager::class)->getCurrentCart();
        $cart->add('item-1', 'Test Item', 100.00, 1);

        $condition = new CartCondition('promo', 'fixed', 'subtotal', '-5.0');

        $event = new ItemConditionRemoved($condition, $cart, 'item-1', 'Promo expired');
        $array = $event->toArray();

        expect($array)->toBeArray();
        expect($array)->toHaveKey('condition');
        expect($array)->toHaveKey('item');
        expect($array)->toHaveKey('cart');
        expect($array)->toHaveKey('reason', 'Promo expired');
        expect($array)->toHaveKey('timestamp');
    });

    it('handles optional reason', function (): void {
        $cart = app(CartManager::class)->getCurrentCart();
        $cart->add('item-1', 'Test Item', 50.00, 1);

        $condition = new CartCondition('discount', 'percentage', 'subtotal', '-10.0');

        $event = new ItemConditionRemoved($condition, $cart, 'item-1');

        expect($event->reason)->toBeNull();
    });
});

describe('MetadataAdded Event', function (): void {
    it('creates event with key, value, and cart', function (): void {
        $cart = app(CartManager::class)->getCurrentCart();

        $event = new MetadataAdded('customer_note', 'Please gift wrap', $cart);

        expect($event)->toBeInstanceOf(MetadataAdded::class);
        expect($event->key)->toBe('customer_note');
        expect($event->value)->toBe('Please gift wrap');
        expect($event->cart)->toBe($cart);
    });

    it('converts to array', function (): void {
        $cart = app(CartManager::class)->getCurrentCart();

        $event = new MetadataAdded('promo_code', 'SAVE20', $cart);
        $array = $event->toArray();

        expect($array)->toBeArray();
        expect($array)->toHaveKey('key', 'promo_code');
        expect($array)->toHaveKey('value', 'SAVE20');
        expect($array)->toHaveKey('cart');
        expect($array)->toHaveKey('timestamp');
    });
});

describe('MetadataRemoved Event', function (): void {
    it('creates event with key and cart', function (): void {
        $cart = app(CartManager::class)->getCurrentCart();

        $event = new MetadataRemoved('customer_note', $cart);

        expect($event)->toBeInstanceOf(MetadataRemoved::class);
        expect($event->key)->toBe('customer_note');
        expect($event->cart)->toBe($cart);
    });

    it('converts to array', function (): void {
        $cart = app(CartManager::class)->getCurrentCart();

        $event = new MetadataRemoved('promo_code', $cart);
        $array = $event->toArray();

        expect($array)->toBeArray();
        expect($array)->toHaveKey('key', 'promo_code');
        expect($array)->toHaveKey('cart');
        expect($array)->toHaveKey('timestamp');
    });
});
