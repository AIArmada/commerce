<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Support;

use AIArmada\Affiliates\Features\Upline\Models\AffiliateRank;
use AIArmada\Affiliates\Features\Identity\Models\Affiliate;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

final class RankLabelProjection
{
    /** @return Builder<AffiliateRank> */
    public static function query(): Builder
    {
        $affiliate = new Affiliate;
        $rank = new AffiliateRank;

        if ($affiliate->getConnection() !== $rank->getConnection()) {
            throw new LogicException('Rank label projection requires a shared database connection.');
        }

        $query = AffiliateRank::query()->select($rank->qualifyColumn('name'))
            ->whereColumn($rank->qualifyColumn('id'), $affiliate->qualifyColumn('rank_id'));

        if (config('affiliates.owner.enabled', false)) {
            foreach (['owner_type', 'owner_id'] as $column) {
                $rankColumn = $rank->qualifyColumn($column);
                $affiliateColumn = $affiliate->qualifyColumn($column);

                $query->where(static fn (Builder $scope) => $scope->whereColumn($rankColumn, $affiliateColumn)
                    ->orWhere(static fn (Builder $global) => $global->whereNull($rankColumn)->whereNull($affiliateColumn)));
            }
        }

        return $query;
    }
}
