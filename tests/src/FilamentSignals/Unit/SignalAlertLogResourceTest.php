<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentSignals\FilamentSignalsTestCase;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Support\OwnerResolvers\FixedOwnerResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\FilamentSignals\Resources\SignalAlertLogResource;
use AIArmada\Signals\Models\SignalAlertLog;
use AIArmada\Signals\Models\SignalAlertRule;

uses(FilamentSignalsTestCase::class);

it('scopes signal alert logs to the current owner', function (): void {
    /** @var User $ownerA */
    $ownerA = User::query()->create([
        'name' => 'Alert Log Resource Owner A',
        'email' => 'alert-log-resource-owner-a@signals.test',
        'password' => 'secret',
    ]);

    /** @var User $ownerB */
    $ownerB = User::query()->create([
        'name' => 'Alert Log Resource Owner B',
        'email' => 'alert-log-resource-owner-b@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $ruleA = SignalAlertRule::query()->create([
        'name' => 'Owner A Rule',
        'slug' => 'owner-a-rule',
        'metric_key' => 'events',
        'operator' => '>=',
        'threshold' => 1,
    ]);
    $ruleA->assignOwner($ownerA)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $ruleB = SignalAlertRule::query()->create([
        'name' => 'Owner B Rule',
        'slug' => 'owner-b-rule',
        'metric_key' => 'events',
        'operator' => '>=',
        'threshold' => 1,
    ]);
    $ruleB->assignOwner($ownerB)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $logA = SignalAlertLog::query()->create([
        'signal_alert_rule_id' => $ruleA->id,
        'metric_key' => 'events',
        'operator' => '>=',
        'metric_value' => 5,
        'threshold_value' => 1,
        'severity' => 'warning',
        'title' => 'Owner A Alert',
        'channels_notified' => ['database'],
    ]);
    $logA->assignOwner($ownerA)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $logB = SignalAlertLog::query()->create([
        'signal_alert_rule_id' => $ruleB->id,
        'metric_key' => 'events',
        'operator' => '>=',
        'metric_value' => 5,
        'threshold_value' => 1,
        'severity' => 'warning',
        'title' => 'Owner B Alert',
        'channels_notified' => ['database'],
    ]);
    $logB->assignOwner($ownerB)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $query = SignalAlertLogResource::getEloquentQuery();

    expect($query->count())->toBe(1)
        ->and($query->first()?->id)->toBe($logA->id);
});
