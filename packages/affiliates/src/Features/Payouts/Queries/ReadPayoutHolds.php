<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Queries;

use AIArmada\Affiliates\Features\Payouts\Api\PayoutHoldReader;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutHold;
use Illuminate\Support\Str;

final readonly class ReadPayoutHolds implements PayoutHoldReader
{
    public function hasActive(string $affiliateId): bool
    {
        return Str::isUuid($affiliateId) && AffiliatePayoutHold::query()->where('affiliate_id', $affiliateId)->active()->exists();
    }

    public function count(string $affiliateId): int
    {
        return Str::isUuid($affiliateId) ? AffiliatePayoutHold::query()->where('affiliate_id', $affiliateId)->count() : 0;
    }

    public function hasRecords(string $affiliateId): bool
    {
        return Str::isUuid($affiliateId) && AffiliatePayoutHold::query()->where('affiliate_id', $affiliateId)->exists();
    }
}
