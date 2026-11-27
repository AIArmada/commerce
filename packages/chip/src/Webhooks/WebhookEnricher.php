<?php

declare(strict_types=1);

namespace AIArmada\Chip\Webhooks;

use AIArmada\Chip\Data\EnrichedWebhookPayload;
use AIArmada\Chip\Features\Collect\Api\PurchaseWebhookState;
use AIArmada\CommerceSupport\Data\RecordReference;
use AIArmada\CommerceSupport\Support\OwnerContext;

/**
 * Enriches webhook payloads with local context.
 */
class WebhookEnricher
{
    /**
     * Enrich a webhook payload with local context.
     *
     * @param  array<string, mixed>  $payload
     */
    public function enrich(string $event, array $payload): EnrichedWebhookPayload
    {
        $parsed = EnrichedWebhookPayload::fromPayload($event, $payload);
        $purchaseId = $parsed->purchaseId;
        $localPurchaseId = $purchaseId !== null && app(PurchaseWebhookState::class)->exists($purchaseId) ? $purchaseId : null;

        $owner = OwnerContext::resolve();
        $ownerReference = $owner === null ? null : new RecordReference($owner->getMorphClass(), (string) $owner->getKey());

        return EnrichedWebhookPayload::fromPayload($event, $payload, $localPurchaseId, $ownerReference);
    }
}
