<?php

declare(strict_types=1);

namespace AIArmada\Membership\Persistence;

use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;

final readonly class MembershipUniqueViolation
{
    public static function matches(QueryException $exception): bool
    {
        return $exception instanceof UniqueConstraintViolationException
            || (string) $exception->getCode() === '23505';
    }

    public static function detail(QueryException $exception): string
    {
        return mb_strtolower((string) ($exception->errorInfo[2] ?? $exception->getPrevious()?->getMessage() ?? ''));
    }
}
