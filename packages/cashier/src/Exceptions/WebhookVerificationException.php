<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Exceptions;

/**
 * Exception thrown when a webhook cannot be verified.
 */
final class WebhookVerificationException extends CashierException
{
    /**
     * Create a new webhook verification exception.
     */
    public static function invalidSignature(string $gateway): static
    {
        $exception = new static("Webhook signature verification failed for gateway [{$gateway}].");
        $exception->setGateway($gateway);

        return $exception;
    }

    /**
     * Create exception for missing webhook secret.
     */
    public static function missingSecret(string $gateway): static
    {
        $exception = new static("Webhook secret is not configured for gateway [{$gateway}].");
        $exception->setGateway($gateway);

        return $exception;
    }
}
