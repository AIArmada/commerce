<?php

declare(strict_types=1);

namespace AIArmada\Docs\States;

final class Cancelled extends DocStatus
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