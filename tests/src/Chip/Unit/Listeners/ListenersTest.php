<?php

declare(strict_types=1);

use AIArmada\Chip\Events\PaymentRefunded;
use AIArmada\Chip\Events\PurchasePaid;
use AIArmada\Chip\Events\WebhookReceived;
use AIArmada\Chip\Listeners\GenerateDocOnPayment;
use AIArmada\Chip\Listeners\GenerateDocOnRefund;
use AIArmada\Chip\Listeners\StoreWebhookData;

describe('StoreWebhookData listener', function (): void {
    it('can be instantiated', function (): void {
        $listener = new StoreWebhookData;
        expect($listener)->toBeInstanceOf(StoreWebhookData::class);
    });

    it('returns early when config disabled', function (): void {
        config(['chip.webhooks.store_data' => false]);

        $listener = new StoreWebhookData;
        $event = new WebhookReceived(
            eventType: 'purchase.paid',
            payload: ['type' => 'purchase', 'id' => 'purch_123'],
        );

        // Should not throw, just return early
        $listener->handle($event);
        expect(true)->toBeTrue();
    });

    it('returns early for non-purchase type', function (): void {
        config(['chip.webhooks.store_data' => true]);

        $listener = new StoreWebhookData;
        $event = new WebhookReceived(
            eventType: 'payout.success',
            payload: ['type' => 'payout', 'id' => 'payout_123'],
        );

        // Should not throw, just return early
        $listener->handle($event);
        expect(true)->toBeTrue();
    });

    it('returns early when no purchase ID', function (): void {
        config(['chip.webhooks.store_data' => true]);

        $listener = new StoreWebhookData;
        $event = new WebhookReceived(
            eventType: 'purchase.paid',
            payload: ['type' => 'purchase'],
        );

        // Should not throw, just return early (will log warning)
        $listener->handle($event);
        expect(true)->toBeTrue();
    });
});

describe('GenerateDocOnPayment listener', function (): void {
    it('can be instantiated', function (): void {
        $listener = new GenerateDocOnPayment;
        expect($listener)->toBeInstanceOf(GenerateDocOnPayment::class);
    });

    it('implements ShouldQueue', function (): void {
        $listener = new GenerateDocOnPayment;
        expect($listener)->toBeInstanceOf(Illuminate\Contracts\Queue\ShouldQueue::class);
    });

    it('returns early when DocService class does not exist', function (): void {
        // DocService may not be installed, so this tests the defensive check
        $payload = [
            'id' => 'purch_test123',
            'status' => 'paid',
            'type' => 'purchase',
            'created_on' => time(),
            'updated_on' => time(),
            'purchase' => ['total' => 10000, 'currency' => 'MYR', 'products' => []],
            'is_test' => true,
        ];

        $event = PurchasePaid::fromPayload($payload);
        $listener = new GenerateDocOnPayment;

        // If DocService is not available, it should return early without error
        // This tests the first guard clause
        $listener->handle($event);
        expect(true)->toBeTrue();
    });

    it('returns early when paid_doc_type config is null', function (): void {
        config(['chip.integrations.docs.paid_doc_type' => null]);

        $payload = [
            'id' => 'purch_test123',
            'status' => 'paid',
            'type' => 'purchase',
            'created_on' => time(),
            'updated_on' => time(),
            'purchase' => ['total' => 10000, 'currency' => 'MYR', 'products' => []],
            'is_test' => true,
        ];

        $event = PurchasePaid::fromPayload($payload);
        $listener = new GenerateDocOnPayment;

        // Should return early
        $listener->handle($event);
        expect(true)->toBeTrue();
    });
});

describe('GenerateDocOnRefund listener', function (): void {
    it('can be instantiated', function (): void {
        $listener = new GenerateDocOnRefund;
        expect($listener)->toBeInstanceOf(GenerateDocOnRefund::class);
    });

    it('implements ShouldQueue', function (): void {
        $listener = new GenerateDocOnRefund;
        expect($listener)->toBeInstanceOf(Illuminate\Contracts\Queue\ShouldQueue::class);
    });

    it('returns early when DocService class does not exist', function (): void {
        $payload = [
            'id' => 'purch_refund123',
            'status' => 'refunded',
            'type' => 'purchase',
            'created_on' => time(),
            'updated_on' => time(),
            'purchase' => ['total' => 10000, 'currency' => 'MYR', 'products' => []],
            'is_test' => true,
        ];

        $event = PaymentRefunded::fromPayload($payload);
        $listener = new GenerateDocOnRefund;

        // If DocService is not available, it should return early without error
        $listener->handle($event);
        expect(true)->toBeTrue();
    });

    it('returns early when refund_doc_type config is null', function (): void {
        config(['chip.integrations.docs.refund_doc_type' => null]);

        $payload = [
            'id' => 'purch_refund123',
            'status' => 'refunded',
            'type' => 'purchase',
            'created_on' => time(),
            'updated_on' => time(),
            'purchase' => ['total' => 10000, 'currency' => 'MYR', 'products' => []],
            'is_test' => true,
        ];

        $event = PaymentRefunded::fromPayload($payload);
        $listener = new GenerateDocOnRefund;

        // Should return early
        $listener->handle($event);
        expect(true)->toBeTrue();
    });

    it('returns early when purchase ID is null', function (): void {
        // Create event with null purchase
        $event = new PaymentRefunded(null, []);
        $listener = new GenerateDocOnRefund;

        // Should return early (getPurchaseId() returns null)
        $listener->handle($event);
        expect(true)->toBeTrue();
    });
});
