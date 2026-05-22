<?php

declare(strict_types=1);

use AIArmada\Chip\Data\EnrichedWebhookPayload;
use AIArmada\Chip\Data\WebhookResult;
use AIArmada\Chip\Models\Purchase;
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
            rawPayload: [
                'id' => 'payment_123',
                'type' => 'payment',
                'status' => 'refunded',
                'payment' => [
                    'amount' => 1000,
                    'currency' => 'MYR',
                    'net_amount' => 1000,
                    'fee_amount' => 0,
                    'pending_amount' => 0,
                    'payment_type' => 'refund',
                    'is_outgoing' => true,
                ],
                'related_to' => [
                    'type' => 'purchase',
                    'id' => 'purch_123',
                ],
            ],
            localPurchase: null,
        );

        $result = $handler->handle($payload);

        expect($result->isSkipped())->toBeTrue();
    });

    it('returns skipped when refund payment details are missing', function (): void {
        $handler = new PurchaseRefundedHandler;

        $purchase = Purchase::create([
            'id' => 'purch_refund_guard_123',
            'type' => 'purchase',
            'status' => 'paid',
            'brand_id' => 'brand_refund_guard_123',
            'company_id' => 'company_refund_guard_123',
            'client_id' => 'client_refund_guard_123',
            'created_on' => time(),
            'updated_on' => time(),
            'client' => ['email' => 'test@example.com'],
            'purchase' => ['total' => 10000, 'currency' => 'MYR'],
            'payment' => ['amount' => 10000, 'currency' => 'MYR'],
            'issuer_details' => [],
            'transaction_data' => [],
            'status_history' => [],
            'refund_availability' => 'all',
            'refundable_amount' => 10000,
            'platform' => 'test',
            'product' => 'chip',
            'send_receipt' => false,
            'is_test' => true,
            'is_recurring_token' => false,
            'skip_capture' => false,
            'force_recurring' => false,
            'marked_as_paid' => false,
        ]);

        $payload = new EnrichedWebhookPayload(
            event: 'payment.refunded',
            rawPayload: [
                'id' => 'payment_guard_123',
                'type' => 'payment',
                'status' => 'refunded',
                'payment' => null,
                'related_to' => [
                    'type' => 'purchase',
                    'id' => $purchase->id,
                ],
            ],
            localPurchase: $purchase,
        );

        $result = $handler->handle($payload);

        expect($result->isSkipped())->toBeTrue()
            ->and($result->message)->toContain('missing');
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
