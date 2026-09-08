<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Webhooks;

use AIArmada\Checkout\Actions\ProcessCheckoutPaymentNotification;
use AIArmada\CommerceSupport\Webhooks\CommerceWebhookProcessor;
use Illuminate\Support\Facades\Log;

final class ProcessCheckoutWebhook extends CommerceWebhookProcessor
{
    /**
     * @param  array<string, mixed>  $payload
     */
    protected function processEvent(string $eventType, array $payload): void
    {
        $gateway = $this->gatewayForWebhookCall();

        if ($gateway === null) {
            Log::warning('Checkout webhook has no configured gateway route', [
                'webhook_call_id' => $this->webhookCall->id,
                'webhook_config' => $this->webhookCall->name,
            ]);

            return;
        }

        $expectedGateways = data_get(config('checkout.routes.webhooks', []), "{$gateway}.gateways", []);
        $expectedGateways = is_array($expectedGateways)
            ? array_values(array_filter($expectedGateways, static fn (mixed $value): bool => is_string($value)))
            : [];

        if ($expectedGateways === []) {
            Log::warning('Checkout webhook has no expected payment gateways', [
                'webhook_call_id' => $this->webhookCall->id,
                'gateway' => $gateway,
            ]);

            return;
        }

        app(ProcessCheckoutPaymentNotification::class)->handle(
            payload: $payload,
            context: [
                'source' => 'checkout.webhook',
                'event_type' => $eventType,
                'webhook_call_id' => $this->webhookCall->id,
                'gateway' => $gateway,
            ],
            expectedGateways: $expectedGateways,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function extractEventType(array $payload): string
    {
        return parent::extractEventType($payload);
    }

    private function gatewayForWebhookCall(): ?string
    {
        foreach (config('checkout.routes.webhooks', []) as $gateway => $webhook) {
            if (is_array($webhook) && ($webhook['config'] ?? null) === $this->webhookCall->name) {
                return is_string($gateway) ? $gateway : null;
            }
        }

        return null;
    }
}
