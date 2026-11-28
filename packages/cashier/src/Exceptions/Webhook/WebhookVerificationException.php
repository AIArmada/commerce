<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Exceptions\Webhook;

/** @phpstan-consistent-constructor */
class WebhookVerificationException extends WebhookException
{
    public static function invalidSignature(string $gateway): static
    {
        $exception = new static("Webhook signature verification failed for gateway [{$gateway}].");
        $exception->setGateway($gateway);

        return $exception;
    }

    public static function invalidPayload(string $gateway): static
    {
        $exception = new static("Verified webhook body is not a JSON object for gateway [{$gateway}].");
        $exception->setGateway($gateway);

        return $exception;
    }

    public static function missingSecret(string $gateway): static
    {
        $exception = new static("Webhook secret is not configured for gateway [{$gateway}].");
        $exception->setGateway($gateway);

        return $exception;
    }
}
