<?php

declare(strict_types=1);

namespace AIArmada\Docs\States;

final class Overdue extends DocStatus
{
    public static string $name = 'overdue';

    public function label(): string
    {
        return 'Overdue';
    }

    public function color(): string
    {
        return 'danger';
    }

    public function isPayable(): bool
    {
        return true;
    }
}
