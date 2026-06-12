<?php

declare(strict_types=1);

namespace AIArmada\Events\Enums;

use AIArmada\Events\Enums\Concerns\HasLabelOptions;

enum RegistrationStatus: string
{
    use HasLabelOptions;

    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Rejected = 'rejected';
    case Waitlisted = 'waitlisted';
    case CheckedIn = 'checked_in';
    case NoShow = 'no_show';
    case Refunded = 'refunded';
    case Completed = 'completed';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Confirmed => 'Confirmed',
            self::Cancelled => 'Cancelled',
            self::Completed => 'Completed',
            self::Rejected => 'Rejected',
            self::Waitlisted => 'Waitlisted',
            self::CheckedIn => 'Checked In',
            self::NoShow => 'No Show',
            self::Refunded => 'Refunded',
            self::Expired => 'Expired',
        };
    }
}
