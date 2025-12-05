<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Enums;

enum BackorderStatus: string
{
    case Pending = 'pending';
    case PartiallyFulfilled = 'partially_fulfilled';
    case Fulfilled = 'fulfilled';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::PartiallyFulfilled => 'Partially Fulfilled',
            self::Fulfilled => 'Fulfilled',
            self::Cancelled => 'Cancelled',
            self::Expired => 'Expired',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::PartiallyFulfilled => 'info',
            self::Fulfilled => 'success',
            self::Cancelled => 'danger',
            self::Expired => 'gray',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::PartiallyFulfilled], true);
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::Fulfilled, self::Cancelled, self::Expired], true);
    }

    public function canFulfill(): bool
    {
        return in_array($this, [self::Pending, self::PartiallyFulfilled], true);
    }

    public function canCancel(): bool
    {
        return in_array($this, [self::Pending, self::PartiallyFulfilled], true);
    }
}
