<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when a webhook has been handled.
 */
class WebhookHandled
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
}
