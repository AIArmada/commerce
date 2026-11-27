<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Features\Subscriptions\Actions;

use AIArmada\CashierChip\Billing\BillableModel;
use AIArmada\CashierChip\Billing\Coupon;
use AIArmada\CashierChip\Billing\VoucherIntegration;
use AIArmada\CashierChip\Exceptions\InvalidCoupon;
use AIArmada\CashierChip\Features\Subscriptions\Models\Subscription;
use Akaunting\Money\Money;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use LogicException;

final readonly class UpdateSubscriptionDiscount
{
    public function applyCoupon(Subscription $subscription, string $couponId): void
    {
        $this->validateCouponForApplication($couponId);

        $coupon = $this->retrieveCoupon($couponId);

        if (! $coupon) {
            throw InvalidCoupon::notFound($couponId);
        }

        if ($subscription->hasUnknownPrices()) {
            throw new InvalidArgumentException('Cannot apply a coupon to a subscription with unknown item prices. Provide unit_amount for every item.');
        }

        $totalAmount = $subscription->calculateSubscriptionAmount();
        $discount = $coupon->calculateDiscount($totalAmount);

        $subscription->fill([
            'coupon_id' => $couponId,
            'coupon_discount' => $discount,
            'coupon_duration' => $coupon->duration(),
            'coupon_applied_at' => CarbonImmutable::now(),
        ])->save();

        // Record coupon usage
        $this->recordCouponUsage($subscription, $couponId, $discount);
    }

    public function removeDiscount(Subscription $subscription): void
    {
        $subscription->fill([
            'coupon_id' => null,
            'coupon_discount' => null,
            'coupon_duration' => null,
            'coupon_applied_at' => null,
        ])->save();
    }

    private function validateCouponForApplication(string $couponId): void
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

    public function retrieveCoupon(string $couponId): ?Coupon
    {
        $service = VoucherIntegration::service();

        $voucherData = $service->find($couponId);

        if (! $voucherData) {
            return null;
        }

        return new Coupon($voucherData);
    }

    private function recordCouponUsage(Subscription $subscription, string $couponId, int $discountAmount): void
    {
        $service = VoucherIntegration::service();

        $subscription->loadMissing('billable');
        $billable = $subscription->getRelation('billable');

        if (! $billable instanceof BillableModel) {
            throw new LogicException('A billable model is required to record coupon usage.');
        }

        $currency = $billable->preferredCurrency();

        $service->recordUsage(
            code: $couponId,
            discountAmount: Money::$currency($discountAmount),
            channel: 'subscription',
            metadata: ['subscription_id' => $subscription->id],
            redeemedBy: $billable,
        );
    }
}
