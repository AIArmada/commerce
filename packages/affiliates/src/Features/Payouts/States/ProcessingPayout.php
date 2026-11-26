<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\States;

final class ProcessingPayout extends PayoutStatus
{
    public static string $name = 'processing';

    public function label(): string
    {
        return 'Processing';
    }

    public function color(): string
    {
        return 'info';
    }
}
