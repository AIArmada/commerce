<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\States;

final class CompletedPayout extends PayoutStatus
{
    public static string $name = 'completed';

    public function label(): string
    {
        return 'Completed';
    }

    public function color(): string
    {
        return 'success';
    }
}
