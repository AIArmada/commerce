<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Attribution\Api;

use AIArmada\Affiliates\Data\AffiliateAttributionData;

interface AttributionLookup
{
    public function affiliateIdForCookie(string $cookieValue): ?string;

    public function attached(string $identifier, string $instance): ?AffiliateAttributionData;
}
