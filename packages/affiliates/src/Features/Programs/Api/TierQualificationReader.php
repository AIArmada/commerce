<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Api;

interface TierQualificationReader
{
    public function qualifies(string $affiliateId, string $programId, string $tierId): bool;
}
