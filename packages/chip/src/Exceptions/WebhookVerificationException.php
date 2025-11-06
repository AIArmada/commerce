<?php

declare(strict_types=1);

namespace AIArmada\Chip\Exceptions;

use Exception;

final class WebhookVerificationException extends Exception
{
    public static function missingSignature(): self
    {
        return new self('Webhook signature is missing from the request headers.');
    }

    public static function invalidSignatureFormat(): self
    {
        return new self('Webhook signature format is invalid.');
    }

    public static function verificationFailed(): self
    {
        return new self('Webhook signature verification failed.');
    }

    public static function invalidPayload(string $reason = 'Invalid payload format'): self
    {
        return new self("Invalid payload: {$reason}");
    }

    public static function missingPublicKey(): self
    {
        return new self('Webhook public key is not configured.');
    }
}
