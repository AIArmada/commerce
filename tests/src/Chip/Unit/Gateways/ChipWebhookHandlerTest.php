<?php

declare(strict_types=1);

use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Gateways\ChipPaymentIntent;
use AIArmada\Chip\Gateways\ChipWebhookHandler;
use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\Chip\Services\WebhookService;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentStatus;
use AIArmada\CommerceSupport\Contracts\Payment\WebhookHandlerInterface;
use AIArmada\CommerceSupport\Exceptions\WebhookVerificationException;
use Illuminate\Http\Request;

function createWebhookRequest(array $payload): Request
{
    $request = Request::create('/webhook', 'POST', [], [], [], [], json_encode($payload));
    $request->headers->set('Content-Type', 'application/json');

    return $request;
}

describe('ChipWebhookHandler instantiation', function (): void {
    it('can be instantiated with dependencies', function (): void {
        $webhookService = Mockery::mock(WebhookService::class);
        $collectService = Mockery::mock(ChipCollectService::class);

        $handler = new ChipWebhookHandler($webhookService, $collectService);

        expect($handler)->toBeInstanceOf(ChipWebhookHandler::class);
    });

    it('implements WebhookHandlerInterface', function (): void {
        $webhookService = Mockery::mock(WebhookService::class);
        $collectService = Mockery::mock(ChipCollectService::class);

        $handler = new ChipWebhookHandler($webhookService, $collectService);

        expect($handler)->toBeInstanceOf(WebhookHandlerInterface::class);
    });
});

describe('ChipWebhookHandler verifyWebhook', function (): void {
    it('returns true when signature is valid', function (): void {
        $webhookService = Mockery::mock(WebhookService::class);
        $webhookService->shouldReceive('verifySignature')
            ->once()
            ->andReturn(true);

        $collectService = Mockery::mock(ChipCollectService::class);

        $handler = new ChipWebhookHandler($webhookService, $collectService);
        $request = createWebhookRequest(['status' => 'paid']);

        $result = $handler->verifyWebhook($request);

        expect($result)->toBeTrue();
    });

    it('returns false when signature is invalid', function (): void {
        $webhookService = Mockery::mock(WebhookService::class);
        $webhookService->shouldReceive('verifySignature')
            ->once()
            ->andReturn(false);

        $collectService = Mockery::mock(ChipCollectService::class);

        $handler = new ChipWebhookHandler($webhookService, $collectService);
        $request = createWebhookRequest(['status' => 'paid']);

        $result = $handler->verifyWebhook($request);

        expect($result)->toBeFalse();
    });

    it('throws WebhookVerificationException when verification fails', function (): void {
        $webhookService = Mockery::mock(WebhookService::class);
        $webhookService->shouldReceive('verifySignature')
            ->once()
            ->andThrow(new AIArmada\Chip\Exceptions\WebhookVerificationException('Invalid signature'));

        $collectService = Mockery::mock(ChipCollectService::class);

        $handler = new ChipWebhookHandler($webhookService, $collectService);
        $request = createWebhookRequest(['status' => 'paid']);

        expect(fn () => $handler->verifyWebhook($request))
            ->toThrow(WebhookVerificationException::class);
    });
});

describe('ChipWebhookHandler getEventType', function (): void {
    it('returns the documented event_type without deriving it from status', function (): void {
        $handler = new ChipWebhookHandler(
            Mockery::mock(WebhookService::class),
            Mockery::mock(ChipCollectService::class),
        );

        expect($handler->getEventType(createWebhookRequest([
            'event_type' => 'purchase.pending_capture',
            'status' => 'pending_capture',
        ])))->toBe('purchase.pending_capture');

        expect($handler->getEventType(createWebhookRequest([
            'event_type' => 'payment.refunded',
            'status' => 'refunded',
        ])))->toBe('payment.refunded');
    });

    it('returns unknown for invalid JSON', function (): void {
        $webhookService = Mockery::mock(WebhookService::class);
        $collectService = Mockery::mock(ChipCollectService::class);

        $handler = new ChipWebhookHandler($webhookService, $collectService);
        $request = Request::create('/webhook', 'POST', [], [], [], [], 'invalid json');

        $result = $handler->getEventType($request);

        expect($result)->toBe('unknown');
    });

    it('returns unknown when the event_type is missing', function (): void {
        $webhookService = Mockery::mock(WebhookService::class);
        $collectService = Mockery::mock(ChipCollectService::class);

        $handler = new ChipWebhookHandler($webhookService, $collectService);
        $request = createWebhookRequest(['id' => 'test', 'status' => 'paid']);

        $result = $handler->getEventType($request);

        expect($result)->toBe('unknown');
    });
});

