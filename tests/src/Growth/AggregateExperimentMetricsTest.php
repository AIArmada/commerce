<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Growth\Actions\AggregateExperimentMetrics;
use AIArmada\Growth\Actions\BuildExperimentSignalProperties;
use AIArmada\Growth\Models\Assignment;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\TrackedProperty;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function growthMetricsOwner(): User
{
    return User::query()->create([
        'name' => 'Metrics Owner ' . Str::random(6),
        'email' => 'metrics-owner-' . Str::lower(Str::random(8)) . '@example.com',
        'password' => 'secret',
    ]);
}

function growthMetricsExperiment(User $owner): Experiment
{
    return OwnerContext::withOwner($owner, function () use ($owner): Experiment {
        $trackedProperty = TrackedProperty::query()->create([
            'name' => 'Metrics Property ' . Str::random(6),
            'slug' => 'metrics-property-' . Str::lower(Str::random(8)),
            'write_key' => Str::random(40),
            'type' => 'website',
            'timezone' => 'UTC',
            'currency' => 'MYR',
            'is_active' => true,
        ]);

        if (! config('signals.owner.enabled', true)) {
            $trackedProperty->owner_type = $owner->getMorphClass();
            $trackedProperty->owner_id = (string) $owner->getKey();
            $trackedProperty->save();
        }

        /** @var Experiment $experiment */
        $experiment = Experiment::factory()->create([
            'tracked_property_id' => $trackedProperty->getKey(),
            'status' => 'active',
        ]);

        return $experiment;
    });
}

function growthMetricsAssignment(User $owner, Experiment $experiment, Variant $variant, string $subjectKey): Assignment
{
    return OwnerContext::withOwner($owner, fn (): Assignment => Assignment::query()->create([
        'experiment_id' => $experiment->getKey(),
        'variant_id' => $variant->getKey(),
        'subject_key' => $subjectKey,
        'bucket' => 0,
        'assigned_at' => CarbonImmutable::now(),
        'first_exposed_at' => CarbonImmutable::now(),
        'last_seen_at' => CarbonImmutable::now(),
    ]));
}

function growthMetricsEvent(User $owner, Experiment $experiment, Assignment $assignment, string $eventName, int $revenueMinor = 0, string $currency = 'MYR'): SignalEvent
{
    $properties = OwnerContext::withOwner($owner, fn (): array => app(BuildExperimentSignalProperties::class)->handle($assignment));

    return OwnerContext::withOwner($owner, fn (): SignalEvent => SignalEvent::query()->create([
        'tracked_property_id' => $experiment->tracked_property_id,
        'occurred_at' => CarbonImmutable::now(),
        'event_name' => $eventName,
        'event_category' => str_contains($eventName, 'checkout') ? 'checkout' : 'conversion',
        'revenue_minor' => $revenueMinor,
        'currency' => $currency,
        'properties' => $properties,
    ]));
}

