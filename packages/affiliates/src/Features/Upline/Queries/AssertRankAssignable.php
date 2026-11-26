<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Upline\Queries;

use AIArmada\Affiliates\Features\Upline\Api\RankAssignmentGuard;
use AIArmada\Affiliates\Features\Upline\Models\AffiliateRank;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateAccessGuard;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

final readonly class AssertRankAssignable implements RankAssignmentGuard
{
    public function assertAssignable(string $rankId): void
    {
        $connection = (new AffiliateRank)->getConnection();
        app(AffiliateAccessGuard::class)->assertConnection(spl_object_id($connection));

        if ($connection->transactionLevel() === 0) {
            throw new LogicException('Rank assignment requires a shared active database transaction.');
        }

        if (! Str::isUuid($rankId) || AffiliateRank::query()->forOwner()->lockForUpdate()->find($rankId) === null) {
            throw new InvalidArgumentException('The selected affiliate rank is not accessible.');
        }
    }
}
