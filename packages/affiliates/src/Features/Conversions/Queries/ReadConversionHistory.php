<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Queries;

use AIArmada\Affiliates\Features\Conversions\Api\ConversionHistoryReader;
use AIArmada\Affiliates\Features\Conversions\Models\AffiliateConversion;
use LogicException;

final readonly class ReadConversionHistory implements ConversionHistoryReader
{
    public function hasSubjectReference(string $identifier, ?string $instance): bool
    {
        return AffiliateConversion::query()->forOwner(includeGlobal: false)
            ->where('subject_key', $identifier)
            ->when($instance !== null, fn ($query) => $query->where('subject_instance', $instance))
            ->exists();
    }

    public function hasAffiliateHistory(string $affiliateId, int $connectionIdentity): bool
    {
        if (spl_object_id((new AffiliateConversion)->getConnection()) !== $connectionIdentity) {
            throw new LogicException('Conversion history protection requires a shared database connection.');
        }

        return AffiliateConversion::query()->withoutOwnerScope()->where('affiliate_id', $affiliateId)->exists();
    }
}
