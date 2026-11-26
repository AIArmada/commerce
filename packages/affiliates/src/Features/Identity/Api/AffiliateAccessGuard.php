<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Api;

interface AffiliateAccessGuard
{
    public function assertConnection(int $connectionIdentity): void;

    public function assertCanWrite(string $affiliateId, int $connectionIdentity): void;

    public function lockForWrite(string $affiliateId, int $connectionIdentity): void;
}
