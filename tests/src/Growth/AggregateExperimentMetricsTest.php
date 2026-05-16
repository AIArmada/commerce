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
    return OwnerContext::withOwner($owner, function (): Experiment {
        $trackedProperty = TrackedProperty::query()->create([
            'name' => 'Metrics Property ' . Str::random(6),
            'slug' => 'metrics-property-' . Str::lower(Str::random(8)),
            'write_key' => Str::random(40),
            'type' => 'website',
            'timezone' => 'UTC',
            'currency' => 'MYR',
            'is_active' => true,
        ]);

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

function growthMetricsEvent(User $owner, Experiment $experiment, Assignment $assignment, string $eventName, int $revenueMinor = 0): SignalEvent
{
    $properties = app(BuildExperimentSignalProperties::class)->handle($assignment);

    return OwnerContext::withOwner($owner, fn (): SignalEvent => SignalEvent::query()->create([
        'tracked_property_id' => $experiment->tracked_property_id,
        'occurred_at' => CarbonImmutable::now(),
        'event_name' => $eventName,
        'event_category' => str_contains($eventName, 'checkout') ? 'checkout' : 'conversion',
        'revenue_minor' => $revenueMinor,
        'currency' => 'MYR',
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

    $metrics = app(AggregateExperimentMetrics::class)->handle($experiment);

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

    $metrics = app(AggregateExperimentMetrics::class)->handle($experiment);

    expect($metrics['totals']['purchases'])->toBe(1)
        ->and($metrics['totals']['revenue_minor'])->toBe(45000)
        ->and($metrics['winner_variant_id'])->toBe((string) $variantA->getKey());
});