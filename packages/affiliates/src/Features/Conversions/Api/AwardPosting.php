<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Api;

use AIArmada\Affiliates\Data\AffiliateOutcomeResult;

interface AwardPosting
{
    /** @param array<string, mixed> $attributes */
    public function post(string $affiliateId, string $awardKey, int $amountMinor, string $currency, array $attributes = []): AffiliateOutcomeResult;

    public function reverse(string $conversionId, string $awardKey, string $reason): void;
}
