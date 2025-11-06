<?php

declare(strict_types=1);

use AIArmada\Chip\Facades\ChipSend;

beforeEach(function (): void {
    config()->set('chip.send.api_key', 'test-key');
    config()->set('chip.send.api_secret', 'test-secret');
    config()->set('chip.send.environment', 'sandbox');
});

it('proxies send service helpers through the ChipSend facade', function (): void {
    ChipSend::shouldReceive('listAccounts')
        ->once()
        ->andReturn(['account']);

    expect(ChipSend::listAccounts())->toBe(['account']);
});

it('allows mocking of webhook helpers through the ChipSend facade', function (): void {
    $webhook = new AIArmada\Chip\DataObjects\SendWebhook(
        id: 123,
        name: 'test-webhook',
        public_key: 'test-key',
        callback_url: 'https://example.com',
        email: 'test@example.com',
        event_hooks: ['send.created'],
        created_at: '2023-01-01T00:00:00Z',
        updated_at: '2023-01-01T00:00:00Z',
    );

    ChipSend::shouldReceive('createSendWebhook')
        ->once()
        ->with(['url' => 'https://example.com'])
        ->andReturn($webhook);

    expect(ChipSend::createSendWebhook(['url' => 'https://example.com']))
        ->toBe($webhook);
});
