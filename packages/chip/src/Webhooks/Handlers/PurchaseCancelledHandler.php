<?php

declare(strict_types=1);

namespace AIArmada\Chip\Webhooks\Handlers;

use AIArmada\Chip\Data\EnrichedWebhookPayload;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Data\WebhookResult;
use AIArmada\Chip\Events\PurchaseCancelled;
use AIArmada\Chip\Features\Collect\Api\PurchaseWebhookState;

/**
 * Handles purchase.cancelled webhook events.
 */
class PurchaseCancelledHandler implements WebhookHandler
{
    public function handle(EnrichedWebhookPayload $payload): WebhookResult
    {
        $localPurchaseId = $payload->localPurchaseId;

        if ($localPurchaseId === null) {
            return WebhookResult::skipped('Purchase not found locally');
        }

        if (! app(PurchaseWebhookState::class)->markCancelled($localPurchaseId)) {
            return WebhookResult::skipped('Purchase is not eligible for cancellation');
        }

        // Dispatch Laravel event
        PurchaseCancelled::dispatch(
            PurchaseData::from($payload->rawPayload),
            $payload->rawPayload,
        );

        return WebhookResult::handled("Purchase {$localPurchaseId} marked as cancelled");
    }
}
