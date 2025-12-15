<?php

declare(strict_types=1);

use AIArmada\Cart\Broadcasting\Events\CartSynced;
use AIArmada\Cart\Broadcasting\Events\CollaboratorJoined;
use AIArmada\Cart\Broadcasting\Events\CollaboratorLeft;
use Illuminate\Broadcasting\PresenceChannel;

describe('CartSynced', function (): void {
    it('can be instantiated', function (): void {
        $event = new CartSynced('cart-123', ['items' => [], 'total' => 0]);

        expect($event->cartId)->toBe('cart-123')
            ->and($event->cartState)->toBe(['items' => [], 'total' => 0]);
    });

    it('broadcasts on presence channel', function (): void {
        $event = new CartSynced('cart-456', []);

        $channels = $event->broadcastOn();

        expect($channels)->toHaveCount(1)
            ->and($channels[0])->toBeInstanceOf(PresenceChannel::class)
            ->and($channels[0]->name)->toBe('presence-cart.cart-456');
    });

    it('has correct broadcast name', function (): void {
        $event = new CartSynced('cart-789', []);

        expect($event->broadcastAs())->toBe('cart.synced');
    });

    it('broadcasts with correct data', function (): void {
        $cartState = ['items' => ['item-1'], 'total' => 1000];
        $event = new CartSynced('cart-data', $cartState);

        $data = $event->broadcastWith();

        expect($data['cart_id'])->toBe('cart-data')
            ->and($data['state'])->toBe($cartState)
            ->and($data)->toHaveKey('timestamp');
    });
});

describe('CollaboratorJoined', function (): void {
    it('can be instantiated', function (): void {
        $collaborator = ['user_id' => 'user-1', 'name' => 'John', 'role' => 'editor'];
        $event = new CollaboratorJoined('cart-123', $collaborator);

        expect($event->cartId)->toBe('cart-123')
            ->and($event->collaborator)->toBe($collaborator);
    });

    it('broadcasts on presence channel', function (): void {
        $event = new CollaboratorJoined('cart-456', []);

        $channels = $event->broadcastOn();

        expect($channels)->toHaveCount(1)
            ->and($channels[0])->toBeInstanceOf(PresenceChannel::class);
    });

    it('has correct broadcast name', function (): void {
        $event = new CollaboratorJoined('cart-789', []);

        expect($event->broadcastAs())->toBe('collaborator.joined');
    });

    it('broadcasts with correct data', function (): void {
        $collaborator = ['user_id' => 'user-1', 'name' => 'Jane'];
        $event = new CollaboratorJoined('cart-collab', $collaborator);

        $data = $event->broadcastWith();

        expect($data['cart_id'])->toBe('cart-collab')
            ->and($data['collaborator'])->toBe($collaborator)
            ->and($data)->toHaveKey('timestamp');
    });
});

describe('CollaboratorLeft', function (): void {
    it('can be instantiated', function (): void {
        $event = new CollaboratorLeft('cart-123', 'user-456');

        expect($event->cartId)->toBe('cart-123')
            ->and($event->userId)->toBe('user-456');
    });

    it('broadcasts on presence channel', function (): void {
        $event = new CollaboratorLeft('cart-456', 'user-1');

        $channels = $event->broadcastOn();

        expect($channels)->toHaveCount(1)
            ->and($channels[0])->toBeInstanceOf(PresenceChannel::class);
    });

    it('has correct broadcast name', function (): void {
        $event = new CollaboratorLeft('cart-789', 'user-1');

        expect($event->broadcastAs())->toBe('collaborator.left');
    });

    it('broadcasts with correct data', function (): void {
        $event = new CollaboratorLeft('cart-left', 'user-leave');

        $data = $event->broadcastWith();

        expect($data['cart_id'])->toBe('cart-left')
            ->and($data['user_id'])->toBe('user-leave')
            ->and($data)->toHaveKey('timestamp');
    });
});
