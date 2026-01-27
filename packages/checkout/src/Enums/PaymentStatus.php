<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';
    case Cancelled = 'cancelled';

    public function isSuccessful(): bool
    {
        return $this === self::Completed;
    }

    public function isFinal(): bool
    {
        return match ($this) {
            self::Completed, self::Failed, self::Refunded, self::Cancelled => true,
            default => false,
        };
    }
}
