<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Rewards;

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Testing\OwnerScopingContractTests;
use AIArmada\Rewards\Data\RewardBeneficiaryRole;
use AIArmada\Rewards\Data\RewardComponent;
use AIArmada\Rewards\Enums\RewardAwardStatus;
use AIArmada\Rewards\Enums\RewardBenefitGrantStatus;
use AIArmada\Rewards\Enums\RewardEnrollmentStatus;
use AIArmada\Rewards\Enums\RewardProgramMode;
use AIArmada\Rewards\Enums\RewardRecoveryStatus;
use AIArmada\Rewards\Features\Benefits\Api\RewardBenefitRecoveryApi;
use AIArmada\Rewards\Features\Benefits\Models\RewardBenefitGrant;
use AIArmada\Rewards\Features\Benefits\Models\RewardBenefitItem;
use AIArmada\Rewards\Features\Enrollment\Actions\LeaveRewardProgramAction;
use AIArmada\Rewards\Features\Enrollment\Actions\ReacceptRewardTermsAction;
use AIArmada\Rewards\Features\Enrollment\Models\RewardEnrollmentAcceptance;
use AIArmada\Rewards\Features\Entitlements\Actions\AppendRewardAdjustmentAction;
use AIArmada\Rewards\Features\Entitlements\Actions\EvaluateRewardEntitlementAction;
use AIArmada\Rewards\Features\Entitlements\Actions\InvalidateRewardMilestoneContributionAction;
use AIArmada\Rewards\Features\Entitlements\Actions\OpenRewardRecoveryAction;
use AIArmada\Rewards\Features\Entitlements\Actions\RecordRewardMilestoneContributionAction;
use AIArmada\Rewards\Features\Entitlements\Actions\SettleRewardRecoveryAction;
use AIArmada\Rewards\Features\Entitlements\Actions\TransitionRewardAwardAction;
use AIArmada\Rewards\Features\Entitlements\Actions\WriteOffRewardRecoveryAction;
use AIArmada\Rewards\Features\Entitlements\Api\RewardAwardLockApi;
use AIArmada\Rewards\Features\Entitlements\Models\RewardAdjustment;
use AIArmada\Rewards\Features\Entitlements\Models\RewardAward;
use AIArmada\Rewards\Features\Entitlements\Models\RewardMilestoneContribution;
use AIArmada\Rewards\Features\Entitlements\Models\RewardRecovery;
use AIArmada\Rewards\Features\Finance\Api\RewardPayoutQueryApi;
use AIArmada\Rewards\Features\Finance\Models\RewardPayoutRequest;
use AIArmada\Rewards\Features\Points\Actions\ClawBackAwardPointsAction;
use AIArmada\Rewards\Features\Points\Actions\CreditRewardPointsAction;
use AIArmada\Rewards\Features\Points\Actions\DebitRewardPointsAction;
use AIArmada\Rewards\Features\Points\Actions\RefundRewardPointsAction;
use AIArmada\Rewards\Features\Points\Actions\ReleaseRewardPointsAction;
use AIArmada\Rewards\Features\Points\Actions\ReserveRewardPointsAction;
use AIArmada\Rewards\Features\Points\Models\RewardPointLot;
use AIArmada\Rewards\Features\Points\Models\RewardPointMovement;
use AIArmada\Rewards\Features\Programs\Api\RewardProgramLockApi;
use AIArmada\Rewards\Features\Programs\Models\RewardProgram;
use AIArmada\Rewards\Tests\Ledgers\Support\FakeRewardSourceFactReader;
use AIArmada\Rewards\Tests\Ledgers\Support\RewardLedgerFixtures;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
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

    expect(app(RewardProgramLockApi::class)->readProgram((string) $program->getKey())->id)->toBe($program->getKey())
        ->and(fn () => app(RewardProgramLockApi::class)->readProgram((string) Str::uuid()))->toThrow(InvalidArgumentException::class, 'Unknown reward program.');
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
            (string) $program->getKey(),
            RewardComponent::Points,
            'user',
            $memberId,
            RewardBeneficiaryRole::Recipient,
            'src-scope-1',
            CarbonImmutable::parse('2026-06-15T00:00:00Z'),
        ),
    ))->toThrow(AuthorizationException::class);
});

