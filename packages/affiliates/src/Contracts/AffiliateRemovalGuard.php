<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Contracts;

interface AffiliateRemovalGuard
{
    public function assertCanRemove(string $affiliateId, int $connectionInstanceId): void;
}
