<?php

declare(strict_types=1);

use AIArmada\Chip\Clients\ChipCollectClient;
use AIArmada\Chip\Services\Collect\WebhooksApi;
use Illuminate\Support\Facades\Log;

beforeEach(function (): void {
    $this->client = Mockery::mock(ChipCollectClient::class);
    $this->api = new WebhooksApi($this->client);
});

describe('Collect Webhooks API', function (): void {
    it('creates, finds, updates, and deletes webhooks', function (): void {
        $payload = ['callback' => 'https://example.com/webhook', 'events' => ['purchase.paid']];
        $response = ['id' => 'wh_123'];

        $this->client->shouldReceive('post')
            ->once()
            ->with('webhooks/', $payload)
            ->andReturn($response);

        expect($this->api->create($payload))->toBe($response);

        $this->client->shouldReceive('get')
            ->once()
            ->with('webhooks/wh_123/')
            ->andReturn($response + ['callback' => $payload['callback']]);

        expect($this->api->find('wh_123'))
            ->toBe($response + ['callback' => $payload['callback']]);

        $updatePayload = $payload + ['events' => ['purchase.created']];

        $this->client->shouldReceive('put')
            ->once()
            ->with('webhooks/wh_123/', $updatePayload)
            ->andReturn($response + ['events' => ['purchase.created']]);

        expect($this->api->update('wh_123', $updatePayload))
            ->toBe($response + ['events' => ['purchase.created']]);

        $this->client->shouldReceive('delete')
            ->once()
            ->with('webhooks/wh_123/')
            ->andReturn([]);

        expect(fn () => $this->api->delete('wh_123'))->not->toThrow(Exception::class);
    });

    it('lists webhooks with filters and logs errors', function (): void {
        $filters = ['status' => 'active'];
        $expected = ['data' => [['id' => 'wh_active']]];

        $this->client->shouldReceive('get')
            ->once()
            ->with('webhooks/?status=active')
            ->andReturn($expected);

        expect($this->api->list($filters))->toBe($expected);

        Log::spy();

        $this->client->shouldReceive('get')
            ->once()
            ->with('webhooks/')
            ->andThrow(new RuntimeException('request failed'));

        expect(fn () => $this->api->list())->toThrow(RuntimeException::class);

        Log::shouldHaveLogged('error', function ($message, $context) {
            return str_contains($message, 'Failed to list CHIP webhooks')
                && $context['error'] === 'request failed';
        });
    });
});
