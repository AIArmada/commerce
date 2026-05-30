<?php

declare(strict_types=1);

use AIArmada\Chip\Events\WebhookReceived;
use AIArmada\Chip\Http\Controllers\WebhookController;
use AIArmada\Chip\Http\Middleware\VerifyWebhookSignature;
use AIArmada\Chip\Listeners\StoreWebhookData;
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
            'queue.default' => 'sync',
        ]);

        if (! Schema::hasTable('webhook_calls')) {
            Schema::create('webhook_calls', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->string('name');
                $table->string('url', 512);
                $table->json('headers')->nullable();
                $table->json('payload')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->json('attachments')->nullable();
                $table->text('exception')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('webhook_calls', function (Blueprint $table): void {
            if (! Schema::hasColumn('webhook_calls', 'event_type')) {
                $table->string('event_type')->nullable();
            }
            if (! Schema::hasColumn('webhook_calls', 'event')) {
                $table->string('event')->nullable();
            }
            if (! Schema::hasColumn('webhook_calls', 'status')) {
                $table->string('status')->nullable();
            }
            if (! Schema::hasColumn('webhook_calls', 'verified')) {
                $table->boolean('verified')->default(false);
            }
            if (! Schema::hasColumn('webhook_calls', 'processed')) {
                $table->boolean('processed')->default(false);
            }
            if (! Schema::hasColumn('webhook_calls', 'idempotency_key')) {
                $table->string('idempotency_key')->nullable();
            }
            if (! Schema::hasColumn('webhook_calls', 'title')) {
                $table->string('title')->nullable();
            }
            if (! Schema::hasColumn('webhook_calls', 'events')) {
                $table->json('events')->nullable();
            }
            if (! Schema::hasColumn('webhook_calls', 'callback')) {
                $table->string('callback', 512)->nullable();
            }
            if (! Schema::hasColumn('webhook_calls', 'created_on')) {
                $table->bigInteger('created_on')->nullable();
            }
            if (! Schema::hasColumn('webhook_calls', 'updated_on')) {
                $table->bigInteger('updated_on')->nullable();
            }
            if (! Schema::hasColumn('webhook_calls', 'last_error')) {
                $table->text('last_error')->nullable();
            }
            if (! Schema::hasColumn('webhook_calls', 'processing_time_ms')) {
                $table->decimal('processing_time_ms', 10, 3)->nullable();
            }
            if (! Schema::hasColumn('webhook_calls', 'owner_type')) {
                $table->string('owner_type')->nullable();
            }
            if (! Schema::hasColumn('webhook_calls', 'owner_id')) {
                $table->string('owner_id')->nullable();
            }
        });
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

    it('accepts signed webhooks using configured webhook public keys', function (): void {
        $controller = new WebhookController;

        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);

        openssl_pkey_export($key, $privateKey);
        $details = openssl_pkey_get_details($key);

        config([
            'chip.webhooks.verify_signature' => true,
            'chip.webhooks.company_public_key' => null,
            'chip.webhooks.webhook_keys' => [
                'wh_123' => $details['key'],
            ],
        ]);

        $payload = json_encode([
            'id' => 'purch_signed_123',
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
        ], JSON_THROW_ON_ERROR);

        openssl_sign($payload, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        $request = Request::create(
            uri: '/webhook',
            method: 'POST',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_SIGNATURE' => base64_encode($signature),
            ],
            content: $payload,
        );

        $response = $controller->handle($request);

        expect($response->getStatusCode())->toBe(200)
            ->and($response->getData()->status)->toBe('accepted');
    });

    it('ignores duplicate webhook payloads after the first successful processing', function (): void {
        Event::fakeExcept([WebhookReceived::class]);
        Event::listen(WebhookReceived::class, StoreWebhookData::class);

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
            'platform' => 'api',
            'product' => 'purchases',
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
