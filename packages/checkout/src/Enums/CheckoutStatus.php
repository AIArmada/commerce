<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Enums;

enum CheckoutStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case AwaitingPayment = 'awaiting_payment';
    case PaymentProcessing = 'payment_processing';
    case PaymentFailed = 'payment_failed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::Cancelled, self::Expired => true,
            default => false,
        };
    }

    public function canRetryPayment(): bool
    {
        return $this === self::PaymentFailed;
    }

    public function canCancel(): bool
    {
        return match ($this) {
            self::Pending, self::Processing, self::AwaitingPayment, self::PaymentFailed => true,
            default => false,
        };
    }
}
