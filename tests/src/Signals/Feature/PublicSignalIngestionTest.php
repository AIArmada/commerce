<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Signals\Features\Ingestion\Api\SignalIngestion;
use AIArmada\Signals\Features\Ingestion\Models\SignalEvent;
use AIArmada\Signals\Features\Ingestion\Models\TrackedProperty;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(SignalsTestCase::class);

function publicIngestionProperty(): TrackedProperty
{
    return TrackedProperty::query()->create([
        'name' => 'Public Ingestion',
        'slug' => 'public-ingestion',
        'write_key' => 'public-ingestion-write-key',
    ]);
}

it('ingests a trusted event through the public api and returns an immutable receipt', function (): void {
    $property = publicIngestionProperty();

    $event = app(SignalIngestion::class)->handle($property->id, [
        'event_name' => 'test.public_ingestion',
        'event_category' => 'test',
        'occurred_at' => '2026-05-10T08:00:00Z',
        'idempotency_key' => 'test:public-ingestion',
        'properties' => ['source' => 'api'],
    ], true);

    expect($event->id)->not->toBe('')
        ->and($event->trackedPropertyId)->toBe($property->id)
        ->and($event->eventName)->toBe('test.public_ingestion')
        ->and($event->eventCategory)->toBe('test')
        ->and($event->occurredAt->toIso8601String())->toBe('2026-05-10T08:00:00+00:00')
        ->and($event->idempotencyKey)->toBe('test:public-ingestion')
        ->and($event->properties)->toBe(['source' => 'api'])
        ->and($event->revenueMinor)->toBe(0)
        ->and($event->currency)->toBeString();

    expect(SignalEvent::query()->whereKey($event->id)->exists())->toBeTrue();
});

it('returns the same receipt when the same idempotency key is ingested twice', function (): void {
    $property = publicIngestionProperty();

    $payload = [
        'event_name' => 'test.public_ingestion',
        'event_category' => 'test',
        'idempotency_key' => 'test:replay',
        'properties' => [],
    ];

    $first = app(SignalIngestion::class)->handle($property->id, $payload, true);
    $second = app(SignalIngestion::class)->handle($property->id, $payload, true);

    expect($second->id)->toBe($first->id)
        ->and(SignalEvent::query()->count())->toBe(1);
});

it('rejects an unknown tracked property', function (): void {
    expect(fn () => app(SignalIngestion::class)->handle((string) Str::uuid(), [
        'event_name' => 'test.public_ingestion',
        'event_category' => 'test',
        'properties' => [],
    ], true))->toThrow(AuthorizationException::class);
});

it('rejects a malformed tracked property id', function (): void {
    expect(fn () => app(SignalIngestion::class)->handle('not-a-uuid', [
        'event_name' => 'test.public_ingestion',
        'event_category' => 'test',
        'properties' => [],
    ], true))->toThrow(InvalidArgumentException::class);
});

it('rejects a tracked property owned by another owner', function (): void {
    $otherOwner = User::query()->create([
        'name' => 'Other Owner',
        'email' => 'other-owner@signals.test',
        'password' => 'secret',
    ]);

    $property = OwnerContext::withOwner($otherOwner, fn (): TrackedProperty => TrackedProperty::query()->create([
        'name' => 'Other Property',
        'slug' => 'other-property',
        'write_key' => 'other-property-write-key',
    ]));

    expect(fn () => app(SignalIngestion::class)->handle($property->id, [
        'event_name' => 'test.public_ingestion',
        'event_category' => 'test',
        'properties' => [],
    ], true))->toThrow(AuthorizationException::class);
});

it('rejects inactive tracked properties before writing any events', function (): void {
    $property = publicIngestionProperty();
    $property->update(['is_active' => false]);

    expect(fn () => app(SignalIngestion::class)->handle($property->id, [
        'event_name' => 'test.public_ingestion',
    ], true))->toThrow(InvalidArgumentException::class);

    expect(SignalEvent::query()->count())->toBe(0);
});

it('rejects invalid event names before writing any events', function (array $payload): void {
    $property = publicIngestionProperty();

    expect(fn () => app(SignalIngestion::class)->handle($property->id, $payload, true))
        ->toThrow(InvalidArgumentException::class);

    expect(SignalEvent::query()->count())->toBe(0);
})->with([
    'missing' => [[]],
    'empty' => [['event_name' => '']],
    'whitespace' => [['event_name' => '   ']],
    'array' => [['event_name' => ['invalid']]],
    'too long' => [['event_name' => str_repeat('x', 256)]],
]);

it('applies browser event and payload policy at the direct untrusted boundary', function (): void {
    $property = publicIngestionProperty();
    $ingestion = app(SignalIngestion::class);

    foreach ([
        ['event_name' => 'order.paid'],
        ['event_name' => 'custom.click', 'properties' => ['orderId' => 'untrusted']],
        ['event_name' => 'custom.click', 'properties' => ['value' => str_repeat('x', 2048)]],
    ] as $payload) {
        expect(fn () => $ingestion->handle($property->id, $payload, false))
            ->toThrow(ValidationException::class);
    }

    expect(SignalEvent::query()->count())->toBe(0);
    expect($ingestion->handle($property->id, ['event_name' => 'custom.click'], false)->revenueMinor)->toBe(0);
});
