<?php

declare(strict_types=1);

use AIArmada\Cart\Broadcasting\CartChannel;
use AIArmada\Cart\Testing\InMemoryStorage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PresenceChannel;

describe('CartChannel', function (): void {
    beforeEach(function (): void {
        $this->storage = new InMemoryStorage;
        $this->channel = new CartChannel($this->storage);
    });

    it('can be instantiated', function (): void {
        expect($this->channel)->toBeInstanceOf(CartChannel::class);
    });

    it('creates presence channel for cart', function (): void {
        $channel = CartChannel::channelFor('cart-123');

        expect($channel)->toBeInstanceOf(PresenceChannel::class)
            ->and($channel->name)->toBe('presence-cart.cart-123');
    });

    it('creates private channel for cart', function (): void {
        $channel = CartChannel::privateChannelFor('cart-456');

        expect($channel)->toBeInstanceOf(Channel::class)
            ->and($channel->name)->toBe('cart.cart-456');
    });

    it('returns false when cart data not found', function (): void {
        $user = new class
        {
            public int $id = 1;

            public string $name = 'John';

            public ?string $email = 'john@example.com';
        };

        $result = $this->channel->join($user, 'nonexistent-cart');

        expect($result)->toBeFalse();
    });
});
