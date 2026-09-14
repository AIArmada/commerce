<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Support;

/**
 * Builds minimal metadata payloads for stored payment methods.
 *
 * CHIP purchase/token payloads are large and carry PII; only the fields
 * needed to identify and describe the method are retained.
 */
final class PaymentMethodMetadata
{
    /**
     * @param  array<string, mixed>  $purchase
     * @return array<string, mixed>
     */
    public static function fromPurchase(array $purchase, ?string $paymentMethod = null): array
    {
        return array_filter([
            'purchase_id' => $purchase['id'] ?? null,
            'payment_method' => $paymentMethod,
            'description' => data_get($purchase, 'transaction_data.extra.description'),
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $token
     * @return array<string, mixed>
     */
    public static function fromToken(array $token): array
    {
        return array_filter([
            'token_id' => $token['id'] ?? null,
            'payment_method' => $token['payment_method'] ?? null,
            'description' => $token['description'] ?? null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
