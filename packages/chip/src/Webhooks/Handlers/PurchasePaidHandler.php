<?php

declare(strict_types=1);

namespace AIArmada\Chip\Webhooks\Handlers;

use AIArmada\Chip\Data\EnrichedWebhookPayload;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Data\WebhookResult;
use AIArmada\Chip\Enums\PurchaseStatus;
use AIArmada\Chip\Events\PurchasePaid;

/**
 * Handles purchase.paid webhook events.
 */
class PurchasePaidHandler implements WebhookHandler
{
    public function handle(EnrichedWebhookPayload $payload): WebhookResult
    {
        $localPurchase = $payload->localPurchase;

        if ($localPurchase === null) {
            return WebhookResult::skipped('Purchase not found locally');
        }

        $total = $payload->get('purchase.total');
        $paymentMethod = $payload->get('transaction_data.payment_method');
        $updatedOn = $payload->get('updated_on');

        // Update local status plus the denormalized analytics columns; the
        // payment-level paid_on timestamp lives on the Payment row stored by
        // the webhook persistence listener.
        $localPurchase->forceFill([
            'status' => PurchaseStatus::PAID->value,
            'total_minor' => is_numeric($total) ? (int) $total : $localPurchase->total_minor,
            'payment_method' => is_string($paymentMethod) && $paymentMethod !== '' ? $paymentMethod : $localPurchase->payment_method,
            'updated_on' => is_numeric($updatedOn) ? (int) $updatedOn : $localPurchase->getRawOriginal('updated_on'),
        ])->save();

        // Dispatch Laravel event
        PurchasePaid::dispatch(
            PurchaseData::from($payload->rawPayload),
            $payload->rawPayload,
        );

        return WebhookResult::handled("Purchase {$localPurchase->id} marked as paid");
    }
}
