<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Actions;

use AIArmada\CashierChip\Enums\SubscriptionStatus;
use AIArmada\CashierChip\Subscription\RenewalAttempt;
use AIArmada\CashierChip\Subscription\Subscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

final class ClaimRenewalAttempt
{
    use AsAction;

    public function handle(string $subscriptionId): ?RenewalAttempt
    {
        return DB::transaction(function () use ($subscriptionId): ?RenewalAttempt {
            $subscription = Subscription::query()
                ->withoutGlobalScopes()
                ->lockForUpdate()
                ->find($subscriptionId);

            if (! $subscription instanceof Subscription) {
                return null;
            }

            if (in_array($subscription->chip_status, [SubscriptionStatus::PastDue, SubscriptionStatus::Canceled, SubscriptionStatus::Incomplete], true)) {
                return null;
            }

            if (! $subscription->next_billing_at || $subscription->next_billing_at->isFuture()) {
                return null;
            }

            $periodKey = $subscription->next_billing_at->format('Y-m');

            $existingClaim = RenewalAttempt::query()
                ->where('subscription_id', $subscription->id)
                ->where('period_key', $periodKey)
                ->where('status', 'claimed')
                ->where('lease_expires_at', '>', now())
                ->exists();

            if ($existingClaim) {
                return null;
            }

            $leaseMinutes = max(1, (int) config('cashier-chip.renewals.lease_minutes', 30));

            return RenewalAttempt::create([
                'subscription_id' => $subscription->id,
                'status' => 'claimed',
                'amount_minor' => $subscription->calculateSubscriptionAmount(),
                'period_key' => $periodKey,
                'lease_expires_at' => Carbon::now()->addMinutes($leaseMinutes),
            ]);
        }, 3);
    }
}
