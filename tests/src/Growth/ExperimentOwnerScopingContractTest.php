<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Testing\OwnerScopingContractTests;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Signals\Models\TrackedProperty;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class ExperimentOwnerScopingContractTest extends TestCase
{
    use OwnerScopingContractTests;

    protected function getModelClass(): string
    {
        return Experiment::class;
    }

    protected function createOwner(): Model
    {
        return User::query()->create([
            'name' => 'Growth Owner ' . Str::random(6),
            'email' => 'growth-owner-' . Str::lower(Str::random(8)) . '@example.com',
            'password' => 'secret',
        ]);
    }

    protected function createModelForOwner(Model $owner): Model
    {
        return OwnerContext::withOwner($owner, function () use ($owner): Experiment {
            $trackedProperty = $this->createTrackedProperty($owner);

            return Experiment::factory()->create([
                'tracked_property_id' => $trackedProperty->getKey(),
            ]);
        });
    }

    protected function createGlobalModel(): Experiment
    {
        return OwnerContext::withOwner(null, function (): Experiment {
            $trackedProperty = $this->createTrackedProperty(null);

            return Experiment::factory()->create([
                'tracked_property_id' => $trackedProperty->getKey(),
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

    private function createTrackedProperty(?Model $owner): TrackedProperty
    {
        return OwnerContext::withOwner($owner, fn (): TrackedProperty => TrackedProperty::query()->create([
            'name' => 'Growth Property ' . Str::random(6),
            'slug' => 'growth-property-' . Str::lower(Str::random(8)),
            'write_key' => Str::random(40),
            'type' => 'website',
            'timezone' => 'UTC',
            'currency' => 'MYR',
            'is_active' => true,
            'owner_type' => $owner?->getMorphClass(),
            'owner_id' => $owner?->getKey(),
        ]));
    }
}
