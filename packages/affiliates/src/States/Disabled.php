<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\States;

final class Disabled extends AffiliateStatus
{
    public static string $name = 'disabled';

    public function label(): string
    {
        return 'Disabled';
    }

    public function description(): string
    {
        return 'Affiliate is disabled and cannot earn commissions';
    }

    public function isDisabled(): bool
    {
        return true;
    }
}
