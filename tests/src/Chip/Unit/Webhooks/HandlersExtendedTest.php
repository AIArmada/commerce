<?php

declare(strict_types=1);

use AIArmada\Chip\Data\EnrichedWebhookPayload;
use AIArmada\Chip\Data\WebhookResult;
use AIArmada\Chip\Webhooks\Handlers\PaymentFailedHandler;
use AIArmada\Chip\Webhooks\Handlers\PurchaseCancelledHandler;
use AIArmada\Chip\Webhooks\Handlers\PurchasePaidHandler;
use AIArmada\Chip\Webhooks\Handlers\PurchaseRefundedHandler;

/**
 * @param  array<string, mixed>  $rawPayload
 */
function createTestEnrichedPayload(string $event, array $rawPayload = []): EnrichedWebhookPayload
{
    $defaultPayload = $event === 'payment.refunded'
        ? [
            'id' => 'payment-' . uniqid(),
            'type' => 'payment',
            'status' => 'refunded',
            'is_test' => true,
            'client_id' => 'client-123',
            'created_on' => time(),
            'updated_on' => time(),
            'payment' => [
                'amount' => 10000,
                'currency' => 'MYR',
                'net_amount' => 10000,
                'fee_amount' => 0,
                'pending_amount' => 0,
                'payment_type' => 'refund',
                'is_outgoing' => true,
            ],
            'related_to' => [
                'type' => 'purchase',
                'id' => 'purchase-123',
            ],
        ]
        : [
            'id' => 'purchase-' . uniqid(),
            'type' => 'purchase',
            'status' => 'paid',
            'is_test' => true,
            'client_id' => 'client-123',
            'created_on' => time(),
            'updated_on' => time(),
        ];

    $payload = array_merge($defaultPayload, $rawPayload);

    return new EnrichedWebhookPayload(
        event: $event,
        rawPayload: $payload,
        localPurchase: null,
        owner: null,
        receivedAt: now(),
        purchaseId: data_get($payload, 'related_to.id') ?? ($payload['id'] ?? 'purchase-123'),
        clientId: $payload['client_id'] ?? 'client-123',
    );
}

describe('PurchasePaidHandler', function (): void {
    it('can be instantiated', function (): void {
        $handler = app(PurchasePaidHandler::class);
        expect($handler)->toBeInstanceOf(PurchasePaidHandler::class);
    });

    it('returns skipped result when no local purchase exists', function (): void {
        $handler = app(PurchasePaidHandler::class);
        $payload = createTestEnrichedPayload('purchase.paid');

        $result = $handler->handle($payload);

        expect($result)->toBeInstanceOf(WebhookResult::class);
        expect($result->isSkipped())->toBeTrue();
    });

    it('has handle method that accepts EnrichedWebhookPayload', function (): void {
        $handler = app(PurchasePaidHandler::class);
        $reflection = new ReflectionMethod($handler, 'handle');
        $params = $reflection->getParameters();

        expect($params)->toHaveCount(1);
        expect($params[0]->getType()->getName())->toBe(EnrichedWebhookPayload::class);
    });
});

describe('PurchaseCancelledHandler', function (): void {
    it('can be instantiated', function (): void {
        $handler = app(PurchaseCancelledHandler::class);
        expect($handler)->toBeInstanceOf(PurchaseCancelledHandler::class);
    });

    it('returns skipped result when no local purchase exists', function (): void {
        $handler = app(PurchaseCancelledHandler::class);
        $payload = createTestEnrichedPayload('purchase.cancelled');

        $result = $handler->handle($payload);

        expect($result)->toBeInstanceOf(WebhookResult::class);
        expect($result->isSkipped())->toBeTrue();
    });
});

describe('PaymentFailedHandler', function (): void {
    it('can be instantiated', function (): void {
        $handler = app(PaymentFailedHandler::class);
        expect($handler)->toBeInstanceOf(PaymentFailedHandler::class);
    });

    it('returns skipped result when no local purchase exists', function (): void {
        $handler = app(PaymentFailedHandler::class);
        $payload = createTestEnrichedPayload('purchase.payment_failure');

        $result = $handler->handle($payload);

        expect($result)->toBeInstanceOf(WebhookResult::class);
        expect($result->isSkipped())->toBeTrue();
    });

    it('handles payload with failure reason', function (): void {
        $handler = app(PaymentFailedHandler::class);
        $payload = createTestEnrichedPayload('purchase.payment_failure', [
            'status' => 'error',
            'failure_reason' => 'Insufficient funds',
        ]);

        $result = $handler->handle($payload);

        expect($result)->toBeInstanceOf(WebhookResult::class);
    });
});

describe('PurchaseRefundedHandler', function (): void {
    it('can be instantiated', function (): void {
        $handler = app(PurchaseRefundedHandler::class);
        expect($handler)->toBeInstanceOf(PurchaseRefundedHandler::class);
    });

    it('returns skipped result when no local purchase exists', function (): void {
        $handler = app(PurchaseRefundedHandler::class);
        $payload = createTestEnrichedPayload('payment.refunded');

        $result = $handler->handle($payload);

        expect($result)->toBeInstanceOf(WebhookResult::class);
        expect($result->isSkipped())->toBeTrue();
    });
});

describe('Handler edge cases', function (): void {
    it('all handlers handle empty payload gracefully', function (): void {
        $handlers = [
            app(PurchasePaidHandler::class),
            app(PurchaseCancelledHandler::class),
            app(PaymentFailedHandler::class),
            app(PurchaseRefundedHandler::class),
        ];

        $events = [
            'purchase.paid',
            'purchase.cancelled',
            'purchase.payment_failure',
            'payment.refunded',
        ];

        foreach ($handlers as $index => $handler) {
            $payload = createTestEnrichedPayload($events[$index], []);
            $result = $handler->handle($payload);

            expect($result)->toBeInstanceOf(WebhookResult::class);
        }
    });
});
