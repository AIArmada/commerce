<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Support;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Rewards\Models\RewardAppeal;
use AIArmada\Rewards\Models\RewardAward;
use AIArmada\Rewards\Models\RewardBenefitGrant;
use AIArmada\Rewards\Models\RewardBenefitItem;
use AIArmada\Rewards\Models\RewardEnrollment;
use AIArmada\Rewards\Models\RewardMilestoneContribution;
use AIArmada\Rewards\Models\RewardPayoutRequest;
use AIArmada\Rewards\Models\RewardProgram;
use AIArmada\Rewards\Models\RewardRecovery;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

/**
 * Program-boundary write guard: the program is the tenant boundary, and
 * every ledger read/write resolves it first. Ledger tables carry no owner
 * columns; tenancy flows program -> enrollment/award joins, so inbound
 * program and award IDs are validated against the current owner scope
 * before any attach or update.
 */
final class RewardProgramGuard
{
    /**
     * Resolve the program for the current owner scope, including global
     * (platform) programs. Unknown ids throw for a missing program;
     * existing programs outside the scope throw for the scope. When
     * owner scoping is disabled the guard degrades to a plain lookup:
     * single-tenant hosts store owner tuples as data without enforcement.
     */
    public static function findProgramOrFail(RewardProgram | string $program): RewardProgram
    {
        $id = $program instanceof RewardProgram ? (string) $program->getKey() : $program;

        if (! config('rewards.owner.enabled', true)) {
            $plain = RewardProgram::query()->whereKey($id)->first();

            if (! $plain instanceof RewardProgram) {
                throw new InvalidArgumentException('Unknown reward program.');
            }

            return $plain;
        }

        if (RewardProgram::query()->withoutGlobalScopes()->whereKey($id)->doesntExist()) {
            throw new InvalidArgumentException('Unknown reward program.');
        }

        $resolved = OwnerWriteGuard::findOrFailForOwner(RewardProgram::class, $id, includeGlobal: true);

        assert($resolved instanceof RewardProgram);

        return $resolved;
    }

    /**
     * Run a system-context unit of work under the program's own owner.
     *
     * Jobs, sweeps, and commands run without an ambient owner (queue
     * workers, cron) yet must act on every active program. This helper
     * resolves the program row ignoring the ambient scope, enters the
     * program's own owner context — or an explicit-global context for
     * platform programs with a null owner tuple — and restores the
     * previous context afterwards. Ownership stays the security boundary:
     * the callback still sees guarded reads/writes, now under the correct
     * owner instead of a fail-open ambient scope.
     *
     * An unknown program id throws InvalidArgumentException; a program
     * whose owner row was deleted throws InvalidArgumentException naming
     * the orphaned tuple, so queue retries/dead-letter surface the
     * corruption instead of silently skipping the work.
     */
    public static function withProgramOwner(RewardProgram | string $program, callable $callback): mixed
    {
        if (! config('rewards.owner.enabled', true)) {
            return $callback();
        }

        $id = $program instanceof RewardProgram ? (string) $program->getKey() : $program;

        $resolved = RewardProgram::query()->withoutGlobalScopes()->whereKey($id)->first();

        if (! $resolved instanceof RewardProgram) {
            throw new InvalidArgumentException('Unknown reward program.');
        }

        try {
            $owner = OwnerContext::fromTypeAndIdOrFail($resolved->owner_type, $resolved->owner_id);
        } catch (ModelNotFoundException) {
            throw new InvalidArgumentException(sprintf('Reward program "%s" references a deleted owner.', $id));
        }

        return OwnerContext::withOwner($owner, $callback);
    }

    /**
     * Resolve the award and the program it belongs to, proving both the
     * award exists and its program is in the current owner scope.
     */
    public static function findAwardOrFail(RewardAward | string $award): RewardAward
    {
        $id = $award instanceof RewardAward ? (string) $award->getKey() : $award;

        $resolved = RewardAward::query()->whereKey($id)->first();

        if (! $resolved instanceof RewardAward) {
            throw new InvalidArgumentException('Unknown reward award.');
        }

        self::findProgramOrFail((string) $resolved->program_id);

        return $resolved;
    }

    /**
     * Resolve the payout request and prove its program is in the current
     * owner scope.
     */
    public static function findPayoutRequestOrFail(RewardPayoutRequest | string $request): RewardPayoutRequest
    {
        $id = $request instanceof RewardPayoutRequest ? (string) $request->getKey() : $request;

        $resolved = RewardPayoutRequest::query()->whereKey($id)->first();

        if (! $resolved instanceof RewardPayoutRequest) {
            throw new InvalidArgumentException('Unknown reward payout request.');
        }

        self::findProgramOrFail((string) $resolved->program_id);

        return $resolved;
    }

