<?php

declare(strict_types=1);

use AIArmada\Chip\Features\WebhookDelivery\Models\Webhook;
use AIArmada\Chip\Features\WebhookDelivery\Persistence\WebhookDeliveryStore;

it('recovers an idempotency key released by the shared superseded claim', function (): void {
    config()->set('chip.owner.enabled', false);
    config()->set('chip.webhooks.store_webhooks', true);
    config()->set('chip.webhooks.deduplication', true);

    $failed = Webhook::forceCreate([
        'title' => 'Failed delivery',
        'event_type' => 'purchase.paid',
        'events' => ['purchase.paid'],
        'payload' => ['id' => 'purchase-recovery', 'type' => 'purchase'],
        'status' => 'superseded',
        'idempotency_key' => 'delivery-recovery-key',
        'created_on' => time(),
        'updated_on' => time(),
        'callback' => 'http://example.com/webhook',
    ]);

    $redelivery = Webhook::forceCreate([
        'title' => 'Redelivery',
        'event_type' => 'purchase.paid',
        'events' => ['purchase.paid'],
        'payload' => ['id' => 'purchase-recovery', 'type' => 'purchase'],
        'status' => 'pending',
        'created_on' => time(),
        'updated_on' => time(),
        'callback' => 'http://example.com/webhook',
    ]);

    $claimed = app(WebhookDeliveryStore::class)->claim(
        $redelivery,
        'purchase.paid',
        ['id' => 'purchase-recovery', 'type' => 'purchase'],
        'delivery-recovery-key',
    );

    expect($claimed)->not->toBeNull()
        ->and($claimed?->getKey())->toBe($redelivery->getKey())
        ->and($failed->refresh()->idempotency_key)->toBeNull()
        ->and($failed->status)->toBe('superseded')
        ->and($claimed?->idempotency_key)->toBe('delivery-recovery-key');
});
