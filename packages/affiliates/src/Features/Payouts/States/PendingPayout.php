<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\States;

final class PendingPayout extends PayoutStatus
{
    public static string $name = 'pending';

    public function label(): string
    {
        return 'Pending';
    }

    public function color(): string
    {
        return 'warning';
    }
}
