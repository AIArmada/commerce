<?php

declare(strict_types=1);

namespace AIArmada\Chip\Support;

final class ResolveWebhookPurchaseId
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPaymentPayload(array $payload): ?string
    {
        if (($payload['type'] ?? null) === 'payment' && data_get($payload, 'related_to.type') === 'purchase') {
            $purchaseId = data_get($payload, 'related_to.id');

            return is_string($purchaseId) && $purchaseId !== '' ? $purchaseId : null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromAnyPayload(array $payload): ?string
    {
        $fromPayment = self::fromPaymentPayload($payload);

        if ($fromPayment !== null) {
            return $fromPayment;
        }

        $purchaseId = $payload['id'] ?? $payload['data.id'] ?? null;

        return is_string($purchaseId) || is_int($purchaseId) ? (string) $purchaseId : null;
    }
}
