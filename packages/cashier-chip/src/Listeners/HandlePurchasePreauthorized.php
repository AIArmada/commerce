<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Listeners;

use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\Chip\Events\PurchasePreauthorized;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Listens to chip package PurchasePreauthorized events.
 * Handles saving recurring tokens when a card is preauthorized without a charge.
 */
class HandlePurchasePreauthorized
{
    public function handle(PurchasePreauthorized $event): void
    {
        if ((bool) config('cashier-chip.features.owner.enabled', true) && OwnerContext::resolve() === null) {
            return;
        }

        $purchase = $event->purchase;

        $clientId = $purchase->getClientId();

        if ($clientId === null) {
            return;
        }

        /** @var Model|null $billable */
        $billable = (bool) config('cashier-chip.features.owner.enabled', true)
            ? Cashier::findBillableForWebhook($clientId)
            : Cashier::findBillable($clientId);

        if ($billable === null) {
            return;
        }

        $recurringToken = $purchase->recurring_token;

        if ($recurringToken === null) {
            return;
        }

        // Save the recurring token for future use
        $this->handleRecurringToken($billable, $recurringToken, $purchase->toArray());
    }

    /**
     * Handle recurring token from a purchase.
     *
     * @param  array<string, mixed>  $purchase
     */
    protected function handleRecurringToken(object $billable, string $recurringToken, array $purchase): void
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
}
