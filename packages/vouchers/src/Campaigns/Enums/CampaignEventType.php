<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Campaigns\Enums;

/**
 * Event types for campaign tracking.
 */
enum CampaignEventType: string
{
    case Impression = 'impression';
    case Application = 'application';
    case Conversion = 'conversion';
    case Abandonment = 'abandonment';
    case Removal = 'removal';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Impression => 'Impression',
            self::Application => 'Applied',
            self::Conversion => 'Converted',
            self::Abandonment => 'Abandoned',
            self::Removal => 'Removed',
        };
    }

    /**
     * Get description.
     */
    public function description(): string
    {
        return match ($this) {
            self::Impression => 'Voucher was displayed to user',
            self::Application => 'Voucher was applied to cart',
            self::Conversion => 'Order completed with voucher',
            self::Abandonment => 'Cart abandoned with voucher applied',
            self::Removal => 'Voucher was removed from cart',
        };
    }

    /**
     * Check if event should increment a counter metric.
     */
    public function incrementsMetric(): bool
    {
        return in_array($this, [self::Impression, self::Application, self::Conversion], true);
    }

    /**
     * Get the metric name this event increments on variant.
     */
    public function variantMetric(): ?string
    {
        return match ($this) {
            self::Impression => 'impressions',
            self::Application => 'applications',
            self::Conversion => 'conversions',
            default => null,
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
