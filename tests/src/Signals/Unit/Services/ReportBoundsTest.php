<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\SignalIdentity;
use AIArmada\Signals\Models\TrackedProperty;
use AIArmada\Signals\Services\ConversionFunnelReportService;
use AIArmada\Signals\Services\RetentionReportService;
use Carbon\CarbonImmutable;

uses(SignalsTestCase::class);

function createBoundedReportProperty(string $suffix): TrackedProperty
{
    /** @var User $owner */
    $owner = User::query()->create([
        'name' => 'Bounded Report Owner ' . $suffix,
        'email' => 'bounded-report-' . $suffix . '@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    return TrackedProperty::query()->create([
        'name' => 'Bounded Report Property ' . $suffix,
        'slug' => 'bounded-report-' . $suffix,
        'write_key' => 'bounded-report-' . $suffix,
    ]);
}

function createBoundedFunnelJourney(TrackedProperty $property, SignalIdentity $identity, string $occurredAt): void
{
    $steps = [
        ['page_view', 'page_view', 0],
        ['page_view', 'page_view', 5],
        ['conversion.completed', 'conversion', 10],
    ];

    foreach ($steps as [$eventName, $category, $offsetMinutes]) {
        SignalEvent::query()->create([
            'tracked_property_id' => $property->id,
            'signal_identity_id' => $identity->id,
            'occurred_at' => CarbonImmutable::parse($occurredAt)->addMinutes($offsetMinutes),
            'event_name' => $eventName,
            'event_category' => $category,
            'revenue_minor' => $category === 'conversion' ? 5000 : 0,
        ]);
    }
}

it('applies a default window to unbounded funnel reports', function (): void {
    $property = createBoundedReportProperty('funnel-window');

    $recentIdentity = SignalIdentity::query()->create([
        'tracked_property_id' => $property->id,
        'external_id' => 'recent-funnel-visitor',
    ]);
    createBoundedFunnelJourney($property, $recentIdentity, CarbonImmutable::now()->subDay()->toDateTimeString());

    $staleIdentity = SignalIdentity::query()->create([
        'tracked_property_id' => $property->id,
        'external_id' => 'stale-funnel-visitor',
    ]);
    createBoundedFunnelJourney($property, $staleIdentity, CarbonImmutable::now()->subDays(120)->toDateTimeString());

    $summary = app(ConversionFunnelReportService::class)->summary($property->id);

    expect($summary['started'])->toBe(1)
        ->and($summary['paid'])->toBe(1)
        ->and($summary['revenue_minor'])->toBe(5000);
});

it('caps the events scanned by funnel reports', function (): void {
    $property = createBoundedReportProperty('funnel-cap');
    config()->set('signals.reporting.funnel.max_events', 2);

    $identity = SignalIdentity::query()->create([
        'tracked_property_id' => $property->id,
        'external_id' => 'capped-funnel-visitor',
    ]);
    createBoundedFunnelJourney($property, $identity, '2026-03-10 10:00:00');

    $stages = app(ConversionFunnelReportService::class)->stages($property->id, '2026-03-10', '2026-03-10');

    expect($stages[0]['count'])->toBe(1)
        ->and($stages[1]['count'])->toBe(1)
        ->and($stages[2]['count'])->toBe(0);
});

it('applies a default window to unbounded retention reports', function (): void {
    $property = createBoundedReportProperty('retention-window');

    SignalIdentity::query()->create([
        'tracked_property_id' => $property->id,
        'external_id' => 'recent-retained-visitor',
        'first_seen_at' => CarbonImmutable::now()->subDay(),
        'last_seen_at' => CarbonImmutable::now(),
    ]);

    SignalIdentity::query()->create([
        'tracked_property_id' => $property->id,
        'external_id' => 'stale-retained-visitor',
        'first_seen_at' => CarbonImmutable::now()->subDays(120),
        'last_seen_at' => CarbonImmutable::now()->subDays(119),
    ]);

    $summary = app(RetentionReportService::class)->summary($property->id);

    expect($summary['cohorts'])->toBe(1)
        ->and($summary['identities'])->toBe(1);
});

it('caps the identities scanned by retention reports', function (): void {
    $property = createBoundedReportProperty('retention-cap');
    config()->set('signals.reporting.retention.max_identities', 1);

    SignalIdentity::query()->create([
        'tracked_property_id' => $property->id,
        'external_id' => 'capped-visitor-one',
        'first_seen_at' => CarbonImmutable::parse('2026-01-05 10:00:00'),
        'last_seen_at' => CarbonImmutable::parse('2026-01-06 10:00:00'),
    ]);

    SignalIdentity::query()->create([
        'tracked_property_id' => $property->id,
        'external_id' => 'capped-visitor-two',
        'first_seen_at' => CarbonImmutable::parse('2026-01-06 10:00:00'),
        'last_seen_at' => CarbonImmutable::parse('2026-01-07 10:00:00'),
    ]);

    $summary = app(RetentionReportService::class)->summary($property->id, '2026-01-01', '2026-01-31');

    expect($summary['cohorts'])->toBe(1)
        ->and($summary['identities'])->toBe(1);
});
