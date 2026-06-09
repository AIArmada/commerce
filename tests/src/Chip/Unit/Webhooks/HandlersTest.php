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
        $handler = app(PurchasePaidHandler::class);
        expect($handler)->toBeInstanceOf(WebhookHandler::class);
    });

    it('returns skipped when purchase not found', function (): void {
        $handler = app(PurchasePaidHandler::class);

        $payload = new EnrichedWebhookPayload(
            event: 'payment.paid',
            rawPayload: ['id' => 'purchase_123'],
            localPurchase: null,
        );

        $result = $handler->handle($payload);

        expect($result)->toBeInstanceOf(WebhookResult::class);
        expect($result->isSkipped())->toBeTrue()
            ->and($result->message)->toContain('not found');
    });
});

describe('PurchaseCancelledHandler', function (): void {
    it('can be instantiated', function (): void {
        $handler = app(PurchaseCancelledHandler::class);
        expect($handler)->toBeInstanceOf(WebhookHandler::class);
    });

    it('returns skipped when purchase not found', function (): void {
        $handler = app(PurchaseCancelledHandler::class);

        $payload = new EnrichedWebhookPayload(
            event: 'payment.cancelled',
            rawPayload: ['id' => 'purchase_456'],
            localPurchase: null,
        );

        $result = $handler->handle($payload);

        expect($result)->toBeInstanceOf(WebhookResult::class);
        expect($result->isSkipped())->toBeTrue()
            ->and($result->message)->toContain('not found');
    });
});

describe('PurchaseRefundedHandler', function (): void {
    it('can be instantiated', function (): void {
        $handler = app(PurchaseRefundedHandler::class);
        expect($handler)->toBeInstanceOf(WebhookHandler::class);
    });

    it('returns skipped when purchase not found', function (): void {
        $handler = app(PurchaseRefundedHandler::class);

        $payload = new EnrichedWebhookPayload(
            event: 'payment.refunded',
            rawPayload: ['id' => 'purchase_789'],
            localPurchase: null,
        );

        $result = $handler->handle($payload);

        expect($result)->toBeInstanceOf(WebhookResult::class);
        expect($result->isSkipped())->toBeTrue()
            ->and($result->message)->toContain('not found');
    });
});

describe('PaymentFailedHandler', function (): void {
    it('can be instantiated', function (): void {
        $handler = app(PaymentFailedHandler::class);
        expect($handler)->toBeInstanceOf(WebhookHandler::class);
    });

    it('returns skipped when purchase not found', function (): void {
        $handler = app(PaymentFailedHandler::class);

        $payload = new EnrichedWebhookPayload(
            event: 'payment.failed',
            rawPayload: ['id' => 'purchase_abc'],
            localPurchase: null,
        );

        $result = $handler->handle($payload);

        expect($result)->toBeInstanceOf(WebhookResult::class);
        expect($result->isSkipped())->toBeTrue()
            ->and($result->message)->toContain('not found');
    });
});

describe('SendCompletedHandler', function (): void {
    it('can be instantiated', function (): void {
        $handler = app(SendCompletedHandler::class);
        expect($handler)->toBeInstanceOf(WebhookHandler::class);
    });

    it('returns skipped when instruction not found', function (): void {
        $handler = app(SendCompletedHandler::class);

        $payload = new EnrichedWebhookPayload(
            event: 'send.completed',
            rawPayload: ['id' => 'send_123'],
            localPurchase: null,
        );

        $result = $handler->handle($payload);

        expect($result)->toBeInstanceOf(WebhookResult::class);
    });
});

describe('SendRejectedHandler', function (): void {
    it('can be instantiated', function (): void {
        $handler = app(SendRejectedHandler::class);
        expect($handler)->toBeInstanceOf(WebhookHandler::class);
    });

    it('returns skipped when instruction not found', function (): void {
        $handler = app(SendRejectedHandler::class);

        $payload = new EnrichedWebhookPayload(
            event: 'send.rejected',
            rawPayload: ['id' => 'send_123', 'rejection_reason' => 'Test reason'],
            localPurchase: null,
        );

        $result = $handler->handle($payload);

        expect($result)->toBeInstanceOf(WebhookResult::class);
    });
});
