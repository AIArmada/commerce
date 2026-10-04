<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\Signals\Actions\IngestSignalEvent;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\TrackedProperty;
use Illuminate\Testing\TestResponse;

uses(SignalsTestCase::class);

function boundaryTestProperty(string $slug): TrackedProperty
{
    return TrackedProperty::query()->create([
        'name' => 'Boundary ' . $slug,
        'slug' => 'boundary-' . $slug,
        'write_key' => 'boundary-write-' . $slug,
    ]);
}

/**
 * @param  array<string, mixed>  $payload
 */
function postBoundarySignedOutcome(object $test, array $payload, string $secret, ?int $timestamp = null): TestResponse
{
    $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    $timestamp ??= time();
    $signature = hash_hmac('sha256', $timestamp . '.' . $body, $secret);

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

it('keeps browser and trusted idempotency keys in separate namespaces', function (): void {
    $property = boundaryTestProperty('preempt');

    $this->postJson('/api/signals/collect/browser-event', [
        'write_key' => $property->write_key,
        'event_name' => 'custom.preempted',
        'idempotency_key' => 'known-outcome-1',
    ])->assertAccepted();

    $event = app(IngestSignalEvent::class)->handle($property, [
        'event_name' => 'order.paid',
        'event_category' => 'conversion',
        'idempotency_key' => 'known-outcome-1',
        'revenue_minor' => 10000,
    ], trusted: true);

    expect($event->event_name)->toBe('order.paid')
        ->and($event->revenue_minor)->toBe(10000)
        ->and($event->ingestion_source)->toBe('trusted')
        ->and(SignalEvent::query()->withoutOwnerScope()->where('tracked_property_id', $property->id)->count())->toBe(2);
});

it('does not let a trusted key suppress a later browser event', function (): void {
    $property = boundaryTestProperty('reverse');

    $trusted = app(IngestSignalEvent::class)->handle($property, [
        'event_name' => 'order.paid',
        'event_category' => 'conversion',
        'idempotency_key' => 'shared-key-9',
        'revenue_minor' => 5000,
    ], trusted: true);

    $this->postJson('/api/signals/collect/browser-event', [
        'write_key' => $property->write_key,
        'event_name' => 'custom.preempted',
        'idempotency_key' => 'shared-key-9',
    ])->assertAccepted();

    $events = SignalEvent::query()->withoutOwnerScope()->where('tracked_property_id', $property->id)->get();

    expect($events)->toHaveCount(2)
        ->and($trusted->ingestion_source)->toBe('trusted')
        ->and($events->where('ingestion_source', 'browser'))->toHaveCount(1);
});

it('deduplicates browser retries within the browser namespace', function (): void {
    $property = boundaryTestProperty('browser-retry');

    $payload = [
        'write_key' => $property->write_key,
        'event_name' => 'custom.retry',
        'session_identifier' => 'browser-retry-session',
        'idempotency_key' => 'browser-key-1',
        'path' => '/retry',
    ];

    $first = $this->postJson('/api/signals/collect/browser-event', $payload)->assertAccepted();
    $second = $this->postJson('/api/signals/collect/browser-event', $payload)->assertAccepted();

    expect($second->json('data.event_id'))->toBe($first->json('data.event_id'))
        ->and(SignalEvent::query()->withoutOwnerScope()->where('tracked_property_id', $property->id)->count())->toBe(1);
});

it('deduplicates trusted retries within the trusted namespace', function (): void {
    $property = boundaryTestProperty('trusted-retry');

    $payload = [
        'event_name' => 'order.paid',
        'event_category' => 'conversion',
        'idempotency_key' => 'trusted-key-1',
        'revenue_minor' => 10000,
    ];

    $first = app(IngestSignalEvent::class)->handle($property, $payload, trusted: true);
    $second = app(IngestSignalEvent::class)->handle($property, $payload, trusted: true);

    expect($second->id)->toBe($first->id)
        ->and(SignalEvent::query()->withoutOwnerScope()->where('tracked_property_id', $property->id)->count())->toBe(1);
});

it('deduplicates signed trusted HTTP retries without touching the browser namespace', function (): void {
    $property = boundaryTestProperty('signed-retry');
    $secret = 'boundary-signed-secret-with-enough-entropy';
    config()->set('signals.ingestion.trusted.secret', $secret);

    $payload = [
        'write_key' => $property->write_key,
        'event_name' => 'order.paid',
        'event_category' => 'conversion',
        'idempotency_key' => 'signed-key-1',
        'transaction_id' => 'txn-signed-1',
        'revenue_minor' => 10000,
        'currency' => 'MYR',
    ];

    $timestamp = time();
    postBoundarySignedOutcome($this, $payload, $secret, $timestamp)->assertAccepted();
    postBoundarySignedOutcome($this, $payload, $secret, $timestamp + 1)->assertAccepted();

    $this->postJson('/api/signals/collect/browser-event', [
        'write_key' => $property->write_key,
        'event_name' => 'custom.preempted',
        'idempotency_key' => 'signed-key-1',
    ])->assertAccepted();

    $events = SignalEvent::query()->withoutOwnerScope()->where('tracked_property_id', $property->id)->get();

    expect($events)->toHaveCount(2)
        ->and($events->where('ingestion_source', 'trusted'))->toHaveCount(1)
        ->and($events->where('ingestion_source', 'browser'))->toHaveCount(1);
});

it('stores the ingestion boundary on every ingested event', function (): void {
    $property = boundaryTestProperty('boundary-stored');

    $this->postJson('/api/signals/collect/browser-event', [
        'write_key' => $property->write_key,
        'event_name' => 'custom.boundary',
    ])->assertAccepted();

    $trusted = app(IngestSignalEvent::class)->handle($property, [
        'event_name' => 'order.paid',
        'event_category' => 'conversion',
    ], trusted: true);

    $browser = SignalEvent::query()->withoutOwnerScope()
        ->where('tracked_property_id', $property->id)
        ->where('event_name', 'custom.boundary')
        ->firstOrFail();

    expect($browser->ingestion_source)->toBe('browser')
        ->and($trusted->ingestion_source)->toBe('trusted');
});
