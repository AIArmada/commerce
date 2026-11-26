<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\States;

final class PendingConversion extends ConversionStatus
{
    public static string $name = 'pending';

    public function label(): string
    {
        return 'Pending Review';
    }

    public function color(): string
    {
        return 'warning';
    }
}
