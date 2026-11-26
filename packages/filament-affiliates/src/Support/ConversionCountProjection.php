<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Support;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

final class ConversionCountProjection
{
    /** @return Builder<AffiliateConversion> */
    public static function query(): Builder
    {
        $affiliate = new Affiliate;
        $conversion = new AffiliateConversion;

        if ($affiliate->getConnection() !== $conversion->getConnection()) {
            throw new LogicException('Conversion count projection requires a shared database connection.');
        }

        return AffiliateConversion::query()->forOwner(includeGlobal: true)
            ->selectRaw('COUNT(*)')
            ->whereColumn($conversion->qualifyColumn('affiliate_id'), $affiliate->qualifyColumn('id'));
    }
}