it('aggregates per-variant revenue and conversion metrics from signal events', function (): void {
    $owner = growthMetricsOwner();
    $experiment = growthMetricsExperiment($owner);

    $variantA = OwnerContext::withOwner($owner, fn (): Variant => Variant::factory()->create([
        'experiment_id' => $experiment->getKey(),
        'code' => 'A',
        'name' => 'Control',
        'traffic_percentage' => 50,
        'position' => 1,
        'is_control' => true,
    ]));

    $variantB = OwnerContext::withOwner($owner, fn (): Variant => Variant::factory()->create([
        'experiment_id' => $experiment->getKey(),
        'code' => 'B',
        'name' => 'Challenger',
        'traffic_percentage' => 50,
        'position' => 2,
    ]));

    $assignmentA1 = growthMetricsAssignment($owner, $experiment, $variantA, 'identity:a1');
    $assignmentA2 = growthMetricsAssignment($owner, $experiment, $variantA, 'identity:a2');
    $assignmentB1 = growthMetricsAssignment($owner, $experiment, $variantB, 'identity:b1');

    growthMetricsEvent($owner, $experiment, $assignmentA1, 'checkout.started');
    growthMetricsEvent($owner, $experiment, $assignmentA1, 'order.paid', 30000);
    growthMetricsEvent($owner, $experiment, $assignmentB1, 'checkout.started');
    growthMetricsEvent($owner, $experiment, $assignmentB1, 'order.paid', 10000);
    growthMetricsEvent($owner, $experiment, $assignmentB1, 'order.refunded', 2000);

    OwnerContext::withOwner($owner, fn (): SignalEvent => SignalEvent::query()->create([
        'tracked_property_id' => $experiment->tracked_property_id,
        'occurred_at' => CarbonImmutable::now(),
        'event_name' => 'order.paid',
        'event_category' => 'conversion',
        'revenue_minor' => 99999,
        'currency' => 'MYR',
        'properties' => [
            'experiment_id' => 'some-other-experiment',
            'variant_id' => 'variant-x',
        ],
    ]));

    $metrics = OwnerContext::withOwner($owner, fn (): array => app(AggregateExperimentMetrics::class)->handle($experiment));

    expect($metrics['totals']['assignments'])->toBe(3)
        ->and($metrics['totals']['checkout_starts'])->toBe(2)
        ->and($metrics['totals']['purchases'])->toBe(2)
        ->and($metrics['totals']['refunds'])->toBe(1)
        ->and($metrics['totals']['revenue_minor'])->toBe(38000)
        ->and($metrics['winner_variant_id'])->toBe((string) $variantA->getKey())
        ->and($metrics['variants'][0]['revenue_per_visitor'])->toBe(15000.0)
        ->and($metrics['variants'][1]['revenue_per_visitor'])->toBe(8000.0);

    expect($assignmentA2->variant_id)->toBe((string) $variantA->getKey());
});

it('aggregates experiment metrics from nested experiment contexts on signal events', function (): void {
    $owner = growthMetricsOwner();
    $experiment = growthMetricsExperiment($owner);

    $variantA = OwnerContext::withOwner($owner, fn (): Variant => Variant::factory()->create([
        'experiment_id' => $experiment->getKey(),
        'code' => 'A',
        'name' => 'Control',
        'traffic_percentage' => 100,
        'position' => 1,
        'is_control' => true,
    ]));

    $assignmentA = growthMetricsAssignment($owner, $experiment, $variantA, 'identity:context-a');

    $otherExperiment = growthMetricsExperiment($owner);
    $otherVariant = OwnerContext::withOwner($owner, fn (): Variant => Variant::factory()->create([
        'experiment_id' => $otherExperiment->getKey(),
        'code' => 'B',
        'name' => 'Other Variant',
        'traffic_percentage' => 100,
        'position' => 1,
    ]));
    $otherAssignment = growthMetricsAssignment($owner, $otherExperiment, $otherVariant, 'identity:context-b');

    OwnerContext::withOwner($owner, fn (): SignalEvent => SignalEvent::query()->create([
        'tracked_property_id' => $experiment->tracked_property_id,
        'occurred_at' => CarbonImmutable::now(),
        'event_name' => 'order.paid',
        'event_category' => 'conversion',
        'revenue_minor' => 45000,
        'currency' => 'MYR',
        'properties' => [
            'experiment_contexts' => [
                app(BuildExperimentSignalProperties::class)->handle($assignmentA),
                app(BuildExperimentSignalProperties::class)->handle($otherAssignment),
            ],
        ],
    ]));

    $metrics = OwnerContext::withOwner($owner, fn (): array => app(AggregateExperimentMetrics::class)->handle($experiment));

    expect($metrics['totals']['purchases'])->toBe(1)
        ->and($metrics['totals']['revenue_minor'])->toBe(45000)
        ->and($metrics['winner_variant_id'])->toBe((string) $variantA->getKey());
});

