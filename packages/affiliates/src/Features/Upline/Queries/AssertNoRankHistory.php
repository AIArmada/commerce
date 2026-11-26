<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Upline\Queries;

use AIArmada\Affiliates\Features\Upline\Api\RankHistoryRemovalGuard;
use AIArmada\Affiliates\Features\Upline\Models\AffiliateRankHistory;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class AssertNoRankHistory implements RankHistoryRemovalGuard
{
    public function assertCanRemove(string $affiliateId, int $connectionIdentity): void
    {
        if (spl_object_id((new AffiliateRankHistory)->getConnection()) !== $connectionIdentity) {
            throw new LogicException('Affiliate deletion requires a shared database connection.');
        }

        if (AffiliateRankHistory::query()->withoutGlobalScope('affiliate_owner')->where('affiliate_id', $affiliateId)->exists()) {
            throw ValidationException::withMessages(['affiliate' => 'An affiliate with linked business or historical records cannot be deleted.']);
        }
    }
}