describe('ChipWebhookHandler parseWebhook status mapping', function (): void {
    it('maps captured status to paid', function (): void {
        $payload = [
            'id' => 'purchase-captured-123',
            'event_type' => 'purchase.captured',
            'status' => 'paid',
            'updated_on' => 1702819200,
        ];

        $webhookService = Mockery::mock(WebhookService::class);
        $webhookService->shouldReceive('parsePayload')
            ->once()
            ->andReturn((object) $payload);

        $collectService = Mockery::mock(ChipCollectService::class);
        $handler = new ChipWebhookHandler($webhookService, $collectService);
        $result = $handler->parseWebhook(createWebhookRequest($payload));

        expect($result->eventType)->toBe('purchase.captured')
            ->and($result->status)->toBe(PaymentStatus::PAID);
    });

    it('maps released status to cancelled', function (): void {
        $payload = [
            'id' => 'purchase-released-123',
            'event_type' => 'purchase.released',
            'status' => 'released',
            'updated_on' => 1702819200,
        ];

        $webhookService = Mockery::mock(WebhookService::class);
        $webhookService->shouldReceive('parsePayload')
            ->once()
            ->andReturn((object) $payload);

        $collectService = Mockery::mock(ChipCollectService::class);
        $handler = new ChipWebhookHandler($webhookService, $collectService);
        $result = $handler->parseWebhook(createWebhookRequest($payload));

        expect($result->eventType)->toBe('purchase.released')
            ->and($result->status)->toBe(PaymentStatus::CANCELLED);
    });

    it('maps settled status to paid', function (): void {
        $payload = [
            'id' => 'purchase-settled-123',
            'event_type' => 'purchase.settled',
            'status' => 'settled',
            'updated_on' => 1702819200,
        ];

        $webhookService = Mockery::mock(WebhookService::class);
        $webhookService->shouldReceive('parsePayload')
            ->once()
            ->andReturn((object) $payload);

        $collectService = Mockery::mock(ChipCollectService::class);
        $handler = new ChipWebhookHandler($webhookService, $collectService);
        $result = $handler->parseWebhook(createWebhookRequest($payload));

        expect($result->eventType)->toBe('purchase.settled')
            ->and($result->status)->toBe(PaymentStatus::PAID);
    });

    it('maps chargeback status to disputed', function (): void {
        $payload = [
            'id' => 'purchase-chargeback-123',
            'event_type' => 'payment.charged_back',
            'status' => 'chargeback',
            'updated_on' => 1702819200,
        ];

        $webhookService = Mockery::mock(WebhookService::class);
        $webhookService->shouldReceive('parsePayload')
            ->once()
            ->andReturn((object) $payload);

        $collectService = Mockery::mock(ChipCollectService::class);
        $handler = new ChipWebhookHandler($webhookService, $collectService);
        $result = $handler->parseWebhook(createWebhookRequest($payload));

        expect($result->eventType)->toBe('payment.charged_back')
            ->and($result->status)->toBe(PaymentStatus::DISPUTED);
    });
});

describe('ChipWebhookHandler isPaymentEvent', function (): void {
    it('recognizes documented purchase and payment webhook events', function (): void {
        $webhookService = Mockery::mock(WebhookService::class);
        $collectService = Mockery::mock(ChipCollectService::class);

        $handler = new ChipWebhookHandler($webhookService, $collectService);
        $request = createWebhookRequest(['event_type' => 'purchase.paid', 'status' => 'paid']);

        $result = $handler->isPaymentEvent($request);

        expect($result)->toBeTrue();
    });
});

