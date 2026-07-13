<?php

declare(strict_types=1);

use AIArmada\Affiliates\Jobs\DispatchAffiliateWebhook;
use AIArmada\Affiliates\Models\AffiliateWebhookDelivery;
use AIArmada\Affiliates\Support\Webhooks\WebhookDispatcher;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
    Config::set('affiliates.webhooks.signature_secret', 'test-signing-secret');
    Config::set('affiliates.webhooks.delivery.max_attempts', 4);
});

test('webhook dispatcher does nothing when webhooks are disabled', function (): void {
    Config::set('affiliates.events.dispatch_webhooks', false);

    app(WebhookDispatcher::class)->dispatch('test', ['key' => 'value']);

    expect(AffiliateWebhookDelivery::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

test('webhook dispatcher persists immutable signed deliveries before queueing', function (): void {
    Config::set('affiliates.events.dispatch_webhooks', true);
    Config::set('affiliates.webhooks.endpoints.test', ['https://example.com/webhook']);
    Config::set('affiliates.webhooks.headers', ['Authorization' => 'Bearer token']);

    app(WebhookDispatcher::class)->dispatch('test', ['key' => 'value']);

    $delivery = AffiliateWebhookDelivery::query()->sole();
    $body = json_decode($delivery->body_json, true, flags: JSON_THROW_ON_ERROR);

    expect($delivery->event_type)->toBe('test')
        ->and($delivery->status)->toBe('pending')
        ->and($delivery->attempt_count)->toBe(0)
        ->and($delivery->max_attempts)->toBe(4)
        ->and($delivery->headers)->toBe(['Authorization' => 'Bearer token'])
        ->and($body['id'])->toBe($delivery->event_id)
        ->and($body['type'])->toBe('test')
        ->and($body['data'])->toBe(['key' => 'value'])
        ->and($delivery->signature)->toBe(hash_hmac('sha256', $delivery->body_json, 'test-signing-secret'));

    Queue::assertPushed(DispatchAffiliateWebhook::class, fn (DispatchAffiliateWebhook $job): bool => $job->deliveryId === $delivery->id);
});

test('webhook dispatcher creates one delivery per distinct configured destination', function (): void {
    Config::set('affiliates.events.dispatch_webhooks', true);
    Config::set('affiliates.webhooks.endpoints.test', [
        'https://example.com/one',
        'https://example.com/two',
        'https://example.com/one',
        '',
        'mailto:ops@example.com',
    ]);

    app(WebhookDispatcher::class)->dispatch('test', ['key' => 'value']);

    expect(AffiliateWebhookDelivery::query()->count())->toBe(2)
        ->and(AffiliateWebhookDelivery::query()->distinct()->count('event_id'))->toBe(1);
    Queue::assertPushed(DispatchAffiliateWebhook::class, 2);
});

test('unsafe endpoints become durable failures in the queued delivery path', function (): void {
    Config::set('affiliates.events.dispatch_webhooks', true);
    Config::set('affiliates.webhooks.endpoints.test', ['http://127.0.0.1/internal']);

    app(WebhookDispatcher::class)->dispatch('test', ['key' => 'value']);

    expect(AffiliateWebhookDelivery::query()->sole()->endpoint)->toBe('http://127.0.0.1/internal');
    Queue::assertPushed(DispatchAffiliateWebhook::class, 1);
});
