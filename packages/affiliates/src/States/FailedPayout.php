<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\States;

final class FailedPayout extends PayoutStatus
{
    public static string $name = 'failed';

    public function label(): string
    {
        return 'Failed';
    }

    public function color(): string
    {
        return 'danger';
    }
}
