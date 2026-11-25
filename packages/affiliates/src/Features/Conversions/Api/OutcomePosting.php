<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Api;

use AIArmada\Affiliates\Data\AffiliateOutcomeResult;

interface OutcomePosting
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $provenance
     */
    public function post(?string $attributionId, string $conversionType, string $businessKey, array $payload = [], ?string $affiliateId = null, array $provenance = []): AffiliateOutcomeResult;
}
