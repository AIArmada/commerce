<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Api;

interface AffiliateLifecycle
{
    public function approve(string $affiliateId): bool;

    public function reject(string $affiliateId): bool;

    public function pause(string $affiliateId): bool;

    public function disable(string $affiliateId): bool;
}
