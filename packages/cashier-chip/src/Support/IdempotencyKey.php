<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Support;

use AIArmada\Chip\Builders\PurchaseBuilder;
use InvalidArgumentException;

final class IdempotencyKey
{
    /**
     * @param  array<string, mixed>  $options
     */
    public static function apply(PurchaseBuilder $builder, array $options): PurchaseBuilder
    {
        $idempotencyKey = $options['idempotency_key'] ?? null;

        if ($idempotencyKey === null) {
            return $builder;
        }

        if (! is_string($idempotencyKey) || mb_trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('The idempotency_key option must be a non-empty string.');
        }

        return $builder->idempotencyKey($idempotencyKey);
    }
}
