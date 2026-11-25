<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Actions;

use AIArmada\Affiliates\Features\Identity\Api\AffiliateRankMaintenance;
use AIArmada\Affiliates\Models\Affiliate;
use LogicException;

final readonly class DetachAffiliateRank implements AffiliateRankMaintenance
{
    public function detachRank(string $rankId): void
    {
        if ((new Affiliate)->getConnection()->transactionLevel() === 0) {
            throw new LogicException('Rank detachment requires an active removal transaction.');
        }

        Affiliate::query()->forOwner()->where('rank_id', $rankId)->update(['rank_id' => null]);
    }
}
