<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Growth;

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Growth\Actions\AggregateExperimentMetrics;
use AIArmada\Growth\Actions\BuildExperimentSignalProperties;
use AIArmada\Growth\Enums\ExperimentStatus;
use AIArmada\Growth\Models\Assignment;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use AIArmada\Growth\Queries\MetricsCalculator;
use AIArmada\Signals\Features\Ingestion\Models\SignalEvent;
use AIArmada\Signals\Features\Ingestion\Models\TrackedProperty;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function analyticsOwner(): User
{
    return User::query()->create([
        'name' => 'Analytics Owner ' . Str::random(6),
        'email' => 'analytics-' . Str::lower(Str::random(8)) . '@example.com',
        'password' => 'secret',
    ]);
}

function analyticsProperty(User $owner): TrackedProperty
{
    return OwnerContext::withOwner($owner, fn (): TrackedProperty => TrackedProperty::query()->create([
        'name' => 'Analytics Property ' . Str::random(6),
        'slug' => 'analytics-' . Str::lower(Str::random(8)),
        'write_key' => Str::random(40),
        'type' => 'website',
        'timezone' => 'UTC',
        'currency' => 'MYR',
        'is_active' => true,
    ]));
}

function analyticsExperiment(User $owner, TrackedProperty $property, ExperimentStatus $status = ExperimentStatus::Active): Experiment
{
    return OwnerContext::withOwner($owner, fn (): Experiment => Experiment::factory()->create([
        'tracked_property_id' => $property->getKey(),
        'status' => $status,
    ]));
}

function analyticsAssignmentWithEvent(User $owner, Experiment $experiment): Assignment
{
    return OwnerContext::withOwner($owner, function () use ($experiment): Assignment {
        /** @var Variant $variant */
        $variant = Variant::factory()->create([
            'experiment_id' => $experiment->getKey(),
            'code' => 'A',
            'name' => 'Control',
            'traffic_percentage' => 100,
            'position' => 1,
            'is_control' => true,
        ]);

        /** @var Assignment $assignment */
        $assignment = Assignment::query()->create([
            'experiment_id' => $experiment->getKey(),
            'variant_id' => $variant->getKey(),
            'subject_key' => 'anonymous:analytics-' . Str::lower(Str::random(8)),
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
            'revenue_minor' => 12000,
            'currency' => 'MYR',
            'properties' => app(BuildExperimentSignalProperties::class)->handle($assignment),
        ]);

        return $assignment;
    });
}

function clearAnalyticsCaches(): void
{
    request()->attributes->remove('growth.aggregate_metrics');
    request()->attributes->remove('growth.aggregate_metrics.batch');
}

describe('Analytics consistency and bounded reads', function (): void {
    beforeEach(function (): void {
        config()->set('growth.features.owner.enabled', true);
        config()->set('signals.owner.enabled', true);
    });

    it('aggregates historical experiments regardless of lifecycle status', function (): void {
        $owner = analyticsOwner();
        $experiment = analyticsExperiment($owner, analyticsProperty($owner));

        analyticsAssignmentWithEvent($owner, $experiment);

        foreach ([ExperimentStatus::Paused, ExperimentStatus::Concluded, ExperimentStatus::Archived] as $status) {
            OwnerContext::withOwner($owner, function () use ($experiment, $status): void {
                $experiment->transitionTo($status)->save();
            });

            clearAnalyticsCaches();
            $single = OwnerContext::withOwner($owner, fn (): array => app(AggregateExperimentMetrics::class)->handle($experiment));

            clearAnalyticsCaches();
            $batch = OwnerContext::withOwner(
                $owner,
                fn (): array => app(AggregateExperimentMetrics::class)->handleMany(new EloquentCollection([$experiment])),
            );

            expect($single['totals']['revenue_minor'])->toBe(12000)
                ->and($single['totals'])->toBe($batch['results'][(string) $experiment->getKey()]['totals'])
                ->and($single['winner_variant_id'])->toBe($batch['results'][(string) $experiment->getKey()]['winner_variant_id']);
        }
    });

    it('returns an empty batch without querying', function (): void {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $batch = app(AggregateExperimentMetrics::class)->handleMany(new EloquentCollection);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        expect($batch)->toBe(['results' => [], 'variant_count' => 0, 'assignment_count' => 0])
            ->and($queries)->toBe([]);
    });

    it('projects only the assignment columns required by metrics', function (): void {
        $owner = analyticsOwner();
        $experiment = analyticsExperiment($owner, analyticsProperty($owner));

        analyticsAssignmentWithEvent($owner, $experiment);

        DB::flushQueryLog();
        DB::enableQueryLog();

        OwnerContext::withOwner($owner, fn (): array => app(AggregateExperimentMetrics::class)->handle($experiment));

        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        $assignmentSelect = $queries->first(
            static fn (string $sql): bool => str_contains(mb_strtolower($sql), mb_strtolower((new Assignment)->getTable()))
                && str_starts_with(mb_ltrim(mb_strtolower($sql)), 'select'),
        );

        expect($assignmentSelect)->toBeString()
            ->not->toContain('metadata')
            ->not->toContain('subject_key');
    });

    it('indexes event contexts once per experiment for batched aggregation', function (): void {
        $events = new Collection([
            (new SignalEvent)->newFromBuilder([
                'id' => (string) Str::uuid(),
                'properties' => json_encode([
                    'experiment_contexts' => [
                        ['experiment_id' => 'experiment-a', 'variant_id' => 'variant-a'],
                        ['experiment_id' => 'experiment-b', 'variant_id' => 'variant-b'],
                    ],
                ], JSON_THROW_ON_ERROR),
            ]),
            (new SignalEvent)->newFromBuilder([
                'id' => (string) Str::uuid(),
                'properties' => json_encode(['experiment_id' => 'experiment-a', 'variant_id' => 'variant-a'], JSON_THROW_ON_ERROR),
            ]),
        ]);

        $indexed = app(MetricsCalculator::class)->indexEventsByVariantForExperiments(
            $events,
            ['experiment-a', 'experiment-b'],
        );

        expect($indexed['experiment-a']['variant-a'])->toHaveCount(2)
            ->and($indexed['experiment-b']['variant-b'])->toHaveCount(1);
    });
});
