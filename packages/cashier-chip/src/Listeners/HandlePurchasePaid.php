<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Listeners;

use AIArmada\CashierChip\Actions\SyncChipPurchaseStatus;
use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\Chip\Events\PurchasePaid;
use AIArmada\CommerceSupport\Support\OwnerContext;

class HandlePurchasePaid
{
    public function handle(PurchasePaid $event): void
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

        SyncChipPurchaseStatus::run($billable, $purchase, $event->payload);
    }
}
