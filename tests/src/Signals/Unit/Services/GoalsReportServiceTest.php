<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\Commerce\Tests\Support\OwnerResolvers\FixedOwnerResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\SignalGoal;
use AIArmada\Signals\Models\SignalIdentity;
use AIArmada\Signals\Models\SignalSession;
use AIArmada\Signals\Models\TrackedProperty;
use AIArmada\Signals\Services\GoalsReportService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

uses(SignalsTestCase::class);

it('builds an owner-safe goals report', function (): void {
    /** @var User $ownerA */
    $ownerA = User::query()->create([
        'name' => 'Goals Owner A',
        'email' => 'goals-owner-a@signals.test',
        'password' => 'secret',
    ]);

    /** @var User $ownerB */
    $ownerB = User::query()->create([
        'name' => 'Goals Owner B',
        'email' => 'goals-owner-b@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $propertyA = TrackedProperty::query()->create([
        'name' => 'Owner A Goals Property',
        'slug' => 'owner-a-goals-property',
        'write_key' => 'owner-a-goals-key',
    ]);

    $goalA = SignalGoal::query()->create([
        'tracked_property_id' => $propertyA->id,
        'name' => 'Pricing Checkout Goal',
        'slug' => 'pricing-checkout-goal',
        'goal_type' => 'conversion',
        'event_name' => 'checkout.completed',
        'event_category' => 'checkout',
        'conditions' => [
            ['field' => 'path', 'operator' => 'equals', 'value' => '/pricing'],
        ],
    ]);

    $identityA = SignalIdentity::query()->create([
        'tracked_property_id' => $propertyA->id,
        'external_id' => 'goals-owner-a-identity',
        'last_seen_at' => CarbonImmutable::parse('2026-03-10 10:00:00'),
    ]);

    $sessionA = SignalSession::query()->create([
        'tracked_property_id' => $propertyA->id,
        'signal_identity_id' => $identityA->id,
        'session_identifier' => 'goals-owner-a-session',
        'started_at' => CarbonImmutable::parse('2026-03-10 10:00:00'),
    ]);

    SignalEvent::query()->create([
        'tracked_property_id' => $propertyA->id,
        'signal_session_id' => $sessionA->id,
        'signal_identity_id' => $identityA->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 10:05:00'),
        'event_name' => 'checkout.completed',
        'event_category' => 'checkout',
        'path' => '/pricing',
        'revenue_minor' => 12000,
    ]);

    SignalEvent::query()->create([
        'tracked_property_id' => $propertyA->id,
        'signal_session_id' => $sessionA->id,
        'signal_identity_id' => $identityA->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 10:10:00'),
        'event_name' => 'checkout.completed',
        'event_category' => 'checkout',
        'path' => '/checkout',
        'revenue_minor' => 9000,
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $propertyB = TrackedProperty::query()->create([
        'name' => 'Owner B Goals Property',
        'slug' => 'owner-b-goals-property',
        'write_key' => 'owner-b-goals-key',
    ]);

    SignalGoal::query()->create([
        'tracked_property_id' => $propertyB->id,
        'name' => 'Other Owner Goal',
        'slug' => 'other-owner-goal',
        'goal_type' => 'conversion',
        'event_name' => 'order.paid',
        'event_category' => 'conversion',
    ]);

    $identityB = SignalIdentity::query()->create([
        'tracked_property_id' => $propertyB->id,
        'external_id' => 'goals-owner-b-identity',
        'last_seen_at' => CarbonImmutable::parse('2026-03-10 11:00:00'),
    ]);

    $sessionB = SignalSession::query()->create([
        'tracked_property_id' => $propertyB->id,
        'signal_identity_id' => $identityB->id,
        'session_identifier' => 'goals-owner-b-session',
        'started_at' => CarbonImmutable::parse('2026-03-10 11:00:00'),
    ]);

    SignalEvent::query()->create([
        'tracked_property_id' => $propertyB->id,
        'signal_session_id' => $sessionB->id,
        'signal_identity_id' => $identityB->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 11:05:00'),
        'event_name' => 'order.paid',
        'event_category' => 'conversion',
        'path' => '/checkout',
        'revenue_minor' => 25000,
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $service = app(GoalsReportService::class);
    $summary = $service->summary($propertyA->id, '2026-03-10', '2026-03-10');
    $rows = $service->rows($propertyA->id, '2026-03-10', '2026-03-10');

    expect($summary['goals'])->toBe(1)
        ->and($summary['goal_hits'])->toBe(1)
        ->and($summary['visitors'])->toBe(1)
        ->and($summary['revenue_minor'])->toBe(12000)
        ->and($summary['avg_goal_rate'])->toBe(100.0)
        ->and($rows)->toHaveCount(1)
        ->and($rows[0]['id'])->toBe($goalA->id)
        ->and($rows[0]['goal_hits'])->toBe(1)
        ->and($rows[0]['visitors'])->toBe(1)
        ->and($rows[0]['revenue_minor'])->toBe(12000)
        ->and($rows[0]['goal_rate'])->toBe(100.0)
        ->and($rows[0]['tracked_property_name'])->toBe('Owner A Goals Property')
        ->and($service->getTrackedPropertyOptions())
        ->toBe([$propertyA->id => 'Owner A Goals Property']);
});

it('supports numeric and event property goal conditions', function (): void {
    /** @var User $ownerA */
    $ownerA = User::query()->create([
        'name' => 'Goals Condition Owner A',
        'email' => 'goals-condition-owner-a@signals.test',
        'password' => 'secret',
    ]);

    /** @var User $ownerB */
    $ownerB = User::query()->create([
        'name' => 'Goals Condition Owner B',
        'email' => 'goals-condition-owner-b@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $propertyA = TrackedProperty::query()->create([
        'name' => 'Owner A Property Conditions',
        'slug' => 'owner-a-property-conditions',
        'write_key' => 'owner-a-property-conditions-key',
    ]);

    SignalGoal::query()->create([
        'tracked_property_id' => $propertyA->id,
        'name' => 'High Value Chip Goal',
        'slug' => 'high-value-chip-goal',
        'goal_type' => 'revenue',
        'event_name' => 'order.paid',
        'event_category' => 'conversion',
        'conditions' => [
            ['field' => 'revenue_minor', 'operator' => 'greater_than_or_equal', 'value' => '10000'],
            ['field' => 'properties.checkout.gateway', 'operator' => 'not_equals', 'value' => 'stripe'],
            ['field' => 'properties.checkout.attempt_count', 'operator' => 'greater_than_or_equal', 'value' => '2'],
            ['field' => 'properties.order_number', 'operator' => 'starts_with', 'value' => 'ORD-'],
        ],
    ]);

    $identityA = SignalIdentity::query()->create([
        'tracked_property_id' => $propertyA->id,
        'external_id' => 'goals-condition-owner-a-identity',
        'last_seen_at' => CarbonImmutable::parse('2026-03-10 12:00:00'),
    ]);

    $sessionA = SignalSession::query()->create([
        'tracked_property_id' => $propertyA->id,
        'signal_identity_id' => $identityA->id,
        'session_identifier' => 'goals-condition-owner-a-session',
        'started_at' => CarbonImmutable::parse('2026-03-10 12:00:00'),
    ]);

    SignalEvent::query()->create([
        'tracked_property_id' => $propertyA->id,
        'signal_session_id' => $sessionA->id,
        'signal_identity_id' => $identityA->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 12:05:00'),
        'event_name' => 'order.paid',
        'event_category' => 'conversion',
        'revenue_minor' => 12500,
        'properties' => [
            'order_number' => 'ORD-1001',
            'checkout' => ['gateway' => 'chip', 'attempt_count' => 2],
        ],
        'property_types' => [
            'order_number' => 'string',
            'checkout' => ['gateway' => 'string', 'attempt_count' => 'number'],
        ],
    ]);

    SignalEvent::query()->create([
        'tracked_property_id' => $propertyA->id,
        'signal_session_id' => $sessionA->id,
        'signal_identity_id' => $identityA->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 12:10:00'),
        'event_name' => 'order.paid',
        'event_category' => 'conversion',
        'revenue_minor' => 9900,
        'properties' => [
            'order_number' => 'ORD-1002',
            'checkout' => ['gateway' => 'chip', 'attempt_count' => 1],
        ],
        'property_types' => [
            'order_number' => 'string',
            'checkout' => ['gateway' => 'string', 'attempt_count' => 'number'],
        ],
    ]);

    SignalEvent::query()->create([
        'tracked_property_id' => $propertyA->id,
        'signal_session_id' => $sessionA->id,
        'signal_identity_id' => $identityA->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 12:15:00'),
        'event_name' => 'order.paid',
        'event_category' => 'conversion',
        'revenue_minor' => 18000,
        'properties' => [
            'order_number' => 'ORD-1003',
            'checkout' => ['gateway' => 'stripe', 'attempt_count' => 3],
        ],
        'property_types' => [
            'order_number' => 'string',
            'checkout' => ['gateway' => 'string', 'attempt_count' => 'number'],
        ],
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $propertyB = TrackedProperty::query()->create([
        'name' => 'Owner B Property Conditions',
        'slug' => 'owner-b-property-conditions',
        'write_key' => 'owner-b-property-conditions-key',
    ]);

    $identityB = SignalIdentity::query()->create([
        'tracked_property_id' => $propertyB->id,
        'external_id' => 'goals-condition-owner-b-identity',
        'last_seen_at' => CarbonImmutable::parse('2026-03-10 13:00:00'),
    ]);

    $sessionB = SignalSession::query()->create([
        'tracked_property_id' => $propertyB->id,
        'signal_identity_id' => $identityB->id,
        'session_identifier' => 'goals-condition-owner-b-session',
        'started_at' => CarbonImmutable::parse('2026-03-10 13:00:00'),
    ]);

    SignalEvent::query()->create([
        'tracked_property_id' => $propertyB->id,
        'signal_session_id' => $sessionB->id,
        'signal_identity_id' => $identityB->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 13:05:00'),
        'event_name' => 'order.paid',
        'event_category' => 'conversion',
        'revenue_minor' => 30000,
        'properties' => [
            'order_number' => 'ORD-2001',
            'checkout' => ['gateway' => 'chip', 'attempt_count' => 4],
        ],
        'property_types' => [
            'order_number' => 'string',
            'checkout' => ['gateway' => 'string', 'attempt_count' => 'number'],
        ],
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $service = app(GoalsReportService::class);
    $rows = $service->rows($propertyA->id, '2026-03-10', '2026-03-10');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['goal_hits'])->toBe(1)
        ->and($rows[0]['visitors'])->toBe(1)
        ->and($rows[0]['revenue_minor'])->toBe(12500)
        ->and($rows[0]['goal_rate'])->toBe(100.0);
});

it('rejects invalid goal conditions when saving', function (): void {
    /** @var User $owner */
    $owner = User::query()->create([
        'name' => 'Goals Validation Owner',
        'email' => 'goals-validation-owner@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $property = TrackedProperty::query()->create([
        'name' => 'Goals Validation Property',
        'slug' => 'goals-validation-property',
        'write_key' => 'goals-validation-property-key',
    ]);

    expect(fn (): SignalGoal => SignalGoal::query()->create([
        'tracked_property_id' => $property->id,
        'name' => 'Invalid Numeric Goal',
        'slug' => 'invalid-numeric-goal',
        'goal_type' => 'conversion',
        'event_name' => 'checkout.completed',
        'conditions' => [
            ['field' => 'path', 'operator' => 'greater_than', 'value' => '100'],
        ],
    ]))->toThrow(InvalidArgumentException::class, 'operator [greater_than] requires a numeric field');

    expect(fn (): SignalGoal => SignalGoal::query()->create([
        'tracked_property_id' => $property->id,
        'name' => 'Invalid Field Goal',
        'slug' => 'invalid-field-goal',
        'goal_type' => 'conversion',
        'event_name' => 'checkout.completed',
        'conditions' => [
            ['field' => 'properties.checkout.gateway!', 'operator' => 'equals', 'value' => 'chip'],
        ],
    ]))->toThrow(InvalidArgumentException::class, 'unsupported field');
});

it('fails closed when persisted goal conditions are malformed', function (): void {
    /** @var User $owner */
    $owner = User::query()->create([
        'name' => 'Goals Fail Closed Owner',
        'email' => 'goals-fail-closed-owner@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $property = TrackedProperty::query()->create([
        'name' => 'Goals Fail Closed Property',
        'slug' => 'goals-fail-closed-property',
        'write_key' => 'goals-fail-closed-property-key',
    ]);

    $goal = SignalGoal::query()->create([
        'tracked_property_id' => $property->id,
        'name' => 'Fail Closed Goal',
        'slug' => 'fail-closed-goal',
        'goal_type' => 'conversion',
        'event_name' => 'order.paid',
        'event_category' => 'conversion',
        'conditions' => [
            ['field' => 'revenue_minor', 'operator' => 'greater_than', 'value' => '10000'],
        ],
    ]);

    $identity = SignalIdentity::query()->create([
        'tracked_property_id' => $property->id,
        'external_id' => 'goals-fail-closed-identity',
        'last_seen_at' => CarbonImmutable::parse('2026-03-10 14:00:00'),
    ]);

    $session = SignalSession::query()->create([
        'tracked_property_id' => $property->id,
        'signal_identity_id' => $identity->id,
        'session_identifier' => 'goals-fail-closed-session',
        'started_at' => CarbonImmutable::parse('2026-03-10 14:00:00'),
    ]);

    SignalEvent::query()->create([
        'tracked_property_id' => $property->id,
        'signal_session_id' => $session->id,
        'signal_identity_id' => $identity->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 14:05:00'),
        'event_name' => 'order.paid',
        'event_category' => 'conversion',
        'revenue_minor' => 20000,
    ]);

    DB::table($goal->getTable())
        ->where('id', $goal->id)
        ->update([
            'conditions' => json_encode([
                ['field' => 'properties.invalid!', 'operator' => 'equals', 'value' => 'chip'],
            ], JSON_THROW_ON_ERROR),
        ]);

    $service = app(GoalsReportService::class);
    $rows = $service->rows($property->id, '2026-03-10', '2026-03-10');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['goal_hits'])->toBe(0)
        ->and($rows[0]['visitors'])->toBe(0)
        ->and($rows[0]['revenue_minor'])->toBe(0)
        ->and($rows[0]['goal_rate'])->toBe(0.0)
        ->and($rows[0]['last_hit_at'])->toBeNull();
});
