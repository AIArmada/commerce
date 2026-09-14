<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Growth;

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
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Str;

function metricsCapOwner(): User
{
    return User::query()->create([
        'name' => 'Metrics Cap Owner ' . Str::random(6),
        'email' => 'metrics-cap-' . Str::lower(Str::random(8)) . '@example.com',
        'password' => 'secret',
    ]);
}

function metricsCapExperiment(User $owner): Experiment
{
    return OwnerContext::withOwner($owner, function (): Experiment {
        $trackedProperty = TrackedProperty::query()->create([
            'name' => 'Metrics Cap Property ' . Str::random(6),
            'slug' => 'metrics-cap-' . Str::lower(Str::random(8)),
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

        Variant::factory()->create([
            'experiment_id' => $experiment->getKey(),
            'code' => 'A',
            'name' => 'Control',
            'traffic_percentage' => 100,
            'position' => 1,
            'is_control' => true,
        ]);

        return $experiment->fresh(['variants', 'trackedProperty']) ?? $experiment;
    });
}

function metricsCapAssignment(User $owner, Experiment $experiment, string $subjectKey): Assignment
{
    $variant = $experiment->variants->firstWhere('code', 'A');

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

function metricsCapEvent(User $owner, Experiment $experiment, Assignment $assignment, string $eventName, int $revenueMinor = 0, string $currency = 'MYR'): SignalEvent
{
    $properties = OwnerContext::withOwner($owner, fn (): array => app(BuildExperimentSignalProperties::class)->handle($assignment));

    return OwnerContext::withOwner($owner, fn (): SignalEvent => SignalEvent::query()->create([
        'tracked_property_id' => $experiment->tracked_property_id,
        'occurred_at' => CarbonImmutable::now(),
        'event_name' => $eventName,
        'event_category' => 'conversion',
        'revenue_minor' => $revenueMinor,
        'currency' => $currency,
        'properties' => $properties,
    ]));
}

describe('Metrics row windows', function (): void {
    it('caps single-experiment aggregation and flags truncation', function (): void {
        config()->set('growth.metrics.max_assignment_rows', 2);
        config()->set('growth.metrics.max_event_rows', 2);

        $owner = metricsCapOwner();
        $experiment = metricsCapExperiment($owner);

        $first = metricsCapAssignment($owner, $experiment, 'identity:cap-1');
        metricsCapAssignment($owner, $experiment, 'identity:cap-2');
        metricsCapAssignment($owner, $experiment, 'identity:cap-3');

        metricsCapEvent($owner, $experiment, $first, 'order.paid', 1000);
        metricsCapEvent($owner, $experiment, $first, 'order.paid', 2000);
        metricsCapEvent($owner, $experiment, $first, 'order.paid', 4000);

        $metrics = OwnerContext::withOwner($owner, fn (): array => app(AggregateExperimentMetrics::class)->handle($experiment));

        expect($metrics['truncated'])->toBeTrue()
            ->and($metrics['totals']['assignments'])->toBe(2)
            ->and($metrics['totals']['purchases'])->toBe(2);
    });

    it('reports untruncated metrics inside the configured windows', function (): void {
        $owner = metricsCapOwner();
        $experiment = metricsCapExperiment($owner);

        $assignment = metricsCapAssignment($owner, $experiment, 'identity:cap-small');
        metricsCapEvent($owner, $experiment, $assignment, 'order.paid', 1000);

        $metrics = OwnerContext::withOwner($owner, fn (): array => app(AggregateExperimentMetrics::class)->handle($experiment));

        expect($metrics['truncated'])->toBeFalse()
            ->and($metrics['totals']['assignments'])->toBe(1)
            ->and($metrics['totals']['purchases'])->toBe(1);
    });

    it('caps batched aggregation legs and flags the batch', function (): void {
        config()->set('growth.metrics.max_assignment_rows', 1);
        config()->set('growth.metrics.max_event_rows', 1);

        $owner = metricsCapOwner();
        $experiment = metricsCapExperiment($owner);

        $first = metricsCapAssignment($owner, $experiment, 'identity:batch-cap-1');
        metricsCapAssignment($owner, $experiment, 'identity:batch-cap-2');

        metricsCapEvent($owner, $experiment, $first, 'order.paid', 1000);
        metricsCapEvent($owner, $experiment, $first, 'order.paid', 2000);

        $batch = OwnerContext::withOwner(
            $owner,
            fn (): array => app(AggregateExperimentMetrics::class)->handleMany(new EloquentCollection([$experiment]))
        );

        $result = $batch['results'][(string) $experiment->getKey()];

        expect($result['truncated'])->toBeTrue()
            ->and($result['totals']['assignments'])->toBe(1)
            ->and($batch['assignment_count'])->toBe(1);
    });
});
