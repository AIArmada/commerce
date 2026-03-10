<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\Commerce\Tests\Support\OwnerResolvers\FixedOwnerResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Signals\Models\SavedSignalReport;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\SignalSession;
use AIArmada\Signals\Models\TrackedProperty;
use AIArmada\Signals\Services\ContentPerformanceReportService;
use Carbon\CarbonImmutable;

uses(SignalsTestCase::class);

it('builds an owner-safe content performance report', function (): void {
    /** @var User $ownerA */
    $ownerA = User::query()->create([
        'name' => 'Content Owner A',
        'email' => 'content-owner-a@signals.test',
        'password' => 'secret',
    ]);

    /** @var User $ownerB */
    $ownerB = User::query()->create([
        'name' => 'Content Owner B',
        'email' => 'content-owner-b@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $propertyA = TrackedProperty::query()->create([
        'name' => 'Owner A Content Property',
        'slug' => 'owner-a-content-property',
        'write_key' => 'owner-a-content-key',
    ]);
    $propertyA->assignOwner($ownerA)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $propertyB = TrackedProperty::query()->create([
        'name' => 'Owner B Content Property',
        'slug' => 'owner-b-content-property',
        'write_key' => 'owner-b-content-key',
    ]);
    $propertyB->assignOwner($ownerB)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $sessionA1 = SignalSession::query()->create([
        'tracked_property_id' => $propertyA->id,
        'session_identifier' => 'content-a-1',
        'started_at' => CarbonImmutable::parse('2026-03-10 09:00:00'),
    ]);
    $sessionA1->assignOwner($ownerA)->save();

    $sessionA2 = SignalSession::query()->create([
        'tracked_property_id' => $propertyA->id,
        'session_identifier' => 'content-a-2',
        'started_at' => CarbonImmutable::parse('2026-03-10 10:00:00'),
    ]);
    $sessionA2->assignOwner($ownerA)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $sessionB = SignalSession::query()->create([
        'tracked_property_id' => $propertyB->id,
        'session_identifier' => 'content-b-1',
        'started_at' => CarbonImmutable::parse('2026-03-10 11:00:00'),
    ]);
    $sessionB->assignOwner($ownerB)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $viewA1 = SignalEvent::query()->create([
        'tracked_property_id' => $propertyA->id,
        'signal_session_id' => $sessionA1->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 09:05:00'),
        'event_name' => 'page_view',
        'event_category' => 'page_view',
        'path' => '/pricing',
        'url' => 'https://owner-a.test/pricing',
    ]);
    $viewA1->assignOwner($ownerA)->save();

    $viewA2 = SignalEvent::query()->create([
        'tracked_property_id' => $propertyA->id,
        'signal_session_id' => $sessionA2->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 10:05:00'),
        'event_name' => 'page_view',
        'event_category' => 'page_view',
        'path' => '/pricing',
        'url' => 'https://owner-a.test/pricing',
    ]);
    $viewA2->assignOwner($ownerA)->save();

    $conversionA = SignalEvent::query()->create([
        'tracked_property_id' => $propertyA->id,
        'signal_session_id' => $sessionA2->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 10:10:00'),
        'event_name' => 'order.paid',
        'event_category' => 'conversion',
        'path' => '/pricing',
        'url' => 'https://owner-a.test/pricing',
        'revenue_minor' => 12000,
    ]);
    $conversionA->assignOwner($ownerA)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $viewB = SignalEvent::query()->create([
        'tracked_property_id' => $propertyB->id,
        'signal_session_id' => $sessionB->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 11:05:00'),
        'event_name' => 'page_view',
        'event_category' => 'page_view',
        'path' => '/pricing',
        'url' => 'https://owner-b.test/pricing',
    ]);
    $viewB->assignOwner($ownerB)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $service = app(ContentPerformanceReportService::class);
    $summary = $service->summary($propertyA->id, '2026-03-10', '2026-03-10');
    $rows = $service->rows($propertyA->id, '2026-03-10', '2026-03-10');

    expect($summary['paths'])->toBe(1)
        ->and($summary['views'])->toBe(2)
        ->and($summary['conversions'])->toBe(1)
        ->and($summary['revenue_minor'])->toBe(12000)
        ->and($summary['avg_conversion_rate'])->toBe(50.0)
        ->and($rows)->toHaveCount(1)
        ->and($rows[0]['content_path'])->toBe('/pricing')
        ->and($rows[0]['views'])->toBe(2)
        ->and($rows[0]['conversions'])->toBe(1)
        ->and($rows[0]['revenue_minor'])->toBe(12000)
        ->and($rows[0]['conversion_rate'])->toBe(50.0)
        ->and($service->getTrackedPropertyOptions())
        ->toBe([$propertyA->id => 'Owner A Content Property']);
});

