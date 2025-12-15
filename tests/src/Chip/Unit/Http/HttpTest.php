<?php

declare(strict_types=1);

use AIArmada\Chip\Http\Controllers\WebhookController;
use AIArmada\Chip\Http\Middleware\VerifyWebhookSignature;
use AIArmada\Chip\Services\WebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

describe('WebhookController', function () {
    beforeEach(function () {
        // Disable listeners that require database
        Event::fake();
        config(['chip.webhooks.store_data' => false]);
    });

    it('can be instantiated', function () {
        $controller = new WebhookController;
        expect($controller)->toBeInstanceOf(WebhookController::class);
    });

    it('handles purchase.paid webhook', function () {
        $controller = new WebhookController;

        $payload = [
            'id' => 'purch_test123',
            'type' => 'purchase',
            'event_type' => 'purchase.paid',
            'status' => 'paid',
            'brand_id' => 'brand_123',
            'created_on' => time(),
            'updated_on' => time(),
            'purchase' => [
                'total' => 10000,
                'currency' => 'MYR',
                'products' => [['name' => 'Test', 'price' => 10000, 'quantity' => 1]],
            ],
            'is_test' => true,
        ];

        $request = Request::create('/webhook', 'POST', $payload);
        $response = $controller->handle($request);

        expect($response->getStatusCode())->toBe(200)
            ->and($response->getData()->status)->toBe('ok')
            ->and($response->getData()->event_type)->toBe('purchase.paid');
    });

    it('handles payout webhook', function () {
        $controller = new WebhookController;

        $payload = [
            'id' => 'payout_test123',
            'type' => 'payout',
            'event_type' => 'payout.success',
            'status' => 'success',
            'amount' => 10000,
            'currency' => 'MYR',
            'created_on' => time(),
            'updated_on' => time(),
            'is_test' => true,
        ];

        $request = Request::create('/webhook', 'POST', $payload);
        $response = $controller->handle($request);

        expect($response->getStatusCode())->toBe(200)
            ->and($response->getData()->event_type)->toBe('payout.success');
    });

    it('handles billing template client webhook', function () {
        $controller = new WebhookController;

        $payload = [
            'id' => 'btc_test123',
            'type' => 'billing_template_client',
            'event_type' => 'billing_template_client.subscription_billing_cancelled',
            'status' => 'cancelled',
            'billing_template_id' => 'bt_123',
            'client_id' => 'client_123',
            'created_on' => time(),
            'updated_on' => time(),
            'is_test' => true,
        ];

        $request = Request::create('/webhook', 'POST', $payload);
        $response = $controller->handle($request);

        expect($response->getStatusCode())->toBe(200)
            ->and($response->getData()->event_type)->toBe('billing_template_client.subscription_billing_cancelled');
    });

    it('handles unknown event type gracefully', function () {
        $controller = new WebhookController;

        $payload = [
            'id' => 'unknown_123',
            'type' => 'unknown',
            'event_type' => 'unknown.event',
        ];

        $request = Request::create('/webhook', 'POST', $payload);
        $response = $controller->handle($request);

        expect($response->getStatusCode())->toBe(200)
            ->and($response->getData()->event_type)->toBe('unknown.event');
    });

    it('handles missing event_type', function () {
        $controller = new WebhookController;

        $request = Request::create('/webhook', 'POST', []);
        $response = $controller->handle($request);

        expect($response->getStatusCode())->toBe(200)
            ->and($response->getData()->event_type)->toBe('unknown');
    });
});

describe('VerifyWebhookSignature middleware', function () {
    it('can be instantiated', function () {
        $webhookService = Mockery::mock(WebhookService::class);
        $middleware = new VerifyWebhookSignature($webhookService);

        expect($middleware)->toBeInstanceOf(VerifyWebhookSignature::class);
    });

    it('returns 400 when signature header is missing', function () {
        $webhookService = Mockery::mock(WebhookService::class);
        $middleware = new VerifyWebhookSignature($webhookService);

        $request = Request::create('/webhook', 'POST', ['test' => 'data']);

        $response = $middleware->handle($request, fn() => response()->json(['ok' => true]));

        expect($response->getStatusCode())->toBe(400)
            ->and($response->getData()->error)->toContain('Missing');
    });

    it('returns 401 when signature verification fails', function () {
        $webhookService = Mockery::mock(WebhookService::class);
        $webhookService->shouldReceive('verifySignature')
            ->once()
            ->andReturn(false);

        $middleware = new VerifyWebhookSignature($webhookService);

        $request = Request::create('/webhook', 'POST', ['test' => 'data'], [], [], [
            'HTTP_X_SIGNATURE' => 'invalid-signature',
        ]);

        $response = $middleware->handle($request, fn() => response()->json(['ok' => true]));

        expect($response->getStatusCode())->toBe(401)
            ->and($response->getData()->error)->toContain('Invalid');
    });

    it('passes request when signature is valid', function () {
        $webhookService = Mockery::mock(WebhookService::class);
        $webhookService->shouldReceive('verifySignature')
            ->once()
            ->andReturn(true);

        $middleware = new VerifyWebhookSignature($webhookService);

        config(['chip.webhooks.log_payloads' => false]);

        $request = Request::create('/webhook', 'POST', [
            'event_type' => 'purchase.paid',
            'id' => 'purch_123',
        ], [], [], [
            'HTTP_X_SIGNATURE' => 'valid-signature',
        ]);

        $response = $middleware->handle($request, fn() => response()->json(['ok' => true]));

        expect($response->getStatusCode())->toBe(200)
            ->and($response->getData()->ok)->toBeTrue();
    });

    it('logs payload when logging is enabled', function () {
        $webhookService = Mockery::mock(WebhookService::class);
        $webhookService->shouldReceive('verifySignature')
            ->once()
            ->andReturn(true);

        $middleware = new VerifyWebhookSignature($webhookService);

        config(['chip.webhooks.log_payloads' => true]);

        $request = Request::create('/webhook', 'POST', [
            'event_type' => 'purchase.paid',
            'id' => 'purch_123',
        ], [], [], [
            'HTTP_X_SIGNATURE' => 'valid-signature',
        ]);

        $response = $middleware->handle($request, fn() => response()->json(['ok' => true]));

        expect($response->getStatusCode())->toBe(200);
    });
});
