<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\Commerce\Tests\Support\OwnerResolvers\FixedOwnerResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Signals\Models\SavedSignalReport;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\SignalSegment;
use AIArmada\Signals\Models\SignalSession;
use AIArmada\Signals\Models\TrackedProperty;
use AIArmada\Signals\Services\JourneyReportService;
use Carbon\CarbonImmutable;

uses(SignalsTestCase::class);

it('builds an owner-safe journey report', function (): void {
    /** @var User $ownerA */
    $ownerA = User::query()->create([
        'name' => 'Journey Owner A',
        'email' => 'journey-owner-a@signals.test',
        'password' => 'secret',
    ]);

    /** @var User $ownerB */
    $ownerB = User::query()->create([
        'name' => 'Journey Owner B',
        'email' => 'journey-owner-b@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $propertyA = TrackedProperty::query()->create([
        'name' => 'Owner A Journey Property',
        'slug' => 'owner-a-journey-property',
        'write_key' => 'owner-a-journey-key',
    ]);
    $propertyA->assignOwner($ownerA)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $propertyB = TrackedProperty::query()->create([
        'name' => 'Owner B Journey Property',
        'slug' => 'owner-b-journey-property',
        'write_key' => 'owner-b-journey-key',
    ]);
    $propertyB->assignOwner($ownerB)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $sessionA1 = SignalSession::query()->create([
        'tracked_property_id' => $propertyA->id,
        'session_identifier' => 'journey-a-1',
        'started_at' => CarbonImmutable::parse('2026-03-10 09:00:00'),
        'duration_seconds' => 120,
        'entry_path' => '/landing',
        'exit_path' => '/checkout',
        'is_bounce' => false,
    ]);
    $sessionA1->assignOwner($ownerA)->save();

    $sessionA2 = SignalSession::query()->create([
        'tracked_property_id' => $propertyA->id,
        'session_identifier' => 'journey-a-2',
        'started_at' => CarbonImmutable::parse('2026-03-10 10:00:00'),
        'duration_seconds' => 60,
        'entry_path' => '/landing',
        'exit_path' => '/checkout',
        'is_bounce' => true,
    ]);
    $sessionA2->assignOwner($ownerA)->save();

    $sessionA3 = SignalSession::query()->create([
        'tracked_property_id' => $propertyA->id,
        'session_identifier' => 'journey-a-3',
        'started_at' => CarbonImmutable::parse('2026-03-10 11:00:00'),
        'duration_seconds' => 180,
        'entry_path' => '/pricing',
        'exit_path' => '/contact',
        'is_bounce' => false,
    ]);
    $sessionA3->assignOwner($ownerA)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $sessionB = SignalSession::query()->create([
        'tracked_property_id' => $propertyB->id,
        'session_identifier' => 'journey-b-1',
        'started_at' => CarbonImmutable::parse('2026-03-10 12:00:00'),
        'duration_seconds' => 30,
        'entry_path' => '/other',
        'exit_path' => '/other-exit',
        'is_bounce' => true,
    ]);
    $sessionB->assignOwner($ownerB)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $service = app(JourneyReportService::class);
    $summary = $service->summary($propertyA->id, '2026-03-10', '2026-03-10');
    $row = $service->getTableQuery($propertyA->id, '2026-03-10', '2026-03-10')
        ->where('entry_path', '/landing')
        ->where('exit_path', '/checkout')
        ->first();

    expect($summary['sessions'])->toBe(3)
        ->and($summary['unique_entry_paths'])->toBe(2)
        ->and($summary['unique_exit_paths'])->toBe(2)
        ->and($summary['bounced_sessions'])->toBe(1)
        ->and($summary['avg_duration_seconds'])->toBe(120.0)
        ->and($row)->not()->toBeNull()
        ->and($row?->tracked_property_id)->toBe($propertyA->id)
        ->and($row?->journey_entry_path)->toBe('/landing')
        ->and($row?->journey_exit_path)->toBe('/checkout')
        ->and((int) ($row?->sessions ?? 0))->toBe(2)
        ->and((int) ($row?->bounced_sessions ?? 0))->toBe(1)
        ->and(round((float) ($row?->avg_duration_seconds ?? 0), 2))->toBe(90.0)
        ->and($service->getTrackedPropertyOptions())
        ->toBe([$propertyA->id => 'Owner A Journey Property']);
});

