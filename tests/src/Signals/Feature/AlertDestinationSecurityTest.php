<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use AIArmada\Signals\Features\Alerts\Actions\SignalAlertDispatcher;
use AIArmada\Signals\Features\Alerts\Api\AlertRuleAdministration;
use AIArmada\Signals\Features\Alerts\Models\SignalAlertRule;
use AIArmada\Signals\Features\Ingestion\Models\TrackedProperty;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

uses(SignalsTestCase::class);

it('reserves named destinations for global rules while allowing tenant inline destinations', function (): void {
    config()->set('signals.features.alerts.destinations.webhook.ops', ['url' => 'https://alerts.example.test/ops']);
    config()->set('signals.features.alerts.allow_inline_destinations', true);

    $owner = User::query()->create([
        'name' => 'Destination Owner',
        'email' => 'destination-owner@signals.test',
        'password' => 'secret',
    ]);
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));
    $property = TrackedProperty::query()->create([
        'name' => 'Destination Property',
        'slug' => 'destination-property',
        'write_key' => 'destination-property-key',
    ]);

    expect(fn (): string => app(AlertRuleAdministration::class)->create([
        'tracked_property_id' => $property->id,
        'name' => 'Tenant Named Destination',
        'slug' => 'tenant-named-destination',
        'metric_key' => 'events',
        'operator' => '>=',
        'threshold' => 1,
        'channels' => ['webhook'],
        'destination_keys' => ['ops'],
    ]))->toThrow(InvalidArgumentException::class);

    $inlineId = app(AlertRuleAdministration::class)->create([
        'tracked_property_id' => $property->id,
        'name' => 'Tenant Inline Destination',
        'slug' => 'tenant-inline-destination',
        'metric_key' => 'events',
        'operator' => '>=',
        'threshold' => 1,
        'channels' => ['webhook'],
        'inline_destinations' => [
            ['channel' => 'webhook', 'destination' => ['url' => 'https://alerts.example.test/inline-secret']],
        ],
    ]);

    $globalId = OwnerContext::withOwner(null, fn (): string => app(AlertRuleAdministration::class)->create([
        'name' => 'Global Named Destination',
        'slug' => 'global-named-destination',
        'metric_key' => 'events',
        'operator' => '>=',
        'threshold' => 1,
        'channels' => ['webhook'],
        'destination_keys' => ['ops'],
    ]));

    expect($inlineId)->not->toBe('')
        ->and($globalId)->not->toBe('');
});

it('keeps inline destination secrets out of serialization and audit storage', function (): void {
    config()->set('signals.features.alerts.allow_inline_destinations', true);

    $owner = User::query()->create([
        'name' => 'Secret Owner',
        'email' => 'secret-owner@signals.test',
        'password' => 'secret',
    ]);
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $ruleId = app(AlertRuleAdministration::class)->create([
        'name' => 'Inline Secret Rule',
        'slug' => 'inline-secret-rule',
        'metric_key' => 'events',
        'operator' => '>=',
        'threshold' => 1,
        'channels' => ['webhook'],
        'inline_destinations' => [
            ['channel' => 'webhook', 'destination' => ['url' => 'https://alerts.example.test/secret-token-value']],
        ],
    ]);

    $rule = SignalAlertRule::query()->findOrFail($ruleId);
    $activityTable = (string) config('activitylog.table_name', 'activity_log');

    expect($rule->toArray())->not->toHaveKey('inline_destinations')
        ->and(json_encode($rule->toArray(), JSON_THROW_ON_ERROR))->not->toContain('secret-token-value')
        ->and(DB::table('audits')->where('new_values', 'like', '%secret-token-value%')->exists())->toBeFalse()
        ->and(DB::table($activityTable)->where('properties', 'like', '%secret-token-value%')->exists())->toBeFalse();
});

it('does not dispatch named destinations for tenant rules even when configured', function (): void {
    Queue::fake();
    config()->set('signals.features.alerts.destinations.webhook.ops', ['url' => 'https://alerts.example.test/ops']);

    $owner = User::query()->create([
        'name' => 'Legacy Destination Owner',
        'email' => 'legacy-destination-owner@signals.test',
        'password' => 'secret',
    ]);
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));
    $property = TrackedProperty::query()->create([
        'name' => 'Legacy Destination Property',
        'slug' => 'legacy-destination-property',
        'write_key' => 'legacy-destination-property-key',
    ]);
    $rule = SignalAlertRule::query()->create([
        'tracked_property_id' => $property->id,
        'name' => 'Legacy Tenant Destination',
        'slug' => 'legacy-tenant-destination',
        'metric_key' => 'events',
        'operator' => '>=',
        'threshold' => 1,
        'timeframe_minutes' => 60,
        'cooldown_minutes' => 60,
        'channels' => ['webhook'],
        'destination_keys' => ['ops'],
    ]);

    $log = app(SignalAlertDispatcher::class)->dispatch($rule, 2.0);

    expect($log)->not->toBeNull()
        ->and($log?->deliveries()->count())->toBe(0)
        ->and($log?->delivery_results)->toHaveKey('database')
        ->and($log?->delivery_results)->not->toHaveKey('webhook');
    Queue::assertNothingPushed();
});
