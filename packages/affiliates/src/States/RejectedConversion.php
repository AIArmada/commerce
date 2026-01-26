<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\States;

final class RejectedConversion extends ConversionStatus
{
    public static string $name = 'rejected';

    public function label(): string
    {
        return 'Rejected';
    }

    public function color(): string
    {
        return 'danger';
    }
}