/**
 * One tenant-owned program (owner A) carrying a fulfilled points award
 * (lots live), an open recovery, a milestone contribution, and an active
 * enrollment — every surface F2's new guard calls must cover. Returns
 * [ownerA, ownerB, program, version, enrollment, award, recovery, staff].
 */
function guardCrossTenantFixture(string $prefix): array
{
    $ownerA = User::factory()->create();
    $staff = RewardLedgerFixtures::staffId();

    [$program, $version, $enrollment, $memberId] = OwnerContext::withOwner(
        $ownerA,
        static fn (): array => RewardLedgerFixtures::seedActiveEnrolled(
            $prefix,
            RewardProgramMode::Points,
            ['milestones' => [['key' => 'two', 'target_count' => 2, 'window_days' => 30, 'reward' => ['type' => 'points', 'points' => 25, 'unit' => 'pts']]]],
        ),
    );

    $acceptance = RewardEnrollmentAcceptance::query()
        ->where('enrollment_id', $enrollment->getKey())
        ->orderByDesc('seq')
        ->firstOrFail();

    $reader = new FakeRewardSourceFactReader;
    $fact = RewardLedgerFixtures::fact('src-' . $prefix . '-1', $memberId);
    $reader->seed($fact);

    $award = OwnerContext::withOwner(
        $ownerA,
        static fn (): RewardAward => RewardAward::factory()->create([
            'program_id' => $program->getKey(),
            'policy_version_id' => $version->getKey(),
            'enrollment_id' => $enrollment->getKey(),
            'acceptance_id' => $acceptance->getKey(),
            'recipient_id' => $memberId,
            'source_business_key' => 'src-' . $prefix . '-1',
            'family_key' => $program->family_key,
            'component' => RewardComponent::Points,
            'status' => RewardAwardStatus::Approved,
            'amount_snapshot' => ['type' => 'points', 'points' => 100, 'unit' => 'pts'],
            'policy_snapshot' => RewardLedgerFixtures::frozenAwardSnapshots($version, $acceptance, $fact)['policy'],
            'consent_snapshot' => RewardLedgerFixtures::frozenAwardSnapshots($version, $acceptance, $fact)['consent'],
            'source_snapshot' => RewardLedgerFixtures::frozenAwardSnapshots($version, $acceptance, $fact)['source'],
            'held_at' => null,
            'approved_at' => CarbonImmutable::parse('2026-06-17T00:00:00Z'),
        ]),
    );

    $fulfilled = OwnerContext::withOwner(
        $ownerA,
        static fn (): RewardAward => (new TransitionRewardAwardAction(null, $reader))->handle(
            $award,
            RewardAwardStatus::Fulfilled,
            'user',
            $staff,
            'staff',
            'fulfill',
            null,
            null,
            CarbonImmutable::parse('2026-06-18T00:00:00Z'),
        ),
    );

    $recovery = OwnerContext::withOwner(
        $ownerA,
        static fn (): RewardRecovery => OpenRewardRecoveryAction::run(
            $fulfilled,
            'dispute',
            null,
            null,
            null,
            null,
            100,
            'pts',
            'user',
            $staff,
            'staff',
            'consume-audit',
            ['note' => 'audit'],
            CarbonImmutable::parse('2026-06-19T00:00:00Z'),
        ),
    );

    OwnerContext::withOwner(
        $ownerA,
        static fn () => (new RecordRewardMilestoneContributionAction($reader))->handle(
            'user',
            $memberId,
            $program->family_key,
            'two',
            2,
            30,
            RewardComponent::Points,
            ['points' => 25, 'unit' => 'pts'],
            'src-' . $prefix . '-1',
            'user',
            $memberId,
            RewardBeneficiaryRole::Recipient,
            'attendance',
            CarbonImmutable::parse('2026-06-12T00:00:00Z'),
            (string) $program->getKey(),
        ),
    );

    return [
        $ownerA,
        User::factory()->create(),
        $program->refresh(),
        $version,
        $enrollment->refresh(),
        $fulfilled->refresh(),
        $recovery,
        $staff,
    ];
}

