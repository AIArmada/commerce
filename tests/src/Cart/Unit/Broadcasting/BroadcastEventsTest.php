<?php

declare(strict_types=1);

use AIArmada\Cart\Broadcasting\Events\CartItemAdded;
use AIArmada\Cart\Broadcasting\Events\CartItemRemoved;
use AIArmada\Cart\Broadcasting\Events\CartItemUpdated;
use Illuminate\Broadcasting\PresenceChannel;

describe('CartItemAdded', function (): void {
    it('can be instantiated', function (): void {
        $event = new CartItemAdded(
            cartId: 'cart-123',
            itemData: ['id' => 'item-1', 'name' => 'Product', 'quantity' => 2]
        );

        expect($event->cartId)->toBe('cart-123')
            ->and($event->itemData)->toBe(['id' => 'item-1', 'name' => 'Product', 'quantity' => 2]);
    });

    it('broadcasts on presence channel', function (): void {
        $event = new CartItemAdded('cart-456', []);

        $channels = $event->broadcastOn();

        expect($channels)->toHaveCount(1)
            ->and($channels[0])->toBeInstanceOf(PresenceChannel::class)
            ->and($channels[0]->name)->toBe('presence-cart.cart-456');
    });

    it('has correct broadcast name', function (): void {
        $event = new CartItemAdded('cart-789', []);

        expect($event->broadcastAs())->toBe('item.added');
    });

    it('broadcasts with correct data', function (): void {
        $itemData = ['id' => 'item-1', 'name' => 'Widget'];
        $event = new CartItemAdded('cart-broadcast', $itemData);

        $data = $event->broadcastWith();

        expect($data['cart_id'])->toBe('cart-broadcast')
            ->and($data['item'])->toBe($itemData)
            ->and($data)->toHaveKey('timestamp');
    });
});

describe('CartItemUpdated', function (): void {
    it('can be instantiated', function (): void {
        $event = new CartItemUpdated(
            cartId: 'cart-update',
            itemData: ['id' => 'item-1', 'quantity' => 5]
        );

        expect($event->cartId)->toBe('cart-update')
            ->and($event->itemData)->toBe(['id' => 'item-1', 'quantity' => 5]);
    });

    it('broadcasts on presence channel', function (): void {
        $event = new CartItemUpdated('cart-456', ['id' => 'item-1']);

        $channels = $event->broadcastOn();

        expect($channels)->toHaveCount(1)
            ->and($channels[0])->toBeInstanceOf(PresenceChannel::class);
    });

    it('has correct broadcast name', function (): void {
        $event = new CartItemUpdated('cart-789', ['id' => 'item-1']);

        expect($event->broadcastAs())->toBe('item.updated');
    });
});

describe('CartItemRemoved', function (): void {
    it('can be instantiated', function (): void {
        $event = new CartItemRemoved(
            cartId: 'cart-remove',
            itemId: 'item-to-remove'
        );

        expect($event->cartId)->toBe('cart-remove')
            ->and($event->itemId)->toBe('item-to-remove');
    });

    it('broadcasts on presence channel', function (): void {
        $event = new CartItemRemoved('cart-456', 'item-1');

        $channels = $event->broadcastOn();

        expect($channels)->toHaveCount(1)
            ->and($channels[0])->toBeInstanceOf(PresenceChannel::class);
    });

    it('has correct broadcast name', function (): void {
        $event = new CartItemRemoved('cart-789', 'item-1');

        expect($event->broadcastAs())->toBe('item.removed');
    });
});
