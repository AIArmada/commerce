<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Api;

interface ConversionHistoryReader
{
    public function hasSubjectReference(string $identifier, ?string $instance): bool;

    public function hasAffiliateHistory(string $affiliateId, int $connectionIdentity): bool;
}
