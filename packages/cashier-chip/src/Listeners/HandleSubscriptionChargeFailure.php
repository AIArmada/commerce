<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Listeners;

use AIArmada\CashierChip\Actions\CancelChipSubscription;
use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\Chip\Events\PurchaseSubscriptionChargeFailure;
use AIArmada\CommerceSupport\Support\OwnerContext;

class HandleSubscriptionChargeFailure
{
    public function handle(PurchaseSubscriptionChargeFailure $event): void
    {
        if ((bool) config('cashier-chip.features.owner.enabled', true) && OwnerContext::resolve() === null) {
            return;
        }

        $purchase = $event->purchase;

        $clientId = $purchase->getClientId();

        if ($clientId === null) {
            return;
        }

        $billable = (bool) config('cashier-chip.features.owner.enabled', true)
            ? Cashier::findBillableForWebhook($clientId)
            : Cashier::findBillable($clientId);

        if ($billable === null) {
            return;
        }

        $subscriptionType = $this->getSubscriptionTypeFromPurchase($event->payload);

        if ($subscriptionType === null) {
            return;
        }

        $subscription = Cashier::findSubscriptionForWebhook($billable, $subscriptionType);

        if ($subscription) {
            $reason = $event->payload['failure_reason'] ?? $event->payload['error_message'] ?? 'Subscription charge failed';

            app(CancelChipSubscription::class)->markPastDue($subscription, $reason);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function getSubscriptionTypeFromPurchase(array $payload): ?string
    {
        $purchase = $payload['purchase'] ?? $payload;
        $metadata = $purchase['metadata'] ?? [];

        if (isset($metadata['subscription_type'])) {
            return $metadata['subscription_type'];
        }

        $reference = $purchase['reference'] ?? '';
        if (preg_match('/Subscription (\w+)/', $reference, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
