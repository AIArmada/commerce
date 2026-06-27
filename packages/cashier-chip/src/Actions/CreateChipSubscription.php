<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Actions;

use AIArmada\CashierChip\Enums\SubscriptionStatus;
use AIArmada\CashierChip\Events\SubscriptionCreated;
use AIArmada\CashierChip\Subscription\Subscription;
use AIArmada\CashierChip\Subscription\SubscriptionBuilder;
use AIArmada\Vouchers\Services\VoucherService;
use Akaunting\Money\Money;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateChipSubscription
{
    /**
     * @throws Exception
     */
    public function create(SubscriptionBuilder $builder, ?string $recurringToken = null, array $options = []): Subscription
    {
        $items = $builder->getItems();

        if (empty($items)) {
            throw new Exception('At least one price is required when starting subscriptions.');
        }

        $owner = $builder->getOwner();

        if (method_exists($owner, 'createOrGetChipCustomer') && ! $owner->hasChipId()) {
            $owner->createOrGetChipCustomer();
        }

        $couponId = $builder->getCouponId() ?? $builder->getPromotionCodeId();
        $couponDiscount = 0;
        $couponDuration = null;

        if ($couponId) {
            $coupon = $builder->retrieveCoupon($couponId);

            if ($coupon) {
                $totalAmount = $builder->getTotalAmount();
                $couponDiscount = $coupon->calculateDiscount($totalAmount);
                $couponDuration = $coupon->duration();
            }
        }

        $nextBillingAt = $builder->getNextBillingDate();
        $trialExpires = $builder->getTrialEnd();
        $skipTrial = $builder->getSkipTrial();
        $trialEndsAt = ! $skipTrial ? $trialExpires : null;

        if ($trialEndsAt) {
            $nextBillingAt = $trialEndsAt->copy()->add(
                $builder->getBillingInterval(),
                $builder->getBillingIntervalCount()
            );
        }

        $status = $trialEndsAt ? SubscriptionStatus::Trialing : SubscriptionStatus::Active;
        $firstItem = Arr::first($items);
        $isSinglePrice = count($items) === 1;

        return DB::transaction(function () use ($builder, $owner, $status, $trialEndsAt, $nextBillingAt, $recurringToken, $firstItem, $isSinglePrice, $items, $couponId, $couponDiscount, $couponDuration): Subscription {
            $ownerAttributes = $builder->getTenantOwnerAttributes();

            $subscription = new Subscription;
            $subscription->forceFill([
                ...$ownerAttributes,
                'billable_type' => $owner->getMorphClass(),
                'billable_id' => (string) $owner->getKey(),
                'type' => $builder->getType(),
                'chip_id' => Str::uuid()->toString(),
                'chip_status' => $status,
                'chip_price' => $isSinglePrice ? ($firstItem['price'] ?? null) : null,
                'quantity' => $isSinglePrice ? ($firstItem['quantity'] ?? 1) : null,
                'trial_ends_at' => $trialEndsAt,
                'next_billing_at' => $nextBillingAt,
                'billing_interval' => $builder->getBillingInterval(),
                'billing_interval_count' => $builder->getBillingIntervalCount(),
                'recurring_token' => $recurringToken ?? $owner->defaultPaymentMethod()?->id(),
                'ends_at' => null,
                'coupon_id' => $couponId,
                'coupon_discount' => $couponDiscount,
                'coupon_duration' => $couponDuration,
                'coupon_applied_at' => $couponId ? Carbon::now() : null,
            ]);
            $subscription->save();
            $subscription->setRelation('billable', $owner);

            foreach ($items as $item) {
                $subscriptionItem = $subscription->items()->make();
                $subscriptionItem->forceFill([
                    ...$ownerAttributes,
                    'chip_id' => Str::uuid()->toString(),
                    'chip_product' => $item['product'] ?? null,
                    'chip_price' => $item['price'] ?? null,
                    'quantity' => $item['quantity'] ?? 1,
                    'unit_amount' => $item['unit_amount'] ?? null,
                ]);
                $subscriptionItem->save();
            }

            if ($couponId && $couponDiscount > 0) {
                $this->recordCouponUsage($couponId, $couponDiscount, $owner);
            }

            SubscriptionCreated::dispatch($subscription);

            return $subscription;
        });
    }

    private function recordCouponUsage(string $couponId, int $discountAmount, mixed $redeemedBy = null): void
    {
        if (! class_exists(VoucherService::class)) {
            return;
        }

        $service = app(VoucherService::class);
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
