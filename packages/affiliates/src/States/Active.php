<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\States;

final class Active extends AffiliateStatus
{
    public static string $name = 'active';

    public function label(): string
    {
        return 'Active';
    }

    public function description(): string
    {
        return 'Affiliate can earn commissions';
    }

    public function isActive(): bool
    {
        return true;
    }
}
