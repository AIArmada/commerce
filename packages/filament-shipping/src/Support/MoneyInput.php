<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Support;

/**
 * Major-to-minor money conversion for admin form inputs.
 */
final class MoneyInput
{
    public static function toMinor(mixed $state): ?int
    {
        if ($state === null || $state === '') {
            return null;
        }

        return (int) round((float) $state * 100);
    }
}
