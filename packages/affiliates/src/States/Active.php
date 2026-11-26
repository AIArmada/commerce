<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\States;

final class Active extends AffiliateStatus
{
    public static string $name = 'active';

    public static function label(): string
    {
        return 'Active';
    }

    public static function description(): string
    {
        return 'Affiliate can earn commissions';
    }

    public static function color(): string
    {
        return 'success';
    }

    public function isActive(): bool
    {
        return true;
    }
}
