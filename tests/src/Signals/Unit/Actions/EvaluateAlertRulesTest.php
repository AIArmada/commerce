<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\Commerce\Tests\Support\OwnerResolvers\FixedOwnerResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Signals\Actions\EvaluateAlertRules;
use AIArmada\Signals\Models\SignalAlertLog;
use AIArmada\Signals\Models\SignalAlertRule;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\TrackedProperty;
use Carbon\CarbonImmutable;

uses(SignalsTestCase::class);

it('evaluates and dispatches matching alert rules', function (): void {
    $owner = app(OwnerResolverInterface::class)->resolve();
    expect($owner)->toBeInstanceOf(User::class);

    $property = TrackedProperty::query()->create([
        'name' => 'Test Property',
        'slug' => 'test-property',
        'write_key' => 'test-key',
    ]);

    $rule = SignalAlertRule::query()->create([
        'tracked_property_id' => $property->id,
        'name' => 'Test Rule',
        'slug' => 'test-rule',
        'metric_key' => 'events',
        'operator' => '>=',
        'threshold' => 1,
        'timeframe_minutes' => 60,
        'cooldown_minutes' => 30,
        'severity' => 'warning',
    ]);

    SignalEvent::query()->create([
        'tracked_property_id' => $property->id,
        'occurred_at' => CarbonImmutable::now()->subMinutes(5),
        'event_name' => 'page_view',
        'event_category' => 'page_view',
    ]);

    $action = app(EvaluateAlertRules::class);
    $result = $action->handle(trackedPropertyId: (string) $property->id);

    expect($result)->toHaveKey('processed', 1)
        ->and($result)->toHaveKey('skipped', 0)
        ->and($result)->toHaveKey('dispatched', 1)
        ->and(SignalAlertLog::query()->count())->toBe(1)
        ->and($rule->fresh()?->last_triggered_at)->not()->toBeNull();
});

it('skips rules in cooldown', function (): void {
    $property = TrackedProperty::query()->create([
        'name' => 'Cooldown Property',
        'slug' => 'cooldown-property',
        'write_key' => 'cooldown-key',
    ]);

    $rule = SignalAlertRule::query()->create([
        'tracked_property_id' => $property->id,
        'name' => 'Cooldown Rule',
        'slug' => 'cooldown-rule',
        'metric_key' => 'events',
        'operator' => '>=',
        'threshold' => 1,
        'timeframe_minutes' => 60,
        'cooldown_minutes' => 30,
        'severity' => 'warning',
        'last_triggered_at' => CarbonImmutable::now()->subMinutes(5),
    ]);

    SignalEvent::query()->create([
        'tracked_property_id' => $property->id,
        'occurred_at' => CarbonImmutable::now()->subMinutes(5),
        'event_name' => 'page_view',
        'event_category' => 'page_view',
    ]);

    $action = app(EvaluateAlertRules::class);
    $result = $action->handle(trackedPropertyId: (string) $property->id);

    expect($result)->toHaveKey('processed', 0)
        ->and($result)->toHaveKey('skipped', 1)
        ->and($result)->toHaveKey('dispatched', 0);
});

it('returns empty summary when no rules match', function (): void {
    $action = app(EvaluateAlertRules::class);
    $result = $action->handle();

    expect($result)->toHaveKey('processed', 0)
        ->and($result)->toHaveKey('skipped', 0)
        ->and($result)->toHaveKey('dispatched', 0);
});

it('respects dry run mode', function (): void {
    $property = TrackedProperty::query()->create([
        'name' => 'Dry Run Property',
        'slug' => 'dry-run-property',
        'write_key' => 'dry-run-key',
    ]);

    SignalAlertRule::query()->create([
        'tracked_property_id' => $property->id,
        'name' => 'Dry Run Rule',
        'slug' => 'dry-run-rule',
        'metric_key' => 'events',
        'operator' => '>=',
        'threshold' => 1,
        'timeframe_minutes' => 60,
        'cooldown_minutes' => 30,
        'severity' => 'warning',
    ]);

    SignalEvent::query()->create([
        'tracked_property_id' => $property->id,
        'occurred_at' => CarbonImmutable::now()->subMinutes(5),
        'event_name' => 'page_view',
        'event_category' => 'page_view',
    ]);

    $action = app(EvaluateAlertRules::class);
    $result = $action->handle(trackedPropertyId: (string) $property->id, dryRun: true);

    expect($result)->toHaveKey('processed', 1)
        ->and($result)->toHaveKey('skipped', 0)
        ->and($result)->toHaveKey('dispatched', 0)
        ->and(SignalAlertLog::query()->count())->toBe(0);
});
