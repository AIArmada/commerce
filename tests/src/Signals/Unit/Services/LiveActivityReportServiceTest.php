<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\Commerce\Tests\Support\OwnerResolvers\FixedOwnerResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\TrackedProperty;
use AIArmada\Signals\Services\LiveActivityReportService;
use Carbon\CarbonImmutable;

uses(SignalsTestCase::class);

it('builds an owner-safe live activity report', function (): void {
    /** @var User $ownerA */
    $ownerA = User::query()->create([
        'name' => 'Live Owner A',
        'email' => 'live-owner-a@signals.test',
        'password' => 'secret',
    ]);

    /** @var User $ownerB */
    $ownerB = User::query()->create([
        'name' => 'Live Owner B',
        'email' => 'live-owner-b@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $propertyA = TrackedProperty::query()->create([
        'name' => 'Owner A Live Property',
        'slug' => 'owner-a-live-property',
        'write_key' => 'owner-a-live-key',
    ]);
    $propertyA->assignOwner($ownerA)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $propertyB = TrackedProperty::query()->create([
        'name' => 'Owner B Live Property',
        'slug' => 'owner-b-live-property',
        'write_key' => 'owner-b-live-key',
    ]);
    $propertyB->assignOwner($ownerB)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $pageViewA = SignalEvent::query()->create([
        'tracked_property_id' => $propertyA->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 09:05:00'),
        'event_name' => 'page_view',
        'event_category' => 'page_view',
        'path' => '/home',
    ]);
    $pageViewA->assignOwner($ownerA)->save();

    $conversionA = SignalEvent::query()->create([
        'tracked_property_id' => $propertyA->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 09:10:00'),
        'event_name' => 'order.paid',
        'event_category' => 'conversion',
        'path' => '/checkout',
        'revenue_minor' => 25000,
    ]);
    $conversionA->assignOwner($ownerA)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $pageViewB = SignalEvent::query()->create([
        'tracked_property_id' => $propertyB->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 10:05:00'),
        'event_name' => 'page_view',
        'event_category' => 'page_view',
        'path' => '/other',
    ]);
    $pageViewB->assignOwner($ownerB)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $service = app(LiveActivityReportService::class);
    $summary = $service->summary($propertyA->id, '2026-03-10', '2026-03-10');
    $firstRow = $service->getTableQuery($propertyA->id, '2026-03-10', '2026-03-10')->first();

    expect($summary['events'])->toBe(2)
        ->and($summary['page_views'])->toBe(1)
        ->and($summary['conversions'])->toBe(1)
        ->and($summary['revenue_minor'])->toBe(25000)
        ->and($firstRow)->not()->toBeNull()
        ->and($firstRow?->tracked_property_id)->toBe($propertyA->id)
        ->and($firstRow?->event_name)->toBe('order.paid')
        ->and($service->getTrackedPropertyOptions())
        ->toBe([$propertyA->id => 'Owner A Live Property']);
});
