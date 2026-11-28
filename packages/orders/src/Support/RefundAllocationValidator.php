<?php

declare(strict_types=1);

namespace AIArmada\Orders\Support;

use InvalidArgumentException;

final class RefundAllocationValidator
{
    /**
     * Validate optional per-entity refund allocations against the provider amount.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function assertAmount(array $metadata, int $amount): void
    {
        $allocations = $metadata['refund_allocations'] ?? null;

        if ($allocations === null) {
            return;
        }

        if (! is_array($allocations)) {
            throw new InvalidArgumentException('Refund allocations must be an array.');
        }

        $allocated = 0;

        foreach ($allocations as $entityId => $allocation) {
            if (! is_string($entityId) || ! self::isPositiveMinorUnit($allocation)) {
                throw new InvalidArgumentException('Refund allocations must contain positive amounts keyed by entity ID.');
            }

            $minor = (int) $allocation;

            if ($minor > $amount - $allocated) {
                throw new InvalidArgumentException('Refund allocations must add up to the refund amount.');
            }

            $allocated += $minor;
        }

        if ($allocated !== $amount) {
            throw new InvalidArgumentException('Refund allocations must add up to the refund amount.');
        }
    }

    private static function isPositiveMinorUnit(mixed $value): bool
    {
        if (is_int($value)) {
            return $value > 0;
        }

        if (! is_string($value) || ! ctype_digit($value)) {
            return false;
        }

        $normalized = ltrim($value, '0');
        $maximum = (string) PHP_INT_MAX;

        return $normalized !== ''
            && (strlen($normalized) < strlen($maximum)
                || (strlen($normalized) === strlen($maximum) && strcmp($normalized, $maximum) <= 0));
    }
}
