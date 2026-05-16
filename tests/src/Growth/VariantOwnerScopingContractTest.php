<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Testing\OwnerScopingContractTests;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use AIArmada\Signals\Models\TrackedProperty;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class VariantOwnerScopingContractTest extends TestCase
{
    use OwnerScopingContractTests;

    protected function getModelClass(): string
    {
        return Variant::class;
    }

    protected function createOwner(): Model
    {
        return User::query()->create([
            'name' => 'Variant Owner ' . Str::random(6),
            'email' => 'variant-owner-' . Str::lower(Str::random(8)) . '@example.com',
            'password' => 'secret',
        ]);
    }

    protected function createModelForOwner(Model $owner): Model
    {
        return OwnerContext::withOwner($owner, function () use ($owner): Variant {
            $experiment = $this->createExperiment($owner);

            return Variant::factory()->create([
                'experiment_id' => $experiment->getKey(),
                'code' => 'VAR' . Str::upper(Str::random(3)),
            ]);
        });
    }

    protected function createGlobalModel(): Model
    {
        return OwnerContext::withOwner(null, function (): Variant {
            $experiment = $this->createExperiment(null);

            return Variant::factory()->create([
                'experiment_id' => $experiment->getKey(),
                'code' => 'VAR' . Str::upper(Str::random(3)),
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

    private function createExperiment(?Model $owner): Experiment
    {
        return OwnerContext::withOwner($owner, function () use ($owner): Experiment {
            $trackedProperty = TrackedProperty::query()->create([
                'name' => 'Variant Test Property ' . Str::random(6),
                'slug' => 'variant-test-' . Str::lower(Str::random(8)),
                'write_key' => Str::random(40),
                'type' => 'website',
                'timezone' => 'UTC',
                'currency' => 'MYR',
                'is_active' => true,
                'owner_type' => $owner?->getMorphClass(),
                'owner_id' => $owner?->getKey(),
            ]);

            return Experiment::factory()->create([
                'tracked_property_id' => $trackedProperty->getKey(),
                'owner_type' => $owner?->getMorphClass(),
                'owner_id' => $owner?->getKey(),
            ]);
        });
    }
}
