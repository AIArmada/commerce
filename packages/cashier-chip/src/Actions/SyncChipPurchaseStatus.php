<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Actions;

use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\CashierChip\Contracts\BillableContract;
use AIArmada\CashierChip\Events\PaymentFailed;
use AIArmada\CashierChip\Events\PaymentSucceeded;
use AIArmada\Chip\Data\PurchaseData;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
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
        PaymentSucceeded::dispatch($billable, $purchaseData->toArray());

        if ($recurringToken = $purchaseData->recurring_token) {
            $this->saveRecurringToken($billable, $recurringToken, $purchase);
        }

        if ($subscriptionType = $this->getSubscriptionType($purchase)) {
            $this->syncSubscriptionPayment($billable, $subscriptionType);
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

            if ($subscription) {
                $subscription->forceFill([
                    'chip_status' => 'past_due',
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
                'metadata' => $purchase,
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
    private function syncSubscriptionPayment(Model $billable, string $subscriptionType): void
    {
        $subscription = Cashier::findSubscriptionForWebhook($billable, $subscriptionType);

        if ($subscription) {
            $interval = $subscription->billing_interval ?? 'month';
            $count = $subscription->billing_interval_count ?? 1;

            $subscription->forceFill([
                'chip_status' => 'active',
                'next_billing_at' => CarbonImmutable::now()->add($interval, $count),
            ])->save();
        }
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