it('returns no winner when an experiment has no assignments yet', function (): void {
    $owner = growthMetricsOwner();
    $experiment = growthMetricsExperiment($owner);

    OwnerContext::withOwner($owner, fn (): Variant => Variant::factory()->create([
        'experiment_id' => $experiment->getKey(),
        'code' => 'A',
        'name' => 'Control',
        'traffic_percentage' => 100,
        'position' => 1,
        'is_control' => true,
    ]));

    $metrics = OwnerContext::withOwner($owner, fn (): array => app(AggregateExperimentMetrics::class)->handle($experiment));

    expect($metrics['totals']['assignments'])->toBe(0)
        ->and($metrics['totals']['purchases'])->toBe(0)
        ->and($metrics['winner_variant_id'])->toBeNull();
});

it('deduplicates conversion rate by assignment id when one assignment records multiple purchases', function (): void {
    $owner = growthMetricsOwner();
    $experiment = growthMetricsExperiment($owner);

    $variant = OwnerContext::withOwner($owner, fn (): Variant => Variant::factory()->create([
        'experiment_id' => $experiment->getKey(),
        'code' => 'A',
        'name' => 'Control',
        'traffic_percentage' => 100,
        'position' => 1,
        'is_control' => true,
    ]));

    $assignment = growthMetricsAssignment($owner, $experiment, $variant, 'identity:repeat-buyer');

    growthMetricsEvent($owner, $experiment, $assignment, 'order.paid', 15000);
    growthMetricsEvent($owner, $experiment, $assignment, 'order.paid', 10000);

    $metrics = OwnerContext::withOwner($owner, fn (): array => app(AggregateExperimentMetrics::class)->handle($experiment));

    expect($metrics['totals']['purchases'])->toBe(2)
        ->and($metrics['variants'][0]['purchases'])->toBe(2)
        ->and($metrics['variants'][0]['conversion_rate'])->toBe(1.0)
        ->and($metrics['winner_variant_id'])->toBe((string) $variant->getKey());
});

it('keeps conversion rate based on unique assignment ids when purchases repeat on one assignment among many', function (): void {
    $owner = growthMetricsOwner();
    $experiment = growthMetricsExperiment($owner);

    $variant = OwnerContext::withOwner($owner, fn (): Variant => Variant::factory()->create([
        'experiment_id' => $experiment->getKey(),
        'code' => 'A',
        'name' => 'Control',
        'traffic_percentage' => 100,
        'position' => 1,
        'is_control' => true,
    ]));

    $convertingAssignment = growthMetricsAssignment($owner, $experiment, $variant, 'identity:converter');
    growthMetricsAssignment($owner, $experiment, $variant, 'identity:non-converter');

    growthMetricsEvent($owner, $experiment, $convertingAssignment, 'order.paid', 9000);
    growthMetricsEvent($owner, $experiment, $convertingAssignment, 'order.paid', 6000);

    $metrics = OwnerContext::withOwner($owner, fn (): array => app(AggregateExperimentMetrics::class)->handle($experiment));

    expect($metrics['variants'][0]['assignments'])->toBe(2)
        ->and($metrics['variants'][0]['purchases'])->toBe(2)
        ->and($metrics['variants'][0]['conversion_rate'])->toBe(0.5);
});

