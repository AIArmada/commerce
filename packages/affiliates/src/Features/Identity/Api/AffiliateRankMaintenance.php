<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Api;

interface AffiliateRankMaintenance
{
    public function detachRank(string $rankId): void;
}
