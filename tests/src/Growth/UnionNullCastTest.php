<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Growth;

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Growth\Actions\AggregateExperimentMetrics;
use AIArmada\Growth\Enums\ExperimentStatus;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use AIArmada\Signals\Models\TrackedProperty;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ReflectionMethod;

function unionCastOwner(): User
{
    return User::query()->create([
        'name' => 'Union Cast Owner ' . Str::random(6),
        'email' => 'union-cast-' . Str::lower(Str::random(8)) . '@example.com',
        'password' => 'secret',
    ]);
}

function unionNullCastsForDriver(?string $driver): array
{
    $method = new ReflectionMethod(AggregateExperimentMetrics::class, 'unionNullCasts');
    $method->setAccessible(true);

    return $method->invoke(app(AggregateExperimentMetrics::class), $driver);
}

describe('Batch union null casts', function (): void {
    it('emits postgres casts for typed union columns', function (): void {
        $casts = unionNullCastsForDriver('pgsql');

        expect($casts['uuid'])->toBe('CAST(NULL AS uuid)')
            ->and($casts['timestamptz'])->toBe('CAST(NULL AS timestamptz)')
            ->and($casts['bigint'])->toBe('CAST(NULL AS bigint)')
            ->and($casts['json'])->toStartWith('CAST(NULL AS ');
    });

    it('emits mysql-compatible casts without a json cast', function (): void {
        $casts = unionNullCastsForDriver('mysql');

        expect($casts['uuid'])->toBe('CAST(NULL AS char(36))')
            ->and($casts['timestamptz'])->toBe('CAST(NULL AS datetime)')
            ->and($casts['bigint'])->toBe('CAST(NULL AS signed)')
            ->and($casts['json'])->toBe('NULL');
    });

    it('emits bare nulls on sqlite and runs the batched union without casts', function (): void {
        expect(unionNullCastsForDriver('sqlite'))->toBe([
            'uuid' => 'NULL',
            'timestamptz' => 'NULL',
            'bigint' => 'NULL',
            'json' => 'NULL',
        ]);

        $owner = unionCastOwner();

        $experiment = OwnerContext::withOwner($owner, function (): Experiment {
            $trackedProperty = TrackedProperty::query()->create([
                'name' => 'Union Cast Property ' . Str::random(6),
                'slug' => 'union-cast-' . Str::lower(Str::random(8)),
                'write_key' => Str::random(40),
                'type' => 'website',
                'timezone' => 'UTC',
                'currency' => 'MYR',
                'is_active' => true,
            ]);

            /** @var Experiment $experiment */
            $experiment = Experiment::factory()->create([
                'tracked_property_id' => $trackedProperty->getKey(),
                'status' => ExperimentStatus::Active,
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

        $unionSql = [];

        DB::listen(function ($query) use (&$unionSql): void {
            if (str_contains($query->sql, 'union all')) {
                $unionSql[] = $query->sql;
            }
        });

        $batch = OwnerContext::withOwner(
            $owner,
            fn (): array => app(AggregateExperimentMetrics::class)->handleMany(new EloquentCollection([$experiment]))
        );

        expect($unionSql)->not->toBeEmpty()
            ->and(implode("\n", $unionSql))->not->toContain('CAST(')
            ->and($batch['results'])->toHaveKey((string) $experiment->getKey());
    });
});
