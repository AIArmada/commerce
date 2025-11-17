<?php

declare(strict_types=1);

namespace AIArmada\Cart\Conditions;

use AIArmada\Cart\Conditions\Enums\ConditionApplication;
use AIArmada\Cart\Conditions\Enums\ConditionPhase;
use AIArmada\Cart\Conditions\Enums\ConditionScope;

final class Target
{
    private function __construct() {}

    public static function items(): ConditionTargetBuilder
    {
        return new ConditionTargetBuilder(
            ConditionScope::ITEMS,
            ConditionPhase::ITEM_DISCOUNT,
            ConditionApplication::PER_ITEM
        );
    }

    public static function cart(): ConditionTargetBuilder
    {
        return new ConditionTargetBuilder(
            ConditionScope::CART,
            ConditionPhase::CART_SUBTOTAL,
            ConditionApplication::AGGREGATE
        );
    }

    public static function shipments(): ConditionTargetBuilder
    {
        return new ConditionTargetBuilder(
            ConditionScope::SHIPMENTS,
            ConditionPhase::SHIPPING,
            ConditionApplication::PER_GROUP
        );
    }

    public static function payments(): ConditionTargetBuilder
    {
        return new ConditionTargetBuilder(
            ConditionScope::PAYMENTS,
            ConditionPhase::PAYMENT,
            ConditionApplication::PER_PAYMENT
        );
    }

    public static function fulfillments(): ConditionTargetBuilder
    {
        return new ConditionTargetBuilder(
            ConditionScope::FULFILLMENTS,
            ConditionPhase::SHIPPING,
            ConditionApplication::PER_GROUP
        );
    }

    public static function custom(): ConditionTargetBuilder
    {
        return new ConditionTargetBuilder(
            ConditionScope::CUSTOM,
            ConditionPhase::CUSTOM,
            ConditionApplication::AGGREGATE
        );
    }
}
