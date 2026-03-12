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

        // Update local status
        $localPurchase->update([
            'status' => PurchaseStatus::PAID,
        ]);

        // Dispatch Laravel event
        PurchasePaid::dispatch(
            PurchaseData::from($payload->rawPayload),
            $payload->rawPayload,
        );

        return WebhookResult::handled("Purchase {$localPurchase->id} marked as paid");
    }
}
