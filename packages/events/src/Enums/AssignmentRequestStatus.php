<?php

declare(strict_types=1);

namespace AIArmada\Events\Enums;

use AIArmada\Events\Enums\Concerns\HasLabelOptions;

enum AssignmentRequestStatus: string
{
    use HasLabelOptions;

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
        };
    }
}
