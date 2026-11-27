<?php

declare(strict_types=1);

namespace AIArmada\Chip\EntryPoints\Listeners;

use AIArmada\Chip\Events\WebhookReceived;
use AIArmada\Chip\Features\Collect\Api\PurchaseMirror;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Support\Facades\Log;

final readonly class StoreWebhookData
{
    public function __construct(private PurchaseMirror $action) {}

    public function handle(WebhookReceived $event): void
    {
        if (! config('chip.webhooks.store_webhooks', true)) {
            return;
        }

        if ((bool) config('chip.owner.enabled', false) && OwnerContext::resolve() === null) {
            Log::channel(config('chip.logging.channel', 'stack'))
                ->warning('CHIP webhook store_webhooks skipped because owner context is missing while owner scoping is enabled', [
                    'event_type' => $event->eventType,
                    'id' => $event->payload['id'] ?? null,
                    'brand_id' => $event->payload['brand_id'] ?? null,
                ]);

            return;
        }

        $payload = $event->payload;
        $payloadType = $payload['type'] ?? null;

        if ($payloadType === 'purchase') {
            $this->action->storePurchasePayload($payload);

            return;
        }

        if ($payloadType === 'payment' && data_get($payload, 'related_to.type') === 'purchase') {
            $this->action->storePayment($payload);
        }
    }
}
