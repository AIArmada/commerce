<?php

declare(strict_types=1);

namespace AIArmada\Docs\States;

final class Paid extends DocStatus
{
    public static string $name = 'paid';

    public function label(): string
    {
        return 'Paid';
    }

    public function color(): string
    {
        return 'success';
    }

    public function isPaid(): bool
    {
        return true;
    }
}