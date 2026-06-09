<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Actions;

use AIArmada\CashierChip\Events\SubscriptionCanceled;
use AIArmada\CashierChip\Events\SubscriptionRenewalFailed;
use AIArmada\CashierChip\Subscription\Subscription;
use Carbon\Carbon;

final class CancelChipSubscription
{
    public function cancel(Subscription $subscription): void
    {
        $subscription->forceFill([
            'chip_status' => Subscription::STATUS_CANCELED,
            'ends_at' => Carbon::now(),
        ])->save();

        SubscriptionCanceled::dispatch($subscription);
    }

    public function markPastDue(Subscription $subscription, string $reason = 'Subscription charge failed'): void
    {
        $subscription->forceFill([
            'chip_status' => Subscription::STATUS_PAST_DUE,
        ])->save();

        SubscriptionRenewalFailed::dispatch($subscription, $reason);
    }
}
