<?php

declare(strict_types=1);

namespace AIArmada\Cart\Broadcasting\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event broadcast when cart state is synchronized.
 */
final class CartSynced implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $cartId,
        /** @var array<string, mixed> */
        public readonly array $cartState
    ) {}

    /**
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel("cart.{$this->cartId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'cart.synced';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'cart_id' => $this->cartId,
            'state' => $this->cartState,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
