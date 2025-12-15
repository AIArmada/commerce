<?php

declare(strict_types=1);

namespace Tests\Vouchers\Unit\Targeting\Enums;

use AIArmada\Vouchers\Targeting\Enums\TargetingRuleType;
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

describe('TargetingRuleType', function (): void {
    describe('cases', function (): void {
        it('has all expected user-based rule types', function (): void {
            expect(TargetingRuleType::UserSegment->value)->toBe('user_segment');
            expect(TargetingRuleType::UserAttribute->value)->toBe('user_attribute');
            expect(TargetingRuleType::FirstPurchase->value)->toBe('first_purchase');
            expect(TargetingRuleType::CustomerLifetimeValue->value)->toBe('clv');
        });

        it('has all expected cart-based rule types', function (): void {
            expect(TargetingRuleType::CartValue->value)->toBe('cart_value');
            expect(TargetingRuleType::CartQuantity->value)->toBe('cart_quantity');
            expect(TargetingRuleType::ProductInCart->value)->toBe('product_in_cart');
            expect(TargetingRuleType::CategoryInCart->value)->toBe('category_in_cart');
        });

        it('has all expected time-based rule types', function (): void {
            expect(TargetingRuleType::TimeWindow->value)->toBe('time_window');
            expect(TargetingRuleType::DayOfWeek->value)->toBe('day_of_week');
            expect(TargetingRuleType::DateRange->value)->toBe('date_range');
        });

        it('has all expected context-based rule types', function (): void {
            expect(TargetingRuleType::Channel->value)->toBe('channel');
            expect(TargetingRuleType::Device->value)->toBe('device');
            expect(TargetingRuleType::Geographic->value)->toBe('geographic');
            expect(TargetingRuleType::Referrer->value)->toBe('referrer');
        });
    });

    describe('options', function (): void {
        it('returns all rule types as select options', function (): void {
            $options = TargetingRuleType::options();

            expect($options)->toBeArray();
            expect($options)->toHaveKey('user_segment');
            expect($options)->toHaveKey('cart_value');
            expect($options)->toHaveKey('time_window');
            expect($options)->toHaveKey('channel');
            expect(count($options))->toBe(count(TargetingRuleType::cases()));
        });

        it('uses labels as option values', function (): void {
            $options = TargetingRuleType::options();

            expect($options['user_segment'])->toBe('User Segment');
            expect($options['cart_value'])->toBe('Cart Value');
        });
    });

    describe('grouped', function (): void {
        it('groups rule types by category', function (): void {
            $grouped = TargetingRuleType::grouped();

            expect($grouped)->toHaveKey('User');
            expect($grouped)->toHaveKey('Cart');
            expect($grouped)->toHaveKey('Time');
            expect($grouped)->toHaveKey('Context');
        });

        it('puts user rules in User group', function (): void {
            $grouped = TargetingRuleType::grouped();

            expect($grouped['User'])->toHaveKey('user_segment');
            expect($grouped['User'])->toHaveKey('user_attribute');
            expect($grouped['User'])->toHaveKey('first_purchase');
            expect($grouped['User'])->toHaveKey('clv');
        });

        it('puts cart rules in Cart group', function (): void {
            $grouped = TargetingRuleType::grouped();

            expect($grouped['Cart'])->toHaveKey('cart_value');
            expect($grouped['Cart'])->toHaveKey('cart_quantity');
            expect($grouped['Cart'])->toHaveKey('product_in_cart');
            expect($grouped['Cart'])->toHaveKey('category_in_cart');
        });

        it('puts time rules in Time group', function (): void {
            $grouped = TargetingRuleType::grouped();

            expect($grouped['Time'])->toHaveKey('time_window');
            expect($grouped['Time'])->toHaveKey('day_of_week');
            expect($grouped['Time'])->toHaveKey('date_range');
        });

        it('puts context rules in Context group', function (): void {
            $grouped = TargetingRuleType::grouped();

            expect($grouped['Context'])->toHaveKey('channel');
            expect($grouped['Context'])->toHaveKey('device');
            expect($grouped['Context'])->toHaveKey('geographic');
            expect($grouped['Context'])->toHaveKey('referrer');
        });
    });

    describe('label', function (): void {
        it('returns human-readable labels for all types', function (): void {
            expect(TargetingRuleType::UserSegment->label())->toBe('User Segment');
            expect(TargetingRuleType::UserAttribute->label())->toBe('User Attribute');
            expect(TargetingRuleType::FirstPurchase->label())->toBe('First Purchase');
            expect(TargetingRuleType::CustomerLifetimeValue->label())->toBe('Customer Lifetime Value');
            expect(TargetingRuleType::CartValue->label())->toBe('Cart Value');
            expect(TargetingRuleType::CartQuantity->label())->toBe('Cart Quantity');
            expect(TargetingRuleType::ProductInCart->label())->toBe('Product in Cart');
            expect(TargetingRuleType::CategoryInCart->label())->toBe('Category in Cart');
            expect(TargetingRuleType::TimeWindow->label())->toBe('Time Window');
            expect(TargetingRuleType::DayOfWeek->label())->toBe('Day of Week');
            expect(TargetingRuleType::DateRange->label())->toBe('Date Range');
            expect(TargetingRuleType::Channel->label())->toBe('Channel');
            expect(TargetingRuleType::Device->label())->toBe('Device');
            expect(TargetingRuleType::Geographic->label())->toBe('Geographic Location');
            expect(TargetingRuleType::Referrer->label())->toBe('Referrer');
        });
    });

    describe('getEvaluatorClass', function (): void {
        it('returns correct evaluator for user-based types', function (): void {
            expect(TargetingRuleType::UserSegment->getEvaluatorClass())->toBe(UserSegmentEvaluator::class);
            expect(TargetingRuleType::UserAttribute->getEvaluatorClass())->toBe(UserAttributeEvaluator::class);
            expect(TargetingRuleType::FirstPurchase->getEvaluatorClass())->toBe(FirstPurchaseEvaluator::class);
            expect(TargetingRuleType::CustomerLifetimeValue->getEvaluatorClass())->toBe(CustomerLifetimeValueEvaluator::class);
        });

        it('returns correct evaluator for cart-based types', function (): void {
            expect(TargetingRuleType::CartValue->getEvaluatorClass())->toBe(CartValueEvaluator::class);
            expect(TargetingRuleType::CartQuantity->getEvaluatorClass())->toBe(CartQuantityEvaluator::class);
            expect(TargetingRuleType::ProductInCart->getEvaluatorClass())->toBe(ProductInCartEvaluator::class);
            expect(TargetingRuleType::CategoryInCart->getEvaluatorClass())->toBe(CategoryInCartEvaluator::class);
        });

        it('returns correct evaluator for time-based types', function (): void {
            expect(TargetingRuleType::TimeWindow->getEvaluatorClass())->toBe(TimeWindowEvaluator::class);
            expect(TargetingRuleType::DayOfWeek->getEvaluatorClass())->toBe(DayOfWeekEvaluator::class);
            expect(TargetingRuleType::DateRange->getEvaluatorClass())->toBe(DateRangeEvaluator::class);
        });

        it('returns correct evaluator for context-based types', function (): void {
            expect(TargetingRuleType::Channel->getEvaluatorClass())->toBe(ChannelEvaluator::class);
            expect(TargetingRuleType::Device->getEvaluatorClass())->toBe(DeviceEvaluator::class);
            expect(TargetingRuleType::Geographic->getEvaluatorClass())->toBe(GeographicEvaluator::class);
            expect(TargetingRuleType::Referrer->getEvaluatorClass())->toBe(ReferrerEvaluator::class);
        });
    });

    describe('getOperators', function (): void {
        it('returns in/not_in operators for segment-based types', function (): void {
            $operators = TargetingRuleType::UserSegment->getOperators();

            expect($operators)->toHaveKey('in');
            expect($operators)->toHaveKey('not_in');
            expect($operators)->toHaveKey('contains_any');
            expect($operators)->toHaveKey('contains_all');
        });

        it('returns comparison operators for numeric types', function (): void {
            $operators = TargetingRuleType::CartValue->getOperators();

            expect($operators)->toHaveKey('=');
            expect($operators)->toHaveKey('!=');
            expect($operators)->toHaveKey('>');
            expect($operators)->toHaveKey('>=');
            expect($operators)->toHaveKey('<');
            expect($operators)->toHaveKey('<=');
            expect($operators)->toHaveKey('between');
        });

        it('returns only equals for FirstPurchase', function (): void {
            $operators = TargetingRuleType::FirstPurchase->getOperators();

            expect($operators)->toBe(['=' => 'Equals']);
        });

        it('returns only between for TimeWindow', function (): void {
            $operators = TargetingRuleType::TimeWindow->getOperators();

            expect($operators)->toBe(['between' => 'Between']);
        });

        it('returns date operators for DateRange', function (): void {
            $operators = TargetingRuleType::DateRange->getOperators();

            expect($operators)->toHaveKey('between');
            expect($operators)->toHaveKey('before');
            expect($operators)->toHaveKey('after');
        });

        it('returns string operators for UserAttribute', function (): void {
            $operators = TargetingRuleType::UserAttribute->getOperators();

            expect($operators)->toHaveKey('=');
            expect($operators)->toHaveKey('!=');
            expect($operators)->toHaveKey('contains');
            expect($operators)->toHaveKey('starts_with');
            expect($operators)->toHaveKey('ends_with');
        });
    });

    describe('requiresArrayValues', function (): void {
        it('returns true for segment-based types', function (): void {
            expect(TargetingRuleType::UserSegment->requiresArrayValues())->toBeTrue();
            expect(TargetingRuleType::CategoryInCart->requiresArrayValues())->toBeTrue();
            expect(TargetingRuleType::ProductInCart->requiresArrayValues())->toBeTrue();
        });

        it('returns true for multi-value types', function (): void {
            expect(TargetingRuleType::DayOfWeek->requiresArrayValues())->toBeTrue();
            expect(TargetingRuleType::Geographic->requiresArrayValues())->toBeTrue();
        });

        it('returns false for single-value types', function (): void {
            expect(TargetingRuleType::CartValue->requiresArrayValues())->toBeFalse();
            expect(TargetingRuleType::CartQuantity->requiresArrayValues())->toBeFalse();
            expect(TargetingRuleType::FirstPurchase->requiresArrayValues())->toBeFalse();
            expect(TargetingRuleType::TimeWindow->requiresArrayValues())->toBeFalse();
            expect(TargetingRuleType::DateRange->requiresArrayValues())->toBeFalse();
            expect(TargetingRuleType::Channel->requiresArrayValues())->toBeFalse();
            expect(TargetingRuleType::Device->requiresArrayValues())->toBeFalse();
            expect(TargetingRuleType::Referrer->requiresArrayValues())->toBeFalse();
            expect(TargetingRuleType::UserAttribute->requiresArrayValues())->toBeFalse();
            expect(TargetingRuleType::CustomerLifetimeValue->requiresArrayValues())->toBeFalse();
        });
    });
});
