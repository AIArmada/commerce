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
use Illuminate\Database\Eloquent\Model;
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

    protected function createGlobalModel(): Model
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
            ->toThrow(\InvalidArgumentException::class, 'Owner cannot be assigned to a persisted global');
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