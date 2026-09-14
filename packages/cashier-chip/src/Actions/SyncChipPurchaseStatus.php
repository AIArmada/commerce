<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Actions;

use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\CashierChip\Contracts\BillableContract;
use AIArmada\CashierChip\Enums\SubscriptionStatus;
use AIArmada\CashierChip\Events\PaymentFailed;
use AIArmada\CashierChip\Events\PaymentSucceeded;
use AIArmada\CashierChip\Events\SettledPeriodPurchaseConflict;
use AIArmada\CashierChip\Subscription\RenewalAttempt;
use AIArmada\CashierChip\Subscription\Subscription;
use AIArmada\CashierChip\Support\PaymentMethodMetadata;
use AIArmada\Chip\Data\PurchaseData;
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
     * @param  Model&BillableContract  $billable
     * @param  array<string, mixed>  $purchase
     */
    public function handle(Model $billable, PurchaseData $purchaseData, array $purchase): void
    {
        if ($this->alreadyProcessed($purchaseData->id)) {
            return;
        }

        PaymentSucceeded::dispatch($billable, $purchaseData->toArray());

        if ($recurringToken = $purchaseData->recurring_token) {
            $this->saveRecurringToken($billable, $recurringToken, $purchase);
        }

        if ($subscriptionType = $this->getSubscriptionType($purchase)) {
            $this->syncSubscriptionPayment($billable, $subscriptionType, $purchaseData->id);
        }
    }

    /**
     * @param  Model&BillableContract  $billable
     * @param  array<string, mixed>  $purchase
     */
    public function syncFailed(Model $billable, PurchaseData $purchaseData, array $purchase): void
    {
        PaymentFailed::dispatch($billable, $purchaseData->toArray());

        if ($subscriptionType = $this->getSubscriptionType($purchase)) {
            $subscription = Cashier::findSubscriptionForWebhook($billable, $subscriptionType);

            if ($subscription && $subscription->chip_status !== SubscriptionStatus::Canceled) {
                $subscription->forceFill([
                    'chip_status' => SubscriptionStatus::PastDue,
                ])->save();
            }
        }
    }

    /**
     * @param  Model&BillableContract  $billable
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
            $billable,
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
     * @param  Model&BillableContract  $billable
     */
    private function syncSubscriptionPayment(Model $billable, string $subscriptionType, string $purchaseId): void
    {
        $subscription = Cashier::findSubscriptionForWebhook($billable, $subscriptionType);

        if (! $subscription instanceof Subscription) {
            return;
        }

        if ($subscription->chip_status === SubscriptionStatus::Canceled || $subscription->canceled()) {
            return;
        }

        $settledPeriodKey = $subscription->next_billing_at instanceof CarbonImmutable
            ? ClaimRenewalAttempt::periodKeyFor($subscription)
            : null;

        try {
            DB::transaction(function () use ($subscription, $purchaseId, $settledPeriodKey): void {
                $locked = Subscription::query()->lockForUpdate()->find($subscription->id);

                if (! $locked instanceof Subscription) {
                    return;
                }

                if ($locked->chip_status === SubscriptionStatus::Canceled || $locked->canceled()) {
                    return;
                }

                if ($this->alreadyProcessed($purchaseId)) {
                    return;
                }

                $locked->forceFill([
                    'chip_status' => SubscriptionStatus::Active,
                    'next_billing_at' => Subscription::advanceBillingDate(
                        CarbonImmutable::now(),
                        $locked->billing_interval,
                        $locked->billing_interval_count
                    ),
                ])->save();

                $this->completeMatchingAttempt($locked, $purchaseId);
                $this->recordProcessedPurchase($locked, $purchaseId, $settledPeriodKey);
            }, attempts: 3);
        } catch (QueryException $exception) {
            if (! in_array((string) ($exception->errorInfo[0] ?? $exception->getCode()), ['23000', '23505'], true)) {
                throw $exception;
            }

            Log::warning('CHIP settled-period purchase rolled back; host reconciliation required.', [
                'subscription_id' => $subscription->id,
                'purchase_id' => $purchaseId,
                'period_key' => $settledPeriodKey,
            ]);

            SettledPeriodPurchaseConflict::dispatch($subscription, $purchaseId, $settledPeriodKey);
        }
    }

    private function alreadyProcessed(string $purchaseId): bool
    {
        if ((bool) config('cashier-chip.features.owner.enabled', false) && OwnerContext::resolve() === null) {
            return false;
        }

        return RenewalAttempt::query()
            ->where('purchase_id', $purchaseId)
            ->where('status', 'completed')
            ->exists();
    }

    private function completeMatchingAttempt(Subscription $subscription, string $purchaseId): void
    {
        $attempt = RenewalAttempt::query()
            ->where('subscription_id', $subscription->id)
            ->whereIn('status', ['claimed', 'unknown'])
            ->where(function ($query) use ($purchaseId): void {
                $query->whereNull('purchase_id')->orWhere('purchase_id', $purchaseId);
            })
            ->orderByDesc('created_at')
            ->first();

        if (! $attempt instanceof RenewalAttempt) {
            return;
        }

        $attempt->forceFill([
            'status' => 'completed',
            'purchase_id' => $purchaseId,
            'last_error_code' => null,
            'lease_expires_at' => null,
            'completed_at' => CarbonImmutable::now(),
        ])->save();
    }

    private function recordProcessedPurchase(Subscription $subscription, string $purchaseId, ?string $periodKey): void
    {
        $exists = RenewalAttempt::query()->where('purchase_id', $purchaseId)->exists();

        if ($exists) {
            return;
        }

        RenewalAttempt::create([
            'subscription_id' => $subscription->id,
            'status' => 'completed',
            'amount_minor' => $subscription->renewalAmount(),
            'period_key' => $periodKey,
            'purchase_id' => $purchaseId,
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
