<?php

declare(strict_types=1);

use AIArmada\Chip\Data\EnrichedWebhookPayload;
use AIArmada\Chip\Data\WebhookResult;
use AIArmada\Chip\Events\PaymentRefunded;
use AIArmada\Chip\Events\PurchaseCancelled;
use AIArmada\Chip\Events\PurchasePaid;
use AIArmada\Chip\Events\PurchasePaymentFailure;
use AIArmada\Chip\Models\Payment;
use AIArmada\Chip\Models\Purchase;
use AIArmada\Chip\Webhooks\Handlers\PaymentFailedHandler;
use AIArmada\Chip\Webhooks\Handlers\PurchaseCancelledHandler;
use AIArmada\Chip\Webhooks\Handlers\PurchasePaidHandler;
use AIArmada\Chip\Webhooks\Handlers\PurchaseRefundedHandler;
use Illuminate\Support\Facades\Event;

describe('Webhook Handlers Integration', function (): void {
    describe('Handler behavior with local purchase', function (): void {
        /**
         * Create a minimal test purchase with required fields
         *
         * @param  array<string, mixed>  $overrides
         */
        function createTestPurchase(array $overrides = []): Purchase
        {
            return tap(new Purchase, fn (Purchase $p) => $p->forceFill(array_merge([
                'id' => 'purchase-' . uniqid(),
                'type' => 'purchase',
                'status' => 'created',
                'brand_id' => 'brand-' . uniqid(),
                'created_on' => time(),
                'updated_on' => time(),
                'client' => ['email' => 'test@example.com'],
                'purchase' => ['total' => 10000, 'currency' => 'MYR'],
                'payment' => null,
                'issuer_details' => [],
                'transaction_data' => [],
                'status_history' => [],
                'refund_availability' => 'none',
                'refundable_amount' => 0,
                'platform' => 'test',
                'product' => 'chip',
                'send_receipt' => false,
                'is_test' => true,
                'is_recurring_token' => false,
                'skip_capture' => false,
                'force_recurring' => false,
                'marked_as_paid' => false,
            ], $overrides))->save());
        }

        /**
         * Create enriched payload with a local purchase
         *
         * @param  array<string, mixed>  $rawPayload
         */
        function createPayloadWithPurchase(
            string $event,
            Purchase $purchase,
            array $rawPayload = []
        ): EnrichedWebhookPayload {
            $payload = $event === 'payment.refunded'
                ? array_merge([
                    'id' => 'payment-' . uniqid(),
                    'type' => 'payment',
                    'status' => $rawPayload['status'] ?? 'refunded',
                    'is_test' => true,
                    'client_id' => 'client-123',
                    'brand_id' => $purchase->brand_id,
                    'created_on' => time(),
                    'updated_on' => time(),
                    'client' => ['email' => 'test@example.com'],
                    'payment' => [
                        'amount' => 10000,
                        'currency' => 'MYR',
                        'net_amount' => 10000,
                        'fee_amount' => 0,
                        'pending_amount' => 0,
                        'payment_type' => 'refund',
                        'is_outgoing' => true,
                    ],
                    'related_to' => ['type' => 'purchase', 'id' => $purchase->id],
                ], $rawPayload)
                : array_merge([
                    'id' => $purchase->id,
                    'type' => 'purchase',
                    'status' => $rawPayload['status'] ?? 'paid',
                    'is_test' => true,
                    'client_id' => 'client-123',
                    'brand_id' => $purchase->brand_id,
                    'created_on' => time(),
                    'updated_on' => time(),
                    'client' => ['email' => 'test@example.com'],
                    'purchase' => ['total' => 10000, 'currency' => 'MYR'],
                ], $rawPayload);

            return new EnrichedWebhookPayload(
                event: $event,
                rawPayload: $payload,
                localPurchase: $purchase,
                owner: null,
                receivedAt: now(),
                purchaseId: data_get($payload, 'related_to.id') ?? $purchase->id,
                clientId: 'client-123',
            );
        }

        it('PurchasePaidHandler updates purchase status to paid', function (): void {
            Event::fake();

            $purchase = createTestPurchase(['status' => 'created']);
            $payload = createPayloadWithPurchase('purchase.paid', $purchase, ['status' => 'paid']);

            $handler = app(PurchasePaidHandler::class);
            $result = $handler->handle($payload);

            expect($result)->toBeInstanceOf(WebhookResult::class);
            expect($result->isHandled())->toBeTrue();

            $purchase->refresh();
            expect($purchase->status->value ?? $purchase->status)->toBe('paid');

            Event::assertDispatched(PurchasePaid::class);
        });

        it('PurchaseCancelledHandler updates purchase status to cancelled', function (): void {
            Event::fake();

            $purchase = createTestPurchase(['status' => 'created']);
            $payload = createPayloadWithPurchase('purchase.cancelled', $purchase, ['status' => 'cancelled']);

            $handler = app(PurchaseCancelledHandler::class);
            $result = $handler->handle($payload);

            expect($result)->toBeInstanceOf(WebhookResult::class);
            expect($result->isHandled())->toBeTrue();

            $purchase->refresh();
            expect($purchase->status->value ?? $purchase->status)->toBe('cancelled');

            Event::assertDispatched(PurchaseCancelled::class);
        });

        it('PaymentFailedHandler updates purchase status', function (): void {
            Event::fake();

            $purchase = createTestPurchase(['status' => 'pending_execute']);
            $payload = createPayloadWithPurchase('purchase.payment_failure', $purchase, ['status' => 'error']);

            $handler = app(PaymentFailedHandler::class);
            $result = $handler->handle($payload);

            expect($result)->toBeInstanceOf(WebhookResult::class);
            expect($result->isHandled())->toBeTrue();

            Event::assertDispatched(PurchasePaymentFailure::class);
        });

        it('PurchaseRefundedHandler updates purchase', function (): void {
            Event::fake();

            $purchase = createTestPurchase(['status' => 'paid']);
            $payload = createPayloadWithPurchase('payment.refunded', $purchase, [
                'status' => 'refunded',
                'payment' => [
                    'amount' => 3000,
                    'currency' => 'MYR',
                    'net_amount' => 3000,
                    'fee_amount' => 0,
                    'pending_amount' => 0,
                    'payment_type' => 'refund',
                    'is_outgoing' => true,
                ],
            ]);

            $handler = app(PurchaseRefundedHandler::class);
            $result = $handler->handle($payload);

            expect($result)->toBeInstanceOf(WebhookResult::class);
            expect($result->isHandled())->toBeTrue();

            $purchase->refresh();
            expect($purchase->status)->toBe('refunded')
                ->and($purchase->refund_amount_minor)->toBe(3000)
                ->and($purchase->refundable_amount)->toBe(7000)
                ->and($purchase->refunded_at)->not->toBeNull();

            Event::assertDispatched(PaymentRefunded::class);
        });

        it('PurchaseRefundedHandler accumulates persisted refund payments before marking full refund', function (): void {
            Event::fake();

            $purchase = createTestPurchase([
                'status' => 'paid',
                'refund_amount_minor' => 5000,
                'refundable_amount' => 5000,
            ]);

            tap(new Payment, fn (Payment $pmt) => $pmt->forceFill([
                'id' => 'refund-payment-existing',
                'purchase_id' => $purchase->id,
                'payment_type' => 'refund',
                'is_outgoing' => true,
                'amount' => 5000,
                'currency' => 'MYR',
                'net_amount' => 5000,
                'fee_amount' => 0,
                'pending_amount' => 0,
                'created_on' => time() - 60,
                'updated_on' => time() - 60,
            ])->save());

            $payload = createPayloadWithPurchase('payment.refunded', $purchase, [
                'id' => 'refund-payment-final',
                'status' => 'refunded',
                'payment' => [
                    'amount' => 5000,
                    'currency' => 'MYR',
                    'net_amount' => 5000,
                    'fee_amount' => 0,
                    'pending_amount' => 0,
                    'payment_type' => 'refund',
                    'is_outgoing' => true,
                ],
            ]);

            $handler = app(PurchaseRefundedHandler::class);
            $result = $handler->handle($payload);

            expect($result)->toBeInstanceOf(WebhookResult::class)
                ->and($result->isHandled())->toBeTrue();

            $purchase->refresh();
            expect($purchase->status)->toBe('refunded')
                ->and($purchase->refund_amount_minor)->toBe(10000)
                ->and($purchase->refundable_amount)->toBe(0)
                ->and($purchase->refunded_at)->not->toBeNull();
        });

    });
});
