<?php

declare(strict_types=1);

use AIArmada\Chip\Events\SendWebhookReceived;
use AIArmada\Chip\Http\Controllers\SendWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $key = openssl_pkey_new([
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
        'private_key_bits' => 2048,
    ]);

    expect($key)->not->toBeFalse();

    openssl_pkey_export($key, $privateKey);
    $details = openssl_pkey_get_details($key);

    expect($details)->toBeArray()->toHaveKey('key');

    $this->sendPrivateKey = $privateKey;

    config()->set('chip.webhooks.verify_signature', true);
    config()->set('chip.webhooks.send.webhook_id', null);
    config()->set('chip.webhooks.send.webhook_keys', [$details['key']]);
});

it('dispatches the verified Send payload without inventing a Collect event envelope', function (): void {
    Event::fake();

    $payload = json_encode([
        'id' => 17,
        'state' => 'completed',
        'reference' => 'settlement-17',
    ], JSON_THROW_ON_ERROR);

    openssl_sign($payload, $signature, $this->sendPrivateKey, OPENSSL_ALGO_SHA512);

    $response = app(SendWebhookController::class)->handle(
        Request::create(
            uri: '/chip/send/webhooks',
            method: 'POST',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_SIGNATURE' => base64_encode($signature),
            ],
            content: $payload,
        )
    );

    expect($response->getStatusCode())->toBe(200);
    Event::assertDispatched(SendWebhookReceived::class, function (SendWebhookReceived $event) use ($payload): bool {
        return $event->payload === json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
    });
});

it('rejects a non-object Send payload', function (): void {
    $payload = json_encode(['completed'], JSON_THROW_ON_ERROR);
    openssl_sign($payload, $signature, $this->sendPrivateKey, OPENSSL_ALGO_SHA512);

    $response = app(SendWebhookController::class)->handle(
        Request::create(
            uri: '/chip/send/webhooks',
            method: 'POST',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_SIGNATURE' => base64_encode($signature),
            ],
            content: $payload,
        )
    );

    expect($response->getStatusCode())->toBe(400);
});

it('rejects a Send webhook without a signature header', function (): void {
    $payload = json_encode([
        'id' => 18,
        'state' => 'completed',
    ], JSON_THROW_ON_ERROR);

    $response = app(SendWebhookController::class)->handle(
        Request::create(
            uri: '/chip/send/webhooks',
            method: 'POST',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $payload,
        )
    );

    expect($response->getStatusCode())->toBe(401);
});

it('rejects a Send webhook when no Send public key is configured', function (): void {
    config()->set('chip.webhooks.send.webhook_keys', []);

    $payload = json_encode([
        'id' => 19,
        'state' => 'completed',
    ], JSON_THROW_ON_ERROR);

    openssl_sign($payload, $signature, $this->sendPrivateKey, OPENSSL_ALGO_SHA512);

    $response = app(SendWebhookController::class)->handle(
        Request::create(
            uri: '/chip/send/webhooks',
            method: 'POST',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_SIGNATURE' => base64_encode($signature),
            ],
            content: $payload,
        )
    );

    expect($response->getStatusCode())->toBe(401);
});

it('rejects a forged Send webhook signature', function (): void {
    $forgedKey = openssl_pkey_new([
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
        'private_key_bits' => 2048,
    ]);

    expect($forgedKey)->not->toBeFalse();
    openssl_pkey_export($forgedKey, $forgedPrivateKey);

    $payload = json_encode([
        'id' => 20,
        'state' => 'completed',
    ], JSON_THROW_ON_ERROR);

    openssl_sign($payload, $signature, $forgedPrivateKey, OPENSSL_ALGO_SHA512);

    $response = app(SendWebhookController::class)->handle(
        Request::create(
            uri: '/chip/send/webhooks',
            method: 'POST',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_SIGNATURE' => base64_encode($signature),
            ],
            content: $payload,
        )
    );

    expect($response->getStatusCode())->toBe(401);
});

it('rejects a Collect SHA-256 signature on the Send route', function (): void {
    $payload = json_encode([
        'id' => 21,
        'state' => 'completed',
    ], JSON_THROW_ON_ERROR);

    openssl_sign($payload, $signature, $this->sendPrivateKey, OPENSSL_ALGO_SHA256);

    $response = app(SendWebhookController::class)->handle(
        Request::create(
            uri: '/chip/send/webhooks',
            method: 'POST',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_SIGNATURE' => base64_encode($signature),
            ],
            content: $payload,
        )
    );

    expect($response->getStatusCode())->toBe(401);
});
