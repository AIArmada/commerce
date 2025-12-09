<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Exceptions;

use Exception;

/**
 * Exception thrown when a coupon is invalid.
 *
 * Provides Stripe-compatible static factory methods.
 */
class InvalidCoupon extends Exception
{
    /**
     * Create a new exception for a coupon that was not found.
     */
    public static function notFound(string $couponId): self
    {
        return new self("The coupon [{$couponId}] does not exist.");
    }

    /**
     * Create a new exception for an inactive coupon.
     */
    public static function inactive(string $couponId): self
    {
        return new self("The coupon [{$couponId}] is not active or has expired.");
    }

    /**
     * Create a new exception for an expired coupon.
     */
    public static function expired(string $couponId): self
    {
        return new self("The coupon [{$couponId}] has expired.");
    }

    /**
     * Create a new exception when a forever amount_off coupon cannot be used in checkout.
     */
    public static function cannotUseForeverAmountOffInCheckout(string $couponId): self
    {
        return new self(
            "The coupon [{$couponId}] is a forever amount_off coupon and cannot be used in checkout sessions. " .
            'Please use a different coupon or apply it directly to the subscription.'
        );
    }

    /**
     * Create a new exception when a forever amount_off coupon cannot be applied to a subscription.
     */
    public static function cannotApplyForeverAmountOffToSubscription(string $couponId): self
    {
        return new self(
            "The coupon [{$couponId}] is a forever amount_off coupon and cannot be applied to subscriptions. " .
            'Please use a percentage coupon or a fixed duration amount_off coupon.'
        );
    }

    /**
     * Create a new exception when forever amount_off coupons are not allowed.
     */
    public static function foreverAmountOffCouponNotAllowed(string $couponId): self
    {
        return new self(
            "The coupon [{$couponId}] is a forever amount_off coupon which is not allowed for this operation."
        );
    }

    /**
     * Create a new exception when a coupon has reached its usage limit.
     */
    public static function usageLimitReached(string $couponId): self
    {
        return new self("The coupon [{$couponId}] has reached its maximum usage limit.");
    }

    /**
     * Create a new exception when a coupon cannot be used by the user.
     */
    public static function perUserLimitReached(string $couponId): self
    {
        return new self("You have already used the coupon [{$couponId}] the maximum number of times allowed.");
    }

    /**
     * Create a new exception when the minimum cart value is not met.
     */
    public static function minimumNotMet(string $couponId, int $minValue, string $currency): self
    {
        $formatted = number_format($minValue / 100, 2);

        return new self(
            "The coupon [{$couponId}] requires a minimum order value of {$currency} {$formatted}."
        );
    }
}
