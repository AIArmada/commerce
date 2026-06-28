<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Listeners;

use AIArmada\CashierChip\Actions\CancelChipSubscription;
use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\CashierChip\Subscription\Subscription;
use AIArmada\Chip\Events\BillingCancelled;
use AIArmada\CommerceSupport\Support\OwnerContext;

class HandleBillingCancelled
{
    public function handle(BillingCancelled $event): void
    {
        if ((bool) config('cashier-chip.features.owner.enabled', true) && OwnerContext::resolve() === null) {
            return;
        }

        $billingTemplateClient = $event->billingTemplateClient;

        $clientId = $billingTemplateClient->client_id;

        if ($clientId === '') {
            return;
        }

        $billable = (bool) config('cashier-chip.features.owner.enabled', true)
            ? Cashier::findBillableForWebhook($clientId)
            : Cashier::findBillable($clientId);

        if ($billable === null) {
            return;
        }

        $query = Subscription::query()
            ->where('billable_type', $billable->getMorphClass())
            ->where('billable_id', (string) $billable->getKey())
            ->where(function ($query) use ($billingTemplateClient): void {
                $query->where('chip_billing_template_id', $billingTemplateClient->billing_template_id)
                    ->orWhere('recurring_token', $billingTemplateClient->recurring_token);
            });

        $subscription = $query->first();

        if ($subscription) {
            CancelChipSubscription::run($subscription);
        }
    }
}
