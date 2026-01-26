<?php

declare(strict_types=1);

namespace AIArmada\Docs\Enums;

enum DocEInvoiceValidationStatus: string
{
    case Valid = 'valid';
    case Invalid = 'invalid';
    case Pending = 'pending';

    public function label(): string
    {
        return match ($this) {
            self::Valid => 'Valid',
            self::Invalid => 'Invalid',
            self::Pending => 'Pending',
        };
    }
}
