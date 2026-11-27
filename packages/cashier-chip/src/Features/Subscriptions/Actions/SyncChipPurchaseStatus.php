<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Features\Subscriptions\Actions;

use AIArmada\CashierChip\Billing\BillableModel;
use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\CashierChip\Data\BillingCycle;
use AIArmada\CashierChip\Enums\SubscriptionStatus;
use AIArmada\CashierChip\Events\PaymentFailed;
use AIArmada\CashierChip\Events\PaymentSucceeded;
use AIArmada\CashierChip\Events\SettledPeriodPurchaseConflict;
use AIArmada\CashierChip\Features\Subscriptions\Models\RenewalAttempt;
use AIArmada\CashierChip\Features\Subscriptions\Models\Subscription;
use AIArmada\CashierChip\Support\PaymentMethodMetadata;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\CommerceSupport\Data\RecordReference;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

final class SyncChipPurchaseStatus
{
    use AsAction;

    /**
     * @param  Model&BillableModel  $billable
     * @param  array<string, mixed>  $purchase
     */
    public function handle(Model $billable, PurchaseData $purchaseData, array $purchase): void
    {
        if ($this->alreadyProcessed($purchaseData->id)) {
            return;
        }

        PaymentSucceeded::dispatch($billable, Arr::except($purchaseData->toArray(), ['recurring_token']));

        if ($recurringToken = $purchaseData->recurring_token) {
            $this->saveRecurringToken($billable, $recurringToken, $purchase);
        }

        if ($subscriptionType = $this->getSubscriptionType($purchase)) {
            $this->syncSubscriptionPayment($billable, $subscriptionType, $purchaseData);
        }
    }

    /**
     * @param  Model&BillableModel  $billable
     * @param  array<string, mixed>  $purchase
     */
    public function syncFailed(Model $billable, PurchaseData $purchaseData, array $purchase): void
    {
        if ($this->alreadyProcessed($purchaseData->id)) {
            return;
        }

        PaymentFailed::dispatch($billable, Arr::except($purchaseData->toArray(), ['recurring_token']));

        if ($subscriptionType = $this->getSubscriptionType($purchase)) {
            $subscription = $this->findSubscriptionForWebhook($billable, $subscriptionType);

            if ($subscription instanceof Subscription) {
                DB::transaction(function () use ($subscription, $purchaseData): void {
                    $locked = Subscription::query()->forOwner(includeGlobal: false)->whereKey($subscription->getKey())->lockForUpdate()->first();

                    if (! $locked instanceof Subscription || $locked->canceled() || in_array($locked->chip_status, [SubscriptionStatus::Canceled, SubscriptionStatus::Paused, SubscriptionStatus::IncompleteExpired, SubscriptionStatus::Unpaid], true) || $this->alreadyProcessed($purchaseData->id)) {
                        return;
                    }

                    $locked->forceFill(['chip_status' => SubscriptionStatus::PastDue])->save();
                });
            }
        }
    }

    /**
     * @param  Model&BillableModel  $billable
     */
    private function saveRecurringToken(Model $billable, string $recurringToken, array $purchase): void
    {
        $transactionData = $purchase['transaction_data'] ?? [];
        $extra = is_array($transactionData) && is_array($transactionData['extra'] ?? null)
            ? $transactionData['extra']
            : [];
        $paymentMethod = is_array($transactionData) && is_string($transactionData['payment_method'] ?? null)
            ? $transactionData['payment_method']
            : null;

        Cashier::paymentMethodStore()->saveForBillable(
            new RecordReference($billable->getMorphClass(), (string) $billable->getKey()),
            $recurringToken,
            attributes: [
                'type' => $paymentMethod,
                'brand' => $paymentMethod,
                'last_four' => $this->lastFourFromMaskedPan($extra['masked_pan'] ?? null),
                'metadata' => PaymentMethodMetadata::fromPurchase($purchase, $paymentMethod),
            ],
            makeDefault: ! $billable->hasDefaultPaymentMethod(),
        );
    }