it('applies signal segments to journey reports through session events', function (): void {
    /** @var User $owner */
    $owner = User::query()->create([
        'name' => 'Journey Segment Owner',
        'email' => 'journey-segment-owner@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $property = TrackedProperty::query()->create([
        'name' => 'Journey Segment Property',
        'slug' => 'journey-segment-property',
        'write_key' => 'journey-segment-key',
    ]);

    $landingSessionA = SignalSession::query()->create([
        'tracked_property_id' => $property->id,
        'session_identifier' => 'journey-segment-a',
        'started_at' => CarbonImmutable::parse('2026-03-10 09:00:00'),
        'duration_seconds' => 120,
        'entry_path' => '/landing',
        'exit_path' => '/checkout',
        'is_bounce' => false,
    ]);

    $landingSessionB = SignalSession::query()->create([
        'tracked_property_id' => $property->id,
        'session_identifier' => 'journey-segment-b',
        'started_at' => CarbonImmutable::parse('2026-03-10 10:00:00'),
        'duration_seconds' => 60,
        'entry_path' => '/landing',
        'exit_path' => '/checkout',
        'is_bounce' => true,
    ]);

    $pricingSession = SignalSession::query()->create([
        'tracked_property_id' => $property->id,
        'session_identifier' => 'journey-segment-c',
        'started_at' => CarbonImmutable::parse('2026-03-10 11:00:00'),
        'duration_seconds' => 180,
        'entry_path' => '/pricing',
        'exit_path' => '/contact',
        'is_bounce' => false,
    ]);

    foreach ([
        ['session' => $landingSessionA->id, 'path' => '/landing', 'occurred_at' => '2026-03-10 09:05:00'],
        ['session' => $landingSessionB->id, 'path' => '/landing', 'occurred_at' => '2026-03-10 10:05:00'],
        ['session' => $pricingSession->id, 'path' => '/pricing', 'occurred_at' => '2026-03-10 11:05:00'],
    ] as $eventData) {
        SignalEvent::query()->create([
            'tracked_property_id' => $property->id,
            'signal_session_id' => $eventData['session'],
            'occurred_at' => CarbonImmutable::parse($eventData['occurred_at']),
            'event_name' => 'page_view',
            'event_category' => 'page_view',
            'path' => $eventData['path'],
        ]);
    }

    $segment = SignalSegment::query()->create([
        'name' => 'Landing Sessions',
        'slug' => 'landing-sessions',
        'conditions' => [
            ['field' => 'path', 'operator' => 'equals', 'value' => '/landing'],
        ],
    ]);

    $service = app(JourneyReportService::class);
    $summary = $service->summary($property->id, '2026-03-10', '2026-03-10', $segment->id);
    $row = $service->getTableQuery($property->id, '2026-03-10', '2026-03-10', $segment->id)
        ->where('entry_path', '/landing')
        ->where('exit_path', '/checkout')
        ->first();

    expect($summary['sessions'])->toBe(2)
        ->and($summary['unique_entry_paths'])->toBe(1)
        ->and($summary['unique_exit_paths'])->toBe(1)
        ->and($summary['bounced_sessions'])->toBe(1)
        ->and($row)->not()->toBeNull()
        ->and((int) ($row?->sessions ?? 0))->toBe(2);
});

it('uses saved journey report breakdown dimensions', function (): void {
    /** @var User $owner */
    $owner = User::query()->create([
        'name' => 'Journey Breakdown Owner',
        'email' => 'journey-breakdown-owner@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $property = TrackedProperty::query()->create([
        'name' => 'Journey Breakdown Property',
        'slug' => 'journey-breakdown-property',
        'write_key' => 'journey-breakdown-key',
    ]);

    foreach ([
        ['id' => 'journey-breakdown-a', 'device_type' => 'mobile', 'started_at' => '2026-03-10 09:00:00'],
        ['id' => 'journey-breakdown-b', 'device_type' => 'desktop', 'started_at' => '2026-03-10 10:00:00'],
        ['id' => 'journey-breakdown-c', 'device_type' => 'mobile', 'started_at' => '2026-03-10 11:00:00'],
    ] as $sessionData) {
        SignalSession::query()->create([
            'tracked_property_id' => $property->id,
            'session_identifier' => $sessionData['id'],
            'started_at' => CarbonImmutable::parse($sessionData['started_at']),
            'duration_seconds' => 90,
            'entry_path' => '/landing',
            'exit_path' => '/checkout',
            'device_type' => $sessionData['device_type'],
            'is_bounce' => false,
        ]);
    }

    $savedReport = SavedSignalReport::query()->create([
        'tracked_property_id' => $property->id,
        'name' => 'Journey By Device',
        'slug' => 'journey-by-device',
        'report_type' => 'journeys',
        'settings' => [
            'breakdown_dimension' => 'device_type',
        ],
    ]);

    $service = app(JourneyReportService::class);
    $rows = $service->getTableQuery(null, '2026-03-10', '2026-03-10', null, $savedReport->id)
        ->orderBy('journey_breakdown_value')
        ->get();

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->journey_breakdown_label)->toBe('Device Type')
        ->and($rows[0]->journey_breakdown_value)->toBe('desktop')
        ->and((int) $rows[0]->sessions)->toBe(1)
        ->and($rows[1]->journey_breakdown_value)->toBe('mobile')
        ->and((int) $rows[1]->sessions)->toBe(2)
        ->and($service->getSavedReportOptions())
        ->toBe([$savedReport->id => 'Journey By Device']);
});
