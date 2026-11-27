<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Features\Subscriptions\Actions;

use AIArmada\CashierChip\Billing\BillableModel;
use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\CashierChip\Billing\Coupon;
use AIArmada\CashierChip\Billing\CustomerOwnerResolver;
use AIArmada\CashierChip\Billing\VoucherIntegration;
use AIArmada\CashierChip\Enums\SubscriptionStatus;
use AIArmada\CashierChip\Events\SubscriptionCreated;
use AIArmada\CashierChip\Exceptions\InvalidCoupon;
use AIArmada\CashierChip\Features\Subscriptions\Api\CreateSubscription;
use AIArmada\CashierChip\Features\Subscriptions\Api\SubscriptionDraft;
use AIArmada\CashierChip\Features\Subscriptions\Models\Subscription;
use AIArmada\CommerceSupport\Data\RecordReference;
use AIArmada\CommerceSupport\Support\EloquentRecordReference;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Akaunting\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class CreateChipSubscription implements CreateSubscription
{
    public function create(SubscriptionDraft $draft): RecordReference
    {
        $items = $draft->items;
        $owner = EloquentRecordReference::resolve($draft->billable);

        if (! $owner instanceof Model || ! $owner instanceof BillableModel) {
            throw new InvalidArgumentException('A persisted CHIP billable is required to create subscriptions.');
        }

        $ownerAttributes = $this->ownerAttributes($owner);
        $couponId = $draft->couponId;
        $couponDiscount = 0;
        $couponDuration = null;

        if ($couponId !== null) {
            $voucher = VoucherIntegration::service()->find($couponId);

            if ($voucher === null) {
                throw InvalidCoupon::notFound($couponId);
            }

            $coupon = new Coupon($voucher);

            if (! $coupon->isValid()) {
                throw InvalidCoupon::inactive($couponId);
            }

            if ($coupon->isForeverAmountOff()) {
                throw InvalidCoupon::cannotApplyForeverAmountOffToSubscription($couponId);
            }

            if ($draft->hasUnknownPrices()) {
                throw new InvalidArgumentException('Cannot price a coupon against a subscription with unknown item prices. Provide unit_amount for every item.');
            }

            $couponDiscount = $coupon->calculateDiscount($draft->totalAmount());
            $couponDuration = $coupon->duration();
        }

        if (! $owner->hasChipId()) {
            $owner->createOrGetChipCustomer();
        }

        $trialEndsAt = $draft->trialEndsAt === null ? null : CarbonImmutable::instance($draft->trialEndsAt);
        $nextBillingAt = $trialEndsAt !== null
            ? $draft->cycle->advance($trialEndsAt)
            : ($draft->billingCycleAnchor === null
                ? $draft->cycle->advance(CarbonImmutable::now())
                : CarbonImmutable::instance($draft->billingCycleAnchor));

        $status = $trialEndsAt ? SubscriptionStatus::Trialing : SubscriptionStatus::Active;
        $firstItem = Arr::first($items);
        $isSinglePrice = count($items) === 1;

        return DB::transaction(function () use ($draft, $owner, $ownerAttributes, $status, $trialEndsAt, $nextBillingAt, $firstItem, $isSinglePrice, $items, $couponId, $couponDiscount, $couponDuration): RecordReference {
            $model = Cashier::$subscriptionModel;
            $subscription = new $model;

            if (! $subscription instanceof Subscription) {
                throw new InvalidArgumentException('Configured subscription model must extend the CHIP subscription model.');
            }
            $subscription->forceFill([
                ...$ownerAttributes,
                'billable_type' => $owner->getMorphClass(),
                'billable_id' => (string) $owner->getKey(),
                'type' => $draft->type,
                'chip_id' => Str::uuid()->toString(),
                'chip_status' => $status,
                'chip_price' => $isSinglePrice ? ($firstItem['price'] ?? null) : null,
                'quantity' => $isSinglePrice ? ($firstItem['quantity'] ?? 1) : null,
                'trial_ends_at' => $trialEndsAt,
                'next_billing_at' => $nextBillingAt,
                'billing_interval' => $draft->cycle->interval,
                'billing_interval_count' => $draft->cycle->count,
                'ends_at' => null,
                'coupon_id' => $couponId,
                'coupon_discount' => $couponDiscount,
                'coupon_duration' => $couponDuration,
                'coupon_applied_at' => $couponId ? CarbonImmutable::now() : null,
            ]);

            $effectiveRecurringToken = $draft->recurringToken ?? $owner->defaultPaymentMethod()?->id();

            if (is_string($effectiveRecurringToken) && $effectiveRecurringToken !== '') {
                $subscription->recurring_token = $effectiveRecurringToken;
            }

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

            $reference = new RecordReference($subscription->getMorphClass(), (string) $subscription->getKey());
            SubscriptionCreated::dispatch($reference);

            return $reference;
        });
    }

    /** @return array{owner_type?: string, owner_id?: string} */
    private function ownerAttributes(Model $billable): array
    {
        if (! (bool) config('cashier-chip.features.owner.enabled', false)) {
            return [];
        }

        $owner = OwnerContext::resolve();

        if ($owner === null) {
            throw new AuthorizationException('Owner context is required to create subscriptions when owner scoping is enabled.');
        }

        if ((bool) config('cashier-chip.features.owner.validate_billable_owner', true)
            && ! app(CustomerOwnerResolver::class)->canAccess($billable, $owner)) {
            throw new AuthorizationException('Cross-tenant subscription write blocked.');
        }

        return [
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => (string) $owner->getKey(),
        ];
    }

    private function recordCouponUsage(string $couponId, int $discountAmount, mixed $redeemedBy = null): void
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
