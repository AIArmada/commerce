<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Enums;

enum ConversionStatus: string
{
    case Pending = 'pending';
    case Qualified = 'qualified';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Review',
            self::Qualified => 'Qualified',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Paid => 'Paid Out',
        };
    }
}
