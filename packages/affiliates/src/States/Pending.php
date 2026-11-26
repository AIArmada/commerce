<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\States;

final class Pending extends AffiliateStatus
{
    public static string $name = 'pending';

    public static function label(): string
    {
        return 'Pending Approval';
    }

    public static function description(): string
    {
        return 'Affiliate is awaiting approval';
    }

    public static function color(): string
    {
        return 'warning';
    }

    public function isPending(): bool
    {
        return true;
    }
}
