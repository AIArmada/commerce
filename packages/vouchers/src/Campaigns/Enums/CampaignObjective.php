<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Campaigns\Enums;

/**
 * Campaign objectives for measuring success.
 */
enum CampaignObjective: string
{
    case RevenueIncrease = 'revenue_increase';
    case OrderVolumeIncrease = 'order_volume_increase';
    case AverageOrderValue = 'aov_increase';
    case NewCustomerAcquisition = 'new_customer';
    case CustomerRetention = 'retention';
    case InventoryClearance = 'inventory_clearance';
    case CategoryGrowth = 'category_growth';
    case BrandAwareness = 'brand_awareness';

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

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::RevenueIncrease => 'Revenue Increase',
            self::OrderVolumeIncrease => 'Order Volume Increase',
            self::AverageOrderValue => 'Average Order Value Increase',
            self::NewCustomerAcquisition => 'New Customer Acquisition',
            self::CustomerRetention => 'Customer Retention',
            self::InventoryClearance => 'Inventory Clearance',
            self::CategoryGrowth => 'Category Growth',
            self::BrandAwareness => 'Brand Awareness',
        };
    }

    /**
     * Get the primary metric for this objective.
     */
    public function primaryMetric(): string
    {
        return match ($this) {
            self::RevenueIncrease => 'revenue',
            self::OrderVolumeIncrease => 'conversions',
            self::AverageOrderValue => 'aov',
            self::NewCustomerAcquisition => 'new_customers',
            self::CustomerRetention => 'returning_customers',
            self::InventoryClearance => 'units_sold',
            self::CategoryGrowth => 'category_revenue',
            self::BrandAwareness => 'impressions',
        };
    }
}
