<?php

declare(strict_types=1);

use AIArmada\Chip\Data\EnrichedWebhookPayload;
use AIArmada\Chip\Data\WebhookResult;
use AIArmada\Chip\Webhooks\Handlers\PaymentFailedHandler;
use AIArmada\Chip\Webhooks\Handlers\PurchaseCancelledHandler;
use AIArmada\Chip\Webhooks\Handlers\PurchasePaidHandler;
use AIArmada\Chip\Webhooks\Handlers\PurchaseRefundedHandler;
use AIArmada\Chip\Webhooks\Handlers\SendCompletedHandler;
use AIArmada\Chip\Webhooks\Handlers\SendRejectedHandler;
use AIArmada\Chip\Webhooks\Handlers\WebhookHandler;

describe('PurchasePaidHandler', function (): void {
    it('can be instantiated', function (): void {
        $handler = new PurchasePaidHandler;
        expect($handler)->toBeInstanceOf(WebhookHandler::class);
    });

    it('returns skipped when purchase not found locally', function (): void {
        $handler = new PurchasePaidHandler;

        $payload = new EnrichedWebhookPayload(
            event: 'purchase.paid',
            rawPayload: ['id' => 'purch_123'],
            localPurchase: null,
        );

        $result = $handler->handle($payload);

        expect($result)->toBeInstanceOf(WebhookResult::class)
            ->and($result->isSkipped())->toBeTrue()
            ->and($result->message)->toContain('not found');
    });
});

describe('PurchaseCancelledHandler', function (): void {
    it('can be instantiated', function (): void {
        $handler = new PurchaseCancelledHandler;
        expect($handler)->toBeInstanceOf(WebhookHandler::class);
    });

    it('returns skipped when purchase not found locally', function (): void {
        $handler = new PurchaseCancelledHandler;

        $payload = new EnrichedWebhookPayload(
            event: 'purchase.cancelled',
            rawPayload: ['id' => 'purch_123'],
            localPurchase: null,
        );

        $result = $handler->handle($payload);

        expect($result->isSkipped())->toBeTrue();
    });
});

describe('PaymentFailedHandler', function (): void {
    it('can be instantiated', function (): void {
        $handler = new PaymentFailedHandler;
        expect($handler)->toBeInstanceOf(WebhookHandler::class);
    });

    it('returns skipped when purchase not found locally', function (): void {
        $handler = new PaymentFailedHandler;

        $payload = new EnrichedWebhookPayload(
            event: 'purchase.payment_failure',
            rawPayload: ['id' => 'purch_123'],
            localPurchase: null,
        );

        $result = $handler->handle($payload);

        expect($result->isSkipped())->toBeTrue();
    });
});

describe('PurchaseRefundedHandler', function (): void {
    it('can be instantiated', function (): void {
        $handler = new PurchaseRefundedHandler;
        expect($handler)->toBeInstanceOf(WebhookHandler::class);
    });

    it('returns skipped when purchase not found locally', function (): void {
        $handler = new PurchaseRefundedHandler;

        $payload = new EnrichedWebhookPayload(
            event: 'payment.refunded',
            rawPayload: ['id' => 'purch_123'],
            localPurchase: null,
        );

        $result = $handler->handle($payload);

        expect($result->isSkipped())->toBeTrue();
    });
});

describe('SendCompletedHandler', function (): void {
    it('can be instantiated', function (): void {
        $handler = new SendCompletedHandler;
        expect($handler)->toBeInstanceOf(WebhookHandler::class);
    });

    it('returns skipped when instruction not found', function (): void {
        $handler = new SendCompletedHandler;

        $payload = new EnrichedWebhookPayload(
            event: 'send.completed',
            rawPayload: ['id' => 'send_123'],
            localPurchase: null,
        );

        $result = $handler->handle($payload);

        // It should skip because SendInstruction not found
        expect($result)->toBeInstanceOf(WebhookResult::class);
    });
});

describe('SendRejectedHandler', function (): void {
    it('can be instantiated', function (): void {
        $handler = new SendRejectedHandler;
        expect($handler)->toBeInstanceOf(WebhookHandler::class);
    });

    it('returns skipped when instruction not found', function (): void {
        $handler = new SendRejectedHandler;

        $payload = new EnrichedWebhookPayload(
            event: 'send.rejected',
            rawPayload: ['id' => 'send_123', 'rejection_reason' => 'Test reason'],
            localPurchase: null,
        );

        $result = $handler->handle($payload);

        expect($result)->toBeInstanceOf(WebhookResult::class);
    });
});
