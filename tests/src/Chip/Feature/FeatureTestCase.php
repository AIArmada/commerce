<?php

declare(strict_types=1);

namespace AIArmada\Chip\Tests\Feature;

use AIArmada\Chip\Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Config;

abstract class FeatureTestCase extends TestCase
{
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set([
            'chip.collect.secret_key' => 'test_secret_key',
            'chip.collect.brand_id' => 'test_brand_id',
            'chip.send.api_key' => 'test_api_key',
            'chip.send.api_secret' => 'test_api_secret',
            'chip.is_sandbox' => true,
            'chip.webhooks.verify_signature' => false,
            'chip.logging.channel' => 'single',
            'logging.channels.single.driver' => 'single',
            'logging.channels.single.path' => storage_path('logs/laravel.log'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function createWebhookPayload(string $event, array $data, array $overrides = []): array
    {
        return array_merge([
            'event' => $event,
            'data' => $data,
            'timestamp' => now()->toISOString(),
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function signWebhookPayload(array $payload): string
    {
        return base64_encode(hash_hmac('sha256', json_encode($payload, JSON_THROW_ON_ERROR), 'test_secret', true));
    }
}
