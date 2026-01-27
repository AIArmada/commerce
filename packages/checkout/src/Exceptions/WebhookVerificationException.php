<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Exceptions;

/**
 * Exception thrown when webhook signature verification fails.
 */
final class WebhookVerificationException extends CheckoutException
{
    public function __construct(
        string $message,
        public readonly ?string $gateway = null,
    ) {
        parent::__construct($message);
    }

    /**
     * Create exception for missing signature.
     */
    public static function missingSignature(string $gateway): self
    {
        return new self(
            message: "Webhook signature is missing for {$gateway}",
            gateway: $gateway
        );
    }

    /**
     * Create exception for invalid signature.
     */
    public static function invalidSignature(string $gateway): self
    {
        return new self(
            message: "Webhook signature verification failed for {$gateway}",
            gateway: $gateway
        );
    }

    /**
     * Create exception for missing configuration.
     */
    public static function missingConfiguration(string $gateway, string $config): self
    {
        return new self(
            message: "{$config} not configured for {$gateway} webhook verification",
            gateway: $gateway
        );
    }
}