it('refuses a foreign clawback and a member-role clawback on the owner award', function (): void {
    [$ownerA, $ownerB, $program, , $enrollment, $award, $recovery, $staff] = guardCrossTenantFixture('guard-claw');

    $lotCount = RewardPointLot::query()->where('award_id', $award->getKey())->count();
    $adjustCount = RewardAdjustment::query()->where('award_id', $award->getKey())->count();

    $at = CarbonImmutable::parse('2026-06-21T00:00:00Z');

    expect(fn () => OwnerContext::withOwner(
        $ownerB,
        static fn () => ClawBackAwardPointsAction::run((string) $award->getKey(), 'user', $staff, 'staff', 'consume-audit', $at),
    ))->toThrow(AuthorizationException::class);

    expect(fn () => OwnerContext::withOwner(
        $ownerA,
        static fn () => ClawBackAwardPointsAction::run((string) $award->getKey(), 'user', (string) $enrollment->member_id, 'member', 'consume-audit', $at),
    ))->toThrow(InvalidArgumentException::class, 'staff/admin/system identity');

    expect(RewardPointLot::query()->where('award_id', $award->getKey())->count())->toBe($lotCount)
        ->and(RewardAdjustment::query()->where('award_id', $award->getKey())->count())->toBe($adjustCount);
});

it('refuses foreign settle and writeoff of an open recovery', function (): void {
    [$ownerA, $ownerB, $program, , $enrollment, $award, $recovery, $staff] = guardCrossTenantFixture('guard-recovery');

    $at = CarbonImmutable::parse('2026-06-21T00:00:00Z');

    expect(fn () => OwnerContext::withOwner(
        $ownerB,
        static fn () => SettleRewardRecoveryAction::run($recovery, ['recovery_proof' => 'BANK-RETURN-1'], 'user', $staff, 'staff', $at),
    ))->toThrow(AuthorizationException::class);

    expect(fn () => OwnerContext::withOwner(
        $ownerB,
        static fn () => WriteOffRewardRecoveryAction::run($recovery, 'user', $staff, 'staff', 'consume-audit', $at),
    ))->toThrow(AuthorizationException::class);

    expect($recovery->refresh()->status)->toBe(RewardRecoveryStatus::Open);
});

it('refuses foreign leave and reaccept of an enrollment', function (): void {
    [$ownerA, $ownerB, $program, $version, $enrollment, $award, $recovery, $staff] = guardCrossTenantFixture('guard-enroll');

    $at = CarbonImmutable::parse('2026-06-21T00:00:00Z');
    $acceptCount = RewardEnrollmentAcceptance::query()->where('enrollment_id', $enrollment->getKey())->count();

    expect(fn () => OwnerContext::withOwner(
        $ownerB,
        static fn () => LeaveRewardProgramAction::run($enrollment, 'member-left', $at),
    ))->toThrow(AuthorizationException::class);

    expect(fn () => OwnerContext::withOwner(
        $ownerB,
        static fn () => ReacceptRewardTermsAction::run($enrollment, (string) $version->getKey(), 'member-portal', $at),
    ))->toThrow(AuthorizationException::class);

    expect($enrollment->refresh()->status)->toBe(RewardEnrollmentStatus::Active)
        ->and(RewardEnrollmentAcceptance::query()->where('enrollment_id', $enrollment->getKey())->count())->toBe($acceptCount);
});

it('refuses foreign contribution invalidation without touching the cycle', function (): void {
    [$ownerA, $ownerB, $program, , $enrollment, $award, $recovery, $staff] = guardCrossTenantFixture('guard-contribution');

    $contribution = RewardMilestoneContribution::query()
        ->where('source_business_key', 'src-guard-contribution-1')
        ->whereNull('revoked_at')
        ->firstOrFail();

    $at = CarbonImmutable::parse('2026-06-21T00:00:00Z');

    expect(fn () => OwnerContext::withOwner(
        $ownerB,
        static fn () => InvalidateRewardMilestoneContributionAction::run(
            $contribution,
            'user',
            $staff,
            'staff',
            'source-withdrawn',
            $at,
        ),
    ))->toThrow(AuthorizationException::class);

    expect($contribution->refresh()->revoked_at)->toBeNull();
});

