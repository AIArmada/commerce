<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentSignals\FilamentSignalsTestCase;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use AIArmada\FilamentSignals\Features\Alerts\EntryPoints\Filament\Policies\SignalAlertLogPolicy;
use AIArmada\FilamentSignals\Features\Alerts\EntryPoints\Filament\Resources\SignalAlertLogResource;
use AIArmada\FilamentSignals\Features\Alerts\EntryPoints\Filament\Widgets\PendingSignalAlertsWidget;
use AIArmada\Signals\Features\Alerts\Api\AlertLogAdministration;
use AIArmada\Signals\Features\Alerts\Models\SignalAlertDelivery;
use AIArmada\Signals\Features\Alerts\Models\SignalAlertLog;
use AIArmada\Signals\Features\Alerts\Models\SignalAlertRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

uses(FilamentSignalsTestCase::class);

it('registers the alert log policy', function (): void {
    expect(Gate::getPolicyFor(SignalAlertLog::class))->toBeInstanceOf(SignalAlertLogPolicy::class);
});

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

it('isolates pending alerts and deletes logs through the alerts boundary', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Alert Log Boundary Owner A',
        'email' => 'alert-log-boundary-a@signals.test',
        'password' => 'secret',
    ]);
    $ownerB = User::query()->create([
        'name' => 'Alert Log Boundary Owner B',
        'email' => 'alert-log-boundary-b@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $ruleA = OwnerContext::withOwner($ownerA, fn (): SignalAlertRule => SignalAlertRule::query()->create([
        'name' => 'Boundary Rule A',
        'slug' => 'boundary-rule-a',
        'metric_key' => 'events',
        'operator' => '>=',
        'threshold' => 1,
    ]));
    $logA = OwnerContext::withOwner($ownerA, fn (): SignalAlertLog => SignalAlertLog::query()->create([
        'signal_alert_rule_id' => $ruleA->id,
        'metric_key' => 'events',
        'operator' => '>=',
        'metric_value' => 2,
        'threshold_value' => 1,
        'severity' => 'warning',
        'title' => 'Boundary Alert A',
    ]));
    $deliveryA = OwnerContext::withOwner($ownerA, fn (): SignalAlertDelivery => SignalAlertDelivery::query()->create([
        'signal_alert_log_id' => $logA->id,
        'channel' => 'webhook',
        'destination_key' => 'boundary',
        'destination' => ['url' => 'https://alerts.example.test/boundary'],
    ]));

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $ruleB = OwnerContext::withOwner($ownerB, fn (): SignalAlertRule => SignalAlertRule::query()->create([
        'name' => 'Boundary Rule B',
        'slug' => 'boundary-rule-b',
        'metric_key' => 'events',
        'operator' => '>=',
        'threshold' => 1,
    ]));
    $logB = OwnerContext::withOwner($ownerB, fn (): SignalAlertLog => SignalAlertLog::query()->create([
        'signal_alert_rule_id' => $ruleB->id,
        'metric_key' => 'events',
        'operator' => '>=',
        'metric_value' => 2,
        'threshold_value' => 1,
        'severity' => 'warning',
        'title' => 'Boundary Alert B',
    ]));

    [, $globalLog] = OwnerContext::withOwner(null, fn (): array => [
        $rule = SignalAlertRule::query()->create([
            'name' => 'Boundary Rule Global',
            'slug' => 'boundary-rule-global',
            'metric_key' => 'events',
            'operator' => '>=',
            'threshold' => 1,
        ]),
        SignalAlertLog::query()->create([
            'signal_alert_rule_id' => $rule->id,
            'metric_key' => 'events',
            'operator' => '>=',
            'metric_value' => 2,
            'threshold_value' => 1,
            'severity' => 'warning',
            'title' => 'Boundary Alert Global',
        ]),
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $pendingQuery = new ReflectionMethod(PendingSignalAlertsWidget::class, 'getPendingAlertsQuery');
    $query = $pendingQuery->invoke(app(PendingSignalAlertsWidget::class));

    expect($query)->toBeInstanceOf(Builder::class)
        ->and($query->pluck('id')->all())->toBe([$logA->id]);

    $globalQuery = OwnerContext::withOwner(null, fn () => $pendingQuery->invoke(app(PendingSignalAlertsWidget::class)));

    expect($globalQuery->pluck('id')->all())->toBe([$globalLog->id]);

    $deletedForeign = OwnerContext::withOwner(null, fn (): bool => app(AlertLogAdministration::class)->delete((string) $logA->id));

    expect($deletedForeign)->toBeFalse()
        ->and(app(AlertLogAdministration::class)->delete((string) $logA->id))->toBeTrue()
        ->and(SignalAlertDelivery::query()->withoutOwnerScope()->whereKey($deliveryA->id)->exists())->toBeFalse()
        ->and(SignalAlertLog::query()->withoutOwnerScope()->whereKey($logA->id)->exists())->toBeFalse()
        ->and(SignalAlertLog::query()->withoutOwnerScope()->whereKey($logB->id)->exists())->toBeTrue()
        ->and(SignalAlertLog::query()->withoutOwnerScope()->whereKey($globalLog->id)->exists())->toBeTrue()
        ->and(OwnerContext::withOwner(null, fn (): bool => app(AlertLogAdministration::class)->delete((string) $globalLog->id)))->toBeTrue();
});
