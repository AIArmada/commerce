<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentSignals\FilamentSignalsTestCase;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use AIArmada\FilamentSignals\Features\Alerts\EntryPoints\Filament\Resources\SignalAlertRuleResource;
use AIArmada\Signals\Features\Alerts\Models\SignalAlertRule;

uses(FilamentSignalsTestCase::class);

it('scopes signal alert rules to the current owner', function (): void {
    /** @var User $ownerA */
    $ownerA = User::query()->create([
        'name' => 'Alert Rule Resource Owner A',
        'email' => 'alert-rule-resource-owner-a@signals.test',
        'password' => 'secret',
    ]);

    /** @var User $ownerB */
    $ownerB = User::query()->create([
        'name' => 'Alert Rule Resource Owner B',
        'email' => 'alert-rule-resource-owner-b@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $ruleA = SignalAlertRule::query()->create([
        'name' => 'Owner A Alert Rule',
        'slug' => 'owner-a-alert-rule',
        'metric_key' => 'events',
        'operator' => '>=',
        'threshold' => 1,
    ]);
    $ruleA->assignOwner($ownerA)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $ruleB = SignalAlertRule::query()->create([
        'name' => 'Owner B Alert Rule',
        'slug' => 'owner-b-alert-rule',
        'metric_key' => 'events',
        'operator' => '>=',
        'threshold' => 1,
    ]);
    $ruleB->assignOwner($ownerB)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $query = SignalAlertRuleResource::getEloquentQuery();

    expect($query->count())->toBe(1)
        ->and($query->first()?->id)->toBe($ruleA->id);
});

it('projects property names without loading cross-feature relations', function (): void {
    $owner = \AIArmada\Commerce\Tests\Fixtures\Models\User::factory()->create();

    \AIArmada\CommerceSupport\Support\OwnerContext::withOwner($owner, function (): void {
        $property = \AIArmada\Signals\Features\Ingestion\Models\TrackedProperty::query()->create([
            'name' => 'Alert property',
            'slug' => 'alert-property-name',
        ]);

        $rule = \AIArmada\Signals\Features\Alerts\Models\SignalAlertRule::query()->create([
            'name' => 'Alert',
            'slug' => 'alert-property-projection',
            'tracked_property_id' => $property->id,
            'metric_key' => 'events',
            'operator' => 'gte',
            'threshold' => 1,
        ]);

        $record = SignalAlertRuleResource::getEloquentQuery()->findOrFail($rule->id);
        expect($record->getAttribute('tracked_property_name'))->toBe('Alert property');
    });
});
