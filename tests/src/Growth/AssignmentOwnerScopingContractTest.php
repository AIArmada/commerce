<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Testing\OwnerScopingContractTests;
use AIArmada\Growth\Models\Assignment;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use AIArmada\Signals\Models\TrackedProperty;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AssignmentOwnerScopingContractTest extends TestCase
{
    use OwnerScopingContractTests;

    protected function getModelClass(): string
    {
        return Assignment::class;
    }

    protected function createOwner(): Model
    {
        return User::query()->create([
            'name' => 'Assignment Owner ' . Str::random(6),
            'email' => 'assignment-owner-' . Str::lower(Str::random(8)) . '@example.com',
            'password' => 'secret',
        ]);
    }

    protected function createModelForOwner(Model $owner): Model
    {
        return OwnerContext::withOwner($owner, function () use ($owner): Assignment {
            [$experiment, $variant] = $this->createExperimentAndVariant($owner);

            return Assignment::factory()->create([
                'experiment_id' => $experiment->getKey(),
                'variant_id' => $variant->getKey(),
                'subject_key' => 'identity:' . Str::uuid()->toString(),
                'assigned_at' => CarbonImmutable::now(),
            ]);
        });
    }

    protected function createGlobalModel(): Assignment
    {
        return OwnerContext::withOwner(null, function (): Assignment {
            [$experiment, $variant] = $this->createExperimentAndVariant(null);

            return Assignment::factory()->create([
                'experiment_id' => $experiment->getKey(),
                'variant_id' => $variant->getKey(),
                'subject_key' => 'identity:' . Str::uuid()->toString(),
                'assigned_at' => CarbonImmutable::now(),
                'owner_type' => null,
                'owner_id' => null,
            ]);
        });
    }

    public function test_assign_owner_sets_owner(): void
    {
        $owner = $this->createOwner();
        $model = $this->createGlobalModel();

        expect(fn () => $model->assignOwner($owner)->save())
            ->toThrow(InvalidArgumentException::class, 'Owner cannot be assigned to a persisted global');
    }

    public function test_persisted_assignment_revalidates_the_parent_experiment_on_save(): void
    {
        $ownerA = $this->createOwner();
        $ownerB = $this->createOwner();
        [$experimentA, $variantA] = $this->createExperimentAndVariant($ownerA);
        [$experimentB] = $this->createExperimentAndVariant($ownerB);

        $assignment = OwnerContext::withOwner($ownerA, fn (): Assignment => Assignment::factory()->create([
            'experiment_id' => $experimentA->getKey(),
            'variant_id' => $variantA->getKey(),
            'subject_key' => 'identity:' . Str::uuid()->toString(),
            'assigned_at' => CarbonImmutable::now(),
        ]));

        DB::table($assignment->getTable())
            ->where('id', $assignment->getKey())
            ->update(['experiment_id' => $experimentB->getKey()]);

        expect(fn (): bool => OwnerContext::withOwner($ownerA, function () use ($assignment): bool {
            $corruptAssignment = Assignment::query()->findOrFail($assignment->getKey());
            $corruptAssignment->subject_key = 'identity:' . Str::uuid()->toString();

            return $corruptAssignment->save();
        }))->toThrow(AuthorizationException::class, 'Assignment experiment is not accessible in the current owner scope.');
    }

    /**
     * @return array{Experiment, Variant}
     */
    private function createExperimentAndVariant(?Model $owner): array
    {
        return OwnerContext::withOwner($owner, function () use ($owner): array {
            $trackedProperty = TrackedProperty::query()->create([
                'name' => 'Assignment Property ' . Str::random(6),
                'slug' => 'assignment-property-' . Str::lower(Str::random(8)),
                'write_key' => Str::random(40),
                'type' => 'website',
                'timezone' => 'UTC',
                'currency' => 'MYR',
                'is_active' => true,
                'owner_type' => $owner?->getMorphClass(),
                'owner_id' => $owner?->getKey(),
            ]);

            $experiment = Experiment::factory()->create([
                'tracked_property_id' => $trackedProperty->getKey(),
                'owner_type' => $owner?->getMorphClass(),
                'owner_id' => $owner?->getKey(),
            ]);

            $variant = Variant::factory()->create([
                'experiment_id' => $experiment->getKey(),
                'code' => 'ASSIGN' . Str::upper(Str::random(3)),
                'owner_type' => $owner?->getMorphClass(),
                'owner_id' => $owner?->getKey(),
            ]);

            return [$experiment, $variant];
        });
    }
}
