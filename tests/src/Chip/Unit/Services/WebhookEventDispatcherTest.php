<?php

declare(strict_types=1);

use AIArmada\Chip\Data\PaymentData;
use AIArmada\Chip\Data\PayoutData;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Events\PaymentRefunded;
use AIArmada\Chip\Events\PurchaseCreated;
use AIArmada\Chip\Events\PurchasePaid;
use AIArmada\Chip\Services\WebhookEventDispatcher;
use AIArmada\CommerceSupport\Events\PaymentRefunded as CommercePaymentRefunded;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    Event::fake();
});

describe('WebhookEventDispatcher structure', function (): void {
    it('can be instantiated', function (): void {
        $dispatcher = new WebhookEventDispatcher;
        expect($dispatcher)->toBeInstanceOf(WebhookEventDispatcher::class);
    });

    it('is registered as singleton in container', function (): void {
        $dispatcher1 = app(WebhookEventDispatcher::class);
        $dispatcher2 = app(WebhookEventDispatcher::class);
        expect($dispatcher1)->toBe($dispatcher2);
    });
});

describe('WebhookEventDispatcher::extractPurchase', function (): void {
    it('extracts PurchaseData for purchase type', function (): void {
        $dispatcher = new WebhookEventDispatcher;
        $payload = createMinimalPurchasePayload();
        $payload['type'] = 'purchase';

        $result = $dispatcher->extractPurchase($payload);

        expect($result)->toBeInstanceOf(PurchaseData::class);
    });

    it('extracts PurchaseData for purchase.* events', function (): void {
        $dispatcher = new WebhookEventDispatcher;
        $payload = createMinimalPurchasePayload();
        $payload['event_type'] = 'purchase.paid';

        $result = $dispatcher->extractPurchase($payload);

        expect($result)->toBeInstanceOf(PurchaseData::class);
    });

    it('returns null for payment.* events', function (): void {
        $dispatcher = new WebhookEventDispatcher;
        $payload = createMinimalPaymentPayload();

        $result = $dispatcher->extractPurchase($payload);

        expect($result)->toBeNull();
    });

    it('returns null for non-purchase payloads', function (): void {
        $dispatcher = new WebhookEventDispatcher;
        $payload = ['type' => 'payout', 'event_type' => 'payout.success'];

        $result = $dispatcher->extractPurchase($payload);

        expect($result)->toBeNull();
    });
});

describe('WebhookEventDispatcher::extractPayout', function (): void {
    it('extracts PayoutData for payout type', function (): void {
        $dispatcher = new WebhookEventDispatcher;
        $payload = createMinimalPayoutPayload();
        $payload['type'] = 'payout';

        $result = $dispatcher->extractPayout($payload);

        expect($result)->toBeInstanceOf(PayoutData::class);
    });

    it('extracts PayoutData for payout.* events', function (): void {
        $dispatcher = new WebhookEventDispatcher;
        $payload = createMinimalPayoutPayload();
        $payload['event_type'] = 'payout.success';

        $result = $dispatcher->extractPayout($payload);

        expect($result)->toBeInstanceOf(PayoutData::class);
    });

    it('returns null for non-payout payloads', function (): void {
        $dispatcher = new WebhookEventDispatcher;
        $payload = ['type' => 'purchase', 'event_type' => 'purchase.paid'];

        $result = $dispatcher->extractPayout($payload);

        expect($result)->toBeNull();
    });
});

describe('WebhookEventDispatcher::extractPayment', function (): void {
    it('extracts PaymentData for payment type', function (): void {
        $dispatcher = new WebhookEventDispatcher;
        $payload = createMinimalPaymentPayload();

        $result = $dispatcher->extractPayment($payload);

        expect($result)->toBeInstanceOf(PaymentData::class);
    });

    it('extracts PaymentData for payment.* events', function (): void {
        $dispatcher = new WebhookEventDispatcher;
        $payload = createMinimalPaymentPayload();

        $result = $dispatcher->extractPayment($payload);

        expect($result)->toBeInstanceOf(PaymentData::class);
    });

    it('returns null for non-payment payloads', function (): void {
        $dispatcher = new WebhookEventDispatcher;
        $payload = ['type' => 'purchase', 'event_type' => 'purchase.paid'];

        $result = $dispatcher->extractPayment($payload);

        expect($result)->toBeNull();
    });

    it('returns null for malformed payment payloads without amount and currency', function (): void {
        $dispatcher = new WebhookEventDispatcher;
        $payload = [
            'type' => 'payment',
            'event_type' => 'payment.refunded',
            'id' => 'payment-invalid-123',
            'related_to' => [
                'type' => 'purchase',
                'id' => 'purchase-invalid-123',
            ],
        ];

        $result = $dispatcher->extractPayment($payload);

        expect($result)->toBeNull();
    });
});

