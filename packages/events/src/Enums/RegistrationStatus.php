<?php

declare(strict_types=1);

namespace AIArmada\Events\Enums;

enum RegistrationStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case CheckedIn = 'checked_in';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case NoShow = 'no_show';
    case Waitlisted = 'waitlisted';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Confirmed => 'Confirmed',
            self::CheckedIn => 'Checked In',
            self::Cancelled => 'Cancelled',
            self::Refunded => 'Refunded',
            self::NoShow => 'No Show',
            self::Waitlisted => 'Waitlisted',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Confirmed => 'success',
            self::CheckedIn => 'primary',
            self::Cancelled => 'danger',
            self::Refunded => 'gray',
            self::NoShow => 'danger',
            self::Waitlisted => 'info',
        };
    }

    public function canCheckIn(): bool
    {
        return $this === self::Confirmed;
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::CheckedIn, self::Cancelled, self::Refunded, self::NoShow], true);
    }
}
