<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\GiftCards\Enums;

/**
 * Gift card lifecycle statuses.
 */
enum GiftCardStatus: string
{
    case Inactive = 'inactive';
    case Active = 'active';
    case Suspended = 'suspended';
    case Exhausted = 'exhausted';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Inactive => 'Inactive',
            self::Active => 'Active',
            self::Suspended => 'Suspended',
            self::Exhausted => 'Exhausted',
            self::Expired => 'Expired',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Get description of the status.
     */
    public function description(): string
    {
        return match ($this) {
            self::Inactive => 'Gift card has not been activated yet',
            self::Active => 'Gift card is active and can be used',
            self::Suspended => 'Gift card is temporarily suspended',
            self::Exhausted => 'Gift card balance has been fully used',
            self::Expired => 'Gift card has passed its expiration date',
            self::Cancelled => 'Gift card has been cancelled and cannot be used',
        };
    }

    /**
     * Check if this status allows redemption.
     */
    public function canRedeem(): bool
    {
        return $this === self::Active;
    }

    /**
     * Check if this status allows top-up.
     */
    public function canTopUp(): bool
    {
        return in_array($this, [self::Active, self::Exhausted], true);
    }

    /**
     * Check if this status allows transfer.
     */
    public function canTransfer(): bool
    {
        return in_array($this, [self::Active, self::Inactive], true);
    }

    /**
     * Check if this is a terminal status (no further changes possible).
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Expired, self::Cancelled], true);
    }

    /**
     * Get allowed transitions from current status.
     *
     * @return array<GiftCardStatus>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Inactive => [self::Active, self::Cancelled],
            self::Active => [self::Suspended, self::Exhausted, self::Expired, self::Cancelled],
            self::Suspended => [self::Active, self::Cancelled],
            self::Exhausted => [self::Active, self::Cancelled], // Can reactivate if topped up
            self::Expired => [],
            self::Cancelled => [],
        };
    }

    /**
     * Check if can transition to given status.
     */
    public function canTransitionTo(GiftCardStatus $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }

    /**
     * Get color for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::Inactive => 'gray',
            self::Active => 'success',
            self::Suspended => 'warning',
            self::Exhausted => 'info',
            self::Expired => 'danger',
            self::Cancelled => 'danger',
        };
    }

    /**
     * Get options for UI dropdowns.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
