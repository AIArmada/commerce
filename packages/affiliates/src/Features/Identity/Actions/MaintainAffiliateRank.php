<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Actions;

use AIArmada\Affiliates\Features\Identity\Api\AffiliateRankMaintenance;
use AIArmada\Affiliates\Features\Identity\Queries\GuardAffiliateAccess;
use AIArmada\Affiliates\Features\Upline\Api\RankAssignmentGuard;
use AIArmada\Affiliates\Models\Affiliate;
use InvalidArgumentException;
use LogicException;

final readonly class MaintainAffiliateRank implements AffiliateRankMaintenance
{
    public function assignRank(string $affiliateId, ?string $rankId, int $connectionIdentity): void
    {
        $guard = app(GuardAffiliateAccess::class);
        $guard->assertConnection($connectionIdentity);
        $affiliate = $guard->lockedAffiliate($affiliateId);

        if (! $affiliate->isCommercialPurpose()) {
            throw new InvalidArgumentException('A non-commercial affiliate cannot receive a rank.');
        }

        if ($rankId !== null) {
            app(RankAssignmentGuard::class)->assertAssignable($rankId);
        }

        if ($affiliate->rank_id !== $rankId) {
            $affiliate->update(['rank_id' => $rankId]);
        }
    }

    public function detachRank(string $rankId): void
    {
        if ((new Affiliate)->getConnection()->transactionLevel() === 0) {
            throw new LogicException('Rank detachment requires an active removal transaction.');
        }

        Affiliate::query()->forOwner()->where('rank_id', $rankId)->update(['rank_id' => null]);
    }
}
