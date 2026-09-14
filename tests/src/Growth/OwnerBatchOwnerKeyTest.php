<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Growth;

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\NullOwnerResolver;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Growth\Actions\ResolveExperimentAssignment;
use AIArmada\Growth\Enums\ExperimentStatus;
use AIArmada\Growth\Enums\VariantStatus;
use AIArmada\Growth\Models\Assignment;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use AIArmada\Signals\Models\TrackedProperty;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

function batchKeyOwner(): User
{
    return User::query()->create([
        'name' => 'Batch Key Owner ' . Str::random(6),
        'email' => 'batch-key-' . Str::lower(Str::random(8)) . '@example.com',
        'password' => 'secret',
    ]);
}

function batchKeyExperiment(User $owner, ExperimentStatus $status = ExperimentStatus::Active): Experiment
{
    return OwnerContext::withOwner($owner, function () use ($status): Experiment {
        $trackedProperty = TrackedProperty::query()->create([
            'name' => 'Batch Key Property ' . Str::random(6),
            'slug' => 'batch-key-' . Str::lower(Str::random(8)),
            'write_key' => Str::random(40),
            'type' => 'website',
            'timezone' => 'UTC',
            'currency' => 'MYR',
            'is_active' => true,
        ]);

        /** @var Experiment $experiment */
        $experiment = Experiment::factory()->create([
            'tracked_property_id' => $trackedProperty->getKey(),
            'status' => $status,
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

describe('Growth owner batch commands', function (): void {
    beforeEach(function (): void {
        config()->set('growth.features.owner.enabled', true);
        config()->set('signals.owner.enabled', true);

        // Console has no ambient web owner; without this the fixed test
        // resolver short-circuits discovery and the commands silently
        // process nothing.
        app()->instance(OwnerResolverInterface::class, new NullOwnerResolver);
    });

    it('recomputes assignments per owner when growth owner mode is enabled', function (): void {
        $owner = batchKeyOwner();
        $experiment = batchKeyExperiment($owner);

        [$variant, $bucket] = OwnerContext::withOwner(
            $owner,
            fn (): array => app(ResolveExperimentAssignment::class)->variantForSubject($experiment, 'identity:batch-1')
        );

        OwnerContext::withOwner($owner, fn (): Assignment => Assignment::query()->create([
            'experiment_id' => $experiment->getKey(),
            'variant_id' => $variant->getKey(),
            'subject_key' => 'identity:batch-1',
            'bucket' => $bucket,
            'assigned_at' => CarbonImmutable::now(),
            'first_exposed_at' => CarbonImmutable::now(),
            'last_seen_at' => CarbonImmutable::now(),
        ]));

        $this->artisan('growth:recompute-assignments', ['--dry-run' => true])
            ->expectsOutputToContain('Would change: 0; would quarantine: 0; unchanged: 1')
            ->assertSuccessful();
    });

    it('archives concluded experiments per owner when growth owner mode is enabled', function (): void {
        $owner = batchKeyOwner();
        $experiment = batchKeyExperiment($owner, ExperimentStatus::Concluded);

        OwnerContext::withOwner($owner, fn (): int => Experiment::query()->whereKey($experiment->getKey())->update([
            'updated_at' => CarbonImmutable::now()->subDays(120),
        ]));

        $this->artisan('growth:archive-experiments')
            ->expectsOutputToContain('Experiments archived: 1')
            ->assertSuccessful();

        expect($experiment->fresh()->status)->toBe(ExperimentStatus::Archived);
    });

    it('leaves experiments untouched on archive dry runs', function (): void {
        $owner = batchKeyOwner();
        $experiment = batchKeyExperiment($owner, ExperimentStatus::Concluded);

        OwnerContext::withOwner($owner, fn (): int => Experiment::query()->whereKey($experiment->getKey())->update([
            'updated_at' => CarbonImmutable::now()->subDays(120),
        ]));

        $this->artisan('growth:archive-experiments', ['--dry-run' => true])
            ->expectsOutputToContain('Experiments archived: 1')
            ->assertSuccessful();

        expect($experiment->fresh()->status)->toBe(ExperimentStatus::Concluded);
    });

    it('clamps negative archive lookback windows instead of archiving everything', function (): void {
        $this->travelTo(CarbonImmutable::parse('2026-09-01 12:00:00'));

        $owner = batchKeyOwner();
        $experiment = batchKeyExperiment($owner, ExperimentStatus::Concluded);

        // A negative window means "newer than now plus five days" unclamped,
        // which would archive every concluded experiment; clamped to zero it
        // archives nothing updated at or after the frozen now.
        try {
            $this->artisan('growth:archive-experiments', ['--older-than' => '-5'])
                ->assertSuccessful();

            expect($experiment->fresh()->status)->toBe(ExperimentStatus::Concluded);
        } finally {
            $this->travelBack();
        }
    });
});
