<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\FilamentCart;

use AIArmada\Cart\Snapshots\CartSnapshot;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Testing\OwnerScopingContractTests;
use Illuminate\Database\Eloquent\Model;

final class SnapshotOwnerScopingContractTest extends TestCase
{
    use OwnerScopingContractTests;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cart.owner.enabled', true);
        config()->set('cart.owner.include_global', true);
        config()->set('cart.owner.auto_assign_on_create', false);
    }

    protected function getModelClass(): string
    {
        return CartSnapshot::class;
    }

    protected function createOwner(): Model
    {
        return User::factory()->create();
    }

    protected function createModelForOwner(Model $owner): Model
    {
        return OwnerContext::withOwner($owner, function () use ($owner): CartSnapshot {
            $snapshot = CartSnapshot::factory()->make();
            $snapshot->assignOwner($owner);
            $snapshot->save();

            return $snapshot;
        });
    }

    protected function createGlobalModel(): Model
    {
        return OwnerContext::withOwner(null, function (): CartSnapshot {
            $snapshot = CartSnapshot::factory()->make();
            $snapshot->save();

            return $snapshot;
        });
    }

    public function test_assign_owner_sets_owner(): void
    {
        $owner = $this->createOwner();
        $model = CartSnapshot::factory()->make();

        OwnerContext::withOwner($owner, function () use ($model, $owner): void {
            $model->assignOwner($owner);
            $model->save();
        });

        expect($model->hasOwner())->toBeTrue()
            ->and($model->belongsToOwner($owner))->toBeTrue();
    }
}