it('does not infer unique converters from purchase events without assignment ids', function (): void {
    $owner = growthMetricsOwner();
    $experiment = growthMetricsExperiment($owner);

    $variant = OwnerContext::withOwner($owner, fn (): Variant => Variant::factory()->create([
        'experiment_id' => $experiment->getKey(),
        'code' => 'A',
        'name' => 'Control',
        'traffic_percentage' => 100,
        'position' => 1,
        'is_control' => true,
    ]));

    growthMetricsAssignment($owner, $experiment, $variant, 'identity:missing-assignment-a');
    growthMetricsAssignment($owner, $experiment, $variant, 'identity:missing-assignment-b');

    OwnerContext::withOwner($owner, function () use ($experiment, $variant): void {
        SignalEvent::query()->create([
            'tracked_property_id' => $experiment->tracked_property_id,
            'occurred_at' => CarbonImmutable::now(),
            'event_name' => 'order.paid',
            'event_category' => 'conversion',
            'revenue_minor' => 12000,
            'currency' => 'MYR',
            'properties' => [
                'experiment_id' => (string) $experiment->getKey(),
                'variant_id' => (string) $variant->getKey(),
            ],
        ]);

        SignalEvent::query()->create([
            'tracked_property_id' => $experiment->tracked_property_id,
            'occurred_at' => CarbonImmutable::now(),
            'event_name' => 'order.paid',
            'event_category' => 'conversion',
            'revenue_minor' => 8000,
            'currency' => 'MYR',
            'properties' => [
                'experiment_id' => (string) $experiment->getKey(),
                'variant_id' => (string) $variant->getKey(),
            ],
        ]);
    });

    $metrics = OwnerContext::withOwner($owner, fn (): array => app(AggregateExperimentMetrics::class)->handle($experiment));

    expect($metrics['totals']['purchases'])->toBe(2)
        ->and($metrics['totals']['revenue_minor'])->toBe(20000)
        ->and($metrics['variants'][0]['assignments'])->toBe(2)
        ->and($metrics['variants'][0]['purchases'])->toBe(2)
        ->and($metrics['variants'][0]['conversion_rate'])->toBe(0.0)
        ->and($metrics['winner_variant_id'])->toBe((string) $variant->getKey());
});

it('rejects aggregating metrics for experiments outside the current owner scope', function (): void {
    $ownerA = growthMetricsOwner();
    $ownerB = growthMetricsOwner();
    $experiment = growthMetricsExperiment($ownerA);

    expect(fn (): array => OwnerContext::withOwner($ownerB, fn (): array => app(AggregateExperimentMetrics::class)->handle($experiment)))
        ->toThrow(AuthorizationException::class, 'Growth experiment is not accessible in the current owner scope.');
});

it('rejects aggregating metrics for experiments tied to a foreign tracked property when growth owner scoping is disabled but signals owner scoping remains enabled', function (): void {
    config()->set('growth.features.owner.enabled', false);

    $ownerA = growthMetricsOwner();
    $ownerB = growthMetricsOwner();

    $experiment = OwnerContext::withOwner($ownerA, function (): Experiment {
        $trackedProperty = TrackedProperty::query()->create([
            'name' => 'Signals Enabled Metrics Property ' . Str::random(6),
            'slug' => 'signals-enabled-metrics-' . Str::lower(Str::random(8)),
            'write_key' => Str::random(40),
            'type' => 'website',
            'timezone' => 'UTC',
            'currency' => 'USD',
            'is_active' => true,
        ]);

        /** @var Experiment $experiment */
        $experiment = Experiment::factory()->create([
            'tracked_property_id' => $trackedProperty->getKey(),
            'status' => 'active',
        ]);

        return $experiment;
    });

    expect(fn (): array => OwnerContext::withOwner($ownerB, fn (): array => app(AggregateExperimentMetrics::class)->handle($experiment)))
        ->toThrow(AuthorizationException::class, 'Growth experiment is not accessible in the current owner scope.');
});

