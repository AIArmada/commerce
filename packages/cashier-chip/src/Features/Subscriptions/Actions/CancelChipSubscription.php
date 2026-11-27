<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Features\Subscriptions\Actions;

use AIArmada\CashierChip\Enums\SubscriptionStatus;
use AIArmada\CashierChip\Events\SubscriptionCanceled;
use AIArmada\CommerceSupport\Data\RecordReference;
use AIArmada\CashierChip\Events\SubscriptionRenewalFailed;
use AIArmada\CashierChip\Features\Subscriptions\Models\Subscription;
use Carbon\CarbonImmutable;
use Lorisleiva\Actions\Concerns\AsAction;

final class CancelChipSubscription
{
    use AsAction;

    public function handle(Subscription $subscription): void
    {
        $subscription->forceFill([
            'chip_status' => SubscriptionStatus::Canceled,
            'ends_at' => CarbonImmutable::now(),
        ])->save();

        SubscriptionCanceled::dispatch(new RecordReference($subscription->getMorphClass(), (string) $subscription->getKey()));
    }

    public function markPastDue(Subscription $subscription, string $reason = 'Subscription charge failed'): void
    {
        $subscription->forceFill([
            'chip_status' => SubscriptionStatus::PastDue,
        ])->save();

        SubscriptionRenewalFailed::dispatch(new RecordReference($subscription->getMorphClass(), (string) $subscription->getKey()), $reason);
    }
}
