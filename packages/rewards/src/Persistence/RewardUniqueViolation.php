<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Persistence;

use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;

final readonly class RewardUniqueViolation
{
    public static function matches(QueryException $exception): bool
    {
        if ($exception instanceof UniqueConstraintViolationException || (string) $exception->getCode() === '23505') {
            return true;
        }

        $driverCode = $exception->errorInfo[1] ?? null;

        if (in_array($driverCode, [1062, 1555, 2067, 2601, 2627], true)) {
            return true;
        }

        $detail = (string) ($exception->errorInfo[2] ?? '');

        return mb_stripos($detail, 'unique constraint') !== false
            || mb_stripos($detail, 'duplicate key') !== false
            || mb_stripos($detail, 'duplicate entry') !== false;
    }
}
