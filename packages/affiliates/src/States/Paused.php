<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\States;

final class Paused extends AffiliateStatus
{
    public static string $name = 'paused';

    public static function label(): string
    {
        return 'Paused';
    }

    public static function description(): string
    {
        return 'Affiliate is temporarily inactive';
    }

    public static function color(): string
    {
        return 'gray';
    }

    public function isPaused(): bool
    {
        return true;
    }
}