    /**
     * CHIP exposes card digits as the trailing digits of transaction_data.extra.masked_pan.
     */
    private function lastFourFromMaskedPan(mixed $maskedPan): ?string
    {
        if (! is_string($maskedPan) || preg_match('/(\d{4})$/', $maskedPan, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    /**
     * @param  Model&BillableModel  $billable
     */
    private function syncSubscriptionPayment(Model $billable, string $subscriptionType, PurchaseData $purchaseData): void
    {
        $subscription = $this->findSubscriptionForWebhook($billable, $subscriptionType);

        if (! $subscription instanceof Subscription) {
            return;
        }

        if ($subscription->chip_status === SubscriptionStatus::Canceled || $subscription->canceled()) {
            return;
        }

        $purchaseId = $purchaseData->id;
        $actualAmount = $purchaseData->getAmountInCents();
        $actualCurrency = mb_strtoupper($purchaseData->getCurrency());

        $settledPeriodKey = $subscription->next_billing_at instanceof CarbonImmutable
            ? ClaimRenewalAttempt::periodKeyFor($subscription)
            : null;

        try {
            /** @var array{mismatch: bool, expected_amount?: int, actual_amount?: int, expected_currency?: string, actual_currency?: string, period_key?: string|null}|null $outcome */
            $outcome = DB::transaction(function () use ($billable, $subscription, $purchaseId, $actualAmount, $actualCurrency): ?array {
                $locked = Subscription::query()->forOwner(includeGlobal: false)->lockForUpdate()->find($subscription->id);

                if (! $locked instanceof Subscription) {
                    return null;
                }

                if ($locked->chip_status === SubscriptionStatus::Canceled || $locked->canceled()) {
                    return null;
                }

                if ($this->alreadyProcessed($purchaseId)) {
                    return null;
                }

                $locked->loadMissing('items');

                $lockedPeriodKey = $locked->next_billing_at instanceof CarbonImmutable
                    ? ClaimRenewalAttempt::periodKeyFor($locked)
                    : null;
                $expectedAttempt = $lockedPeriodKey === null
                    ? null
                    : RenewalAttempt::query()
                        ->where('subscription_id', $locked->id)
                        ->where('period_key', $lockedPeriodKey)
                        ->whereIn('status', ['claimed', 'unknown'])
                        ->where(function ($query) use ($purchaseId): void {
                            $query->whereNull('purchase_id')->orWhere('purchase_id', $purchaseId);
                        })
                        ->orderByDesc('created_at')
                        ->first();
                $expectedAmount = $expectedAttempt?->amount_minor ?? $locked->renewalAmount();
                $expectedCurrency = mb_strtoupper($billable->preferredCurrency());

                if ($actualAmount !== $expectedAmount || $actualCurrency !== $expectedCurrency) {
                    $this->recordMismatchAttempt($locked, $purchaseId, $lockedPeriodKey, $actualAmount, $actualCurrency, $expectedCurrency);

                    return [
                        'mismatch' => true,
                        'expected_amount' => $expectedAmount,
                        'actual_amount' => $actualAmount,
                        'expected_currency' => $expectedCurrency,
                        'actual_currency' => $actualCurrency,
                        'period_key' => $lockedPeriodKey,
                    ];
                }

                $locked->forceFill([
                    'chip_status' => $locked->chip_status === SubscriptionStatus::Paused ? SubscriptionStatus::Paused : SubscriptionStatus::Active,
                    'next_billing_at' => (new BillingCycle(
                        $locked->billing_interval ?? 'month',
                        (int) $locked->billing_interval_count,
                    ))->advance($locked->next_billing_at instanceof CarbonImmutable ? $locked->next_billing_at : CarbonImmutable::now()),
                ])->save();

                $this->completeMatchingAttempt($expectedAttempt, $purchaseId, $actualAmount);
                $this->recordProcessedPurchase($locked, $purchaseId, $lockedPeriodKey, $actualAmount);

                return ['mismatch' => false];
            }, attempts: 3);
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            // Distinct-purchase period collision: the settle rolled back and
            // the pending claim is preserved. Defer the hook past commit so
            // an outer rollback suppresses it; with no outer transaction this
            // runs immediately, preserving existing behavior.
            DB::afterCommit(function () use ($subscription, $purchaseId, $settledPeriodKey): void {
                Log::warning('CHIP settled-period purchase rolled back; host reconciliation required.', [
                    'subscription_id' => $subscription->id,
                    'purchase_id' => $purchaseId,
                    'period_key' => $settledPeriodKey,
                ]);

                SettledPeriodPurchaseConflict::dispatch(new RecordReference($subscription->getMorphClass(), (string) $subscription->getKey()), $purchaseId, $settledPeriodKey);
            });

            return;
        }

        if (($outcome['mismatch'] ?? false) !== true) {
            return;
        }

        // Amount/currency mismatch: the period stays unsettled and the
        // attempt is terminally recorded. Publish after commit so a retry
        // or outer rollback cannot duplicate listener side effects.
        $settledPeriodKey = $outcome['period_key'] ?? $settledPeriodKey;

        DB::afterCommit(function () use ($subscription, $purchaseId, $settledPeriodKey, $outcome): void {
            Log::warning('CHIP purchase mismatch; host reconciliation required.', [
                'subscription_id' => $subscription->id,
                'purchase_id' => $purchaseId,
                'period_key' => $settledPeriodKey,
                'expected_amount_minor' => $outcome['expected_amount'],
                'actual_amount_minor' => $outcome['actual_amount'],
                'expected_currency' => $outcome['expected_currency'],
                'actual_currency' => $outcome['actual_currency'],
            ]);

            SettledPeriodPurchaseConflict::dispatch(
                new RecordReference($subscription->getMorphClass(), (string) $subscription->getKey()),
                $purchaseId,
                $settledPeriodKey,
                $outcome['expected_amount'],
                $outcome['actual_amount'],
                $outcome['expected_currency'],
                $outcome['actual_currency'],
            );
        });
    }

    private function findSubscriptionForWebhook(Model $billable, string $subscriptionType): ?Subscription
    {
        if ((bool) config('cashier-chip.features.owner.enabled', false) && OwnerContext::resolve() === null) {
            return null;
        }

        return Subscription::query()->forOwner(includeGlobal: false)
            ->where('billable_type', $billable->getMorphClass())
            ->where('billable_id', (string) $billable->getKey())
            ->where('type', $subscriptionType)
            ->latest('created_at')
            ->first();
    }

    private function alreadyProcessed(string $purchaseId): bool
    {
        if ((bool) config('cashier-chip.features.owner.enabled', false) && OwnerContext::resolve() === null) {
            return false;
        }

        return RenewalAttempt::query()
            ->where('purchase_id', $purchaseId)
            ->whereIn('status', ['completed', 'mismatch'])
            ->exists();
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());

        if ($sqlState === '23505') {
            return true;
        }

        if ($sqlState !== '23000') {
            return false;
        }

        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        $message = mb_strtolower($exception->getMessage());

        return in_array($driverCode, [19, 1062], true)
            && (str_contains($message, 'unique constraint') || str_contains($message, 'duplicate entry'));
    }

    private function completeMatchingAttempt(?RenewalAttempt $attempt, string $purchaseId, int $actualAmount): void
    {
        if (! $attempt instanceof RenewalAttempt) {
            return;
        }

        // The gateway's actual charge is ledger truth for the settled
        // period, even when the claim predates a price change. A query
        // update bypasses the frozen-amount model guard by design here.
        RenewalAttempt::query()
            ->whereKey($attempt->id)
            ->whereIn('status', ['claimed', 'unknown'])
            ->update([
                'status' => 'completed',
                'purchase_id' => $purchaseId,
                'amount_minor' => $actualAmount,
                'last_error_code' => null,
                'lease_expires_at' => null,
                'completed_at' => CarbonImmutable::now(),
                'updated_at' => CarbonImmutable::now(),
            ]);
    }

    private function recordProcessedPurchase(Subscription $subscription, string $purchaseId, ?string $periodKey, int $actualAmount): void
    {
        $existing = RenewalAttempt::query()
            ->where('purchase_id', $purchaseId)
            ->orderByDesc('created_at')
            ->lockForUpdate()
            ->first();

        if ($existing instanceof RenewalAttempt) {
            if (in_array($existing->status, ['completed', 'mismatch'], true)) {
                return;
            }

            if (in_array($existing->status, ['claimed', 'unknown'], true)) {
                $this->completeMatchingAttempt($existing, $purchaseId, $actualAmount);

                return;
            }
        }

        $effectivePeriodKey = $periodKey;

        if ($periodKey !== null) {
            $holder = RenewalAttempt::query()
                ->where('subscription_id', $subscription->id)
                ->where('period_key', $periodKey)
                ->lockForUpdate()
                ->first();

            if ($holder instanceof RenewalAttempt
                && (in_array($holder->status, ['failed', 'skipped'], true) || $existing instanceof RenewalAttempt)) {
                $effectivePeriodKey = null;
            }
        }

        RenewalAttempt::create([
            'subscription_id' => $subscription->id,
            'status' => 'completed',
            'amount_minor' => $actualAmount,
            'period_key' => $effectivePeriodKey,
            'purchase_id' => $purchaseId,
            'completed_at' => CarbonImmutable::now(),
        ]);
    }

    private function recordMismatchAttempt(
        Subscription $subscription,
        string $purchaseId,
        ?string $periodKey,
        int $actualAmount,
        string $actualCurrency,
        string $expectedCurrency,
    ): void {
        $code = $actualCurrency !== $expectedCurrency ? 'CURRENCY_MISMATCH' : 'AMOUNT_MISMATCH';

        // Exact purchase match only: a mismatched webhook must never steal
        // an unrelated pending (null purchase) claim. The pending claim stays
        // for the correct purchase.
        $matched = RenewalAttempt::query()
            ->where('subscription_id', $subscription->id)
            ->whereIn('status', ['claimed', 'unknown'])
            ->where('purchase_id', $purchaseId)
            ->orderByDesc('created_at')
            ->first();

        if ($matched instanceof RenewalAttempt) {
            RenewalAttempt::query()
                ->whereKey($matched->id)
                ->whereIn('status', ['claimed', 'unknown'])
                ->update([
                    'status' => 'mismatch',
                    'amount_minor' => $actualAmount,
                    'last_error_code' => $code,
                    'lease_expires_at' => null,
                    'completed_at' => CarbonImmutable::now(),
                    'updated_at' => CarbonImmutable::now(),
                ]);

            return;
        }

        // Only terminal completed/mismatch evidence suppresses a new receipt.
        // A pre-existing failed/skipped (or pending for another subscription)
        // row must not swallow the paid mismatch; the actual charge still
        // needs its own terminal ledger row for idempotency and reconciliation.
        if (RenewalAttempt::query()->where('purchase_id', $purchaseId)->whereIn('status', ['completed', 'mismatch'])->exists()) {
            return;
        }

        // Pending-period collision: when the billing period is already
        // occupied by an unrelated attempt, the mismatch receipt uses a null
        // period_key (the unique allows multiple nulls) so it cannot steal
        // or overwrite the occupant. The caller still carries the known
        // billing-period key in the event/log. With no occupant, keep the
        // period_key so the mismatch blocks fresh claims until reconciled.
        $effectivePeriodKey = $periodKey;

        if ($periodKey !== null) {
            $occupied = RenewalAttempt::query()
                ->where('subscription_id', $subscription->id)
                ->where('period_key', $periodKey)
                ->exists();

            if ($occupied) {
                $effectivePeriodKey = null;
            }
        }

        RenewalAttempt::create([
            'subscription_id' => $subscription->id,
            'status' => 'mismatch',
            'amount_minor' => $actualAmount,
            'period_key' => $effectivePeriodKey,
            'purchase_id' => $purchaseId,
            'last_error_code' => $code,
            'completed_at' => CarbonImmutable::now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function getSubscriptionType(array $payload): ?string
    {
        $metadata = Arr::get($payload, 'purchase.metadata', []);

        $subscriptionType = is_array($metadata) ? $metadata['subscription_type'] ?? null : null;

        return is_string($subscriptionType) && $subscriptionType !== '' ? $subscriptionType : null;
    }
}
