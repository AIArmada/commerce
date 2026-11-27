<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Features\Subscriptions\Actions;

use AIArmada\CashierChip\Enums\SubscriptionStatus;
use AIArmada\CashierChip\Features\Subscriptions\Models\Subscription;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;
use InvalidArgumentException;
use LogicException;

final readonly class UpdateSubscriptionLifecycle
{
    public function endTrial(Subscription $subscription): Subscription
    {
        if (is_null($subscription->trial_ends_at)) {
            return $subscription;
        }

        $subscription->trial_ends_at = null;
        $subscription->save();

        return $subscription;
    }

    public function extendTrial(Subscription $subscription, CarbonInterface $date): Subscription
    {
        if (! $date->isFuture()) {
            throw new InvalidArgumentException("Extending a subscription's trial requires a date in the future.");
        }

        $subscription->trial_ends_at = CarbonImmutable::instance($date);
        $subscription->save();

        return $subscription;
    }

    public function cancel(Subscription $subscription): Subscription
    {
        // If the user was on trial, we will set the grace period to end when the trial
        // would have ended. Otherwise, we'll use the next billing date as the end of
        // the grace period for this current user.
        if ($subscription->onTrial()) {
            $subscription->ends_at = $subscription->trial_ends_at;
        } else {
            $subscription->ends_at = $subscription->next_billing_at ?? CarbonImmutable::now();
        }

        $subscription->canceled_at = CarbonImmutable::now();
        $subscription->save();

        return $subscription;
    }

    public function cancelAt(Subscription $subscription, DateTimeInterface | int $endsAt): Subscription
    {
        if ($endsAt instanceof DateTimeInterface) {
            $endsAt = CarbonImmutable::instance($endsAt);
        } else {
            $endsAt = CarbonImmutable::createFromTimestamp($endsAt);
        }

        $subscription->ends_at = $endsAt;
        $subscription->canceled_at = CarbonImmutable::now();
        $subscription->save();

        return $subscription;
    }

    public function markAsCanceled(Subscription $subscription): void
    {
        $subscription->forceFill([
            'chip_status' => SubscriptionStatus::Canceled,
            'ends_at' => CarbonImmutable::now(),
            'canceled_at' => CarbonImmutable::now(),
        ])->save();
    }

    public function resume(Subscription $subscription): Subscription
    {
        if (! $subscription->onGracePeriod()) {
            throw new LogicException('Unable to resume subscription that is not within grace period.');
        }

        // Finally, we will remove the ending timestamp from the user's record in the
        // local database to indicate that the subscription is active again and is
        // no longer "canceled". Then we shall save this record in the database.
        $subscription->forceFill([
            'chip_status' => SubscriptionStatus::Active,
            'ends_at' => null,
            'canceled_at' => null,
        ])->save();

        return $subscription;
    }

    public function setRecurringToken(Subscription $subscription, string $token): Subscription
    {
        $subscription->recurring_token = $token;
        $subscription->save();

        return $subscription;
    }

    public function syncChipStatus(Subscription $subscription): void
    {
        $status = $this->calculateCurrentStatus($subscription);

        $subscription->chip_status = $status;
        $subscription->save();
    }

    public function pause(Subscription $subscription): Subscription
    {
        $subscription->forceFill([
            'chip_status' => SubscriptionStatus::Paused,
            'paused_at' => CarbonImmutable::now(),
        ])->save();

        return $subscription;
    }

    public function unpause(Subscription $subscription): Subscription
    {
        $subscription->forceFill([
            'chip_status' => SubscriptionStatus::Active,
            'paused_at' => null,
        ])->save();

        return $subscription;
    }

    private function calculateCurrentStatus(Subscription $subscription): SubscriptionStatus
    {
        if ($subscription->ended()) {
            return SubscriptionStatus::Canceled;
        }

        if ($subscription->onTrial()) {
            return SubscriptionStatus::Trialing;
        }

        if ($subscription->onGracePeriod()) {
            return SubscriptionStatus::Canceled;
        }

        return SubscriptionStatus::Active;
    }
}
