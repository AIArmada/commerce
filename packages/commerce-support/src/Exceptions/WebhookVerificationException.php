<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Exceptions;

use Exception;

/**
 * Exception thrown when webhook signature verification fails.
 */
class WebhookVerificationException extends Exception
{
    public function __construct(
        string $message,
        public readonly string $gatewayName = 'unknown',
    ) {
        parent::__construct($message);
    }

    /**
     * Create exception for missing signature.
     */
    public static function missingSignature(string $gatewayName): self
    {
        return new self(
            message: 'Webhook signature is missing',
            gatewayName: $gatewayName
        );
    }

    /**
     * Create exception for invalid signature.
     */
    public static function invalidSignature(string $gatewayName): self
    {
        return new self(
            message: 'Webhook signature verification failed',
            gatewayName: $gatewayName
        );
    }

    /**
     * Create exception for missing public key.
     */
    public static function missingPublicKey(string $gatewayName): self
    {
        return new self(
            message: 'Public key for webhook verification is not configured',
            gatewayName: $gatewayName
        );
    }

    /**
     * Create exception for invalid payload.
     */
    public static function invalidPayload(string $gatewayName, string $reason): self
    {
        return new self(
            message: "Invalid webhook payload: {$reason}",
            gatewayName: $gatewayName
        );
    }
}
