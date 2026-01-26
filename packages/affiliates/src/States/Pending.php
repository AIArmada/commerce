<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\States;

final class Pending extends AffiliateStatus
{
    public static string $name = 'pending';

    public function label(): string
    {
        return 'Pending Approval';
    }

    public function description(): string
    {
        return 'Affiliate is awaiting approval';
    }

    public function isPending(): bool
    {
        return true;
    }
}