it('refuses credit bound to a foreign award or program but allows dangling references', function (): void {
    [$ownerA, $ownerB, $program, , $enrollment, $award, $recovery, $staff] = guardCrossTenantFixture('guard-credit');

    $at = CarbonImmutable::parse('2026-06-21T00:00:00Z');
    $lotCountBefore = RewardPointLot::query()->where('award_id', $award->getKey())->count();
    $programLotCountBefore = RewardPointLot::query()->where('program_id', $program->getKey())->count();

    expect(fn () => OwnerContext::withOwner(
        $ownerB,
        static fn () => CreditRewardPointsAction::run(
            'user',
            (string) $enrollment->member_id,
            'other-family',
            'pts',
            5,
            $at,
            null,
            (string) $award->getKey(),
        ),
    ))->toThrow(AuthorizationException::class);

    expect(RewardPointLot::query()->where('award_id', $award->getKey())->count())->toBe($lotCountBefore);

    expect(fn () => OwnerContext::withOwner(
        $ownerB,
        static fn () => CreditRewardPointsAction::run(
            'user',
            (string) $enrollment->member_id,
            'other-family',
            'pts',
            5,
            $at,
            null,
            null,
            (string) $program->getKey(),
            'manual-credit:guard-credit-program-foreign',
            'user',
            $staff,
            'manual-credit',
            $at,
        ),
    ))->toThrow(AuthorizationException::class);

    expect(RewardPointLot::query()->where('program_id', $program->getKey())->count())->toBe($programLotCountBefore);

    $danglingProgramId = (string) Str::uuid();
    $danglingProgramMovement = OwnerContext::withOwner(
        $ownerB,
        static fn () => CreditRewardPointsAction::run(
            'user',
            (string) $enrollment->member_id,
            'other-family',
            'pts',
            5,
            $at,
            null,
            null,
            $danglingProgramId,
            'manual-credit:guard-credit-program-dangling',
            'user',
            $staff,
            'manual-credit',
            $at,
        ),
    );

    expect($danglingProgramMovement->exists)->toBeTrue()
        ->and(RewardPointLot::query()->where('program_id', $danglingProgramId)->exists())->toBeTrue();

    // A dangling (nonexistent) award reference stays allowed for append-only annotations.
    OwnerContext::withOwner(
        $ownerB,
        static fn () => CreditRewardPointsAction::run(
            'user',
            (string) $enrollment->member_id,
            'other-family',
            'pts',
            5,
            $at,
            null,
            (string) Str::uuid(),
            null,
            'manual-credit:guard-credit-dangling',
            'user',
            $staff,
            'manual-credit',
            $at,
        ),
    );
});

it('refuses foreign adjustment resolution and linked adjustment creation', function (): void {
    [$ownerA, $ownerB, , , , $award, , $staff] = guardCrossTenantFixture('guard-adjustment');

    $adjustment = OwnerContext::withOwner($ownerA, static fn () => AppendRewardAdjustmentAction::run(
        'adjustment',
        (string) $award->getKey(),
        null,
        null,
        'guard-adjustment:root',
        null,
        'user',
        $staff,
        'staff',
        'audit',
    ));

    $resolver = (string) Str::uuid();

    expect(fn () => OwnerContext::withOwner($ownerB, static fn () => app(AppendRewardAdjustmentAction::class)->resolve(
        $adjustment,
        'user',
        $resolver,
        'staff',
        'foreign-resolution',
    )))->toThrow(AuthorizationException::class);

    expect(fn () => OwnerContext::withOwner($ownerB, static fn () => AppendRewardAdjustmentAction::run(
        'adjustment',
        null,
        null,
        (string) $adjustment->getKey(),
        'guard-adjustment:foreign',
        null,
        'user',
        $resolver,
        'staff',
        'foreign-link',
    )))->toThrow(AuthorizationException::class);

    expect($adjustment->refresh()->resolved_at)->toBeNull()
        ->and(RewardAdjustment::query()->where('operation_key', 'guard-adjustment:foreign')->exists())->toBeFalse();
});

it('rejects recovery replay with conflicting frozen terms', function (): void {
    [$ownerA, , , , , $award, $recovery, $staff] = guardCrossTenantFixture('guard-recovery-replay');

    expect(fn () => OwnerContext::withOwner($ownerA, static fn () => OpenRewardRecoveryAction::run(
        $award,
        'dispute',
        null,
        null,
        null,
        null,
        101,
        'pts',
        'user',
        $staff,
        'staff',
        'consume-audit',
    )))->toThrow(InvalidArgumentException::class, 'different terms');

    expect($recovery->refresh()->points)->toBe(100);
});

