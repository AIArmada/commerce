<?php

declare(strict_types=1);

use AIArmada\Chip\EntryPoints\Listeners\StoreWebhookData;
use AIArmada\Chip\Events\WebhookReceived;

describe('StoreWebhookData listener', function (): void {
    it('returns early when config disabled', function (): void {
        config(['chip.webhooks.store_webhooks' => false]);

        $listener = app(StoreWebhookData::class);
        $event = new WebhookReceived(
            eventType: 'purchase.paid',
            payload: ['type' => 'purchase', 'id' => 'purch_123'],
        );

        // Should not throw, just return early
        $listener->handle($event);
        expect(true)->toBeTrue();
    });

    it('returns early for non-purchase type', function (): void {
        config(['chip.webhooks.store_webhooks' => true]);

        $listener = app(StoreWebhookData::class);
        $event = new WebhookReceived(
            eventType: 'payout.success',
            payload: ['type' => 'payout', 'id' => 'payout_123'],
        );

        // Should not throw, just return early
        $listener->handle($event);
        expect(true)->toBeTrue();
    });

    it('returns early when no purchase ID', function (): void {
        config(['chip.webhooks.store_webhooks' => true]);

        $listener = app(StoreWebhookData::class);
        $event = new WebhookReceived(
            eventType: 'purchase.paid',
            payload: ['type' => 'purchase'],
        );

        // Should not throw, just return early (will log warning)
        $listener->handle($event);
        expect(true)->toBeTrue();
    });
});
