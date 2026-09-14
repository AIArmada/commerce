<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Support;

use AIArmada\CommerceSupport\Support\ConnectionDriver;
use Illuminate\Database\Eloquent\Builder;

/**
 * Per-driver SQL expressions over the CHIP purchases JSON payload.
 *
 * Mirrors the driver handling in PurchaseTable's high-value filter so
 * widget aggregates stay consistent with table filtering. Amounts are
 * minor units; missing or non-numeric totals evaluate to 0, matching
 * the previous `(int) ($purchase->purchase['total'] ?? 0)` semantics.
 */
final class PurchaseRevenueExpressions
{
    /**
     * SQL expression evaluating to the purchase total in minor units.
     */
    public static function totalMinor(Builder $query): string
    {
        return match (ConnectionDriver::name($query->getConnection())) {
            'pgsql' => "COALESCE(CASE WHEN (purchase->>'total') ~ '^-?[0-9]+$' THEN (purchase->>'total')::bigint ELSE 0 END, 0)",
            'mysql', 'mariadb' => "COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(purchase, '$.total')) AS SIGNED), 0)",
            default => "COALESCE(CAST(json_extract(purchase, '$.total') AS INTEGER), 0)",
        };
    }

    /**
     * SQL expression evaluating to the raw payment-method key.
     */
    public static function paymentMethodKey(Builder $query): string
    {
        return match (ConnectionDriver::name($query->getConnection())) {
            'pgsql' => "COALESCE(payment->>'payment_type', transaction_data->>'payment_method', 'unknown')",
            'mysql', 'mariadb' => "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(payment, '$.payment_type')), JSON_UNQUOTE(JSON_EXTRACT(transaction_data, '$.payment_method')), 'unknown')",
            default => "COALESCE(json_extract(payment, '$.payment_type'), json_extract(transaction_data, '$.payment_method'), 'unknown')",
        };
    }
}
