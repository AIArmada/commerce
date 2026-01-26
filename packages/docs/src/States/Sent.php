<?php

declare(strict_types=1);

namespace AIArmada\Docs\States;

final class Sent extends DocStatus
{
    public static string $name = 'sent';

    public function label(): string
    {
        return 'Sent';
    }

    public function color(): string
    {
        return 'info';
    }

    public function isPayable(): bool
    {
        return true;
    }
}