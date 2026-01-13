<?php

declare(strict_types=1);

namespace AIArmada\Chip\Webhooks;

use AIArmada\Chip\Services\WebhookEventDispatcher;
use AIArmada\CommerceSupport\Webhooks\CommerceWebhookProcessor;
use Spatie\WebhookClient\Models\WebhookCall;

/**
 * Process CHIP webhook events using spatie/laravel-webhook-client.
 *
 * This job handles incoming CHIP webhooks and dispatches the appropriate events
 * using the centralized WebhookEventDispatcher service.
 *
 * @property WebhookCall $webhookCall
 */
class ProcessChipWebhook extends CommerceWebhookProcessor
{
    /**
     * Process the webhook event.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function processEvent(string $eventType, array $payload): void
    {
        $dispatcher = app(WebhookEventDispatcher::class);
        $dispatcher->dispatch($eventType, $payload);
    }
}
