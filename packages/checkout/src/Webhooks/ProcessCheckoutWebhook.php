<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Webhooks;

use AIArmada\Checkout\Actions\ProcessCheckoutPaymentNotification;
use AIArmada\CommerceSupport\Webhooks\CommerceWebhookProcessor;
use Illuminate\Support\Arr;

final class ProcessCheckoutWebhook extends CommerceWebhookProcessor
{
    /**
     * @param  array<string, mixed>  $payload
     */
    protected function processEvent(string $eventType, array $payload): void
    {
        app(ProcessCheckoutPaymentNotification::class)->handle(
            payload: $payload,
            context: [
                'source' => 'checkout.webhook',
                'event_type' => $eventType,
                'webhook_call_id' => $this->webhookCall->id,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function extractEventType(array $payload): string
    {
        return (string) (
            Arr::get($payload, 'event_type')
            ?? Arr::get($payload, 'event')
            ?? Arr::get($payload, 'type')
            ?? 'unknown'
        );
    }
}
