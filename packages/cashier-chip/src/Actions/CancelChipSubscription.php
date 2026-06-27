<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Actions;

use AIArmada\CashierChip\Enums\SubscriptionStatus;
use AIArmada\CashierChip\Events\SubscriptionCanceled;
use AIArmada\CashierChip\Events\SubscriptionRenewalFailed;
use AIArmada\CashierChip\Subscription\Subscription;
use Carbon\Carbon;

final class CancelChipSubscription
{
    public function cancel(Subscription $subscription): void
    {
        $subscription->forceFill([
            'chip_status' => SubscriptionStatus::Canceled,
            'ends_at' => Carbon::now(),
        ])->save();

        SubscriptionCanceled::dispatch($subscription);
    }

    public function markPastDue(Subscription $subscription, string $reason = 'Subscription charge failed'): void
    {
        $subscription->forceFill([
            'chip_status' => SubscriptionStatus::PastDue,
        ])->save();

        SubscriptionRenewalFailed::dispatch($subscription, $reason);
    }
}
