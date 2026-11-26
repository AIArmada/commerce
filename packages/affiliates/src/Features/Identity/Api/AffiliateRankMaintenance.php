<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Api;

interface AffiliateRankMaintenance
{
    public function detachRank(string $rankId): void;

    public function assignRank(string $affiliateId, ?string $rankId, int $connectionIdentity): void;
}
