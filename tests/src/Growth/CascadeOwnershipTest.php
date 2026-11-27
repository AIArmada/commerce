<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Growth;

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Growth\Actions\DeleteExperimentDependents;
use AIArmada\Growth\Actions\DeleteVariantAssignments;
use AIArmada\Growth\Enums\ExperimentStatus;
use AIArmada\Growth\Models\Assignment;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use AIArmada\Signals\Features\Ingestion\Models\TrackedProperty;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

function cascadeOwner(string $label): User
{
    return User::query()->create([
        'name' => 'Cascade Owner ' . $label . ' ' . Str::random(6),
        'email' => 'cascade-' . Str::lower($label) . '-' . Str::lower(Str::random(8)) . '@example.com',
        'password' => 'secret',
    ]);
}

function cascadeExperiment(User $owner): array
{
    return OwnerContext::withOwner($owner, function (): array {
        $property = TrackedProperty::query()->create([
            'name' => 'Cascade Property ' . Str::random(6),
            'slug' => 'cascade-' . Str::lower(Str::random(8)),
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
            'subject_key' => 'anonymous:cascade-' . Str::lower(Str::random(8)),
            'bucket' => 0,
            'assigned_at' => CarbonImmutable::now(),
            'first_exposed_at' => CarbonImmutable::now(),
            'last_seen_at' => CarbonImmutable::now(),
        ]);

        return [$experiment, $variant, $assignment];
    });
}

describe('Growth dependent deletion boundaries', function (): void {
    beforeEach(function (): void {
        config()->set('growth.features.owner.enabled', true);
        config()->set('signals.owner.enabled', true);
    });

    it('requires persisted parents for dependent cleanup', function (): void {
        expect(fn (): mixed => app(DeleteExperimentDependents::class)->handle(new Experiment))
            ->toThrow(InvalidArgumentException::class);

        expect(fn (): mixed => app(DeleteVariantAssignments::class)->handle(new Variant))
            ->toThrow(InvalidArgumentException::class);
    });

    it('rejects dependent cleanup for parents outside the writable owner scope', function (): void {
        $ownerA = cascadeOwner('A');
        $ownerB = cascadeOwner('B');
        [$experiment, $variant] = cascadeExperiment($ownerB);

        OwnerContext::withOwner($ownerA, function () use ($experiment, $variant): void {
            expect(fn (): mixed => app(DeleteExperimentDependents::class)->handle($experiment))
                ->toThrow(AuthorizationException::class);

            expect(fn (): mixed => app(DeleteVariantAssignments::class)->handle($variant))
                ->toThrow(AuthorizationException::class);
        });
    });

    it('deletes only owner-matched children while preserving drifted rows', function (): void {
        $ownerA = cascadeOwner('Owned');
        $ownerB = cascadeOwner('Drifted');
        [$experiment, $variant, $assignment] = cascadeExperiment($ownerA);

        $driftedVariantId = (string) Str::uuid();
        $driftedAssignmentId = (string) Str::uuid();
        $timestamp = CarbonImmutable::now();

        DB::table((new Variant)->getTable())->insert([
            'id' => $driftedVariantId,
            'experiment_id' => $experiment->getKey(),
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => (string) $ownerB->getKey(),
            'code' => 'DRIFT',
            'name' => 'Drifted Variant',
            'description' => null,
            'traffic_percentage' => 50,
            'position' => 2,
            'is_control' => false,
            'status' => 'active',
            'settings' => null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        DB::table((new Assignment)->getTable())->insert([
            'id' => $driftedAssignmentId,
            'experiment_id' => $experiment->getKey(),
            'variant_id' => $driftedVariantId,
            'signal_identity_id' => null,
            'signal_session_id' => null,
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => (string) $ownerB->getKey(),
            'subject_key' => 'anonymous:drifted-child',
            'bucket' => 0,
            'metadata' => null,
            'assigned_at' => $timestamp,
            'first_exposed_at' => $timestamp,
            'last_seen_at' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        OwnerContext::withOwner($ownerA, fn (): bool => (bool) $experiment->delete());

        expect(Experiment::query()->withoutOwnerScope()->whereKey($experiment->getKey())->exists())->toBeFalse()
            ->and(Variant::query()->withoutOwnerScope()->whereKey($variant->getKey())->exists())->toBeFalse()
            ->and(Assignment::query()->withoutOwnerScope()->whereKey($assignment->getKey())->exists())->toBeFalse()
            ->and(Variant::query()->withoutOwnerScope()->whereKey($driftedVariantId)->exists())->toBeTrue()
            ->and(Assignment::query()->withoutOwnerScope()->whereKey($driftedAssignmentId)->exists())->toBeTrue();
    });
});
