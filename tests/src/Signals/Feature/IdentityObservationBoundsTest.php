<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\Signals\Actions\IdentifySignalIdentity;
use AIArmada\Signals\Actions\IngestSignalEvent;
use AIArmada\Signals\Models\SignalIdentity;
use AIArmada\Signals\Models\TrackedProperty;
use AIArmada\Signals\Services\RetentionReportService;
use AIArmada\Signals\Services\SignalsDashboardService;
use Carbon\CarbonImmutable;

uses(SignalsTestCase::class);

function sig03_property(string $slug): TrackedProperty
{
    return TrackedProperty::query()->create([
        'name' => 'Identity Bounds ' . $slug,
        'slug' => 'sig03-' . $slug,
        'write_key' => 'sig03-' . $slug,
    ]);
}

it('keeps identity observation bounds chronological when observations arrive out of order', function (): void {
    $property = sig03_property('out-of-order');
    $action = app(IdentifySignalIdentity::class);

    $action->handle($property, ['external_id' => 'bounds-customer', 'seen_at' => '2026-10-17 12:00:00']);
    $identity = $action->handle($property, ['external_id' => 'bounds-customer', 'seen_at' => '2026-10-01 12:00:00']);

    $fresh = $identity->fresh();

    expect($fresh->first_seen_at->toDateString())->toBe('2026-10-01')
        ->and($fresh->last_seen_at->toDateString())->toBe('2026-10-17');
});

it('extends identity bounds when newer observations arrive after older ones', function (): void {
    $property = sig03_property('in-order');
    $action = app(IdentifySignalIdentity::class);

    $action->handle($property, ['external_id' => 'bounds-ordered', 'seen_at' => '2026-10-01 12:00:00']);
    $identity = $action->handle($property, ['external_id' => 'bounds-ordered', 'seen_at' => '2026-10-17 12:00:00']);

    $fresh = $identity->fresh();

    expect($fresh->first_seen_at->toDateString())->toBe('2026-10-01')
        ->and($fresh->last_seen_at->toDateString())->toBe('2026-10-17');
});

it('keeps identity bounds stable for equal observations', function (): void {
    $property = sig03_property('equal');
    $action = app(IdentifySignalIdentity::class);

    $action->handle($property, ['external_id' => 'bounds-equal', 'seen_at' => '2026-10-10 08:00:00']);
    $action->handle($property, ['external_id' => 'bounds-equal', 'seen_at' => '2026-10-10 08:00:00']);
    $identity = $action->handle($property, ['external_id' => 'bounds-equal', 'seen_at' => '2026-10-10 08:00:00']);

    $fresh = $identity->fresh();

    expect($fresh->first_seen_at->toDateTimeString())->toBe('2026-10-10 08:00:00')
        ->and($fresh->last_seen_at->toDateTimeString())->toBe('2026-10-10 08:00:00');
});

it('uses event occurrence time for identity bounds during trusted ingestion', function (): void {
    $property = sig03_property('trusted');
    $ingest = app(IngestSignalEvent::class);

    $ingest->handle($property, [
        'event_name' => 'order.paid',
        'event_category' => 'conversion',
        'external_id' => 'bounds-trusted',
        'occurred_at' => '2026-10-17 09:00:00',
    ], trusted: true);

    $ingest->handle($property, [
        'event_name' => 'checkout.started',
        'event_category' => 'checkout',
        'external_id' => 'bounds-trusted',
        'occurred_at' => '2026-10-03 09:00:00',
    ], trusted: true);

    $identity = SignalIdentity::query()->withoutOwnerScope()
        ->where('tracked_property_id', $property->id)
        ->where('external_id', 'bounds-trusted')
        ->firstOrFail();

    expect($identity->first_seen_at->toDateString())->toBe('2026-10-03')
        ->and($identity->last_seen_at->toDateString())->toBe('2026-10-17');
});

it('keeps identity bounds chronological through the public identify endpoint', function (): void {
    $property = sig03_property('public');

    $this->postJson('/api/signals/collect/identify', [
        'write_key' => $property->write_key,
        'external_id' => 'bounds-public',
        'seen_at' => '2026-10-17 12:00:00',
    ])->assertAccepted();

    $this->postJson('/api/signals/collect/identify', [
        'write_key' => $property->write_key,
        'external_id' => 'bounds-public',
        'seen_at' => '2026-10-01 12:00:00',
    ])->assertAccepted();

    $identity = SignalIdentity::query()->withoutOwnerScope()
        ->where('tracked_property_id', $property->id)
        ->where('external_id', 'bounds-public')
        ->firstOrFail();

    expect($identity->first_seen_at->toDateString())->toBe('2026-10-01')
        ->and($identity->last_seen_at->toDateString())->toBe('2026-10-17');
});

it('keeps retention results based on the preserved earliest and latest observations', function (): void {
    $property = sig03_property('retention');
    $action = app(IdentifySignalIdentity::class);

    $action->handle($property, ['external_id' => 'bounds-retention', 'seen_at' => '2026-10-01 12:00:00']);
    $action->handle($property, ['external_id' => 'bounds-retention', 'seen_at' => '2026-10-17 12:00:00']);
    $action->handle($property, ['external_id' => 'bounds-retention', 'seen_at' => '2026-10-05 12:00:00']);

    $summary = app(RetentionReportService::class)->summary($property->id, '2026-10-01', '2026-10-31');

    expect($summary['identities'])->toBe(1)
        ->and(collect($summary['windows'])->firstWhere('days', 7)['retained'])->toBe(1);
});

it('keeps identities active in ranges anchored on the latest observation', function (): void {
    $property = sig03_property('activity');
    $action = app(IdentifySignalIdentity::class);

    $action->handle($property, ['external_id' => 'bounds-activity', 'seen_at' => '2026-10-01 12:00:00']);
    $action->handle($property, ['external_id' => 'bounds-activity', 'seen_at' => '2026-10-17 12:00:00']);
    $action->handle($property, ['external_id' => 'bounds-activity', 'seen_at' => '2026-10-05 12:00:00']);

    $summary = app(SignalsDashboardService::class)->summary(
        $property,
        CarbonImmutable::parse('2026-10-10 00:00:00'),
        CarbonImmutable::parse('2026-10-20 23:59:59'),
    );

    expect($summary['identities'])->toBe(1);
});
