<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use AIArmada\Signals\Models\SignalAlertRule;
use AIArmada\Signals\Models\SignalDailyMetric;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\SignalGoal;
use AIArmada\Signals\Models\SignalIdentity;
use AIArmada\Signals\Models\SignalInteractionRule;
use AIArmada\Signals\Models\SignalSession;
use AIArmada\Signals\Models\TrackedProperty;
use AIArmada\Signals\Services\SignalAlertEvaluator;
use Carbon\CarbonImmutable;

uses(SignalsTestCase::class);

function createAlertStreamProperty(string $suffix): TrackedProperty
{
    /** @var User $owner */
    $owner = User::query()->create([
        'name' => 'Alert Stream Owner ' . $suffix,
        'email' => 'alert-stream-' . $suffix . '@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    return TrackedProperty::query()->create([
        'name' => 'Alert Stream Property ' . $suffix,
        'slug' => 'alert-stream-' . $suffix,
        'write_key' => 'alert-stream-' . $suffix,
    ]);
}

function createAlertStreamRule(TrackedProperty $property, string $metricKey, array $filters = []): SignalAlertRule
{
    return SignalAlertRule::query()->create([
        'tracked_property_id' => $property->id,
        'name' => 'Stream Rule ' . $metricKey,
        'slug' => 'stream-rule-' . $metricKey . '-' . uniqid(),
        'metric_key' => $metricKey,
        'operator' => '>=',
        'threshold' => 1,
        'timeframe_minutes' => 60,
        'cooldown_minutes' => 30,
        'severity' => 'warning',
        'event_filters' => $filters === [] ? null : $filters,
    ]);
}

function createAlertStreamEvent(TrackedProperty $property, string $name, string $category, ?array $properties = null): SignalEvent
{
    return SignalEvent::query()->create([
        'tracked_property_id' => $property->id,
        'occurred_at' => CarbonImmutable::now()->subMinutes(5),
        'event_name' => $name,
        'event_category' => $category,
        'properties' => $properties,
    ]);
}

it('evaluates property metrics through filtered streaming', function (): void {
    $property = createAlertStreamProperty('metrics');

    createAlertStreamEvent($property, 'cart.snapshot.synced', 'cart', ['cart_total_minor' => 12000]);
    createAlertStreamEvent($property, 'cart.snapshot.synced', 'cart', ['cart_total_minor' => 8000]);
    createAlertStreamEvent($property, 'cart.snapshot.synced', 'cart', ['cart_total_minor' => 50000]);

    $rule = createAlertStreamRule($property, 'property_sum:cart_total_minor', [
        'properties' => [
            ['key' => 'cart_total_minor', 'operator' => '<', 'value' => 20000],
        ],
    ]);

    $result = app(SignalAlertEvaluator::class)->evaluate($rule);

    expect($result['matched'])->toBeTrue()
        ->and($result['metric_value'])->toBe(20000.0);
});

it('evaluates conversion rates through filtered streaming', function (): void {
    $property = createAlertStreamProperty('rate');

    createAlertStreamEvent($property, 'page_view', 'page_view', ['channel' => 'web']);
    createAlertStreamEvent($property, 'page_view', 'page_view', ['channel' => 'web']);
    createAlertStreamEvent($property, 'conversion.completed', 'conversion', ['channel' => 'web']);
    createAlertStreamEvent($property, 'conversion.completed', 'conversion', ['channel' => 'app']);

    $rule = createAlertStreamRule($property, 'conversion_rate', [
        'properties' => ['channel' => 'web'],
    ]);

    $result = app(SignalAlertEvaluator::class)->evaluate($rule);

    expect($result['matched'])->toBeTrue()
        ->and($result['metric_value'])->toBe(50.0);
});

it('cascades tracked property deletion to telemetry records', function (): void {
    $property = createAlertStreamProperty('cascade');

    $identity = SignalIdentity::query()->create([
        'tracked_property_id' => $property->id,
        'external_id' => 'cascade-customer',
    ]);

    SignalSession::query()->create([
        'tracked_property_id' => $property->id,
        'signal_identity_id' => $identity->id,
        'session_identifier' => 'cascade-session',
        'started_at' => CarbonImmutable::now()->subHour(),
    ]);

    createAlertStreamEvent($property, 'page_view', 'page_view');

    SignalInteractionRule::query()->create([
        'tracked_property_id' => $property->id,
        'name' => 'Cascade Rule',
        'slug' => 'cascade-rule',
        'event_name' => 'custom.clicked',
    ]);

    SignalDailyMetric::query()->create([
        'tracked_property_id' => $property->id,
        'date' => '2026-03-10',
    ]);

    $goal = SignalGoal::query()->create([
        'tracked_property_id' => $property->id,
        'name' => 'Cascade Goal',
        'slug' => 'cascade-goal',
        'event_name' => 'conversion.completed',
    ]);

    $property->delete();

    expect(SignalIdentity::query()->withoutOwnerScope()->where('tracked_property_id', $property->id)->count())->toBe(0)
        ->and(SignalSession::query()->withoutOwnerScope()->where('tracked_property_id', $property->id)->count())->toBe(0)
        ->and(SignalEvent::query()->withoutOwnerScope()->where('tracked_property_id', $property->id)->count())->toBe(0)
        ->and(SignalInteractionRule::query()->withoutOwnerScope()->where('tracked_property_id', $property->id)->count())->toBe(0)
        ->and(SignalDailyMetric::query()->withoutOwnerScope()->where('tracked_property_id', $property->id)->count())->toBe(0)
        ->and($goal->fresh()?->tracked_property_id)->toBeNull();
});
