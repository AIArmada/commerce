<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Testing\OwnerScopingContractTests;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use AIArmada\Signals\Models\TrackedProperty;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
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

    protected function createGlobalModel(): Variant
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

    public function test_experiment_id_is_immutable_after_creation(): void
    {
        $owner = $this->createOwner();
        $experimentA = $this->createExperiment($owner);
        $experimentB = $this->createExperiment($owner);

        $variant = OwnerContext::withOwner($owner, fn (): Variant => Variant::factory()->create([
            'experiment_id' => $experimentA->getKey(),
            'code' => 'IMM' . Str::upper(Str::random(3)),
        ]));

        expect(fn (): bool => OwnerContext::withOwner($owner, function () use ($experimentB, $variant): bool {
            $variant->experiment_id = (string) $experimentB->getKey();

            return $variant->save();
        }))->toThrow(InvalidArgumentException::class, 'Variant experiment_id cannot be changed after creation.');
    }

    public function test_foreign_tracked_property_experiment_is_rejected_when_growth_owner_scoping_is_disabled(): void
    {
        config()->set('growth.features.owner.enabled', false);

        $ownerA = $this->createOwner();
        $ownerB = $this->createOwner();
        $experiment = $this->createExperiment($ownerA);

        expect(fn (): Variant => OwnerContext::withOwner($ownerB, fn (): Variant => Variant::factory()->create([
            'experiment_id' => $experiment->getKey(),
            'code' => 'FOR' . Str::upper(Str::random(3)),
        ])))->toThrow(AuthorizationException::class, 'Variant experiment is not accessible in the current owner scope.');
    }

    public function test_persisted_variant_revalidates_the_parent_experiment_on_save(): void
    {
        $ownerA = $this->createOwner();
        $ownerB = $this->createOwner();
        $experimentA = $this->createExperiment($ownerA);
        $experimentB = $this->createExperiment($ownerB);

        $variant = OwnerContext::withOwner($ownerA, fn (): Variant => Variant::factory()->create([
            'experiment_id' => $experimentA->getKey(),
            'code' => 'DRV' . Str::upper(Str::random(3)),
        ]));

        DB::table($variant->getTable())
            ->where('id', $variant->getKey())
            ->update(['experiment_id' => $experimentB->getKey()]);

        expect(fn (): bool => OwnerContext::withOwner($ownerA, function () use ($variant): bool {
            $corruptVariant = Variant::query()->findOrFail($variant->getKey());
            $corruptVariant->name = 'Drifted Variant';

            return $corruptVariant->save();
        }))->toThrow(AuthorizationException::class, 'Variant experiment is not accessible in the current owner scope.');
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
