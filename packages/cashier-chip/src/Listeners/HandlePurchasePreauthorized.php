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
}
