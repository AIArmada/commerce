<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Cart;

use AIArmada\Cart\Models\Condition;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Testing\OwnerScopingContractTests;
use Illuminate\Database\Eloquent\Model;

final class ConditionOwnerScopingContractTest extends TestCase
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
        return Condition::class;
    }

    protected function createOwner(): Model
    {
        return User::factory()->create();
    }

    protected function createModelForOwner(Model $owner): Model
    {
        return OwnerContext::withOwner($owner, function () use ($owner): Condition {
            $condition = Condition::factory()->make();
            $condition->assignOwner($owner);
            $condition->save();

            return $condition;
        });
    }

    protected function createGlobalModel(): Model
    {
        return OwnerContext::withOwner(null, function (): Condition {
            $condition = Condition::factory()->make();
            $condition->save();

            return $condition;
        });
    }

    public function test_model_can_be_global(): void
    {
        $model = $this->createGlobalModel();

        expect($model->hasOwner())->toBeFalse()
            ->and($model->owner_type)->toBeNull()
            ->and($model->owner_id)->toBeNull();
    }

    public function test_assign_owner_sets_owner(): void
    {
        $owner = $this->createOwner();
        $model = Condition::factory()->make();

        OwnerContext::withOwner($owner, function () use ($model, $owner): void {
            $model->assignOwner($owner);
            $model->save();
        });

        expect($model->hasOwner())->toBeTrue()
            ->and($model->belongsToOwner($owner))->toBeTrue();
    }

    public function test_remove_owner_allowed_on_unsaved_model(): void
    {
        $model = new Condition;
        $model->removeOwner();

        expect($model->hasOwner())->toBeFalse();
    }
}
