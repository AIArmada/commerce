<?php

declare(strict_types=1);

namespace AIArmada\Chip\Webhooks\Handlers;

use AIArmada\Chip\Data\EnrichedWebhookPayload;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Data\WebhookResult;
use AIArmada\Chip\Events\PurchasePaid;
use AIArmada\Chip\Features\Collect\Api\PurchaseWebhookState;

/**
 * Handles purchase.paid webhook events.
 */
class PurchasePaidHandler implements WebhookHandler
{
    public function handle(EnrichedWebhookPayload $payload): WebhookResult
    {
        $localPurchaseId = $payload->localPurchaseId;

        if ($localPurchaseId === null) {
            return WebhookResult::skipped('Purchase not found locally');
        }

        $total = $payload->get('purchase.total');
        $paymentMethod = $payload->get('transaction_data.payment_method');
        $updatedOn = $payload->get('updated_on');

        if (! app(PurchaseWebhookState::class)->markPaid(
            $localPurchaseId,
            is_numeric($total) ? (int) $total : null,
            is_string($paymentMethod) && $paymentMethod !== '' ? $paymentMethod : null,
            is_numeric($updatedOn) ? (int) $updatedOn : null,
        )) {
            return WebhookResult::skipped('Purchase is not eligible for a paid update');
        }

        // Dispatch Laravel event
        PurchasePaid::dispatch(
            PurchaseData::from($payload->rawPayload),
            $payload->rawPayload,
        );

        return WebhookResult::handled("Purchase {$localPurchaseId} marked as paid");
    }
}
