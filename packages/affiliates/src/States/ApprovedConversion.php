<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\States;

final class ApprovedConversion extends ConversionStatus
{
    public static string $name = 'approved';

    public function label(): string
    {
        return 'Approved';
    }

    public function color(): string
    {
        return 'success';
    }
}
