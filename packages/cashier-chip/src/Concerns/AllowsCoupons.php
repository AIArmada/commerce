<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Concerns;

use AIArmada\CashierChip\Billing\Coupon;
use AIArmada\CashierChip\Billing\VoucherIntegration;
use AIArmada\CashierChip\Exceptions\InvalidCoupon;
use Akaunting\Money\Money;
use LogicException;

trait AllowsCoupons
{
    /**
     * The coupon ID being applied.
     */
    public ?string $couponId = null;

    /**
     * The promotion code ID being applied.
     */
    public ?string $promotionCodeId = null;

    /**
     * Determines if user redeemable promotion codes are available in Checkout.
     */
    public bool $allowPromotionCodes = false;

    /**
     * The coupon ID to be applied.
     *
     * @return $this
     */
    public function withCoupon(?string $couponId): static
    {
        $this->couponId = $couponId;

        return $this;
    }

    /**
     * The promotion code ID to apply.
     *
     * @return $this
     */
    public function withPromotionCode(?string $promotionCodeId): static
    {
        $this->promotionCodeId = $promotionCodeId;

        return $this;
    }

    /**
     * Enables user redeemable promotion codes for a Checkout session.
     *
     * @return $this
     */
    public function allowPromotionCodes(): static
    {
        $this->allowPromotionCodes = true;

        return $this;
    }

    /**
     * Return the discounts for a Checkout session.
     *
     * @return array<int, array<string, string>>|null
     *
     * @throws InvalidCoupon
     */
    protected function checkoutDiscounts(): ?array
    {
        $discounts = [];

        if ($this->couponId) {
            $this->validateCouponForCheckout($this->couponId);

            $discounts[] = ['coupon' => $this->couponId];
        }

        if ($this->promotionCodeId) {
            $discounts[] = ['promotion_code' => $this->promotionCodeId];
        }

        return ! empty($discounts) ? $discounts : null;
    }

    /**
     * Validate that a coupon can be used in checkout sessions.
     *
     * @throws InvalidCoupon
     */
    protected function validateCouponForCheckout(string $couponId): void
    {
        $coupon = $this->retrieveCoupon($couponId);

        if (! $coupon) {
            throw InvalidCoupon::notFound($couponId);
        }

        if (! $coupon->isValid()) {
            throw InvalidCoupon::inactive($couponId);
        }

        if ($coupon->isForeverAmountOff()) {
            throw InvalidCoupon::cannotUseForeverAmountOffInCheckout($couponId);
        }
    }

    /**
     * Validate that a coupon can be applied to a subscription.
     *
     * @throws InvalidCoupon
     */
    protected function validateCouponForSubscriptionApplication(string $couponId): void
    {
        $coupon = $this->retrieveCoupon($couponId);

        if (! $coupon) {
            throw InvalidCoupon::notFound($couponId);
        }

        if (! $coupon->isValid()) {
            throw InvalidCoupon::inactive($couponId);
        }

        if ($coupon->isForeverAmountOff()) {
            throw InvalidCoupon::cannotApplyForeverAmountOffToSubscription($couponId);
        }
    }

    /**
     * Retrieve a coupon by its ID (voucher code).
     *
     * @throws LogicException when the vouchers integration is unavailable.
     */
    protected function retrieveCoupon(string $couponId): ?Coupon
    {
        $service = VoucherIntegration::service();

        $voucherData = $service->find($couponId);

        if (! $voucherData) {
            return null;
        }

        return new Coupon($voucherData);
    }

    /**
     * Calculate the discount amount for the given total.
     */
    protected function calculateCouponDiscount(int $amount): int
    {
        if (! $this->couponId && ! $this->promotionCodeId) {
            return 0;
        }

        $couponCode = $this->couponId ?? $this->promotionCodeId;

        if (! $couponCode) {
            return 0;
        }

        $coupon = $this->retrieveCoupon($couponCode);

        if (! $coupon) {
            return 0;
        }

        return $coupon->calculateDiscount($amount);
    }

    /**
     * Record coupon usage after successful application.
     */
    protected function recordCouponUsage(string $couponId, int $discountAmount, mixed $redeemedBy = null): void
    {
        $service = VoucherIntegration::service();

        $currency = config('cashier-chip.currency', 'MYR');

        $service->recordUsage(
            code: $couponId,
            discountAmount: Money::$currency($discountAmount),
            channel: 'subscription',
            metadata: null,
            redeemedBy: $redeemedBy,
        );
    }
}
