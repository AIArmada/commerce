<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Actions;

use AIArmada\Cashier\Events\WebhookHandled;
use AIArmada\Cashier\Events\WebhookReceived;
use AIArmada\Cashier\Facades\Cashier;
use AIArmada\Cashier\Gateways\AbstractGateway;
use Lorisleiva\Actions\Concerns\AsAction;

final class SyncWebhook
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers
     */
    public function handle(string $gateway, array $payload, array $headers = []): mixed
    {
        WebhookReceived::dispatch($gateway, $payload);

        /** @var AbstractGateway $gatewayInstance */
        $gatewayInstance = Cashier::gateway($gateway);

        $result = $gatewayInstance->handleWebhook($payload, $headers);

        WebhookHandled::dispatch($gateway, $payload);

        return $result;
    }
}