it('ignores signal events from another owner when signals owner scoping is disabled', function (): void {
    config()->set('signals.owner.enabled', false);

    $ownerA = growthMetricsOwner();
    $ownerB = growthMetricsOwner();
    $experiment = growthMetricsExperiment($ownerA);

    $variant = OwnerContext::withOwner($ownerA, fn (): Variant => Variant::factory()->create([
        'experiment_id' => $experiment->getKey(),
        'code' => 'A',
        'name' => 'Control',
        'traffic_percentage' => 100,
        'position' => 1,
        'is_control' => true,
    ]));

    $assignment = growthMetricsAssignment($ownerA, $experiment, $variant, 'identity:owner-filter');
    $properties = OwnerContext::withOwner($ownerA, fn (): array => app(BuildExperimentSignalProperties::class)->handle($assignment));
    $timestamp = CarbonImmutable::now();

    DB::table((new SignalEvent)->getTable())->insert([
        [
            'id' => (string) Str::uuid(),
            'tracked_property_id' => $experiment->tracked_property_id,
            'signal_session_id' => null,
            'signal_identity_id' => null,
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => (string) $ownerA->getKey(),
            'occurred_at' => $timestamp,
            'event_name' => 'order.paid',
            'event_category' => 'conversion',
            'idempotency_key' => null,
            'source_event_id' => null,
            'path' => null,
            'url' => null,
            'referrer' => null,
            'source' => null,
            'medium' => null,
            'campaign' => null,
            'content' => null,
            'term' => null,
            'revenue_minor' => 25000,
            'currency' => 'MYR',
            'properties' => json_encode($properties),
            'property_types' => null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ],
        [
            'id' => (string) Str::uuid(),
            'tracked_property_id' => $experiment->tracked_property_id,
            'signal_session_id' => null,
            'signal_identity_id' => null,
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => (string) $ownerB->getKey(),
            'occurred_at' => $timestamp,
            'event_name' => 'order.paid',
            'event_category' => 'conversion',
            'idempotency_key' => null,
            'source_event_id' => null,
            'path' => null,
            'url' => null,
            'referrer' => null,
            'source' => null,
            'medium' => null,
            'campaign' => null,
            'content' => null,
            'term' => null,
            'revenue_minor' => 99000,
            'currency' => 'MYR',
            'properties' => json_encode($properties),
            'property_types' => null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ],
    ]);

    $metrics = OwnerContext::withOwner($ownerA, fn (): array => app(AggregateExperimentMetrics::class)->handle($experiment));

    expect($metrics['totals']['assignments'])->toBe(1)
        ->and($metrics['totals']['purchases'])->toBe(1)
        ->and($metrics['totals']['revenue_minor'])->toBe(25000)
        ->and($metrics['winner_variant_id'])->toBe((string) $variant->getKey())
        ->and($metrics['variants'])->toHaveCount(1)
        ->and($metrics['variants'][0]['variant_id'])->toBe((string) $variant->getKey())
        ->and($metrics['variants'][0]['revenue_minor'])->toBe(25000);
});

it('keeps winner pending when assignments exist without qualifying winner metric data', function (): void {
    $owner = growthMetricsOwner();
    $experiment = growthMetricsExperiment($owner);

    $variant = OwnerContext::withOwner($owner, fn (): Variant => Variant::factory()->create([
        'experiment_id' => $experiment->getKey(),
        'code' => 'A',
        'name' => 'Control',
        'traffic_percentage' => 100,
        'position' => 1,
        'is_control' => true,
    ]));

    growthMetricsAssignment($owner, $experiment, $variant, 'identity:no-outcome-yet');

    $metrics = OwnerContext::withOwner($owner, fn (): array => app(AggregateExperimentMetrics::class)->handle($experiment));

    expect($metrics['totals']['assignments'])->toBe(1)
        ->and($metrics['totals']['purchases'])->toBe(0)
        ->and($metrics['winner_variant_id'])->toBeNull();
});

it('ignores revenue from signal events recorded in a different currency than the experiment currency', function (): void {
    $owner = growthMetricsOwner();
    $experiment = growthMetricsExperiment($owner);

    $variantA = OwnerContext::withOwner($owner, fn (): Variant => Variant::factory()->create([
        'experiment_id' => $experiment->getKey(),
        'code' => 'A',
        'name' => 'Control',
        'traffic_percentage' => 50,
        'position' => 1,
        'is_control' => true,
    ]));

    $variantB = OwnerContext::withOwner($owner, fn (): Variant => Variant::factory()->create([
        'experiment_id' => $experiment->getKey(),
        'code' => 'B',
        'name' => 'Challenger',
        'traffic_percentage' => 50,
        'position' => 2,
    ]));

    $assignmentA = growthMetricsAssignment($owner, $experiment, $variantA, 'identity:currency-a');
    $assignmentB = growthMetricsAssignment($owner, $experiment, $variantB, 'identity:currency-b');

    growthMetricsEvent($owner, $experiment, $assignmentA, 'order.paid', 12000, 'MYR');
    growthMetricsEvent($owner, $experiment, $assignmentB, 'order.paid', 99000, 'USD');

    $metrics = OwnerContext::withOwner($owner, fn (): array => app(AggregateExperimentMetrics::class)->handle($experiment));

    expect($metrics['currency'])->toBe('MYR')
        ->and($metrics['totals']['purchases'])->toBe(2)
        ->and($metrics['totals']['revenue_minor'])->toBe(12000)
        ->and($metrics['winner_variant_id'])->toBe((string) $variantA->getKey())
        ->and($metrics['variants'][0]['revenue_minor'])->toBe(12000)
        ->and($metrics['variants'][1]['revenue_minor'])->toBe(0);
});

