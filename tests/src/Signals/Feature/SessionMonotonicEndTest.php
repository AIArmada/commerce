<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\Signals\Actions\IngestSignalEvent;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\TrackedProperty;
use Illuminate\Support\Str;

uses(SignalsTestCase::class);

function monotonicSessionProperty(): TrackedProperty
{
    return TrackedProperty::query()->create([
        'name' => 'Monotonic Session',
        'slug' => 'monotonic-session-' . Str::lower(Str::random(6)),
        'type' => 'website',
        'currency' => 'MYR',
        'timezone' => 'UTC',
        'is_active' => true,
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function ingestMonotonicEvent(TrackedProperty $property, string $occurredAt, array $overrides = []): SignalEvent
{
    return app(IngestSignalEvent::class)->handle($property, array_merge([
        'event_name' => 'custom.monotonic',
        'session_identifier' => 'monotonic-session',
        'occurred_at' => $occurredAt,
        'path' => '/at-' . str_replace(':', '', mb_substr($occurredAt, 11, 5)),
    ], $overrides), trusted: false);
}

it('keeps the latest session end when events arrive out of order', function (): void {
    $property = monotonicSessionProperty();

    ingestMonotonicEvent($property, '2026-10-04T10:00:00Z');
    ingestMonotonicEvent($property, '2026-10-04T10:10:00Z');
    $late = ingestMonotonicEvent($property, '2026-10-04T10:05:00Z');

    $session = $late->session->refresh();

    expect($session->ended_at->toAtomString())->toBe('2026-10-04T10:10:00+00:00')
        ->and($session->duration_milliseconds)->toBe(600000)
        ->and($session->exit_path)->toBe('/at-1010');
});

it('ignores backdated events for session end while still recording them', function (): void {
    $property = monotonicSessionProperty();

    ingestMonotonicEvent($property, '2026-10-04T12:00:00Z');
    $backfill = ingestMonotonicEvent($property, '2026-10-03T09:00:00Z');

    $session = $backfill->session->refresh();

    expect(SignalEvent::query()->withoutOwnerScope()->where('tracked_property_id', $property->id)->count())->toBe(2)
        ->and($session->ended_at->toAtomString())->toBe('2026-10-04T12:00:00+00:00')
        ->and($session->exit_path)->toBe('/at-1200');
});

it('does not mutate the session when a retry deduplicates', function (): void {
    $property = monotonicSessionProperty();

    $first = ingestMonotonicEvent($property, '2026-10-04T10:00:00Z', [
        'idempotency_key' => 'monotonic-retry-1',
        'session_started_at' => '2026-10-04T10:00:00Z',
    ]);
    $before = $first->session->refresh()->getAttributes();

    $retry = ingestMonotonicEvent($property, '2026-10-04T10:20:00Z', [
        'idempotency_key' => 'monotonic-retry-1',
        'path' => '/retry-should-not-win',
    ]);

    $after = $retry->session->refresh()->getAttributes();

    expect($retry->id)->toBe($first->id)
        ->and($after['ended_at'])->toBe($before['ended_at'])
        ->and($after['duration_milliseconds'])->toBe($before['duration_milliseconds'])
        ->and($after['exit_path'])->toBe($before['exit_path']);
});

it('keeps bounce semantics with monotonic session ends', function (): void {
    $property = monotonicSessionProperty();

    $single = app(IngestSignalEvent::class)->handle($property, [
        'event_name' => 'custom.bounce',
        'session_identifier' => 'bounce-session',
        'occurred_at' => '2026-10-04T10:00:00Z',
        'path' => '/landing',
    ], trusted: false);

    expect($single->session->refresh()->bounced_at)->not->toBeNull();

    $second = app(IngestSignalEvent::class)->handle($property, [
        'event_name' => 'custom.bounce',
        'session_identifier' => 'bounce-session',
        'occurred_at' => '2026-10-04T10:05:00Z',
        'path' => '/second',
    ], trusted: false);

    $session = $second->session->refresh();

    expect($session->bounced_at)->toBeNull()
        ->and($session->ended_at->toAtomString())->toBe('2026-10-04T10:05:00+00:00')
        ->and($session->duration_milliseconds)->toBe(300000);
});
