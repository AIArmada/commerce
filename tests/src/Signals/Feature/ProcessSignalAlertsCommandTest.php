<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\Commerce\Tests\Support\OwnerResolvers\FixedOwnerResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Signals\Models\SignalAlertLog;
use AIArmada\Signals\Models\SignalAlertRule;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\TrackedProperty;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

uses(SignalsTestCase::class);

it('processes signal alert rules for the current owner scope', function (): void {
    /** @var User $ownerA */
    $ownerA = User::query()->create([
        'name' => 'Alert Owner A',
        'email' => 'alert-owner-a@signals.test',
        'password' => 'secret',
    ]);

    /** @var User $ownerB */
    $ownerB = User::query()->create([
        'name' => 'Alert Owner B',
        'email' => 'alert-owner-b@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $propertyA = TrackedProperty::query()->create([
        'name' => 'Alert Property A',
        'slug' => 'alert-property-a',
        'write_key' => 'alert-property-a-key',
    ]);
    $propertyA->assignOwner($ownerA)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $propertyB = TrackedProperty::query()->create([
        'name' => 'Alert Property B',
        'slug' => 'alert-property-b',
        'write_key' => 'alert-property-b-key',
    ]);
    $propertyB->assignOwner($ownerB)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $ruleA = SignalAlertRule::query()->create([
        'tracked_property_id' => $propertyA->id,
        'name' => 'Page Views Spike',
        'slug' => 'page-views-spike',
        'metric_key' => 'page_views',
        'operator' => '>=',
        'threshold' => 2,
        'timeframe_minutes' => 60,
        'cooldown_minutes' => 30,
        'severity' => 'warning',
    ]);
    $ruleA->assignOwner($ownerA)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $ruleB = SignalAlertRule::query()->create([
        'tracked_property_id' => $propertyB->id,
        'name' => 'Owner B Conversions',
        'slug' => 'owner-b-conversions',
        'metric_key' => 'conversions',
        'operator' => '>=',
        'threshold' => 1,
        'timeframe_minutes' => 60,
        'cooldown_minutes' => 30,
        'severity' => 'critical',
    ]);
    $ruleB->assignOwner($ownerB)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    foreach ([1, 2] as $index) {
        $event = SignalEvent::query()->create([
            'tracked_property_id' => $propertyA->id,
            'occurred_at' => CarbonImmutable::now()->subMinutes($index * 5),
            'event_name' => 'page_view',
            'event_category' => 'page_view',
            'path' => '/landing',
        ]);
        $event->assignOwner($ownerA)->save();
    }

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $eventB = SignalEvent::query()->create([
        'tracked_property_id' => $propertyB->id,
        'occurred_at' => CarbonImmutable::now()->subMinutes(5),
        'event_name' => 'order.paid',
        'event_category' => 'conversion',
        'path' => '/checkout',
    ]);
    $eventB->assignOwner($ownerB)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $this->artisan('signals:process-alerts')
        ->expectsOutputToContain('Summary:')
        ->assertSuccessful();

    expect(SignalAlertLog::query()->forOwner()->count())->toBe(1)
        ->and(SignalAlertLog::query()->forOwner()->first()?->signal_alert_rule_id)->toBe($ruleA->id)
        ->and($ruleA->fresh()?->last_triggered_at)->not()->toBeNull()
        ->and($ruleB->fresh()?->last_triggered_at)->toBeNull();
});

it('processes signal alert rules for each owner when no ambient owner is resolved', function (): void {
    /** @var User $ownerA */
    $ownerA = User::query()->create([
        'name' => 'Alert Multi Owner A',
        'email' => 'alert-multi-owner-a@signals.test',
        'password' => 'secret',
    ]);

    /** @var User $ownerB */
    $ownerB = User::query()->create([
        'name' => 'Alert Multi Owner B',
        'email' => 'alert-multi-owner-b@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $propertyA = TrackedProperty::query()->create([
        'name' => 'Alert Multi Property A',
        'slug' => 'alert-multi-property-a',
        'write_key' => 'alert-multi-property-a-key',
    ]);

    $ruleA = SignalAlertRule::query()->create([
        'tracked_property_id' => $propertyA->id,
        'name' => 'Alert Multi Rule A',
        'slug' => 'alert-multi-rule-a',
        'metric_key' => 'page_views',
        'operator' => '>=',
        'threshold' => 1,
        'timeframe_minutes' => 60,
        'cooldown_minutes' => 30,
        'severity' => 'warning',
    ]);

    SignalEvent::query()->create([
        'tracked_property_id' => $propertyA->id,
        'occurred_at' => CarbonImmutable::now()->subMinutes(5),
        'event_name' => 'page_view',
        'event_category' => 'page_view',
        'path' => '/owner-a',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $propertyB = TrackedProperty::query()->create([
        'name' => 'Alert Multi Property B',
        'slug' => 'alert-multi-property-b',
        'write_key' => 'alert-multi-property-b-key',
    ]);

    $ruleB = SignalAlertRule::query()->create([
        'tracked_property_id' => $propertyB->id,
        'name' => 'Alert Multi Rule B',
        'slug' => 'alert-multi-rule-b',
        'metric_key' => 'conversions',
        'operator' => '>=',
        'threshold' => 1,
        'timeframe_minutes' => 60,
        'cooldown_minutes' => 30,
        'severity' => 'critical',
    ]);

    SignalEvent::query()->create([
        'tracked_property_id' => $propertyB->id,
        'occurred_at' => CarbonImmutable::now()->subMinutes(5),
        'event_name' => 'order.paid',
        'event_category' => 'conversion',
        'path' => '/owner-b',
    ]);

    app()->instance(OwnerResolverInterface::class, new class implements OwnerResolverInterface
    {
        public function resolve(): ?Model
        {
            return null;
        }
    });

    $this->artisan('signals:process-alerts')
        ->expectsOutputToContain('Summary: 2 processed, 0 skipped, 2 dispatched')
        ->assertSuccessful();

    $logs = SignalAlertLog::query()
        ->withoutOwnerScope()
        ->orderBy('signal_alert_rule_id')
        ->get();

    expect($logs)->toHaveCount(2)
        ->and($logs->pluck('signal_alert_rule_id')->all())->toContain($ruleA->id, $ruleB->id)
        ->and($ruleA->fresh()?->last_triggered_at)->not()->toBeNull()
        ->and($ruleB->fresh()?->last_triggered_at)->not()->toBeNull();
});