it('ignores mismatched owner rows when aggregating a global experiment that is only readable in tenant context', function (): void {
    config()->set('growth.features.owner.include_global', true);

    $owner = growthMetricsOwner();

    [$experiment, $variant, $assignment] = OwnerContext::withOwner(null, function (): array {
        $trackedProperty = TrackedProperty::query()->create([
            'name' => 'Global Metrics Property ' . Str::random(6),
            'slug' => 'global-metrics-' . Str::lower(Str::random(8)),
            'write_key' => Str::random(40),
            'type' => 'website',
            'timezone' => 'UTC',
            'currency' => 'MYR',
            'is_active' => true,
            'owner_type' => null,
            'owner_id' => null,
        ]);

        /** @var Experiment $experiment */
        $experiment = Experiment::factory()->global()->create([
            'tracked_property_id' => $trackedProperty->getKey(),
            'status' => 'active',
        ]);

        /** @var Variant $variant */
        $variant = Variant::factory()->global()->create([
            'experiment_id' => $experiment->getKey(),
            'code' => 'G',
            'name' => 'Global Control',
            'traffic_percentage' => 100,
            'position' => 1,
            'is_control' => true,
        ]);

        /** @var Assignment $assignment */
        $assignment = Assignment::factory()->global()->create([
            'experiment_id' => $experiment->getKey(),
            'variant_id' => $variant->getKey(),
            'subject_key' => 'anonymous:global-good',
            'bucket' => 0,
            'assigned_at' => CarbonImmutable::now(),
            'first_exposed_at' => CarbonImmutable::now(),
            'last_seen_at' => CarbonImmutable::now(),
        ]);

        SignalEvent::query()->create([
            'tracked_property_id' => $experiment->tracked_property_id,
            'occurred_at' => CarbonImmutable::now(),
            'event_name' => 'order.paid',
            'event_category' => 'conversion',
            'revenue_minor' => 25000,
            'currency' => 'MYR',
            'properties' => app(BuildExperimentSignalProperties::class)->handle($assignment),
            'owner_type' => null,
            'owner_id' => null,
        ]);

        return [$experiment, $variant, $assignment];
    });

    $strayVariantId = (string) Str::uuid();
    $strayAssignmentId = (string) Str::uuid();
    $timestamp = CarbonImmutable::now();

    DB::table((new Variant)->getTable())->insert([
        'id' => $strayVariantId,
        'experiment_id' => $experiment->getKey(),
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => (string) $owner->getKey(),
        'code' => 'X',
        'name' => 'Stray Variant',
        'description' => null,
        'traffic_percentage' => 100,
        'position' => 99,
        'is_control' => false,
        'is_active' => true,
        'settings' => json_encode([]),
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    DB::table((new Assignment)->getTable())->insert([
        'id' => $strayAssignmentId,
        'experiment_id' => $experiment->getKey(),
        'variant_id' => $strayVariantId,
        'signal_identity_id' => null,
        'signal_session_id' => null,
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => (string) $owner->getKey(),
        'subject_key' => 'anonymous:global-stray',
        'bucket' => 0,
        'metadata' => json_encode([]),
        'assigned_at' => $timestamp,
        'first_exposed_at' => $timestamp,
        'last_seen_at' => $timestamp,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    DB::table((new SignalEvent)->getTable())->insert([
        'id' => (string) Str::uuid(),
        'tracked_property_id' => $experiment->tracked_property_id,
        'signal_session_id' => null,
        'signal_identity_id' => null,
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => (string) $owner->getKey(),
        'occurred_at' => $timestamp,
        'event_name' => 'order.paid',
        'event_category' => 'conversion',
        'idempotency_key' => null,
        'source_event_id' => null,
        'path' => null,
        'url' => null,
        'referrer' => null,
        'source' => null,
        'medium' => null,
        'campaign' => null,
        'content' => null,
        'term' => null,
        'revenue_minor' => 99999,
        'currency' => 'MYR',
        'properties' => json_encode([
            'experiment_id' => (string) $experiment->getKey(),
            'variant_id' => $strayVariantId,
            'assignment_id' => $strayAssignmentId,
        ]),
        'property_types' => null,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    $metrics = OwnerContext::withOwner($owner, fn (): array => app(AggregateExperimentMetrics::class)->handle($experiment));

    expect($metrics['totals']['assignments'])->toBe(1)
        ->and($metrics['totals']['purchases'])->toBe(1)
        ->and($metrics['totals']['revenue_minor'])->toBe(25000)
        ->and($metrics['winner_variant_id'])->toBe((string) $variant->getKey())
        ->and($metrics['variants'])->toHaveCount(1)
        ->and($metrics['variants'][0]['variant_id'])->toBe((string) $variant->getKey())
        ->and($metrics['variants'][0]['assignments'])->toBe(1)
        ->and($metrics['variants'][0]['revenue_minor'])->toBe(25000);
});

it('resolves the tracked property for readable global experiments using the experiment owner tuple', function (): void {
    config()->set('growth.features.owner.include_global', true);
    config()->set('signals.owner.include_global', false);

    $owner = growthMetricsOwner();

    [$experiment, $variant] = OwnerContext::withOwner(null, function (): array {
        $trackedProperty = TrackedProperty::query()->create([
            'name' => 'Global Aggregate Currency Property ' . Str::random(6),
            'slug' => 'global-aggregate-currency-' . Str::lower(Str::random(8)),
            'write_key' => Str::random(40),
            'type' => 'website',
            'timezone' => 'UTC',
            'currency' => 'USD',
            'is_active' => true,
            'owner_type' => null,
            'owner_id' => null,
        ]);

        /** @var Experiment $experiment */
        $experiment = Experiment::factory()->global()->create([
            'tracked_property_id' => $trackedProperty->getKey(),
            'status' => 'active',
        ]);

        /** @var Variant $variant */
        $variant = Variant::factory()->global()->create([
            'experiment_id' => $experiment->getKey(),
            'code' => 'G',
            'name' => 'Global Control',
            'traffic_percentage' => 100,
            'position' => 1,
            'is_control' => true,
        ]);

        /** @var Assignment $assignment */
        $assignment = Assignment::factory()->global()->create([
            'experiment_id' => $experiment->getKey(),
            'variant_id' => $variant->getKey(),
            'subject_key' => 'anonymous:global-currency',
            'bucket' => 0,
            'assigned_at' => CarbonImmutable::now(),
            'first_exposed_at' => CarbonImmutable::now(),
            'last_seen_at' => CarbonImmutable::now(),
        ]);

        SignalEvent::query()->create([
            'tracked_property_id' => $experiment->tracked_property_id,
            'occurred_at' => CarbonImmutable::now(),
            'event_name' => 'order.paid',
            'event_category' => 'conversion',
            'revenue_minor' => 47000,
            'currency' => 'USD',
            'properties' => app(BuildExperimentSignalProperties::class)->handle($assignment),
            'owner_type' => null,
            'owner_id' => null,
        ]);

        return [$experiment, $variant];
    });

    $metrics = OwnerContext::withOwner($owner, fn (): array => app(AggregateExperimentMetrics::class)->handle($experiment));

    expect($metrics['currency'])->toBe('USD')
        ->and($metrics['totals']['revenue_minor'])->toBe(47000)
        ->and($metrics['winner_variant_id'])->toBe((string) $variant->getKey());
});

it('aggregates readable global experiments through tracked properties when signals include_global is enabled and growth owner scoping is disabled', function (): void {
    config()->set('growth.features.owner.enabled', false);
    config()->set('signals.owner.include_global', true);

    $owner = growthMetricsOwner();

    [$experiment, $variant] = OwnerContext::withOwner(null, function (): array {
        $trackedProperty = TrackedProperty::query()->create([
            'name' => 'Global Signals Include Property ' . Str::random(6),
            'slug' => 'global-signals-include-' . Str::lower(Str::random(8)),
            'write_key' => Str::random(40),
            'type' => 'website',
            'timezone' => 'UTC',
            'currency' => 'USD',
            'is_active' => true,
            'owner_type' => null,
            'owner_id' => null,
        ]);

        /** @var Experiment $experiment */
        $experiment = Experiment::factory()->global()->create([
            'tracked_property_id' => $trackedProperty->getKey(),
            'status' => 'active',
        ]);

        /** @var Variant $variant */
        $variant = Variant::factory()->global()->create([
            'experiment_id' => $experiment->getKey(),
            'code' => 'GI',
            'name' => 'Global Include Variant',
            'traffic_percentage' => 100,
            'position' => 1,
            'is_control' => true,
        ]);

        /** @var Assignment $assignment */
        $assignment = Assignment::factory()->global()->create([
            'experiment_id' => $experiment->getKey(),
            'variant_id' => $variant->getKey(),
            'subject_key' => 'anonymous:global-signals-include',
            'bucket' => 0,
            'assigned_at' => CarbonImmutable::now(),
            'first_exposed_at' => CarbonImmutable::now(),
            'last_seen_at' => CarbonImmutable::now(),
        ]);

        SignalEvent::query()->create([
            'tracked_property_id' => $experiment->tracked_property_id,
            'occurred_at' => CarbonImmutable::now(),
            'event_name' => 'order.paid',
            'event_category' => 'conversion',
            'revenue_minor' => 51000,
            'currency' => 'USD',
            'properties' => app(BuildExperimentSignalProperties::class)->handle($assignment),
            'owner_type' => null,
            'owner_id' => null,
        ]);

        return [$experiment, $variant];
    });

    $metrics = OwnerContext::withOwner($owner, fn (): array => app(AggregateExperimentMetrics::class)->handle($experiment));

    expect($metrics['currency'])->toBe('USD')
        ->and($metrics['totals']['revenue_minor'])->toBe(51000)
        ->and($metrics['winner_variant_id'])->toBe((string) $variant->getKey());
});

it('rejects aggregating metrics when an experiment tracked property drifts outside the experiment owner tuple', function (): void {
    $ownerA = growthMetricsOwner();
    $ownerB = growthMetricsOwner();
    $experiment = growthMetricsExperiment($ownerA);

    $foreignTrackedProperty = OwnerContext::withOwner($ownerB, fn (): TrackedProperty => TrackedProperty::query()->create([
        'name' => 'Foreign Drifted Metrics Property ' . Str::random(6),
        'slug' => 'foreign-drifted-metrics-' . Str::lower(Str::random(8)),
        'write_key' => Str::random(40),
        'type' => 'website',
        'timezone' => 'UTC',
        'currency' => 'USD',
        'is_active' => true,
    ]));

    DB::table($experiment->getTable())
        ->where('id', $experiment->getKey())
        ->update(['tracked_property_id' => $foreignTrackedProperty->getKey()]);

    expect(fn (): array => OwnerContext::withOwner($ownerA, fn (): array => app(AggregateExperimentMetrics::class)->handle($experiment)))
        ->toThrow(AuthorizationException::class, 'Growth experiment is not accessible in the current owner scope.');
});
