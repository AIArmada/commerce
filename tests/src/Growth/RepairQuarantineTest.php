<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Growth;

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\NullOwnerResolver;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Growth\Actions\RecomputeExperimentAssignments;
use AIArmada\Growth\Actions\RepairExperimentAssignment;
use AIArmada\Growth\Actions\ResolveExperimentAssignment;
use AIArmada\Growth\Enums\ExperimentStatus;
use AIArmada\Growth\Models\Assignment;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use AIArmada\Signals\Features\Ingestion\Models\TrackedProperty;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function quarantineOwner(string $label): User
{
    return User::query()->create([
        'name' => 'Quarantine Owner ' . $label . ' ' . Str::random(6),
        'email' => 'quarantine-' . Str::lower($label) . '-' . Str::lower(Str::random(8)) . '@example.com',
        'password' => 'secret',
    ]);
}

function quarantineExperiment(User $owner): Experiment
{
    return OwnerContext::withOwner($owner, function (): Experiment {
        $property = TrackedProperty::query()->create([
            'name' => 'Quarantine Property ' . Str::random(6),
            'slug' => 'quarantine-' . Str::lower(Str::random(8)),
            'write_key' => Str::random(40),
            'type' => 'website',
            'timezone' => 'UTC',
            'currency' => 'MYR',
            'is_active' => true,
        ]);

        /** @var Experiment $experiment */
        $experiment = Experiment::factory()->create([
            'tracked_property_id' => $property->getKey(),
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

        return $experiment;
    });
}

function quarantineInsertOrphanAssignment(User $owner, string $label): Assignment
{
    $id = (string) Str::uuid();
    $timestamp = CarbonImmutable::now();

    DB::table((new Assignment)->getTable())->insert([
        'id' => $id,
        'experiment_id' => (string) Str::uuid(),
        'variant_id' => (string) Str::uuid(),
        'signal_identity_id' => null,
        'signal_session_id' => null,
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => (string) $owner->getKey(),
        'subject_key' => 'anonymous:' . $label,
        'bucket' => 0,
        'metadata' => null,
        'assigned_at' => $timestamp,
        'first_exposed_at' => $timestamp,
        'last_seen_at' => $timestamp,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    return Assignment::query()->withoutOwnerScope()->findOrFail($id);
}

describe('Assignment repair quarantine', function (): void {
    beforeEach(function (): void {
        config()->set('growth.features.owner.enabled', true);
        config()->set('signals.owner.enabled', true);
    });

    it('quarantines missing parents in dry runs without persisting metadata', function (): void {
        $owner = quarantineOwner('Dry Run');
        $assignment = quarantineInsertOrphanAssignment($owner, 'orphan-dry-run');

        $result = OwnerContext::withOwner(
            $owner,
            fn (): string => app(RepairExperimentAssignment::class)->handle($assignment, false),
        );

        expect($result)->toBe(RepairExperimentAssignment::QUARANTINED)
            ->and($assignment->fresh()?->metadata)->toBeNull();
    });

    it('quarantines missing and drifted parents in live runs', function (): void {
        $ownerA = quarantineOwner('Drift A');
        $ownerB = quarantineOwner('Drift B');
        $experiment = quarantineExperiment($ownerA);

        $assignment = OwnerContext::withOwner(
            $ownerA,
            fn (): Assignment => app(ResolveExperimentAssignment::class)->handle($experiment, anonymousId: 'drift-live'),
        );

        DB::table($experiment->getTable())
            ->where('id', $experiment->getKey())
            ->update([
                'owner_type' => $ownerB->getMorphClass(),
                'owner_id' => (string) $ownerB->getKey(),
            ]);

        $driftedResult = OwnerContext::withOwner(
            $ownerA,
            fn (): string => app(RepairExperimentAssignment::class)->handle($assignment),
        );

        expect($driftedResult)->toBe(RepairExperimentAssignment::QUARANTINED)
            ->and($assignment->fresh()?->metadata['repair_quarantine']['reason'] ?? null)->toBe('inaccessible_parent');

        $orphanOwner = quarantineOwner('Missing');
        $orphan = quarantineInsertOrphanAssignment($orphanOwner, 'orphan-live');

        $orphanResult = OwnerContext::withOwner(
            $orphanOwner,
            fn (): string => app(RepairExperimentAssignment::class)->handle($orphan),
        );

        expect($orphanResult)->toBe(RepairExperimentAssignment::QUARANTINED)
            ->and($orphan->fresh()?->metadata['repair_quarantine']['reason'] ?? null)->toBe('inaccessible_parent');
    });

    it('recomputes visible assignments when only Signals ownership is enabled', function (): void {
        config()->set('growth.features.owner.enabled', false);

        app()->instance(OwnerResolverInterface::class, new NullOwnerResolver);

        $owner = quarantineOwner('Signals Only');
        $experiment = quarantineExperiment($owner);

        OwnerContext::withOwner(
            $owner,
            fn (): Assignment => app(ResolveExperimentAssignment::class)->handle($experiment, anonymousId: 'signals-only-valid'),
        );

        $counts = app(RecomputeExperimentAssignments::class)->handle(false);

        expect($counts['unchanged'])->toBe(1)
            ->and($counts['quarantined'])->toBe(0)
            ->and($counts['changed'])->toBe(0);
    });

    it('continues cross-owner batches after quarantining a broken parent', function (bool $signalsOwnerEnabled): void {
        app()->instance(OwnerResolverInterface::class, new NullOwnerResolver);

        $ownerA = quarantineOwner('Batch A');
        $ownerB = quarantineOwner('Batch B');
        $experimentA = quarantineExperiment($ownerA);
        quarantineExperiment($ownerB);

        $validAssignment = OwnerContext::withOwner(
            $ownerA,
            fn (): Assignment => app(ResolveExperimentAssignment::class)->handle($experimentA, anonymousId: 'batch-valid'),
        );
        $brokenAssignment = quarantineInsertOrphanAssignment($ownerB, 'batch-broken');

        config()->set('signals.owner.enabled', $signalsOwnerEnabled);

        $counts = app(RecomputeExperimentAssignments::class)->handle(false);

        expect($counts['unchanged'])->toBe(1)
            ->and($counts['quarantined'])->toBe(1)
            ->and($counts['changed'])->toBe(0)
            ->and($validAssignment->fresh()?->metadata)->toBeNull()
            ->and($brokenAssignment->fresh()?->metadata['repair_quarantine']['reason'] ?? null)->toBe('inaccessible_parent');
    })->with([true, false]);
});
