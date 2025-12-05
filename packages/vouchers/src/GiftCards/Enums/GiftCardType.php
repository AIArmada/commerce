<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\GiftCards\Enums;

/**
 * Types of gift cards based on how they are issued and their value structure.
 */
enum GiftCardType: string
{
    case Standard = 'standard';
    case OpenValue = 'open_value';
    case Promotional = 'promotional';
    case Reward = 'reward';
    case Corporate = 'corporate';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Standard',
            self::OpenValue => 'Open Value',
            self::Promotional => 'Promotional',
            self::Reward => 'Reward',
            self::Corporate => 'Corporate',
        };
    }

    /**
     * Get description of the gift card type.
     */
    public function description(): string
    {
        return match ($this) {
            self::Standard => 'Fixed denomination gift card purchased by customer',
            self::OpenValue => 'Customer chooses the gift card amount',
            self::Promotional => 'Issued by merchant at no cost for marketing',
            self::Reward => 'Earned through loyalty or rewards programs',
            self::Corporate => 'Bulk purchase for B2B or employee programs',
        };
    }

    /**
     * Check if this type requires a purchase transaction.
     */
    public function requiresPurchase(): bool
    {
        return match ($this) {
            self::Standard, self::OpenValue, self::Corporate => true,
            self::Promotional, self::Reward => false,
        };
    }

    /**
     * Check if this type can be topped up.
     */
    public function canBeToppedup(): bool
    {
        return match ($this) {
            self::Standard, self::OpenValue, self::Corporate => true,
            self::Promotional, self::Reward => false,
        };
    }

    /**
     * Check if this type can be transferred to another recipient.
     */
    public function canBeTransferred(): bool
    {
        return match ($this) {
            self::Standard, self::OpenValue, self::Corporate => true,
            self::Promotional => false,
            self::Reward => false,
        };
    }

    /**
     * Get color for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::Standard => 'primary',
            self::OpenValue => 'info',
            self::Promotional => 'success',
            self::Reward => 'warning',
            self::Corporate => 'gray',
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
