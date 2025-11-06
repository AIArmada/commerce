<?php

declare(strict_types=1);

namespace AIArmada\Docs\Enums;

enum DocStatus: string
{
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case SENT = 'sent';
    case PAID = 'paid';
    case PARTIALLY_PAID = 'partially_paid';
    case OVERDUE = 'overdue';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PENDING => 'Pending',
            self::SENT => 'Sent',
            self::PAID => 'Paid',
            self::PARTIALLY_PAID => 'Partially Paid',
            self::OVERDUE => 'Overdue',
            self::CANCELLED => 'Cancelled',
            self::REFUNDED => 'Refunded',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::PENDING => 'warning',
            self::SENT => 'info',
            self::PAID => 'success',
            self::PARTIALLY_PAID => 'warning',
            self::OVERDUE => 'danger',
            self::CANCELLED => 'gray',
            self::REFUNDED => 'info',
        };
    }

    public function isPaid(): bool
    {
        return $this === self::PAID;
    }

    public function isPayable(): bool
    {
        return in_array($this, [self::PENDING, self::SENT, self::PARTIALLY_PAID, self::OVERDUE], true);
    }
}
