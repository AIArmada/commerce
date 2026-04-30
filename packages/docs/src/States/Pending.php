<?php

declare(strict_types=1);

namespace AIArmada\Docs\States;

final class Pending extends DocStatus
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

    public function isPayable(): bool
    {
        return true;
    }
}
