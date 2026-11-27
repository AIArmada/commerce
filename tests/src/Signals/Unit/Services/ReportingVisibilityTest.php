<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use AIArmada\Signals\Features\Alerts\Models\SignalAlertLog;
use AIArmada\Signals\Features\Alerts\Models\SignalAlertRule;
use AIArmada\Signals\Features\Ingestion\Api\TelemetryRead;
use AIArmada\Signals\Features\Ingestion\Models\TrackedProperty;
use AIArmada\Signals\Features\Reporting\Api\DashboardRead;
use AIArmada\Signals\Features\Reporting\Api\ReportConfiguration;
use AIArmada\Signals\Features\Reporting\Api\SegmentOptions;
use AIArmada\Signals\Features\Reporting\Models\SignalDailyMetric;
use AIArmada\Signals\Features\Reporting\Models\SignalGoal;
use AIArmada\Signals\Features\Reporting\Models\SignalSegment;
use Carbon\CarbonImmutable;

uses(SignalsTestCase::class);

it('keeps filter options tenant-only while included global metrics remain visible to the dashboard', function (): void {
    config()->set('signals.owner.include_global', true);

    $owner = User::query()->create([
        'name' => 'Visibility Owner',
        'email' => 'visibility-owner@signals.test',
        'password' => 'secret',
    ]);
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    [$tenantProperty, $tenantSegment, $tenantGoal, $tenantRule, $tenantLog] = OwnerContext::withOwner($owner, function (): array {
        $property = TrackedProperty::query()->create([
            'name' => 'Tenant Property',
            'slug' => 'visibility-tenant-property',
            'write_key' => 'visibility-tenant-key',
        ]);
        $segment = SignalSegment::query()->create([
            'name' => 'Tenant Segment',
            'slug' => 'visibility-tenant-segment',
        ]);
        $goal = SignalGoal::query()->create([
            'name' => 'Tenant Shared Goal',
            'slug' => 'visibility-shared-goal',
            'goal_type' => 'conversion',
            'event_name' => 'conversion.completed',
        ]);
        $rule = SignalAlertRule::query()->create([
            'name' => 'Tenant Rule',
            'slug' => 'visibility-tenant-rule',
            'metric_key' => 'events',
            'operator' => '>=',
            'threshold' => 1,
        ]);
        $log = SignalAlertLog::query()->create([
            'signal_alert_rule_id' => $rule->id,
            'metric_key' => 'events',
            'operator' => '>=',
            'metric_value' => 2,
            'threshold_value' => 1,
            'severity' => 'warning',
            'title' => 'Tenant Alert',
        ]);
        SignalDailyMetric::query()->create([
            'tracked_property_id' => $property->id,
            'date' => '2026-10-17',
            'events' => 3,
        ]);

        return [$property, $segment, $goal, $rule, $log];
    });

    [$globalProperty, $globalSegment, $globalGoal, $globalRule, $globalLog] = OwnerContext::withOwner(null, function (): array {
        $property = TrackedProperty::query()->create([
            'name' => 'Global Property',
            'slug' => 'visibility-global-property',
            'write_key' => 'visibility-global-key',
        ]);
        $segment = SignalSegment::query()->create([
            'name' => 'Global Segment',
            'slug' => 'visibility-global-segment',
        ]);
        $goal = SignalGoal::query()->create([
            'name' => 'Global Shared Goal',
            'slug' => 'visibility-shared-goal',
            'goal_type' => 'conversion',
            'event_name' => 'conversion.completed',
        ]);
        $rule = SignalAlertRule::query()->create([
            'name' => 'Global Rule',
            'slug' => 'visibility-global-rule',
            'metric_key' => 'events',
            'operator' => '>=',
            'threshold' => 1,
        ]);
        $log = SignalAlertLog::query()->create([
            'signal_alert_rule_id' => $rule->id,
            'metric_key' => 'events',
            'operator' => '>=',
            'metric_value' => 2,
            'threshold_value' => 1,
            'severity' => 'warning',
            'title' => 'Global Alert',
        ]);
        SignalDailyMetric::query()->create([
            'tracked_property_id' => $property->id,
            'date' => '2026-10-17',
            'events' => 5,
        ]);

        return [$property, $segment, $goal, $rule, $log];
    });

    expect(app(TelemetryRead::class)->propertyOptions())->toBe([$tenantProperty->id => 'Tenant Property'])
        ->and(app(SegmentOptions::class)->getSegmentOptions())->toBe([$tenantSegment->id => 'Tenant Segment'])
        ->and(app(ReportConfiguration::class)->goalsBySlug(null, ['visibility-shared-goal'])['visibility-shared-goal']->name)->toBe('Tenant Shared Goal');

    $summary = app(DashboardRead::class)->summary(
        null,
        CarbonImmutable::parse('2026-10-17 00:00:00'),
        CarbonImmutable::parse('2026-10-17 23:59:59'),
    );

    expect($summary['tracked_properties'])->toBe(2)
        ->and($summary['events'])->toBe(8)
        ->and($summary['active_alert_rules'])->toBe(2)
        ->and($summary['unread_alerts'])->toBe(2);

    expect(SignalGoal::query()->whereKey($globalGoal->id)->exists())->toBeTrue()
        ->and(SignalSegment::query()->whereKey($globalSegment->id)->exists())->toBeTrue()
        ->and(SignalAlertRule::query()->whereKey($globalRule->id)->exists())->toBeTrue()
        ->and(SignalAlertLog::query()->whereKey($globalLog->id)->exists())->toBeTrue()
        ->and($tenantGoal->exists)->toBeTrue()
        ->and($tenantRule->exists)->toBeTrue()
        ->and($tenantLog->exists)->toBeTrue();
});
