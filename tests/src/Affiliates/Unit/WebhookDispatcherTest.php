<?php

declare(strict_types=1);

use AIArmada\Affiliates\Support\Webhooks\WebhookDispatcher;
use AIArmada\CommerceSupport\Support\PublicHttpUrlGuard;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::fake();
});

function publicWebhookDispatcher(): WebhookDispatcher
{
    return new WebhookDispatcher(new PublicHttpUrlGuard(
        static fn (string $_host): array => ['93.184.216.34'],
    ));
}

test('webhook dispatcher does nothing when webhooks disabled', function (): void {
    Config::set('affiliates.events.dispatch_webhooks', false);

    $dispatcher = publicWebhookDispatcher();
    $dispatcher->dispatch('test', ['key' => 'value']);

    Http::assertNothingSent();
});

test('webhook dispatcher sends requests to endpoints', function (): void {
    Config::set('affiliates.events.dispatch_webhooks', true);
    Config::set('affiliates.webhooks.endpoints.test', ['https://example.com/webhook']);
    Config::set('affiliates.webhooks.headers', ['Authorization' => 'Bearer token']);

    $dispatcher = publicWebhookDispatcher();
    $dispatcher->dispatch('test', ['key' => 'value']);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://example.com/webhook' &&
               $request->hasHeader('Authorization', 'Bearer token') &&
               $request->hasHeader('X-Affiliates-Webhook-Signature') &&
               $request['type'] === 'test' &&
               $request['data'] === ['key' => 'value'];
    });
});

test('webhook dispatcher handles multiple endpoints', function (): void {
    Config::set('affiliates.events.dispatch_webhooks', true);
    Config::set('affiliates.webhooks.endpoints.test', ['https://example.com/1', 'https://example.com/2']);

    $dispatcher = publicWebhookDispatcher();
    $dispatcher->dispatch('test', ['key' => 'value']);

    Http::assertSentCount(2);
});

test('webhook dispatcher skips empty endpoints', function (): void {
    Config::set('affiliates.events.dispatch_webhooks', true);
    Config::set('affiliates.webhooks.endpoints.test', ['https://example.com', '', '  ']);

    $dispatcher = publicWebhookDispatcher();
    $dispatcher->dispatch('test', ['key' => 'value']);

    Http::assertSentCount(1);
});

test('webhook dispatcher rejects private network endpoints', function (): void {
    Config::set('affiliates.events.dispatch_webhooks', true);
    Config::set('affiliates.webhooks.endpoints.test', ['http://127.0.0.1/internal']);

    publicWebhookDispatcher()->dispatch('test', ['key' => 'value']);

    Http::assertNothingSent();
});

test('webhook dispatcher does not follow redirects', function (): void {
    Http::fakeSequence()
        ->push('', 302, ['Location' => 'http://127.0.0.1/internal']);

    Config::set('affiliates.events.dispatch_webhooks', true);
    Config::set('affiliates.webhooks.endpoints.test', ['https://example.com/webhook']);

    publicWebhookDispatcher()->dispatch('test', ['key' => 'value']);

    Http::assertSentCount(1);
});
