<?php

declare(strict_types=1);

namespace AIArmada\Docs\Enums;

enum DocEInvoiceSubmissionStatus: string
{
    case Pending = 'pending';
    case Submitted = 'submitted';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Submitted => 'Submitted',
            self::Processing => 'Processing',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }
}
