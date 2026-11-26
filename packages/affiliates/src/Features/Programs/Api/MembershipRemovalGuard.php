<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Api;

interface MembershipRemovalGuard
{
    public function assertCanRemove(string $affiliateId, int $connectionIdentity): void;
}
