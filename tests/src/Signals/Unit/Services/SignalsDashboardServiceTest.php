<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\Commerce\Tests\Support\OwnerResolvers\FixedOwnerResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Signals\Models\SignalAlertLog;
use AIArmada\Signals\Models\SignalAlertRule;
use AIArmada\Signals\Models\SignalDailyMetric;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\SignalIdentity;
use AIArmada\Signals\Models\SignalSession;
use AIArmada\Signals\Models\TrackedProperty;
use AIArmada\Signals\Services\SignalsDashboardService;
use Carbon\CarbonImmutable;

uses(SignalsTestCase::class);

it('builds dashboard metrics and respects owner scoping', function (): void {
    /** @var User $ownerA */
    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a@signals.test',
        'password' => 'secret',
    ]);

    /** @var User $ownerB */
    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'owner-b@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $propertyA = TrackedProperty::query()->create([
        'name' => 'Storefront A',
        'slug' => 'storefront-a',
        'write_key' => 'write-key-a',
        'domain' => 'a.test',
    ]);
    $propertyA->assignOwner($ownerA)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $propertyB = TrackedProperty::query()->create([
        'name' => 'Storefront B',
        'slug' => 'storefront-b',
        'write_key' => 'write-key-b',
        'domain' => 'b.test',
    ]);
    $propertyB->assignOwner($ownerB)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $identityA = SignalIdentity::query()->create([
        'tracked_property_id' => $propertyA->id,
        'external_id' => 'customer-a',
        'last_seen_at' => CarbonImmutable::parse('2026-03-10 10:00:00'),
    ]);
    $identityA->assignOwner($ownerA)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $identityB = SignalIdentity::query()->create([
        'tracked_property_id' => $propertyB->id,
        'external_id' => 'customer-b',
        'last_seen_at' => CarbonImmutable::parse('2026-03-10 10:00:00'),
    ]);
    $identityB->assignOwner($ownerB)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $sessionA = SignalSession::query()->create([
        'tracked_property_id' => $propertyA->id,
        'signal_identity_id' => $identityA->id,
        'session_identifier' => 'session-a',
        'started_at' => CarbonImmutable::parse('2026-03-10 10:00:00'),
        'duration_seconds' => 180,
    ]);
    $sessionA->assignOwner($ownerA)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $sessionB = SignalSession::query()->create([
        'tracked_property_id' => $propertyB->id,
        'signal_identity_id' => $identityB->id,
        'session_identifier' => 'session-b',
        'started_at' => CarbonImmutable::parse('2026-03-10 10:00:00'),
        'duration_seconds' => 120,
    ]);
    $sessionB->assignOwner($ownerB)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $pageView = SignalEvent::query()->create([
        'tracked_property_id' => $propertyA->id,
        'signal_session_id' => $sessionA->id,
        'signal_identity_id' => $identityA->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 10:05:00'),
        'event_name' => 'page_view',
        'event_category' => 'page_view',
        'path' => '/products',
    ]);
    $pageView->assignOwner($ownerA)->save();

    $conversion = SignalEvent::query()->create([
        'tracked_property_id' => $propertyA->id,
        'signal_session_id' => $sessionA->id,
        'signal_identity_id' => $identityA->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 10:10:00'),
        'event_name' => 'purchase_completed',
        'event_category' => 'conversion',
        'revenue_minor' => 129900,
    ]);
    $conversion->assignOwner($ownerA)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $otherOwnerEvent = SignalEvent::query()->create([
        'tracked_property_id' => $propertyB->id,
        'signal_session_id' => $sessionB->id,
        'signal_identity_id' => $identityB->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 10:10:00'),
        'event_name' => 'page_view',
        'event_category' => 'page_view',
    ]);
    $otherOwnerEvent->assignOwner($ownerB)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $metricA = SignalDailyMetric::query()->create([
        'tracked_property_id' => $propertyA->id,
        'date' => '2026-03-10',
        'unique_identities' => 1,
        'sessions' => 1,
        'bounced_sessions' => 0,
        'page_views' => 1,
        'events' => 2,
        'conversions' => 1,
        'revenue_minor' => 129900,
    ]);
    $metricA->assignOwner($ownerA)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $metricB = SignalDailyMetric::query()->create([
        'tracked_property_id' => $propertyB->id,
        'date' => '2026-03-10',
        'unique_identities' => 1,
        'sessions' => 1,
        'bounced_sessions' => 0,
        'page_views' => 1,
        'events' => 1,
        'conversions' => 0,
        'revenue_minor' => 0,
    ]);
    $metricB->assignOwner($ownerB)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $ruleA = SignalAlertRule::query()->create([
        'tracked_property_id' => $propertyA->id,
        'name' => 'Revenue spike',
        'slug' => 'revenue-spike',
        'metric_key' => 'revenue_minor',
        'operator' => 'gte',
        'threshold' => 1000,
        'timeframe_minutes' => 60,
        'cooldown_minutes' => 15,
        'severity' => 'critical',
        'priority' => 90,
        'is_active' => true,
    ]);
    $ruleA->assignOwner($ownerA)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $ruleB = SignalAlertRule::query()->create([
        'tracked_property_id' => $propertyB->id,
        'name' => 'Other owner rule',
        'slug' => 'other-owner-rule',
        'metric_key' => 'events',
        'operator' => 'gte',
        'threshold' => 10,
        'timeframe_minutes' => 60,
        'cooldown_minutes' => 15,
        'severity' => 'warning',
        'priority' => 50,
        'is_active' => true,
    ]);
    $ruleB->assignOwner($ownerB)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $logA = SignalAlertLog::query()->create([
        'signal_alert_rule_id' => $ruleA->id,
        'tracked_property_id' => $propertyA->id,
        'metric_key' => 'revenue_minor',
        'operator' => 'gte',
        'metric_value' => 129900,
        'threshold_value' => 1000,
        'severity' => 'critical',
        'title' => 'Revenue threshold reached',
        'message' => 'Revenue exceeded the configured threshold.',
        'is_read' => false,
        'created_at' => CarbonImmutable::parse('2026-03-10 11:00:00'),
        'updated_at' => CarbonImmutable::parse('2026-03-10 11:00:00'),
    ]);
    $logA->assignOwner($ownerA)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $logB = SignalAlertLog::query()->create([
        'signal_alert_rule_id' => $ruleB->id,
        'tracked_property_id' => $propertyB->id,
        'metric_key' => 'events',
        'operator' => 'gte',
        'metric_value' => 20,
        'threshold_value' => 10,
        'severity' => 'warning',
        'title' => 'Other owner alert',
        'message' => 'This alert belongs to another owner.',
        'is_read' => false,
        'created_at' => CarbonImmutable::parse('2026-03-10 11:00:00'),
        'updated_at' => CarbonImmutable::parse('2026-03-10 11:00:00'),
    ]);
    $logB->assignOwner($ownerB)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $service = app(SignalsDashboardService::class);

    $summary = $service->summary(
        null,
        CarbonImmutable::parse('2026-03-10 00:00:00'),
        CarbonImmutable::parse('2026-03-10 23:59:59'),
    );

    expect($summary['tracked_properties'])->toBe(1)
        ->and($summary['active_alert_rules'])->toBe(1)
        ->and($summary['unread_alerts'])->toBe(1)
        ->and($summary['identities'])->toBe(1)
        ->and($summary['sessions'])->toBe(1)
        ->and($summary['events'])->toBe(2)
        ->and($summary['conversions'])->toBe(1)
        ->and($summary['revenue_minor'])->toBe(129900);

    $trend = $service->trend(
        null,
        CarbonImmutable::parse('2026-03-10 00:00:00'),
        CarbonImmutable::parse('2026-03-10 23:59:59'),
    );

    expect($trend)->toHaveCount(1)
        ->and($trend[0]['events'])->toBe(2)
        ->and($trend[0]['conversions'])->toBe(1)
        ->and($trend[0]['revenue_minor'])->toBe(129900);
});
