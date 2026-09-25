<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\States;

final class ReversedConversion extends ConversionStatus
{
    public static string $name = 'reversed';

    public function label(): string
    {
        return 'Reversed';
    }

    public function color(): string
    {
        return 'gray';
    }
}
