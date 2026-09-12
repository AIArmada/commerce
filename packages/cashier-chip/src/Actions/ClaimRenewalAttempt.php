<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Actions;

use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\CashierChip\Enums\SubscriptionStatus;
use AIArmada\CashierChip\Subscription\RenewalAttempt;
use AIArmada\CashierChip\Subscription\Subscription;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

final class ClaimRenewalAttempt
{
    use AsAction;

    public function handle(string $subscriptionId): ?RenewalAttempt
    {
        try {
            return DB::transaction(function () use ($subscriptionId): ?RenewalAttempt {
                if ((bool) config('cashier-chip.features.owner.enabled', false)) {
                    OwnerWriteGuard::findOrFailForOwner(
                        Subscription::class,
                        $subscriptionId,
                        includeGlobal: (bool) config('cashier-chip.features.owner.include_global', false),
                        message: 'Subscription is not accessible in the current owner scope.',
                    );
                }

                $subscription = Subscription::query()
                    ->with('items')
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

                $periodKey = ($subscription->billing_interval ?? 'month') === 'month'
                    ? $subscription->next_billing_at->format('Y-m')
                    : $subscription->next_billing_at->toIso8601String();

                $existingClaim = RenewalAttempt::query()
                    ->where('subscription_id', $subscription->id)
                    ->where('period_key', $periodKey)
                    ->where('status', 'claimed')
                    ->where('lease_expires_at', '>', CarbonImmutable::now())
                    ->exists();

                if ($existingClaim) {
                    return null;
                }

                $amountMinor = $subscription->calculateSubscriptionAmount();

                if ($amountMinor > Cashier::maximumAmount()) {
                    return null;
                }

                $leaseMinutes = max(1, (int) config('cashier-chip.renewals.lease_minutes', 30));

                return RenewalAttempt::create([
                    'subscription_id' => $subscription->id,
                    'status' => 'claimed',
                    'amount_minor' => $amountMinor,
                    'period_key' => $periodKey,
                    'lease_expires_at' => CarbonImmutable::now()->addMinutes($leaseMinutes),
                ]);
            }, 3);
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            return null;
        }
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return in_array((string) ($exception->errorInfo[0] ?? $exception->getCode()), ['23000', '23505'], true);
    }
}
