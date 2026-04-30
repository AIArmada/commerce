<?php

declare(strict_types=1);

namespace AIArmada\Docs\States;

final class Refunded extends DocStatus
{
    public static string $name = 'refunded';

    public function label(): string
    {
        return 'Refunded';
    }

    public function color(): string
    {
        return 'info';
    }
}
