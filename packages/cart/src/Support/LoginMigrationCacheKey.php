<?php

declare(strict_types=1);

namespace AIArmada\Cart\Support;

final class LoginMigrationCacheKey
{
    public static function make(string $identifier): string
    {
        return 'cart_migration_' . hash('sha256', mb_strtolower(mb_trim($identifier)));
    }
}
