<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;

/**
 * Dispatched when a webhook is received from a gateway.
 *
 * The HTTP request is sync-only context: it is never queued. Queued
 * listeners receive the gateway name and payload only.
 */
class WebhookReceived
{
    use Dispatchable;

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
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        return [
            'gateway' => $this->gateway,
            'payload' => $this->payload,
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function __unserialize(array $values): void
    {
        $this->gateway = (string) ($values['gateway'] ?? '');
        $this->payload = (array) ($values['payload'] ?? []);
        $this->request = null;
    }

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
