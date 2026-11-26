<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\States;

final class CancelledPayout extends PayoutStatus
{
    public static string $name = 'cancelled';

    public function label(): string
    {
        return 'Cancelled';
    }

    public function color(): string
    {
        return 'gray';
    }
}
