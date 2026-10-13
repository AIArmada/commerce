<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Support;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Rewards\Models\RewardAppeal;
use AIArmada\Rewards\Models\RewardAward;
use AIArmada\Rewards\Models\RewardBenefitGrant;
use AIArmada\Rewards\Models\RewardBenefitItem;
use AIArmada\Rewards\Models\RewardPayoutRequest;
use AIArmada\Rewards\Models\RewardProgram;
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
}
