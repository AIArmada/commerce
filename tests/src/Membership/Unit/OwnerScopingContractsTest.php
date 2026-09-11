<?php

declare(strict_types=1);

namespace AIArmada\Membership\Tests\Unit;

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Testing\OwnerScopingContractTests;
use AIArmada\Membership\Enums\ApplicationStatus;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Membership\Models\MembershipApplication;
use AIArmada\Membership\Models\MembershipInvitation;
use AIArmada\Membership\Tests\Fixtures\TestSubject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

trait MembershipOwnerScopingContractTests
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
        $model = $this->createGlobalModel();

        expect(fn () => $model->assignOwner($owner)->save())
            ->toThrow(InvalidArgumentException::class, 'persisted global');
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

    /**
     * @return class-string<Model>
     */
    abstract protected function getModelClass(): string;

    protected function createOwner(): Model
    {
        return User::query()->create([
            'name' => 'Membership owner ' . Str::upper(Str::random(8)),
            'email' => Str::uuid() . '@membership-owner.example.com',
            'password' => 'secret',
        ]);
    }

    protected function createModelForOwner(Model $owner): Model
    {
        return OwnerContext::withOwner($owner, fn (): Model => $this->createModel($owner));
    }

    protected function createGlobalModel(): Model
    {
        return OwnerContext::withOwner(null, fn (): Model => $this->createModel(null));
    }

    private function createModel(?Model $owner): Model
    {
        $subject = TestSubject::query()->create([
            'name' => 'Contract subject ' . Str::uuid(),
        ]);
        $actor = $owner ?? $this->createOwner();

        return match ($this->getModelClass()) {
            MembershipApplication::class => MembershipApplication::query()->create([
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'applicant_id' => $actor->getKey(),
                'status' => ApplicationStatus::Pending,
                'justification' => 'Owner contract application.',
            ]),
            MembershipInvitation::class => $this->createInvitation([
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'email' => 'contract-' . Str::uuid() . '@example.com',
                'role' => MemberRole::Viewer->spatieRoleName(),
                'invited_by' => $actor->getKey(),
            ]),
            default => throw new LogicException('Unhandled membership owner contract model.'),
        };
    }
}
