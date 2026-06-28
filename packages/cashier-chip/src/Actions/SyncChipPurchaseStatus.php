<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Actions;

use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\CashierChip\Contracts\BillableContract;
use AIArmada\CashierChip\Events\PaymentFailed;
use AIArmada\CashierChip\Events\PaymentSucceeded;
use AIArmada\Chip\Data\PurchaseData;
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
        $extra = $transactionData['extra'] ?? [];
        $card = $purchase['card'] ?? [];

        Cashier::paymentMethodStore()->saveForBillable(
            $billable,
            $recurringToken,
            attributes: [
                'type' => $transactionData['payment_method'] ?? 'card',
                'brand' => $card['brand'] ?? $extra['card_brand'] ?? $transactionData['payment_method'] ?? 'card',
                'last_four' => $card['last_4'] ?? $extra['card_last_4'] ?? null,
                'metadata' => $purchase,
            ],
            makeDefault: ! $billable->hasDefaultPaymentMethod(),
        );
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
                'next_billing_at' => now()->add($interval, $count),
            ])->save();
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function getSubscriptionType(array $payload): ?string
    {
        $metadata = Arr::get($payload, 'metadata');

        if (! is_array($metadata)) {
            $metadata = Arr::get($payload, 'purchase.metadata', []);
        }

        $subscriptionType = $metadata['subscription_type'] ?? null;

        if (is_string($subscriptionType) && $subscriptionType !== '') {
            return $subscriptionType;
        }

        $reference = Arr::get($payload, 'reference')
            ?? Arr::get($payload, 'purchase.reference')
            ?? '';

        if (preg_match('/Subscription (\w+)/', $reference, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
