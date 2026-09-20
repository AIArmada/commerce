<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Support;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Parse YYYY-MM bonus months into inclusive month ranges.
 */
final class BonusMonth
{
    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    public static function parse(mixed $month): ?array
    {
        if ($month === null || $month === '') {
            $now = CarbonImmutable::now();

            return [$now->startOfMonth(), $now->endOfMonth()];
        }

        if (! is_string($month) || preg_match('/^(\d{4})-(\d{2})$/', $month, $matches) !== 1) {
            return null;
        }

        $monthNumber = (int) $matches[2];

        if ($monthNumber < 1 || $monthNumber > 12) {
            return null;
        }

        try {
            $start = CarbonImmutable::create((int) $matches[1], $monthNumber, 1, 0, 0, 0);
        } catch (Throwable) {
            return null;
        }

        if (! $start instanceof CarbonImmutable) {
            return null;
        }

        return [$start->startOfMonth(), $start->endOfMonth()];
    }
}