it('resolves frozen award policies only through scoped program identities', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    [$programA, $versionA] = OwnerContext::withOwner(
        $ownerA,
        static fn (): array => RewardLedgerFixtures::seedActiveEnrolled('guard-policy-a', RewardProgramMode::Points),
    );

    [$programB, $versionB] = OwnerContext::withOwner(
        $ownerB,
        static fn (): array => RewardLedgerFixtures::seedActiveEnrolled('guard-policy-b', RewardProgramMode::Points),
    );

    $programs = app(RewardProgramLockApi::class);
    $idA = (string) $programA->getKey();
    $idB = (string) $programB->getKey();
    $versionIdA = (string) $versionA->getKey();
    $versionIdB = (string) $versionB->getKey();

    OwnerContext::withOwner($ownerA, static function () use ($programs, $idA, $idB, $versionIdA, $versionIdB): void {
        expect($programs->findPolicy($idA, $versionIdA)?->id)->toBe($versionIdA)
            ->and($programs->findPolicy($idA, $versionIdB))->toBeNull()
            ->and(fn () => $programs->findPolicy($idB, $versionIdB))->toThrow(AuthorizationException::class);
    });
});

it('refuses foreign payout snapshots and benefit recovery queries', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $program = OwnerContext::withOwner($owner, static fn (): RewardProgram => RewardProgram::factory()->create());
    $award = RewardAward::factory()->create(['program_id' => $program->getKey()]);
    $payout = RewardPayoutRequest::factory()->create([
        'program_id' => $program->getKey(),
        'award_id' => $award->getKey(),
    ]);
    $item = RewardBenefitItem::factory()->create(['program_id' => $program->getKey()]);
    $grant = RewardBenefitGrant::factory()->create([
        'item_id' => $item->getKey(),
        'award_id' => $award->getKey(),
        'status' => RewardBenefitGrantStatus::Consumed,
    ]);
    $awardId = (string) $award->getKey();
    $grantId = (string) $grant->getKey();
    $payoutQuery = app(RewardPayoutQueryApi::class);
    $benefitQuery = app(RewardBenefitRecoveryApi::class);

    OwnerContext::withOwner($owner, static function () use ($payoutQuery, $benefitQuery, $awardId, $grantId, $payout): void {
        expect($payoutQuery->forAward($awardId)?->id)->toBe((string) $payout->getKey());

        DB::transaction(static function () use ($benefitQuery, $awardId, $grantId): void {
            expect($benefitQuery->consumedGrantsForReversal($awardId))->toHaveCount(1);
            $benefitQuery->assertGrantBelongsToAward($grantId, $awardId);
        });
    });

    OwnerContext::withOwner($other, static function () use ($payoutQuery, $benefitQuery, $awardId, $grantId): void {
        expect(fn () => $payoutQuery->forAward($awardId))->toThrow(AuthorizationException::class)
            ->and(fn () => DB::transaction(fn () => $benefitQuery->consumedGrantsForReversal($awardId)))->toThrow(AuthorizationException::class)
            ->and(fn () => DB::transaction(fn () => $benefitQuery->assertGrantBelongsToAward($grantId, $awardId)))->toThrow(AuthorizationException::class);
    });
});

it('refuses to lock awards from another owner when resolving refund reversal status', function (): void {
    [$ownerA, $ownerB, , , , $award] = guardCrossTenantFixture('guard-refund-award-lock');
    $awardId = (string) $award->getKey();
    $awards = app(RewardAwardLockApi::class);

    expect(OwnerContext::withOwner(
        $ownerA,
        static fn (): array => DB::transaction(static fn (): array => $awards->lockAndFindReversedAwards([$awardId, $awardId])),
    ))->toBe([]);

    expect(fn () => OwnerContext::withOwner(
        $ownerB,
        static fn (): array => DB::transaction(static fn (): array => $awards->lockAndFindReversedAwards([$awardId])),
    ))->toThrow(AuthorizationException::class);

    expect($award->fresh()->status)->toBe(RewardAwardStatus::Fulfilled);
});

