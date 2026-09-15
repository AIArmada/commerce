<?php

declare(strict_types=1);

use AIArmada\Chip\Data\PaymentData;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Facades\Chip;
use AIArmada\Chip\Gateways\ChipGateway;
use AIArmada\Chip\Gateways\ChipPaymentIntent;
use AIArmada\Chip\Gateways\ChipWebhookHandler;
use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\Chip\Services\WebhookService;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

describe('Chip Facade', function (): void {
    it('returns webhook URL', function (): void {
        Config::set('chip.webhooks.route', '/chip/webhooks');

        $url = Chip::webhookUrl();

        expect($url)->toContain('/chip/webhooks');
    });

    it('uses custom webhook route from config', function (): void {
        Config::set('chip.webhooks.route', '/custom/chip/hook');

        $url = Chip::webhookUrl();

        expect($url)->toContain('/custom/chip/hook');
    });

    it('returns facade accessor', function (): void {
        $class = new ReflectionClass(Chip::class);
        $method = $class->getMethod('getFacadeAccessor');

        $accessor = $method->invoke(null);

        expect($accessor)->toBe(ChipCollectService::class);
    });
});

describe('ChipWebhookHandler', function (): void {
    beforeEach(function (): void {
        $this->webhookService = Mockery::mock(WebhookService::class);
        $this->collectService = Mockery::mock(ChipCollectService::class);
        $this->handler = new ChipWebhookHandler($this->webhookService, $this->collectService);
    });

    afterEach(function (): void {
        Mockery::close();
    });

    describe('getEventType', function (): void {
        it('returns the documented purchase.paid event type', function (): void {
            $request = Request::create('/webhook', 'POST', [], [], [], [], json_encode([
                'id' => 'purchase-123',
                'event_type' => 'purchase.paid',
                'status' => 'paid',
            ]));

            expect($this->handler->getEventType($request))->toBe('purchase.paid');
        });

        it('returns the documented payment.refunded event type', function (): void {
            $request = Request::create('/webhook', 'POST', [], [], [], [], json_encode([
                'event_type' => 'payment.refunded',
                'status' => 'refunded',
            ]));

            expect($this->handler->getEventType($request))->toBe('payment.refunded');
        });

        it('returns the documented purchase.cancelled event type', function (): void {
            $request = Request::create('/webhook', 'POST', [], [], [], [], json_encode([
                'event_type' => 'purchase.cancelled',
                'status' => 'cancelled',
            ]));

            expect($this->handler->getEventType($request))->toBe('purchase.cancelled');
        });

        it('returns the documented purchase.payment_failure event type', function (): void {
            $request = Request::create('/webhook', 'POST', [], [], [], [], json_encode([
                'event_type' => 'purchase.payment_failure',
                'status' => 'error',
            ]));

            expect($this->handler->getEventType($request))->toBe('purchase.payment_failure');
        });

        it('does not derive an event type from a blocked status', function (): void {
            $request = Request::create('/webhook', 'POST', [], [], [], [], json_encode([
                'event_type' => 'purchase.payment_failure',
                'status' => 'blocked',
            ]));

            expect($this->handler->getEventType($request))->toBe('purchase.payment_failure');
        });

        it('returns the documented purchase.hold event type', function (): void {
            $request = Request::create('/webhook', 'POST', [], [], [], [], json_encode([
                'event_type' => 'purchase.hold',
                'status' => 'hold',
            ]));

            expect($this->handler->getEventType($request))->toBe('purchase.hold');
        });

        it('returns the documented purchase.preauthorized event type', function (): void {
            $request = Request::create('/webhook', 'POST', [], [], [], [], json_encode([
                'event_type' => 'purchase.preauthorized',
                'status' => 'preauthorized',
            ]));

            expect($this->handler->getEventType($request))->toBe('purchase.preauthorized');
        });

        it('returns the documented purchase.pending_execute event type', function (): void {
            $request = Request::create('/webhook', 'POST', [], [], [], [], json_encode([
                'event_type' => 'purchase.pending_execute',
                'status' => 'pending_execute',
            ]));

            expect($this->handler->getEventType($request))->toBe('purchase.pending_execute');
        });

        it('returns the documented purchase.pending_refund event type', function (): void {
            $request = Request::create('/webhook', 'POST', [], [], [], [], json_encode([
                'event_type' => 'purchase.pending_refund',
                'status' => 'pending_refund',
            ]));

            expect($this->handler->getEventType($request))->toBe('purchase.pending_refund');
        });
    });

    describe('isPaymentEvent', function (): void {
        it('always returns true', function (): void {
            $request = Request::create('/webhook', 'POST', [], [], [], [], json_encode([
                'event_type' => 'purchase.paid',
            ]));

            expect($this->handler->isPaymentEvent($request))->toBeTrue();
        });
    });

    describe('verifyWebhook', function (): void {
        it('returns false when signature is invalid', function (): void {
            $request = Request::create('/webhook', 'POST');

            $this->webhookService->shouldReceive('verifySignature')
                ->once()
                ->with($request)
                ->andReturn(false);

            expect($this->handler->verifyWebhook($request))->toBeFalse();
        });
    });

    describe('parseWebhook', function (): void {
        it('parses webhook payload correctly', function (): void {
            $request = Request::create('/webhook', 'POST', [], [], [], [], json_encode([
                'id' => 'purchase-123',
                'event_type' => 'purchase.paid',
                'type' => 'purchase',
                'status' => 'paid',
                'reference' => 'REF-123',
                'updated_on' => time(),
            ]));

            $this->webhookService->shouldReceive('parsePayload')
                ->once()
                ->andReturn((object) [
                    'id' => 'purchase-123',
                    'event_type' => 'purchase.paid',
                    'type' => 'purchase',
                    'status' => 'paid',
                    'reference' => 'REF-123',
                    'updated_on' => time(),
                ]);

            $result = $this->handler->parseWebhook($request);

            expect($result->paymentId)->toBe('purchase-123');
            expect($result->reference)->toBe('REF-123');
            expect($result->gatewayName)->toBe('chip');
            expect($result->status)->toBe(PaymentStatus::PAID);
        });
    });
});

