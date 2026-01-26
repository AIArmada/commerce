<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\States;

final class PaidConversion extends ConversionStatus
{
    public static string $name = 'paid';

    public function label(): string
    {
        return 'Paid Out';
    }

    public function color(): string
    {
        return 'primary';
    }
}
