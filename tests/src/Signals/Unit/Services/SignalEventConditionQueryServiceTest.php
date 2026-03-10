<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\Commerce\Tests\Support\OwnerResolvers\FixedOwnerResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\TrackedProperty;
use AIArmada\Signals\Services\SignalEventConditionQueryService;
use Carbon\CarbonImmutable;

uses(SignalsTestCase::class);

it('supports typed property operators for numeric and string comparisons', function (): void {
    /** @var User $owner */
    $owner = User::query()->create([
        'name' => 'Condition Query Owner',
        'email' => 'condition-query-owner@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $property = TrackedProperty::query()->create([
        'name' => 'Condition Query Property',
        'slug' => 'condition-query-property',
        'write_key' => 'condition-query-key',
    ]);

    SignalEvent::query()->create([
        'tracked_property_id' => $property->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 10:05:00'),
        'event_name' => 'order.paid',
        'event_category' => 'conversion',
        'properties' => [
            'checkout' => [
                'gateway' => 'chip',
                'attempt_count' => 2,
            ],
        ],
        'property_types' => [
            'checkout' => [
                'gateway' => 'string',
                'attempt_count' => 'number',
            ],
        ],
    ]);

    SignalEvent::query()->create([
        'tracked_property_id' => $property->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 10:10:00'),
        'event_name' => 'order.paid',
        'event_category' => 'conversion',
        'properties' => [
            'checkout' => [
                'gateway' => 'stripe',
                'attempt_count' => 1,
            ],
        ],
        'property_types' => [
            'checkout' => [
                'gateway' => 'string',
                'attempt_count' => 'number',
            ],
        ],
    ]);

    $query = app(SignalEventConditionQueryService::class)->apply(
        SignalEvent::query(),
        [
            ['field' => 'properties.checkout.gateway', 'operator' => 'not_equals', 'value' => 'stripe'],
            ['field' => 'properties.checkout.attempt_count', 'operator' => 'greater_than_or_equal', 'value' => '2'],
        ],
    );

    expect($query->count())->toBe(1)
        ->and($query->first()?->properties['checkout']['gateway'] ?? null)->toBe('chip');
});

it('fails closed for numeric property comparisons when stored property types are not numeric', function (): void {
    /** @var User $owner */
    $owner = User::query()->create([
        'name' => 'Condition Type Guard Owner',
        'email' => 'condition-type-guard-owner@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $property = TrackedProperty::query()->create([
        'name' => 'Condition Type Guard Property',
        'slug' => 'condition-type-guard-property',
        'write_key' => 'condition-type-guard-key',
    ]);

    SignalEvent::query()->create([
        'tracked_property_id' => $property->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 11:05:00'),
        'event_name' => 'order.paid',
        'event_category' => 'conversion',
        'properties' => [
            'checkout' => [
                'attempt_count' => '2',
            ],
        ],
        'property_types' => [
            'checkout' => [
                'attempt_count' => 'string',
            ],
        ],
    ]);

    $count = app(SignalEventConditionQueryService::class)
        ->apply(SignalEvent::query(), [
            ['field' => 'properties.checkout.attempt_count', 'operator' => 'greater_than', 'value' => '1'],
        ])
        ->count();

    expect($count)->toBe(0);
});
