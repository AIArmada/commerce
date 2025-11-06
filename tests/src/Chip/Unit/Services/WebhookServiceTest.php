<?php

declare(strict_types=1);

use AIArmada\Chip\Exceptions\WebhookVerificationException;
use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\Chip\Services\WebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

describe('WebhookService', function (): void {
    beforeEach(function (): void {
        $this->webhookService = new WebhookService;

        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);

        openssl_pkey_export($key, $privateKey);
        $details = openssl_pkey_get_details($key);

        $this->privateKey = $privateKey;
        $this->publicKey = $details['key'];

        config()->set('chip.webhooks.verify_signature', true);
        config()->set('cache.default', 'array');
        Cache::store('array')->clear();
    });

    afterEach(function (): void {
        Mockery::close();
    });

    it('verifies valid webhook signatures', function (): void {
        $payload = json_encode([
            'event' => 'purchase.paid',
            'data' => ['id' => 'purchase_123'],
        ], JSON_THROW_ON_ERROR);

        openssl_sign($payload, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);

        expect($this->webhookService->verifySignature($payload, base64_encode($signature), $this->publicKey))
            ->toBeTrue();
    });

    it('skips signature verification when disabled', function (): void {
        config()->set('chip.webhooks.verify_signature', false);

        expect($this->webhookService->verifySignature('payload'))->toBeTrue();
    });

    it('throws when signature header is missing', function (): void {
        $request = Request::create('/', 'POST', [], [], [], [], json_encode(['event' => 'purchase.created', 'data' => []]));

        $service = new class extends WebhookService
        {
            public function getPublicKey(?string $webhookId = null): string
            {
                return 'dummy-public-key';
            }
        };

        expect(fn () => $service->verifySignature($request))
            ->toThrow(WebhookVerificationException::class, 'Missing signature header');
    });

    it('throws when signature is not valid base64', function (): void {
        $service = new class extends WebhookService
        {
            private ?string $pem = null;

            public function getPublicKey(?string $webhookId = null): string
            {
                return $this->pem ??= openssl_pkey_get_details(openssl_pkey_new([
                    'private_key_bits' => 1024,
                    'private_key_type' => OPENSSL_KEYTYPE_RSA,
                ]))['key'];
            }
        };

        expect(fn () => $service->verifySignature('payload', '***invalid***'))
            ->toThrow(WebhookVerificationException::class, 'Signature is not valid base64');
    });

    it('parses payloads and rejects invalid json', function (): void {
        $payload = json_encode(['foo' => 'bar'], JSON_THROW_ON_ERROR);

        expect($this->webhookService->parsePayload($payload))->toEqual((object) ['foo' => 'bar']);

        expect(fn () => $this->webhookService->parsePayload('{invalid json'))
            ->toThrow(WebhookVerificationException::class, 'Invalid JSON payload');
    });

    it('retrieves and caches public keys from the collect service', function (): void {
        Cache::forget(config('chip.cache.prefix').'public_key');

        // Clear the company_public_key to force API fetch
        config(['chip.webhooks.company_public_key' => null]);

        $originalClient = app(AIArmada\Chip\Clients\ChipCollectClient::class);
        $collectClient = Mockery::mock(AIArmada\Chip\Clients\ChipCollectClient::class);
        $collectClient->shouldReceive('get')
            ->once()
            ->with('public_key/')
            ->andReturn('-----BEGIN PUBLIC KEY-----\ntest-cached-public-key\n-----END PUBLIC KEY-----');

        app()->instance(AIArmada\Chip\Clients\ChipCollectClient::class, $collectClient);

        try {
            // Create a new WebhookService instance after installing the mock
            $webhookService = new WebhookService;
            expect($webhookService->getPublicKey())->toBe('-----BEGIN PUBLIC KEY-----\ntest-cached-public-key\n-----END PUBLIC KEY-----');
            expect($webhookService->getPublicKey())->toBe('-----BEGIN PUBLIC KEY-----\ntest-cached-public-key\n-----END PUBLIC KEY-----'); // Second call uses cache
        } finally {
            app()->instance(AIArmada\Chip\Clients\ChipCollectClient::class, $originalClient);
        }
    });

    it('retrieves webhook specific public keys when webhook id provided', function (): void {
        $originalCollect = app(ChipCollectService::class);
        $collectService = Mockery::mock(ChipCollectService::class);
        $collectService->shouldReceive('getWebhook')
            ->once()
            ->with('wh_123')
            ->andReturn(['public_key' => 'webhook-key']);

        app()->instance(ChipCollectService::class, $collectService);

        try {
            expect($this->webhookService->getPublicKey('wh_123'))->toBe('webhook-key');
        } finally {
            app()->instance(ChipCollectService::class, $originalCollect);
        }
    });

    it('falls back to configured company public key when api calls fail', function (): void {
        config(['chip.webhooks.company_public_key' => 'company-fallback-key']);

        $originalCollect = app(ChipCollectService::class);
        $collectService = Mockery::mock(ChipCollectService::class);
        $collectService->shouldReceive('getPublicKey')->andThrow(new RuntimeException('network error'));

        app()->instance(ChipCollectService::class, $collectService);

        try {
            expect($this->webhookService->getPublicKey())->toBe('company-fallback-key');
        } finally {
            app()->instance(ChipCollectService::class, $originalCollect);
        }
    });

    it('throws exception when company public key is not configured', function (): void {
        config(['chip.webhooks.company_public_key' => null]);

        $originalCollect = app(ChipCollectService::class);
        $collectService = Mockery::mock(ChipCollectService::class);
        $collectService->shouldReceive('getPublicKey')->andThrow(new RuntimeException('network error'));

        app()->instance(ChipCollectService::class, $collectService);

        try {
            expect(fn () => $this->webhookService->getPublicKey())
                ->toThrow(WebhookVerificationException::class, 'Company public key is required but not configured');
        } finally {
            app()->instance(ChipCollectService::class, $originalCollect);
        }
    });

    it('prevents disabling signature verification in production', function (): void {
        // Temporarily set the app environment to production
        $originalEnv = app()->environment();

        // Force the environment to production for this test
        app()->bind('env', fn () => 'production');

        try {
            // Verify we're actually in production
            expect(app()->environment())->toBe('production');

            // Try to disable signature verification
            config()->set('chip.webhooks.verify_signature', false);

            // Should throw an exception
            expect(fn () => $this->webhookService->verifySignature('payload'))
                ->toThrow(WebhookVerificationException::class, 'Signature verification cannot be disabled in production environment');
        } finally {
            // Restore original environment
            app()->bind('env', fn () => $originalEnv);
        }
    });
});
