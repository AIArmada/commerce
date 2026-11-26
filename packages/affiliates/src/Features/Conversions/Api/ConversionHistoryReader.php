<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Api;

interface ConversionHistoryReader
{
    public function hasAffiliateHistory(string $affiliateId, int $connectionIdentity): bool;
}
