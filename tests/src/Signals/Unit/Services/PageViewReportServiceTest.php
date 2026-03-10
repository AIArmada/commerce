<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\Commerce\Tests\Support\OwnerResolvers\FixedOwnerResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\SignalSession;
use AIArmada\Signals\Models\TrackedProperty;
use AIArmada\Signals\Services\PageViewReportService;
use Carbon\CarbonImmutable;

uses(SignalsTestCase::class);

it('returns aggregated page views for the current owner only', function (): void {
    /** @var User $ownerA */
    $ownerA = User::query()->create([
        'name' => 'Report Owner A',
        'email' => 'report-owner-a@signals.test',
        'password' => 'secret',
    ]);

    /** @var User $ownerB */
    $ownerB = User::query()->create([
        'name' => 'Report Owner B',
        'email' => 'report-owner-b@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $propertyA = TrackedProperty::query()->create([
        'name' => 'Owner A Property',
        'slug' => 'owner-a-property-report',
        'write_key' => 'owner-a-property-report-key',
    ]);
    $propertyA->assignOwner($ownerA)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $propertyB = TrackedProperty::query()->create([
        'name' => 'Owner B Property',
        'slug' => 'owner-b-property-report',
        'write_key' => 'owner-b-property-report-key',
    ]);
    $propertyB->assignOwner($ownerB)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $sessionA1 = SignalSession::query()->create([
        'tracked_property_id' => $propertyA->id,
        'session_identifier' => 'report-session-a-1',
        'started_at' => CarbonImmutable::parse('2026-01-01 10:00:00'),
    ]);
    $sessionA1->assignOwner($ownerA)->save();

    $sessionA2 = SignalSession::query()->create([
        'tracked_property_id' => $propertyA->id,
        'session_identifier' => 'report-session-a-2',
        'started_at' => CarbonImmutable::parse('2026-01-01 11:00:00'),
    ]);
    $sessionA2->assignOwner($ownerA)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $sessionB = SignalSession::query()->create([
        'tracked_property_id' => $propertyB->id,
        'session_identifier' => 'report-session-b-1',
        'started_at' => CarbonImmutable::parse('2026-01-01 12:00:00'),
    ]);
    $sessionB->assignOwner($ownerB)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $eventA1 = SignalEvent::query()->create([
        'tracked_property_id' => $propertyA->id,
        'signal_session_id' => $sessionA1->id,
        'occurred_at' => CarbonImmutable::parse('2026-01-01 10:05:00'),
        'event_name' => 'page_view',
        'event_category' => 'page_view',
        'path' => '/pricing',
        'url' => 'https://owner-a.test/pricing',
    ]);
    $eventA1->assignOwner($ownerA)->save();

    $eventA2 = SignalEvent::query()->create([
        'tracked_property_id' => $propertyA->id,
        'signal_session_id' => $sessionA2->id,
        'occurred_at' => CarbonImmutable::parse('2026-01-01 11:15:00'),
        'event_name' => 'page_view',
        'event_category' => 'page_view',
        'path' => '/pricing',
        'url' => 'https://owner-a.test/pricing',
    ]);
    $eventA2->assignOwner($ownerA)->save();

    $customEvent = SignalEvent::query()->create([
        'tracked_property_id' => $propertyA->id,
        'signal_session_id' => $sessionA2->id,
        'occurred_at' => CarbonImmutable::parse('2026-01-01 11:30:00'),
        'event_name' => 'purchase_completed',
        'event_category' => 'conversion',
        'path' => '/pricing',
        'url' => 'https://owner-a.test/pricing',
    ]);
    $customEvent->assignOwner($ownerA)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $eventB = SignalEvent::query()->create([
        'tracked_property_id' => $propertyB->id,
        'signal_session_id' => $sessionB->id,
        'occurred_at' => CarbonImmutable::parse('2026-01-01 12:05:00'),
        'event_name' => 'page_view',
        'event_category' => 'page_view',
        'path' => '/pricing',
        'url' => 'https://owner-b.test/pricing',
    ]);
    $eventB->assignOwner($ownerB)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $result = app(PageViewReportService::class)
        ->getTableQuery()
        ->first();

    expect($result)->not()->toBeNull()
        ->and($result?->tracked_property_id)->toBe($propertyA->id)
        ->and($result?->page_path)->toBe('/pricing')
        ->and((int) ($result?->views ?? 0))->toBe(2)
        ->and((int) ($result?->visitors ?? 0))->toBe(2)
        ->and($result?->trackedProperty?->name)->toBe('Owner A Property')
        ->and(app(PageViewReportService::class)->getTrackedPropertyOptions())
        ->toBe([$propertyA->id => 'Owner A Property']);
});
