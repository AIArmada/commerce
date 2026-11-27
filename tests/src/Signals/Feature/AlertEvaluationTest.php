<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use AIArmada\Signals\Features\Alerts\Actions\EvaluateAlertRules;
use AIArmada\Signals\Features\Alerts\Models\SignalAlertRule;
use AIArmada\Signals\Features\Alerts\Queries\SignalAlertEvaluator;
use AIArmada\Signals\Features\Ingestion\Models\SignalEvent;
use AIArmada\Signals\Features\Ingestion\Models\SignalIdentity;
use AIArmada\Signals\Features\Ingestion\Models\SignalInteractionRule;
use AIArmada\Signals\Features\Ingestion\Models\SignalSession;
use AIArmada\Signals\Features\Ingestion\Models\TrackedProperty;
use AIArmada\Signals\Features\Reporting\Models\SignalDailyMetric;
use AIArmada\Signals\Features\Reporting\Models\SignalGoal;
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

it('honors numeric not-equals and numeric-string contains filters', function (): void {
    $property = createAlertStreamProperty('operators');

    createAlertStreamEvent($property, 'cart.snapshot.synced', 'cart', ['cart_total_minor' => 1000, 'reference' => '12345']);
    createAlertStreamEvent($property, 'cart.snapshot.synced', 'cart', ['cart_total_minor' => 500, 'reference' => '99999']);

    $notEquals = createAlertStreamRule($property, 'events', [
        'properties' => [
            ['key' => 'cart_total_minor', 'operator' => 'not_eq', 'value' => '500'],
        ],
    ]);
    $contains = createAlertStreamRule($property, 'events', [
        'properties' => [
            ['key' => 'reference', 'operator' => 'contains', 'value' => '23'],
        ],
    ]);

    expect(app(SignalAlertEvaluator::class)->evaluate($notEquals)['metric_value'])->toBe(1.0)
        ->and(app(SignalAlertEvaluator::class)->evaluate($contains)['metric_value'])->toBe(1.0);
});

it('deactivates detached rules and preserves their revenue currency', function (): void {
    $property = createAlertStreamProperty('detach');
    $owner = OwnerContext::resolve();
    $revenueRule = createAlertStreamRule($property, 'revenue_minor');
    $eventsRule = createAlertStreamRule($property, 'events');

    OwnerContext::withOwner($owner, fn (): bool => $property->delete());

    $revenueRule->refresh();
    $eventsRule->refresh();

    expect($revenueRule->tracked_property_id)->toBeNull()
        ->and($revenueRule->is_active)->toBeFalse()
        ->and($revenueRule->event_filters['currency'] ?? null)->toBe('MYR')
        ->and($revenueRule->revenueCurrency())->toBe('MYR')
        ->and($eventsRule->tracked_property_id)->toBeNull()
        ->and($eventsRule->is_active)->toBeFalse()
        ->and($revenueRule->save())->toBeTrue();
});

it('skips rules whose property disappeared during evaluation', function (): void {
    $property = createAlertStreamProperty('concurrent-delete');
    createAlertStreamRule($property, 'revenue_minor');
    TrackedProperty::query()->withoutOwnerScope()->whereKey($property->id)->delete();

    $result = app(EvaluateAlertRules::class)->handle();

    expect($result)->toMatchArray(['processed' => 0, 'skipped' => 1, 'dispatched' => 0]);
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
