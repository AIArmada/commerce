<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when a webhook is received from a gateway.
 */
class WebhookReceived
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $gateway,
        public readonly array $payload,
        public readonly ?Request $request = null,
    ) {}

    /**
     * Get the gateway name.
     */
    public function gateway(): string
    {
        return $this->gateway;
    }

    /**
     * Get the webhook payload.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    /**
     * Get the event type from the payload.
     */
    public function eventType(): ?string
    {
        // Stripe uses 'type', CHIP might use 'event' or similar
        return $this->payload['type'] ?? $this->payload['event'] ?? null;
    }
}
