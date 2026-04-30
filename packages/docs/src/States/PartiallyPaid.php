<?php

declare(strict_types=1);

namespace AIArmada\Docs\States;

final class PartiallyPaid extends DocStatus
{
    public static string $name = 'partially_paid';

    public function label(): string
    {
        return 'Partially Paid';
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
