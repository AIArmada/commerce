<?php

declare(strict_types=1);

use AIArmada\Chip\Http\Controllers\WebhookController;
use AIArmada\Chip\Http\Middleware\VerifyWebhookSignature;
use AIArmada\Chip\Models\Webhook;
use AIArmada\Chip\Services\WebhookService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

describe('WebhookController', function (): void {
    beforeEach(function (): void {
        // Disable listeners that require database
        Event::fake();
        config([
            'chip.webhooks.store_webhooks' => false,
            'chip.webhooks.verify_signature' => false,
        ]);

        if (! Schema::hasTable('webhook_calls')) {
            Schema::create('webhook_calls', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->string('name');
                $table->string('url')->nullable();
                $table->json('headers')->nullable();
                $table->json('payload')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->json('attachments')->nullable();
                $table->text('exception')->nullable();
                $table->timestamps();
            });
        }
    });

    afterEach(function (): void {
        Mockery::close();
    });

    it('can be instantiated', function (): void {
        $controller = new WebhookController;
        expect($controller)->toBeInstanceOf(WebhookController::class);
    });

    it('handles purchase.paid webhook', function (): void {
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
            ->and($response->getData()->status)->toBe('accepted');
    });

    it('handles payout webhook', function (): void {
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
            ->and($response->getData()->status)->toBe('accepted');
    });

    it('handles billing template client webhook', function (): void {
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
            ->and($response->getData()->status)->toBe('accepted');
    });

    it('handles unknown event type gracefully', function (): void {
        $controller = new WebhookController;

        $payload = [
            'id' => 'unknown_123',
            'type' => 'unknown',
            'event_type' => 'unknown.event',
        ];

        $request = Request::create('/webhook', 'POST', $payload);
        $response = $controller->handle($request);

        expect($response->getStatusCode())->toBe(200)
            ->and($response->getData()->status)->toBe('accepted');
    });

    it('handles missing event_type', function (): void {
        $controller = new WebhookController;

        $request = Request::create('/webhook', 'POST', []);
        $response = $controller->handle($request);

        expect($response->getStatusCode())->toBe(200)
            ->and($response->getData()->status)->toBe('accepted');
    });

    it('ignores duplicate webhook payloads after the first successful processing', function (): void {
        config([
            'chip.webhooks.store_webhooks' => true,
            'chip.webhooks.deduplication' => true,
        ]);

        $controller = new WebhookController;

        $payload = [
            'id' => 'purch_duplicate_123',
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

        $responseOne = $controller->handle(Request::create('/webhook', 'POST', $payload));
        $responseTwo = $controller->handle(Request::create('/webhook', 'POST', $payload));

        expect($responseOne->getStatusCode())->toBe(200)
            ->and($responseTwo->getStatusCode())->toBe(200)
            ->and($responseTwo->getData()->status)->toBe('accepted')
            ->and(Webhook::count())->toBe(1)
            ->and(Webhook::query()->first()?->processed)->toBeTrue();
    });
});

describe('VerifyWebhookSignature middleware', function (): void {
    afterEach(function (): void {
        Mockery::close();
    });

    it('can be instantiated', function (): void {
        $webhookService = Mockery::mock(WebhookService::class);
        $middleware = new VerifyWebhookSignature($webhookService);

        expect($middleware)->toBeInstanceOf(VerifyWebhookSignature::class);
    });

    it('returns 400 when signature header is missing', function (): void {
        $webhookService = Mockery::mock(WebhookService::class);
        $middleware = new VerifyWebhookSignature($webhookService);

        $request = Request::create('/webhook', 'POST', ['test' => 'data']);

        $response = $middleware->handle($request, fn () => response()->json(['ok' => true]));

        expect($response->getStatusCode())->toBe(400)
            ->and($response->getData()->error)->toContain('Missing');
    });

    it('returns 401 when signature verification fails', function (): void {
        $webhookService = Mockery::mock(WebhookService::class);
        $webhookService->shouldReceive('verifySignature')
            ->once()
            ->andReturn(false);

        $middleware = new VerifyWebhookSignature($webhookService);

        $request = Request::create('/webhook', 'POST', ['test' => 'data'], [], [], [
            'HTTP_X_SIGNATURE' => 'invalid-signature',
        ]);

        $response = $middleware->handle($request, fn () => response()->json(['ok' => true]));

        expect($response->getStatusCode())->toBe(401)
            ->and($response->getData()->error)->toContain('Invalid');
    });

    it('passes request when signature is valid', function (): void {
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

        $response = $middleware->handle($request, fn () => response()->json(['ok' => true]));

        expect($response->getStatusCode())->toBe(200)
            ->and($response->getData()->ok)->toBeTrue();
    });

    it('logs payload when logging is enabled', function (): void {
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

        $response = $middleware->handle($request, fn () => response()->json(['ok' => true]));

        expect($response->getStatusCode())->toBe(200);
    });
});
