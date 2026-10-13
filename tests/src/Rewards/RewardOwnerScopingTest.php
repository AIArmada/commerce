<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Rewards;

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Testing\OwnerScopingContractTests;
use AIArmada\Rewards\Actions\EvaluateRewardEntitlementAction;
use AIArmada\Rewards\Enums\RewardBeneficiaryRole;
use AIArmada\Rewards\Enums\RewardComponent;
use AIArmada\Rewards\Enums\RewardProgramMode;
use AIArmada\Rewards\Models\RewardProgram;
use AIArmada\Rewards\Support\RewardProgramGuard;
use AIArmada\Rewards\Tests\Ledgers\Support\FakeRewardSourceFactReader;
use AIArmada\Rewards\Tests\Ledgers\Support\RewardLedgerFixtures;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;

trait RewardOwnerScopingPestTests
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

        expect($model->isGlobal())->toBeTrue();

        expect(function () use ($model, $owner): void {
            $model->assignOwner($owner);
            $model->save();
        })->toThrow(InvalidArgumentException::class, 'persisted global');
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

    protected function getModelClass(): string
    {
        return RewardProgram::class;
    }

    protected function createOwner(): Model
    {
        return User::factory()->create();
    }

    protected function createModelForOwner(Model $owner): Model
    {
        return OwnerContext::withOwner(
            $owner,
            static fn (): Model => RewardProgram::factory()->create([
                'family_key' => 'scope-' . Str::lower(Str::random(8)),
            ]),
        );
    }

    protected function createGlobalModel(): Model
    {
        return OwnerContext::withOwner(
            null,
            static fn (): Model => RewardProgram::factory()->create([
                'family_key' => 'scope-global-' . Str::lower(Str::random(8)),
                'owner_type' => null,
                'owner_id' => null,
            ]),
        );
    }
}

uses(RewardOwnerScopingPestTests::class);

it('verifies the program uses the HasOwner trait', function (): void {
    $this->test_model_uses_has_owner_trait();
});

it('verifies the program can be assigned to an owner', function (): void {
    $this->test_model_can_be_assigned_owner();
});

it('verifies the program can be global', function (): void {
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

it('verifies assigning an owner to a persisted global record is rejected', function (): void {
    $this->test_assign_owner_sets_owner();
});

it('verifies removing an owner from a persisted record is rejected', function (): void {
    $this->test_remove_owner_throws_on_persisted_owned_record();
});

it('verifies removing an owner from an unsaved model is allowed', function (): void {
    $this->test_remove_owner_allowed_on_unsaved_model();
});

it('prevents cross-tenant program access', function (): void {
    $this->test_cross_tenant_access_prevented();
});

it('hides foreign programs from default reads', function (): void {
    $owner = User::factory()->create();

    $program = OwnerContext::withOwner($owner, fn (): RewardProgram => RewardProgram::factory()->create([
        'family_key' => 'scope-read-' . Str::lower(Str::random(6)),
    ]));

    $other = User::factory()->create();

    $found = OwnerContext::withOwner($other, fn () => RewardProgram::query()->whereKey($program->getKey())->first());

    expect($found)->toBeNull();
});

it('degrades to plain lookups when owner scoping is disabled', function (): void {
    config()->set('rewards.owner.enabled', false);

    $program = RewardProgram::factory()->create([
        'family_key' => 'scope-plain-' . Str::lower(Str::random(6)),
    ]);

    expect(RewardProgramGuard::findProgramOrFail((string) $program->getKey())->getKey())->toBe($program->getKey())
        ->and(fn () => RewardProgramGuard::findProgramOrFail((string) Str::uuid()))->toThrow(InvalidArgumentException::class, 'Unknown reward program.');
});

it('refuses evaluation against a foreign program', function (): void {
    $owner = User::factory()->create();

    [$program, , , $memberId] = OwnerContext::withOwner(
        $owner,
        static fn (): array => RewardLedgerFixtures::seedActiveEnrolled('scope-eval', RewardProgramMode::Points),
    );

    $other = User::factory()->create();
    $reader = new FakeRewardSourceFactReader;
    $action = new EvaluateRewardEntitlementAction($reader);

    expect(fn () => OwnerContext::withOwner(
        $other,
        static fn () => $action->handle(
            $program,
            RewardComponent::Points,
            'user',
            $memberId,
            RewardBeneficiaryRole::Recipient,
            'src-scope-1',
            CarbonImmutable::parse('2026-06-15T00:00:00Z'),
        ),
    ))->toThrow(AuthorizationException::class);
});
