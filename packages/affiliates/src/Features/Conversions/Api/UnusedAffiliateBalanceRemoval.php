<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Api;

interface UnusedAffiliateBalanceRemoval
{
    public function remove(string $affiliateId): void;
}
