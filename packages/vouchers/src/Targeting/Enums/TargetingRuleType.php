<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Targeting\Enums;

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
            self::UserSegment => \AIArmada\Vouchers\Targeting\Evaluators\UserSegmentEvaluator::class,
            self::UserAttribute => \AIArmada\Vouchers\Targeting\Evaluators\UserAttributeEvaluator::class,
            self::FirstPurchase => \AIArmada\Vouchers\Targeting\Evaluators\FirstPurchaseEvaluator::class,
            self::CustomerLifetimeValue => \AIArmada\Vouchers\Targeting\Evaluators\CustomerLifetimeValueEvaluator::class,
            self::CartValue => \AIArmada\Vouchers\Targeting\Evaluators\CartValueEvaluator::class,
            self::CartQuantity => \AIArmada\Vouchers\Targeting\Evaluators\CartQuantityEvaluator::class,
            self::ProductInCart => \AIArmada\Vouchers\Targeting\Evaluators\ProductInCartEvaluator::class,
            self::CategoryInCart => \AIArmada\Vouchers\Targeting\Evaluators\CategoryInCartEvaluator::class,
            self::TimeWindow => \AIArmada\Vouchers\Targeting\Evaluators\TimeWindowEvaluator::class,
            self::DayOfWeek => \AIArmada\Vouchers\Targeting\Evaluators\DayOfWeekEvaluator::class,
            self::DateRange => \AIArmada\Vouchers\Targeting\Evaluators\DateRangeEvaluator::class,
            self::Channel => \AIArmada\Vouchers\Targeting\Evaluators\ChannelEvaluator::class,
            self::Device => \AIArmada\Vouchers\Targeting\Evaluators\DeviceEvaluator::class,
            self::Geographic => \AIArmada\Vouchers\Targeting\Evaluators\GeographicEvaluator::class,
            self::Referrer => \AIArmada\Vouchers\Targeting\Evaluators\ReferrerEvaluator::class,
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