describe('ChipPaymentIntent', function (): void {
    /**
     * Create a minimal PurchaseData for testing
     */
    function createTestPurchaseData(array $overrides = []): PurchaseData
    {
        return PurchaseData::from(array_merge([
            'id' => 'purchase-' . uniqid(),
            'type' => 'purchase',
            'status' => 'paid',
            'brand_id' => 'brand-123',
            'is_test' => true,
            'created_on' => time(),
            'updated_on' => time(),
            'client' => ['email' => 'test@example.com'],
            'purchase' => ['total' => 10000, 'currency' => 'MYR'],
        ], $overrides));
    }

    it('can be instantiated from PurchaseData', function (): void {
        $purchase = createTestPurchaseData();
        $intent = new ChipPaymentIntent($purchase);

        expect($intent)->toBeInstanceOf(ChipPaymentIntent::class);
    });

    it('returns metadata', function (): void {
        $purchase = createTestPurchaseData([
            'purchase' => ['total' => 10000, 'currency' => 'MYR', 'metadata' => ['order_id' => 123]],
        ]);
        $intent = new ChipPaymentIntent($purchase);

        expect($intent->getMetadata())->toBe(['order_id' => 123]);
    });

    it('checks if paid', function (): void {
        $purchase = createTestPurchaseData(['status' => 'paid']);
        $intent = new ChipPaymentIntent($purchase);

        expect($intent->isPaid())->toBeTrue();
    });

    it('checks if not paid', function (): void {
        $purchase = createTestPurchaseData(['status' => 'pending_execute']);
        $intent = new ChipPaymentIntent($purchase);

        expect($intent->isPaid())->toBeFalse();
    });

    it('checks if failed', function (): void {
        $purchase = createTestPurchaseData(['status' => 'error']);
        $intent = new ChipPaymentIntent($purchase);

        expect($intent->isFailed())->toBeTrue();
    });
});

describe('ChipGateway', function (): void {
    beforeEach(function (): void {
        $this->collectService = Mockery::mock(ChipCollectService::class);
        $this->webhookService = Mockery::mock(WebhookService::class);
        $this->gateway = new ChipGateway($this->collectService, $this->webhookService);
    });

    it('reloads the related purchase when refund returns a payment payload', function (): void {
        $refund = PaymentData::from([
            'type' => 'payment',
            'id' => 'payment-refund-123',
            'created_on' => time(),
            'updated_on' => time(),
            'related_to' => [
                'type' => 'purchase',
                'id' => 'purchase-123',
            ],
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
        $purchase = createTestPurchaseData(['id' => 'purchase-123', 'status' => 'refunded']);

        $this->collectService->shouldReceive('refundPurchase')
            ->once()
            ->with('purchase-123', null)
            ->andReturn($refund);

        $this->collectService->shouldReceive('getPurchase')
            ->once()
            ->with('purchase-123')
            ->andReturn($purchase);

        $intent = $this->gateway->refundPayment('purchase-123');

        expect($intent)->toBeInstanceOf(ChipPaymentIntent::class);
        expect($intent->getPaymentId())->toBe('purchase-123');
        expect($intent->getStatus())->toBe(PaymentStatus::REFUNDED);
    });
});
