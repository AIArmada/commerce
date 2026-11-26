<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Attribution\Api;

use AIArmada\Affiliates\Data\AffiliateAttributionData;

interface AttributionActivity
{
    /** @param array<string, mixed> $context */
    public function touch(string $cookieValue, array $context = []): ?AffiliateAttributionData;
}
