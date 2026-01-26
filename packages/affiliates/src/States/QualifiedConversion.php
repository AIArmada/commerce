<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\States;

final class QualifiedConversion extends ConversionStatus
{
    public static string $name = 'qualified';

    public function label(): string
    {
        return 'Qualified';
    }

    public function color(): string
    {
        return 'info';
    }
}
