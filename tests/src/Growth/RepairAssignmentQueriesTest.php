<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Growth;

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Growth\Actions\ResolveExperimentAssignment;
use AIArmada\Growth\Enums\ExperimentStatus;
use AIArmada\Growth\Enums\VariantStatus;
use AIArmada\Growth\Models\Assignment;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use AIArmada\Signals\Models\TrackedProperty;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function repairQueryOwner(string $label): User
{
    return User::query()->create([
        'name' => $label . ' ' . Str::random(6),
        'email' => Str::lower(Str::random(10)) . '@example.com',
        'password' => 'secret',
    ]);
}

function repairQueryExperiment(User $owner): Experiment
{
    return OwnerContext::withOwner($owner, function (): Experiment {
        $trackedProperty = TrackedProperty::query()->create([
            'name' => 'Repair Query Property ' . Str::random(6),
            'slug' => 'repair-query-' . Str::lower(Str::random(8)),
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
            'traffic_percentage' => 50,
            'position' => 1,
            'is_control' => true,
            'status' => VariantStatus::Active,
        ]);

        Variant::factory()->create([
            'experiment_id' => $experiment->getKey(),
            'code' => 'B',
            'name' => 'Challenger',
            'traffic_percentage' => 50,
            'position' => 2,
            'is_control' => false,
            'status' => VariantStatus::Active,
        ]);

        return $experiment->fresh(['variants', 'trackedProperty']) ?? $experiment;
    });
}

describe('Variant resolution scoping and run caching', function (): void {
    beforeEach(function (): void {
        config()->set('growth.features.owner.enabled', true);
        config()->set('signals.owner.enabled', true);
    });

    it('queries active variants once per experiment per resolver run', function (): void {
        $owner = repairQueryOwner('Repair Query Owner');
        $experiment = repairQueryExperiment($owner);

        foreach (['identity:q-1', 'identity:q-2', 'identity:q-3'] as $subjectKey) {
            OwnerContext::withOwner($owner, fn (): Assignment => Assignment::query()->create([
                'experiment_id' => $experiment->getKey(),
                'variant_id' => $experiment->variants->first()->getKey(),
                'subject_key' => $subjectKey,
                'bucket' => 0,
                'assigned_at' => CarbonImmutable::now(),
                'first_exposed_at' => CarbonImmutable::now(),
                'last_seen_at' => CarbonImmutable::now(),
            ]));
        }

        $resolver = app(ResolveExperimentAssignment::class);
        $variantSelects = 0;

        DB::listen(function ($query) use (&$variantSelects): void {
            $sql = mb_ltrim($query->sql);

            if (str_starts_with($sql, 'select') && str_contains($query->sql, 'growth_variants')) {
                $variantSelects++;
            }
        });

        OwnerContext::withOwner($owner, function () use ($resolver, $experiment): void {
            $resolver->variantForSubject($experiment, 'identity:q-1');
            $resolver->variantForSubject($experiment, 'identity:q-2');
            $resolver->variantForSubject($experiment, 'identity:q-3');
        });

        expect($variantSelects)->toBe(1);
    });

    it('rejects variant resolution for experiments outside the owner scope', function (): void {
        $ownerA = repairQueryOwner('Repair Owner A');
        $ownerB = repairQueryOwner('Repair Owner B');
        $experimentB = repairQueryExperiment($ownerB);

        $resolver = app(ResolveExperimentAssignment::class);

        expect(fn (): array => OwnerContext::withOwner(
            $ownerA,
            fn (): array => $resolver->variantForSubject($experimentB, 'identity:foreign')
        ))->toThrow(AuthorizationException::class);
    });

    it('resolves variants for experiments inside the owner scope', function (): void {
        $owner = repairQueryOwner('Repair Owner');
        $experiment = repairQueryExperiment($owner);

        [$variant, $bucket] = OwnerContext::withOwner(
            $owner,
            fn (): array => app(ResolveExperimentAssignment::class)->variantForSubject($experiment, 'identity:home')
        );

        expect($variant)->toBeInstanceOf(Variant::class)
            ->and($variant->experiment_id)->toBe($experiment->getKey())
            ->and($bucket)->toBeInt()->toBeGreaterThanOrEqual(0)->toBeLessThan(100);
    });
});
