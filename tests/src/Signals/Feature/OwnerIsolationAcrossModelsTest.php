<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Signals\Models\SavedSignalReport;
use AIArmada\Signals\Models\SignalAlertDelivery;
use AIArmada\Signals\Models\SignalAlertLog;
use AIArmada\Signals\Models\SignalAlertRule;
use AIArmada\Signals\Models\SignalDailyMetric;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\SignalGoal;
use AIArmada\Signals\Models\SignalIdentity;
use AIArmada\Signals\Models\SignalInteractionRule;
use AIArmada\Signals\Models\SignalSegment;
use AIArmada\Signals\Models\SignalSession;
use AIArmada\Signals\Models\TrackedProperty;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

uses(SignalsTestCase::class);

it('keeps telemetry rows isolated across the canonical HasOwner models', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Signals Isolation Owner A',
        'email' => 'signals-isolation-a@example.com',
        'password' => 'secret',
    ]);
    $ownerB = User::query()->create([
        'name' => 'Signals Isolation Owner B',
        'email' => 'signals-isolation-b@example.com',
        'password' => 'secret',
    ]);

    [$property, $identity, $session, $event, $dailyMetric, $segment, $goal, $interactionRule, $savedReport, $alertRule, $alertLog, $delivery] = OwnerContext::withOwner($ownerA, function (): array {
        $property = TrackedProperty::query()->create([
            'name' => 'Signals Isolation Property',
            'slug' => 'signals-isolation-' . Str::lower(Str::random(8)),
            'write_key' => Str::random(40),
            'type' => 'website',
            'timezone' => 'UTC',
            'currency' => 'MYR',
            'is_active' => true,
        ]);
        $identity = SignalIdentity::query()->create([
            'tracked_property_id' => $property->getKey(),
            'external_id' => 'signals-isolation-external',
            'anonymous_id' => 'signals-isolation-anonymous',
        ]);
        $session = SignalSession::query()->create([
            'tracked_property_id' => $property->getKey(),
            'signal_identity_id' => $identity->getKey(),
            'session_identifier' => 'signals-isolation-session',
            'started_at' => CarbonImmutable::now(),
        ]);
        $event = SignalEvent::query()->create([
            'tracked_property_id' => $property->getKey(),
            'signal_session_id' => $session->getKey(),
            'signal_identity_id' => $identity->getKey(),
            'occurred_at' => CarbonImmutable::now(),
            'event_name' => 'signals.isolation',
            'event_category' => 'custom',
        ]);
        $dailyMetric = SignalDailyMetric::query()->create([
            'tracked_property_id' => $property->getKey(),
            'date' => '2026-09-08',
        ]);
        $segment = SignalSegment::query()->create([
            'name' => 'Signals Isolation Segment',
            'slug' => 'signals-isolation-segment-' . Str::lower(Str::random(8)),
            'match_type' => 'all',
            'is_active' => true,
        ]);
        $goal = SignalGoal::query()->create([
            'tracked_property_id' => $property->getKey(),
            'name' => 'Signals Isolation Goal',
            'slug' => 'signals-isolation-goal-' . Str::lower(Str::random(8)),
            'goal_type' => 'conversion',
            'event_name' => 'signals.isolation',
            'is_active' => true,
        ]);
        $interactionRule = SignalInteractionRule::query()->create([
            'tracked_property_id' => $property->getKey(),
            'name' => 'Signals Isolation Interaction',
            'slug' => 'signals-isolation-interaction-' . Str::lower(Str::random(8)),
            'trigger_type' => 'click',
            'event_name' => 'signals.isolation',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $savedReport = SavedSignalReport::query()->create([
            'tracked_property_id' => $property->getKey(),
            'signal_segment_id' => $segment->getKey(),
            'name' => 'Signals Isolation Report',
            'slug' => 'signals-isolation-report-' . Str::lower(Str::random(8)),
            'report_type' => 'dashboard',
            'is_shared' => false,
            'is_active' => true,
        ]);
        $alertRule = SignalAlertRule::query()->create([
            'tracked_property_id' => $property->getKey(),
            'name' => 'Signals Isolation Alert',
            'slug' => 'signals-isolation-alert-' . Str::lower(Str::random(8)),
            'metric_key' => 'revenue',
            'operator' => 'greater_than',
            'threshold' => 100,
            'severity' => 'warning',
            'priority' => 1,
            'is_active' => true,
        ]);
        $alertLog = SignalAlertLog::query()->create([
            'signal_alert_rule_id' => $alertRule->getKey(),
            'tracked_property_id' => $property->getKey(),
            'metric_key' => 'revenue',
            'operator' => 'greater_than',
            'metric_value' => 150,
            'threshold_value' => 100,
            'severity' => 'warning',
            'title' => 'Signals Isolation Alert Log',
        ]);
        $delivery = SignalAlertDelivery::query()->create([
            'signal_alert_log_id' => $alertLog->getKey(),
            'channel' => 'email',
            'destination_key' => 'signals-isolation@example.com',
            'destination' => ['address' => 'signals-isolation@example.com'],
        ]);

        return [$property, $identity, $session, $event, $dailyMetric, $segment, $goal, $interactionRule, $savedReport, $alertRule, $alertLog, $delivery];
    });

    OwnerContext::withOwner($ownerB, function () use ($property, $identity, $session, $event, $dailyMetric, $segment, $goal, $interactionRule, $savedReport, $alertRule, $alertLog, $delivery): void {
        expect(TrackedProperty::query()->whereKey($property->getKey())->exists())->toBeFalse()
            ->and(SignalIdentity::query()->whereKey($identity->getKey())->exists())->toBeFalse()
            ->and(SignalSession::query()->whereKey($session->getKey())->exists())->toBeFalse()
            ->and(SignalEvent::query()->whereKey($event->getKey())->exists())->toBeFalse()
            ->and(SignalDailyMetric::query()->whereKey($dailyMetric->getKey())->exists())->toBeFalse()
            ->and(SignalSegment::query()->whereKey($segment->getKey())->exists())->toBeFalse()
            ->and(SignalGoal::query()->whereKey($goal->getKey())->exists())->toBeFalse()
            ->and(SignalInteractionRule::query()->whereKey($interactionRule->getKey())->exists())->toBeFalse()
            ->and(SavedSignalReport::query()->whereKey($savedReport->getKey())->exists())->toBeFalse()
            ->and(SignalAlertRule::query()->whereKey($alertRule->getKey())->exists())->toBeFalse()
            ->and(SignalAlertLog::query()->whereKey($alertLog->getKey())->exists())->toBeFalse()
            ->and(SignalAlertDelivery::query()->whereKey($delivery->getKey())->exists())->toBeFalse();
    });
});
