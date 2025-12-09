<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Targeting\Enums;

use AIArmada\Vouchers\Targeting\Evaluators\CartQuantityEvaluator;
use AIArmada\Vouchers\Targeting\Evaluators\CartValueEvaluator;
use AIArmada\Vouchers\Targeting\Evaluators\CategoryInCartEvaluator;
use AIArmada\Vouchers\Targeting\Evaluators\ChannelEvaluator;
use AIArmada\Vouchers\Targeting\Evaluators\CustomerLifetimeValueEvaluator;
use AIArmada\Vouchers\Targeting\Evaluators\DateRangeEvaluator;
use AIArmada\Vouchers\Targeting\Evaluators\DayOfWeekEvaluator;
use AIArmada\Vouchers\Targeting\Evaluators\DeviceEvaluator;
use AIArmada\Vouchers\Targeting\Evaluators\FirstPurchaseEvaluator;
use AIArmada\Vouchers\Targeting\Evaluators\GeographicEvaluator;
use AIArmada\Vouchers\Targeting\Evaluators\ProductInCartEvaluator;
use AIArmada\Vouchers\Targeting\Evaluators\ReferrerEvaluator;
use AIArmada\Vouchers\Targeting\Evaluators\TimeWindowEvaluator;
use AIArmada\Vouchers\Targeting\Evaluators\UserAttributeEvaluator;
use AIArmada\Vouchers\Targeting\Evaluators\UserSegmentEvaluator;

/**
 * Types of targeting rules for voucher eligibility.
 */
enum TargetingRuleType: string
{
    // User-based rules
    case UserSegment = 'user_segment';
    case UserAttribute = 'user_attribute';
    case FirstPurchase = 'first_purchase';
    case CustomerLifetimeValue = 'clv';

    // Cart-based rules
    case CartValue = 'cart_value';
    case CartQuantity = 'cart_quantity';
    case ProductInCart = 'product_in_cart';
    case CategoryInCart = 'category_in_cart';

    // Time-based rules
    case TimeWindow = 'time_window';
    case DayOfWeek = 'day_of_week';
    case DateRange = 'date_range';

    // Context-based rules
    case Channel = 'channel';
    case Device = 'device';
    case Geographic = 'geographic';
    case Referrer = 'referrer';

    /**
     * Get all rule types as options for select fields.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }

    /**
     * Group rule types by category.
     *
     * @return array<string, array<string, string>>
     */
    public static function grouped(): array
    {
        return [
            'User' => [
                self::UserSegment->value => self::UserSegment->label(),
                self::UserAttribute->value => self::UserAttribute->label(),
                self::FirstPurchase->value => self::FirstPurchase->label(),
                self::CustomerLifetimeValue->value => self::CustomerLifetimeValue->label(),
            ],
            'Cart' => [
                self::CartValue->value => self::CartValue->label(),
                self::CartQuantity->value => self::CartQuantity->label(),
                self::ProductInCart->value => self::ProductInCart->label(),
                self::CategoryInCart->value => self::CategoryInCart->label(),
            ],
            'Time' => [
                self::TimeWindow->value => self::TimeWindow->label(),
                self::DayOfWeek->value => self::DayOfWeek->label(),
                self::DateRange->value => self::DateRange->label(),
            ],
            'Context' => [
                self::Channel->value => self::Channel->label(),
                self::Device->value => self::Device->label(),
                self::Geographic->value => self::Geographic->label(),
                self::Referrer->value => self::Referrer->label(),
            ],
        ];
    }

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::UserSegment => 'User Segment',
            self::UserAttribute => 'User Attribute',
            self::FirstPurchase => 'First Purchase',
            self::CustomerLifetimeValue => 'Customer Lifetime Value',
            self::CartValue => 'Cart Value',
            self::CartQuantity => 'Cart Quantity',
            self::ProductInCart => 'Product in Cart',
            self::CategoryInCart => 'Category in Cart',
            self::TimeWindow => 'Time Window',
            self::DayOfWeek => 'Day of Week',
            self::DateRange => 'Date Range',
            self::Channel => 'Channel',
            self::Device => 'Device',
            self::Geographic => 'Geographic Location',
            self::Referrer => 'Referrer',
        };
    }

    /**
     * Get the evaluator class for this rule type.
     */
    public function getEvaluatorClass(): string
    {
        return match ($this) {
            self::UserSegment => UserSegmentEvaluator::class,
            self::UserAttribute => UserAttributeEvaluator::class,
            self::FirstPurchase => FirstPurchaseEvaluator::class,
            self::CustomerLifetimeValue => CustomerLifetimeValueEvaluator::class,
            self::CartValue => CartValueEvaluator::class,
            self::CartQuantity => CartQuantityEvaluator::class,
            self::ProductInCart => ProductInCartEvaluator::class,
            self::CategoryInCart => CategoryInCartEvaluator::class,
            self::TimeWindow => TimeWindowEvaluator::class,
            self::DayOfWeek => DayOfWeekEvaluator::class,
            self::DateRange => DateRangeEvaluator::class,
            self::Channel => ChannelEvaluator::class,
            self::Device => DeviceEvaluator::class,
            self::Geographic => GeographicEvaluator::class,
            self::Referrer => ReferrerEvaluator::class,
        };
    }

    /**
     * Get available operators for this rule type.
     *
     * @return array<string, string>
     */
    public function getOperators(): array
    {
        return match ($this) {
            self::UserSegment, self::CategoryInCart, self::ProductInCart => [
                'in' => 'Is in',
                'not_in' => 'Is not in',
                'contains_any' => 'Contains any of',
                'contains_all' => 'Contains all of',
            ],
            self::CartValue, self::CartQuantity, self::CustomerLifetimeValue => [
                '=' => 'Equals',
                '!=' => 'Not equals',
                '>' => 'Greater than',
                '>=' => 'Greater than or equal',
                '<' => 'Less than',
                '<=' => 'Less than or equal',
                'between' => 'Between',
            ],
            self::FirstPurchase => [
                '=' => 'Equals',
            ],
            self::TimeWindow => [
                'between' => 'Between',
            ],
            self::DayOfWeek => [
                'in' => 'Is in',
                'not_in' => 'Is not in',
            ],
            self::DateRange => [
                'between' => 'Between',
                'before' => 'Before',
                'after' => 'After',
            ],
            self::Channel, self::Device, self::Referrer => [
                '=' => 'Equals',
                '!=' => 'Not equals',
                'in' => 'Is in',
                'not_in' => 'Is not in',
            ],
            self::Geographic => [
                'in' => 'Country is in',
                'not_in' => 'Country is not in',
            ],
            self::UserAttribute => [
                '=' => 'Equals',
                '!=' => 'Not equals',
                'contains' => 'Contains',
                'starts_with' => 'Starts with',
                'ends_with' => 'Ends with',
            ],
        };
    }

    /**
     * Check if this rule type requires an array of values.
     */
    public function requiresArrayValues(): bool
    {
        return match ($this) {
            self::UserSegment, self::CategoryInCart, self::ProductInCart,
            self::DayOfWeek, self::Geographic => true,
            default => false,
        };
    }
}