it('refuses new refunds and replay of refunds from a foreign program', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();
    $recipient = (string) Str::uuid();
    $at = CarbonImmutable::parse('2026-06-21T00:00:00Z');

    $program = OwnerContext::withOwner($ownerA, static fn (): RewardProgram => RewardProgram::factory()->create());

    OwnerContext::withOwner($ownerA, static function () use ($program, $recipient, $at): void {
        CreditRewardPointsAction::run(
            'user', $recipient, $program->family_key, 'pts', 50, $at,
            null, null, (string) $program->getKey(), 'credit:scope-refund-replay',
        );
        DebitRewardPointsAction::run('user', $recipient, $program->family_key, 'pts', 20, 'debit:scope-refund-replay', 'spend', null, $at);
        RefundRewardPointsAction::run('refund:scope-refund-replay', 'debit:scope-refund-replay', 10, 'return', $at);
    });

    $lotCount = RewardPointLot::query()->count();

    foreach (['refund:scope-refund-replay', 'refund:scope-refund-foreign-new'] as $key) {
        expect(fn () => OwnerContext::withOwner(
            $ownerB,
            static fn (): array => RefundRewardPointsAction::run($key, 'debit:scope-refund-replay', 10, 'return', $at),
        ))->toThrow(AuthorizationException::class);
    }

    expect(RewardPointLot::query()->count())->toBe($lotCount);

    $replay = OwnerContext::withOwner(
        $ownerA,
        static fn (): array => RefundRewardPointsAction::run('refund:scope-refund-replay', 'debit:scope-refund-replay', 10, 'return', $at),
    );

    expect($replay)->toHaveCount(1)
        ->and(RewardPointLot::query()->count())->toBe($lotCount);
});

it('refuses spending reserving capturing releasing and replaying foreign program points', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $recipient = (string) Str::uuid();
    $at = CarbonImmutable::parse('2026-06-21T00:00:00Z');
    $program = OwnerContext::withOwner($owner, static fn (): RewardProgram => RewardProgram::factory()->create());
    $family = $program->family_key;

    OwnerContext::withOwner($owner, static function () use ($program, $recipient, $at, $family): void {
        CreditRewardPointsAction::run('user', $recipient, $family, 'pts', 100, $at, null, null, (string) $program->getKey(), 'credit:owner-ledger');
        DebitRewardPointsAction::run('user', $recipient, $family, 'pts', 10, 'debit:owner-ledger', 'spend', null, $at);
        ReserveRewardPointsAction::run('user', $recipient, $family, 'pts', 20, 'reserve:owner-ledger', 'hold', $at);
    });

    $before = RewardPointMovement::query()->count();

    OwnerContext::withOwner($other, static function () use ($recipient, $family, $at): void {
        foreach (['debit:owner-ledger', 'debit:foreign-ledger'] as $key) {
            expect(fn () => DebitRewardPointsAction::run('user', $recipient, $family, 'pts', 10, $key, 'spend', null, $at))
                ->toThrow(AuthorizationException::class);
        }

        foreach (['reserve:owner-ledger', 'reserve:foreign-ledger'] as $key) {
            expect(fn () => ReserveRewardPointsAction::run('user', $recipient, $family, 'pts', 20, $key, 'hold', $at))
                ->toThrow(AuthorizationException::class);
        }

        expect(fn () => DebitRewardPointsAction::run('user', $recipient, $family, 'pts', 20, 'capture:foreign-ledger', 'capture', 'reserve:owner-ledger', $at))
            ->toThrow(AuthorizationException::class)
            ->and(fn () => ReleaseRewardPointsAction::run('reserve:owner-ledger', 'release', $at))
            ->toThrow(AuthorizationException::class);
    });

    expect(RewardPointMovement::query()->count())->toBe($before);

    expect(OwnerContext::withOwner($owner, static fn (): array => ReleaseRewardPointsAction::run('reserve:owner-ledger', 'release', $at)))
        ->toHaveCount(1);

    expect(fn () => OwnerContext::withOwner($other, static fn (): array => ReleaseRewardPointsAction::run('reserve:owner-ledger', 'release', $at)))
        ->toThrow(AuthorizationException::class);
});
