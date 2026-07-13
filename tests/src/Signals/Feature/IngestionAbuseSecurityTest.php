<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\TrackedProperty;
use Illuminate\Testing\TestResponse;

uses(SignalsTestCase::class);

function createSecureSignalsProperty(object $test, string $writeKey, ?string $domain = null): TrackedProperty
{
    /** @var User $owner */
    $owner = User::query()->create([
        'name' => 'Secure Signals Owner ' . $writeKey,
        'email' => $writeKey . '@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $property = TrackedProperty::query()->create([
        'name' => 'Secure Signals Property ' . $writeKey,
        'slug' => 'secure-' . $writeKey,
        'write_key' => $writeKey,
        'domain' => $domain,
    ]);
    $property->assignOwner($owner)->save();

    return $property;
}

/**
 * @param array<string, mixed> $payload
 */
function postSignedSignalsOutcome(object $test, array $payload, string $secret, ?int $timestamp = null, ?string $signature = null): TestResponse
{
    $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    $timestamp ??= time();
    $signature ??= hash_hmac('sha256', $timestamp . '.' . $body, $secret);

    return $test->call(
        'POST',
        '/api/signals/collect/server-outcome',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_SIGNALS_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_SIGNALS_SIGNATURE' => 'sha256=' . $signature,
        ],
        $body,
    );
}

it('rejects financial values and transaction identifiers on the browser route', function (): void {
    createSecureSignalsProperty($this, 'browser-financial-key');

    $this->postJson('/api/signals/collect/browser-event', [
        'write_key' => 'browser-financial-key',
        'event_name' => 'checkout_progressed',
        'revenue_minor' => 12_500,
        'properties' => [
            'transaction_id' => 'txn-untrusted',
        ],
    ])->assertUnprocessable();

    expect(SignalEvent::query()->withoutOwnerScope()->count())->toBe(0);
});

it('rejects oversized and over-nested browser payloads', function (): void {
    createSecureSignalsProperty($this, 'browser-size-key');
    config()->set('signals.ingestion.browser.max_string_bytes', 32);

    $this->postJson('/api/signals/collect/browser-event', [
        'write_key' => 'browser-size-key',
        'event_name' => 'custom.large',
        'properties' => [
            'title' => str_repeat('x', 33),
        ],
    ])->assertUnprocessable();

    expect(SignalEvent::query()->withoutOwnerScope()->count())->toBe(0);
});

it('does not treat a forged origin as browser authentication', function (): void {
    createSecureSignalsProperty($this, 'origin-policy-key', 'shop.example.test');

    $this->withHeader('Origin', 'https://shop.example.test')
        ->postJson('/api/signals/collect/browser-event', [
            'write_key' => 'not-a-real-write-key',
            'event_name' => 'page_view',
            'url' => 'https://shop.example.test/products',
        ])
        ->assertNotFound();

    $this->withHeader('Origin', 'https://attacker.example')
        ->postJson('/api/signals/collect/browser-event', [
            'write_key' => 'origin-policy-key',
            'event_name' => 'page_view',
            'url' => 'https://attacker.example/products',
        ])
        ->assertUnprocessable();
});

it('rate limits browser ingestion by property key and client address', function (): void {
    createSecureSignalsProperty($this, 'browser-rate-key');
    config()->set('signals.ingestion.browser.rate_limit_per_minute', 1);

    $payload = [
        'write_key' => 'browser-rate-key',
        'event_name' => 'custom.clicked',
    ];

    $this->postJson('/api/signals/collect/browser-event', $payload)->assertAccepted();
    $this->postJson('/api/signals/collect/browser-event', $payload)->assertStatus(429);
});

it('requires a valid trusted signature and rejects replayed requests', function (): void {
    $property = createSecureSignalsProperty($this, 'trusted-signature-key');
    $secret = 'trusted-signals-secret-with-enough-entropy';
    config()->set('signals.ingestion.trusted.secret', $secret);

    $payload = [
        'write_key' => 'trusted-signature-key',
        'event_name' => 'order.paid',
        'event_category' => 'conversion',
        'idempotency_key' => 'order-paid-1001',
        'transaction_id' => 'txn-1001',
        'revenue_minor' => 45_900,
        'currency' => 'myr',
        'properties' => [
            'order_number' => 'ORD-1001',
        ],
    ];

    postSignedSignalsOutcome($this, $payload, $secret, signature: str_repeat('0', 64))
        ->assertUnauthorized();

    $timestamp = time();
    postSignedSignalsOutcome($this, $payload, $secret, $timestamp)
        ->assertAccepted()
        ->assertJsonPath('data.tracked_property_id', $property->id);

    postSignedSignalsOutcome($this, $payload, $secret, $timestamp)
        ->assertStatus(409);

    $event = SignalEvent::query()->withoutOwnerScope()->firstOrFail();

    expect($event->source_event_id)->toBe('txn-1001')
        ->and($event->revenue_minor)->toBe(45_900)
        ->and($event->currency)->toBe('MYR')
        ->and($event->properties['transaction_id'] ?? null)->toBe('txn-1001');
});

it('deduplicates separately signed trusted retries by idempotency key', function (): void {
    createSecureSignalsProperty($this, 'trusted-idempotency-key');
    $secret = 'second-trusted-signals-secret';
    config()->set('signals.ingestion.trusted.secret', $secret);

    $payload = [
        'write_key' => 'trusted-idempotency-key',
        'event_name' => 'order.refunded',
        'event_category' => 'conversion',
        'idempotency_key' => 'refund-2002',
        'transaction_id' => 'refund-txn-2002',
        'revenue_minor' => 9_900,
        'currency' => 'MYR',
    ];

    $timestamp = time();
    postSignedSignalsOutcome($this, $payload, $secret, $timestamp)->assertAccepted();
    postSignedSignalsOutcome($this, $payload, $secret, $timestamp + 1)->assertAccepted();

    expect(SignalEvent::query()->withoutOwnerScope()->count())->toBe(1);
});
