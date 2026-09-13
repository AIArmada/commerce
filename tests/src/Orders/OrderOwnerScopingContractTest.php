<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Orders;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Testing\OwnerScopingContractTests;
use AIArmada\CommerceSupport\Tests\Fixtures\TestOwner;
use AIArmada\Orders\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

trait OrderOwnerScopingPestTests
{
    use OwnerScopingContractTests {
        test_model_uses_has_owner_trait as protected runModelUsesHasOwnerTrait;
        test_model_can_be_assigned_owner as protected runModelCanBeAssignedOwner;
        test_model_can_be_global as protected runModelCanBeGlobal;
        test_for_owner_scope_filters_by_owner as protected runForOwnerScopeFiltersByOwner;
        test_for_owner_with_include_global_includes_global_records as protected runForOwnerWithIncludeGlobal;
        test_global_only_scope_returns_only_global_records as protected runGlobalOnlyScopeReturnsOnlyGlobalRecords;
        test_owner_context_with_owner_scopes_queries as protected runOwnerContextWithOwnerScopesQueries;
        test_remove_owner_throws_on_persisted_owned_record as protected runRemoveOwnerThrowsOnPersistedOwnedRecord;
        test_remove_owner_allowed_on_unsaved_model as protected runRemoveOwnerAllowedOnUnsavedModel;
        test_cross_tenant_access_prevented as protected runCrossTenantAccessPrevented;
    }

    protected function test_model_uses_has_owner_trait(): void
    {
        $this->runModelUsesHasOwnerTrait();
    }

    protected function test_model_can_be_assigned_owner(): void
    {
        $this->runModelCanBeAssignedOwner();
    }

    protected function test_model_can_be_global(): void
    {
        $this->runModelCanBeGlobal();
    }

    protected function test_for_owner_scope_filters_by_owner(): void
    {
        $this->runForOwnerScopeFiltersByOwner();
    }

    protected function test_for_owner_with_include_global_includes_global_records(): void
    {
        $this->runForOwnerWithIncludeGlobal();
    }

    protected function test_global_only_scope_returns_only_global_records(): void
    {
        $this->runGlobalOnlyScopeReturnsOnlyGlobalRecords();
    }

    protected function test_owner_context_with_owner_scopes_queries(): void
    {
        $this->runOwnerContextWithOwnerScopesQueries();
    }

    protected function test_assign_owner_sets_owner(): void
    {
        $owner = $this->createOwner();
        $model = new Order;

        OwnerContext::withOwner($owner, function () use ($model, $owner): void {
            $model->assignOwner($owner);
            $model->save();
        });

        expect($model->hasOwner())->toBeTrue()
            ->and($model->belongsToOwner($owner))->toBeTrue();
    }

    protected function test_remove_owner_throws_on_persisted_owned_record(): void
    {
        $this->runRemoveOwnerThrowsOnPersistedOwnedRecord();
    }

    protected function test_remove_owner_allowed_on_unsaved_model(): void
    {
        $this->runRemoveOwnerAllowedOnUnsavedModel();
    }

    protected function test_cross_tenant_access_prevented(): void
    {
        $this->runCrossTenantAccessPrevented();
    }

    protected function setUpOwnerScoping(): void
    {
        Schema::dropIfExists('test_owners');
        Schema::create('test_owners', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();
        });

        config()->set('orders.owner.enabled', true);
        config()->set('orders.owner.include_global', true);
        config()->set('orders.owner.auto_assign_on_create', false);

        app()->instance(OwnerResolverInterface::class, new class implements OwnerResolverInterface
        {
            public function resolve(): ?Model
            {
                return null;
            }
        });
    }

    protected function getModelClass(): string
    {
        return Order::class;
    }

    protected function createOwner(): Model
    {
        return TestOwner::query()->create([
            'name' => 'Order Contract Owner',
        ]);
    }

    protected function createModelForOwner(Model $owner): Model
    {
        return OwnerContext::withOwner($owner, function () use ($owner): Order {
            return Order::factory()->create([
                'owner_type' => $owner->getMorphClass(),
                'owner_id' => $owner->getKey(),
            ]);
        });
    }

    protected function createGlobalModel(): Model
    {
        return OwnerContext::withOwner(null, function (): Order {
            return Order::factory()->create([
                'owner_type' => null,
                'owner_id' => null,
            ]);
        });
    }
}

uses(OrderOwnerScopingPestTests::class);

beforeEach(function (): void {
    $this->setUpOwnerScoping();
});

it('verifies the model uses the HasOwner trait', function (): void {
    $this->test_model_uses_has_owner_trait();
});

it('verifies the model can be assigned to an owner', function (): void {
    $this->test_model_can_be_assigned_owner();
});

it('verifies the model can be global', function (): void {
    $this->test_model_can_be_global();
});

it('verifies the for-owner scope filters by owner', function (): void {
    $this->test_for_owner_scope_filters_by_owner();
});

it('verifies the for-owner scope can include global records', function (): void {
    $this->test_for_owner_with_include_global_includes_global_records();
});

it('verifies the global-only scope returns only global records', function (): void {
    $this->test_global_only_scope_returns_only_global_records();
});

it('verifies owner context scopes queries', function (): void {
    $this->test_owner_context_with_owner_scopes_queries();
});

it('verifies assigning an owner to a new record succeeds', function (): void {
    $this->test_assign_owner_sets_owner();
});

it('verifies removing an owner from a persisted record is rejected', function (): void {
    $this->test_remove_owner_throws_on_persisted_owned_record();
});

it('verifies removing an owner from an unsaved model is allowed', function (): void {
    $this->test_remove_owner_allowed_on_unsaved_model();
});

it('prevents cross-tenant access', function (): void {
    $this->test_cross_tenant_access_prevented();
});
