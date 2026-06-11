<?php

declare(strict_types=1);

namespace AIArmada\Events\Enums;

use AIArmada\Events\Enums\Concerns\HasLabelOptions;
use AIArmada\Events\Support\Policy\LifecyclePolicy;

enum RegistrationStatus: string
{
    use HasLabelOptions;

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
        return LifecyclePolicy::registrationCanCheckIn($this);
    }

    /**
     * @return list<string>
     */
    public static function capacityBlockingValues(): array
    {
        return LifecyclePolicy::registrationCapacityBlockingValues();
    }

    public function isTerminal(): bool
    {
        return LifecyclePolicy::registrationIsTerminal($this);
    }

    public function canTransitionTo(self $next): bool
    {
        return match ([$this, $next]) {
            [self::Pending, self::Confirmed],
            [self::Pending, self::Cancelled],
            [self::Pending, self::Waitlisted],
            [self::Confirmed, self::CheckedIn],
            [self::Confirmed, self::Cancelled],
            [self::Confirmed, self::Refunded],
            [self::Confirmed, self::NoShow],
            [self::CheckedIn, self::Cancelled],
            [self::CheckedIn, self::NoShow],
            [self::Cancelled, self::Refunded],
            [self::Waitlisted, self::Pending],
            [self::Waitlisted, self::Cancelled],
            [self::NoShow, self::Cancelled] => true,
            default => false,
        };
    }
}