    /**
     * Resolve the benefit grant and prove its catalog item's program is in
     * the current owner scope.
     */
    public static function findGrantOrFail(RewardBenefitGrant | string $grant): RewardBenefitGrant
    {
        $id = $grant instanceof RewardBenefitGrant ? (string) $grant->getKey() : $grant;

        $resolved = RewardBenefitGrant::query()->whereKey($id)->first();

        if (! $resolved instanceof RewardBenefitGrant) {
            throw new InvalidArgumentException('Unknown reward benefit grant.');
        }

        self::findItemOrFail((string) $resolved->item_id);

        return $resolved;
    }

    /**
     * Resolve the catalog item and prove its program is in the current
     * owner scope.
     */
    public static function findItemOrFail(RewardBenefitItem | string $item): RewardBenefitItem
    {
        $id = $item instanceof RewardBenefitItem ? (string) $item->getKey() : $item;

        $resolved = RewardBenefitItem::query()->whereKey($id)->first();

        if (! $resolved instanceof RewardBenefitItem) {
            throw new InvalidArgumentException('Unknown reward benefit item.');
        }

        self::findProgramOrFail((string) $resolved->program_id);

        return $resolved;
    }

    /**
     * Scope-check an award attach reference only when the row exists.
     * Dangling references stay allowed for append-only annotations.
     */
    public static function assertAwardInScopeWhenExists(string $awardId): void
    {
        if (RewardAward::query()->whereKey($awardId)->exists()) {
            self::findAwardOrFail($awardId);
        }
    }

    /**
     * Scope-check an appeal attach reference only when the row exists.
     * Dangling references stay allowed for append-only annotations.
     */
    public static function assertAppealInScopeWhenExists(string $appealId): void
    {
        if (RewardAppeal::query()->whereKey($appealId)->exists()) {
            self::findAppealOrFail($appealId);
        }
    }

    /**
     * Resolve the appeal and prove its award's program is in the current
     * owner scope.
     */
    public static function findAppealOrFail(RewardAppeal | string $appeal): RewardAppeal
    {
        $id = $appeal instanceof RewardAppeal ? (string) $appeal->getKey() : $appeal;

        $resolved = RewardAppeal::query()->whereKey($id)->first();

        if (! $resolved instanceof RewardAppeal) {
            throw new InvalidArgumentException('Unknown reward appeal.');
        }

        self::findAwardOrFail((string) $resolved->award_id);

        return $resolved;
    }

    /**
     * Resolve the enrollment and prove its program is in the current
     * owner scope.
     */
    public static function findEnrollmentOrFail(RewardEnrollment | string $enrollment): RewardEnrollment
    {
        $id = $enrollment instanceof RewardEnrollment ? (string) $enrollment->getKey() : $enrollment;

        $resolved = RewardEnrollment::query()->whereKey($id)->first();

        if (! $resolved instanceof RewardEnrollment) {
            throw new InvalidArgumentException('Unknown reward enrollment.');
        }

        self::findProgramOrFail((string) $resolved->program_id);

        return $resolved;
    }

    /**
     * Resolve the recovery and prove its award's program is in the
     * current owner scope.
     */
    public static function findRecoveryOrFail(RewardRecovery | string $recovery): RewardRecovery
    {
        $id = $recovery instanceof RewardRecovery ? (string) $recovery->getKey() : $recovery;

        $resolved = RewardRecovery::query()->whereKey($id)->first();

        if (! $resolved instanceof RewardRecovery) {
            throw new InvalidArgumentException('Unknown reward recovery.');
        }

        self::findAwardOrFail((string) $resolved->award_id);

        return $resolved;
    }

    /**
     * Resolve the contribution and prove its cycle's program is in the
     * current owner scope. A cycle without a program throws — a
     * program-less contribution must fail closed, never resolve against
     * whatever ambient scope happens to exist.
     */
    public static function findContributionOrFail(RewardMilestoneContribution | string $contribution): RewardMilestoneContribution
    {
        $id = $contribution instanceof RewardMilestoneContribution ? (string) $contribution->getKey() : $contribution;

        $resolved = RewardMilestoneContribution::query()->whereKey($id)->first();

        if (! $resolved instanceof RewardMilestoneContribution) {
            throw new InvalidArgumentException('Unknown reward milestone contribution.');
        }

        $programId = $resolved->cycle?->program_id;

        if ($programId === null) {
            throw new InvalidArgumentException('Milestone contribution references no program.');
        }

        self::findProgramOrFail((string) $programId);

        return $resolved;
    }

    /**
     * Scope-check a program attach reference only when the row exists.
     * Dangling references stay allowed for append-only annotations.
     */
    public static function assertProgramInScopeWhenExists(string $programId): void
    {
        if (RewardProgram::query()->withoutGlobalScopes()->whereKey($programId)->exists()) {
            self::findProgramOrFail($programId);
        }
    }
}
