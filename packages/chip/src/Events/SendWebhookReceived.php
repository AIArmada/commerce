<?php

declare(strict_types=1);

namespace AIArmada\Chip\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched after a CHIP Send webhook has passed signature verification.
 *
 * CHIP Send uses event_hooks and a resource payload rather than the Collect
 * event_type envelope. The package therefore exposes the verified payload
 * without inventing a status-to-event mapping.
 *
 * @see https://docs.chip-in.asia/chip-send/api-reference/webhooks/create
 */
final class SendWebhookReceived
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public readonly array $payload) {}
}