describe('WebhookEventDispatcher::dispatch', function (): void {
    it('dispatches PurchaseCreated event for purchase.created', function (): void {
        $dispatcher = new WebhookEventDispatcher;
        $payload = createMinimalPurchasePayload();
        $payload['event_type'] = 'purchase.created';
        $payload['type'] = 'purchase';

        $dispatcher->dispatch('purchase.created', $payload);

        Event::assertDispatched(PurchaseCreated::class);
    });

    it('dispatches PurchasePaid event for purchase.paid', function (): void {
        $dispatcher = new WebhookEventDispatcher;
        $payload = createMinimalPurchasePayload();
        $payload['event_type'] = 'purchase.paid';
        $payload['type'] = 'purchase';

        $dispatcher->dispatch('purchase.paid', $payload);

        Event::assertDispatched(PurchasePaid::class);
    });

    it('dispatches PaymentRefunded event for payment.refunded', function (): void {
        $dispatcher = new WebhookEventDispatcher;
        $payload = createMinimalPaymentPayload();

        $dispatcher->dispatch('payment.refunded', $payload);

        Event::assertDispatched(PaymentRefunded::class, fn (PaymentRefunded $event): bool => $event->getPurchaseId() === 'purchase-123');
        Event::assertDispatched(CommercePaymentRefunded::class, fn (CommercePaymentRefunded $event): bool => $event->provider === 'chip'
            && $event->paymentId === 'payment-123'
            && $event->relatedPaymentId === 'purchase-123'
            && $event->amount === 1000
            && $event->currency === 'MYR');
    });

    it('does not invent application metadata for a provider payload', function (): void {
        $dispatcher = new WebhookEventDispatcher;
        $payload = createMinimalPaymentPayload();
        $payload['metadata'] = [];
        $payload['payment']['metadata'] = [
            'order_id' => 'order-123',
            'refund_id' => 'refund-123',
        ];

        $dispatcher->dispatch('payment.refunded', $payload);

        Event::assertDispatched(CommercePaymentRefunded::class, fn (CommercePaymentRefunded $event): bool => $event->metadata === []);
    });

    it('skips malformed payment.refunded payloads that have no payment details', function (): void {
        $dispatcher = new WebhookEventDispatcher;
        $payload = createMinimalPaymentPayload();
        unset($payload['payment']);

        $dispatcher->dispatch('payment.refunded', $payload);

        Event::assertNotDispatched(PaymentRefunded::class);
    });

    it('logs warning for unknown event type', function (): void {
        $dispatcher = new WebhookEventDispatcher;
        $payload = ['event_type' => 'unknown.event'];

        // Should not throw, just log warning
        $dispatcher->dispatch('unknown.event', $payload);

        // Event should not be dispatched
        Event::assertNotDispatched(PurchaseCreated::class);
        Event::assertNotDispatched(PurchasePaid::class);
    });
});

/**
 * Helper to create minimal valid purchase payload.
 *
 * @return array<string, mixed>
 */
function createMinimalPurchasePayload(): array
{
    return [
        'id' => '550e8400-e29b-41d4-a716-446655440000',
        'type' => 'purchase',
        'event_type' => 'purchase.paid',
        'created_on' => time(),
        'updated_on' => time(),
        'client' => [
            'email' => 'test@example.com',
        ],
        'purchase' => [
            'currency' => 'MYR',
            'products' => [
                ['name' => 'Test', 'price' => 1000, 'quantity' => '1'],
            ],
            'total' => 1000,
        ],
        'brand_id' => '550e8400-e29b-41d4-a716-446655440001',
        'payment' => null,
        'issuer_details' => [],
        'transaction_data' => [],
        'status' => 'created',
        'status_history' => [],
        'viewed_on' => null,
        'company_id' => null,
        'is_test' => true,
        'user_id' => null,
        'billing_template_id' => null,
        'client_id' => null,
        'send_receipt' => false,
        'is_recurring_token' => false,
        'recurring_token' => null,
        'skip_capture' => false,
        'force_recurring' => false,
        'reference_generated' => 'REF-001',
        'reference' => null,
        'notes' => null,
        'issued' => null,
        'due' => null,
        'refund_availability' => 'all',
        'refundable_amount' => 0,
        'currency_conversion' => null,
        'payment_method_whitelist' => [],
        'success_redirect' => null,
        'failure_redirect' => null,
        'cancel_redirect' => null,
        'success_callback' => null,
        'creator_agent' => 'test',
        'platform' => 'api',
        'product' => 'purchases',
        'created_from_ip' => null,
        'invoice_url' => null,
        'checkout_url' => 'https://example.com/checkout',
        'direct_post_url' => null,
        'marked_as_paid' => false,
        'order_id' => null,
        'upsell_campaigns' => [],
        'referral_campaign_id' => null,
        'referral_code' => null,
        'referral_code_details' => null,
        'referral_code_generated' => null,
        'retain_level_details' => null,
        'can_retrieve' => false,
        'can_chargeback' => false,
    ];
}

/**
 * Helper to create minimal valid payment payload.
 *
 * @return array<string, mixed>
 */
function createMinimalPaymentPayload(): array
{
    return [
        'id' => 'payment-123',
        'type' => 'payment',
        'event_type' => 'payment.refunded',
        'created_on' => time(),
        'updated_on' => time(),
        'client' => [
            'email' => 'test@example.com',
        ],
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
            'id' => 'purchase-123',
        ],
        'transaction_data' => [],
        'brand_id' => '550e8400-e29b-41d4-a716-446655440001',
        'company_id' => null,
        'reference' => 'REF-REFUND-123',
        'is_test' => true,
    ];
}

/**
 * Helper to create minimal valid payout payload.
 *
 * @return array<string, mixed>
 */
function createMinimalPayoutPayload(): array
{
    return [
        'id' => '550e8400-e29b-41d4-a716-446655440002',
        'type' => 'payout',
        'event_type' => 'payout.pending',
        'status' => 'pending',
        'payment' => [
            'amount' => 1000,
            'currency' => 'MYR',
            'net_amount' => 1000,
            'fee_amount' => 0,
            'pending_amount' => 0,
            'payment_type' => 'payout',
            'is_outgoing' => true,
        ],
        'client' => ['email' => 'recipient@example.com'],
        'brand_id' => 'brand_123',
        'transaction_data' => ['attempts' => []],
        'created_on' => time(),
        'updated_on' => time(),
    ];
}
