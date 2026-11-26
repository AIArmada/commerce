<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Queries;

use AIArmada\Affiliates\Features\Conversions\Api\ConversionHistoryReader;
use AIArmada\Affiliates\Models\AffiliateConversion;
use LogicException;

final readonly class ReadConversionHistory implements ConversionHistoryReader
{
    public function hasAffiliateHistory(string $affiliateId, int $connectionIdentity): bool
    {
        if (spl_object_id((new AffiliateConversion)->getConnection()) !== $connectionIdentity) {
            throw new LogicException('Conversion history protection requires a shared database connection.');
        }

        return AffiliateConversion::query()->withoutOwnerScope()->where('affiliate_id', $affiliateId)->exists();
    }
}
