<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Support;

use AIArmada\Cashier\Exceptions\Webhook\WebhookVerificationException;
use JsonException;

final class VerifiedWebhookPayload
{
    /** @return array<string, mixed> */
    public static function decode(string $gateway, string $rawPayload): array
    {
        try {
            $payload = json_decode($rawPayload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw WebhookVerificationException::invalidPayload($gateway);
        }

        if (! is_array($payload) || ! str_starts_with(mb_ltrim($rawPayload), '{')) {
            throw WebhookVerificationException::invalidPayload($gateway);
        }

        /** @var array<string, mixed> $payload */
        return $payload;
    }
}
