<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Upline\Api;

interface RankHistoryRemovalGuard
{
    public function assertCanRemove(string $affiliateId, int $connectionIdentity): void;
}
