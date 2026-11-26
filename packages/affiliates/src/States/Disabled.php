<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\States;

final class Disabled extends AffiliateStatus
{
    public static string $name = 'disabled';

    public static function label(): string
    {
        return 'Disabled';
    }

    public static function description(): string
    {
        return 'Affiliate is disabled and cannot earn commissions';
    }

    public static function color(): string
    {
        return 'danger';
    }

    public function isDisabled(): bool
    {
        return true;
    }
}
