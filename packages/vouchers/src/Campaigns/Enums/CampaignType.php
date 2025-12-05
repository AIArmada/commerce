<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Campaigns\Enums;

/**
 * Campaign types for different promotional strategies.
 */
enum CampaignType: string
{
    case Promotional = 'promotional';
    case Acquisition = 'acquisition';
    case Retention = 'retention';
    case Loyalty = 'loyalty';
    case Seasonal = 'seasonal';
    case Flash = 'flash';
    case Referral = 'referral';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Promotional => 'Promotional',
            self::Acquisition => 'Customer Acquisition',
            self::Retention => 'Customer Retention',
            self::Loyalty => 'Loyalty Program',
            self::Seasonal => 'Seasonal',
            self::Flash => 'Flash Sale',
            self::Referral => 'Referral Program',
        };
    }

    /**
     * Get description.
     */
    public function description(): string
    {
        return match ($this) {
            self::Promotional => 'Time-limited sales and discounts',
            self::Acquisition => 'New customer incentives and welcome offers',
            self::Retention => 'Win-back campaigns for lapsed customers',
            self::Loyalty => 'Rewards for repeat customers',
            self::Seasonal => 'Holiday and seasonal promotions',
            self::Flash => 'Limited-time urgency deals',
            self::Referral => 'Refer-a-friend incentive programs',
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