it('uses saved content report breakdown dimensions', function (): void {
    /** @var User $owner */
    $owner = User::query()->create([
        'name' => 'Content Breakdown Owner',
        'email' => 'content-breakdown-owner@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $property = TrackedProperty::query()->create([
        'name' => 'Content Breakdown Property',
        'slug' => 'content-breakdown-property',
        'write_key' => 'content-breakdown-key',
    ]);

    $session = SignalSession::query()->create([
        'tracked_property_id' => $property->id,
        'session_identifier' => 'content-breakdown-session',
        'started_at' => CarbonImmutable::parse('2026-03-10 09:00:00'),
    ]);

    foreach ([
        ['source' => 'google', 'occurred_at' => '2026-03-10 09:05:00', 'event_name' => 'page_view', 'event_category' => 'page_view', 'revenue_minor' => 0],
        ['source' => 'google', 'occurred_at' => '2026-03-10 09:10:00', 'event_name' => 'order.paid', 'event_category' => 'conversion', 'revenue_minor' => 5000],
        ['source' => 'newsletter', 'occurred_at' => '2026-03-10 09:15:00', 'event_name' => 'page_view', 'event_category' => 'page_view', 'revenue_minor' => 0],
    ] as $eventData) {
        SignalEvent::query()->create([
            'tracked_property_id' => $property->id,
            'signal_session_id' => $session->id,
            'occurred_at' => CarbonImmutable::parse($eventData['occurred_at']),
            'event_name' => $eventData['event_name'],
            'event_category' => $eventData['event_category'],
            'path' => '/pricing',
            'url' => 'https://content-breakdown.test/pricing',
            'source' => $eventData['source'],
            'revenue_minor' => $eventData['revenue_minor'],
        ]);
    }

    $savedReport = SavedSignalReport::query()->create([
        'tracked_property_id' => $property->id,
        'name' => 'Content By Source',
        'slug' => 'content-by-source',
        'report_type' => 'content_performance',
        'settings' => [
            'breakdown_dimension' => 'source',
        ],
    ]);

    $service = app(ContentPerformanceReportService::class);
    $rows = $service->rows(null, '2026-03-10', '2026-03-10', null, $savedReport->id);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['content_path'])->toBe('/pricing')
        ->and($service->getSavedReportOptions())
        ->toBe([$savedReport->id => 'Content By Source']);

    $queryRows = $service->getTableQuery(null, '2026-03-10', '2026-03-10', null, $savedReport->id)
        ->orderBy('content_breakdown_value')
        ->get();

    expect($queryRows[0]->content_breakdown_label)->toBe('Source')
        ->and($queryRows[0]->content_breakdown_value)->toBe('google')
        ->and((int) $queryRows[0]->views)->toBe(1)
        ->and((int) $queryRows[0]->conversions)->toBe(1)
        ->and($queryRows[1]->content_breakdown_value)->toBe('newsletter')
        ->and((int) $queryRows[1]->views)->toBe(1);
});
