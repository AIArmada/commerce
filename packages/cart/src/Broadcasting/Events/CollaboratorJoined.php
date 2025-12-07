<?php

declare(strict_types=1);

namespace AIArmada\Cart\Broadcasting\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event broadcast when a collaborator joins a cart.
 */
final class CollaboratorJoined implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $cartId,
        /** @var array<string, mixed> */
        public readonly array $collaborator
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
        return 'collaborator.joined';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'cart_id' => $this->cartId,
            'collaborator' => $this->collaborator,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
