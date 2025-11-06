<?php

declare(strict_types=1);

namespace AIArmada\Chip\Enums;

/**
 * Official CHIP Collect Purchase Status Values
 *
 * Source: https://docs.chip-in.asia/chip-collect/api-reference/purchases
 * All values are directly from official CHIP API documentation
 */
enum PurchaseStatus: string
{
    // Initial states
    case CREATED = 'created';
    case SENT = 'sent';
    case VIEWED = 'viewed';

    // Payment processing states
    case PENDING_EXECUTE = 'pending_execute';
    case PENDING_CHARGE = 'pending_charge';

    // Authorization states
    case HOLD = 'hold';
    case PENDING_CAPTURE = 'pending_capture';
    case PENDING_RELEASE = 'pending_release';
    case PREAUTHORIZED = 'preauthorized';

    // Success states
    case PAID = 'paid';
    case PAID_AUTHORIZED = 'paid_authorized';
    case RECURRING_SUCCESSFUL = 'recurring_successful';
    case CLEARED = 'cleared';
    case SETTLED = 'settled';

    // Refund states
    case PENDING_REFUND = 'pending_refund';
    case REFUNDED = 'refunded';

    // Failure states
    case ERROR = 'error';
    case BLOCKED = 'blocked';
    case CANCELLED = 'cancelled';
    case OVERDUE = 'overdue';
    case EXPIRED = 'expired';

    // Release states
    case RELEASED = 'released';

    // Chargeback
    case CHARGEBACK = 'chargeback';

    // Attempt states
    case ATTEMPTED_CAPTURE = 'attempted_capture';
    case ATTEMPTED_REFUND = 'attempted_refund';
    case ATTEMPTED_RECURRING = 'attempted_recurring';

    public function label(): string
    {
        return match ($this) {
            self::CREATED => 'Created',
            self::SENT => 'Sent',
            self::VIEWED => 'Viewed',
            self::PENDING_EXECUTE => 'Pending Execution',
            self::PENDING_CHARGE => 'Pending Charge',
            self::HOLD => 'On Hold',
            self::PENDING_CAPTURE => 'Pending Capture',
            self::PENDING_RELEASE => 'Pending Release',
            self::PREAUTHORIZED => 'Pre-authorized',
            self::PAID => 'Paid',
            self::PAID_AUTHORIZED => 'Paid (Authorized)',
            self::RECURRING_SUCCESSFUL => 'Recurring Successful',
            self::CLEARED => 'Cleared',
            self::SETTLED => 'Settled',
            self::PENDING_REFUND => 'Pending Refund',
            self::REFUNDED => 'Refunded',
            self::ERROR => 'Error',
            self::BLOCKED => 'Blocked',
            self::CANCELLED => 'Cancelled',
            self::OVERDUE => 'Overdue',
            self::EXPIRED => 'Expired',
            self::RELEASED => 'Released',
            self::CHARGEBACK => 'Chargeback',
            self::ATTEMPTED_CAPTURE => 'Attempted Capture',
            self::ATTEMPTED_REFUND => 'Attempted Refund',
            self::ATTEMPTED_RECURRING => 'Attempted Recurring',
        };
    }

    public function isSuccessful(): bool
    {
        return in_array($this, [
            self::PAID,
            self::PAID_AUTHORIZED,
            self::RECURRING_SUCCESSFUL,
            self::CLEARED,
            self::SETTLED,
        ]);
    }

    public function isPending(): bool
    {
        return in_array($this, [
            self::CREATED,
            self::SENT,
            self::VIEWED,
            self::PENDING_EXECUTE,
            self::PENDING_CHARGE,
            self::HOLD,
            self::PENDING_CAPTURE,
            self::PENDING_RELEASE,
            self::PREAUTHORIZED,
            self::PENDING_REFUND,
            self::ATTEMPTED_CAPTURE,
            self::ATTEMPTED_REFUND,
            self::ATTEMPTED_RECURRING,
        ]);
    }

    public function isFailed(): bool
    {
        return in_array($this, [
            self::ERROR,
            self::BLOCKED,
            self::CANCELLED,
            self::OVERDUE,
            self::EXPIRED,
        ]);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this, [
            self::CREATED,
            self::SENT,
            self::VIEWED,
        ]);
    }

    public function canBeCaptured(): bool
    {
        return $this === self::HOLD;
    }

    public function canBeReleased(): bool
    {
        return $this === self::HOLD;
    }

    public function canBeRefunded(): bool
    {
        return in_array($this, [
            self::PAID,
            self::PAID_AUTHORIZED,
            self::CLEARED,
            self::SETTLED,
        ]);
    }
}
