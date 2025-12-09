<?php

declare(strict_types=1);

namespace AIArmada\Cart\Broadcasting\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event broadcast when an item is updated in a collaborative cart.
 */
final class CartItemUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $cartId,
        /** @var array<string, mixed> */
        public readonly array $itemData
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel("cart.{$this->cartId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'item.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'cart_id' => $this->cartId,
            'item' => $this->itemData,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
