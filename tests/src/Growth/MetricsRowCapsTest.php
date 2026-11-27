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
use AIArmada\Signals\Features\Ingestion\Models\SignalEvent;
use AIArmada\Signals\Features\Ingestion\Models\TrackedProperty;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ReflectionProperty;

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

    it('keeps truncated shared batch windows out of single-experiment aggregation', function (): void {
        config()->set('growth.metrics.max_assignment_rows', 1);
        config()->set('growth.metrics.max_event_rows', 1);

        $owner = metricsCapOwner();
        $firstExperiment = metricsCapExperiment($owner);
        $secondExperiment = metricsCapExperiment($owner);
        $firstAssignment = metricsCapAssignment($owner, $firstExperiment, 'identity:shared-cap-1');
        $secondAssignment = metricsCapAssignment($owner, $secondExperiment, 'identity:shared-cap-2');
        $firstEvent = metricsCapEvent($owner, $firstExperiment, $firstAssignment, 'order.paid', 1000);
        $secondEvent = metricsCapEvent($owner, $secondExperiment, $secondAssignment, 'order.paid', 2000);

        OwnerContext::withOwner($owner, function () use ($firstAssignment, $firstEvent): void {
            Assignment::query()->whereKey($firstAssignment->getKey())->update(['assigned_at' => CarbonImmutable::now()->subDay()]);
            SignalEvent::query()->whereKey($firstEvent->getKey())->update(['occurred_at' => CarbonImmutable::now()->subDay()]);
        });

        $batch = OwnerContext::withOwner(
            $owner,
            fn (): array => app(AggregateExperimentMetrics::class)->handleMany(
                new EloquentCollection([$firstExperiment, $secondExperiment]),
            ),
        );

        $secondBatchResult = $batch['results'][(string) $secondExperiment->getKey()];

        expect($secondBatchResult['truncated'])->toBeTrue()
            ->and($secondBatchResult['totals']['assignments'])->toBe(0)
            ->and($secondBatchResult['totals']['purchases'])->toBe(0)
            ->and($batch['assignment_count'])->toBe(1);

        $single = OwnerContext::withOwner(
            $owner,
            fn (): array => app(AggregateExperimentMetrics::class)->handle($secondExperiment),
        );

        expect($single['truncated'])->toBeFalse()
            ->and($single['totals']['assignments'])->toBe(1)
            ->and($single['totals']['purchases'])->toBe(1);
    });
});

it('uses stable event ids to break timestamp ties in single and batch windows', function (): void {
    config()->set('growth.metrics.max_event_rows', 1);

    $owner = metricsCapOwner();
    $experiment = metricsCapExperiment($owner);
    $assignment = metricsCapAssignment($owner, $experiment, 'identity:stable-window');
    $first = metricsCapEvent($owner, $experiment, $assignment, 'order.paid', 1000);
    $second = metricsCapEvent($owner, $experiment, $assignment, 'order.paid', 2000);

    OwnerContext::withOwner($owner, function () use ($first, $second): void {
        SignalEvent::query()->whereKey([$first->id, $second->id])->update(['occurred_at' => '2026-01-01 00:00:00']);
    });

    $expected = strcmp($first->id, $second->id) < 0 ? 1000 : 2000;
    $single = OwnerContext::withOwner($owner, fn (): array => app(AggregateExperimentMetrics::class)->handle($experiment));
    $batch = OwnerContext::withOwner($owner, fn (): array => app(AggregateExperimentMetrics::class)->handleMany(new EloquentCollection([$experiment])));

    expect($single['totals']['revenue_minor'])->toBe($expected)
        ->and($batch['results'][(string) $experiment->getKey()]['totals']['revenue_minor'])->toBe($expected);
});

it('reuses identical batch snapshots without repeating queries', function (): void {
    $owner = metricsCapOwner();
    $experiment = metricsCapExperiment($owner);
    $assignment = metricsCapAssignment($owner, $experiment, 'identity:batch-cache');
    metricsCapEvent($owner, $experiment, $assignment, 'order.paid', 1000);

    $consoleProperty = new ReflectionProperty(Application::class, 'isRunningInConsole');
    $consoleProperty->setAccessible(true);
    $wasRunningInConsole = $consoleProperty->getValue(app());

    app()->instance('request', Request::create('/growth-batch-cache'));
    $consoleProperty->setValue(app(), false);

    $queries = 0;
    DB::listen(static function () use (&$queries): void {
        $queries++;
    });

    try {
        $first = OwnerContext::withOwner(
            $owner,
            fn (): array => app(AggregateExperimentMetrics::class)->handleMany(new EloquentCollection([$experiment])),
        );
        $firstQueryCount = $queries;
        $second = OwnerContext::withOwner(
            $owner,
            fn (): array => app(AggregateExperimentMetrics::class)->handleMany(new EloquentCollection([$experiment])),
        );

        expect($firstQueryCount)->toBeGreaterThan(0)
            ->and($queries)->toBe($firstQueryCount)
            ->and($second)->toBe($first);
    } finally {
        $consoleProperty->setValue(app(), $wasRunningInConsole);
    }
});
