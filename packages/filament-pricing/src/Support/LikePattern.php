<?php

declare(strict_types=1);

namespace AIArmada\FilamentPricing\Support;

/**
 * Escape user input for LIKE patterns so % and _ match literally.
 */
final class LikePattern
{
    public static function escape(string $search): string
    {
        return addcslashes($search, '\\%_');
    }
}
