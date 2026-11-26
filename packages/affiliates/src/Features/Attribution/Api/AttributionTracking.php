<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Attribution\Api;

use AIArmada\Affiliates\Data\AffiliateAttributionData;

interface AttributionTracking
{
    /** @param array<string, mixed> $context */
    public function track(string $code, array $context = [], ?string $cookieValue = null): ?AffiliateAttributionData;
}
