<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Actions;

use AIArmada\Cashier\Events\WebhookHandled;
use AIArmada\Cashier\Events\WebhookReceived;
use AIArmada\Cashier\Exceptions\Webhook\WebhookVerificationException;
use AIArmada\Cashier\Facades\Cashier;
use AIArmada\Cashier\Gateways\AbstractGateway;
use AIArmada\Cashier\Support\ActionGuard;
use AIArmada\Chip\Data\WebhookResult;
use Lorisleiva\Actions\Concerns\AsAction;

final class SyncWebhook
{
    use AsAction;

    /**
     * Sync an inbound webhook through the gateway.
     *
     * When the raw request body is supplied, the signature is verified
     * before WebhookReceived is dispatched. A null raw body marks trusted
     * internal replays (e.g. events re-fetched from the gateway API) that
     * carry no HTTP signature to verify.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers
     */
    public function handle(string $gateway, array $payload, array $headers = [], ?string $rawPayload = null): mixed
    {
        $gatewayName = ActionGuard::gatewayName($gateway);

        /** @var AbstractGateway $gatewayInstance */
        $gatewayInstance = Cashier::gateway($gatewayName);

        if ($rawPayload !== null && ! $gatewayInstance->verifyWebhookSignature($rawPayload, $headers)) {
            throw WebhookVerificationException::invalidSignature($gatewayName);
        }

        WebhookReceived::dispatch($gatewayName, $payload);

        $result = $rawPayload === null
            ? $gatewayInstance->handleWebhook($payload, $headers)
            : $gatewayInstance->handleWebhook($payload, $headers, $rawPayload);

        if ($result !== null && (! $result instanceof WebhookResult || $result->isHandled())) {
            WebhookHandled::dispatch($gatewayName, $payload);
        }

        return $result;
    }
}
