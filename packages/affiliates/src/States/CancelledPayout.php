<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\States;

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