describe('ChipWebhookHandler getPaymentFromWebhook', function (): void {
    it('returns null for invalid JSON', function (): void {
        $webhookService = Mockery::mock(WebhookService::class);
        $collectService = Mockery::mock(ChipCollectService::class);

        $handler = new ChipWebhookHandler($webhookService, $collectService);
        $request = Request::create('/webhook', 'POST', [], [], [], [], 'invalid');

        $result = $handler->getPaymentFromWebhook($request);

        expect($result)->toBeNull();
    });

    it('returns null for payload without id', function (): void {
        $webhookService = Mockery::mock(WebhookService::class);
        $collectService = Mockery::mock(ChipCollectService::class);

        $handler = new ChipWebhookHandler($webhookService, $collectService);
        $request = createWebhookRequest(['status' => 'paid']);

        $result = $handler->getPaymentFromWebhook($request);

        expect($result)->toBeNull();
    });

    it('returns ChipPaymentIntent from valid payload', function (): void {
        $payload = [
            'id' => 'purchase-123',
            'type' => 'purchase',
            'event_type' => 'purchase.paid',
            'status' => 'paid',
            'is_test' => true,
            'client_id' => 'client-456',
            'brand_id' => 'brand-789',
            'created_on' => 1702819200,
            'updated_on' => 1702819200,
            'client' => ['email' => 'test@example.com'],
            'purchase' => [
                'total' => 10000,
                'currency' => 'MYR',
                'products' => [['name' => 'Test', 'price' => 10000, 'quantity' => 1]],
            ],
        ];

        $webhookService = Mockery::mock(WebhookService::class);
        $collectService = Mockery::mock(ChipCollectService::class);
        $collectService->shouldReceive('getPurchase')->never();

        $handler = new ChipWebhookHandler($webhookService, $collectService);
        $request = createWebhookRequest($payload);

        $result = $handler->getPaymentFromWebhook($request);

        expect($result)->toBeInstanceOf(ChipPaymentIntent::class);
        expect($result->getPaymentId())->toBe('purchase-123');
    });

    it('uses the related purchase id for payment-shaped refund webhooks', function (): void {
        $payload = [
            'id' => 'payment-refund-123',
            'type' => 'payment',
            'event_type' => 'payment.refunded',
            'status' => 'refunded',
            'is_test' => true,
            'updated_on' => 1702819200,
            'related_to' => [
                'type' => 'purchase',
                'id' => 'purchase-123',
            ],
            'payment' => [
                'amount' => 10000,
                'currency' => 'MYR',
                'net_amount' => 10000,
                'fee_amount' => 0,
                'pending_amount' => 0,
                'payment_type' => 'refund',
                'is_outgoing' => true,
            ],
        ];

        $webhookService = Mockery::mock(WebhookService::class);
        $webhookService->shouldReceive('parsePayload')
            ->once()
            ->andReturn((object) $payload);

        $collectService = Mockery::mock(ChipCollectService::class);
        $collectService->shouldReceive('getPurchase')
            ->once()
            ->with('purchase-123')
            ->andReturn(PurchaseData::from([
                'id' => 'purchase-123',
                'type' => 'purchase',
                'status' => 'refunded',
                'brand_id' => 'brand-789',
                'is_test' => true,
                'created_on' => 1702819200,
                'updated_on' => 1702819200,
                'client' => ['email' => 'test@example.com'],
                'purchase' => [
                    'total' => 10000,
                    'currency' => 'MYR',
                    'products' => [['name' => 'Test', 'price' => 10000, 'quantity' => 1]],
                ],
            ]));

        $handler = new ChipWebhookHandler($webhookService, $collectService);
        $request = createWebhookRequest($payload);

        $result = $handler->getPaymentFromWebhook($request);
        $parsed = $handler->parseWebhook($request);

        expect($result)->toBeInstanceOf(ChipPaymentIntent::class);
        expect($result?->getPaymentId())->toBe('purchase-123');
        expect($parsed->paymentId)->toBe('purchase-123');
    });
});
